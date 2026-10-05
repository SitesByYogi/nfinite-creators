<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Shared commerce resolver for legacy Creator playlist rows and nfinite_track.
 *
 * WooCommerce is optional. A direct Buy URL works without WooCommerce.
 */
class Nfinite_Creators_Track_Commerce {

	/**
	 * Register WooCommerce compatibility hooks for Nfinite-generated beat products.
	 */
	public static function init() {
		add_filter( 'woocommerce_product_class', array( __CLASS__, 'force_beat_product_class' ), 20, 4 );
	}

	/**
	 * Nfinite beat products are always variable products because the purchasable
	 * unit is a license. Force WooCommerce to instantiate the correct class even
	 * when a persistent/object cache still remembers the product's old simple
	 * product type from before Creator Commerce converted it.
	 */
	public static function force_beat_product_class( $classname, $product_type, $post_type, $product_id ) {
		$product_id = absint( $product_id );

		if (
			$product_id &&
			'product' === $post_type &&
			'beat' === get_post_meta( $product_id, '_nfinite_creator_product_type', true )
		) {
			return 'WC_Product_Variable';
		}

		return $classname;
	}

	public static function products() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		return wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => -1,
				'orderby' => 'title',
				'order'   => 'ASC',
				'return'  => 'objects',
			)
		);
	}

	/**
	 * Return a concise, visible price string for Creator UI.
	 *
	 * WooCommerce's get_price_html() intentionally contains accessibility-only
	 * screen-reader text such as "Price range: $150 through $300". Stripping
	 * the HTML turns that hidden text into visible duplicate copy, so Creator
	 * surfaces should never derive display text by wp_strip_all_tags().
	 */
	public static function price_text( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}

		if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_price' ) ) {
			$min = (float) $product->get_variation_price( 'min', true );
			$max = (float) $product->get_variation_price( 'max', true );

			if ( $min > 0 && $max > 0 ) {
				$min_text = html_entity_decode( wp_strip_all_tags( wc_price( $min ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
				$max_text = html_entity_decode( wp_strip_all_tags( wc_price( $max ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
				return abs( $max - $min ) > 0.00001 ? $min_text . ' – ' . $max_text : $min_text;
			}
		}

		$price = (float) $product->get_price();
		if ( $price <= 0 ) {
			return '';
		}

		return html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
	}

	public static function resolve( $data ) {
		$data = is_array( $data ) ? $data : array();

		$product_id = isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0;
		$buy_url    = isset( $data['buy_url'] ) ? esc_url_raw( $data['buy_url'] ) : '';
		$buy_label  = isset( $data['buy_label'] ) ? sanitize_text_field( $data['buy_label'] ) : '';
		$show_price = ! empty( $data['show_price'] );

		$result = array(
			'product_id' => $product_id,
			'url'        => '',
			'label'      => $buy_label ?: __( 'Buy Now', 'nfinite-creators' ),
			'price_html' => '',
			'price_text' => '',
			'available'  => false,
			'licenses'   => array(),
		);

		if ( $product_id && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );

			if ( $product && 'publish' === get_post_status( $product_id ) ) {
				$result['url']       = get_permalink( $product_id );
				$result['available'] = true;

				if ( $show_price ) {
					$result['price_html'] = $product->get_price_html();
					$result['price_text'] = self::price_text( $product );
				}

				return apply_filters( 'nfinite_track_commerce', $result, $data );
			}
		}

		if ( $buy_url ) {
			$result['url']       = $buy_url;
			$result['available'] = true;
		}

		return apply_filters( 'nfinite_track_commerce', $result, $data );
	}

	public static function legacy_track( $track ) {
		$track = is_array( $track ) ? $track : array();
		$result = self::resolve(
			array(
				'product_id' => isset( $track['product_id'] ) ? $track['product_id'] : 0,
				'buy_url'    => isset( $track['buy_url'] ) ? $track['buy_url'] : '',
				'buy_label'  => isset( $track['buy_label'] ) ? $track['buy_label'] : '',
				'show_price' => ! empty( $track['show_price'] ),
			)
		);
		$result['licenses'] = array();
		$labels = array( 'mp3' => 'MP3 Lease', 'wav' => 'WAV Lease', 'trackout' => 'Trackout', 'unlimited' => 'Unlimited', 'exclusive' => 'Exclusive' );
		if ( ! empty( $track['sell_beat'] ) && ! empty( $track['licenses'] ) && is_array( $track['licenses'] ) ) {
			foreach ( $track['licenses'] as $key => $price ) {
				if ( isset( $labels[ $key ] ) && (float) $price > 0 ) {
					$result['licenses'][ $key ] = array( 'label' => $labels[ $key ], 'price' => (float) $price );
				}
			}
		}
		return $result;
	}

	public static function nfinite_track( $track_id ) {
		return self::resolve(
			array(
				'product_id' => get_post_meta( $track_id, '_nfinite_track_product_id', true ),
				'buy_url'    => get_post_meta( $track_id, '_nfinite_track_buy_url', true ),
				'buy_label'  => get_post_meta( $track_id, '_nfinite_track_buy_label', true ),
				'show_price' => '1' === get_post_meta( $track_id, '_nfinite_track_show_price', true ),
			)
		);
	}

	/**
	 * Create/update one variable WooCommerce product for a creator beat.
	 * Each enabled license is represented as a variation.
	 */
	public static function sync_beat_product( $creator_id, $track, $existing_product_id = 0 ) {
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Product_Variable' ) || ! class_exists( 'WC_Product_Variation' ) ) {
			return absint( $existing_product_id );
		}

		$track = is_array( $track ) ? $track : array();
		$raw_licenses = isset( $track['licenses'] ) && is_array( $track['licenses'] ) ? $track['licenses'] : array();
		$licenses = array();

		// A blank or 0.00 price means the creator is not offering that license.
		foreach ( $raw_licenses as $key => $price ) {
			$price = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $price ) : preg_replace( '/[^0-9.]/', '', (string) $price );
			if ( '' !== $price && (float) $price > 0 ) {
				$licenses[ sanitize_key( $key ) ] = $price;
			}
		}

		if ( empty( $licenses ) ) {
			return absint( $existing_product_id );
		}

		$title = isset( $track['title'] ) && $track['title'] ? sanitize_text_field( $track['title'] ) : __( 'Untitled Beat', 'nfinite-creators' );
		$product_id = absint( $existing_product_id );

		/*
		 * Preserve an existing beat product URL whenever possible. Older Nfinite
		 * versions linked Creator tracks to simple WooCommerce products. Convert
		 * that product in place instead of silently creating a second product.
		 */
		if ( $product_id && 'product' === get_post_type( $product_id ) ) {
			wp_set_object_terms( $product_id, 'variable', 'product_type' );
			wc_delete_product_transients( $product_id );
			$product = new WC_Product_Variable( $product_id );
		} else {
			$product = new WC_Product_Variable();
		}

		$product->set_name( $title );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_virtual( true );
		$product->set_description( sprintf( __( 'Choose the license that fits your use of %s.', 'nfinite-creators' ), $title ) );
		$product->set_short_description( __( 'Select a beat license below to continue.', 'nfinite-creators' ) );

		$labels = array(
			'mp3'       => 'MP3 Lease',
			'wav'       => 'WAV Lease',
			'trackout'  => 'Trackout',
			'unlimited' => 'Unlimited',
			'exclusive' => 'Exclusive',
		);

		$options = array();
		foreach ( array_keys( $licenses ) as $key ) {
			if ( isset( $labels[ $key ] ) ) {
				$options[] = $labels[ $key ];
			}
		}

		$attribute = new WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( 'License' );
		$attribute->set_options( $options );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$product->set_attributes( array( $attribute ) );

		$product_id = $product->save();
		update_post_meta( $product_id, '_nfinite_creator_id', absint( $creator_id ) );
		update_post_meta( $product_id, '_nfinite_creator_product_type', 'beat' );
		update_post_meta( $product_id, '_wpnfinite_music_product', 'yes' );
		$delivery_assets = isset( $track['delivery_assets'] ) && is_array( $track['delivery_assets'] ) ? array_map( 'esc_url_raw', $track['delivery_assets'] ) : array();
		update_post_meta( $product_id, '_nfinite_beat_delivery_assets', $delivery_assets );

		if ( ! empty( $track['audio_url'] ) ) {
			update_post_meta( $product_id, '_wpnfinite_audio_preview', esc_url_raw( $track['audio_url'] ) );
		}
		if ( ! empty( $track['cover_url'] ) && function_exists( 'attachment_url_to_postid' ) ) {
			$image_id = attachment_url_to_postid( esc_url_raw( $track['cover_url'] ) );
			if ( $image_id ) {
				$product->set_image_id( $image_id );
				$product->save();
			}
		}

		// Rebuild variations so Creator Studio is always the source of truth.
		$product = wc_get_product( $product_id );
		if ( $product && $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				wp_delete_post( $child_id, true );
			}
		}

		foreach ( $licenses as $key => $price ) {
			if ( ! isset( $labels[ $key ] ) ) {
				continue;
			}

			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			$variation->set_attributes( array( 'license' => sanitize_title( $labels[ $key ] ) ) );
			$variation->set_regular_price( wc_format_decimal( $price ) );
			$variation->set_price( wc_format_decimal( $price ) );
			$variation->set_virtual( true );
			$downloads = array();
			$asset_map = array(
				'mp3'       => array( 'mp3' ),
				'wav'       => array( 'mp3', 'wav' ),
				'trackout'  => array( 'mp3', 'wav', 'trackout' ),
				'unlimited' => array( 'mp3', 'wav', 'trackout' ),
				'exclusive' => array( 'mp3', 'wav', 'trackout' ),
			);
			if ( class_exists( 'WC_Product_Download' ) && isset( $asset_map[ $key ] ) ) {
				foreach ( $asset_map[ $key ] as $asset_key ) {
					if ( empty( $delivery_assets[ $asset_key ] ) ) { continue; }
					$download = new WC_Product_Download();
					$download->set_id( md5( $product_id . '|' . $key . '|' . $asset_key . '|' . $delivery_assets[ $asset_key ] ) );
					$download->set_name( $title . ' — ' . strtoupper( $asset_key ) );
					$download->set_file( $delivery_assets[ $asset_key ] );
					$downloads[ $download->get_id() ] = $download;
				}
			}
			$variation->set_downloadable( ! empty( $downloads ) );
			if ( $downloads ) { $variation->set_downloads( $downloads ); }
			$variation->set_manage_stock( false );
			$variation->set_stock_status( get_post_meta( $product_id, '_nfinite_exclusive_order_id', true ) ? 'outofstock' : 'instock' );
			$variation->set_status( 'publish' );
			$variation_id = $variation->save();
			update_post_meta( $variation_id, '_nfinite_license_key', $key );
		}

		WC_Product_Variable::sync( $product_id );
		WC_Product_Variable::sync_stock_status( $product_id );
		if ( get_post_meta( $product_id, '_nfinite_exclusive_order_id', true ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) { $product->set_stock_status( 'outofstock' ); $product->save(); }
		}
		wc_delete_product_transients( $product_id );
		clean_post_cache( $product_id );

		return absint( $product_id );
	}

}
