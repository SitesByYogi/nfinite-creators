<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Admin {
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'manage_nfinite_creator_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_nfinite_creator_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();

		if ( ! $screen || 'nfinite_creator' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'nfinite-creators-admin',
			NFINITE_CREATORS_URL . 'admin/css/nfinite-creators-admin.css',
			array(),
			NFINITE_CREATORS_VERSION
		);

		wp_enqueue_script(
			'nfinite-creators-admin',
			NFINITE_CREATORS_URL . 'admin/js/nfinite-creators-admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			NFINITE_CREATORS_VERSION,
			true
		);

		wp_localize_script(
			'nfinite-creators-admin',
			'NfiniteCreatorsAdmin',
			array(
				'selectAudio'   => __( 'Select audio', 'nfinite-creators' ),
				'useAudio'      => __( 'Use audio', 'nfinite-creators' ),
				'selectImage'   => __( 'Select image', 'nfinite-creators' ),
				'useImage'      => __( 'Use image', 'nfinite-creators' ),
				'trackLabel'    => __( 'Track', 'nfinite-creators' ),
				'remove'        => __( 'Remove', 'nfinite-creators' ),
				'untitledTrack' => __( 'Untitled Track', 'nfinite-creators' ),
				'videoLabel'    => __( 'Video', 'nfinite-creators' ),
				'featuredVideo' => __( 'Featured Video', 'nfinite-creators' ),
			)
		);
	}

	public static function columns( $columns ) {
		$columns['creator_type']     = __( 'Creator Type', 'nfinite-creators' );
		$columns['creator_owner']    = __( 'Owner', 'nfinite-creators' );
		$columns['creator_featured'] = __( 'Featured', 'nfinite-creators' );
		$columns['creator_tracks']   = __( 'Tracks', 'nfinite-creators' );
		$columns['creator_videos']   = __( 'Videos', 'nfinite-creators' );
		$columns['creator_kit']      = __( 'Creator Kit', 'nfinite-creators' );
		return $columns;
	}

	public static function column_content( $column, $post_id ) {
		if ( 'creator_type' === $column ) {
			$terms = get_the_terms( $post_id, 'nfinite_creator_type' );
			echo esc_html( $terms && ! is_wp_error( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '—' );
		}

		if ( 'creator_owner' === $column ) {
			$user = get_user_by( 'id', (int) get_post_field( 'post_author', $post_id ) );
			echo esc_html( $user ? $user->display_name : '—' );
		}

		if ( 'creator_featured' === $column ) {
			echo get_post_meta( $post_id, '_nfinite_creator_featured', true ) ? esc_html__( 'Yes', 'nfinite-creators' ) : '—';
		}

		if ( 'creator_tracks' === $column ) {
			$tracks = get_post_meta( $post_id, '_nfinite_creator_tracks', true );
			echo esc_html( is_array( $tracks ) ? count( $tracks ) : 0 );
		}

		if ( 'creator_videos' === $column ) {
			$videos = get_post_meta( $post_id, '_nfinite_creator_videos', true );
			echo esc_html( is_array( $videos ) ? count( $videos ) : 0 );
		}

		if ( 'creator_kit' === $column ) {
			echo get_post_meta( $post_id, '_nfinite_creator_kit_enabled', true ) ? esc_html__( 'Enabled', 'nfinite-creators' ) : '—';
		}
	}
}
