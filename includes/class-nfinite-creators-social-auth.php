<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Social authentication, account linking, and reCAPTCHA protection.
 */
class Nfinite_Creators_Social_Auth {
	const OPTION = 'nfinite_creator_auth_settings';
	const GOOGLE_ID_META = '_nfinite_social_google_id';
	const FACEBOOK_ID_META = '_nfinite_social_facebook_id';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_auth_assets' ), 25 );

		add_action( 'admin_post_nopriv_nfinite_social_start', array( __CLASS__, 'social_start' ) );
		add_action( 'admin_post_nfinite_social_start', array( __CLASS__, 'social_start' ) );
		add_action( 'admin_post_nopriv_nfinite_social_callback', array( __CLASS__, 'social_callback' ) );
		add_action( 'admin_post_nfinite_social_callback', array( __CLASS__, 'social_callback' ) );
	}

	public static function defaults() {
		return array(
			'google_enabled' => 0,
			'google_client_id' => '',
			'google_client_secret' => '',
			'facebook_enabled' => 0,
			'facebook_app_id' => '',
			'facebook_app_secret' => '',
			'recaptcha_enabled' => 0,
			'recaptcha_site_key' => '',
			'recaptcha_secret_key' => '',
			'recaptcha_score' => '0.5',
			'recaptcha_login' => 1,
			'recaptcha_register' => 1,
			'recaptcha_reset' => 1,
		);
	}

	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	private static function constant_or_setting( $constant, $setting ) {
		if ( defined( $constant ) && constant( $constant ) ) {
			return (string) constant( $constant );
		}
		$s = self::settings();
		return isset( $s[ $setting ] ) ? (string) $s[ $setting ] : '';
	}

	public static function google_client_id() { return self::constant_or_setting( 'NFINITE_GOOGLE_CLIENT_ID', 'google_client_id' ); }
	public static function google_client_secret() { return self::constant_or_setting( 'NFINITE_GOOGLE_CLIENT_SECRET', 'google_client_secret' ); }
	public static function facebook_app_id() { return self::constant_or_setting( 'NFINITE_FACEBOOK_APP_ID', 'facebook_app_id' ); }
	public static function facebook_app_secret() { return self::constant_or_setting( 'NFINITE_FACEBOOK_APP_SECRET', 'facebook_app_secret' ); }
	public static function recaptcha_site_key() { return self::constant_or_setting( 'NFINITE_RECAPTCHA_SITE_KEY', 'recaptcha_site_key' ); }
	public static function recaptcha_secret_key() { return self::constant_or_setting( 'NFINITE_RECAPTCHA_SECRET_KEY', 'recaptcha_secret_key' ); }

	public static function google_enabled() {
		$s = self::settings();
		return ! empty( $s['google_enabled'] ) && self::google_client_id() && self::google_client_secret();
	}

	public static function facebook_enabled() {
		$s = self::settings();
		return ! empty( $s['facebook_enabled'] ) && self::facebook_app_id() && self::facebook_app_secret();
	}

	public static function recaptcha_enabled_for( $action ) {
		$s = self::settings();
		if ( empty( $s['recaptcha_enabled'] ) || ! self::recaptcha_site_key() || ! self::recaptcha_secret_key() ) { return false; }
		$key = 'recaptcha_' . sanitize_key( $action );
		return ! empty( $s[ $key ] );
	}

	public static function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=nfinite_creator',
			__( 'Authentication & Security', 'nfinite-creators' ),
			__( 'Authentication', 'nfinite-creators' ),
			'manage_options',
			'nfinite-creator-auth',
			array( __CLASS__, 'settings_page' )
		);
	}

	public static function register_settings() {
		register_setting( 'nfinite_creator_auth', self::OPTION, array( __CLASS__, 'sanitize_settings' ) );
	}

	public static function sanitize_settings( $input ) {
		$out = self::defaults();
		$input = is_array( $input ) ? $input : array();
		foreach ( array( 'google_enabled','facebook_enabled','recaptcha_enabled','recaptcha_login','recaptcha_register','recaptcha_reset' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}
		foreach ( array( 'google_client_id','google_client_secret','facebook_app_id','facebook_app_secret','recaptcha_site_key','recaptcha_secret_key' ) as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( wp_unslash( $input[ $key ] ) ) : '';
		}
		$score = isset( $input['recaptcha_score'] ) ? (float) $input['recaptcha_score'] : 0.5;
		$out['recaptcha_score'] = (string) min( 1, max( 0, $score ) );
		return $out;
	}

	public static function callback_url( $provider = '' ) {
		// Use one canonical OAuth callback for every provider. The provider is
		// securely recovered from the short-lived state transient on return.
		// Keeping provider out of redirect_uri prevents OAuth providers from
		// normalizing/dropping the second query parameter and breaking the flow.
		return add_query_arg(
			array( 'action' => 'nfinite_social_callback' ),
			admin_url( 'admin-post.php' )
		);
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$s = self::settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Nfinite Authentication & Security', 'nfinite-creators' ); ?></h1>
			<p><?php esc_html_e( 'Configure social login and bot protection for PairOfDice creator accounts.', 'nfinite-creators' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'nfinite_creator_auth' ); ?>
				<h2><?php esc_html_e( 'Google', 'nfinite-creators' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Enable Google', 'nfinite-creators' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[google_enabled]" value="1" <?php checked( ! empty( $s['google_enabled'] ) ); ?>> <?php esc_html_e( 'Show Continue with Google', 'nfinite-creators' ); ?></label></td></tr>
					<tr><th><label for="nfinite-google-id"><?php esc_html_e( 'Client ID', 'nfinite-creators' ); ?></label></th><td><input id="nfinite-google-id" class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[google_client_id]" value="<?php echo esc_attr( $s['google_client_id'] ); ?>"></td></tr>
					<tr><th><label for="nfinite-google-secret"><?php esc_html_e( 'Client Secret', 'nfinite-creators' ); ?></label></th><td><input id="nfinite-google-secret" class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr( self::OPTION ); ?>[google_client_secret]" value="<?php echo esc_attr( $s['google_client_secret'] ); ?>"><p class="description"><?php esc_html_e( 'Authorized redirect URI:', 'nfinite-creators' ); ?> <code><?php echo esc_html( self::callback_url( 'google' ) ); ?></code></p></td></tr>
				</table>

				<h2><?php esc_html_e( 'Facebook', 'nfinite-creators' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Enable Facebook', 'nfinite-creators' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[facebook_enabled]" value="1" <?php checked( ! empty( $s['facebook_enabled'] ) ); ?>> <?php esc_html_e( 'Show Continue with Facebook', 'nfinite-creators' ); ?></label></td></tr>
					<tr><th><label for="nfinite-facebook-id"><?php esc_html_e( 'App ID', 'nfinite-creators' ); ?></label></th><td><input id="nfinite-facebook-id" class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[facebook_app_id]" value="<?php echo esc_attr( $s['facebook_app_id'] ); ?>"></td></tr>
					<tr><th><label for="nfinite-facebook-secret"><?php esc_html_e( 'App Secret', 'nfinite-creators' ); ?></label></th><td><input id="nfinite-facebook-secret" class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr( self::OPTION ); ?>[facebook_app_secret]" value="<?php echo esc_attr( $s['facebook_app_secret'] ); ?>"><p class="description"><?php esc_html_e( 'Valid OAuth redirect URI:', 'nfinite-creators' ); ?> <code><?php echo esc_html( self::callback_url( 'facebook' ) ); ?></code></p></td></tr>
				</table>

				<h2><?php esc_html_e( 'Google reCAPTCHA v3', 'nfinite-creators' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Enable reCAPTCHA', 'nfinite-creators' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[recaptcha_enabled]" value="1" <?php checked( ! empty( $s['recaptcha_enabled'] ) ); ?>> <?php esc_html_e( 'Protect Nfinite authentication forms', 'nfinite-creators' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Site Key', 'nfinite-creators' ); ?></th><td><input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[recaptcha_site_key]" value="<?php echo esc_attr( $s['recaptcha_site_key'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Secret Key', 'nfinite-creators' ); ?></th><td><input class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr( self::OPTION ); ?>[recaptcha_secret_key]" value="<?php echo esc_attr( $s['recaptcha_secret_key'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Minimum Score', 'nfinite-creators' ); ?></th><td><input type="number" min="0" max="1" step="0.1" name="<?php echo esc_attr( self::OPTION ); ?>[recaptcha_score]" value="<?php echo esc_attr( $s['recaptcha_score'] ); ?>"><p class="description"><?php esc_html_e( '0.5 is a reasonable starting point. Higher is stricter.', 'nfinite-creators' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Protect', 'nfinite-creators' ); ?></th><td>
						<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[recaptcha_login]" value="1" <?php checked( ! empty( $s['recaptcha_login'] ) ); ?>> <?php esc_html_e( 'Login', 'nfinite-creators' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[recaptcha_register]" value="1" <?php checked( ! empty( $s['recaptcha_register'] ) ); ?>> <?php esc_html_e( 'Registration', 'nfinite-creators' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[recaptcha_reset]" value="1" <?php checked( ! empty( $s['recaptcha_reset'] ) ); ?>> <?php esc_html_e( 'Password reset', 'nfinite-creators' ); ?></label>
					</td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<hr>
			<p><strong><?php esc_html_e( 'Optional wp-config.php constants', 'nfinite-creators' ); ?></strong></p>
			<code>NFINITE_GOOGLE_CLIENT_ID</code>, <code>NFINITE_GOOGLE_CLIENT_SECRET</code>, <code>NFINITE_FACEBOOK_APP_ID</code>, <code>NFINITE_FACEBOOK_APP_SECRET</code>, <code>NFINITE_RECAPTCHA_SITE_KEY</code>, <code>NFINITE_RECAPTCHA_SECRET_KEY</code>
		</div>
		<?php
	}

	public static function social_buttons_html( $mode = 'login' ) {
		$providers = array();
		if ( self::google_enabled() ) { $providers['google'] = __( 'Continue with Google', 'nfinite-creators' ); }
		if ( self::facebook_enabled() ) { $providers['facebook'] = __( 'Continue with Facebook', 'nfinite-creators' ); }
		if ( ! $providers ) { return ''; }
		$out = '<div class="nfinite-social-auth">';
		foreach ( $providers as $provider => $label ) {
			$url = wp_nonce_url(
				add_query_arg(
					array( 'action' => 'nfinite_social_start', 'provider' => $provider, 'mode' => sanitize_key( $mode ) ),
					admin_url( 'admin-post.php' )
				),
				'nfinite_social_start_' . $provider,
				'nfinite_social_nonce'
			);
			$icon = 'google' === $provider ? self::google_icon() : self::facebook_icon();
			$out .= '<a class="nfinite-social-btn is-' . esc_attr( $provider ) . '" href="' . esc_url( $url ) . '">' . $icon . '<span>' . esc_html( $label ) . '</span></a>';
		}
		$out .= '<div class="nfinite-auth-divider"><span>' . esc_html__( 'or', 'nfinite-creators' ) . '</span></div></div>';
		return $out;
	}

	private static function google_icon() {
		return '<svg aria-hidden="true" viewBox="0 0 24 24"><path fill="#4285F4" d="M21.6 12.23c0-.72-.06-1.26-.2-1.82H12v3.6h5.52c-.11.9-.71 2.25-2.04 3.16l-.02.12 2.97 2.3.21.02c1.95-1.8 2.96-4.45 2.96-7.38z"/><path fill="#34A853" d="M12 22c2.7 0 4.96-.89 6.61-2.4l-3.15-2.44c-.84.57-1.98.97-3.46.97-2.65 0-4.9-1.79-5.7-4.26l-.12.01-3.09 2.39-.04.11C4.69 19.7 8.08 22 12 22z"/><path fill="#FBBC05" d="M6.3 13.87A6.1 6.1 0 0 1 5.96 12c0-.65.12-1.28.33-1.87l-.01-.13-3.13-2.43-.1.05A10 10 0 0 0 2 12c0 1.57.37 3.05 1.05 4.38l3.25-2.51z"/><path fill="#EA4335" d="M12 5.87c1.88 0 3.15.81 3.87 1.48l2.8-2.73C16.95 3.02 14.7 2 12 2 8.08 2 4.69 4.3 3.05 7.62l3.24 2.51C7.1 7.66 9.35 5.87 12 5.87z"/></svg>';
	}

	private static function facebook_icon() {
		return '<svg aria-hidden="true" viewBox="0 0 24 24"><path fill="currentColor" d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.413c0-3.025 1.79-4.697 4.533-4.697 1.313 0 2.686.236 2.686.236v2.973h-1.513c-1.49 0-1.956.932-1.956 1.887v2.261h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>';
	}

	public static function recaptcha_field( $action ) {
		if ( ! self::recaptcha_enabled_for( $action ) ) { return ''; }
		return '<input type="hidden" class="nfinite-recaptcha-token" name="nfinite_recaptcha_token" value="" data-action="' . esc_attr( $action ) . '">';
	}

	public static function enqueue_auth_assets() {
		$site_key = self::recaptcha_site_key();
		$s = self::settings();
		if ( empty( $s['recaptcha_enabled'] ) || ! $site_key ) { return; }
		wp_enqueue_script( 'google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $site_key ), array(), null, true );
		wp_enqueue_script( 'nfinite-auth-security', NFINITE_CREATORS_URL . 'public/js/nfinite-auth-security.js', array( 'google-recaptcha-v3' ), NFINITE_CREATORS_VERSION, true );
		wp_localize_script( 'nfinite-auth-security', 'NfiniteAuthSecurity', array( 'siteKey' => $site_key ) );
	}

	public static function verify_recaptcha( $action ) {
		if ( ! self::recaptcha_enabled_for( $action ) ) { return true; }
		$token = isset( $_POST['nfinite_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['nfinite_recaptcha_token'] ) ) : '';
		if ( ! $token ) { return false; }
		$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
			'timeout' => 10,
			'body' => array( 'secret' => self::recaptcha_secret_key(), 'response' => $token, 'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ),
		) );
		if ( is_wp_error( $response ) ) { return false; }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['success'] ) ) { return false; }
		if ( ! empty( $data['action'] ) && $action !== $data['action'] ) { return false; }
		$s = self::settings();
		$min = isset( $s['recaptcha_score'] ) ? (float) $s['recaptcha_score'] : 0.5;
		return isset( $data['score'] ) && (float) $data['score'] >= $min;
	}

	public static function social_start() {
		$provider = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';
		$mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'login';
		$nonce = isset( $_GET['nfinite_social_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nfinite_social_nonce'] ) ) : '';
		if ( ! in_array( $provider, array( 'google', 'facebook' ), true ) || ! wp_verify_nonce( $nonce, 'nfinite_social_start_' . $provider ) ) {
			self::fail( 'social_failed', $mode );
		}
		if ( ( 'google' === $provider && ! self::google_enabled() ) || ( 'facebook' === $provider && ! self::facebook_enabled() ) ) {
			self::fail( 'social_unavailable', $mode );
		}
		$state = wp_generate_password( 40, false, false );
		set_transient( 'nfinite_oauth_' . hash( 'sha256', $state ), array( 'provider' => $provider, 'mode' => $mode, 'time' => time() ), 10 * MINUTE_IN_SECONDS );
		$redirect_uri = self::callback_url( $provider );
		if ( 'google' === $provider ) {
			$url = add_query_arg( array(
				'client_id' => self::google_client_id(), 'redirect_uri' => $redirect_uri, 'response_type' => 'code',
				'scope' => 'openid email profile', 'state' => $state, 'prompt' => 'select_account', 'access_type' => 'online',
			), 'https://accounts.google.com/o/oauth2/v2/auth' );
		} else {
			$url = add_query_arg( array(
				'client_id' => self::facebook_app_id(), 'redirect_uri' => $redirect_uri, 'response_type' => 'code',
				'scope' => 'email,public_profile', 'state' => $state,
			), 'https://www.facebook.com/dialog/oauth' );
		}
		wp_redirect( esc_url_raw( $url ) ); exit;
	}

	public static function social_callback() {
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$key = $state ? 'nfinite_oauth_' . hash( 'sha256', $state ) : '';
		$flow = $key ? get_transient( $key ) : false;
		if ( $key ) { delete_transient( $key ); }
		$mode = is_array( $flow ) && ! empty( $flow['mode'] ) ? sanitize_key( $flow['mode'] ) : 'login';
		$provider = is_array( $flow ) && ! empty( $flow['provider'] ) ? sanitize_key( $flow['provider'] ) : '';
		if ( ! $state || ! $code || ! is_array( $flow ) || ! in_array( $provider, array( 'google', 'facebook' ), true ) ) {
			self::fail( 'social_failed', $mode );
		}
		$profile = 'google' === $provider ? self::google_profile( $code ) : self::facebook_profile( $code );
		if ( is_wp_error( $profile ) ) { self::fail( 'social_failed', $mode ); }
		$user = self::resolve_user( $provider, $profile );
		if ( is_wp_error( $user ) ) { self::fail( 'social_email_missing', $mode ); }
		self::login_user( $user );
		$creator_id = absint( get_user_meta( $user->ID, '_nfinite_creator_profile_id', true ) );
		$destination = $creator_id ? Nfinite_Creators_Roles::dashboard_url() : home_url( '/community/' );
		wp_safe_redirect( add_query_arg( 'nfinite_auth', 'social_success', $destination ) ); exit;
	}

	private static function google_profile( $code ) {
		$token = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout' => 15, 'body' => array(
			'code' => $code, 'client_id' => self::google_client_id(), 'client_secret' => self::google_client_secret(),
			'redirect_uri' => self::callback_url( 'google' ), 'grant_type' => 'authorization_code',
		) ) );
		if ( is_wp_error( $token ) ) { return $token; }
		$data = json_decode( wp_remote_retrieve_body( $token ), true );
		if ( empty( $data['access_token'] ) ) { return new WP_Error( 'google_token' ); }
		$user = wp_remote_get( 'https://openidconnect.googleapis.com/v1/userinfo', array( 'timeout' => 15, 'headers' => array( 'Authorization' => 'Bearer ' . $data['access_token'] ) ) );
		if ( is_wp_error( $user ) ) { return $user; }
		$info = json_decode( wp_remote_retrieve_body( $user ), true );
		if ( empty( $info['sub'] ) || empty( $info['email'] ) || empty( $info['email_verified'] ) ) { return new WP_Error( 'google_profile' ); }
		return array( 'id' => sanitize_text_field( $info['sub'] ), 'email' => sanitize_email( $info['email'] ), 'name' => sanitize_text_field( isset( $info['name'] ) ? $info['name'] : $info['email'] ), 'avatar' => esc_url_raw( isset( $info['picture'] ) ? $info['picture'] : '' ) );
	}

	private static function facebook_profile( $code ) {
		$token_url = add_query_arg( array( 'client_id' => self::facebook_app_id(), 'client_secret' => self::facebook_app_secret(), 'redirect_uri' => self::callback_url( 'facebook' ), 'code' => $code ), 'https://graph.facebook.com/oauth/access_token' );
		$token = wp_remote_get( $token_url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $token ) ) { return $token; }
		$data = json_decode( wp_remote_retrieve_body( $token ), true );
		if ( empty( $data['access_token'] ) ) { return new WP_Error( 'facebook_token' ); }
		$me_url = add_query_arg( array( 'fields' => 'id,name,email,picture.type(large)', 'access_token' => $data['access_token'] ), 'https://graph.facebook.com/me' );
		$user = wp_remote_get( $me_url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $user ) ) { return $user; }
		$info = json_decode( wp_remote_retrieve_body( $user ), true );
		if ( empty( $info['id'] ) || empty( $info['email'] ) ) { return new WP_Error( 'facebook_profile' ); }
		$avatar = isset( $info['picture']['data']['url'] ) ? $info['picture']['data']['url'] : '';
		return array( 'id' => sanitize_text_field( $info['id'] ), 'email' => sanitize_email( $info['email'] ), 'name' => sanitize_text_field( isset( $info['name'] ) ? $info['name'] : $info['email'] ), 'avatar' => esc_url_raw( $avatar ) );
	}

	private static function resolve_user( $provider, $profile ) {
		$meta_key = 'google' === $provider ? self::GOOGLE_ID_META : self::FACEBOOK_ID_META;
		$linked = get_users( array( 'number' => 1, 'meta_key' => $meta_key, 'meta_value' => $profile['id'], 'fields' => 'all' ) );
		if ( $linked ) { return $linked[0]; }
		if ( empty( $profile['email'] ) || ! is_email( $profile['email'] ) ) { return new WP_Error( 'social_email' ); }
		$user = get_user_by( 'email', $profile['email'] );
		if ( ! $user ) {
			$user_id = self::create_social_creator( $profile );
			if ( is_wp_error( $user_id ) ) { return $user_id; }
			$user = get_user_by( 'id', $user_id );
		}
		update_user_meta( $user->ID, $meta_key, $profile['id'] );
		update_user_meta( $user->ID, '_nfinite_social_' . $provider . '_connected', time() );
		if ( ! empty( $profile['avatar'] ) ) { update_user_meta( $user->ID, '_nfinite_social_avatar', $profile['avatar'] ); }
		return $user;
	}

	private static function create_social_creator( $profile ) {
		$email = $profile['email'];
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( ! $base ) { $base = 'creator'; }
		$username = $base; $i = 1;
		while ( username_exists( $username ) ) { $username = $base . $i; $i++; }
		$user_id = wp_insert_user( array( 'user_login' => $username, 'user_email' => $email, 'user_pass' => wp_generate_password( 32, true, true ), 'display_name' => $profile['name'], 'role' => Nfinite_Creators_Roles::ROLE ) );
		if ( is_wp_error( $user_id ) ) { return $user_id; }
		$creator_id = wp_insert_post( array( 'post_type' => 'nfinite_creator', 'post_status' => 'pending', 'post_title' => $profile['name'], 'post_author' => $user_id, 'post_content' => '' ), true );
		if ( is_wp_error( $creator_id ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $user_id ); return $creator_id;
		}
		update_post_meta( $creator_id, '_nfinite_creator_email', $email );
		update_user_meta( $user_id, '_nfinite_creator_profile_id', (int) $creator_id );
		return $user_id;
	}

	private static function login_user( $user ) {
		clean_user_cache( $user->ID );
		wp_set_current_user( $user->ID, $user->user_login );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );
	}

	private static function fail( $status, $mode ) {
		$url = 'register' === $mode ? Nfinite_Creators_Auth::page_url( 'register' ) : Nfinite_Creators_Auth::page_url( 'login' );
		wp_safe_redirect( add_query_arg( 'nfinite_auth', sanitize_key( $status ), $url ) ); exit;
	}
}
