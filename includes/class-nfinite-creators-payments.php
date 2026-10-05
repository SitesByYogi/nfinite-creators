<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Creator Payments V1.
 *
 * Uses Stripe Connect separate charges and transfers: WooCommerce/Stripe creates
 * the platform charge, then Nfinite transfers each creator's net share to that
 * creator's connected Stripe account. The platform fee is retained by simply
 * transferring less than the eligible creator merchandise subtotal.
 *
 * Important: this class moves money between Stripe accounts. Automatic transfers
 * are opt-in and should be tested with Stripe test keys/accounts first.
 */
class Nfinite_Creators_Payments {
	const DB_VERSION = '2';
	const DB_VERSION_OPTION = 'nfinite_creator_payments_db_version';
	const META_ORDER_PROCESSED = '_nfinite_creator_transfers_processed';
	const META_ORDER_CHARGE = '_nfinite_creator_source_charge_id';
	const META_ORDER_TRANSFER_GROUP = '_nfinite_creator_transfer_group';
	const META_REFUND_PROCESSED = '_nfinite_creator_refund_reversal_processed';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 2 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'maybe_process_order' ), 40 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_process_order' ), 40 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'handle_order_refund' ), 20, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'admin_post_nfinite_retry_creator_transfers', array( __CLASS__, 'admin_retry_transfers' ) );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table_name();
		$charset = $wpdb->get_charset_collate();
		// 0.36.0 turns the commerce transfer table into the unified Nfinite Creator Earnings ledger.
		// Older installs used a unique order_creator index, which would prevent multiple non-WooCommerce
		// earnings for the same creator because those rows intentionally use order_id=0. Remove it first.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists === $table ) {
			$old_index = $wpdb->get_var( "SHOW INDEX FROM {$table} WHERE Key_name='order_creator'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $old_index ) {
				$wpdb->query( "ALTER TABLE {$table} DROP INDEX order_creator" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			}
		}
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entry_key varchar(191) NOT NULL DEFAULT '',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			creator_id bigint(20) unsigned NOT NULL,
			source_type varchar(60) NOT NULL DEFAULT 'product_sale',
			source_id varchar(191) NOT NULL DEFAULT '',
			source_label varchar(255) NOT NULL DEFAULT '',
			earning_kind varchar(40) NOT NULL DEFAULT 'commerce',
			accounting_period varchar(20) NOT NULL DEFAULT '',
			connected_account_id varchar(255) NOT NULL DEFAULT '',
			currency varchar(12) NOT NULL DEFAULT '',
			eligible_amount bigint(20) NOT NULL DEFAULT 0,
			platform_fee_amount bigint(20) NOT NULL DEFAULT 0,
			creator_amount bigint(20) NOT NULL DEFAULT 0,
			source_charge_id varchar(255) NOT NULL DEFAULT '',
			transfer_group varchar(255) NOT NULL DEFAULT '',
			transfer_id varchar(255) NOT NULL DEFAULT '',
			status varchar(40) NOT NULL DEFAULT 'pending',
			earning_status varchar(40) NOT NULL DEFAULT 'finalized',
			payout_status varchar(40) NOT NULL DEFAULT 'pending',
			reversed_amount bigint(20) NOT NULL DEFAULT 0,
			is_provisional tinyint(1) NOT NULL DEFAULT 0,
			finalized_at datetime NULL,
			last_error text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY entry_key_lookup (entry_key),
			KEY order_creator (order_id,creator_id),
			KEY creator_status (creator_id,status),
			KEY creator_source (creator_id,source_type),
			KEY accounting_period (accounting_period),
			KEY transfer_id (transfer_id),
			KEY source_charge_id (source_charge_id)
		) {$charset};";
		dbDelta( $sql );

		// Backfill stable identifiers and source metadata for pre-0.36 WooCommerce rows.
		$wpdb->query( "UPDATE {$table} SET entry_key=CONCAT('woocommerce:',order_id,':',creator_id) WHERE entry_key='' AND order_id>0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "UPDATE {$table} SET source_type='product_sale', source_id=CAST(order_id AS CHAR), earning_kind='commerce' WHERE order_id>0 AND (source_id='' OR source_type='')" );
		$wpdb->query( "UPDATE {$table} SET earning_status='finalized', payout_status=status WHERE order_id>0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$unique_index = $wpdb->get_var( "SHOW INDEX FROM {$table} WHERE Key_name='entry_key_unique'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $unique_index ) {
			$wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY entry_key_unique (entry_key)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public static function maybe_install() {
		if ( self::DB_VERSION !== (string) get_option( self::DB_VERSION_OPTION, '' ) ) {
			self::install();
		}
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'nfinite_creator_ledger';
	}

	public static function settings() {
		$settings = class_exists( 'Nfinite_Creators_Commerce' ) ? Nfinite_Creators_Commerce::settings() : array();
		return wp_parse_args( is_array( $settings ) ? $settings : array(), array(
			'automatic_transfers' => '0',
			'transfer_timing' => 'completed',
			'webhook_secret' => '',
		) );
	}

	public static function automatic_transfers_enabled() {
		$s = self::settings();
		return class_exists( 'Nfinite_Creators_Commerce' ) && Nfinite_Creators_Commerce::enabled() && '1' === (string) $s['automatic_transfers'];
	}

	public static function webhook_secret() {
		if ( defined( 'NFINITE_STRIPE_WEBHOOK_SECRET' ) && NFINITE_STRIPE_WEBHOOK_SECRET ) {
			return trim( (string) NFINITE_STRIPE_WEBHOOK_SECRET );
		}
		$s = self::settings();
		return trim( (string) $s['webhook_secret'] );
	}

	public static function maybe_process_order( $order_id ) {
		if ( ! self::automatic_transfers_enabled() || ! function_exists( 'wc_get_order' ) ) { return; }
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() ) { return; }
		$s = self::settings();
		$timing = sanitize_key( $s['transfer_timing'] );
		if ( 'completed' === $timing && 'completed' !== $order->get_status() ) { return; }
		if ( 'processing' === $timing && ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) { return; }
		self::process_order( $order );
	}

	public static function process_order( $order, $force = false ) {
		if ( is_numeric( $order ) ) { $order = wc_get_order( $order ); }
		if ( ! $order || ! $order->is_paid() ) {
			return new WP_Error( 'nfinite_order_not_paid', __( 'The WooCommerce order has not been paid.', 'nfinite-creators' ) );
		}

		$allocations = self::order_allocations( $order );
		if ( empty( $allocations ) ) { return new WP_Error( 'nfinite_no_creator_items', __( 'This order has no creator-owned items.', 'nfinite-creators' ) ); }

		$charge_id = self::resolve_source_charge_id( $order );
		if ( is_wp_error( $charge_id ) ) {
			foreach ( $allocations as $creator_id => $allocation ) {
				self::upsert_ledger( $order, $creator_id, $allocation, array( 'status' => 'failed', 'last_error' => $charge_id->get_error_message() ) );
			}
			return $charge_id;
		}
		$order->update_meta_data( self::META_ORDER_CHARGE, $charge_id );
		$transfer_group = 'NFINITE_ORDER_' . $order->get_id();
		$order->update_meta_data( self::META_ORDER_TRANSFER_GROUP, $transfer_group );
		$order->save();

		$charge = self::stripe_request( 'GET', '/v1/charges/' . rawurlencode( $charge_id ) );
		if ( is_wp_error( $charge ) ) { return $charge; }
		if ( empty( $charge['paid'] ) || ! empty( $charge['refunded'] ) ) {
			return new WP_Error( 'nfinite_charge_not_transferable', __( 'The Stripe charge is not in a transferable state.', 'nfinite-creators' ) );
		}
		$order_currency = strtolower( $order->get_currency() );
		if ( ! empty( $charge['currency'] ) && strtolower( $charge['currency'] ) !== $order_currency ) {
			return new WP_Error( 'nfinite_charge_currency_mismatch', __( 'The Stripe charge currency does not match the WooCommerce order.', 'nfinite-creators' ) );
		}

		$results = array();
		foreach ( $allocations as $creator_id => $allocation ) {
			$existing = self::ledger_for_order_creator( $order->get_id(), $creator_id );
			if ( $existing && ! empty( $existing['transfer_id'] ) && ! $force ) {
				$results[ $creator_id ] = $existing;
				continue;
			}

			$account_id = class_exists( 'Nfinite_Creators_Commerce' ) ? Nfinite_Creators_Commerce::creator_account_id( $creator_id, (int) get_post_field( 'post_author', $creator_id ) ) : '';
			if ( ! $account_id ) {
				$results[ $creator_id ] = self::upsert_ledger( $order, $creator_id, $allocation, array(
					'source_charge_id' => $charge_id,
					'transfer_group' => $transfer_group,
					'status' => 'blocked',
					'last_error' => __( 'Creator has not connected a Stripe payout account.', 'nfinite-creators' ),
				) );
				continue;
			}

			$status = Nfinite_Creators_Commerce::cached_status( $creator_id, (int) get_post_field( 'post_author', $creator_id ) );
			if ( 'active' !== ( isset( $status['transfer_status'] ) ? $status['transfer_status'] : '' ) ) {
				$remote = Nfinite_Creators_Commerce::sync_account_status( $creator_id, (int) get_post_field( 'post_author', $creator_id ), $account_id );
				if ( ! is_wp_error( $remote ) ) { $status = $remote; }
			}
			if ( 'active' !== ( isset( $status['transfer_status'] ) ? $status['transfer_status'] : '' ) ) {
				$results[ $creator_id ] = self::upsert_ledger( $order, $creator_id, $allocation, array(
					'connected_account_id' => $account_id,
					'source_charge_id' => $charge_id,
					'transfer_group' => $transfer_group,
					'status' => 'blocked',
					'last_error' => __( 'Creator Stripe account is not enabled for transfers.', 'nfinite-creators' ),
				) );
				continue;
			}

			$creator_amount = (int) $allocation['creator_amount'];
			if ( $creator_amount <= 0 ) {
				$results[ $creator_id ] = self::upsert_ledger( $order, $creator_id, $allocation, array( 'status' => 'skipped', 'last_error' => __( 'Creator transfer amount is zero.', 'nfinite-creators' ) ) );
				continue;
			}

			$payload = array(
				'amount' => $creator_amount,
				'currency' => strtolower( $order->get_currency() ),
				'destination' => $account_id,
				'source_transaction' => $charge_id,
				'transfer_group' => $transfer_group,
				'description' => sprintf( 'PairOfDice creator earnings - order #%d', $order->get_id() ),
				'metadata' => array(
					'woocommerce_order_id' => (string) $order->get_id(),
					'nfinite_creator_id' => (string) $creator_id,
					'nfinite_platform_fee' => (string) $allocation['platform_fee'],
				),
			);
			$idempotency = 'nfinite_transfer_' . $order->get_id() . '_' . $creator_id . '_' . $creator_amount;
			$transfer = self::stripe_request( 'POST', '/v1/transfers', $payload, array( 'Idempotency-Key' => $idempotency ) );
			if ( is_wp_error( $transfer ) ) {
				$results[ $creator_id ] = self::upsert_ledger( $order, $creator_id, $allocation, array(
					'connected_account_id' => $account_id,
					'source_charge_id' => $charge_id,
					'transfer_group' => $transfer_group,
					'status' => 'failed',
					'last_error' => $transfer->get_error_message(),
				) );
				$order->add_order_note( sprintf( __( 'Nfinite creator transfer failed for creator #%1$d: %2$s', 'nfinite-creators' ), $creator_id, $transfer->get_error_message() ) );
				continue;
			}

			$results[ $creator_id ] = self::upsert_ledger( $order, $creator_id, $allocation, array(
				'connected_account_id' => $account_id,
				'source_charge_id' => $charge_id,
				'transfer_group' => $transfer_group,
				'transfer_id' => sanitize_text_field( $transfer['id'] ?? '' ),
				'status' => 'transferred',
				'reversed_amount' => isset( $transfer['amount_reversed'] ) ? absint( $transfer['amount_reversed'] ) : 0,
				'last_error' => '',
			) );
			$order->add_order_note( sprintf( __( 'Nfinite transferred creator #%1$d earnings to Stripe. Transfer: %2$s', 'nfinite-creators' ), $creator_id, sanitize_text_field( $transfer['id'] ?? '' ) ) );
		}

		$order->update_meta_data( self::META_ORDER_PROCESSED, gmdate( 'c' ) );
		$order->save();
		return $results;
	}

	public static function order_allocations( $order ) {
		$fee_percent = class_exists( 'Nfinite_Creators_Commerce_Dashboard' ) ? Nfinite_Creators_Commerce_Dashboard::platform_fee_percent() : 10.0;
		$currency = $order->get_currency();
		$allocations = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product_id = $item->get_product_id();
			$creator_id = absint( get_post_meta( $product_id, '_nfinite_creator_id', true ) );
			if ( ! $creator_id ) { continue; }
			// V1 payout base is the discounted merchandise/service line total excluding tax and shipping.
			$eligible_minor = self::to_minor( (float) $item->get_total(), $currency );
			if ( $eligible_minor <= 0 ) { continue; }
			if ( ! isset( $allocations[ $creator_id ] ) ) {
				$allocations[ $creator_id ] = array( 'eligible_amount' => 0, 'platform_fee' => 0, 'creator_amount' => 0, 'currency' => $currency, 'source_types' => array() );
			}
			$allocations[ $creator_id ]['eligible_amount'] += $eligible_minor;
			$product_type = sanitize_key( (string) get_post_meta( $product_id, '_nfinite_creator_product_type', true ) );
			$source_type = 'product_sale';
			if ( 'beat' === $product_type ) { $source_type = 'beat_sale'; }
			elseif ( in_array( $product_type, array( 'service', 'booking' ), true ) ) { $source_type = 'service_sale'; }
			$allocations[ $creator_id ]['source_types'][ $source_type ] = true;
		}
		foreach ( $allocations as $creator_id => &$allocation ) {
			$allocation['platform_fee'] = (int) round( $allocation['eligible_amount'] * ( $fee_percent / 100 ) );
			$allocation['creator_amount'] = max( 0, $allocation['eligible_amount'] - $allocation['platform_fee'] );
			$types = array_keys( $allocation['source_types'] );
			$allocation['source_type'] = 1 === count( $types ) ? reset( $types ) : 'product_sale';
		}
		unset( $allocation );
		return $allocations;
	}

	public static function resolve_source_charge_id( $order ) {
		$stored = sanitize_text_field( (string) $order->get_meta( self::META_ORDER_CHARGE, true ) );
		if ( 0 === strpos( $stored, 'ch_' ) ) { return $stored; }

		$candidates = array(
			$order->get_transaction_id(),
			$order->get_meta( '_stripe_charge_id', true ),
			$order->get_meta( '_stripe_source_id', true ),
			$order->get_meta( '_stripe_intent_id', true ),
			$order->get_meta( '_wc_stripe_intent_id', true ),
		);
		foreach ( $candidates as $candidate ) {
			$id = sanitize_text_field( (string) $candidate );
			if ( 0 === strpos( $id, 'ch_' ) ) { return $id; }
			if ( 0 === strpos( $id, 'pi_' ) ) {
				$intent = self::stripe_request( 'GET', '/v1/payment_intents/' . rawurlencode( $id ) );
				if ( is_wp_error( $intent ) ) { continue; }
				$charge = $intent['latest_charge'] ?? '';
				if ( is_array( $charge ) ) { $charge = $charge['id'] ?? ''; }
				if ( is_string( $charge ) && 0 === strpos( $charge, 'ch_' ) ) { return $charge; }
			}
		}
		return new WP_Error( 'nfinite_no_stripe_charge', __( 'Nfinite could not resolve the Stripe charge for this WooCommerce order. Automatic transfers require the platform payment to be processed by the Stripe account configured for Nfinite.', 'nfinite-creators' ) );
	}

	public static function handle_order_refund( $order_id, $refund_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) { return; }
		$refund = wc_get_order( $refund_id );
		$order = wc_get_order( $order_id );
		if ( ! $refund || ! $order || $refund->get_meta( self::META_REFUND_PROCESSED, true ) ) { return; }
		$creator_refunds = self::refund_creator_amounts( $order, $refund );
		foreach ( $creator_refunds as $creator_id => $refund_eligible_minor ) {
			$ledger = self::ledger_for_order_creator( $order_id, $creator_id );
			if ( ! $ledger || empty( $ledger['transfer_id'] ) || $refund_eligible_minor <= 0 ) { continue; }
			// Use the original ledger economics, not the site's current fee setting.
			$eligible_original = max( 1, (int) $ledger['eligible_amount'] );
			$creator_ratio = (int) $ledger['creator_amount'] / $eligible_original;
			$desired = max( 0, (int) round( $refund_eligible_minor * $creator_ratio ) );
			$remaining = max( 0, (int) $ledger['creator_amount'] - (int) $ledger['reversed_amount'] );
			$amount = min( $desired, $remaining );
			if ( $amount <= 0 ) { continue; }
			self::reverse_transfer( $ledger, $amount, 'woocommerce_refund_' . $refund_id );
		}
		$refund->update_meta_data( self::META_REFUND_PROCESSED, gmdate( 'c' ) );
		$refund->save();
	}

	private static function refund_creator_amounts( $order, $refund ) {
		$currency = $order->get_currency();
		$out = array();
		foreach ( $refund->get_items( 'line_item' ) as $item ) {
			$product_id = $item->get_product_id();
			$creator_id = absint( get_post_meta( $product_id, '_nfinite_creator_id', true ) );
			if ( ! $creator_id ) { continue; }
			$amount = abs( self::to_minor( (float) $item->get_total(), $currency ) );
			if ( $amount > 0 ) { $out[ $creator_id ] = ( $out[ $creator_id ] ?? 0 ) + $amount; }
		}
		if ( $out ) { return $out; }

		// Amount-only refund: allocate proportionally across creator eligible amounts.
		$total_refund = abs( self::to_minor( (float) $refund->get_amount(), $currency ) );
		$ledgers = self::ledgers_for_order( $order->get_id() );
		$total_eligible = array_sum( array_map( function( $l ) { return (int) $l['eligible_amount']; }, $ledgers ) );
		if ( $total_refund <= 0 || $total_eligible <= 0 ) { return array(); }
		$remaining = $total_refund;
		$count = count( $ledgers );
		foreach ( $ledgers as $i => $ledger ) {
			$share = ( $i === $count - 1 ) ? $remaining : (int) round( $total_refund * ( (int) $ledger['eligible_amount'] / $total_eligible ) );
			$out[ (int) $ledger['creator_id'] ] = max( 0, $share );
			$remaining -= $share;
		}
		return $out;
	}

	public static function reverse_transfer( $ledger, $amount, $reason = '' ) {
		$transfer_id = sanitize_text_field( $ledger['transfer_id'] ?? '' );
		if ( ! $transfer_id || $amount <= 0 ) { return new WP_Error( 'nfinite_no_transfer', __( 'There is no creator transfer to reverse.', 'nfinite-creators' ) ); }
		$payload = array(
			'amount' => absint( $amount ),
			'metadata' => array(
				'woocommerce_order_id' => (string) $ledger['order_id'],
				'nfinite_creator_id' => (string) $ledger['creator_id'],
				'nfinite_reason' => sanitize_text_field( $reason ),
			),
		);
		$idempotency = 'nfinite_reversal_' . $transfer_id . '_' . md5( $reason . ':' . $amount );
		$reversal = self::stripe_request( 'POST', '/v1/transfers/' . rawurlencode( $transfer_id ) . '/reversals', $payload, array( 'Idempotency-Key' => $idempotency ) );
		if ( is_wp_error( $reversal ) ) {
			self::update_ledger_by_id( $ledger['id'], array( 'last_error' => $reversal->get_error_message() ) );
			return $reversal;
		}
		$new_reversed = min( (int) $ledger['creator_amount'], (int) $ledger['reversed_amount'] + absint( $reversal['amount'] ?? $amount ) );
		$status = $new_reversed >= (int) $ledger['creator_amount'] ? 'reversed' : 'partially_reversed';
		self::update_ledger_by_id( $ledger['id'], array( 'reversed_amount' => $new_reversed, 'status' => $status, 'last_error' => '' ) );
		return $reversal;
	}

	public static function register_rest_routes() {
		register_rest_route( 'nfinite-creators/v1', '/stripe/webhook', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'webhook' ),
			'permission_callback' => '__return_true',
		) );
	}

	public static function webhook( WP_REST_Request $request ) {
		$payload = $request->get_body();
		$signature = $request->get_header( 'stripe-signature' );
		$secret = self::webhook_secret();
		if ( ! $secret || ! self::verify_webhook_signature( $payload, $signature, $secret ) ) {
			return new WP_REST_Response( array( 'received' => false ), 400 );
		}
		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['type'] ) ) { return new WP_REST_Response( array( 'received' => false ), 400 ); }
		self::reconcile_event( $event );
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	private static function verify_webhook_signature( $payload, $header, $secret, $tolerance = 300 ) {
		if ( ! $payload || ! $header ) { return false; }
		$timestamp = 0; $signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			list( $k, $v ) = array_pad( explode( '=', trim( $part ), 2 ), 2, '' );
			if ( 't' === $k ) { $timestamp = absint( $v ); }
			if ( 'v1' === $k ) { $signatures[] = $v; }
		}
		if ( ! $timestamp || abs( time() - $timestamp ) > $tolerance ) { return false; }
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $sig ) { if ( hash_equals( $expected, $sig ) ) { return true; } }
		return false;
	}

	private static function reconcile_event( $event ) {
		$type = sanitize_text_field( $event['type'] );
		$obj = $event['data']['object'] ?? array();
		if ( ! is_array( $obj ) ) { return; }
		if ( in_array( $type, array( 'transfer.created', 'transfer.updated', 'transfer.reversed' ), true ) ) {
			$transfer_id = sanitize_text_field( $obj['id'] ?? '' );
			if ( ! $transfer_id ) { return; }
			$ledger = self::ledger_by_transfer_id( $transfer_id );
			if ( ! $ledger ) { return; }
			$reversed = absint( $obj['amount_reversed'] ?? 0 );
			$status = $reversed > 0 ? ( $reversed >= (int) $ledger['creator_amount'] ? 'reversed' : 'partially_reversed' ) : 'transferred';
			self::update_ledger_by_id( $ledger['id'], array( 'status' => $status, 'payout_status' => $status, 'reversed_amount' => $reversed, 'last_error' => '' ) );
		}
		if ( 'charge.refunded' === $type ) {
			$charge_id = sanitize_text_field( $obj['id'] ?? '' );
			$charge_amount = max( 1, absint( $obj['amount'] ?? 0 ) );
			$amount_refunded = min( $charge_amount, absint( $obj['amount_refunded'] ?? 0 ) );
			if ( $charge_id && $amount_refunded > 0 ) {
				$ratio = $amount_refunded / $charge_amount;
				foreach ( self::ledgers_for_charge( $charge_id ) as $ledger ) {
					$target_reversed = min( (int) $ledger['creator_amount'], (int) round( (int) $ledger['creator_amount'] * $ratio ) );
					$delta = max( 0, $target_reversed - (int) $ledger['reversed_amount'] );
					if ( $delta > 0 ) { self::reverse_transfer( $ledger, $delta, 'stripe_charge_refund_' . $charge_id . '_' . $amount_refunded ); }
				}
			}
		}

		if ( 'charge.dispute.created' === $type ) {
			$charge_id = sanitize_text_field( $obj['charge'] ?? '' );
			foreach ( self::ledgers_for_charge( $charge_id ) as $ledger ) {
				$remaining = max( 0, (int) $ledger['creator_amount'] - (int) $ledger['reversed_amount'] );
				if ( $remaining > 0 ) { self::reverse_transfer( $ledger, $remaining, 'stripe_dispute_' . sanitize_text_field( $obj['id'] ?? '' ) ); }
				self::update_ledger_by_id( $ledger['id'], array( 'status' => 'disputed' ) );
			}
		}
	}

	/**
	 * Record or update a non-commerce creator earning in the unified ledger.
	 *
	 * Amounts are stored in the currency's minor unit, matching Stripe and the
	 * existing commerce payment ledger. Callers should supply a stable entry_key
	 * when an event may be retried so writes remain idempotent.
	 */
	public static function record_earning( $args ) {
		global $wpdb;
		$defaults = array(
			'entry_key' => '', 'creator_id' => 0, 'source_type' => 'adjustment', 'source_id' => '', 'source_label' => '',
			'earning_kind' => 'content', 'accounting_period' => gmdate( 'Y-m' ), 'currency' => 'USD',
			'eligible_amount' => 0, 'platform_fee_amount' => 0, 'creator_amount' => 0, 'status' => 'provisional',
			'earning_status' => 'provisional', 'payout_status' => 'not_scheduled', 'is_provisional' => 1, 'finalized_at' => null, 'connected_account_id' => '', 'source_charge_id' => '',
			'transfer_group' => '', 'transfer_id' => '', 'reversed_amount' => 0, 'last_error' => '',
		);
		$a = wp_parse_args( is_array( $args ) ? $args : array(), $defaults );
		$a['creator_id'] = absint( $a['creator_id'] );
		if ( ! $a['creator_id'] ) { return new WP_Error( 'nfinite_earning_creator_required', __( 'A creator is required for an earning.', 'nfinite-creators' ) ); }
		$a['source_type'] = self::sanitize_source_type( $a['source_type'] );
		$a['source_id'] = sanitize_text_field( (string) $a['source_id'] );
		$a['source_label'] = sanitize_text_field( (string) $a['source_label'] );
		$a['earning_kind'] = sanitize_key( $a['earning_kind'] );
		$a['accounting_period'] = preg_match( '/^\\d{4}-\\d{2}$/', (string) $a['accounting_period'] ) ? $a['accounting_period'] : gmdate( 'Y-m' );
		$a['currency'] = strtoupper( sanitize_text_field( (string) $a['currency'] ) );
		foreach ( array( 'eligible_amount', 'platform_fee_amount', 'creator_amount', 'reversed_amount' ) as $amount_key ) { $a[ $amount_key ] = max( 0, (int) $a[ $amount_key ] ); }
		$a['status'] = sanitize_key( $a['status'] );
		$a['earning_status'] = sanitize_key( $a['earning_status'] );
		$a['payout_status'] = sanitize_key( $a['payout_status'] );
		$a['is_provisional'] = empty( $a['is_provisional'] ) ? 0 : 1;
		if ( ! $a['entry_key'] ) {
			$a['entry_key'] = 'earning:' . $a['source_type'] . ':' . $a['creator_id'] . ':' . md5( $a['source_id'] . '|' . $a['accounting_period'] . '|' . wp_json_encode( array( $a['eligible_amount'], $a['creator_amount'] ) ) );
		}
		$a['entry_key'] = substr( sanitize_text_field( (string) $a['entry_key'] ), 0, 191 );
		$now = current_time( 'mysql', true );
		$data = array(
			'entry_key' => $a['entry_key'], 'order_id' => 0, 'creator_id' => $a['creator_id'], 'source_type' => $a['source_type'],
			'source_id' => $a['source_id'], 'source_label' => $a['source_label'], 'earning_kind' => $a['earning_kind'],
			'accounting_period' => $a['accounting_period'], 'connected_account_id' => sanitize_text_field( $a['connected_account_id'] ),
			'currency' => $a['currency'], 'eligible_amount' => $a['eligible_amount'], 'platform_fee_amount' => $a['platform_fee_amount'],
			'creator_amount' => $a['creator_amount'], 'source_charge_id' => sanitize_text_field( $a['source_charge_id'] ),
			'transfer_group' => sanitize_text_field( $a['transfer_group'] ), 'transfer_id' => sanitize_text_field( $a['transfer_id'] ),
			'status' => $a['status'], 'earning_status' => $a['earning_status'], 'payout_status' => $a['payout_status'], 'reversed_amount' => $a['reversed_amount'], 'is_provisional' => $a['is_provisional'],
			'finalized_at' => $a['finalized_at'] ? sanitize_text_field( $a['finalized_at'] ) : null, 'last_error' => sanitize_textarea_field( $a['last_error'] ),
			'updated_at' => $now,
		);
		$table = self::table_name();
		$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE entry_key=%s LIMIT 1", $a['entry_key'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $existing_id ) {
			$wpdb->update( $table, $data, array( 'id' => absint( $existing_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$id = absint( $existing_id );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$id = absint( $wpdb->insert_id );
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function finalize_earning( $entry_key, $status = 'finalized' ) {
		global $wpdb; $table = self::table_name();
		$entry_key = substr( sanitize_text_field( (string) $entry_key ), 0, 191 );
		if ( ! $entry_key ) { return false; }
		return false !== $wpdb->update( $table, array( 'is_provisional' => 0, 'status' => sanitize_key( $status ), 'earning_status' => sanitize_key( $status ), 'finalized_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ), array( 'entry_key' => $entry_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/** Update a unified earnings entry by stable key. Used by non-commerce payout engines. */
	public static function update_earning_entry( $entry_key, $data ) {
		global $wpdb; $table = self::table_name();
		$entry_key = substr( sanitize_text_field( (string) $entry_key ), 0, 191 );
		if ( ! $entry_key || ! is_array( $data ) ) { return false; }
		$allowed = array( 'connected_account_id', 'transfer_group', 'transfer_id', 'status', 'earning_status', 'payout_status', 'reversed_amount', 'is_provisional', 'finalized_at', 'last_error' );
		$clean = array();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) { continue; }
			$value = $data[ $key ];
			if ( in_array( $key, array( 'status', 'earning_status', 'payout_status' ), true ) ) { $value = sanitize_key( $value ); }
			elseif ( 'reversed_amount' === $key ) { $value = max( 0, (int) $value ); }
			elseif ( 'is_provisional' === $key ) { $value = empty( $value ) ? 0 : 1; }
			elseif ( 'last_error' === $key ) { $value = sanitize_textarea_field( $value ); }
			elseif ( null !== $value ) { $value = sanitize_text_field( (string) $value ); }
			$clean[ $key ] = $value;
		}
		if ( ! $clean ) { return false; }
		$clean['updated_at'] = current_time( 'mysql', true );
		return false !== $wpdb->update( $table, $clean, array( 'entry_key' => $entry_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	public static function supported_source_types() {
		return apply_filters( 'nfinite_creator_earning_source_types', array(
			'beat_sale', 'product_sale', 'service_sale', 'music_streaming', 'video_engagement', 'membership',
			'event', 'distribution', 'editorial', 'affiliate', 'sponsorship', 'adjustment',
		) );
	}

	public static function sanitize_source_type( $source_type ) {
		$source_type = sanitize_key( $source_type );
		return in_array( $source_type, self::supported_source_types(), true ) ? $source_type : 'adjustment';
	}

	public static function source_totals( $creator_id, $limit = 1000 ) {
		$totals = array();
		foreach ( self::creator_ledger( $creator_id, $limit ) as $row ) {
			$type = self::sanitize_source_type( $row['source_type'] ?? 'adjustment' );
			if ( ! isset( $totals[ $type ] ) ) { $totals[ $type ] = array( 'eligible_amount' => 0, 'creator_amount' => 0, 'reversed_amount' => 0, 'count' => 0 ); }
			$totals[ $type ]['eligible_amount'] += (int) $row['eligible_amount'];
			$totals[ $type ]['creator_amount'] += (int) $row['creator_amount'];
			$totals[ $type ]['reversed_amount'] += (int) $row['reversed_amount'];
			$totals[ $type ]['count']++;
		}
		return $totals;
	}

	public static function creator_ledger( $creator_id, $limit = 100 ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE creator_id=%d ORDER BY created_at DESC LIMIT %d", absint( $creator_id ), max( 1, absint( $limit ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Public read accessor for financial operations and statement tooling. */
	public static function ledger_entry_by_id( $id ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d LIMIT 1", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function ledger_for_order_creator( $order_id, $creator_id ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id=%d AND creator_id=%d LIMIT 1", absint( $order_id ), absint( $creator_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function ledgers_for_order( $order_id ) {
		global $wpdb; $table = self::table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id=%d", absint( $order_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function ledgers_for_charge( $charge_id ) {
		global $wpdb; $table = self::table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_charge_id=%s", sanitize_text_field( $charge_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function ledger_by_transfer_id( $transfer_id ) {
		global $wpdb; $table = self::table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE transfer_id=%s LIMIT 1", sanitize_text_field( $transfer_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function upsert_ledger( $order, $creator_id, $allocation, $extra = array() ) {
		global $wpdb; $table = self::table_name(); $now = current_time( 'mysql', true );
		$existing = self::ledger_for_order_creator( $order->get_id(), $creator_id );
		$data = array_merge( array(
			'entry_key' => 'woocommerce:' . $order->get_id() . ':' . $creator_id, 'order_id' => $order->get_id(), 'creator_id' => $creator_id,
			'source_type' => isset( $allocation['source_type'] ) ? self::sanitize_source_type( $allocation['source_type'] ) : 'product_sale', 'source_id' => (string) $order->get_id(), 'source_label' => sprintf( __( 'WooCommerce order #%d', 'nfinite-creators' ), $order->get_id() ),
			'earning_kind' => 'commerce', 'accounting_period' => gmdate( 'Y-m', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() ), 'connected_account_id' => '',
			'currency' => strtoupper( $order->get_currency() ), 'eligible_amount' => absint( $allocation['eligible_amount'] ),
			'platform_fee_amount' => absint( $allocation['platform_fee'] ), 'creator_amount' => absint( $allocation['creator_amount'] ),
			'source_charge_id' => '', 'transfer_group' => '', 'transfer_id' => '', 'status' => 'pending', 'earning_status' => 'finalized', 'payout_status' => 'pending', 'reversed_amount' => 0,
			'last_error' => '', 'updated_at' => $now,
		), $extra );
		if ( isset( $data['status'] ) && ! isset( $extra['payout_status'] ) ) { $data['payout_status'] = sanitize_key( $data['status'] ); }
		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => $existing['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return self::ledger_for_order_creator( $order->get_id(), $creator_id );
		}
		$data['created_at'] = $now;
		$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return self::ledger_for_order_creator( $order->get_id(), $creator_id );
	}

	private static function update_ledger_by_id( $id, $data ) {
		global $wpdb; $table = self::table_name();
		if ( isset( $data['status'] ) && ! isset( $data['payout_status'] ) ) { $data['payout_status'] = sanitize_key( $data['status'] ); }
		$data['updated_at'] = current_time( 'mysql', true );
		$wpdb->update( $table, $data, array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	public static function admin_retry_transfers() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to retry creator transfers.', 'nfinite-creators' ) ); }
		$order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;
		check_admin_referer( 'nfinite_retry_creator_transfers_' . $order_id );
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
		$result = $order ? self::process_order( $order, true ) : new WP_Error( 'nfinite_missing_order', __( 'Order not found.', 'nfinite-creators' ) );
		$url = wp_get_referer() ?: admin_url( 'edit.php?post_type=nfinite_creator&page=nfinite-creator-commerce' );
		wp_safe_redirect( add_query_arg( 'nfinite_transfer_retry', is_wp_error( $result ) ? 'error' : 'success', $url ) ); exit;
	}

	public static function stripe_request( $method, $path, $payload = null, $headers = array() ) {
		$key = class_exists( 'Nfinite_Creators_Commerce' ) ? Nfinite_Creators_Commerce::secret_key() : '';
		if ( ! $key ) { return new WP_Error( 'nfinite_stripe_missing_key', __( 'Stripe secret key is not configured.', 'nfinite-creators' ) ); }
		$args = array( 'method' => strtoupper( $method ), 'headers' => array_merge( array( 'Authorization' => 'Bearer ' . $key ), $headers ), 'timeout' => 30 );
		if ( null !== $payload ) { $args['body'] = $payload; }
		$response = wp_remote_request( 'https://api.stripe.com' . $path, $args );
		if ( is_wp_error( $response ) ) { return $response; }
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Stripe API request failed.', 'nfinite-creators' );
			return new WP_Error( 'nfinite_stripe_api_error', sanitize_text_field( $message ), array( 'status' => $code, 'response' => $body ) );
		}
		return is_array( $body ) ? $body : array();
	}

	public static function to_minor( $amount, $currency ) {
		$zero_decimal = array( 'BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF' );
		$multiplier = in_array( strtoupper( $currency ), $zero_decimal, true ) ? 1 : 100;
		return (int) round( (float) $amount * $multiplier );
	}

	public static function from_minor( $amount, $currency ) {
		$zero_decimal = array( 'BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF' );
		$divisor = in_array( strtoupper( $currency ), $zero_decimal, true ) ? 1 : 100;
		return (float) $amount / $divisor;
	}
}
