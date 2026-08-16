<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_CPT {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
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
		);

		foreach ( $defaults as $term ) {
			if ( ! term_exists( $term, 'nfinite_creator_type' ) ) {
				wp_insert_term( $term, 'nfinite_creator_type' );
			}
		}
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
