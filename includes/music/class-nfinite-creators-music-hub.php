<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Music_Hub {
	const OPTION = 'nfinite_music_hub_settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_shortcode( 'nfinite_music_hub', array( __CLASS__, 'shortcode' ) );
	}

	public static function defaults() {
		return array(
			'eyebrow'             => __( 'Listen', 'nfinite-creators' ),
			'title'               => __( 'Music', 'nfinite-creators' ),
			'intro'               => __( 'Discover new releases, tracks, beats, videos, and creators from our community.', 'nfinite-creators' ),
			'featured_release_id' => 0,
			'new_release_count'   => 8,
			'latest_track_count'  => 10,
			'beat_count'          => 8,
			'creator_count'       => 4,
			'video_count'         => 3,
			'show_featured'       => 1,
			'show_releases'       => 1,
			'show_tracks'         => 1,
			'show_beats'          => 1,
			'show_creators'       => 1,
			'show_videos'         => 1,
		);
	}

	public static function settings() {
		return wp_parse_args(
			get_option( self::OPTION, array() ),
			self::defaults()
		);
	}

	public static function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=nfinite_release',
			__( 'Music Hub', 'nfinite-creators' ),
			__( 'Music Hub', 'nfinite-creators' ),
			'manage_options',
			'nfinite-music-hub',
			array( __CLASS__, 'settings_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'nfinite_music_hub_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function sanitize_settings( $raw ) {
		$defaults = self::defaults();
		$raw      = is_array( $raw ) ? $raw : array();

		$clean = array(
			'eyebrow' => isset( $raw['eyebrow'] ) ? sanitize_text_field( $raw['eyebrow'] ) : $defaults['eyebrow'],
			'title'   => isset( $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : $defaults['title'],
			'intro'   => isset( $raw['intro'] ) ? sanitize_textarea_field( $raw['intro'] ) : $defaults['intro'],
		);

		$clean['featured_release_id'] = isset( $raw['featured_release_id'] ) ? absint( $raw['featured_release_id'] ) : 0;

		foreach ( array(
			'new_release_count'  => array( 1, 24, 8 ),
			'latest_track_count' => array( 1, 30, 10 ),
			'beat_count'         => array( 1, 24, 8 ),
			'creator_count'      => array( 1, 12, 4 ),
			'video_count'        => array( 1, 9, 3 ),
		) as $key => $limits ) {
			$value = isset( $raw[ $key ] ) ? absint( $raw[ $key ] ) : $limits[2];
			$clean[ $key ] = max( $limits[0], min( $limits[1], $value ) );
		}

		foreach ( array(
			'show_featured',
			'show_releases',
			'show_tracks',
			'show_beats',
			'show_creators',
			'show_videos',
		) as $key ) {
			$clean[ $key ] = ! empty( $raw[ $key ] ) ? 1 : 0;
		}

		return $clean;
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::settings();

		$releases = get_posts(
			array(
				'post_type'      => 'nfinite_release',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		?>
		<div class="wrap nfinite-music-hub-settings">
			<h1><?php esc_html_e( 'Nfinite Music Hub', 'nfinite-creators' ); ?></h1>
			<p><?php esc_html_e( 'Configure the dynamic /music/ discovery experience. Sections automatically pull from published Nfinite Creators, Releases, Tracks, Beats, and profile videos.', 'nfinite-creators' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'nfinite_music_hub_group' ); ?>

				<h2><?php esc_html_e( 'Hub Introduction', 'nfinite-creators' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="nfinite-music-hub-eyebrow"><?php esc_html_e( 'Eyebrow', 'nfinite-creators' ); ?></label></th>
						<td><input id="nfinite-music-hub-eyebrow" class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[eyebrow]" value="<?php echo esc_attr( $settings['eyebrow'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="nfinite-music-hub-title"><?php esc_html_e( 'Title', 'nfinite-creators' ); ?></label></th>
						<td><input id="nfinite-music-hub-title" class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[title]" value="<?php echo esc_attr( $settings['title'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="nfinite-music-hub-intro"><?php esc_html_e( 'Intro', 'nfinite-creators' ); ?></label></th>
						<td><textarea id="nfinite-music-hub-intro" class="large-text" rows="3" name="<?php echo esc_attr( self::OPTION ); ?>[intro]"><?php echo esc_textarea( $settings['intro'] ); ?></textarea></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Editorial Controls', 'nfinite-creators' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="nfinite-featured-release"><?php esc_html_e( 'Featured Release', 'nfinite-creators' ); ?></label></th>
						<td>
							<select id="nfinite-featured-release" name="<?php echo esc_attr( self::OPTION ); ?>[featured_release_id]">
								<option value="0"><?php esc_html_e( 'Automatic — newest release marked Featured', 'nfinite-creators' ); ?></option>
								<?php foreach ( $releases as $release ) : ?>
									<option value="<?php echo esc_attr( $release->ID ); ?>" <?php selected( $settings['featured_release_id'], $release->ID ); ?>>
										<?php
										$creator_id = absint( get_post_meta( $release->ID, '_nfinite_release_creator_id', true ) );
										echo esc_html(
											$release->post_title .
											( $creator_id ? ' — ' . get_the_title( $creator_id ) : '' )
										);
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'If Automatic is selected, Nfinite uses the newest published release marked Featured. If none are marked, the newest published release is used.', 'nfinite-creators' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Sections', 'nfinite-creators' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$sections = array(
						'show_featured' => array( __( 'Featured Release', 'nfinite-creators' ), null ),
						'show_releases' => array( __( 'New Releases', 'nfinite-creators' ), 'new_release_count' ),
						'show_tracks'   => array( __( 'Latest Tracks', 'nfinite-creators' ), 'latest_track_count' ),
						'show_beats'    => array( __( 'Beats & Instrumentals', 'nfinite-creators' ), 'beat_count' ),
						'show_creators' => array( __( 'Artists / Creators to Know', 'nfinite-creators' ), 'creator_count' ),
						'show_videos'   => array( __( 'Music Videos', 'nfinite-creators' ), 'video_count' ),
					);
					foreach ( $sections as $toggle => $data ) :
					?>
						<tr>
							<th scope="row"><?php echo esc_html( $data[0] ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $toggle ); ?>]" value="1" <?php checked( ! empty( $settings[ $toggle ] ) ); ?>>
									<?php esc_html_e( 'Show section', 'nfinite-creators' ); ?>
								</label>
								<?php if ( $data[1] ) : ?>
									&nbsp;&nbsp;
									<label>
										<?php esc_html_e( 'Items:', 'nfinite-creators' ); ?>
										<input type="number" min="1" max="30" class="small-text" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $data[1] ); ?>]" value="<?php echo esc_attr( $settings[ $data[1] ] ); ?>">
									</label>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>
			<p><strong><?php esc_html_e( 'Music Hub URL:', 'nfinite-creators' ); ?></strong> <a href="<?php echo esc_url( get_post_type_archive_link( 'nfinite_release' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_post_type_archive_link( 'nfinite_release' ) ); ?></a></p>
			<p><code>[nfinite_music_hub]</code> <?php esc_html_e( 'can also render the same hub inside another WordPress page.', 'nfinite-creators' ); ?></p>
		</div>
		<?php
	}

	public static function featured_release() {
		$rule = Nfinite_Creators_Programming::rule( 'hero' );
		if ( 'automatic' !== $rule['mode'] ) {
			$items = Nfinite_Creators_Programming::select( 'hero', 1 );
			return $items ? $items[0] : null;
		}
		$settings = self::settings();
		$selected = absint( $settings['featured_release_id'] );

		if ( $selected && Nfinite_Creators_Programming::public_item( $selected ) && 'nfinite_release' === get_post_type( $selected ) && ! in_array( $selected, $rule['exclude'], true ) ) {
			return get_post( $selected );
		}

		$featured = get_posts(
			array(
				'post_type'      => 'nfinite_release',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_nfinite_release_featured',
				'meta_value'     => '1',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		foreach ( $featured as $post ) {
			if ( Nfinite_Creators_Programming::public_item( $post->ID ) && ! in_array( $post->ID, $rule['exclude'], true ) ) { return $post; }
		}
		$newest = Nfinite_Creators_Programming::select( 'hero', 1 );
		return $newest ? $newest[0] : null;
	}

	public static function new_releases( $limit ) {
		return Nfinite_Creators_Programming::select( 'new_releases', max( 1, absint( $limit ) ) );
	}

	public static function latest_tracks( $limit ) {
		return Nfinite_Creators_Programming::select( 'latest_tracks', max( 1, absint( $limit ) ) );
	}

	public static function creator_beats( $limit ) {
		$limit = max( 1, absint( $limit ) );
		$beats = array();
		$seen  = array();

		$append = static function ( $beat ) use ( &$beats, &$seen, $limit ) {
			if ( ! is_array( $beat ) || empty( $beat['audioUrl'] ) ) {
				return false;
			}

			$key = strtolower( trim( (string) $beat['audioUrl'] ) );

			if ( isset( $seen[ $key ] ) ) {
				return false;
			}

			$seen[ $key ] = true;
			$beats[]      = $beat;

			return count( $beats ) >= $limit;
		};

		/*
		 * 1. Current Nfinite Track records.
		 *
		 * Beats are intentionally explicit. A song title such as "Produced by",
		 * "Jacking For Beats", or an artist/producer name containing "beat" must
		 * never turn a normal recording into a marketplace/discovery beat.
		 */
		$tracks = get_posts(
			array(
				'post_type'      => 'nfinite_track',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		foreach ( $tracks as $track ) {
			$audio_url = get_post_meta( $track->ID, '_nfinite_track_audio_url', true );
			if ( ! $audio_url ) { continue; }

			$format = sanitize_key( get_post_meta( $track->ID, '_nfinite_track_format', true ) );
			$is_beat = in_array( $format, array( 'beat', 'instrumental' ), true );

			// A linked Nfinite beat product is also an explicit classification.
			if ( ! $is_beat ) {
				$product_id = absint( get_post_meta( $track->ID, '_nfinite_track_product_id', true ) );
				if ( $product_id && 'beat' === get_post_meta( $product_id, '_nfinite_creator_product_type', true ) ) {
					$is_beat = true;
				}
			}

			if ( ! $is_beat ) { continue; }

			$payload = Nfinite_Creators_Music_Query::track_payload( $track->ID );
			if ( ! empty( $payload ) ) {
				$payload['release'] = 'instrumental' === $format ? __( 'Instrumental', 'nfinite-creators' ) : __( 'Beat', 'nfinite-creators' );
				if ( $append( $payload ) ) { return $beats; }
			}
		}

		/*
		 * 2. Legacy Creator profile tracks.
		 */
		$creators = get_posts(
			array(
				'post_type'      => 'nfinite_creator',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		foreach ( $creators as $creator ) {
			$creator_tracks = get_post_meta( $creator->ID, '_nfinite_creator_tracks', true );

			if ( ! is_array( $creator_tracks ) ) {
				continue;
			}

			foreach ( $creator_tracks as $index => $track ) {
				if ( ! is_array( $track ) || empty( $track['audio_url'] ) ) {
					continue;
				}

				$type  = strtolower( trim( isset( $track['type'] ) ? $track['type'] : '' ) );
				$title = strtolower( trim( isset( $track['title'] ) ? $track['title'] : '' ) );

				if ( 'beat' !== $type && 'instrumental' !== $type ) {
					continue;
				}

				$cover = ! empty( $track['cover_url'] )
					? esc_url_raw( $track['cover_url'] )
					: get_the_post_thumbnail_url( $creator->ID, 'large' );

				$commerce = class_exists( 'Nfinite_Creators_Track_Commerce' )
					? Nfinite_Creators_Track_Commerce::legacy_track( $track )
					: array();

				$legacy_key = 'creator-beat-' . $creator->ID . '-' . absint( $index );
				$legacy_analytics_id = absint( sprintf( '%u', crc32( $legacy_key ) ) );

				$beat = array(
					'id'                => $legacy_key,
					'analyticsObjectId' => $legacy_analytics_id,
					'analyticsObjectType' => 'legacy_track',
					'analyticsTitle'    => ! empty( $track['title'] ) ? sanitize_text_field( $track['title'] ) : __( 'Untitled Beat', 'nfinite-creators' ),
					'title'             => ! empty( $track['title'] ) ? sanitize_text_field( $track['title'] ) : __( 'Untitled Beat', 'nfinite-creators' ),
					'artist'            => get_the_title( $creator->ID ),
					'creatorId'         => $creator->ID,
					'releaseId'   => 0,
					'release'     => __( 'Beat', 'nfinite-creators' ),
					'audioUrl'    => esc_url_raw( $track['audio_url'] ),
					'source'      => 'local',
					'sourceUrl'   => esc_url_raw( $track['audio_url'] ),
					'artwork'     => esc_url_raw( $cover ),
					'explicit'    => false,
					'trackNumber' => 0,
					'duration'    => '',
					'credits'     => ! empty( $track['credits'] ) ? sanitize_textarea_field( $track['credits'] ) : '',
					'productUrl'  => ! empty( $commerce['url'] ) ? esc_url_raw( $commerce['url'] ) : '',
					'buyLabel'    => ! empty( $commerce['label'] ) ? sanitize_text_field( $commerce['label'] ) : __( 'Buy Now', 'nfinite-creators' ),
					'priceHtml'   => ! empty( $commerce['price_html'] ) ? $commerce['price_html'] : '',
				);

				if ( $append( $beat ) ) {
					return $beats;
				}
			}
		}

		/*
		 * 3. WooCommerce Beat / Instrumental products.
		 *
		 * PairOfDice already sells beats through WooCommerce. When WPNfinite
		 * audio-preview fields are present, those products can participate in
		 * Nfinite discovery without making WooCommerce a required dependency.
		 */
		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products(
				array(
					'status' => 'publish',
					'limit'  => -1,
					'order'  => 'DESC',
					'orderby'=> 'date',
				)
			);

			foreach ( $products as $product ) {
				if ( ! $product instanceof WC_Product ) {
					continue;
				}

				$product_id = $product->get_id();
				$is_beat    = 'beat' === get_post_meta( $product_id, '_nfinite_creator_product_type', true );

				$terms = wp_get_post_terms(
					$product_id,
					'product_cat',
					array( 'fields' => 'all' )
				);

				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$value = strtolower( $term->slug . ' ' . $term->name );

						if (
							false !== strpos( $value, 'beat' ) ||
							false !== strpos( $value, 'instrumental' )
						) {
							$is_beat = true;
							break;
						}
					}
				}


				if ( ! $is_beat ) {
					continue;
				}

				$audio_url = esc_url_raw(
					get_post_meta( $product_id, '_wpnfinite_audio_preview', true )
				);

				if ( ! $audio_url && 'yes' === get_post_meta( $product_id, '_wpnfinite_audio_download_fallback', true ) ) {
					foreach ( $product->get_downloads() as $download ) {
						$file = $download->get_file();

						if ( preg_match( '/\.(mp3|m4a|ogg|oga|wav|aac)(?:\?.*)?$/i', $file ) ) {
							$audio_url = esc_url_raw( $file );
							break;
						}
					}
				}

				/*
				 * A product without public preview audio still belongs on the
				 * Beats page, but it cannot join the player queue.
				 */
				$title  = $product->get_name();
				$artist = '';

				if ( false !== strpos( $title, '|' ) ) {
					$parts  = array_map( 'trim', explode( '|', $title, 2 ) );
					$title  = $parts[0];
					$artist = isset( $parts[1] ) ? $parts[1] : '';
				}

				$artist_meta = sanitize_text_field(
					(string) get_post_meta( $product_id, '_wpnfinite_music_artist', true )
				);

				if ( $artist_meta ) {
					$artist = $artist_meta;
				}

				$artwork = wp_get_attachment_image_url(
					$product->get_image_id(),
					'large'
				);

				$key = $audio_url
					? strtolower( trim( $audio_url ) )
					: 'woo-product-' . $product_id;

				if ( isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ] = true;

				$product_creator_id = absint( get_post_meta( $product_id, '_nfinite_creator_id', true ) );

				$beats[] = array(
					'id'                  => 'woo-beat-' . $product_id,
					'analyticsObjectId'   => $product_id,
					'analyticsObjectType' => 'product_preview',
					'analyticsTitle'      => sanitize_text_field( $title ),
					'title'               => sanitize_text_field( $title ),
					'artist'              => sanitize_text_field( $artist ),
					'creatorId'           => $product_creator_id,
					'releaseId'   => 0,
					'release'     => __( 'Beat', 'nfinite-creators' ),
					'audioUrl'    => $audio_url,
					'source'      => 'local',
					'sourceUrl'   => $audio_url,
					'artwork'     => esc_url_raw( $artwork ),
					'explicit'    => false,
					'trackNumber' => 0,
					'duration'    => '',
					'credits'     => '',
					'productUrl'  => get_permalink( $product_id ),
					'priceHtml'   => $product->get_price_html(),
				);

				if ( count( $beats ) >= $limit ) {
					return $beats;
				}
			}
		}

		return $beats;
	}

	public static function featured_creators( $limit ) {
		return Nfinite_Creators_Programming::select( 'creators', max( 1, absint( $limit ) ) );
	}

	public static function latest_videos( $limit ) {
		$featured = array();
		$regular  = array();

		// Site-curated PairOfDice video programming. This allows podcasts, documentaries,
		// news clips, movies, vlogs, streams, etc. to exist independently of a creator profile.
		$library_videos = get_posts(
			array(
				'post_type'      => 'nfinite_video',
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, absint( $limit ) ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		foreach ( $library_videos as $video_post ) {
			$url = esc_url_raw( get_post_meta( $video_post->ID, '_nfinite_video_url', true ) );
			if ( ! $url || ( class_exists( 'Nfinite_Creators_Video' ) && ! Nfinite_Creators_Video::is_supported_url( $url ) ) ) {
				continue;
			}

			$creator_id = absint( get_post_meta( $video_post->ID, '_nfinite_video_creator_id', true ) );
			$source_name = sanitize_text_field( get_post_meta( $video_post->ID, '_nfinite_video_source_name', true ) );
			$source_url  = esc_url_raw( get_post_meta( $video_post->ID, '_nfinite_video_source_url', true ) );
			$creator_name = $creator_id ? get_the_title( $creator_id ) : $source_name;
			$creator_url  = $creator_id ? get_permalink( $creator_id ) : $source_url;
			$description  = has_excerpt( $video_post ) ? get_the_excerpt( $video_post ) : wp_strip_all_tags( $video_post->post_content );

			$item = array(
				'video_id'      => $video_post->ID,
				'creator_id'    => $creator_id,
				'creator_name'  => $creator_name ? $creator_name : __( 'PairOfDice Media', 'nfinite-creators' ),
				'creator_url'   => $creator_url,
				'title'         => get_the_title( $video_post ),
				'type'          => sanitize_text_field( get_post_meta( $video_post->ID, '_nfinite_video_type', true ) ) ?: __( 'Video', 'nfinite-creators' ),
				'channel'       => sanitize_text_field( get_post_meta( $video_post->ID, '_nfinite_video_channel', true ) ),
				'source_name'   => $source_name,
				'source_url'    => $source_url,
				'original_url'  => esc_url_raw( get_post_meta( $video_post->ID, '_nfinite_video_original_url', true ) ),
				'url'           => $url,
				'description'   => wp_trim_words( $description, 40 ),
				'featured'      => (bool) get_post_meta( $video_post->ID, '_nfinite_video_featured', true ),
				'tv'            => (bool) get_post_meta( $video_post->ID, '_nfinite_video_tv', true ),
				'tv_section'    => sanitize_key( get_post_meta( $video_post->ID, '_nfinite_video_tv_section', true ) ),
				'thumbnail_url' => get_the_post_thumbnail_url( $video_post->ID, 'large' ),
				'published_at'  => get_post_time( 'U', true, $video_post ),
			);

			if ( $item['featured'] ) {
				$featured[] = $item;
			} else {
				$regular[] = $item;
			}
		}

		// Existing creator-profile videos remain fully compatible and continue feeding the hub.
		$creators = get_posts(
			array(
				'post_type'      => 'nfinite_creator',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		foreach ( $creators as $creator ) {
			$videos = get_post_meta( $creator->ID, '_nfinite_creator_videos', true );
			if ( ! is_array( $videos ) ) {
				continue;
			}

			foreach ( $videos as $video ) {
				if ( ! is_array( $video ) || empty( $video['url'] ) ) {
					continue;
				}

				$item = array(
					'video_id'      => 0,
					'creator_id'    => $creator->ID,
					'creator_name'  => get_the_title( $creator->ID ),
					'creator_url'   => get_permalink( $creator->ID ),
					'title'         => ! empty( $video['title'] ) ? sanitize_text_field( $video['title'] ) : __( 'Video', 'nfinite-creators' ),
					'type'          => ! empty( $video['type'] ) ? sanitize_text_field( $video['type'] ) : __( 'Video', 'nfinite-creators' ),
					'channel'       => ! empty( $video['channel'] ) ? sanitize_text_field( $video['channel'] ) : '',
					'source_name'   => ! empty( $video['source_name'] ) ? sanitize_text_field( $video['source_name'] ) : '',
					'source_url'    => ! empty( $video['source_url'] ) ? esc_url_raw( $video['source_url'] ) : '',
					'original_url'  => ! empty( $video['original_url'] ) ? esc_url_raw( $video['original_url'] ) : '',
					'url'           => esc_url_raw( $video['url'] ),
					'description'   => ! empty( $video['description'] ) ? sanitize_textarea_field( $video['description'] ) : '',
					'featured'      => ! empty( $video['featured'] ),
					'tv'            => ! empty( $video['tv'] ),
					'tv_section'    => ! empty( $video['tv_section'] ) ? sanitize_key( $video['tv_section'] ) : '',
					'thumbnail_url' => '',
					'published_at'  => get_post_modified_time( 'U', true, $creator ),
				);

				if ( $item['featured'] ) {
					$featured[] = $item;
				} else {
					$regular[] = $item;
				}
			}
		}

		$by_recency = static function ( $a, $b ) {
			return (int) ( $b['published_at'] ?? 0 ) <=> (int) ( $a['published_at'] ?? 0 );
		};
		usort( $featured, $by_recency );
		usort( $regular, $by_recency );

		return array_slice( array_merge( $featured, $regular ), 0, max( 1, absint( $limit ) ) );
	}

	public static function release_card( $release_id ) {
		$release_id = absint( $release_id );
		if ( 'nfinite_release' !== get_post_type( $release_id ) ) {
			return '';
		}

		$artwork    = get_the_post_thumbnail_url( $release_id, 'large' );
		$type       = Nfinite_Creators_Music_Query::release_type_label( $release_id );
		$creator_id = absint( get_post_meta( $release_id, '_nfinite_release_creator_id', true ) );
		$date       = get_post_meta( $release_id, '_nfinite_release_date', true );

		ob_start();
		?>
		<article class="nfinite-release-card nfinite-music-hub-release-card">
			<a class="nfinite-release-card__art" href="<?php echo esc_url( get_permalink( $release_id ) ); ?>">
				<?php if ( $artwork ) : ?>
					<img src="<?php echo esc_url( $artwork ); ?>" alt="">
				<?php else : ?>
					<span class="nfinite-music-hub-placeholder"></span>
				<?php endif; ?>
				<span class="nfinite-music-hub-release-card__play" aria-hidden="true">▶</span>
			</a>
			<div class="nfinite-release-card__body">
				<span class="nfinite-eyebrow"><?php echo esc_html( $type ); ?></span>
				<h3><a href="<?php echo esc_url( get_permalink( $release_id ) ); ?>"><?php echo esc_html( get_the_title( $release_id ) ); ?></a></h3>
				<p>
					<?php echo $creator_id ? esc_html( get_the_title( $creator_id ) ) : ''; ?>
					<?php if ( $date ) : ?>
						<span aria-hidden="true"> · </span><?php echo esc_html( wp_date( 'Y', strtotime( $date ) ) ); ?>
					<?php endif; ?>
				</p>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	public static function render() {
		$settings = self::settings();

		$featured = ! empty( $settings['show_featured'] ) ? self::featured_release() : null;
		$releases = ! empty( $settings['show_releases'] ) ? self::new_releases( $settings['new_release_count'] ) : array();
		$tracks   = ! empty( $settings['show_tracks'] ) ? self::latest_tracks( $settings['latest_track_count'] ) : array();
		$beats    = ! empty( $settings['show_beats'] ) ? self::creator_beats( $settings['beat_count'] ) : array();
		$creators = ! empty( $settings['show_creators'] ) ? self::featured_creators( $settings['creator_count'] ) : array();
		$videos   = ! empty( $settings['show_videos'] ) ? self::latest_videos( $settings['video_count'] ) : array();

		ob_start();
		include NFINITE_CREATORS_DIR . 'templates/music/music-hub.php';
		return ob_get_clean();
	}

	public static function shortcode() {
		return self::render();
	}
}
