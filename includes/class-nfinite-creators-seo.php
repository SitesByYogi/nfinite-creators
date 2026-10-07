<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Public SEO metadata for Nfinite-owned archives, rewrite endpoints, and
 * generated destination pages. These routes do not always behave like normal
 * WordPress Pages, so relying on a theme title or an SEO-plugin archive label
 * can expose internal names such as "Creator Posts Archive".
 */
class Nfinite_Creators_SEO {
	private static $current = null;

	public static function init() {
		// WordPress/native document title.
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 99 );

		// Yoast SEO integrations. These filters are harmless when Yoast is absent.
		add_filter( 'wpseo_title', array( __CLASS__, 'yoast_title' ), 99 );
		add_filter( 'wpseo_metadesc', array( __CLASS__, 'yoast_description' ), 99 );
		add_filter( 'wpseo_canonical', array( __CLASS__, 'yoast_canonical' ), 99 );
		add_filter( 'wpseo_opengraph_title', array( __CLASS__, 'yoast_title' ), 99 );
		add_filter( 'wpseo_opengraph_desc', array( __CLASS__, 'yoast_description' ), 99 );
		add_filter( 'wpseo_opengraph_url', array( __CLASS__, 'yoast_canonical' ), 99 );
		add_filter( 'wpseo_twitter_title', array( __CLASS__, 'yoast_title' ), 99 );
		add_filter( 'wpseo_twitter_description', array( __CLASS__, 'yoast_description' ), 99 );
		add_filter( 'wpseo_schema_webpage_type', array( __CLASS__, 'yoast_webpage_type' ), 99 );
		add_filter( 'wpseo_opengraph_image', array( __CLASS__, 'yoast_social_image' ), 99 );
		add_filter( 'wpseo_twitter_image', array( __CLASS__, 'yoast_social_image' ), 99 );
		add_filter( 'wpseo_opengraph_image', array( __CLASS__, 'singular_social_image' ), 100 );
		add_filter( 'wpseo_twitter_image', array( __CLASS__, 'singular_social_image' ), 100 );

