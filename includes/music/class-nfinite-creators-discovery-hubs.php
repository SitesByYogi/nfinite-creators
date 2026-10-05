<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Discovery_Hubs {
	const OPTION = 'nfinite_discovery_hub_settings';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrites' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 60 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_flush_rewrites' ), 20 );
		add_shortcode( 'nfinite_beats_hub', array( __CLASS__, 'beats_shortcode' ) );
		add_shortcode( 'nfinite_videos_hub', array( __CLASS__, 'videos_shortcode' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
	}

	public static function maybe_flush_rewrites() {
		$version = '0.32.2-video-routes';
		if ( get_option( 'nfinite_discovery_rewrite_version' ) === $version ) { return; }
		self::rewrites();
		flush_rewrite_rules( false );
		update_option( 'nfinite_discovery_rewrite_version', $version, false );
	}

	public static function rewrites() {
		add_rewrite_rule( '^beats/?$', 'index.php?nfinite_discovery_hub=beats', 'top' );
		add_rewrite_rule( '^videos/?$', 'index.php?nfinite_discovery_hub=videos', 'top' );
		add_rewrite_rule( '^videos/(music-videos|podcasts|vlogs|live|news|commentary|documentaries|movies|interviews)/?$', 'index.php?nfinite_discovery_hub=videos&nfinite_video_filter=$matches[1]', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'nfinite_discovery_hub';
		$vars[] = 'nfinite_video_filter';
		return $vars;
	}

	public static function is_hub( $hub = '' ) {
		$current = sanitize_key( get_query_var( 'nfinite_discovery_hub' ) );
		return $hub ? $hub === $current : in_array( $current, array( 'beats', 'videos' ), true );
	}


	public static function body_classes( $classes ) {
		if ( self::is_hub( 'videos' ) ) {
			$classes[] = 'nfinite-videos-route';
		}
		if ( self::is_hub( 'beats' ) ) {
			$classes[] = 'nfinite-beats-route';
		}
		return $classes;
	}

	public static function template( $template ) {
		if ( self::is_hub( 'beats' ) ) {
			$theme = locate_template( array( 'nfinite-creators/beats-hub.php' ) );
			return $theme ? $theme : NFINITE_CREATORS_DIR . 'templates/music/beats-hub.php';
		}
		if ( self::is_hub( 'videos' ) ) {
			$theme = locate_template( array( 'nfinite-creators/videos-hub.php' ) );
			return $theme ? $theme : NFINITE_CREATORS_DIR . 'templates/music/videos-hub.php';
		}
		return $template;
	}

	public static function defaults() {
		return array(
			'beats_eyebrow' => 'Create', 'beats_title' => 'Beats',
			'beats_intro' => 'Find your next sound. Discover beats and instrumentals from independent producers and creators.',
			'beats_count' => 24, 'beats_creator_count' => 4,
			'videos_eyebrow' => 'Watch', 'videos_title' => 'Videos',
			'videos_intro' => 'Watch what’s moving culture — music, podcasts, vlogs, streams, news, documentaries, movies and original voices.',
			'videos_count' => 24, 'videos_creator_count' => 4,
		);
	}

	public static function settings() { return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() ); }

	public static function register_settings() {
		register_setting( 'nfinite_discovery_hubs_group', self::OPTION, array( 'type'=>'array', 'sanitize_callback'=>array(__CLASS__,'sanitize'), 'default'=>self::defaults() ) );
	}

	public static function sanitize( $raw ) {
		$d = self::defaults(); $raw = is_array($raw) ? $raw : array(); $clean = array();
		foreach ( array('beats_eyebrow','beats_title','videos_eyebrow','videos_title') as $k ) $clean[$k] = isset($raw[$k]) ? sanitize_text_field($raw[$k]) : $d[$k];
		foreach ( array('beats_intro','videos_intro') as $k ) $clean[$k] = isset($raw[$k]) ? sanitize_textarea_field($raw[$k]) : $d[$k];
		foreach ( array('beats_count'=>array(4,60),'videos_count'=>array(4,60),'beats_creator_count'=>array(1,12),'videos_creator_count'=>array(1,12)) as $k=>$limits ) {
			$v = isset($raw[$k]) ? absint($raw[$k]) : $d[$k]; $clean[$k] = max($limits[0], min($limits[1], $v));
		}
		return $clean;
	}

	public static function admin_menu() {
		add_submenu_page( 'edit.php?post_type=nfinite_release', 'Discovery Hubs', 'Beats & Videos', 'manage_options', 'nfinite-discovery-hubs', array(__CLASS__,'settings_page') );
	}

	public static function settings_page() {
		if ( ! current_user_can('manage_options') ) return; $s = self::settings();
		?>
		<div class="wrap"><h1><?php esc_html_e('Nfinite Beats & Videos Hubs','nfinite-creators'); ?></h1>
		<p><?php esc_html_e('Configure the dynamic /beats/ and /videos/ discovery pages. Both pages use existing Nfinite Creator media and update automatically as content is published.','nfinite-creators'); ?></p>
		<form method="post" action="options.php"><?php settings_fields('nfinite_discovery_hubs_group'); ?>
		<?php foreach ( array('beats'=>'Beats Hub','videos'=>'Videos Hub') as $prefix=>$label ) : ?>
		<h2><?php echo esc_html($label); ?></h2><table class="form-table" role="presentation">
		<tr><th>Eyebrow</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($prefix); ?>_eyebrow]" value="<?php echo esc_attr($s[$prefix.'_eyebrow']); ?>"></td></tr>
		<tr><th>Title</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($prefix); ?>_title]" value="<?php echo esc_attr($s[$prefix.'_title']); ?>"></td></tr>
		<tr><th>Introduction</th><td><textarea class="large-text" rows="3" name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($prefix); ?>_intro]"><?php echo esc_textarea($s[$prefix.'_intro']); ?></textarea></td></tr>
		<tr><th>Items</th><td><input type="number" min="4" max="60" name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($prefix); ?>_count]" value="<?php echo esc_attr($s[$prefix.'_count']); ?>"></td></tr>
		<tr><th>Featured Creators</th><td><input type="number" min="1" max="12" name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($prefix); ?>_creator_count]" value="<?php echo esc_attr($s[$prefix.'_creator_count']); ?>"></td></tr>
		</table><?php endforeach; ?><?php submit_button(); ?></form>
		<hr><p><strong>Beats:</strong> <code><?php echo esc_html(home_url('/beats/')); ?></code> &nbsp; <code>[nfinite_beats_hub]</code></p>
		<p><strong>Videos:</strong> <code><?php echo esc_html(home_url('/videos/')); ?></code> &nbsp; <code>[nfinite_videos_hub]</code></p></div><?php
	}

	public static function video_types( $videos ) {
		$groups = array();
		foreach ( $videos as $v ) {
			$raw_type = ! empty( $v['type'] ) ? $v['type'] : 'Other';
			$type = class_exists( 'Nfinite_Creators_Video' ) ? Nfinite_Creators_Video::hub_category( $raw_type ) : $raw_type;
			$groups[ $type ][] = $v;
		}
		return $groups;
	}

	public static function video_thumbnail( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( false !== strpos( $host, 'youtu' ) ) {
			$id = '';
			if ( false !== strpos( $host, 'youtu.be' ) ) {
				$id = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
			} else {
				parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
				$id = isset( $q['v'] ) ? sanitize_text_field( $q['v'] ) : '';
				if ( ! $id && preg_match( '#/(?:embed|shorts)/([^/?]+)#', $url, $m ) ) $id = $m[1];
			}
			if ( $id ) return 'https://i.ytimg.com/vi/' . rawurlencode( $id ) . '/hqdefault.jpg';
		}
		if ( false !== strpos( $host, 'archive.org' ) ) {
			$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
			if ( preg_match( '#^(?:details|embed)/([^/]+)#', $path, $m ) ) {
				return 'https://archive.org/services/img/' . rawurlencode( $m[1] );
			}
		}
		$data = wp_oembed_get( $url );
		if ( $data ) {
			$response = wp_oembed_get( $url ); // warm provider cache without exposing iframe in the grid.
		}
		$provider = _wp_oembed_get_object()->get_data( $url );
		return ( $provider && ! empty( $provider->thumbnail_url ) ) ? esc_url_raw( $provider->thumbnail_url ) : '';
	}

	public static function video_embed_url( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$host = preg_replace( '/^www\./', '', $host );
		if ( 'archive.org' === $host ) {
			$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
			if ( preg_match( '#^(?:details|embed)/([^/]+)#', $path, $m ) ) {
				return esc_url_raw( 'https://archive.org/embed/' . rawurlencode( $m[1] ) );
			}
		}
		$embed = wp_oembed_get( $url, array( 'width' => 1200 ) );
		if ( ! $embed || ! preg_match( '/src=["\\\']([^"\\\']+)/i', $embed, $m ) ) return '';
		return esc_url_raw( html_entity_decode( $m[1] ) );
	}

	public static function beats_shortcode() { return self::render_beats(); }
	public static function videos_shortcode() { return self::render_videos(); }
	public static function render_beats() {
		$s=self::settings(); $beats=Nfinite_Creators_Music_Hub::creator_beats($s['beats_count']); $creators=Nfinite_Creators_Music_Hub::featured_creators($s['beats_creator_count']);
		ob_start(); include NFINITE_CREATORS_DIR.'templates/music/beats-hub-content.php'; return ob_get_clean();
	}
	public static function video_filter_map() {
		return array(
			'music-videos'  => array( 'label' => 'Music Videos',  'category' => 'Music Video' ),
			'podcasts'      => array( 'label' => 'Podcasts',      'category' => 'Podcast' ),
			'vlogs'         => array( 'label' => 'Vlogs',         'category' => 'Vlog' ),
			'live'          => array( 'label' => 'Live',          'category' => 'Live Stream' ),
			'news'          => array( 'label' => 'News',          'category' => 'News' ),
			'commentary'    => array( 'label' => 'Commentary',    'category' => 'Commentary' ),
			'documentaries' => array( 'label' => 'Documentaries', 'category' => 'Documentary' ),
			'movies'        => array( 'label' => 'Movies',        'category' => 'Movie' ),
			'interviews'    => array( 'label' => 'Interviews',    'category' => 'Interview' ),
		);
	}

	public static function current_video_filter() {
		$slug = sanitize_key( get_query_var( 'nfinite_video_filter' ) );
		$map  = self::video_filter_map();
		return isset( $map[ $slug ] ) ? array_merge( array( 'slug' => $slug ), $map[ $slug ] ) : array();
	}

	public static function video_filter_url( $slug = '' ) {
		$slug = sanitize_key( $slug );
		if ( ! $slug || ! isset( self::video_filter_map()[ $slug ] ) ) {
			return home_url( '/videos/' );
		}
		return home_url( '/videos/' . $slug . '/' );
	}

	public static function render_videos() {
		$s       = self::settings();
		$filter  = self::current_video_filter();
		$limit   = $filter ? max( 60, (int) $s['videos_count'] ) : (int) $s['videos_count'];
		$videos  = Nfinite_Creators_Music_Hub::latest_videos( $limit );

		if ( $filter ) {
			$videos = array_values( array_filter( $videos, function( $video ) use ( $filter ) {
				$raw = ! empty( $video['type'] ) ? $video['type'] : 'Other';
				$category = class_exists( 'Nfinite_Creators_Video' ) ? Nfinite_Creators_Video::hub_category( $raw ) : $raw;
				return $category === $filter['category'];
			} ) );
			$s['videos_eyebrow'] = 'Watch';
			$s['videos_title']   = $filter['label'];
			$s['videos_intro']   = sprintf( 'Browse the latest %s from creators, publishers and culture on PairOfDice Media.', strtolower( $filter['label'] ) );
		}

		$groups   = self::video_types( $videos );
		$creators = Nfinite_Creators_Music_Hub::featured_creators( $s['videos_creator_count'] );
		ob_start(); include NFINITE_CREATORS_DIR.'templates/music/videos-hub-content.php'; return ob_get_clean();
	}
}
