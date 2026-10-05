<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Video Engagement V1.
 *
 * Measures first-party watch activity for Nfinite episodes and library videos.
 * Public playback and revenue-eligible engagement remain separate concepts.
 */
class Nfinite_Creators_Video_Engagement {
	const DB_VERSION = '1.0.0';
	const DB_OPTION  = 'nfinite_video_engagement_db_version';
	const TABLE_SLUG = 'nfinite_engagement_video';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 7 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 38 );
		add_action( 'wp_ajax_nfinite_video_engagement_event', array( __CLASS__, 'handle_event' ) );
		add_action( 'wp_ajax_nopriv_nfinite_video_engagement_event', array( __CLASS__, 'handle_event' ) );
	}

	public static function table_name() { global $wpdb; return $wpdb->prefix . self::TABLE_SLUG; }
	public static function maybe_install() { if ( self::DB_VERSION !== (string) get_option( self::DB_OPTION, '' ) ) { self::install(); } }

	public static function install() {
		global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table_name(); $charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			engagement_key char(64) NOT NULL,
			creator_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_type varchar(30) NOT NULL DEFAULT 'episode',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			visitor_hash char(64) NOT NULL,
			session_hash char(64) NOT NULL,
			playback_hash char(64) NOT NULL,
			started_at datetime NOT NULL,
			last_event_at datetime NOT NULL,
			ended_at datetime NULL,
			watched_ms bigint(20) unsigned NOT NULL DEFAULT 0,
			max_position_ms bigint(20) unsigned NOT NULL DEFAULT 0,
			duration_ms bigint(20) unsigned NOT NULL DEFAULT 0,
			completion_pct decimal(6,2) NOT NULL DEFAULT 0,
			surface varchar(40) NOT NULL DEFAULT 'other',
			provider varchar(30) NOT NULL DEFAULT 'unknown',
			qualified tinyint(1) unsigned NOT NULL DEFAULT 0,
			qualified_at datetime NULL,
			fraud_score smallint(5) unsigned NOT NULL DEFAULT 0,
			fraud_flags text NULL,
			metadata longtext NULL,
			PRIMARY KEY (id),
			UNIQUE KEY engagement_key (engagement_key),
			KEY creator_started (creator_id,started_at),
			KEY creator_qualified (creator_id,qualified,started_at),
			KEY object_started (object_type,object_id,started_at),
			KEY visitor_object (visitor_hash,object_id,started_at),
			KEY provider_started (provider,started_at)
		) {$charset};" );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	public static function enqueue_assets() {
		if ( is_admin() || is_feed() || is_robots() || wp_doing_cron() ) { return; }
		wp_enqueue_script( 'nfinite-video-engagement', NFINITE_CREATORS_URL . 'public/js/nfinite-video-engagement.js', array( 'nfinite-creators-video-player' ), NFINITE_CREATORS_VERSION, true );
		wp_localize_script( 'nfinite-video-engagement', 'NfiniteVideoEngagement', array(
			'endpoint' => admin_url( 'admin-ajax.php' ),
			'action' => 'nfinite_video_engagement_event',
			'progressIntervalMs' => 10000,
			'maxPulseMs' => 15000,
		) );
	}

	private static function same_origin() {
		$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); if ( ! $home ) { return true; }
		$candidate = ! empty( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : ( ! empty( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '' );
		return ! $candidate || strtolower( (string) wp_parse_url( $candidate, PHP_URL_HOST ) ) === $home;
	}
	private static function valid_id( $value ) { return is_string( $value ) && (bool) preg_match( '/^[a-f0-9-]{16,80}$/i', $value ); }
	private static function hash_id( $value ) { return hash_hmac( 'sha256', strtolower( trim( $value ) ), wp_salt( 'auth' ) ); }
	private static function allowed_surface( $value ) { $value = sanitize_key( $value ); $allowed = array( 'tv','episode','show','videos_hub','creator_profile','editorial','other' ); return in_array( $value, $allowed, true ) ? $value : 'other'; }
	private static function allowed_provider( $value ) { $value = sanitize_key( $value ); return in_array( $value, array( 'youtube','vimeo','local','unknown' ), true ) ? $value : 'unknown'; }

	private static function resolve_object( $creator_id, $object_type, $object_id ) {
		$creator_id = absint( $creator_id ); $object_id = absint( $object_id ); $object_type = sanitize_key( $object_type );
		if ( 'episode' === $object_type && class_exists( 'Nfinite_Creators_Shows' ) && Nfinite_Creators_Shows::EPISODE_POST_TYPE === get_post_type( $object_id ) && 'publish' === get_post_status( $object_id ) ) {
			$owner = absint( get_post_meta( $object_id, '_nfinite_episode_creator_id', true ) );
			if ( ! $owner ) { $show = absint( get_post_meta( $object_id, '_nfinite_episode_show_id', true ) ); if ( $show ) { $owner = absint( get_post_meta( $show, '_nfinite_show_creator_id', true ) ); } }
			return $owner && $owner === $creator_id ? array( 'object_type'=>'episode','object_id'=>$object_id ) : false;
		}
		if ( 'video' === $object_type && 'nfinite_video' === get_post_type( $object_id ) && 'publish' === get_post_status( $object_id ) ) {
			return absint( get_post_meta( $object_id, '_nfinite_video_creator_id', true ) ) === $creator_id ? array( 'object_type'=>'video','object_id'=>$object_id ) : false;
		}
		return false;
	}

	private static function qualification_threshold_ms( $duration_ms ) {
		$duration_ms = absint( $duration_ms );
		if ( $duration_ms > 0 ) { return (int) min( 60000, max( 15000, round( $duration_ms * 0.25 ) ) ); }
		return 60000;
	}

	private static function repeat_pressure( $visitor_hash, $object_id ) {
		global $wpdb; $table = self::table_name(); $since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE visitor_hash=%s AND object_id=%d AND started_at>=%s", $visitor_hash, absint( $object_id ), $since ) );
		if ( $count >= 20 ) { return array( 100, 'extreme_repeat' ); }
		if ( $count >= 10 ) { return array( 70, 'high_repeat' ); }
		if ( $count >= 6 ) { return array( 35, 'repeat_pressure' ); }
		return array( 0, '' );
	}
	private static function append_flag( $existing, $flag ) { $flags = array_filter( array_map( 'sanitize_key', explode( ',', (string) $existing ) ) ); if ( $flag && ! in_array( $flag, $flags, true ) ) { $flags[] = $flag; } return implode( ',', $flags ); }

	public static function handle_event() {
		if ( ! self::same_origin() ) { wp_send_json_error( array( 'message'=>'Invalid origin.' ), 403 ); }
		$event = isset( $_POST['event_type'] ) ? sanitize_key( wp_unslash( $_POST['event_type'] ) ) : '';
		if ( ! in_array( $event, array( 'video_start','video_progress','video_end' ), true ) ) { wp_send_json_error( array( 'message'=>'Invalid event.' ), 400 ); }
		$creator_id = absint( $_POST['creator_id'] ?? 0 ); $object_id = absint( $_POST['object_id'] ?? 0 ); $object_type = sanitize_key( wp_unslash( $_POST['object_type'] ?? 'episode' ) );
		$resolved = self::resolve_object( $creator_id, $object_type, $object_id ); if ( ! $creator_id || ! $resolved ) { wp_send_json_error( array( 'message'=>'Invalid media.' ), 400 ); }
		$visitor = sanitize_text_field( wp_unslash( $_POST['visitor_id'] ?? '' ) ); $session = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) ); $playback = sanitize_text_field( wp_unslash( $_POST['playback_id'] ?? '' ) );
		if ( ! self::valid_id( $visitor ) || ! self::valid_id( $session ) || ! self::valid_id( $playback ) ) { wp_send_json_error( array( 'message'=>'Invalid identifiers.' ), 400 ); }
		$visitor_hash = self::hash_id( $visitor ); $session_hash = self::hash_id( $session ); $playback_hash = self::hash_id( $playback );
		$key = hash_hmac( 'sha256', $creator_id . '|' . $resolved['object_type'] . '|' . $object_id . '|' . $playback_hash, wp_salt( 'nonce' ) );
		$duration_ms = min( DAY_IN_SECONDS * 1000, absint( $_POST['duration_ms'] ?? 0 ) ); $position_ms = min( DAY_IN_SECONDS * 1000, absint( $_POST['position_ms'] ?? 0 ) ); $delta_ms = absint( $_POST['played_delta_ms'] ?? 0 );
		$surface = self::allowed_surface( wp_unslash( $_POST['surface'] ?? 'other' ) ); $provider = self::allowed_provider( wp_unslash( $_POST['provider'] ?? 'unknown' ) ); $title = sanitize_text_field( wp_unslash( $_POST['media_title'] ?? '' ) ); $now = current_time( 'mysql', true );
		global $wpdb; $table = self::table_name(); $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE engagement_key=%s", $key ), ARRAY_A );
		if ( ! $row ) {
			list( $repeat_score, $repeat_flag ) = self::repeat_pressure( $visitor_hash, $object_id );
			$wpdb->insert( $table, array( 'engagement_key'=>$key,'creator_id'=>$creator_id,'object_type'=>$resolved['object_type'],'object_id'=>$object_id,'visitor_hash'=>$visitor_hash,'session_hash'=>$session_hash,'playback_hash'=>$playback_hash,'started_at'=>$now,'last_event_at'=>$now,'duration_ms'=>$duration_ms,'surface'=>$surface,'provider'=>$provider,'fraud_score'=>$repeat_score,'fraud_flags'=>$repeat_flag,'metadata'=>wp_json_encode( array( 'title'=>$title ) ) ) );
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE engagement_key=%s", $key ), ARRAY_A );
		}
		if ( ! $row ) { wp_send_json_error( array( 'message'=>'Could not record engagement.' ), 500 ); }

		$fraud = absint( $row['fraud_score'] ); $flags = (string) $row['fraud_flags'];
		if ( $delta_ms > 15000 ) { $fraud = max( $fraud, 70 ); $flags = self::append_flag( $flags, 'oversized_pulse' ); $delta_ms = 15000; }
		if ( $duration_ms && $position_ms > $duration_ms + 5000 ) { $fraud = max( $fraud, 70 ); $flags = self::append_flag( $flags, 'invalid_position' ); }
		$watched = absint( $row['watched_ms'] ); if ( 'video_progress' === $event || 'video_end' === $event ) { $watched += $delta_ms; }
		$duration = $duration_ms ?: absint( $row['duration_ms'] ); $max_position = max( absint( $row['max_position_ms'] ), $position_ms ); $completion = $duration > 0 ? min( 100, ( $max_position / $duration ) * 100 ) : 0;
		$qualified = absint( $row['qualified'] ); $qualified_at = $row['qualified_at']; $threshold = self::qualification_threshold_ms( $duration );
		if ( ! $qualified && $watched >= $threshold && $fraud < 70 ) { $qualified = 1; $qualified_at = $now; }
		$update = array( 'last_event_at'=>$now,'watched_ms'=>$watched,'max_position_ms'=>$max_position,'duration_ms'=>$duration,'completion_pct'=>$completion,'surface'=>$surface,'provider'=>$provider,'qualified'=>$qualified,'qualified_at'=>$qualified_at,'fraud_score'=>$fraud,'fraud_flags'=>$flags );
		if ( 'video_end' === $event ) { $update['ended_at'] = $now; }
		$was_qualified = absint( $row['qualified'] ); $wpdb->update( $table, $update, array( 'id'=>absint( $row['id'] ) ) );
		if ( ! $was_qualified && $qualified ) { do_action( 'nfinite_video_engagement_qualified', absint( $row['id'] ), $creator_id ); }
		wp_send_json_success( array( 'qualified'=>(bool)$qualified,'watched_ms'=>$watched,'completion_pct'=>round( $completion, 2 ),'fraud_score'=>$fraud ) );
	}

	public static function creator_summary( $creator_id, $period = '' ) {
		global $wpdb; $table = self::table_name(); $creator_id = absint( $creator_id ); $where = 'creator_id=%d'; $args = array( $creator_id );
		if ( preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $period ) ) { $start=$period.'-01 00:00:00'; $dt=new DateTime($start,new DateTimeZone('UTC')); $end=clone $dt; $end->modify('+1 month'); $where .= ' AND started_at>=%s AND started_at<%s'; $args[]=$dt->format('Y-m-d H:i:s'); $args[]=$end->format('Y-m-d H:i:s'); }
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) raw_sessions,SUM(qualified) qualified_views,SUM(watched_ms) watched_ms,COUNT(DISTINCT CASE WHEN qualified=1 THEN visitor_hash END) qualified_viewers,AVG(completion_pct) avg_completion,SUM(CASE WHEN fraud_score>=70 THEN 1 ELSE 0 END) flagged FROM {$table} WHERE {$where}", $args ), ARRAY_A );
		return is_array( $row ) ? $row : array();
	}
}