		// Fallback metadata for sites without a dedicated SEO plugin.
		add_action( 'wp_head', array( __CLASS__, 'fallback_meta' ), 2 );
	}

	private static function site_name() {
		$name = trim( (string) get_bloginfo( 'name' ) );
		return $name ? $name : 'PairOfDice Media';
	}

	private static function with_site( $title ) {
		return trim( $title ) . ' | ' . self::site_name();
	}

	private static function current_url_without_query() {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = strtok( $path, '?' );
		return home_url( $path ?: '/' );
	}

	private static function generated_page_id( $option ) {
		return absint( get_option( $option ) );
	}

	private static function is_generated_page( $option, $slug ) {
		$id = self::generated_page_id( $option );
		return ( $id && is_page( $id ) ) || is_page( $slug );
	}

	/**
	 * Return normalized metadata for the current Nfinite-controlled destination.
	 * Singular releases/creators/events remain editable through normal Yoast
	 * controls and are intentionally not overridden here.
	 */
	public static function current() {
		if ( null !== self::$current ) { return self::$current; }
		self::$current = false;

		$meta = array();

		if ( class_exists( 'Nfinite_Creators_Discovery_Hubs' ) && Nfinite_Creators_Discovery_Hubs::is_hub( 'videos' ) ) {
			$filter = Nfinite_Creators_Discovery_Hubs::current_video_filter();
			if ( $filter ) {
				$label = $filter['label'];
				$meta = array(
					'title'       => self::with_site( $label . ' | Watch & Discover' ),
					'description' => sprintf( 'Watch and discover %s from creators, publishers and culture on PairOfDice Media. Browse featured and recent videos in one place.', strtolower( $label ) ),
					'canonical'   => Nfinite_Creators_Discovery_Hubs::video_filter_url( $filter['slug'] ),
					'type'        => 'CollectionPage',
				);
			} else {
				$meta = array(
					'title'       => self::with_site( 'Videos, Podcasts & Documentaries' ),
					'description' => 'Watch music videos, podcasts, interviews, documentaries, news, vlogs, live streams, movies and original voices from creators and culture.',
					'canonical'   => home_url( '/videos/' ),
					'type'        => 'CollectionPage',
				);
			}
		} elseif ( class_exists( 'Nfinite_Creators_Discovery_Hubs' ) && Nfinite_Creators_Discovery_Hubs::is_hub( 'beats' ) ) {
			$meta = array(
				'title'       => self::with_site( 'Beats & Instrumentals from Independent Producers' ),
				'description' => 'Discover beats and instrumentals from independent producers. Browse new sounds, preview tracks and connect with creators on PairOfDice Media.',
				'canonical'   => home_url( '/beats/' ),
				'type'        => 'CollectionPage',
			);
		} elseif ( is_post_type_archive( 'nfinite_release' ) ) {
			$meta = array(
				'title'       => self::with_site( 'New Music, Albums, Mixtapes & Singles' ),
				'description' => 'Discover new music from independent artists, including albums, mixtapes, singles, playlists and featured releases on PairOfDice Media.',
				'canonical'   => get_post_type_archive_link( 'nfinite_release' ) ?: home_url( '/music/' ),
				'type'        => 'CollectionPage',
			);
		} elseif ( is_post_type_archive( 'nfinite_creator_post' ) ) {
			$meta = array(
				'title'       => self::with_site( 'Creator Community & Updates' ),
				'description' => 'Discover creator updates, conversations, music, projects, polls and community posts from artists and independent creatives on PairOfDice Media.',
				'canonical'   => get_post_type_archive_link( 'nfinite_creator_post' ) ?: home_url( '/community/' ),
				'type'        => 'CollectionPage',
			);
		} elseif ( is_post_type_archive( 'nfinite_creator' ) ) {
			$meta = array(
				'title'       => self::with_site( 'Independent Artists & Creators' ),
				'description' => 'Discover independent artists, producers, designers, photographers, podcasters and other creators building what is next on PairOfDice Media.',
				'canonical'   => get_post_type_archive_link( 'nfinite_creator' ) ?: home_url( '/creators/' ),
				'type'        => 'CollectionPage',
			);
		} elseif ( is_post_type_archive( 'nfinite_event' ) ) {
			$meta = array(
				'title'       => self::with_site( 'Events, Showcases & Creator Experiences' ),
				'description' => 'Find creator events, showcases, performances, tournaments, community experiences and upcoming PairOfDice events.',
				'canonical'   => get_post_type_archive_link( 'nfinite_event' ) ?: home_url( '/events/' ),
				'type'        => 'CollectionPage',
			);
		} elseif ( self::is_generated_page( 'nfinite_radio_page_id', 'radio' ) ) {
			$meta = array(
				'title'       => self::with_site( 'PairOfDice Radio: Independent Music & Artists' ),
				'description' => 'Listen to PairOfDice Radio for a continuous rotation of independent music, artists, producers and tracks from the PairOfDice community.',
				'canonical'   => class_exists( 'Nfinite_Creators_Radio' ) ? Nfinite_Creators_Radio::page_url() : home_url( '/radio/' ),
				'type'        => 'WebPage',
			);
		} elseif ( self::is_generated_page( 'nfinite_all_releases_page_id', 'all-releases' ) ) {
			$meta = array(
				'title'       => self::with_site( 'All Music Releases: Albums, Mixtapes & Singles' ),
				'description' => 'Browse the complete PairOfDice music library, including albums, EPs, mixtapes, singles, compilations and playlists from independent artists.',
				'canonical'   => class_exists( 'Nfinite_Creators_Release_Library' ) ? Nfinite_Creators_Release_Library::page_url() : home_url( '/all-releases/' ),
				'type'        => 'CollectionPage',
			);
		} elseif ( is_tax( 'nfinite_release_type' ) ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$name = $term->name;
				$meta = array(
					'title'       => self::with_site( $name . ' Music Releases' ),
					'description' => sprintf( 'Browse %s releases from independent artists on PairOfDice Media. Discover music, creators and new projects from the PairOfDice community.', strtolower( $name ) ),
					'canonical'   => get_term_link( $term ),
					'type'        => 'CollectionPage',
				);
			}
		} elseif ( is_tax( 'nfinite_creator_type' ) ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$meta = array(
					'title'       => self::with_site( $term->name . ' Creators' ),
					'description' => sprintf( 'Discover independent %s on PairOfDice Media. Explore creator profiles, music, projects, videos and ways to connect.', strtolower( $term->name ) ),
					'canonical'   => get_term_link( $term ),
					'type'        => 'CollectionPage',
				);
			}
		} elseif ( is_tax( 'nfinite_event_type' ) ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$meta = array(
					'title'       => self::with_site( $term->name . ' Events' ),
					'description' => sprintf( 'Browse %s events, experiences and upcoming community activities from PairOfDice Media and participating creators.', strtolower( $term->name ) ),
					'canonical'   => get_term_link( $term ),
					'type'        => 'CollectionPage',
				);
			}
		} elseif ( is_singular( 'nfinite_creator' ) && get_query_var( 'nfinite_creator_wall' ) ) {
			$creator_id = get_queried_object_id();
			$name       = get_the_title( $creator_id );
			$meta = array(
				'title'       => self::with_site( $name . ' Updates & Community Posts' ),
				'description' => sprintf( 'Follow the latest updates, posts, projects and conversations from %s on PairOfDice Media.', $name ),
				'canonical'   => class_exists( 'Nfinite_Creators_Publishing' ) ? Nfinite_Creators_Publishing::creator_wall_url( $creator_id ) : self::current_url_without_query(),
				'type'        => 'CollectionPage',
			);
		}

		if ( $meta && ! is_wp_error( $meta['canonical'] ) ) {
			$meta['canonical'] = esc_url_raw( $meta['canonical'] );
			self::$current = $meta;
		}

		return self::$current;
	}

	public static function document_title( $title ) {
		$meta = self::current();
		return $meta ? $meta['title'] : $title;
	}

	public static function yoast_title( $title ) {
		$meta = self::current();
		return $meta ? $meta['title'] : $title;
	}

	public static function yoast_description( $description ) {
		$meta = self::current();
		return $meta ? $meta['description'] : $description;
	}

	public static function yoast_canonical( $canonical ) {
		$meta = self::current();
		return $meta ? $meta['canonical'] : $canonical;
	}

	public static function yoast_webpage_type( $type ) {
		$meta = self::current();
		return $meta && ! empty( $meta['type'] ) ? $meta['type'] : $type;
	}

	private static function social_image_url() {
		$custom_logo_id = absint( get_theme_mod( 'custom_logo' ) );
		if ( $custom_logo_id ) {
			$image = wp_get_attachment_image_url( $custom_logo_id, 'full' );
			if ( $image ) { return $image; }
		}
		$site_icon = get_site_icon_url( 512 );
		return $site_icon ? $site_icon : '';
	}

	public static function yoast_social_image( $image ) {
		if ( ! self::current() ) { return $image; }
		$fallback = self::social_image_url();
		return $image ?: $fallback;
	}

	/** Use canonical artwork on music and creator links, without overriding editor-selected Yoast images. */
	private static function singular_artwork() {
		if ( ! is_singular( array( 'nfinite_release', 'nfinite_creator', 'nfinite_track' ) ) ) { return ''; }
		$id = get_queried_object_id();
		if ( ! $id ) { return ''; }
		$image = get_the_post_thumbnail_url( $id, 'full' );
		if ( $image ) { return $image; }
		if ( 'nfinite_track' === get_post_type( $id ) ) {
			$release = absint( get_post_meta( $id, '_nfinite_track_release_id', true ) );
			if ( $release ) { $image = get_the_post_thumbnail_url( $release, 'full' ); }
		}
		return $image ? $image : '';
	}

	public static function singular_social_image( $image ) {
		if ( ! is_singular( array( 'nfinite_release', 'nfinite_creator', 'nfinite_track' ) ) ) { return $image; }
		$id = get_queried_object_id();
		// Preserve manually chosen social artwork in Yoast, if any.
		$custom = get_post_meta( $id, '_yoast_wpseo_opengraph-image', true );
		$custom_id = absint( get_post_meta( $id, '_yoast_wpseo_opengraph-image-id', true ) );
		if ( $custom_id ) { $custom = wp_get_attachment_image_url( $custom_id, 'full' ) ?: $custom; }
		if ( $custom ) { return esc_url_raw( $custom ); }
		$art = self::singular_artwork();
		return $art ? esc_url_raw( $art ) : $image;
	}

	private static function has_seo_plugin() {
		return defined( 'WPSEO_VERSION' )
			|| defined( 'RANK_MATH_VERSION' )
			|| defined( 'AIOSEO_VERSION' )
			|| class_exists( 'WPSEO_Options' )
			|| class_exists( 'RankMath' );
	}

	public static function fallback_meta() {
		$meta = self::current();
		if ( ! $meta || self::has_seo_plugin() ) { return; }

		$image = self::social_image_url();
		?>
		<meta name="description" content="<?php echo esc_attr( $meta['description'] ); ?>">
		<link rel="canonical" href="<?php echo esc_url( $meta['canonical'] ); ?>">
		<meta property="og:type" content="website">
		<meta property="og:title" content="<?php echo esc_attr( $meta['title'] ); ?>">
		<meta property="og:description" content="<?php echo esc_attr( $meta['description'] ); ?>">
		<meta property="og:url" content="<?php echo esc_url( $meta['canonical'] ); ?>">
		<?php if ( $image ) : ?><meta property="og:image" content="<?php echo esc_url( $image ); ?>"><?php endif; ?>
		<meta name="twitter:card" content="summary_large_image">
		<meta name="twitter:title" content="<?php echo esc_attr( $meta['title'] ); ?>">
		<meta name="twitter:description" content="<?php echo esc_attr( $meta['description'] ); ?>">
		<?php if ( $image ) : ?><meta name="twitter:image" content="<?php echo esc_url( $image ); ?>"><?php endif; ?>
		<script type="application/ld+json"><?php echo wp_json_encode( array(
			'@context'     => 'https://schema.org',
			'@type'        => $meta['type'],
			'name'         => $meta['title'],
			'description'  => $meta['description'],
			'url'          => $meta['canonical'],
			'isPartOf'     => array( '@type' => 'WebSite', 'name' => self::site_name(), 'url' => home_url( '/' ) ),
		), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
		<?php
	}
}
