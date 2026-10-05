<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Creator-facing WooCommerce operations for Nfinite Creator Commerce.
 */
class Nfinite_Creators_Commerce_Dashboard {

	public static function init() {
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'handle_exclusive_sale' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'handle_exclusive_sale' ) );
	}

	public static function platform_fee_percent() {
		$settings = class_exists( 'Nfinite_Creators_Commerce' ) ? Nfinite_Creators_Commerce::settings() : array();
		$fee = isset( $settings['platform_fee'] ) ? (float) $settings['platform_fee'] : 10.0;
		return max( 0, min( 100, $fee ) );
	}

	public static function creator_orders( $creator_id, $limit = 75 ) {
		if ( ! function_exists( 'wc_get_orders' ) || ! $creator_id ) { return array(); }
		$orders = wc_get_orders( array( 'limit' => max( 1, absint( $limit ) ), 'orderby' => 'date', 'order' => 'DESC', 'status' => array_keys( wc_get_order_statuses() ) ) );
		$rows = array();
		foreach ( $orders as $order ) {
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$product_id = $item->get_product_id();
				if ( (int) get_post_meta( $product_id, '_nfinite_creator_id', true ) !== (int) $creator_id ) { continue; }
				$product = $item->get_product();
				$license = '';
				if ( $product && $product->is_type( 'variation' ) ) {
					$license_key = get_post_meta( $product->get_id(), '_nfinite_license_key', true );
					$labels = array( 'mp3' => 'MP3 Lease', 'wav' => 'WAV Lease', 'trackout' => 'Trackout', 'unlimited' => 'Unlimited', 'exclusive' => 'Exclusive' );
					$license = isset( $labels[ $license_key ] ) ? $labels[ $license_key ] : '';
				}
				$gross = (float) $item->get_total() + (float) $item->get_total_tax();
				$fee = $gross * ( self::platform_fee_percent() / 100 );
				$ledger = class_exists( 'Nfinite_Creators_Payments' ) ? Nfinite_Creators_Payments::ledger_for_order_creator( $order->get_id(), $creator_id ) : array();
				$rows[] = array(
					'order_id' => $order->get_id(),
					'date' => $order->get_date_created(),
					'status' => wc_get_order_status_name( $order->get_status() ),
					'customer' => trim( $order->get_formatted_billing_full_name() ) ?: $order->get_billing_email(),
					'item' => $item->get_name(),
					'license' => $license,
					'gross' => $gross,
					'fee' => $fee,
					'creator_net' => max( 0, $gross - $fee ),
					'currency' => $order->get_currency(),
					'transfer_status' => $ledger ? sanitize_key( $ledger['status'] ) : 'not_started',
					'transfer_id' => $ledger ? sanitize_text_field( $ledger['transfer_id'] ) : '',
					'ledger_creator_amount' => $ledger ? Nfinite_Creators_Payments::from_minor( (int) $ledger['creator_amount'], $ledger['currency'] ) : null,
					'ledger_reversed_amount' => $ledger ? Nfinite_Creators_Payments::from_minor( (int) $ledger['reversed_amount'], $ledger['currency'] ) : 0,
				);
			}
		}
		return $rows;
	}

	public static function summary( $creator_id ) {
		$summary = array( 'gross' => 0.0, 'fee' => 0.0, 'net' => 0.0, 'transferred' => 0.0, 'reversed' => 0.0, 'pending' => 0.0, 'orders' => array(), 'count' => 0, 'has_ledger' => false, 'source_totals' => array() );
		if ( class_exists( 'Nfinite_Creators_Payments' ) ) {
			$ledger = Nfinite_Creators_Payments::creator_ledger( $creator_id, 250 );
			if ( $ledger ) {
				$summary['has_ledger'] = true;
				foreach ( $ledger as $row ) {
					$currency = $row['currency'];
					$eligible = Nfinite_Creators_Payments::from_minor( (int) $row['eligible_amount'], $currency );
					$fee = Nfinite_Creators_Payments::from_minor( (int) $row['platform_fee_amount'], $currency );
					$creator = Nfinite_Creators_Payments::from_minor( (int) $row['creator_amount'], $currency );
					$reversed = Nfinite_Creators_Payments::from_minor( (int) $row['reversed_amount'], $currency );
					$summary['gross'] += $eligible;
					$summary['fee'] += $fee;
					$summary['net'] += max( 0, $creator - $reversed );
					$summary['reversed'] += $reversed;
					$source_type = class_exists( 'Nfinite_Creators_Payments' ) ? Nfinite_Creators_Payments::sanitize_source_type( $row['source_type'] ?? 'adjustment' ) : 'adjustment';
					if ( ! isset( $summary['source_totals'][ $source_type ] ) ) { $summary['source_totals'][ $source_type ] = 0.0; }
					$summary['source_totals'][ $source_type ] += max( 0, $creator - $reversed );
					if ( in_array( $row['status'], array( 'transferred', 'partially_reversed', 'reversed', 'disputed' ), true ) ) {
						$summary['transferred'] += max( 0, $creator - $reversed );
					} else {
						$summary['pending'] += max( 0, $creator - $reversed );
					}
					$summary['orders'][ $row['order_id'] ] = true;
				}
				$summary['count'] = count( $summary['orders'] );
				return $summary;
			}
		}
		$rows = self::creator_orders( $creator_id, 200 );
		foreach ( $rows as $row ) {
			if ( in_array( strtolower( $row['status'] ), array( 'cancelled', 'refunded', 'failed' ), true ) ) { continue; }
			$summary['gross'] += $row['gross'];
			$summary['fee'] += $row['fee'];
			$summary['net'] += $row['creator_net'];
			$summary['pending'] += $row['creator_net'];
			$summary['orders'][ $row['order_id'] ] = true;
		}
		$summary['count'] = count( $summary['orders'] );
		return $summary;
	}

	public static function orders_panel( $creator_id ) {
		$rows = self::creator_orders( $creator_id );
		ob_start(); ?>
		<div class="nfinite-form-section__head"><span>6</span><div><h3><?php esc_html_e( 'Orders', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Orders that contain products owned by this creator.', 'nfinite-creators' ); ?></p></div></div>
		<?php if ( ! $rows ) : ?><p class="nfinite-creators-empty"><?php esc_html_e( 'No creator orders yet.', 'nfinite-creators' ); ?></p><?php else : ?>
		<div class="nfinite-commerce-table-wrap"><table class="nfinite-commerce-table"><thead><tr><th><?php esc_html_e( 'Order', 'nfinite-creators' ); ?></th><th><?php esc_html_e( 'Item', 'nfinite-creators' ); ?></th><th><?php esc_html_e( 'Customer', 'nfinite-creators' ); ?></th><th><?php esc_html_e( 'Status', 'nfinite-creators' ); ?></th><th><?php esc_html_e( 'Gross', 'nfinite-creators' ); ?></th><th><?php esc_html_e( 'Creator earnings', 'nfinite-creators' ); ?></th><th><?php esc_html_e( 'Transfer', 'nfinite-creators' ); ?></th></tr></thead><tbody>
		<?php foreach ( $rows as $row ) : ?><tr><td>#<?php echo esc_html( $row['order_id'] ); ?><small><?php echo $row['date'] ? esc_html( $row['date']->date_i18n( get_option( 'date_format' ) ) ) : ''; ?></small></td><td><?php echo esc_html( $row['item'] ); ?><?php if ( $row['license'] ) : ?><small><?php echo esc_html( $row['license'] ); ?></small><?php endif; ?></td><td><?php echo esc_html( $row['customer'] ); ?></td><td><?php echo esc_html( $row['status'] ); ?></td><td><?php echo wp_kses_post( wc_price( $row['gross'], array( 'currency' => $row['currency'] ) ) ); ?></td><td><?php $earnings = null !== $row['ledger_creator_amount'] ? max( 0, $row['ledger_creator_amount'] - $row['ledger_reversed_amount'] ) : $row['creator_net']; echo wp_kses_post( wc_price( $earnings, array( 'currency' => $row['currency'] ) ) ); ?></td><td><strong><?php echo esc_html( self::transfer_status_label( $row['transfer_status'] ) ); ?></strong><?php if ( $row['transfer_id'] ) : ?><small><?php echo esc_html( $row['transfer_id'] ); ?></small><?php endif; ?></td></tr><?php endforeach; ?>
		</tbody></table></div><?php endif;
		return ob_get_clean();
	}

	public static function earnings_panel( $creator_id ) {
		$summary = self::summary( $creator_id );
		$fee = self::platform_fee_percent();
		ob_start(); ?>
		<div class="nfinite-form-section__head"><span>7</span><div><h3><?php esc_html_e( 'Earnings', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Unified creator earnings, accounting status, and Stripe transfer activity.', 'nfinite-creators' ); ?></p></div></div>
		<div class="nfinite-earnings-grid">
			<div><span><?php esc_html_e( 'Eligible creator sales', 'nfinite-creators' ); ?></span><strong><?php echo wp_kses_post( function_exists( 'wc_price' ) ? wc_price( $summary['gross'] ) : '$' . number_format_i18n( $summary['gross'], 2 ) ); ?></strong></div>
			<div><span><?php printf( esc_html__( 'PairOfDice fee (%s%%)', 'nfinite-creators' ), esc_html( rtrim( rtrim( number_format( $fee, 2 ), '0' ), '.' ) ) ); ?></span><strong><?php echo wp_kses_post( function_exists( 'wc_price' ) ? wc_price( $summary['fee'] ) : '$' . number_format_i18n( $summary['fee'], 2 ) ); ?></strong></div>
			<div><span><?php esc_html_e( 'Transferred to Stripe', 'nfinite-creators' ); ?></span><strong><?php echo wp_kses_post( function_exists( 'wc_price' ) ? wc_price( $summary['transferred'] ) : '$' . number_format_i18n( $summary['transferred'], 2 ) ); ?></strong></div>
			<div><span><?php esc_html_e( 'Pending / blocked', 'nfinite-creators' ); ?></span><strong><?php echo wp_kses_post( function_exists( 'wc_price' ) ? wc_price( $summary['pending'] ) : '$' . number_format_i18n( $summary['pending'], 2 ) ); ?></strong></div>
		</div>

		<?php if ( class_exists( 'Nfinite_Creators_Streaming_Earnings' ) ) :
			$streaming = Nfinite_Creators_Streaming_Earnings::creator_streaming_summary( $creator_id );
			if ( $streaming['qualified_streams'] > 0 || $streaming['amount_minor'] > 0 ) :
				$streaming_major = Nfinite_Creators_Payments::from_minor( $streaming['amount_minor'], 'USD' ); ?>
				<div class="nfinite-earnings-sources nfinite-streaming-statement">
					<h4><?php esc_html_e( 'Streaming earnings', 'nfinite-creators' ); ?></h4>
					<div class="nfinite-earnings-grid">
						<div><span><?php echo esc_html( $streaming['period'] ); ?></span><strong><?php echo wp_kses_post( function_exists( 'wc_price' ) ? wc_price( $streaming_major ) : '$' . number_format_i18n( $streaming_major, 2 ) ); ?></strong></div>
						<div><span><?php esc_html_e( 'Eligible qualified streams', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( number_format_i18n( $streaming['qualified_streams'] ) ); ?></strong></div>
					</div>
					<p class="nfinite-field-help"><?php echo 'finalized' === $streaming['status'] ? esc_html__( 'This accounting period has been finalized.', 'nfinite-creators' ) : esc_html__( 'Current-period streaming earnings are provisional and may change until PairOfDice finalizes the monthly accounting period.', 'nfinite-creators' ); ?></p>
				</div>
			<?php endif;
		endif; ?>

		<?php if ( ! empty( $summary['source_totals'] ) ) : ?>
			<div class="nfinite-earnings-sources">
				<h4><?php esc_html_e( 'Earnings by source', 'nfinite-creators' ); ?></h4>
				<div class="nfinite-earnings-grid">
				<?php foreach ( $summary['source_totals'] as $source_type => $source_amount ) : ?>
					<div><span><?php echo esc_html( self::source_type_label( $source_type ) ); ?></span><strong><?php echo wp_kses_post( function_exists( 'wc_price' ) ? wc_price( $source_amount ) : '$' . number_format_i18n( $source_amount, 2 ) ); ?></strong></div>
				<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $summary['reversed'] > 0 ) : ?><p class="nfinite-field-help"><?php printf( esc_html__( '%s has been reversed or refunded from prior creator transfers.', 'nfinite-creators' ), wp_kses_post( function_exists( 'wc_price' ) ? wc_price( $summary['reversed'] ) : '$' . number_format_i18n( $summary['reversed'], 2 ) ) ); ?></p><?php endif; ?>
		<p class="nfinite-field-help"><?php echo $summary['has_ledger'] ? esc_html__( 'Transferred means PairOfDice has moved funds into your connected Stripe balance. Stripe then handles payout to your bank according to your Stripe payout schedule.', 'nfinite-creators' ) : esc_html__( 'No payment ledger entries exist yet. New eligible paid orders will appear here once Creator Payments is enabled.', 'nfinite-creators' ); ?></p>
		<?php if ( class_exists( 'Nfinite_Creators_Financial_Admin' ) ) { echo wp_kses_post( Nfinite_Creators_Financial_Admin::creator_statements_shortcode( array( 'creator_id' => $creator_id ) ) ); } ?>
		<?php return ob_get_clean();
	}

	public static function source_type_label( $source_type ) {
		$labels = array(
			'beat_sale' => __( 'Beat sales', 'nfinite-creators' ),
			'product_sale' => __( 'Product sales', 'nfinite-creators' ),
			'service_sale' => __( 'Services', 'nfinite-creators' ),
			'music_streaming' => __( 'Music streaming', 'nfinite-creators' ),
			'video_engagement' => __( 'Video engagement', 'nfinite-creators' ),
			'membership' => __( 'Memberships', 'nfinite-creators' ),
			'event' => __( 'Events', 'nfinite-creators' ),
			'distribution' => __( 'Distribution', 'nfinite-creators' ),
			'editorial' => __( 'Editorial', 'nfinite-creators' ),
			'affiliate' => __( 'Affiliate', 'nfinite-creators' ),
			'sponsorship' => __( 'Sponsorship', 'nfinite-creators' ),
			'adjustment' => __( 'Adjustments', 'nfinite-creators' ),
		);
		return $labels[ $source_type ] ?? ucfirst( str_replace( '_', ' ', $source_type ) );
	}

	public static function transfer_status_label( $status ) {
		$labels = array(
			'not_started' => __( 'Not started', 'nfinite-creators' ),
			'pending' => __( 'Pending', 'nfinite-creators' ),
			'provisional' => __( 'Provisional', 'nfinite-creators' ),
			'finalized' => __( 'Finalized', 'nfinite-creators' ),
			'blocked' => __( 'Action needed', 'nfinite-creators' ),
			'failed' => __( 'Failed', 'nfinite-creators' ),
			'skipped' => __( 'Skipped', 'nfinite-creators' ),
			'transferred' => __( 'Transferred', 'nfinite-creators' ),
			'partially_reversed' => __( 'Partially refunded', 'nfinite-creators' ),
			'reversed' => __( 'Refunded', 'nfinite-creators' ),
			'disputed' => __( 'Disputed', 'nfinite-creators' ),
		);
		return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}

	public static function handle_exclusive_sale( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) { return; }
		$order = wc_get_order( $order_id );
		if ( ! $order ) { return; }
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$variation_id = $item->get_variation_id();
			if ( ! $variation_id || 'exclusive' !== get_post_meta( $variation_id, '_nfinite_license_key', true ) ) { continue; }
			$parent_id = $item->get_product_id();
			if ( 'beat' !== get_post_meta( $parent_id, '_nfinite_creator_product_type', true ) ) { continue; }
			if ( get_post_meta( $parent_id, '_nfinite_exclusive_order_id', true ) ) { continue; }
			update_post_meta( $parent_id, '_nfinite_exclusive_order_id', absint( $order_id ) );
			$product = wc_get_product( $parent_id );
			if ( $product && $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $child_id ) {
					$variation = wc_get_product( $child_id );
					if ( $variation ) { $variation->set_stock_status( 'outofstock' ); $variation->save(); }
				}
				$product->set_stock_status( 'outofstock' );
				$product->save();
				wc_delete_product_transients( $parent_id );
			}
		}
	}
}
