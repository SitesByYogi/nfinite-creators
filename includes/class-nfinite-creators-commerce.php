<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Creator Commerce foundation.
 *
 * V1 scope: create Stripe Connect recipient accounts, generate Stripe-hosted
 * onboarding links, sync recipient transfer capability status, and send creators
 * to the Stripe Express Dashboard. No payment/transfer logic is included yet.
 */
class Nfinite_Creators_Commerce {
	const OPTION_KEY = 'nfinite_creators_commerce_settings';
	const META_ACCOUNT_ID = '_nfinite_stripe_account_id';
	const META_STATUS = '_nfinite_stripe_account_status';
	const META_LAST_SYNC = '_nfinite_stripe_last_sync';
	const API_VERSION = '2026-07-29.preview';
	const STATUS_SCHEMA_VERSION = 2;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_nfinite_commerce_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_nfinite_stripe_connect', array( __CLASS__, 'handle_connect' ) );
		add_action( 'admin_post_nfinite_stripe_manage', array( __CLASS__, 'handle_manage' ) );
		add_action( 'admin_post_nfinite_stripe_return', array( __CLASS__, 'handle_return' ) );
		add_action( 'admin_post_nopriv_nfinite_stripe_return', array( __CLASS__, 'handle_return' ) );
		add_action( 'admin_post_nfinite_stripe_refresh', array( __CLASS__, 'handle_refresh' ) );
		add_action( 'admin_post_nopriv_nfinite_stripe_refresh', array( __CLASS__, 'handle_refresh' ) );
	}

	public static function settings() {
		$defaults = array(
			'enabled'    => '0',
			'secret_key' => '',
			'country'    => 'US',
			'platform_fee' => '10',
			'automatic_transfers' => '0',
			'transfer_timing' => 'completed',
			'webhook_secret' => '',
		);
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	public static function enabled() {
		$settings = self::settings();
		return '1' === (string) $settings['enabled'] && '' !== self::secret_key();
	}

	public static function secret_key() {
		if ( defined( 'NFINITE_STRIPE_SECRET_KEY' ) && NFINITE_STRIPE_SECRET_KEY ) {
			return trim( (string) NFINITE_STRIPE_SECRET_KEY );
		}
		$settings = self::settings();
		return trim( (string) $settings['secret_key'] );
	}

	public static function mode_label() {
		$key = self::secret_key();
		if ( 0 === strpos( $key, 'sk_live_' ) ) {
			return __( 'Live', 'nfinite-creators' );
		}
		if ( 0 === strpos( $key, 'sk_test_' ) ) {
			return __( 'Test', 'nfinite-creators' );
		}
		return __( 'Unknown', 'nfinite-creators' );
	}

	public static function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=nfinite_creator',
			__( 'Creator Commerce', 'nfinite-creators' ),
			__( 'Creator Commerce', 'nfinite-creators' ),
			'manage_options',
			'nfinite-creator-commerce',
			array( __CLASS__, 'settings_page' )
		);
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$settings = self::settings();
		$constant = defined( 'NFINITE_STRIPE_SECRET_KEY' ) && NFINITE_STRIPE_SECRET_KEY;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Nfinite Creator Commerce', 'nfinite-creators' ); ?></h1>
			<p><?php esc_html_e( 'Stripe Connect onboarding and Creator Payments V1 for PairOfDice-style creator marketplaces. WooCommerce remains the checkout/order engine while Nfinite can transfer creator earnings to connected Stripe accounts.', 'nfinite-creators' ); ?></p>
			<?php if ( isset( $_GET['nfinite_commerce_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Creator Commerce settings saved.', 'nfinite-creators' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nfinite_commerce_save_settings">
				<?php wp_nonce_field( 'nfinite_commerce_save_settings', 'nfinite_commerce_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Stripe Connect', 'nfinite-creators' ); ?></th>
						<td><label><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'], '1' ); ?>> <?php esc_html_e( 'Show Stripe Connect controls in the creator dashboard', 'nfinite-creators' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="nfinite-stripe-key"><?php esc_html_e( 'Stripe secret key', 'nfinite-creators' ); ?></label></th>
						<td>
							<?php if ( $constant ) : ?>
								<input class="regular-text" type="password" value="••••••••••••••••" disabled>
								<p class="description"><?php esc_html_e( 'Loaded from NFINITE_STRIPE_SECRET_KEY in wp-config.php. This is the recommended production setup.', 'nfinite-creators' ); ?></p>
							<?php else : ?>
								<input id="nfinite-stripe-key" class="regular-text" type="password" name="secret_key" autocomplete="new-password" value="" placeholder="sk_test_… or sk_live_…">
								<p class="description"><?php esc_html_e( 'Leave blank to keep the currently saved key. For production, define NFINITE_STRIPE_SECRET_KEY in wp-config.php instead of storing the key in WordPress.', 'nfinite-creators' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nfinite-stripe-country"><?php esc_html_e( 'Default creator country', 'nfinite-creators' ); ?></label></th>
						<td><input id="nfinite-stripe-country" class="small-text" type="text" maxlength="2" name="country" value="<?php echo esc_attr( strtoupper( $settings['country'] ) ); ?>"><p class="description"><?php esc_html_e( 'Two-letter country code used when creating a connected account. Default: US.', 'nfinite-creators' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="nfinite-platform-fee"><?php esc_html_e( 'Marketplace fee (%)', 'nfinite-creators' ); ?></label></th>
						<td><input id="nfinite-platform-fee" class="small-text" type="number" min="0" max="100" step="0.01" name="platform_fee" value="<?php echo esc_attr( $settings['platform_fee'] ); ?>"><p class="description"><?php esc_html_e( 'Retained by PairOfDice from eligible creator-owned line-item revenue. Creator Payments transfers the remaining creator share.', 'nfinite-creators' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Automatic creator transfers', 'nfinite-creators' ); ?></th>
						<td>
							<label><input type="checkbox" name="automatic_transfers" value="1" <?php checked( $settings['automatic_transfers'], '1' ); ?>> <?php esc_html_e( 'Automatically transfer creator earnings after eligible WooCommerce orders are paid', 'nfinite-creators' ); ?></label>
							<p class="description"><?php esc_html_e( 'Keep this disabled until you have completed end-to-end Stripe test-mode transactions. Nfinite uses separate charges and transfers and validates the Stripe source charge before moving money.', 'nfinite-creators' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nfinite-transfer-timing"><?php esc_html_e( 'Transfer timing', 'nfinite-creators' ); ?></label></th>
						<td>
							<select id="nfinite-transfer-timing" name="transfer_timing">
								<option value="completed" <?php selected( $settings['transfer_timing'], 'completed' ); ?>><?php esc_html_e( 'Order completed (recommended)', 'nfinite-creators' ); ?></option>
								<option value="processing" <?php selected( $settings['transfer_timing'], 'processing' ); ?>><?php esc_html_e( 'Order processing', 'nfinite-creators' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Completed provides a safer hold point. Processing can be useful when digital orders do not auto-complete.', 'nfinite-creators' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nfinite-webhook-secret"><?php esc_html_e( 'Stripe webhook signing secret', 'nfinite-creators' ); ?></label></th>
						<td>
							<?php if ( defined( 'NFINITE_STRIPE_WEBHOOK_SECRET' ) && NFINITE_STRIPE_WEBHOOK_SECRET ) : ?>
								<input class="regular-text" type="password" value="••••••••••••••••" disabled>
								<p class="description"><?php esc_html_e( 'Loaded from NFINITE_STRIPE_WEBHOOK_SECRET in wp-config.php.', 'nfinite-creators' ); ?></p>
							<?php else : ?>
								<input id="nfinite-webhook-secret" class="regular-text" type="password" name="webhook_secret" autocomplete="new-password" value="" placeholder="whsec_…">
								<p class="description"><?php esc_html_e( 'Leave blank to keep the saved value. For production, use NFINITE_STRIPE_WEBHOOK_SECRET in wp-config.php.', 'nfinite-creators' ); ?></p>
							<?php endif; ?>
							<p class="description"><strong><?php esc_html_e( 'Webhook URL:', 'nfinite-creators' ); ?></strong> <code><?php echo esc_html( rest_url( 'nfinite-creators/v1/stripe/webhook' ) ); ?></code></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Current connection', 'nfinite-creators' ); ?></h2>
			<p><strong><?php esc_html_e( 'Mode:', 'nfinite-creators' ); ?></strong> <?php echo esc_html( self::mode_label() ); ?></p>
			<p><strong><?php esc_html_e( 'Accounts API:', 'nfinite-creators' ); ?></strong> v2 / recipient / Express</p>
			<p><strong><?php esc_html_e( 'Stripe API version:', 'nfinite-creators' ); ?></strong> <?php echo esc_html( self::API_VERSION ); ?></p>
		</div>
		<?php
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'nfinite-creators' ) );
		}
		check_admin_referer( 'nfinite_commerce_save_settings', 'nfinite_commerce_nonce' );
		$settings = self::settings();
		$settings['enabled'] = isset( $_POST['enabled'] ) ? '1' : '0';
		$country = isset( $_POST['country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['country'] ) ) ) : 'US';
		$settings['country'] = preg_match( '/^[A-Z]{2}$/', $country ) ? $country : 'US';
		$fee = isset( $_POST['platform_fee'] ) ? (float) wp_unslash( $_POST['platform_fee'] ) : 10;
		$settings['platform_fee'] = (string) max( 0, min( 100, $fee ) );
		$settings['automatic_transfers'] = isset( $_POST['automatic_transfers'] ) ? '1' : '0';
		$timing = isset( $_POST['transfer_timing'] ) ? sanitize_key( wp_unslash( $_POST['transfer_timing'] ) ) : 'completed';
		$settings['transfer_timing'] = in_array( $timing, array( 'completed', 'processing' ), true ) ? $timing : 'completed';
		if ( ! defined( 'NFINITE_STRIPE_WEBHOOK_SECRET' ) && isset( $_POST['webhook_secret'] ) ) {
			$webhook_secret = trim( sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) ) );
			if ( '' !== $webhook_secret ) { $settings['webhook_secret'] = $webhook_secret; }
		}
		if ( ! defined( 'NFINITE_STRIPE_SECRET_KEY' ) && isset( $_POST['secret_key'] ) ) {
			$key = trim( sanitize_text_field( wp_unslash( $_POST['secret_key'] ) ) );
			if ( '' !== $key ) {
				$settings['secret_key'] = $key;
			}
		}
		update_option( self::OPTION_KEY, $settings, false );
		wp_safe_redirect( add_query_arg( 'nfinite_commerce_saved', '1', admin_url( 'edit.php?post_type=nfinite_creator&page=nfinite-creator-commerce' ) ) );
		exit;
	}

	public static function creator_account_id( $creator_id, $user_id = 0 ) {
		$account_id = $creator_id ? get_post_meta( $creator_id, self::META_ACCOUNT_ID, true ) : '';
		if ( ! $account_id && $user_id ) {
			$account_id = get_user_meta( $user_id, self::META_ACCOUNT_ID, true );
		}
		return sanitize_text_field( (string) $account_id );
	}

	private static function save_account_id( $creator_id, $user_id, $account_id ) {
		if ( $creator_id ) { update_post_meta( $creator_id, self::META_ACCOUNT_ID, $account_id ); }
		if ( $user_id ) { update_user_meta( $user_id, self::META_ACCOUNT_ID, $account_id ); }
	}

	private static function save_status( $creator_id, $user_id, $status ) {
		$status = is_array( $status ) ? $status : array();
		if ( $creator_id ) {
			update_post_meta( $creator_id, self::META_STATUS, $status );
			update_post_meta( $creator_id, self::META_LAST_SYNC, time() );
		}
		if ( $user_id ) {
			update_user_meta( $user_id, self::META_STATUS, $status );
			update_user_meta( $user_id, self::META_LAST_SYNC, time() );
		}
	}

	public static function cached_status( $creator_id, $user_id = 0 ) {
		$status = $creator_id ? get_post_meta( $creator_id, self::META_STATUS, true ) : array();
		if ( ! is_array( $status ) && $user_id ) {
			$status = get_user_meta( $user_id, self::META_STATUS, true );
		}
		return is_array( $status ) ? $status : array();
	}

	public static function dashboard_card( $creator_id, $user_id, $embedded = false ) {
		if ( ! $creator_id || ! $user_id ) { return ''; }

		$configured = self::enabled();
		$account_id = self::creator_account_id( $creator_id, $user_id );
		$status = self::cached_status( $creator_id, $user_id );

		$status_schema = isset( $status['schema_version'] ) ? absint( $status['schema_version'] ) : 0;
		if ( $configured && $account_id && ( self::STATUS_SCHEMA_VERSION !== $status_schema || empty( $status['synced_at'] ) || ( time() - absint( $status['synced_at'] ) ) > 300 ) ) {
			$remote = self::sync_account_status( $creator_id, $user_id, $account_id );
			if ( ! is_wp_error( $remote ) ) { $status = $remote; }
		}

		$transfer_status = isset( $status['transfer_status'] ) ? sanitize_key( $status['transfer_status'] ) : '';
		$payout_status   = isset( $status['payout_status'] ) ? sanitize_key( $status['payout_status'] ) : '';
		$requirements_due = ! empty( $status['requirements_due'] );
		$transfers_ready = 'active' === $transfer_status;
		$payouts_ready   = 'active' === $payout_status;
		$is_active       = $transfers_ready && $payouts_ready && ! $requirements_due;
		$connect_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'     => 'nfinite_stripe_connect',
					'creator_id' => $creator_id,
				),
				admin_url( 'admin-post.php' )
			),
			'nfinite_stripe_connect_' . $creator_id,
			'nfinite_stripe_nonce'
		);
		$manage_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'     => 'nfinite_stripe_manage',
					'creator_id' => $creator_id,
				),
				admin_url( 'admin-post.php' )
			),
			'nfinite_stripe_manage_' . $creator_id,
			'nfinite_stripe_nonce'
		);

		ob_start();
		?>
		<div class="nfinite-commerce-card<?php echo $embedded ? ' nfinite-commerce-card--embedded' : ''; ?>">
			<div class="nfinite-commerce-card__head">
				<div><span class="nfinite-eyebrow"><?php esc_html_e( 'Creator Commerce', 'nfinite-creators' ); ?></span><h3><?php esc_html_e( 'Payments & Earnings', 'nfinite-creators' ); ?></h3></div>
				<?php if ( $account_id ) : ?><span class="nfinite-commerce-badge <?php echo $is_active ? 'is-active' : 'is-pending'; ?>"><?php echo $is_active ? esc_html__( 'Ready', 'nfinite-creators' ) : esc_html__( 'Action needed', 'nfinite-creators' ); ?></span><?php endif; ?>
			</div>

			<p class="nfinite-commerce-card__intro"><?php esc_html_e( 'Connect your Stripe account to receive payouts from beat sales, products, memberships, and other PairOfDice creator commerce.', 'nfinite-creators' ); ?></p>

			<?php if ( isset( $_GET['nfinite_stripe'] ) ) : ?>
				<?php $notice = sanitize_key( wp_unslash( $_GET['nfinite_stripe'] ) ); ?>
				<?php if ( 'returned' === $notice ) : ?><div class="nfinite-creators-notice nfinite-creators-notice--success"><?php esc_html_e( 'Stripe account status refreshed.', 'nfinite-creators' ); ?></div><?php endif; ?>
				<?php if ( 'error' === $notice ) : ?><div class="nfinite-creators-notice"><?php esc_html_e( 'Stripe could not complete that request. Please try again or contact support.', 'nfinite-creators' ); ?></div><?php endif; ?>
			<?php endif; ?>

			<?php if ( ! $configured ) : ?>
				<div class="nfinite-commerce-setup-note">
					<strong><?php esc_html_e( 'Payouts are not available yet.', 'nfinite-creators' ); ?></strong>
					<span><?php esc_html_e( 'Stripe Connect must be enabled by the site administrator before creators can connect a payout account.', 'nfinite-creators' ); ?></span>
				</div>
			<?php elseif ( ! $account_id ) : ?>
				<div class="nfinite-commerce-status-grid nfinite-commerce-status-grid--preconnect">
					<div><span><?php esc_html_e( 'Stripe Account', 'nfinite-creators' ); ?></span><strong><?php esc_html_e( 'Not connected', 'nfinite-creators' ); ?></strong></div>
					<div><span><?php esc_html_e( 'Payouts', 'nfinite-creators' ); ?></span><strong><?php esc_html_e( 'Not enabled', 'nfinite-creators' ); ?></strong></div>
				</div>
				<div class="nfinite-commerce-actions">
					<a class="nfinite-btn nfinite-btn-primary" href="<?php echo esc_url( $connect_url ); ?>"><?php esc_html_e( 'Connect Stripe', 'nfinite-creators' ); ?></a>
				</div>
			<?php else : ?>
				<div class="nfinite-commerce-status-grid">
					<div><span><?php esc_html_e( 'Stripe Account', 'nfinite-creators' ); ?></span><strong><?php esc_html_e( 'Connected', 'nfinite-creators' ); ?></strong></div>
					<div><span><?php esc_html_e( 'Transfers', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( $transfers_ready ? __( 'Ready', 'nfinite-creators' ) : __( 'Pending', 'nfinite-creators' ) ); ?></strong></div>
					<div><span><?php esc_html_e( 'Payouts', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( $payouts_ready ? __( 'Ready', 'nfinite-creators' ) : __( 'Pending', 'nfinite-creators' ) ); ?></strong></div>
					<div><span><?php esc_html_e( 'Requirements', 'nfinite-creators' ); ?></span><strong><?php echo $requirements_due ? esc_html__( 'Action needed', 'nfinite-creators' ) : esc_html__( 'Complete', 'nfinite-creators' ); ?></strong></div>
					<div><span><?php esc_html_e( 'Mode', 'nfinite-creators' ); ?></span><strong><?php echo ! empty( $status['livemode'] ) ? esc_html__( 'Live', 'nfinite-creators' ) : esc_html__( 'Test', 'nfinite-creators' ); ?></strong></div>
				</div>
				<div class="nfinite-commerce-actions">
					<?php if ( ! $is_active ) : ?>
						<a class="nfinite-btn nfinite-btn-primary" href="<?php echo esc_url( $connect_url ); ?>"><?php esc_html_e( 'Continue Stripe Setup', 'nfinite-creators' ); ?></a>
					<?php endif; ?>
					<a class="nfinite-btn nfinite-btn-secondary" href="<?php echo esc_url( $manage_url ); ?>"><?php esc_html_e( 'Manage Payout Account', 'nfinite-creators' ); ?></a>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function handle_connect() {
		if ( ! is_user_logged_in() ) { auth_redirect(); }
		$creator_id = isset( $_REQUEST['creator_id'] ) ? absint( $_REQUEST['creator_id'] ) : 0;
		$acting_user_id = get_current_user_id();
		self::assert_creator_owner( $creator_id, $acting_user_id );
		$user_id = (int) get_post_field( 'post_author', $creator_id );
		check_admin_referer( 'nfinite_stripe_connect_' . $creator_id, 'nfinite_stripe_nonce' );
		if ( ! self::enabled() ) { self::redirect_dashboard( 'error' ); }

		$account_id = self::creator_account_id( $creator_id, $user_id );
		if ( ! $account_id ) {
			$account = self::create_recipient_account( $creator_id, $user_id );
			if ( is_wp_error( $account ) || empty( $account['id'] ) ) { self::redirect_dashboard( 'error' ); }
			$account_id = $account['id'];
			self::save_account_id( $creator_id, $user_id, $account_id );
			self::store_status_from_account( $creator_id, $user_id, $account );
		}

		$token = wp_generate_password( 32, false, false );
		set_transient( 'nfinite_stripe_flow_' . $token, array( 'user_id' => $user_id, 'creator_id' => $creator_id, 'account_id' => $account_id, 'return_to' => self::safe_local_url( wp_get_referer() ) ), 30 * MINUTE_IN_SECONDS );
		$link = self::create_onboarding_link( $account_id, $token );
		if ( is_wp_error( $link ) || empty( $link['url'] ) ) { self::redirect_dashboard( 'error' ); }
		wp_redirect( esc_url_raw( $link['url'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	public static function handle_refresh() {
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$flow = $token ? get_transient( 'nfinite_stripe_flow_' . $token ) : false;
		if ( ! is_array( $flow ) || empty( $flow['account_id'] ) ) { self::redirect_dashboard( 'error' ); }
		if ( is_user_logged_in() && get_current_user_id() !== absint( $flow['user_id'] ) ) { self::redirect_dashboard( 'error' ); }
		$link = self::create_onboarding_link( $flow['account_id'], $token );
		if ( is_wp_error( $link ) || empty( $link['url'] ) ) { self::redirect_dashboard( 'error' ); }
		wp_redirect( esc_url_raw( $link['url'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	public static function handle_return() {
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$flow = $token ? get_transient( 'nfinite_stripe_flow_' . $token ) : false;
		if ( ! is_array( $flow ) || empty( $flow['account_id'] ) ) { self::redirect_dashboard( 'error' ); }
		self::sync_account_status( absint( $flow['creator_id'] ), absint( $flow['user_id'] ), sanitize_text_field( $flow['account_id'] ) );
		$return_to = ! empty( $flow['return_to'] ) ? self::safe_local_url( $flow['return_to'] ) : '';
		delete_transient( 'nfinite_stripe_flow_' . $token );
		self::redirect_dashboard( 'returned', $return_to );
	}

	public static function handle_manage() {
		if ( ! is_user_logged_in() ) { auth_redirect(); }
		$creator_id = isset( $_REQUEST['creator_id'] ) ? absint( $_REQUEST['creator_id'] ) : 0;
		$acting_user_id = get_current_user_id();
		self::assert_creator_owner( $creator_id, $acting_user_id );
		$user_id = (int) get_post_field( 'post_author', $creator_id );
		check_admin_referer( 'nfinite_stripe_manage_' . $creator_id, 'nfinite_stripe_nonce' );
		$account_id = self::creator_account_id( $creator_id, $user_id );
		if ( ! $account_id ) { self::redirect_dashboard( 'error' ); }
		$link = self::api_request( 'POST', '/v1/accounts/' . rawurlencode( $account_id ) . '/login_links', null, false );
		if ( is_wp_error( $link ) || empty( $link['url'] ) ) { self::redirect_dashboard( 'error' ); }
		wp_redirect( esc_url_raw( $link['url'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	private static function create_recipient_account( $creator_id, $user_id ) {
		$user = get_userdata( $user_id );
		$email = get_post_meta( $creator_id, '_nfinite_creator_email', true );
		if ( ! is_email( $email ) && $user ) { $email = $user->user_email; }
		$display_name = get_the_title( $creator_id );
		if ( ! $display_name && $user ) { $display_name = $user->display_name; }
		$settings = self::settings();
		$payload = array(
			'contact_email' => sanitize_email( $email ),
			'display_name' => sanitize_text_field( $display_name ),
			'defaults' => array(
				'responsibilities' => array(
					'fees_collector' => 'application',
					'losses_collector' => 'application',
				),
			),
			'dashboard' => 'express',
			'identity' => array( 'country' => strtolower( $settings['country'] ) ),
			'configuration' => array(
				'recipient' => array(
					'capabilities' => array(
						'stripe_balance' => array(
							'stripe_transfers' => array( 'requested' => true ),
						),
					),
				),
			),
			'metadata' => array(
				'nfinite_user_id' => (string) $user_id,
				'nfinite_creator_id' => (string) $creator_id,
			),
			'include' => array( 'configuration.recipient', 'identity', 'requirements' ),
		);
		return self::api_request( 'POST', '/v2/core/accounts', $payload, true );
	}

	private static function create_onboarding_link( $account_id, $token ) {
		$return_url = add_query_arg( array( 'action' => 'nfinite_stripe_return', 'token' => $token ), admin_url( 'admin-post.php' ) );
		$refresh_url = add_query_arg( array( 'action' => 'nfinite_stripe_refresh', 'token' => $token ), admin_url( 'admin-post.php' ) );
		$payload = array(
			'account' => $account_id,
			'use_case' => array(
				'type' => 'account_onboarding',
				'account_onboarding' => array(
					'configurations' => array( 'recipient' ),
					'return_url' => $return_url,
					'refresh_url' => $refresh_url,
					'collection_options' => array( 'fields' => 'eventually_due' ),
				),
			),
		);
		return self::api_request( 'POST', '/v2/core/account_links', $payload, true );
	}

	public static function sync_account_status( $creator_id, $user_id, $account_id = '' ) {
		if ( ! $account_id ) { $account_id = self::creator_account_id( $creator_id, $user_id ); }
		if ( ! $account_id ) { return new WP_Error( 'nfinite_no_stripe_account', __( 'No Stripe account is connected.', 'nfinite-creators' ) ); }

		// Accounts v2 expects repeated include parameters (include=value), not PHP-style include[]=value.
		$path = '/v2/core/accounts/' . rawurlencode( $account_id ) . '?include=configuration.recipient&include=requirements';
		$account = self::api_request( 'GET', $path, null, true );
		if ( is_wp_error( $account ) ) { return $account; }

		// Keep Stripe's bookkeeping aligned if creator ownership changes after the account was created.
		self::sync_account_metadata( $account_id, $creator_id, $user_id, $account );

		return self::store_status_from_account( $creator_id, $user_id, $account );
	}

	private static function sync_account_metadata( $account_id, $creator_id, $user_id, $account ) {
		$metadata = isset( $account['metadata'] ) && is_array( $account['metadata'] ) ? $account['metadata'] : array();
		$current_creator = isset( $metadata['nfinite_creator_id'] ) ? (string) $metadata['nfinite_creator_id'] : '';
		$current_user    = isset( $metadata['nfinite_user_id'] ) ? (string) $metadata['nfinite_user_id'] : '';
		if ( (string) $creator_id === $current_creator && (string) $user_id === $current_user ) { return; }

		// Metadata repair is best-effort and must never block a legitimate payout-status refresh.
		self::api_request(
			'POST',
			'/v2/core/accounts/' . rawurlencode( $account_id ),
			array(
				'metadata' => array(
					'nfinite_creator_id' => (string) $creator_id,
					'nfinite_user_id'    => (string) $user_id,
				),
			),
			true
		);
	}

	private static function store_status_from_account( $creator_id, $user_id, $account ) {
		$recipient = isset( $account['configuration']['recipient'] ) && is_array( $account['configuration']['recipient'] ) ? $account['configuration']['recipient'] : array();
		$balance_caps = isset( $recipient['capabilities']['stripe_balance'] ) && is_array( $recipient['capabilities']['stripe_balance'] ) ? $recipient['capabilities']['stripe_balance'] : array();
		$transfer_cap = isset( $balance_caps['stripe_transfers'] ) && is_array( $balance_caps['stripe_transfers'] ) ? $balance_caps['stripe_transfers'] : array();
		$payout_cap   = isset( $balance_caps['payouts'] ) && is_array( $balance_caps['payouts'] ) ? $balance_caps['payouts'] : array();
		$requirements = isset( $account['requirements'] ) && is_array( $account['requirements'] ) ? $account['requirements'] : array();
		$entries      = isset( $requirements['entries'] ) && is_array( $requirements['entries'] ) ? $requirements['entries'] : array();

		// For recipient accounts, Stripe exposes actionable requirements in requirements.entries.
		// Empty entries means there is nothing currently blocking recipient transfer/payout readiness.
		$due = ! empty( $entries );

		$status = array(
			'schema_version'    => self::STATUS_SCHEMA_VERSION,
			'transfer_status'   => isset( $transfer_cap['status'] ) ? sanitize_key( $transfer_cap['status'] ) : '',
			'payout_status'     => isset( $payout_cap['status'] ) ? sanitize_key( $payout_cap['status'] ) : '',
			'requirements_due'  => $due,
			'requirements_count'=> count( $entries ),
			'livemode'          => ! empty( $account['livemode'] ),
			'synced_at'         => time(),
		);
		self::save_status( $creator_id, $user_id, $status );
		return $status;
	}

	private static function api_request( $method, $path, $payload = null, $v2 = true ) {
		$key = self::secret_key();
		if ( '' === $key ) { return new WP_Error( 'nfinite_stripe_missing_key', __( 'Stripe secret key is not configured.', 'nfinite-creators' ) ); }
		$headers = array( 'Authorization' => 'Bearer ' . $key );
		$args = array( 'method' => strtoupper( $method ), 'headers' => $headers, 'timeout' => 30 );
		if ( $v2 ) {
			$args['headers']['Stripe-Version'] = self::API_VERSION;
			if ( null !== $payload ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body'] = wp_json_encode( $payload );
			}
		} elseif ( null !== $payload ) {
			$args['body'] = $payload;
		}
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

	private static function assert_creator_owner( $creator_id, $user_id ) {
		$is_owner = $creator_id && (int) get_post_field( 'post_author', $creator_id ) === (int) $user_id;
		if ( ! $creator_id || 'nfinite_creator' !== get_post_type( $creator_id ) || ( ! $is_owner && ! current_user_can( 'manage_options' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to manage commerce for this creator.', 'nfinite-creators' ) );
		}
	}

	private static function safe_local_url( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( ! $url ) { return ''; }
		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );
		return $home_host && $url_host && strtolower( $home_host ) === strtolower( $url_host ) ? $url : '';
	}

	private static function dashboard_url( $preferred = '' ) {
		$url = self::safe_local_url( $preferred );
		if ( ! $url ) { $url = self::safe_local_url( wp_get_referer() ); }
		if ( ! $url || false !== strpos( $url, 'admin-post.php' ) ) {
			$url = home_url( '/creator-dashboard/' );
		}
		return apply_filters( 'nfinite_creator_dashboard_url', $url );
	}

	private static function redirect_dashboard( $status, $preferred = '' ) {
		wp_safe_redirect( add_query_arg( 'nfinite_stripe', sanitize_key( $status ), self::dashboard_url( $preferred ) ) );
		exit;
	}
}
