<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Streaming Earnings V1.
 *
 * Converts qualified, monetized, first-party audio engagement into a monthly
 * reserve-funded creator revenue share. The reserve is an internal accounting
 * control only: adding reserve funds here does not charge a card or move Stripe
 * money. Finalized creator statements may later be paid from the PairOfDice
 * Stripe platform balance to connected creator accounts.
 */
class Nfinite_Creators_Streaming_Earnings {
	const DB_VERSION = '1.0.0';
	const DB_OPTION = 'nfinite_streaming_earnings_db_version';
	const OPTION_KEY = 'nfinite_streaming_earnings_settings';
	const CRON_HOOK = 'nfinite_streaming_earnings_sync';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 8 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_nfinite_streaming_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_nfinite_streaming_add_reserve', array( __CLASS__, 'add_reserve' ) );
		add_action( 'admin_post_nfinite_streaming_sync_period', array( __CLASS__, 'admin_sync_period' ) );
		add_action( 'admin_post_nfinite_streaming_finalize_period', array( __CLASS__, 'admin_finalize_period' ) );
		add_action( 'admin_post_nfinite_streaming_pay_period', array( __CLASS__, 'admin_pay_period' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_sync' ) );
		add_action( 'nfinite_engagement_qualified', array( __CLASS__, 'qualified_engagement_changed' ), 10, 2 );
		add_action( 'wp', array( __CLASS__, 'ensure_cron' ) );
	}

	public static function settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), array(
			'enabled' => '0',
			'currency' => 'USD',
			'monthly_budget_minor' => 50000,
			'auto_sync' => '1',
		) );
	}

	public static function enabled() {
		$s = self::settings();
		return '1' === (string) $s['enabled'];
	}

	public static function periods_table() { global $wpdb; return $wpdb->prefix . 'nfinite_streaming_periods'; }
	public static function allocations_table() { global $wpdb; return $wpdb->prefix . 'nfinite_streaming_allocations'; }
	public static function reserve_table() { global $wpdb; return $wpdb->prefix . 'nfinite_streaming_reserve'; }

	public static function maybe_install() {
		if ( self::DB_VERSION !== (string) get_option( self::DB_OPTION, '' ) ) { self::install(); }
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$periods = self::periods_table();
		$allocations = self::allocations_table();
		$reserve = self::reserve_table();

		dbDelta( "CREATE TABLE {$periods} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			period varchar(7) NOT NULL,
			currency varchar(12) NOT NULL DEFAULT 'USD',
			budget_amount bigint(20) NOT NULL DEFAULT 0,
			allocated_amount bigint(20) NOT NULL DEFAULT 0,
			eligible_streams bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'open',
			finalized_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY period (period),
			KEY status (status)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$allocations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			period varchar(7) NOT NULL,
			creator_id bigint(20) unsigned NOT NULL,
			object_type varchar(30) NOT NULL DEFAULT 'track',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			qualified_streams bigint(20) unsigned NOT NULL DEFAULT 0,
			amount bigint(20) NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'provisional',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY period_object (period,creator_id,object_type,object_id),
			KEY creator_period (creator_id,period),
			KEY period_status (period,status)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$reserve} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entry_key varchar(191) NOT NULL,
			period varchar(7) NOT NULL DEFAULT '',
			entry_type varchar(30) NOT NULL DEFAULT 'deposit',
			amount bigint(20) NOT NULL DEFAULT 0,
			currency varchar(12) NOT NULL DEFAULT 'USD',
			note varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY entry_key (entry_key),
			KEY period (period),
			KEY entry_type (entry_type)
		) {$charset};" );

		// Seed the bookkeeping reserve once. This does not move real money.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$reserve}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 0 === $count ) {
			$wpdb->insert( $reserve, array(
				'entry_key' => 'seed:0.39.0', 'period' => '', 'entry_type' => 'deposit', 'amount' => 50000,
				'currency' => 'USD', 'note' => 'Initial internal PairOfDice streaming reserve', 'created_at' => current_time( 'mysql', true ),
			) );
		}
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	public static function ensure_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK ); }
	}

	public static function cron_sync() {
		$s = self::settings();
		if ( self::enabled() && '1' === (string) $s['auto_sync'] ) { self::sync_period( gmdate( 'Y-m' ) ); }
	}

	public static function qualified_engagement_changed( $engagement_id, $creator_id ) {
		if ( ! self::enabled() ) { return; }
		// Keep front-end qualification cheap: at most one full provisional refresh every five minutes.
		if ( get_transient( 'nfinite_streaming_sync_lock' ) ) { return; }
		set_transient( 'nfinite_streaming_sync_lock', 1, 5 * MINUTE_IN_SECONDS );
		self::sync_period( gmdate( 'Y-m' ) );
	}

	public static function current_period() { return gmdate( 'Y-m' ); }

	private static function valid_period( $period ) {
		$period = sanitize_text_field( (string) $period );
		return preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $period ) ? $period : '';
	}

	private static function period_bounds( $period ) {
		$period = self::valid_period( $period );
		if ( ! $period ) { return false; }
		$start = $period . '-01 00:00:00';
		$dt = DateTime::createFromFormat( '!Y-m-d H:i:s', $start, new DateTimeZone( 'UTC' ) );
		if ( ! $dt ) { return false; }
		$end = clone $dt; $end->modify( '+1 month' );
		return array( $dt->format( 'Y-m-d H:i:s' ), $end->format( 'Y-m-d H:i:s' ) );
	}

	public static function reserve_balance() {
		global $wpdb; $table = self::reserve_table();
		return (int) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function reserve_entries( $limit = 25 ) {
		global $wpdb; $table = self::reserve_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC,id DESC LIMIT %d", max( 1, absint( $limit ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function period_row( $period ) {
		global $wpdb; $table = self::periods_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE period=%s LIMIT 1", self::valid_period( $period ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function period_allocations( $period, $creator_id = 0 ) {
		global $wpdb; $table = self::allocations_table(); $period = self::valid_period( $period );
		if ( $creator_id ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE period=%s AND creator_id=%d ORDER BY amount DESC,qualified_streams DESC", $period, absint( $creator_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE period=%s ORDER BY amount DESC,qualified_streams DESC", $period ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function legacy_track_for_object( $creator_id, $object_id ) {
		$tracks = get_post_meta( absint( $creator_id ), '_nfinite_creator_tracks', true );
		if ( ! is_array( $tracks ) ) { return false; }
		foreach ( $tracks as $index => $track ) {
			$legacy = absint( sprintf( '%u', crc32( 'creator-beat-' . absint( $creator_id ) . '-' . absint( $index ) ) ) );
			if ( $legacy === absint( $object_id ) ) { return is_array( $track ) ? $track : false; }
		}
		return false;
	}

	private static function eligible_media( $creator_id, $object_type, $object_id, $fallback_title = '' ) {
		if ( class_exists( 'Nfinite_Creators_Identity' ) && Nfinite_Creators_Identity::is_publisher( $creator_id ) ) { return false; }
		if ( 'track' === $object_type && 'nfinite_track' === get_post_type( $object_id ) ) {
			if ( ! class_exists( 'Nfinite_Creators_Music_Monetization' ) || ! Nfinite_Creators_Music_Monetization::track_is_monetized( $object_id ) ) { return false; }
			if ( absint( get_post_meta( $object_id, '_nfinite_track_creator_id', true ) ) !== absint( $creator_id ) ) { return false; }
			return array( 'object_type' => 'track', 'object_id' => absint( $object_id ), 'title' => get_the_title( $object_id ) ?: $fallback_title );
		}
		// Engagement 0.37 stores legacy Creator Studio audio as object_type=beat.
		// Only a CRC-matched Creator Studio track can enter streaming earnings;
		// WooCommerce beat previews therefore remain excluded.
		if ( 'beat' === $object_type ) {
			$track = self::legacy_track_for_object( $creator_id, $object_id );
			if ( ! $track || ! class_exists( 'Nfinite_Creators_Music_Monetization' ) || ! Nfinite_Creators_Music_Monetization::legacy_track_is_monetized( $track ) ) { return false; }
			return array( 'object_type' => 'legacy_track', 'object_id' => absint( $object_id ), 'title' => ! empty( $track['title'] ) ? sanitize_text_field( $track['title'] ) : $fallback_title );
		}
		return false;
	}

	public static function eligible_stream_rows( $period ) {
		if ( ! class_exists( 'Nfinite_Creators_Engagement' ) ) { return array(); }
		$bounds = self::period_bounds( $period ); if ( ! $bounds ) { return array(); }
		global $wpdb; $table = Nfinite_Creators_Engagement::table_name();
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT creator_id,object_type,object_id,COUNT(*) qualified_streams,MAX(metadata) metadata
			FROM {$table}
			WHERE qualified=1 AND fraud_score<70 AND media_source='local' AND started_at>=%s AND started_at<%s
			GROUP BY creator_id,object_type,object_id",
			$bounds[0], $bounds[1]
		), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$eligible = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$meta = json_decode( (string) $row['metadata'], true );
			$fallback = is_array( $meta ) && ! empty( $meta['title'] ) ? sanitize_text_field( $meta['title'] ) : '';
			$media = self::eligible_media( absint( $row['creator_id'] ), sanitize_key( $row['object_type'] ), absint( $row['object_id'] ), $fallback );
			if ( ! $media ) { continue; }
			$eligible[] = array_merge( $media, array( 'creator_id' => absint( $row['creator_id'] ), 'qualified_streams' => absint( $row['qualified_streams'] ) ) );
		}
		return $eligible;
	}

	private static function allocate_minor( $rows, $budget ) {
		$budget = max( 0, (int) $budget );
		$total_streams = array_sum( array_map( function( $r ) { return (int) $r['qualified_streams']; }, $rows ) );
		if ( ! $rows || $budget <= 0 || $total_streams <= 0 ) { return array( $rows, 0, $total_streams ); }
		$used = 0; $fractions = array();
		foreach ( $rows as $i => &$row ) {
			$exact = ( $budget * (int) $row['qualified_streams'] ) / $total_streams;
			$row['amount'] = (int) floor( $exact );
			$fractions[ $i ] = $exact - $row['amount'];
			$used += $row['amount'];
		}
		unset( $row );
		$remainder = $budget - $used;
		arsort( $fractions, SORT_NUMERIC );
		foreach ( array_keys( $fractions ) as $i ) {
			if ( $remainder <= 0 ) { break; }
			$rows[ $i ]['amount']++; $remainder--;
		}
		return array( $rows, $budget, $total_streams );
	}

	public static function sync_period( $period ) {
		$period = self::valid_period( $period );
		if ( ! $period ) { return new WP_Error( 'nfinite_streaming_period_invalid', __( 'Invalid streaming accounting period.', 'nfinite-creators' ) ); }
		global $wpdb; $periods = self::periods_table(); $allocations = self::allocations_table();
		$existing = self::period_row( $period );
		if ( $existing && 'finalized' === $existing['status'] ) { return $existing; }
		$s = self::settings(); $currency = strtoupper( sanitize_text_field( $s['currency'] ) );
		$configured_budget = max( 0, (int) $s['monthly_budget_minor'] );
		$budget = min( $configured_budget, max( 0, self::reserve_balance() ) );
		$rows = self::eligible_stream_rows( $period );
		list( $rows, $allocated, $stream_count ) = self::allocate_minor( $rows, $budget );
		$now = current_time( 'mysql', true );
		$data = array( 'period' => $period, 'currency' => $currency, 'budget_amount' => $budget, 'allocated_amount' => $allocated, 'eligible_streams' => $stream_count, 'status' => 'open', 'updated_at' => $now );
		if ( $existing ) { $wpdb->update( $periods, $data, array( 'id' => absint( $existing['id'] ) ) ); }
		else { $data['created_at'] = $now; $wpdb->insert( $periods, $data ); }

		$seen = array();
		foreach ( $rows as $row ) {
			$key = $row['creator_id'] . '|' . $row['object_type'] . '|' . $row['object_id']; $seen[] = $key;
			$current = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$allocations} WHERE period=%s AND creator_id=%d AND object_type=%s AND object_id=%d", $period, $row['creator_id'], $row['object_type'], $row['object_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$alloc = array( 'period' => $period, 'creator_id' => $row['creator_id'], 'object_type' => $row['object_type'], 'object_id' => $row['object_id'], 'title' => $row['title'], 'qualified_streams' => $row['qualified_streams'], 'amount' => $row['amount'], 'status' => 'provisional', 'updated_at' => $now );
			if ( $current ) { $wpdb->update( $allocations, $alloc, array( 'id' => absint( $current ) ) ); }
			else { $alloc['created_at'] = $now; $wpdb->insert( $allocations, $alloc ); }
		}
		// Remove stale provisional rows when rights/status or fraud review changes before finalization.
		$existing_allocs = self::period_allocations( $period );
		foreach ( $existing_allocs as $alloc ) {
			$key = $alloc['creator_id'] . '|' . $alloc['object_type'] . '|' . $alloc['object_id'];
			if ( ! in_array( $key, $seen, true ) && 'provisional' === $alloc['status'] ) { $wpdb->delete( $allocations, array( 'id' => absint( $alloc['id'] ) ) ); }
		}
		return self::period_row( $period );
	}

	public static function finalize_period( $period ) {
		$period = self::valid_period( $period ); if ( ! $period ) { return new WP_Error( 'nfinite_streaming_period_invalid', 'Invalid period.' ); }
		$current = self::sync_period( $period ); if ( is_wp_error( $current ) ) { return $current; }
		if ( 'finalized' === ( $current['status'] ?? '' ) ) { return $current; }
		global $wpdb; $periods = self::periods_table(); $allocations = self::allocations_table(); $reserve = self::reserve_table();
		$alloc_rows = self::period_allocations( $period );
		$creator_totals = array();
		foreach ( $alloc_rows as $row ) {
			$cid = absint( $row['creator_id'] );
			if ( ! isset( $creator_totals[ $cid ] ) ) { $creator_totals[ $cid ] = array( 'amount' => 0, 'streams' => 0 ); }
			$creator_totals[ $cid ]['amount'] += (int) $row['amount'];
			$creator_totals[ $cid ]['streams'] += (int) $row['qualified_streams'];
		}
		$currency = $current['currency'] ?: 'USD'; $now = current_time( 'mysql', true );
		foreach ( $creator_totals as $creator_id => $totals ) {
			if ( $totals['amount'] <= 0 ) { continue; }
			$account_id = class_exists( 'Nfinite_Creators_Commerce' ) ? Nfinite_Creators_Commerce::creator_account_id( $creator_id, (int) get_post_field( 'post_author', $creator_id ) ) : '';
			Nfinite_Creators_Payments::record_earning( array(
				'entry_key' => 'music_streaming:' . $period . ':' . $creator_id,
				'creator_id' => $creator_id, 'source_type' => 'music_streaming', 'source_id' => $period,
				'source_label' => sprintf( __( 'PairOfDice streaming statement — %s', 'nfinite-creators' ), $period ),
				'earning_kind' => 'content', 'accounting_period' => $period, 'currency' => $currency,
				'eligible_amount' => $totals['amount'], 'platform_fee_amount' => 0, 'creator_amount' => $totals['amount'],
				'status' => 'finalized', 'earning_status' => 'finalized', 'payout_status' => $account_id ? 'pending' : 'blocked',
				'is_provisional' => 0, 'finalized_at' => $now, 'connected_account_id' => $account_id,
				'last_error' => $account_id ? '' : __( 'Connect Stripe to receive finalized streaming earnings.', 'nfinite-creators' ),
			) );
		}
		$wpdb->update( $allocations, array( 'status' => 'finalized', 'updated_at' => $now ), array( 'period' => $period ) );
		$wpdb->update( $periods, array( 'status' => 'finalized', 'finalized_at' => $now, 'updated_at' => $now ), array( 'period' => $period ) );
		$amount = (int) $current['allocated_amount'];
		if ( $amount > 0 ) {
			$wpdb->replace( $reserve, array(
				'entry_key' => 'period-allocation:' . $period, 'period' => $period, 'entry_type' => 'allocation', 'amount' => -$amount,
				'currency' => $currency, 'note' => 'Finalized creator streaming statements', 'created_at' => $now,
			) );
		}
		return self::period_row( $period );
	}

	public static function pay_period( $period ) {
		$period = self::valid_period( $period ); $period_row = self::period_row( $period );
		if ( ! $period_row || 'finalized' !== $period_row['status'] ) { return new WP_Error( 'nfinite_streaming_not_finalized', __( 'Finalize the accounting period before paying statements.', 'nfinite-creators' ) ); }
		global $wpdb; $ledger = Nfinite_Creators_Payments::table_name();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$ledger} WHERE source_type='music_streaming' AND accounting_period=%s ORDER BY creator_id ASC", $period ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = array();
		foreach ( $rows as $row ) { $results[ absint( $row['creator_id'] ) ] = self::pay_statement_row( $row ); }
		return $results;
	}

	private static function pay_statement_row( $row ) {
		if ( ! class_exists( 'Nfinite_Creators_Commerce' ) || ! Nfinite_Creators_Commerce::enabled() ) { return new WP_Error( 'nfinite_streaming_stripe_disabled', __( 'Stripe Connect is not enabled.', 'nfinite-creators' ) ); }
		if ( ! empty( $row['transfer_id'] ) ) { return $row; }
		$creator_id = absint( $row['creator_id'] ); $amount = max( 0, (int) $row['creator_amount'] - (int) $row['reversed_amount'] );
		if ( $amount <= 0 ) { return new WP_Error( 'nfinite_streaming_zero_amount', __( 'Statement amount is zero.', 'nfinite-creators' ) ); }
		$account_id = Nfinite_Creators_Commerce::creator_account_id( $creator_id, (int) get_post_field( 'post_author', $creator_id ) );
		if ( ! $account_id ) { Nfinite_Creators_Payments::update_earning_entry( $row['entry_key'], array( 'status' => 'blocked', 'payout_status' => 'blocked', 'last_error' => 'Creator has not connected Stripe.' ) ); return new WP_Error( 'nfinite_streaming_no_account', __( 'Creator has not connected Stripe.', 'nfinite-creators' ) ); }
		$status = Nfinite_Creators_Commerce::cached_status( $creator_id, (int) get_post_field( 'post_author', $creator_id ) );
		if ( 'active' !== ( $status['transfer_status'] ?? '' ) ) {
			$remote = Nfinite_Creators_Commerce::sync_account_status( $creator_id, (int) get_post_field( 'post_author', $creator_id ), $account_id );
			if ( ! is_wp_error( $remote ) ) { $status = $remote; }
		}
		if ( 'active' !== ( $status['transfer_status'] ?? '' ) ) { Nfinite_Creators_Payments::update_earning_entry( $row['entry_key'], array( 'status' => 'blocked', 'payout_status' => 'blocked', 'last_error' => 'Creator Stripe account is not enabled for transfers.' ) ); return new WP_Error( 'nfinite_streaming_account_blocked', __( 'Creator Stripe account is not enabled for transfers.', 'nfinite-creators' ) ); }

		$payload = array(
			'amount' => $amount, 'currency' => strtolower( $row['currency'] ?: 'USD' ), 'destination' => $account_id,
			'transfer_group' => 'NFINITE_STREAMING_' . str_replace( '-', '_', $row['accounting_period'] ),
			'description' => sprintf( 'PairOfDice streaming earnings - %s', $row['accounting_period'] ),
			'metadata' => array( 'nfinite_creator_id' => (string) $creator_id, 'accounting_period' => (string) $row['accounting_period'], 'source_type' => 'music_streaming' ),
		);
		$transfer = Nfinite_Creators_Payments::stripe_request( 'POST', '/v1/transfers', $payload, array( 'Idempotency-Key' => 'nfinite_streaming_' . $row['accounting_period'] . '_' . $creator_id . '_' . $amount ) );
		if ( is_wp_error( $transfer ) ) { Nfinite_Creators_Payments::update_earning_entry( $row['entry_key'], array( 'status' => 'failed', 'payout_status' => 'failed', 'last_error' => $transfer->get_error_message() ) ); return $transfer; }
		Nfinite_Creators_Payments::update_earning_entry( $row['entry_key'], array(
			'connected_account_id' => $account_id, 'transfer_group' => $payload['transfer_group'], 'transfer_id' => sanitize_text_field( $transfer['id'] ?? '' ),
			'status' => 'transferred', 'payout_status' => 'transferred', 'last_error' => '',
		) );
		return $transfer;
	}

	public static function creator_streaming_summary( $creator_id, $period = '' ) {
		$period = self::valid_period( $period ?: self::current_period() );
		$allocs = self::period_allocations( $period, $creator_id ); $amount = 0; $streams = 0; $status = 'none';
		foreach ( $allocs as $row ) { $amount += (int) $row['amount']; $streams += (int) $row['qualified_streams']; $status = $row['status']; }
		return array( 'period' => $period, 'qualified_streams' => $streams, 'amount_minor' => $amount, 'status' => $status, 'allocations' => $allocs );
	}

	public static function admin_menu() {
		add_submenu_page( 'edit.php?post_type=nfinite_creator', __( 'Streaming Earnings', 'nfinite-creators' ), __( 'Streaming Earnings', 'nfinite-creators' ), 'manage_options', 'nfinite-streaming-earnings', array( __CLASS__, 'admin_page' ) );
	}

	private static function money( $minor, $currency = 'USD' ) {
		$major = class_exists( 'Nfinite_Creators_Payments' ) ? Nfinite_Creators_Payments::from_minor( (int) $minor, $currency ) : ( (int) $minor / 100 );
		return function_exists( 'wc_price' ) ? wc_price( $major, array( 'currency' => $currency ) ) : '$' . number_format_i18n( $major, 2 );
	}

	public static function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$s = self::settings(); $period = isset( $_GET['period'] ) ? self::valid_period( wp_unslash( $_GET['period'] ) ) : self::current_period(); if ( ! $period ) { $period = self::current_period(); }
		$row = self::period_row( $period ); $allocs = self::period_allocations( $period ); $balance = self::reserve_balance();
		$creator_totals = array(); foreach ( $allocs as $a ) { $cid = absint( $a['creator_id'] ); if ( ! isset( $creator_totals[$cid] ) ) { $creator_totals[$cid] = array('streams'=>0,'amount'=>0); } $creator_totals[$cid]['streams'] += (int)$a['qualified_streams']; $creator_totals[$cid]['amount'] += (int)$a['amount']; }
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Nfinite Streaming Earnings', 'nfinite-creators' ); ?></h1>
		<p><?php esc_html_e( 'Streaming Earnings V1 allocates an internal monthly reserve across qualified listening for music that PairOfDice has approved for monetization. Public plays and payable engagement remain separate.', 'nfinite-creators' ); ?></p>
		<?php if ( isset( $_GET['nfinite_streaming_notice'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['nfinite_streaming_notice'] ) ) ); ?></p></div><?php endif; ?>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;max-width:900px;margin:18px 0;">
			<div class="card"><h2><?php esc_html_e( 'Internal reserve', 'nfinite-creators' ); ?></h2><p style="font-size:24px;margin:.4em 0;"><strong><?php echo wp_kses_post( self::money( $balance, $s['currency'] ) ); ?></strong></p><p><?php esc_html_e( 'Bookkeeping reserve available for future finalized streaming statements.', 'nfinite-creators' ); ?></p></div>
			<div class="card"><h2><?php echo esc_html( $period ); ?></h2><p style="font-size:24px;margin:.4em 0;"><strong><?php echo wp_kses_post( self::money( $row ? $row['allocated_amount'] : 0, $s['currency'] ) ); ?></strong></p><p><?php printf( esc_html__( '%1$s eligible streams • %2$s', 'nfinite-creators' ), esc_html( number_format_i18n( $row ? $row['eligible_streams'] : 0 ) ), esc_html( $row ? ucfirst( $row['status'] ) : 'Not synced' ) ); ?></p></div>
		</div>

		<h2><?php esc_html_e( 'Program settings', 'nfinite-creators' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="nfinite_streaming_save_settings"><?php wp_nonce_field( 'nfinite_streaming_save_settings' ); ?>
		<table class="form-table"><tr><th><?php esc_html_e( 'Enable streaming earnings', 'nfinite-creators' ); ?></th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'], '1' ); ?>> <?php esc_html_e( 'Calculate provisional earnings from qualified, monetized PairOfDice audio listening', 'nfinite-creators' ); ?></label></td></tr>
		<tr><th><label for="nfinite-monthly-budget"><?php esc_html_e( 'Monthly allocation cap', 'nfinite-creators' ); ?></label></th><td><input id="nfinite-monthly-budget" type="number" min="0" step="0.01" name="monthly_budget" value="<?php echo esc_attr( number_format( Nfinite_Creators_Payments::from_minor( (int)$s['monthly_budget_minor'], $s['currency'] ), 2, '.', '' ) ); ?>"> <?php echo esc_html( $s['currency'] ); ?><p class="description"><?php esc_html_e( 'The period can allocate no more than this amount and never more than the available internal reserve.', 'nfinite-creators' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'Automatic provisional sync', 'nfinite-creators' ); ?></th><td><label><input type="checkbox" name="auto_sync" value="1" <?php checked( $s['auto_sync'], '1' ); ?>> <?php esc_html_e( 'Refresh the open period hourly and after new qualified listening (rate limited)', 'nfinite-creators' ); ?></label></td></tr></table><?php submit_button( __( 'Save streaming settings', 'nfinite-creators' ) ); ?></form>

		<h2><?php esc_html_e( 'Add to internal reserve', 'nfinite-creators' ); ?></h2><p><?php esc_html_e( 'This records internal funding only. It does not charge a payment method or add money to your Stripe balance.', 'nfinite-creators' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;"><input type="hidden" name="action" value="nfinite_streaming_add_reserve"><?php wp_nonce_field( 'nfinite_streaming_add_reserve' ); ?><label><?php esc_html_e( 'Amount', 'nfinite-creators' ); ?><br><input type="number" min="0.01" step="0.01" name="amount" required></label><label><?php esc_html_e( 'Note', 'nfinite-creators' ); ?><br><input type="text" class="regular-text" name="note" placeholder="Sponsorship revenue allocation"></label><?php submit_button( __( 'Add reserve entry', 'nfinite-creators' ), 'secondary', 'submit', false ); ?></form>

		<hr><h2><?php esc_html_e( 'Accounting period', 'nfinite-creators' ); ?></h2>
		<form method="get" style="margin-bottom:12px;"><input type="hidden" name="post_type" value="nfinite_creator"><input type="hidden" name="page" value="nfinite-streaming-earnings"><input type="month" name="period" value="<?php echo esc_attr($period); ?>"> <button class="button"><?php esc_html_e( 'View', 'nfinite-creators' ); ?></button></form>
		<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="nfinite_streaming_sync_period"><input type="hidden" name="period" value="<?php echo esc_attr($period); ?>"><?php wp_nonce_field('nfinite_streaming_sync_period'); ?><button class="button button-primary" <?php disabled( $row && 'finalized' === $row['status'] ); ?>><?php esc_html_e( 'Sync provisional earnings', 'nfinite-creators' ); ?></button></form>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Finalize this period? Qualified streams and creator statement amounts will be locked and the allocation will be deducted from the internal reserve.');"><input type="hidden" name="action" value="nfinite_streaming_finalize_period"><input type="hidden" name="period" value="<?php echo esc_attr($period); ?>"><?php wp_nonce_field('nfinite_streaming_finalize_period'); ?><button class="button" <?php disabled( $row && 'finalized' === $row['status'] ); ?>><?php esc_html_e( 'Finalize period', 'nfinite-creators' ); ?></button></form>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Send finalized streaming statements to eligible connected Stripe accounts? This moves real funds when Stripe live mode is configured.');"><input type="hidden" name="action" value="nfinite_streaming_pay_period"><input type="hidden" name="period" value="<?php echo esc_attr($period); ?>"><?php wp_nonce_field('nfinite_streaming_pay_period'); ?><button class="button" <?php disabled( ! $row || 'finalized' !== $row['status'] ); ?>><?php esc_html_e( 'Pay finalized statements', 'nfinite-creators' ); ?></button></form>
		</div>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e('Creator','nfinite-creators'); ?></th><th><?php esc_html_e('Qualified streams','nfinite-creators'); ?></th><th><?php esc_html_e('Statement amount','nfinite-creators'); ?></th></tr></thead><tbody><?php if(!$creator_totals): ?><tr><td colspan="3"><?php esc_html_e('No eligible monetized qualified streams in this period yet.','nfinite-creators'); ?></td></tr><?php else: foreach($creator_totals as $cid=>$totals): ?><tr><td><a href="<?php echo esc_url(get_edit_post_link($cid)); ?>"><?php echo esc_html(get_the_title($cid)); ?></a></td><td><?php echo esc_html(number_format_i18n($totals['streams'])); ?></td><td><?php echo wp_kses_post(self::money($totals['amount'],$s['currency'])); ?></td></tr><?php endforeach; endif; ?></tbody></table>
		<h2><?php esc_html_e('Recent reserve activity','nfinite-creators'); ?></h2><table class="widefat striped"><thead><tr><th>Date</th><th>Type</th><th>Period</th><th>Amount</th><th>Note</th></tr></thead><tbody><?php foreach(self::reserve_entries() as $entry): ?><tr><td><?php echo esc_html($entry['created_at']); ?></td><td><?php echo esc_html(ucfirst($entry['entry_type'])); ?></td><td><?php echo esc_html($entry['period']); ?></td><td><?php echo wp_kses_post(self::money($entry['amount'],$entry['currency'])); ?></td><td><?php echo esc_html($entry['note']); ?></td></tr><?php endforeach; ?></tbody></table>
		</div><?php
	}

	private static function redirect_notice( $message, $period = '' ) {
		$url = add_query_arg( array( 'post_type' => 'nfinite_creator', 'page' => 'nfinite-streaming-earnings', 'nfinite_streaming_notice' => $message ), admin_url( 'edit.php' ) );
		if ( $period ) { $url = add_query_arg( 'period', $period, $url ); }
		wp_safe_redirect( $url ); exit;
	}

	public static function save_settings() {
		if ( ! current_user_can('manage_options') ) { wp_die('Forbidden'); } check_admin_referer('nfinite_streaming_save_settings');
		$s = self::settings(); $s['enabled'] = isset($_POST['enabled']) ? '1' : '0'; $s['auto_sync'] = isset($_POST['auto_sync']) ? '1' : '0';
		$major = isset($_POST['monthly_budget']) ? max(0,(float)wp_unslash($_POST['monthly_budget'])) : 0; $s['monthly_budget_minor'] = Nfinite_Creators_Payments::to_minor($major,$s['currency']);
		update_option(self::OPTION_KEY,$s,false); self::redirect_notice(__('Streaming earnings settings saved.','nfinite-creators'));
	}

	public static function add_reserve() {
		if ( ! current_user_can('manage_options') ) { wp_die('Forbidden'); } check_admin_referer('nfinite_streaming_add_reserve');
		$s=self::settings(); $major=isset($_POST['amount'])?max(0,(float)wp_unslash($_POST['amount'])):0; $minor=Nfinite_Creators_Payments::to_minor($major,$s['currency']); if($minor<=0){self::redirect_notice(__('Reserve amount must be greater than zero.','nfinite-creators'));}
		global $wpdb; $wpdb->insert(self::reserve_table(),array('entry_key'=>'deposit:'.wp_generate_uuid4(),'period'=>'','entry_type'=>'deposit','amount'=>$minor,'currency'=>$s['currency'],'note'=>isset($_POST['note'])?sanitize_text_field(wp_unslash($_POST['note'])):'','created_at'=>current_time('mysql',true)));
		self::redirect_notice(__('Internal streaming reserve updated.','nfinite-creators'));
	}

	public static function admin_sync_period() { if(!current_user_can('manage_options')){wp_die('Forbidden');} check_admin_referer('nfinite_streaming_sync_period'); $period=self::valid_period($_POST['period']??''); $r=self::sync_period($period); self::redirect_notice(is_wp_error($r)?$r->get_error_message():__('Provisional streaming earnings synced.','nfinite-creators'),$period); }
	public static function admin_finalize_period() { if(!current_user_can('manage_options')){wp_die('Forbidden');} check_admin_referer('nfinite_streaming_finalize_period'); $period=self::valid_period($_POST['period']??''); $r=self::finalize_period($period); self::redirect_notice(is_wp_error($r)?$r->get_error_message():__('Streaming accounting period finalized.','nfinite-creators'),$period); }
	public static function admin_pay_period() { if(!current_user_can('manage_options')){wp_die('Forbidden');} check_admin_referer('nfinite_streaming_pay_period'); $period=self::valid_period($_POST['period']??''); $r=self::pay_period($period); $message=is_wp_error($r)?$r->get_error_message():sprintf(__('Processed %d creator streaming statement(s). Review Creator Earnings and Stripe for individual payout status.','nfinite-creators'),count($r)); self::redirect_notice($message,$period); }
}
