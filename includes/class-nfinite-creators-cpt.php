<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_CPT {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'default_creator_archive_to_artists' ) );
	}

	public static function register() {
		register_post_type(
			'nfinite_creator',
			array(
				'labels' => array(
					'name'               => __( 'Creators', 'nfinite-creators' ),
					'singular_name'      => __( 'Creator', 'nfinite-creators' ),
					'add_new_item'       => __( 'Add Creator', 'nfinite-creators' ),
					'edit_item'          => __( 'Edit Creator', 'nfinite-creators' ),
					'view_item'          => __( 'View Creator', 'nfinite-creators' ),
					'search_items'       => __( 'Search Creators', 'nfinite-creators' ),
					'not_found'          => __( 'No creators found.', 'nfinite-creators' ),
				),
				'public'             => true,
				'show_in_rest'       => true,
				'has_archive'        => true,
				'rewrite'            => array( 'slug' => 'creators', 'with_front' => false ),
				'menu_icon'          => 'dashicons-groups',
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
				'menu_position'      => 25,
				'publicly_queryable' => true,
			)
		);

		register_taxonomy(
			'nfinite_creator_type',
			array( 'nfinite_creator' ),
			array(
				'labels' => array(
					'name'          => __( 'Creator Types', 'nfinite-creators' ),
					'singular_name' => __( 'Creator Type', 'nfinite-creators' ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => false,
				'rewrite'           => array( 'slug' => 'creator-type', 'with_front' => false ),
			)
		);

		$defaults = array(
			'Artist',
			'Producer',
			'Designer',
			'Developer',
			'Audio Engineer',
			'Photographer',
			'Videographer',
			'DJ',
			'Songwriter',
			'Writer',
			'Blogger',
			'Podcaster',
			'Streamer',
		);

		foreach ( $defaults as $term ) {
			if ( ! term_exists( $term, 'nfinite_creator_type' ) ) {
				wp_insert_term( $term, 'nfinite_creator_type' );
			}
		}
	}

	/**
	 * Make the Artist view the default state of /creators/ while preserving
	 * an explicit All view at /creators/?type=all.
	 */
	public static function default_creator_archive_to_artists( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'nfinite_creator' ) ) {
			return;
		}

		$requested_type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Keep the public creator directory in a balanced 3/4-column grid by
		// showing 12 creator profiles per archive page.
		$query->set( 'posts_per_page', 12 );
		if ( 'all' === $requested_type ) {
			return;
		}

		// The bare /creators/ archive should open on Artists. A future explicit
		// type query remains available without changing the canonical archive URL.
		$slug = $requested_type ? $requested_type : 'artist';
		$term = get_term_by( 'slug', $slug, 'nfinite_creator_type' );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => 'nfinite_creator_type',
					'field'    => 'term_id',
					'terms'    => array( (int) $term->term_id ),
				),
			)
		);
	}

	public static function columns( $columns ) {
		$columns['creator_type'] = __( 'Creator Type', 'nfinite-creators' );
		$columns['creator_owner'] = __( 'Owner', 'nfinite-creators' );
		$columns['creator_kit'] = __( 'Creator Kit', 'nfinite-creators' );
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
		if ( 'creator_kit' === $column ) {
			echo get_post_meta( $post_id, '_nfinite_creator_kit_enabled', true ) ? esc_html__( 'Enabled', 'nfinite-creators' ) : '—';
		}
	}
}
