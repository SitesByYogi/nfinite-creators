<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Profile {
	public static function creator_type_label( $creator_id ) {
		if ( 'nfinite_creator' !== get_post_type( $creator_id ) ) {
			return __( 'Creator', 'nfinite-creators' );
		}

		$terms = wp_get_object_terms(
			$creator_id,
			'nfinite_creator_type',
			array(
				'orderby' => 'name',
				'order'   => 'ASC',
			)
		);

		if ( ! $terms || is_wp_error( $terms ) ) {
			return __( 'Creator', 'nfinite-creators' );
		}

		return implode( ' • ', wp_list_pluck( $terms, 'name' ) );
	}

	public static function has_profile_audio( $creator_id ) {
		$tracks = get_post_meta( $creator_id, '_nfinite_creator_tracks', true );
		return is_array( $tracks ) && ! empty( $tracks );
	}

	public static function has_discography( $creator_id ) {
		return ! empty( Nfinite_Creators_Music_Query::creator_releases( $creator_id, 1 ) );
	}

	public static function related_creators_enabled( $creator_id ) {
		$value = get_post_meta( $creator_id, '_nfinite_creator_related_enabled', true );

		/*
		 * Existing profiles default to discovery enabled until an administrator
		 * explicitly disables it.
		 */
		return '' === $value || '1' === $value;
	}

	public static function related_creators( $creator_id ) {
		$creator_id = absint( $creator_id );

		if (
			! $creator_id ||
			'nfinite_creator' !== get_post_type( $creator_id ) ||
			! self::related_creators_enabled( $creator_id )
		) {
			return array();
		}

		$mode = sanitize_key(
			(string) get_post_meta( $creator_id, '_nfinite_creator_related_mode', true )
		);

		if ( ! in_array( $mode, array( 'automatic', 'manual', 'hybrid' ), true ) ) {
			$mode = 'hybrid';
		}

		$limit = absint( get_post_meta( $creator_id, '_nfinite_creator_related_limit', true ) );
		$limit = $limit ? max( 1, min( 6, $limit ) ) : 4;

		$manual_ids = get_post_meta( $creator_id, '_nfinite_creator_related_ids', true );
		if ( ! is_array( $manual_ids ) ) {
			$manual_ids = array();
		}

		$manual_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $manual_ids ),
					static function ( $id ) use ( $creator_id ) {
						return $id &&
							$id !== $creator_id &&
							'nfinite_creator' === get_post_type( $id ) &&
							'publish' === get_post_status( $id );
					}
				)
			)
		);

		$related = array();

		if ( in_array( $mode, array( 'manual', 'hybrid' ), true ) ) {
			foreach ( $manual_ids as $manual_id ) {
				$related[] = $manual_id;
				if ( count( $related ) >= $limit ) {
					break;
				}
			}
		}

		if ( 'manual' === $mode || count( $related ) >= $limit ) {
			return array_slice( $related, 0, $limit );
		}

		$term_ids = wp_get_object_terms(
			$creator_id,
			'nfinite_creator_type',
			array( 'fields' => 'ids' )
		);

		if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
			return array_slice( $related, 0, $limit );
		}

		$exclude = array_merge( array( $creator_id ), $related );

		$automatic_ids = get_posts(
			array(
				'post_type'              => 'nfinite_creator',
				'post_status'            => 'publish',
				'posts_per_page'         => $limit - count( $related ),
				'post__not_in'           => $exclude,
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => 'nfinite_creator_type',
						'field'    => 'term_id',
						'terms'    => array_map( 'absint', $term_ids ),
						'operator' => 'IN',
					),
				),
			)
		);

		foreach ( $automatic_ids as $automatic_id ) {
			$automatic_id = absint( $automatic_id );
			if ( $automatic_id && ! in_array( $automatic_id, $related, true ) ) {
				$related[] = $automatic_id;
			}
		}

		return array_slice( $related, 0, $limit );
	}

}
