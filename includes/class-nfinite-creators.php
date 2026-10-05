<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Nfinite_Creators {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-programming.php';
		Nfinite_Creators_Programming::init();
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-cpt.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-admin.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-meta.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-identity.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-roles.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-social-auth.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-auth.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-frontend.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-commerce.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-commerce-dashboard.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-payments.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-financial-admin.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-hardening.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-printful.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-audio.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-video.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-tv.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-shows.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-profile.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-analytics.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-analytics-hub.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-engagement.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-video-engagement.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-streaming-earnings.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-video-earnings.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-monetization.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-ad-delivery.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-promoted-content.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-opportunities.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-memberships.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-organizations.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-library.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-discovery.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-search.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-notifications.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-playlists.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-publishing.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-seo.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-audio-sources.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-internet-archive.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-music-cpt.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-track-commerce.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-music-query.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-music-hub.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-discovery-hubs.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-music-meta.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-music-monetization.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-distribution-catalog.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-music-player.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-radio.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-release-library.php';
		require_once NFINITE_CREATORS_DIR . 'includes/music/class-nfinite-creators-music-migration.php';
		require_once NFINITE_CREATORS_DIR . 'includes/events/class-nfinite-creators-events-cpt.php';
		require_once NFINITE_CREATORS_DIR . 'includes/events/class-nfinite-creators-events-query.php';
		require_once NFINITE_CREATORS_DIR . 'includes/events/class-nfinite-creators-events-meta.php';
		require_once NFINITE_CREATORS_DIR . 'includes/events/class-nfinite-creators-events-studio.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-templates.php';

		Nfinite_Creators_CPT::init();
		Nfinite_Creators_Admin::init();
		Nfinite_Creators_Meta::init();
		Nfinite_Creators_Identity::init();
		Nfinite_Creators_Roles::init();
		Nfinite_Creators_Social_Auth::init();
		Nfinite_Creators_Auth::init();
		Nfinite_Creators_Frontend::init();
		Nfinite_Creators_Commerce::init();
		Nfinite_Creators_Commerce_Dashboard::init();
		Nfinite_Creators_Payments::init();
		Nfinite_Creators_Financial_Admin::init();
		Nfinite_Creators_Hardening::init();
		Nfinite_Creators_Printful::init();
		Nfinite_Creators_Audio::init();
		Nfinite_Creators_Video::init();
		Nfinite_Creators_TV::init();
		Nfinite_Creators_Shows::init();
		Nfinite_Creators_Analytics::init();
		Nfinite_Creators_Analytics_Hub::init();
		Nfinite_Creators_Engagement::init();
		Nfinite_Creators_Video_Engagement::init();
		Nfinite_Creators_Streaming_Earnings::init();
		Nfinite_Creators_Video_Earnings::init();
		Nfinite_Creators_Monetization::init();
		Nfinite_Creators_Ad_Delivery::init();
		Nfinite_Creators_Promoted_Content::init();
		Nfinite_Creators_Opportunities::init();
		Nfinite_Creators_Memberships::init();
		Nfinite_Creators_Organizations::init();
		Nfinite_Creators_Library::init();
		Nfinite_Creators_Discovery::init();
		Nfinite_Creators_Search::init();
		Nfinite_Creators_Notifications::init();
		Nfinite_Creators_Playlists::init();
		Nfinite_Creators_Publishing::init();
		Nfinite_Creators_SEO::init();
		Nfinite_Creators_Internet_Archive::init();
		Nfinite_Creators_Music_CPT::init();
		Nfinite_Creators_Music_Meta::init();
		Nfinite_Creators_Music_Monetization::init();
		Nfinite_Creators_Distribution_Catalog::init();
		Nfinite_Creators_Track_Commerce::init();
		Nfinite_Creators_Music_Hub::init();
		Nfinite_Creators_Discovery_Hubs::init();
		Nfinite_Creators_Music_Player::init();
		Nfinite_Creators_Radio::init();
		Nfinite_Creators_Release_Library::init();
		Nfinite_Creators_Music_Migration::init();
		Nfinite_Creators_Events_CPT::init();
		Nfinite_Creators_Events_Meta::init();
		Nfinite_Creators_Events_Studio::init();
		Nfinite_Creators_Templates::init();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'body_class', array( $this, 'body_classes' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_version_marker' ), 999 );
	}

	public function enqueue_assets() {
		wp_enqueue_style(
			'nfinite-creators',
			NFINITE_CREATORS_URL . 'public/css/nfinite-creators.css',
			array(),
			NFINITE_CREATORS_VERSION
		);

		wp_enqueue_script(
			'nfinite-creators',
			NFINITE_CREATORS_URL . 'public/js/nfinite-creators.js',
			array(),
			NFINITE_CREATORS_VERSION,
			true
		);

		wp_localize_script( 'nfinite-creators', 'NfiniteCreatorEngagement', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'nfinite_creator_engagement' ) ) );

		wp_localize_script( 'nfinite-creators', 'NfiniteCreatorLibrary', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'nfinite_library' ) ) );

		wp_enqueue_script(
			'nfinite-creators-video-player',
			NFINITE_CREATORS_URL . 'public/js/nfinite-video-player.js',
			array( 'nfinite-creators' ),
			NFINITE_CREATORS_VERSION,
			true
		);

		wp_enqueue_script(
			'nfinite-creators-music-player',
			NFINITE_CREATORS_URL . 'public/js/nfinite-music-player.js',
			array( 'nfinite-creators-video-player' ),
			NFINITE_CREATORS_VERSION,
			true
		);
	}

	public function body_classes( $classes ) {
		$classes[] = 'nfinite-creators-active';
		$classes[] = 'nfinite-music-library-active';
		$classes[] = 'nfinite-events-active';
		$classes[] = 'nfinite-creators-v' . str_replace( '.', '-', NFINITE_CREATORS_VERSION );
		if ( wp_get_theme()->get( 'Name' ) === 'WPNfinite' || wp_get_theme()->get_template() === 'wpnfinite' ) {
			$classes[] = 'nfinite-creators-wpnfinite';
		}
		return $classes;
	}


	public function admin_bar_version_marker( $wp_admin_bar ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'nfinite-creators-version',
				'title' => sprintf( 'Nfinite Creators %s', NFINITE_CREATORS_VERSION ),
				'href'  => admin_url( 'plugins.php' ),
				'meta'  => array(
					'title' => sprintf( 'Active Nfinite Creators version: %s', NFINITE_CREATORS_VERSION ),
				),
			)
		);
	}

	public static function activate() {
		Nfinite_Creators_Roles::register_role();
		Nfinite_Creators_CPT::register();
		Nfinite_Creators_Auth::ensure_pages();
		Nfinite_Creators_Music_CPT::register();
		Nfinite_Creators_Radio::ensure_page();
		Nfinite_Creators_TV::ensure_page();
		Nfinite_Creators_Shows::register();
		Nfinite_Creators_Release_Library::ensure_page();
		Nfinite_Creators_Discovery_Hubs::rewrites();
		Nfinite_Creators_Events_CPT::register();
		Nfinite_Creators_Organizations::register();
		Nfinite_Creators_Payments::install();
		Nfinite_Creators_Financial_Admin::install();
		Nfinite_Creators_Analytics::install();
		Nfinite_Creators_Engagement::install();
		Nfinite_Creators_Video_Engagement::install();
		Nfinite_Creators_Video_Earnings::install();
		Nfinite_Creators_Monetization::install();
		Nfinite_Creators_Memberships::register();
		Nfinite_Creators_Publishing::register();
		Nfinite_Creators_Notifications::install();
		Nfinite_Creators_Playlists::register();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
