<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_TV {
	const PAGE_SLUG   = 'tv';
	const PAGE_OPTION = 'nfinite_tv_page_id';

	public static function init() {
		add_shortcode( 'nfinite_tv_hub', array( __CLASS__, 'shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		// Do not continuously recreate/reserve /tv/. The page should remain an ordinary
		// editable WordPress page so site owners can control its template/layout.
		add_action( 'admin_init', array( __CLASS__, 'sync_existing_page' ), 20 );
	}

	public static function register_rest_routes() {
		register_rest_route(
			'nfinite/v1',
			'/tv/catalog',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_catalog' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'default'           => 160,
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $value ) { return absint( $value ) > 0; },
					),
				),
			)
		);
	}

	public static function rest_catalog( WP_REST_Request $request ) {
		$limit = min( 200, max( 1, absint( $request->get_param( 'limit' ) ) ) );
		$items = array_map( array( __CLASS__, 'rest_item' ), self::tv_items( $limit ) );
		$creator_ids = array_values( array_unique( array_filter( array_map( static function ( $item ) { return absint( $item['creator_id'] ?? 0 ); }, $items ) ) ) );
		$creators = array();
		foreach ( $creator_ids as $creator_id ) {
			if ( 'publish' !== get_post_status( $creator_id ) ) { continue; }
			$creators[] = array(
				'id'          => 'creator-' . $creator_id,
				'creatorId'   => $creator_id,
				'title'       => html_entity_decode( get_the_title( $creator_id ), ENT_QUOTES, 'UTF-8' ),
				'type'        => 'Creator',
				'meta'        => 'PairOfDice Creator',
				'description' => wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $creator_id ) ), 30 ),
				'thumbnail'   => self::creator_artwork( $creator_id ),
				'backdrop'    => get_the_post_thumbnail_url( $creator_id, 'full' ) ?: '',
				'url'         => get_permalink( $creator_id ),
			);
		}

		$response = rest_ensure_response(
			array(
				'version'    => NFINITE_CREATORS_VERSION,
				'generated'  => gmdate( 'c' ),
				'items'      => array_values( $items ),
				'creators'   => $creators,
				'sections'   => self::sections(),
			)
		);
		$response->header( 'Cache-Control', 'public, max-age=60, s-maxage=60' );
		return $response;
	}

	private static function youtube_video_id( $url ) {
		if ( ! $url ) { return ''; }
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) { return ''; }
		$host = strtolower( preg_replace( '/^www\\./', '', $parts['host'] ) );
		if ( 'youtu.be' === $host ) {
			return sanitize_text_field( trim( $parts['path'] ?? '', '/' ) );
		}
		if ( in_array( $host, array( 'youtube.com', 'm.youtube.com' ), true ) ) {
			if ( ! empty( $parts['query'] ) ) {
				parse_str( $parts['query'], $query );
				if ( ! empty( $query['v'] ) ) { return sanitize_text_field( $query['v'] ); }
			}
			$segments = array_values( array_filter( explode( '/', trim( $parts['path'] ?? '', '/' ) ) ) );
			if ( ! empty( $segments[0] ) && in_array( $segments[0], array( 'embed', 'shorts', 'live' ), true ) && ! empty( $segments[1] ) ) {
				return sanitize_text_field( $segments[1] );
			}
		}
		return '';
	}

	private static function derived_thumbnail( $url ) {
		$youtube_id = self::youtube_video_id( $url );
		return $youtube_id ? 'https://i.ytimg.com/vi/' . rawurlencode( $youtube_id ) . '/hqdefault.jpg' : '';
	}

	private static function creator_artwork( $creator_id ) {
		$creator_id = absint( $creator_id );
		if ( ! $creator_id ) { return ''; }
		return get_the_post_thumbnail_url( $creator_id, 'large' ) ?: '';
	}

	private static function item_thumbnail( $item ) {
		$thumbnail = esc_url_raw( $item['thumbnail_url'] ?? '' );
		if ( $thumbnail ) { return $thumbnail; }
		$thumbnail = self::derived_thumbnail( $item['url'] ?? '' );
		if ( $thumbnail ) { return $thumbnail; }
		if ( ! empty( $item['show_id'] ) ) {
			$thumbnail = get_the_post_thumbnail_url( absint( $item['show_id'] ), 'large' );
			if ( $thumbnail ) { return $thumbnail; }
		}
		return self::creator_artwork( $item['creator_id'] ?? 0 );
	}

	private static function item_backdrop( $item, $thumbnail ) {
		$backdrop = esc_url_raw( $item['backdrop_url'] ?? '' );
		if ( $backdrop ) { return $backdrop; }
		if ( ! empty( $item['show_id'] ) ) {
			$backdrop = get_the_post_thumbnail_url( absint( $item['show_id'] ), 'full' );
			if ( $backdrop ) { return $backdrop; }
		}
		return $thumbnail;
	}

	private static function rest_item( $item ) {
		$thumbnail = self::item_thumbnail( $item );
		$backdrop  = self::item_backdrop( $item, $thumbnail );
		$meta = array_values( array_filter( array( $item['year'] ?? '', $item['runtime'] ?? '', $item['rating'] ?? '', $item['type'] ?? '' ) ) );
		$section = sanitize_key( $item['section'] ?? 'featured' );
		return array(
			'id'          => ( ! empty( $item['object_type'] ) ? sanitize_key( $item['object_type'] ) : 'creator-video' ) . '-' . ( absint( $item['object_id'] ?? 0 ) ?: md5( ( $item['url'] ?? '' ) . '|' . ( $item['title'] ?? '' ) ) ),
			'objectType'  => sanitize_key( $item['object_type'] ?? 'video' ),
			'objectId'    => absint( $item['object_id'] ?? 0 ),
			'creatorId'   => absint( $item['creator_id'] ?? 0 ),
			'creator'     => sanitize_text_field( $item['creator_name'] ?? '' ),
			'creatorUrl'  => esc_url_raw( $item['creator_url'] ?? '' ),
			'title'       => sanitize_text_field( $item['title'] ?? '' ),
			'type'        => sanitize_text_field( $item['type'] ?? 'Video' ),
			'meta'        => implode( ' · ', array_map( 'sanitize_text_field', $meta ) ),
			'description' => sanitize_textarea_field( $item['description'] ?? '' ),
			'thumbnail'   => $thumbnail,
			'backdrop'    => $backdrop,
			'playUrl'     => esc_url_raw( $item['url'] ?? '' ),
			'section'     => $section,
			'live'        => ! empty( $item['live'] ),
			'featured'    => ! empty( $item['featured'] ),
			'original'    => (bool) preg_match( '/original/i', ( $item['type'] ?? '' ) . ' ' . ( $item['description'] ?? '' ) ),
			'publishedAt' => absint( $item['published_at'] ?? 0 ),
			'showId'      => absint( $item['show_id'] ?? 0 ),
			'showTitle'   => sanitize_text_field( $item['show_title'] ?? '' ),
		);
	}

	public static function sync_existing_page() {
		$page_id = absint( get_option( self::PAGE_OPTION ) );
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( ! $page || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
			$page = get_page_by_path( self::PAGE_SLUG );
			if ( $page ) {
				$page_id = (int) $page->ID;
				update_option( self::PAGE_OPTION, $page_id, false );
			}
		}

		if ( ! $page_id || ! $page ) {
			return;
		}

		// Older Nfinite versions generated the TV page automatically. If that page is
		// still the untouched shortcode-only page and WPNfinite is active, move it to
		// the theme's full-width template. This preserves /tv/ while giving the editor
		// full control going forward.
		$content = trim( (string) $page->post_content );
		if ( '[nfinite_tv_hub]' === $content && self::is_wpnfinite_active() ) {
			$template = (string) get_post_meta( $page_id, '_wp_page_template', true );
			if ( ! $template || 'default' === $template ) {
				update_post_meta( $page_id, '_wp_page_template', 'templates/template-full-width.php' );
			}
		}
	}

	private static function is_wpnfinite_active() {
		$theme = wp_get_theme();
		return 'WPNfinite' === $theme->get( 'Name' ) || 'wpnfinite' === $theme->get_template() || 'wpnfinite' === $theme->get_stylesheet();
	}

	public static function ensure_page() {
		$page_id = absint( get_option( self::PAGE_OPTION ) );
		if ( $page_id && 'trash' !== get_post_status( $page_id ) ) { return; }

		$page = get_page_by_path( self::PAGE_SLUG );
		if ( $page ) {
			update_option( self::PAGE_OPTION, $page->ID, false );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) && ! wp_doing_cron() ) { return; }

		$page_id = wp_insert_post(
			array(
				'post_title'   => 'PairOfDice TV',
				'post_name'    => self::PAGE_SLUG,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[nfinite_tv_hub]',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( self::PAGE_OPTION, $page_id, false );
		}
	}

	public static function page_url() {
		$page_id = absint( get_option( self::PAGE_OPTION ) );
		if ( $page_id && 'trash' !== get_post_status( $page_id ) ) {
			return get_permalink( $page_id );
		}
		$page = get_page_by_path( self::PAGE_SLUG );
		return $page ? get_permalink( $page->ID ) : home_url( '/tv/' );
	}

	private static function normalize_section( $section ) {
		$section = sanitize_key( $section );
		$allowed = array_keys( self::sections() );
		return in_array( $section, $allowed, true ) ? $section : 'featured';
	}

	public static function sections() {
		return array(
			'featured'      => __( 'Featured', 'nfinite-creators' ),
			'live'          => __( 'Live TV', 'nfinite-creators' ),
			'movies'        => __( 'Movies', 'nfinite-creators' ),
			'shows'         => __( 'Shows & Series', 'nfinite-creators' ),
			'documentaries' => __( 'Documentaries', 'nfinite-creators' ),
			'independent'   => __( 'Independent', 'nfinite-creators' ),
			'music-culture' => __( 'Music & Culture', 'nfinite-creators' ),
			'comedy'        => __( 'Comedy', 'nfinite-creators' ),
			'classics'      => __( 'Classics', 'nfinite-creators' ),
		);
	}

	public static function tv_items( $limit = 160 ) {
		$items = array();

		$library = get_posts(
			array(
				'post_type'      => 'nfinite_video',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'     => '_nfinite_video_tv',
						'value'   => '1',
						'compare' => '=',
					),
				),
				'no_found_rows'  => true,
			)
		);

		foreach ( $library as $post ) {
			$url = esc_url_raw( get_post_meta( $post->ID, '_nfinite_video_url', true ) );
			if ( ! $url || ! Nfinite_Creators_Video::is_supported_url( $url ) ) { continue; }

			$creator_id = absint( get_post_meta( $post->ID, '_nfinite_video_creator_id', true ) );
			$source     = sanitize_text_field( get_post_meta( $post->ID, '_nfinite_video_source_name', true ) );
			$section    = self::normalize_section( get_post_meta( $post->ID, '_nfinite_video_tv_section', true ) );

			$items[] = array(
				'video_id'       => $post->ID,
				'object_type'    => 'video',
				'object_id'      => $post->ID,
				'creator_id'     => $creator_id,
				'creator_name'   => $creator_id ? get_the_title( $creator_id ) : ( $source ?: __( 'PairOfDice Media', 'nfinite-creators' ) ),
				'creator_url'    => $creator_id ? get_permalink( $creator_id ) : esc_url_raw( get_post_meta( $post->ID, '_nfinite_video_source_url', true ) ),
				'title'          => get_the_title( $post ),
				'type'           => sanitize_text_field( get_post_meta( $post->ID, '_nfinite_video_type', true ) ) ?: __( 'Video', 'nfinite-creators' ),
				'url'            => $url,
				'description'    => wp_trim_words( wp_strip_all_tags( $post->post_content ), 42 ),
				'thumbnail_url'  => get_the_post_thumbnail_url( $post->ID, 'large' ),
				'backdrop_url'   => esc_url_raw( get_post_meta( $post->ID, '_nfinite_video_tv_backdrop', true ) ),
				'section'        => $section,
				'year'           => sanitize_text_field( get_post_meta( $post->ID, '_nfinite_video_tv_year', true ) ),
				'runtime'        => sanitize_text_field( get_post_meta( $post->ID, '_nfinite_video_tv_runtime', true ) ),
				'rating'         => sanitize_text_field( get_post_meta( $post->ID, '_nfinite_video_tv_rating', true ) ),
				'live'           => (bool) get_post_meta( $post->ID, '_nfinite_video_tv_live', true ),
				'featured'       => (bool) get_post_meta( $post->ID, '_nfinite_video_tv_featured', true ),
				'published_at'   => get_post_time( 'U', true, $post ),
			);
		}


		// Structured episodes from Nfinite Shows / Series can also feed PairOfDice TV.
		if ( class_exists( 'Nfinite_Creators_Shows' ) ) {
			$episodes = get_posts(
				array(
					'post_type'      => Nfinite_Creators_Shows::EPISODE_POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'meta_query'     => array(
						array(
							'key'     => '_nfinite_episode_tv',
							'value'   => '1',
							'compare' => '=',
						),
					),
					'no_found_rows'  => true,
				)
			);

			foreach ( $episodes as $episode ) {
				$url = esc_url_raw( get_post_meta( $episode->ID, '_nfinite_episode_video_url', true ) );
				if ( ! $url || ! Nfinite_Creators_Video::is_supported_url( $url ) ) { continue; }
				$show_id    = absint( get_post_meta( $episode->ID, '_nfinite_episode_show_id', true ) );
				$creator_id = absint( get_post_meta( $episode->ID, '_nfinite_episode_creator_id', true ) );
				if ( ! $creator_id && $show_id ) { $creator_id = absint( get_post_meta( $show_id, '_nfinite_show_creator_id', true ) ); }
				$show_title = $show_id ? get_the_title( $show_id ) : '';
				$items[] = array(
					'video_id'       => $episode->ID,
					'object_type'    => 'episode',
					'object_id'      => $episode->ID,
					'creator_id'     => $creator_id,
					'creator_name'   => $creator_id ? get_the_title( $creator_id ) : ( $show_title ?: __( 'PairOfDice Media', 'nfinite-creators' ) ),
					'creator_url'    => $creator_id ? get_permalink( $creator_id ) : ( $show_id ? get_permalink( $show_id ) : '' ),
					'title'          => get_the_title( $episode ),
					'type'           => __( 'Episode', 'nfinite-creators' ),
					'url'            => $url,
					'description'    => wp_trim_words( wp_strip_all_tags( $episode->post_content ), 42 ),
					'thumbnail_url'  => get_the_post_thumbnail_url( $episode->ID, 'large' ),
					'backdrop_url'   => $show_id ? get_the_post_thumbnail_url( $show_id, 'full' ) : '',
					'section'        => 'shows',
					'year'           => get_the_date( 'Y', $episode ),
					'runtime'        => sanitize_text_field( get_post_meta( $episode->ID, '_nfinite_episode_runtime', true ) ),
					'rating'         => '',
					'live'           => false,
					'featured'       => (bool) get_post_meta( $episode->ID, '_nfinite_episode_featured', true ),
					'published_at'   => get_post_time( 'U', true, $episode ),
					'show_id'        => $show_id,
					'show_title'     => $show_title,
				);
			}
		}

		// Creator-owned TV programming can be submitted from Creator Studio Videos.
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
			if ( ! is_array( $videos ) ) { continue; }

			foreach ( $videos as $video ) {
				if ( empty( $video['tv'] ) || empty( $video['url'] ) ) { continue; }
				$url = esc_url_raw( $video['url'] );
				if ( ! Nfinite_Creators_Video::is_supported_url( $url ) ) { continue; }

				$items[] = array(
					'video_id'       => 0,
					'object_type'    => '',
					'object_id'      => 0,
					'creator_id'     => $creator->ID,
					'creator_name'   => get_the_title( $creator ),
					'creator_url'    => get_permalink( $creator ),
					'title'          => ! empty( $video['title'] ) ? sanitize_text_field( $video['title'] ) : __( 'Creator Programming', 'nfinite-creators' ),
					'type'           => ! empty( $video['type'] ) ? sanitize_text_field( $video['type'] ) : __( 'Video', 'nfinite-creators' ),
					'url'            => $url,
					'description'    => ! empty( $video['description'] ) ? sanitize_textarea_field( $video['description'] ) : '',
					'thumbnail_url'  => '',
					'backdrop_url'   => '',
					'section'        => self::normalize_section( ! empty( $video['tv_section'] ) ? $video['tv_section'] : 'independent' ),
					'year'           => '',
					'runtime'        => '',
					'rating'         => '',
					'live'           => ! empty( $video['tv_live'] ),
					'featured'       => ! empty( $video['tv_featured'] ),
					'published_at'   => get_post_modified_time( 'U', true, $creator ),
				);
			}
		}

		usort(
			$items,
			static function ( $a, $b ) {
				$featured = (int) ! empty( $b['featured'] ) <=> (int) ! empty( $a['featured'] );
				if ( 0 !== $featured ) { return $featured; }
				return (int) ( $b['published_at'] ?? 0 ) <=> (int) ( $a['published_at'] ?? 0 );
			}
		);

		return Nfinite_Creators_Programming::tv( $items, $limit );
	}

	public static function shortcode() {
		$items = self::tv_items();
		$featured = ! empty( $items ) ? $items[0] : null;
		$rows = array();

		foreach ( self::sections() as $key => $label ) {
			if ( 'featured' === $key ) { continue; }
			$rows[ $key ] = array(
				'label' => $label,
				'items' => array_values(
					array_filter(
						$items,
						static function ( $item ) use ( $key ) {
							if ( 'live' === $key ) { return ! empty( $item['live'] ) || 'live' === $item['section']; }
							return $item['section'] === $key;
						}
					)
				),
			);
		}

		// Useful fallback rows so a newly-created hub does not feel empty when
		// editors classify by content type before choosing a TV shelf.
		$fallbacks = array(
			'movies'        => array( 'Movie / Film', 'Movie' ),
			'documentaries' => array( 'Documentary', 'Mini Documentary', 'Feature / Story' ),
			'shows'         => array( 'Podcast Episode', 'Talk / Discussion', 'Interview', 'Vlog' ),
			'comedy'        => array( 'Comedy' ),
			'music-culture' => array( 'Performance', 'Live Performance', 'Music Video' ),
		);

		foreach ( $fallbacks as $row => $types ) {
			if ( ! empty( $rows[ $row ]['items'] ) ) { continue; }
			$rows[ $row ]['items'] = array_values(
				array_filter(
					$items,
					static function ( $item ) use ( $types ) {
						return in_array( $item['type'], $types, true );
					}
				)
			);
		}

		ob_start();
		include NFINITE_CREATORS_DIR . 'templates/tv/tv-hub.php';
		return ob_get_clean();
	}
}
