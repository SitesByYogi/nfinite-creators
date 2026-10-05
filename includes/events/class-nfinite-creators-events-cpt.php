<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Events_CPT {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type( 'nfinite_event', array(
			'labels' => array(
				'name'               => __( 'Events', 'nfinite-creators' ),
				'singular_name'      => __( 'Event', 'nfinite-creators' ),
				'add_new_item'       => __( 'Add Event', 'nfinite-creators' ),
				'edit_item'          => __( 'Edit Event', 'nfinite-creators' ),
				'view_item'          => __( 'View Event', 'nfinite-creators' ),
				'search_items'       => __( 'Search Events', 'nfinite-creators' ),
				'not_found'          => __( 'No events found.', 'nfinite-creators' ),
				'not_found_in_trash' => __( 'No events found in Trash.', 'nfinite-creators' ),
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_in_rest'       => true,
			'has_archive'        => 'events',
			'rewrite'            => array( 'slug' => 'events', 'with_front' => false ),
			'menu_icon'          => 'dashicons-calendar-alt',
			'menu_position'      => 28,
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
		) );

		register_taxonomy( 'nfinite_event_type', array( 'nfinite_event' ), array(
			'labels' => array(
				'name'          => __( 'Event Types', 'nfinite-creators' ),
				'singular_name' => __( 'Event Type', 'nfinite-creators' ),
			),
			'public'       => true,
			'show_in_rest' => true,
			'hierarchical' => false,
			'rewrite'      => array( 'slug' => 'event-type', 'with_front' => false ),
		) );
	}
}
