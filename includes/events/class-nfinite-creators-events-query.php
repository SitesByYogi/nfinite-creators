<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Events_Query {
	public static function timestamp( $event_id, $which = 'start' ) {
		$date = get_post_meta( $event_id, '_nfinite_event_' . $which . '_date', true );
		$time = get_post_meta( $event_id, '_nfinite_event_' . $which . '_time', true );
		if ( ! $date ) { return 0; }
		$time = $time ? $time : ( 'end' === $which ? '23:59' : '00:00' );
		$dt = date_create_immutable_from_format( 'Y-m-d H:i', $date . ' ' . $time, wp_timezone() );
		return $dt ? $dt->getTimestamp() : 0;
	}

	public static function status( $event_id ) {
		$now   = current_datetime()->getTimestamp();
		$start = self::timestamp( $event_id, 'start' );
		$end   = self::timestamp( $event_id, 'end' );
		if ( ! $start ) { return 'upcoming'; }
		if ( $end && $now >= $start && $now <= $end ) { return 'live'; }
		if ( $now > ( $end ? $end : $start ) ) { return 'past'; }
		return 'upcoming';
	}

	public static function date_label( $event_id ) {
		$start_date = get_post_meta( $event_id, '_nfinite_event_start_date', true );
		$end_date   = get_post_meta( $event_id, '_nfinite_event_end_date', true );
		$start_time = get_post_meta( $event_id, '_nfinite_event_start_time', true );
		if ( ! $start_date ) { return __( 'Date TBA', 'nfinite-creators' ); }
		$start = self::timestamp( $event_id, 'start' );
		$label = wp_date( 'F j, Y', $start, wp_timezone() );
		if ( $end_date && $end_date !== $start_date ) {
			$end = self::timestamp( $event_id, 'end' );
			$label = wp_date( 'F j', $start, wp_timezone() ) . '–' . wp_date( 'F j, Y', $end, wp_timezone() );
		}
		if ( $start_time ) { $label .= ' · ' . wp_date( get_option( 'time_format' ), $start, wp_timezone() ); }
		return $label;
	}

	public static function location_label( $event_id ) {
		$venue = trim( (string) get_post_meta( $event_id, '_nfinite_event_venue', true ) );
		$city  = trim( (string) get_post_meta( $event_id, '_nfinite_event_city', true ) );
		$state = trim( (string) get_post_meta( $event_id, '_nfinite_event_state', true ) );
		$place = implode( ', ', array_filter( array( $city, $state ) ) );
		return implode( ' · ', array_filter( array( $venue, $place ) ) );
	}

	public static function creator_ids( $event_id ) {
		$ids = get_post_meta( $event_id, '_nfinite_event_creator_ids', true );
		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	}

	public static function events( $scope = 'upcoming', $limit = -1 ) {
		$posts = get_posts( array(
			'post_type'      => 'nfinite_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => '_nfinite_event_start_date',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		) );
		$posts = array_values( array_filter( $posts, function( $post ) use ( $scope ) {
			$status = self::status( $post->ID );
			if ( 'upcoming' === $scope ) { return in_array( $status, array( 'upcoming', 'live' ), true ); }
			return $status === $scope;
		} ) );
		if ( 'past' === $scope ) { $posts = array_reverse( $posts ); }
		return $limit > -1 ? array_slice( $posts, 0, $limit ) : $posts;
	}
}
