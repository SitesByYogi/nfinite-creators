<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Frontend Creator authentication, registration, and account routing.
 */
class Nfinite_Creators_Auth {
	const PAGES_OPTION = 'nfinite_creator_account_pages';
	const PAGES_VERSION_OPTION = 'nfinite_creator_account_pages_version';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_ensure_pages' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy_register_page' ), 1 );
		add_shortcode( 'nfinite_creator_login', array( __CLASS__, 'login_shortcode' ) );
		add_shortcode( 'nfinite_creator_register', array( __CLASS__, 'register_shortcode' ) );
		add_shortcode( 'nfinite_creator_account_link', array( __CLASS__, 'account_link_shortcode' ) );

		add_action( 'admin_post_nopriv_nfinite_creator_login', array( __CLASS__, 'handle_login' ) );
		add_action( 'admin_post_nopriv_nfinite_creator_register', array( __CLASS__, 'handle_register' ) );
		add_action( 'admin_post_nopriv_nfinite_creator_lost_password', array( __CLASS__, 'handle_lost_password' ) );
		add_action( 'admin_post_nfinite_creator_logout', array( __CLASS__, 'handle_logout' ) );
	}

	public static function page_definitions() {
		return array(
			'login' => array(
				'title'   => __( 'Creator Login', 'nfinite-creators' ),
				'slug'    => 'creator-login',
				'content' => '[nfinite_creator_login]',
			),
			'register' => array(
				'title'   => __( 'Join PairOfDice', 'nfinite-creators' ),
				'slug'    => 'join-pairofdice',
				'content' => '[nfinite_creator_register]',
			),
			'dashboard' => array(
				'title'   => __( 'My Creator Profile', 'nfinite-creators' ),
				'slug'    => 'my-creator-profile',
				'content' => '[nfinite_creator_dashboard]',
			),
		);
	}

	public static function ensure_pages() {
		$stored = get_option( self::PAGES_OPTION, array() );
		if ( ! is_array( $stored ) ) { $stored = array(); }

		foreach ( self::page_definitions() as $key => $definition ) {
			$page_id = ! empty( $stored[ $key ] ) ? absint( $stored[ $key ] ) : 0;
			$page = $page_id ? get_post( $page_id ) : null;

			if ( ! $page || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
				$page = get_page_by_path( $definition['slug'] );
			}

			if ( $page instanceof WP_Post ) {
				$stored[ $key ] = (int) $page->ID;
				continue;
			}

			$new_id = wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => $definition['title'],
					'post_name'      => $definition['slug'],
					'post_content'   => $definition['content'],
					'comment_status' => 'closed',
				),
				true
			);
			if ( ! is_wp_error( $new_id ) ) {
				$stored[ $key ] = (int) $new_id;
			}
		}

		update_option( self::PAGES_OPTION, $stored, false );
		update_option( self::PAGES_VERSION_OPTION, NFINITE_CREATORS_VERSION, false );
		return $stored;
	}

	public static function maybe_ensure_pages() {
		if ( NFINITE_CREATORS_VERSION !== get_option( self::PAGES_VERSION_OPTION ) ) {
			self::ensure_pages();
		}
	}

	public static function creator_intelligence_signup_url() {
		return apply_filters( 'nfinite_creator_intelligence_signup_url', 'https://ci.pairofdice.media/?signup=1' );
	}

	public static function redirect_legacy_register_page() {
		if ( is_admin() || wp_doing_ajax() || ! is_page() ) { return; }
		$stored = get_option( self::PAGES_OPTION, array() );
		$register_id = is_array( $stored ) && ! empty( $stored['register'] ) ? absint( $stored['register'] ) : 0;
		if ( ( $register_id && is_page( $register_id ) ) || is_page( 'join-pairofdice' ) ) {
			wp_redirect( self::creator_intelligence_signup_url(), 302, 'Nfinite Creators' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}
	}

	public static function page_url( $key ) {
		if ( 'register' === $key ) { return self::creator_intelligence_signup_url(); }
		$stored = get_option( self::PAGES_OPTION, array() );
		if ( is_array( $stored ) && ! empty( $stored[ $key ] ) ) {
			$url = get_permalink( absint( $stored[ $key ] ) );
			if ( $url ) { return $url; }
		}
		$defs = self::page_definitions();
		return isset( $defs[ $key ] ) ? home_url( '/' . $defs[ $key ]['slug'] . '/' ) : home_url( '/' );
	}

	private static function status_notice() {
		$status = isset( $_GET['nfinite_auth'] ) ? sanitize_key( wp_unslash( $_GET['nfinite_auth'] ) ) : '';
		$messages = array(
			'login_failed'       => array( 'error', __( 'We could not log you in with those details. Please try again.', 'nfinite-creators' ) ),
			'registered'         => array( 'success', __( 'Your creator account is ready. Welcome to Creator Studio.', 'nfinite-creators' ) ),
			'email_exists'       => array( 'error', __( 'An account already exists for that email address. Try logging in instead.', 'nfinite-creators' ) ),
			'password_mismatch'  => array( 'error', __( 'The passwords did not match.', 'nfinite-creators' ) ),
			'weak_password'      => array( 'error', __( 'Please choose a password with at least 10 characters.', 'nfinite-creators' ) ),
			'missing_fields'     => array( 'error', __( 'Please complete all required fields.', 'nfinite-creators' ) ),
			'invalid_email'      => array( 'error', __( 'Please enter a valid email address.', 'nfinite-creators' ) ),
			'registration_error' => array( 'error', __( 'We could not create your creator account. Please try again.', 'nfinite-creators' ) ),
			'reset_sent'         => array( 'success', __( 'If that account exists, a password reset email has been sent.', 'nfinite-creators' ) ),
			'logged_out'         => array( 'success', __( 'You have been logged out.', 'nfinite-creators' ) ),
			'social_failed'      => array( 'error', __( 'We could not complete social sign-in. Please try again.', 'nfinite-creators' ) ),
			'social_unavailable' => array( 'error', __( 'That social sign-in provider is not configured yet.', 'nfinite-creators' ) ),
			'social_email_missing' => array( 'error', __( 'Your social account did not provide a verified email address.', 'nfinite-creators' ) ),
			'social_success'     => array( 'success', __( 'You are signed in.', 'nfinite-creators' ) ),
			'recaptcha_failed'   => array( 'error', __( 'We could not verify this request. Please try again.', 'nfinite-creators' ) ),
		);
		if ( ! isset( $messages[ $status ] ) ) { return ''; }
		return '<div class="nfinite-auth-notice is-' . esc_attr( $messages[ $status ][0] ) . '">' . esc_html( $messages[ $status ][1] ) . '</div>';
	}

	public static function login_shortcode() {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$dashboard = Nfinite_Creators_Roles::dashboard_url();
			$logout = wp_nonce_url( admin_url( 'admin-post.php?action=nfinite_creator_logout' ), 'nfinite_creator_logout' );
			return '<section class="nfinite-auth-card"><span class="nfinite-eyebrow">' . esc_html__( 'PairOfDice Account', 'nfinite-creators' ) . '</span><h2>' . sprintf( esc_html__( 'Welcome, %s', 'nfinite-creators' ), esc_html( $user->display_name ) ) . '</h2><p>' . esc_html__( 'You are already signed in.', 'nfinite-creators' ) . '</p><div class="nfinite-auth-actions"><a class="nfinite-btn" href="' . esc_url( $dashboard ) . '">' . esc_html__( 'Open Creator Studio', 'nfinite-creators' ) . '</a><a class="nfinite-auth-secondary" href="' . esc_url( $logout ) . '">' . esc_html__( 'Log out', 'nfinite-creators' ) . '</a></div></section>';
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		ob_start();
		?>
		<section class="nfinite-auth-shell">
			<div class="nfinite-auth-card">
				<span class="nfinite-eyebrow"><?php esc_html_e( 'Creator Account', 'nfinite-creators' ); ?></span>
				<?php echo self::status_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( 'lostpassword' === $action ) : ?>
					<h2><?php esc_html_e( 'Reset your password', 'nfinite-creators' ); ?></h2>
					<p><?php esc_html_e( 'Enter the email address for your PairOfDice account and we will send a reset link.', 'nfinite-creators' ); ?></p>
					<form class="nfinite-auth-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="nfinite_creator_lost_password">
						<?php wp_nonce_field( 'nfinite_creator_lost_password', 'nfinite_auth_nonce' ); ?>
						<?php echo Nfinite_Creators_Social_Auth::recaptcha_field( 'reset' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<label><span><?php esc_html_e( 'Email', 'nfinite-creators' ); ?></span><input type="email" name="user_login" required autocomplete="email"></label>
						<button class="nfinite-btn" type="submit"><?php esc_html_e( 'Send Reset Link', 'nfinite-creators' ); ?></button>
					</form>
					<p class="nfinite-auth-meta"><a href="<?php echo esc_url( self::page_url( 'login' ) ); ?>"><?php esc_html_e( 'Back to login', 'nfinite-creators' ); ?></a></p>
				<?php else : ?>
					<h2><?php esc_html_e( 'Welcome back', 'nfinite-creators' ); ?></h2>
					<p><?php esc_html_e( 'Log in to manage your creator profile, media, products, orders, and earnings.', 'nfinite-creators' ); ?></p>
					<?php echo Nfinite_Creators_Social_Auth::social_buttons_html( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<form class="nfinite-auth-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="nfinite_creator_login">
						<?php wp_nonce_field( 'nfinite_creator_login', 'nfinite_auth_nonce' ); ?>
						<?php echo Nfinite_Creators_Social_Auth::recaptcha_field( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<label><span><?php esc_html_e( 'Email or Username', 'nfinite-creators' ); ?></span><input type="text" name="log" required autocomplete="username"></label>
						<label><span><?php esc_html_e( 'Password', 'nfinite-creators' ); ?></span><input type="password" name="pwd" required autocomplete="current-password"></label>
						<label class="nfinite-auth-check"><input type="checkbox" name="rememberme" value="1"><span><?php esc_html_e( 'Remember me', 'nfinite-creators' ); ?></span></label>
						<button class="nfinite-btn" type="submit"><?php esc_html_e( 'Log In', 'nfinite-creators' ); ?></button>
					</form>
					<div class="nfinite-auth-links"><a href="<?php echo esc_url( add_query_arg( 'action', 'lostpassword', self::page_url( 'login' ) ) ); ?>"><?php esc_html_e( 'Forgot password?', 'nfinite-creators' ); ?></a><a href="<?php echo esc_url( self::page_url( 'register' ) ); ?>"><?php esc_html_e( 'Create a creator account', 'nfinite-creators' ); ?></a></div>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function register_shortcode() {
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$creator_id = absint( get_user_meta( $user_id, '_nfinite_creator_profile_id', true ) );
			if ( ! $creator_id ) {
				$creator = get_posts( array(
					'post_type'      => 'nfinite_creator',
					'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
					'author'         => $user_id,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				) );
				$creator_id = $creator ? absint( $creator[0] ) : 0;
			}

			if ( $creator_id ) {
				return '<section class="nfinite-auth-card"><h2>' . esc_html__( 'Your creator account is already set up.', 'nfinite-creators' ) . '</h2><p>' . esc_html__( 'Head to Creator Studio to manage your profile, content, products, and more.', 'nfinite-creators' ) . '</p><a class="nfinite-btn" href="' . esc_url( Nfinite_Creators_Roles::dashboard_url() ) . '">' . esc_html__( 'Open Creator Studio', 'nfinite-creators' ) . '</a></section>';
			}

			return '<section class="nfinite-auth-card"><span class="nfinite-eyebrow">' . esc_html__( 'Join PairOfDice', 'nfinite-creators' ) . '</span><h2>' . esc_html__( 'Create your creator profile', 'nfinite-creators' ) . '</h2><p>' . esc_html__( 'Your account is already signed in. Continue to Creator Studio to build the public profile connected to it.', 'nfinite-creators' ) . '</p><a class="nfinite-btn" href="' . esc_url( Nfinite_Creators_Roles::dashboard_url() ) . '">' . esc_html__( 'Create Your Profile', 'nfinite-creators' ) . '</a></section>';
		}
		ob_start();
		?>
		<section class="nfinite-auth-shell">
			<div class="nfinite-auth-card">
				<span class="nfinite-eyebrow"><?php esc_html_e( 'Join PairOfDice', 'nfinite-creators' ); ?></span>
				<?php echo self::status_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h2><?php esc_html_e( 'Create your creator account', 'nfinite-creators' ); ?></h2>
				<p><?php esc_html_e( 'Start your profile now. New creator profiles are submitted for review before becoming public.', 'nfinite-creators' ); ?></p>
				<?php echo Nfinite_Creators_Social_Auth::social_buttons_html( 'register' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<form class="nfinite-auth-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nfinite_creator_register">
					<?php wp_nonce_field( 'nfinite_creator_register', 'nfinite_auth_nonce' ); ?>
					<?php echo Nfinite_Creators_Social_Auth::recaptcha_field( 'register' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<label><span><?php esc_html_e( 'Creator / Display Name', 'nfinite-creators' ); ?></span><input type="text" name="display_name" required autocomplete="name" placeholder="Artist, brand, or creator name"></label>
					<label><span><?php esc_html_e( 'Email', 'nfinite-creators' ); ?></span><input type="email" name="email" required autocomplete="email"></label>
					<label><span><?php esc_html_e( 'Password', 'nfinite-creators' ); ?></span><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
					<label><span><?php esc_html_e( 'Confirm Password', 'nfinite-creators' ); ?></span><input type="password" name="password_confirm" required minlength="10" autocomplete="new-password"></label>
					<label class="nfinite-auth-check"><input type="checkbox" name="terms" value="1" required><span><?php esc_html_e( 'I agree to create a PairOfDice creator account and follow the platform terms and content policies.', 'nfinite-creators' ); ?></span></label>
					<input type="text" name="company_website" value="" class="nfinite-auth-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
					<button class="nfinite-btn" type="submit"><?php esc_html_e( 'Create Creator Account', 'nfinite-creators' ); ?></button>
				</form>
				<p class="nfinite-auth-meta"><?php esc_html_e( 'Already have an account?', 'nfinite-creators' ); ?> <a href="<?php echo esc_url( self::page_url( 'login' ) ); ?>"><?php esc_html_e( 'Log in', 'nfinite-creators' ); ?></a></p>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function account_link_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'class' => '', 'login_label' => __( 'Log In', 'nfinite-creators' ), 'account_label' => __( 'Creator Studio', 'nfinite-creators' ) ), $atts, 'nfinite_creator_account_link' );
		if ( is_user_logged_in() ) {
			$url = Nfinite_Creators_Roles::dashboard_url();
			$label = $atts['account_label'];
		} else {
			$url = self::page_url( 'login' );
			$label = $atts['login_label'];
		}
		return '<a class="' . esc_attr( $atts['class'] ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	public static function handle_login() {
		if ( ! isset( $_POST['nfinite_auth_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_auth_nonce'] ) ), 'nfinite_creator_login' ) ) {
			wp_safe_redirect( add_query_arg( 'nfinite_auth', 'login_failed', self::page_url( 'login' ) ) ); exit;
		}
		if ( ! Nfinite_Creators_Social_Auth::verify_recaptcha( 'login' ) ) {
			wp_safe_redirect( add_query_arg( 'nfinite_auth', 'recaptcha_failed', self::page_url( 'login' ) ) ); exit;
		}
		$credentials = array(
			'user_login'    => isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '',
			'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '',
			'remember'      => ! empty( $_POST['rememberme'] ),
		);
		$user = wp_signon( $credentials, is_ssl() );
		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( add_query_arg( 'nfinite_auth', 'login_failed', self::page_url( 'login' ) ) ); exit;
		}
		$destination = Nfinite_Creators_Roles::is_creator_user( $user ) && ! user_can( $user, 'manage_options' ) ? Nfinite_Creators_Roles::dashboard_url() : home_url( '/' );
		wp_safe_redirect( $destination ); exit;
	}

	public static function handle_register() {
		// Creator Intelligence is the only supported creator-account signup surface.
		wp_redirect( self::creator_intelligence_signup_url(), 302, 'Nfinite Creators' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;

		$register_url = self::page_url( 'register' );
		if ( ! isset( $_POST['nfinite_auth_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_auth_nonce'] ) ), 'nfinite_creator_register' ) ) {
			wp_safe_redirect( add_query_arg( 'nfinite_auth', 'registration_error', $register_url ) ); exit;
		}
		if ( ! empty( $_POST['company_website'] ) ) { wp_safe_redirect( home_url( '/' ) ); exit; }
		if ( ! Nfinite_Creators_Social_Auth::verify_recaptcha( 'register' ) ) { wp_safe_redirect( add_query_arg( 'nfinite_auth', 'recaptcha_failed', $register_url ) ); exit; }

		$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$confirm = isset( $_POST['password_confirm'] ) ? (string) wp_unslash( $_POST['password_confirm'] ) : '';
		if ( ! $display_name || ! $email || ! $password || empty( $_POST['terms'] ) ) { wp_safe_redirect( add_query_arg( 'nfinite_auth', 'missing_fields', $register_url ) ); exit; }
		if ( ! is_email( $email ) ) { wp_safe_redirect( add_query_arg( 'nfinite_auth', 'invalid_email', $register_url ) ); exit; }
		if ( email_exists( $email ) ) { wp_safe_redirect( add_query_arg( 'nfinite_auth', 'email_exists', $register_url ) ); exit; }
		if ( $password !== $confirm ) { wp_safe_redirect( add_query_arg( 'nfinite_auth', 'password_mismatch', $register_url ) ); exit; }
		if ( strlen( $password ) < 10 ) { wp_safe_redirect( add_query_arg( 'nfinite_auth', 'weak_password', $register_url ) ); exit; }

		$base_username = sanitize_user( current( explode( '@', $email ) ), true );
		if ( ! $base_username ) { $base_username = 'creator'; }
		$username = $base_username;
		$suffix = 1;
		while ( username_exists( $username ) ) { $username = $base_username . $suffix; $suffix++; }

		$user_id = wp_insert_user( array(
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $display_name,
			'role'         => Nfinite_Creators_Roles::ROLE,
		) );
		if ( is_wp_error( $user_id ) ) { wp_safe_redirect( add_query_arg( 'nfinite_auth', 'registration_error', $register_url ) ); exit; }

		$creator_id = wp_insert_post( array(
			'post_type'    => 'nfinite_creator',
			'post_status'  => 'pending',
			'post_title'   => $display_name,
			'post_author'  => $user_id,
			'post_content' => '',
		), true );
		if ( is_wp_error( $creator_id ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			wp_safe_redirect( add_query_arg( 'nfinite_auth', 'registration_error', $register_url ) ); exit;
		}
		update_post_meta( $creator_id, '_nfinite_creator_email', $email );
		update_user_meta( $user_id, '_nfinite_creator_profile_id', (int) $creator_id );

		// Registration is also the creator's first login. Establish the session
		// immediately so there is never an unnecessary login step between Join
		// PairOfDice and Creator Studio.
		clean_user_cache( $user_id );
		$user = get_userdata( $user_id );
		wp_set_current_user( $user_id, $username );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		do_action( 'wp_login', $username, $user );

		// Always land a successful signup inside the new creator's Studio.
		// The query flag also gives the dashboard a reliable welcome notice.
		$destination = add_query_arg(
			array(
				'nfinite_auth' => 'registered',
				'creator'      => (int) $creator_id,
			),
			Nfinite_Creators_Roles::dashboard_url()
		);
		nocache_headers();
		wp_safe_redirect( $destination, 302, 'Nfinite Creators' ); exit;
	}

	public static function handle_lost_password() {
		if ( ! isset( $_POST['nfinite_auth_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_auth_nonce'] ) ), 'nfinite_creator_lost_password' ) ) {
			wp_safe_redirect( add_query_arg( 'nfinite_auth', 'reset_sent', self::page_url( 'login' ) ) ); exit;
		}
		if ( ! Nfinite_Creators_Social_Auth::verify_recaptcha( 'reset' ) ) { wp_safe_redirect( add_query_arg( 'nfinite_auth', 'recaptcha_failed', self::page_url( 'login' ) ) ); exit; }
		$login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		if ( $login ) { retrieve_password( $login ); }
		wp_safe_redirect( add_query_arg( 'nfinite_auth', 'reset_sent', self::page_url( 'login' ) ) ); exit;
	}

	public static function handle_logout() {
		check_admin_referer( 'nfinite_creator_logout' );
		wp_logout();
		wp_safe_redirect( add_query_arg( 'nfinite_auth', 'logged_out', self::page_url( 'login' ) ) ); exit;
	}
}
