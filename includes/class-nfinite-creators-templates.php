<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Templates {
	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template' ), 50 );
	}

	public static function template( $template ) {
		if ( is_singular( 'nfinite_event' ) ) {
			$theme_template = locate_template( array( 'nfinite-creators/single-event.php' ) );
			return $theme_template ? $theme_template : NFINITE_CREATORS_DIR . 'templates/events/single-event.php';
		}

		if ( is_post_type_archive( 'nfinite_event' ) || is_tax( 'nfinite_event_type' ) ) {
			$theme_template = locate_template( array( 'nfinite-creators/archive-events.php' ) );
			return $theme_template ? $theme_template : NFINITE_CREATORS_DIR . 'templates/events/archive-events.php';
		}

		if ( is_singular( 'nfinite_release' ) ) {
			$theme_template = locate_template( array( 'nfinite-creators/single-release.php' ) );
			return $theme_template ? $theme_template : NFINITE_CREATORS_DIR . 'templates/music/single-release.php';
		}

		if ( is_post_type_archive( 'nfinite_release' ) || is_tax( 'nfinite_release_type' ) ) {
			$theme_template = locate_template( array( 'nfinite-creators/archive-releases.php' ) );
			return $theme_template ? $theme_template : NFINITE_CREATORS_DIR . 'templates/music/archive-releases.php';
		}

		if ( is_singular( 'nfinite_creator' ) ) {
			$theme_template = locate_template( array( 'nfinite-creators/single-creator.php' ) );
			return $theme_template ? $theme_template : NFINITE_CREATORS_DIR . 'templates/single-creator.php';
		}

		if ( is_post_type_archive( 'nfinite_creator' ) || is_tax( 'nfinite_creator_type' ) ) {
			$theme_template = locate_template( array( 'nfinite-creators/archive-creators.php' ) );
			return $theme_template ? $theme_template : NFINITE_CREATORS_DIR . 'templates/archive-creators.php';
		}

		return $template;
	}
}
