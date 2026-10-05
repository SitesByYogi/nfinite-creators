<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Music_CPT {
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register' ) );
    }

    public static function register() {
        register_post_type( 'nfinite_release', array(
            'labels' => array(
                'name' => __( 'Releases', 'nfinite-creators' ),
                'singular_name' => __( 'Release', 'nfinite-creators' ),
                'add_new_item' => __( 'Add Release', 'nfinite-creators' ),
                'edit_item' => __( 'Edit Release', 'nfinite-creators' ),
                'view_item' => __( 'View Release', 'nfinite-creators' ),
                'search_items' => __( 'Search Releases', 'nfinite-creators' ),
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_in_rest' => true,
            'has_archive' => 'music',
            'rewrite' => array( 'slug' => 'music', 'with_front' => false ),
            'menu_icon' => 'dashicons-album',
            'menu_position' => 26,
            'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
        ) );

        register_post_type( 'nfinite_track', array(
            'labels' => array(
                'name' => __( 'Tracks', 'nfinite-creators' ),
                'singular_name' => __( 'Track', 'nfinite-creators' ),
                'add_new_item' => __( 'Add Track', 'nfinite-creators' ),
                'edit_item' => __( 'Edit Track', 'nfinite-creators' ),
                'search_items' => __( 'Search Tracks', 'nfinite-creators' ),
            ),
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-format-audio',
            'menu_position' => 27,
            'supports' => array( 'title', 'editor', 'thumbnail', 'revisions' ),
        ) );

        register_taxonomy( 'nfinite_release_type', array( 'nfinite_release' ), array(
            'labels' => array(
                'name' => __( 'Release Types', 'nfinite-creators' ),
                'singular_name' => __( 'Release Type', 'nfinite-creators' ),
            ),
            'public' => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'rewrite' => array( 'slug' => 'release-type', 'with_front' => false ),
        ) );

        foreach ( array( 'Album', 'EP', 'Mixtape', 'Single', 'Compilation', 'Playlist' ) as $term ) {
            if ( ! term_exists( $term, 'nfinite_release_type' ) ) {
                wp_insert_term( $term, 'nfinite_release_type' );
            }
        }
    }
}
