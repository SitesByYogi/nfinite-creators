<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Nfinite Engagement V1.
 *
 * A first-party qualification layer that sits between raw player activity and
 * future creator earnings. It intentionally does not create money/ledger rows.
 */
class Nfinite_Creators_Engagement {
	const DB_VERSION = '1.1.0';
	const DB_OPTION  = 'nfinite_creators_engagement_db_version';
	const TABLE_SLUG = 'nfinite_engagement_audio';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 6 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 35 );
		add_action( 'wp_ajax_nfinite_engagement_event', array( __CLASS__, 'handle_event' ) );
		add_action( 'wp_ajax_nopriv_nfinite_engagement_event', array( __CLASS__, 'handle_event' ) );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SLUG;
	}

	public static function maybe_install() {
		if ( self::DB_VERSION !== get_option( self::DB_OPTION ) ) { self::install(); }
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			engagement_key char(64) NOT NULL,
			creator_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_type varchar(30) NOT NULL DEFAULT 'track',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			visitor_hash char(64) NOT NULL,
			session_hash char(64) NOT NULL,
			playback_hash char(64) NOT NULL,
			started_at datetime NOT NULL,
			last_event_at datetime NOT NULL,
			ended_at datetime NULL,
			listened_ms bigint(20) unsigned NOT NULL DEFAULT 0,
			max_position_ms bigint(20) unsigned NOT NULL DEFAULT 0,
			duration_ms bigint(20) unsigned NOT NULL DEFAULT 0,
			completion_pct decimal(6,2) NOT NULL DEFAULT 0,
			surface varchar(40) NOT NULL DEFAULT 'other',
			media_source varchar(30) NOT NULL DEFAULT 'unknown',
			end_reason varchar(24) NOT NULL DEFAULT '',
			skipped tinyint(1) unsigned NOT NULL DEFAULT 0,
			qualified tinyint(1) unsigned NOT NULL DEFAULT 0,
			qualified_at datetime NULL,
			fraud_score smallint(5) unsigned NOT NULL DEFAULT 0,
			fraud_flags text NULL,
			metadata longtext NULL,
			PRIMARY KEY (id),
			UNIQUE KEY engagement_key (engagement_key),
			KEY creator_started (creator_id, started_at),
			KEY creator_qualified (creator_id, qualified, started_at),
			KEY object_started (object_id, started_at),
			KEY visitor_object (visitor_hash, object_id, started_at),
			KEY surface_started (surface, started_at)
		) {$charset};";
		dbDelta( $sql );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	public static function enqueue_assets() {
		if ( is_admin() || is_feed() || is_robots() || wp_doing_cron() ) { return; }
		wp_enqueue_script(
			'nfinite-creators-engagement',
			NFINITE_CREATORS_URL . 'public/js/nfinite-engagement.js',
			array( 'nfinite-creators-music-player' ),
			NFINITE_CREATORS_VERSION,
			true
		);
		wp_localize_script( 'nfinite-creators-engagement', 'NfiniteEngagement', array(
			'endpoint'          => admin_url( 'admin-ajax.php' ),
			'action'            => 'nfinite_engagement_event',
			'progressIntervalMs'=> 10000,
			'maxPulseMs'        => 15000,
		) );
	}

	private static function valid_id( $value ) {
		return is_string( $value ) && (bool) preg_match( '/^[a-f0-9-]{16,80}$/i', $value );
	}

	private static function hash_id( $value ) {
		return hash_hmac( 'sha256', strtolower( trim( $value ) ), wp_salt( 'auth' ) );
	}

	private static function same_origin() {
		$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( ! $home ) { return true; }
		$candidate = '';
		if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) { $candidate = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ); }
		elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) { $candidate = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ); }
		if ( ! $candidate ) { return true; }
		return strtolower( (string) wp_parse_url( $candidate, PHP_URL_HOST ) ) === $home;
	}

	private static function allowed_surface( $value ) {
		$value = sanitize_key( $value );
		$allowed = array( 'radio', 'rotation', 'release', 'creator_profile', 'playlist', 'music_hub', 'beats', 'tv', 'editorial', 'app_music', 'app_radio', 'app_release', 'app_creator', 'other' );
		return in_array( $value, $allowed, true ) ? $value : 'other';
	}

	private static function allowed_source( $value ) {
		$value = sanitize_key( $value );
		$allowed = array( 'local', 'internet_archive', 'spotify', 'soundcloud', 'apple_music', 'unknown' );
		return in_array( $value, $allowed, true ) ? $value : 'unknown';
	}

	private static function resolve_object( $creator_id, $object_id, $declared_type ) {
		$creator_id = absint( $creator_id );
		$object_id = absint( $object_id );
		$type = sanitize_key( $declared_type );
		if ( 'track' === $type ) {
			if ( ! $object_id || 'nfinite_track' !== get_post_type( $object_id ) || 'publish' !== get_post_status( $object_id ) ) { return false; }
			if ( absint( get_post_meta( $object_id, '_nfinite_track_creator_id', true ) ) !== $creator_id ) { return false; }
			return array( 'object_type' => 'track', 'object_id' => $object_id );
		}
		if ( 'product_preview' === $type ) {
			if ( ! $object_id || 'product' !== get_post_type( $object_id ) || 'publish' !== get_post_status( $object_id ) ) { return false; }
			if ( absint( get_post_meta( $object_id, '_nfinite_creator_id', true ) ) !== $creator_id ) { return false; }
			return array( 'object_type' => 'beat', 'object_id' => $object_id );
		}
		if ( 'legacy_track' === $type ) {
			$tracks = get_post_meta( $creator_id, '_nfinite_creator_tracks', true );
			if ( ! is_array( $tracks ) ) { return false; }
			foreach ( array_keys( $tracks ) as $index ) {
				$legacy = absint( sprintf( '%u', crc32( 'creator-beat-' . $creator_id . '-' . absint( $index ) ) ) );
				if ( $legacy === $object_id ) { return array( 'object_type' => 'beat', 'object_id' => $object_id ); }
			}
		}
		return false;
	}

	private static function qualification_threshold_ms( $duration_ms ) {
		$duration_ms = absint( $duration_ms );
		if ( $duration_ms > 0 ) {
			// Long-form music uses 30 seconds. Very short clips require half the
			// runtime, but never less than ten seconds.
			return (int) min( 30000, max( 10000, round( $duration_ms * 0.50 ) ) );
		}
		return 30000;
	}

	private static function repeat_pressure( $visitor_hash, $object_id ) {
		global $wpdb;
		$table = self::table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE visitor_hash=%s AND object_id=%d AND started_at >= %s",
			$visitor_hash, absint( $object_id ), $since
		) );
		if ( $count >= 30 ) { return array( 100, 'extreme_repeat' ); }
		if ( $count >= 15 ) { return array( 70, 'high_repeat' ); }
		if ( $count >= 8 ) { return array( 35, 'repeat_pressure' ); }
		return array( 0, '' );
	}

	private static function append_flag( $existing, $flag ) {
		$flags = array_filter( array_map( 'sanitize_key', explode( ',', (string) $existing ) ) );
		if ( $flag && ! in_array( $flag, $flags, true ) ) { $flags[] = $flag; }
		return implode( ',', $flags );
	}

	public static function handle_event() {
		if ( ! self::same_origin() ) { wp_send_json_error( array( 'message' => 'Invalid origin.' ), 403 ); }
		$event = isset( $_POST['event_type'] ) ? sanitize_key( wp_unslash( $_POST['event_type'] ) ) : '';
		if ( ! in_array( $event, array( 'audio_start', 'audio_progress', 'audio_end' ), true ) ) { wp_send_json_error( array( 'message' => 'Invalid event.' ), 400 ); }

		$creator_id = isset( $_POST['creator_id'] ) ? absint( $_POST['creator_id'] ) : 0;
		$object_id = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;
		$object_type = isset( $_POST['object_type'] ) ? sanitize_key( wp_unslash( $_POST['object_type'] ) ) : 'track';
		$resolved = self::resolve_object( $creator_id, $object_id, $object_type );
		if ( ! $creator_id || ! $resolved ) { wp_send_json_error( array( 'message' => 'Invalid media.' ), 400 ); }

		$visitor = isset( $_POST['visitor_id'] ) ? sanitize_text_field( wp_unslash( $_POST['visitor_id'] ) ) : '';
		$session = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$playback = isset( $_POST['playback_id'] ) ? sanitize_text_field( wp_unslash( $_POST['playback_id'] ) ) : '';
		if ( ! self::valid_id( $visitor ) || ! self::valid_id( $session ) || ! self::valid_id( $playback ) ) { wp_send_json_error( array( 'message' => 'Invalid identifiers.' ), 400 ); }

		$visitor_hash = self::hash_id( $visitor );
		$session_hash = self::hash_id( $session );
		$playback_hash = self::hash_id( $playback );
		$key = hash_hmac( 'sha256', $creator_id . '|' . $object_id . '|' . $playback_hash, wp_salt( 'nonce' ) );
		$duration_ms = isset( $_POST['duration_ms'] ) ? min( DAY_IN_SECONDS * 1000, absint( $_POST['duration_ms'] ) ) : 0;
		$position_ms = isset( $_POST['position_ms'] ) ? min( DAY_IN_SECONDS * 1000, absint( $_POST['position_ms'] ) ) : 0;
		$delta_ms = isset( $_POST['played_delta_ms'] ) ? absint( $_POST['played_delta_ms'] ) : 0;
		$surface = self::allowed_surface( isset( $_POST['surface'] ) ? wp_unslash( $_POST['surface'] ) : 'other' );
		$source = self::allowed_source( isset( $_POST['media_source'] ) ? wp_unslash( $_POST['media_source'] ) : 'unknown' );
		$title = isset( $_POST['media_title'] ) ? sanitize_text_field( wp_unslash( $_POST['media_title'] ) ) : '';
		$end_reason = isset( $_POST['end_reason'] ) ? sanitize_key( wp_unslash( $_POST['end_reason'] ) ) : '';
		$allowed_end_reasons = array( '', 'completed', 'manual_skip', 'track_change', 'stop', 'pagehide', 'error' );
		if ( ! in_array( $end_reason, $allowed_end_reasons, true ) ) { $end_reason = ''; }
		$now = current_time( 'mysql', true );

		global $wpdb;
		$table = self::table_name();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE engagement_key=%s", $key ), ARRAY_A );
		if ( ! $row ) {
			list( $repeat_score, $repeat_flag ) = self::repeat_pressure( $visitor_hash, $object_id );
			$flags = $repeat_flag;
			$wpdb->insert( $table, array(
				'engagement_key' => $key, 'creator_id' => $creator_id, 'object_type' => $resolved['object_type'], 'object_id' => $resolved['object_id'],
				'visitor_hash' => $visitor_hash, 'session_hash' => $session_hash, 'playback_hash' => $playback_hash,
				'started_at' => $now, 'last_event_at' => $now, 'listened_ms' => 0, 'max_position_ms' => $position_ms,
				'duration_ms' => $duration_ms, 'completion_pct' => 0, 'surface' => $surface, 'media_source' => $source,
				'qualified' => 0, 'fraud_score' => $repeat_score, 'fraud_flags' => $flags,
				'metadata' => wp_json_encode( array( 'title' => substr( $title, 0, 191 ) ) ),
			), array( '%s','%d','%s','%d','%s','%s','%s','%s','%s','%d','%d','%d','%f','%s','%s','%d','%d','%s','%s' ) );
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE engagement_key=%s", $key ), ARRAY_A );
		}
		if ( ! $row ) { wp_send_json_error( array( 'message' => 'Could not create engagement.' ), 500 ); }

		$fraud_score = (int) $row['fraud_score'];
		$flags = (string) $row['fraud_flags'];
		$accepted_delta = 0;
		if ( 'audio_progress' === $event || 'audio_end' === $event ) {
			if ( $delta_ms > 15000 ) { $fraud_score += 20; $flags = self::append_flag( $flags, 'oversized_pulse' ); }
			$accepted_delta = min( 15000, $delta_ms );
			if ( $duration_ms && $position_ms > $duration_ms + 5000 ) { $fraud_score += 25; $flags = self::append_flag( $flags, 'invalid_position' ); }
		}
		$fraud_score = min( 100, $fraud_score );
		$listened = (int) $row['listened_ms'] + $accepted_delta;
		$duration = max( (int) $row['duration_ms'], $duration_ms );
		$max_position = max( (int) $row['max_position_ms'], $position_ms );
		$completion = $duration > 0 ? min( 100, round( ( $max_position / $duration ) * 100, 2 ) ) : (float) $row['completion_pct'];
		$threshold = self::qualification_threshold_ms( $duration );
		$qualified = (int) $row['qualified'];
		$was_qualified = (bool) $qualified;
		$qualified_at = $row['qualified_at'];
		if ( ! $qualified && $listened >= $threshold && $fraud_score < 70 ) { $qualified = 1; $qualified_at = $now; }

		$update = array(
			'last_event_at' => $now, 'listened_ms' => $listened, 'max_position_ms' => $max_position, 'duration_ms' => $duration,
			'completion_pct' => $completion, 'qualified' => $qualified, 'qualified_at' => $qualified_at,
			'fraud_score' => $fraud_score, 'fraud_flags' => $flags,
		);
		$formats = array( '%s','%d','%d','%d','%f','%d','%s','%d','%s' );
		if ( 'audio_end' === $event ) {
			$update['ended_at'] = $now; $formats[] = '%s';
			$update['end_reason'] = $end_reason; $formats[] = '%s';
			$update['skipped'] = in_array( $end_reason, array( 'manual_skip', 'track_change' ), true ) && $completion < 95 ? 1 : 0; $formats[] = '%d';
		}
		$wpdb->update( $table, $update, array( 'id' => absint( $row['id'] ) ), $formats, array( '%d' ) );
		if ( ! $was_qualified && $qualified ) { do_action( 'nfinite_engagement_qualified', absint( $row['id'] ), $creator_id ); }

		wp_send_json_success( array(
			'qualified' => (bool) $qualified,
			'listened_ms' => $listened,
			'threshold_ms' => $threshold,
			'fraud_score' => $fraud_score,
		) );
	}

	public static function creator_summary( $creator_id, $days = 30 ) {
		global $wpdb;
		$creator_id = absint( $creator_id );
		$days = max( 1, min( 3650, absint( $days ) ) );
		$table = self::table_name();
		$start = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$base = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) raw_plays,
			SUM(CASE WHEN qualified=1 THEN 1 ELSE 0 END) qualified_streams,
			COUNT(DISTINCT CASE WHEN qualified=1 THEN visitor_hash END) qualified_listeners,
			COALESCE(SUM(listened_ms),0) listening_ms,
			AVG(CASE WHEN duration_ms>0 THEN completion_pct ELSE NULL END) avg_completion,
			SUM(CASE WHEN skipped=1 THEN 1 ELSE 0 END) skips,
			SUM(CASE WHEN fraud_score>=70 THEN 1 ELSE 0 END) flagged_plays
			FROM {$table} WHERE creator_id=%d AND started_at >= %s",
			$creator_id, $start
		), ARRAY_A );
		$surfaces = $wpdb->get_results( $wpdb->prepare(
			"SELECT surface, COUNT(*) raw_plays, SUM(CASE WHEN qualified=1 THEN 1 ELSE 0 END) qualified_streams, COALESCE(SUM(listened_ms),0) listening_ms
			FROM {$table} WHERE creator_id=%d AND started_at >= %s GROUP BY surface ORDER BY qualified_streams DESC, raw_plays DESC LIMIT 8",
			$creator_id, $start
		), ARRAY_A );
		$top = $wpdb->get_results( $wpdb->prepare(
			"SELECT object_id, MAX(object_type) object_type, COUNT(*) raw_plays, SUM(CASE WHEN qualified=1 THEN 1 ELSE 0 END) qualified_streams,
			COUNT(DISTINCT CASE WHEN qualified=1 THEN visitor_hash END) listeners, COALESCE(SUM(listened_ms),0) listening_ms,
			AVG(CASE WHEN duration_ms>0 THEN completion_pct ELSE NULL END) completion_rate, MAX(metadata) metadata
			FROM {$table} WHERE creator_id=%d AND started_at >= %s GROUP BY object_id ORDER BY qualified_streams DESC, raw_plays DESC LIMIT 10",
			$creator_id, $start
		), ARRAY_A );
		foreach ( $top as &$item ) {
			$meta = json_decode( (string) $item['metadata'], true );
			$item['title'] = is_array( $meta ) && ! empty( $meta['title'] ) ? sanitize_text_field( $meta['title'] ) : ( $item['object_id'] && get_post_status( $item['object_id'] ) ? get_the_title( $item['object_id'] ) : '' );
			$item['raw_plays'] = (int) $item['raw_plays'];
			$item['qualified_streams'] = (int) $item['qualified_streams'];
			$item['listeners'] = (int) $item['listeners'];
			$item['listening_ms'] = (int) $item['listening_ms'];
			$item['completion_rate'] = round( (float) $item['completion_rate'], 1 );
			unset( $item['metadata'] );
		}
		unset( $item );
		return array(
			'raw_plays' => isset( $base['raw_plays'] ) ? (int) $base['raw_plays'] : 0,
			'qualified_streams' => isset( $base['qualified_streams'] ) ? (int) $base['qualified_streams'] : 0,
			'qualified_listeners' => isset( $base['qualified_listeners'] ) ? (int) $base['qualified_listeners'] : 0,
			'listening_ms' => isset( $base['listening_ms'] ) ? (int) $base['listening_ms'] : 0,
			'avg_completion' => isset( $base['avg_completion'] ) ? round( (float) $base['avg_completion'], 1 ) : 0,
			'skips' => isset( $base['skips'] ) ? (int) $base['skips'] : 0,
			'flagged_plays' => isset( $base['flagged_plays'] ) ? (int) $base['flagged_plays'] : 0,
			'surfaces' => is_array( $surfaces ) ? $surfaces : array(),
			'top_media' => is_array( $top ) ? $top : array(),
		);
	}
}
