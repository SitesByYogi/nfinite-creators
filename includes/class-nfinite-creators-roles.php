<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Creator account roles/capabilities and frontend-only access rules.
 */
class Nfinite_Creators_Roles {
	const ROLE = 'nfinite_creator';

	public static function init() {
		// Keep capabilities in sync for upgrades where activation does not run.
		add_action( 'init', array( __CLASS__, 'register_role' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'redirect_creator_admin' ), 1 );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'maybe_hide_admin_bar' ) );
	}

	public static function capabilities() {
		return array(
			'read'                            => true,
			'upload_files'                    => true,
			'nfinite_manage_creator_profile'  => true,
			'nfinite_manage_creator_media'    => true,
			'nfinite_manage_creator_products' => true,
			'nfinite_view_creator_orders'     => true,
			'nfinite_view_creator_earnings'   => true,
			'nfinite_connect_creator_payments'=> true,
		);
	}

	public static function register_role() {
		$role = get_role( self::ROLE );
		if ( ! $role ) {
			add_role( self::ROLE, __( 'Creator', 'nfinite-creators' ), self::capabilities() );
			$role = get_role( self::ROLE );
		}

		// Existing installs may have an older version of the role.
		if ( $role ) {
			foreach ( self::capabilities() as $cap => $grant ) {
				if ( $grant ) {
					$role->add_cap( $cap );
				} else {
					$role->remove_cap( $cap );
				}
			}
		}

		// Administrators should always be able to use creator-management capabilities.
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( array_keys( self::capabilities() ) as $cap ) {
				if ( 0 === strpos( $cap, 'nfinite_' ) ) {
					$administrator->add_cap( $cap );
				}
			}
		}
	}

	public static function is_creator_user( $user = null ) {
		if ( null === $user ) {
			$user = wp_get_current_user();
		} elseif ( is_numeric( $user ) ) {
			$user = get_userdata( absint( $user ) );
		}
		return $user instanceof WP_User && in_array( self::ROLE, (array) $user->roles, true );
	}

	public static function dashboard_url() {
		if ( class_exists( 'Nfinite_Creators_Auth' ) ) {
			return apply_filters( 'nfinite_creator_account_dashboard_url', Nfinite_Creators_Auth::page_url( 'dashboard' ) );
		}
		return apply_filters( 'nfinite_creator_account_dashboard_url', home_url( '/my-creator-profile/' ) );
	}

	public static function redirect_creator_admin() {
		if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) || ! self::is_creator_user() ) {
			return;
		}

		// Frontend forms, media uploads, AJAX and cron still rely on WordPress admin endpoints.
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		global $pagenow;
		$allowed = array( 'admin-post.php', 'async-upload.php' );
		if ( in_array( (string) $pagenow, $allowed, true ) ) {
			return;
		}

		wp_safe_redirect( self::dashboard_url() );
		exit;
	}

	public static function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( $user instanceof WP_User && self::is_creator_user( $user ) && ! user_can( $user, 'manage_options' ) ) {
			return self::dashboard_url();
		}
		return $redirect_to;
	}

	public static function maybe_hide_admin_bar( $show ) {
		if ( is_user_logged_in() && self::is_creator_user() && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return $show;
	}

	/**
	 * Ensure a creator owner has the Creator role without stripping any existing roles.
	 * Privileged WordPress users are left unchanged.
	 */
	public static function ensure_creator_role( $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user instanceof WP_User ) {
			return false;
		}
		if ( user_can( $user, 'manage_options' ) || user_can( $user, 'edit_others_posts' ) ) {
			return true;
		}
		if ( ! in_array( self::ROLE, (array) $user->roles, true ) ) {
			$user->add_role( self::ROLE );
		}
		return true;
	}
}
