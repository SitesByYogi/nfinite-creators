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
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-cpt.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-admin.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-meta.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-frontend.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-audio.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-video.php';
		require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators-templates.php';

		Nfinite_Creators_CPT::init();
		Nfinite_Creators_Admin::init();
		Nfinite_Creators_Meta::init();
		Nfinite_Creators_Frontend::init();
		Nfinite_Creators_Audio::init();
		Nfinite_Creators_Video::init();
		Nfinite_Creators_Templates::init();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'body_class', array( $this, 'body_classes' ) );
	}

	public function enqueue_assets() {
		if (
			is_post_type_archive( 'nfinite_creator' ) ||
			is_singular( 'nfinite_creator' ) ||
			has_shortcode( get_post_field( 'post_content', get_queried_object_id() ), 'nfinite_creator_directory' ) ||
			has_shortcode( get_post_field( 'post_content', get_queried_object_id() ), 'nfinite_creator_dashboard' ) ||
			has_shortcode( get_post_field( 'post_content', get_queried_object_id() ), 'nfinite_creator_audio' )
		) {
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
		}
	}

	public function body_classes( $classes ) {
		$classes[] = 'nfinite-creators-active';
		if ( wp_get_theme()->get( 'Name' ) === 'WPNfinite' || wp_get_theme()->get_template() === 'wpnfinite' ) {
			$classes[] = 'nfinite-creators-wpnfinite';
		}
		return $classes;
	}

	public static function activate() {
		Nfinite_Creators_CPT::register();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
