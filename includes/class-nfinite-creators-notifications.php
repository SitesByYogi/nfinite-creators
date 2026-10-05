<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Creator + Fan Notifications V1.
 *
 * Stores first-party in-app notifications and connects them to the Follow/Save,
 * publishing, memberships and brand-opportunity systems without relying on email
 * or a third-party push provider.
 */
class Nfinite_Creators_Notifications {
	const DB_VERSION = '1';
	const DB_OPTION = 'nfinite_notifications_db_version';
	const PAGE_OPTION = 'nfinite_notifications_page_id';
	const PREF_META = '_nfinite_notification_preferences';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 6 );
		add_action( 'transition_post_status', array( __CLASS__, 'content_published' ), 30, 3 );
		add_action( 'save_post', array( __CLASS__, 'maybe_notify_after_meta_save' ), 99, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'brand_application_status_changed' ), 20, 4 );

		add_action( 'wp_ajax_nfinite_notification_read', array( __CLASS__, 'ajax_read' ) );
		add_action( 'wp_ajax_nfinite_notifications_read_all', array( __CLASS__, 'ajax_read_all' ) );
		add_action( 'admin_post_nfinite_notification_preferences', array( __CLASS__, 'save_preferences' ) );

		add_shortcode( 'nfinite_notifications', array( __CLASS__, 'notifications_shortcode' ) );
		add_shortcode( 'nfinite_notification_bell', array( __CLASS__, 'bell_shortcode' ) );

		add_action( 'rest_api_init', array( __CLASS__, 'rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 25 );

		// Creator-facing commerce notifications. These run after Memberships grants/revokes access.
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'membership_order_paid' ), 70 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'membership_order_paid' ), 70 );
		add_action( 'woocommerce_subscription_status_active', array( __CLASS__, 'membership_subscription_active' ), 70 );
		add_action( 'woocommerce_subscription_status_cancelled', array( __CLASS__, 'membership_subscription_inactive' ), 70 );
		add_action( 'woocommerce_subscription_status_expired', array( __CLASS__, 'membership_subscription_inactive' ), 70 );
		add_action( 'woocommerce_subscription_status_on-hold', array( __CLASS__, 'membership_subscription_inactive' ), 70 );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nfinite_notifications';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table = self::table();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(50) NOT NULL DEFAULT 'general',
			actor_creator_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			message text NOT NULL,
			url text NOT NULL,
			dedupe_key varchar(191) NOT NULL DEFAULT '',
			is_read tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			read_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_dedupe (user_id,dedupe_key),
			KEY user_read_created (user_id,is_read,created_at),
			KEY creator_created (actor_creator_id,created_at),
			KEY object_id (object_id)
		) {$charset};";
		dbDelta( $sql );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
		self::ensure_page();
	}

	public static function maybe_install() {
		if ( self::DB_VERSION !== (string) get_option( self::DB_OPTION, '' ) ) {
			self::install();
		}
	}

	public static function ensure_page() {
		$page_id = absint( get_option( self::PAGE_OPTION ) );
		if ( $page_id && 'trash' !== get_post_status( $page_id ) ) { return $page_id; }
		$existing = get_page_by_path( 'notifications' );
		if ( $existing ) {
			update_option( self::PAGE_OPTION, $existing->ID, false );
			return (int) $existing->ID;
		}
		$page_id = wp_insert_post( array(
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => __( 'Notifications', 'nfinite-creators' ),
			'post_name' => 'notifications',
			'post_content' => '[nfinite_notifications]',
		) );
		if ( $page_id && ! is_wp_error( $page_id ) ) { update_option( self::PAGE_OPTION, (int) $page_id, false ); }
		return is_wp_error( $page_id ) ? 0 : (int) $page_id;
	}

	public static function page_url() {
		$page_id = absint( get_option( self::PAGE_OPTION ) );
		return $page_id && get_post_status( $page_id ) ? get_permalink( $page_id ) : home_url( '/notifications/' );
	}

	public static function assets() {
		if ( ! is_user_logged_in() ) { return; }
		wp_enqueue_script( 'nfinite-notifications', NFINITE_CREATORS_URL . 'public/js/nfinite-notifications.js', array(), NFINITE_CREATORS_VERSION, true );
		wp_localize_script( 'nfinite-notifications', 'NfiniteNotifications', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'nfinite_notifications' ),
		) );
	}

	public static function preferences( $user_id ) {
		$defaults = array(
			'music' => 1,
			'video' => 1,
			'posts' => 1,
			'events' => 1,
			'memberships' => 1,
			'opportunities' => 1,
			'follows' => 1,
		);
		$saved = get_user_meta( absint( $user_id ), self::PREF_META, true );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	private static function preference_allows( $user_id, $bucket ) {
		$prefs = self::preferences( $user_id );
		return ! empty( $prefs[ $bucket ] );
	}

	public static function create( $args ) {
		global $wpdb;
		$args = wp_parse_args( $args, array(
			'user_id' => 0, 'type' => 'general', 'actor_creator_id' => 0, 'object_id' => 0,
			'title' => '', 'message' => '', 'url' => '', 'dedupe_key' => '',
		) );
		$user_id = absint( $args['user_id'] );
		if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) { return 0; }
		$dedupe = sanitize_key( $args['dedupe_key'] );
		if ( ! $dedupe ) {
			$dedupe = substr( md5( $args['type'] . '|' . $args['object_id'] . '|' . $args['actor_creator_id'] . '|' . $args['title'] . '|' . microtime( true ) ), 0, 32 );
		}
		$result = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO " . self::table() . " (user_id,type,actor_creator_id,object_id,title,message,url,dedupe_key,is_read,created_at) VALUES (%d,%s,%d,%d,%s,%s,%s,%s,0,%s)",
			$user_id,
			sanitize_key( $args['type'] ),
			absint( $args['actor_creator_id'] ),
			absint( $args['object_id'] ),
			sanitize_text_field( $args['title'] ),
			sanitize_textarea_field( $args['message'] ),
			esc_url_raw( $args['url'] ),
			$dedupe,
			current_time( 'mysql', true )
		) );
		return $result ? (int) $wpdb->insert_id : 0;
	}

	public static function unread_count( $user_id = 0 ) {
		global $wpdb;
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id ) { return 0; }
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id=%d AND is_read=0', $user_id ) );
	}

	public static function items( $user_id = 0, $limit = 40, $offset = 0 ) {
		global $wpdb;
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id ) { return array(); }
		$limit = min( 100, max( 1, absint( $limit ) ) );
		$offset = max( 0, absint( $offset ) );
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE user_id=%d ORDER BY created_at DESC,id DESC LIMIT %d OFFSET %d',
			$user_id, $limit, $offset
		) );
	}

	private static function creator_ids_for( $post_id ) {
		$post_id = absint( $post_id );
		$type = get_post_type( $post_id );
		$ids = array();
		$keys = array(
			'nfinite_release' => '_nfinite_release_creator_id',
			'nfinite_track' => '_nfinite_track_creator_id',
			'nfinite_show' => '_nfinite_show_creator_id',
			'nfinite_episode' => '_nfinite_episode_creator_id',
			'nfinite_video' => '_nfinite_video_creator_id',
			'nfinite_creator_post' => '_nfinite_creator_id',
			'post' => '_nfinite_creator_id',
		);
		if ( isset( $keys[ $type ] ) ) {
			$id = absint( get_post_meta( $post_id, $keys[ $type ], true ) );
			if ( $id ) { $ids[] = $id; }
		}
		if ( 'nfinite_episode' === $type && ! $ids ) {
			$show = absint( get_post_meta( $post_id, '_nfinite_episode_show_id', true ) );
			$id = $show ? absint( get_post_meta( $show, '_nfinite_show_creator_id', true ) ) : 0;
			if ( $id ) { $ids[] = $id; }
		}
		if ( 'nfinite_event' === $type ) {
			$event_ids = (array) get_post_meta( $post_id, '_nfinite_event_creator_ids', true );
			$ids = array_merge( $ids, array_map( 'absint', $event_ids ) );
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	private static function follower_user_ids( $creator_id ) {
		global $wpdb;
		$creator_id = absint( $creator_id );
		if ( ! $creator_id ) { return array(); }
		// Nfinite Library persists integer IDs in a serialized array.
		$needle = '%i:' . $creator_id . ';%';
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key=%s AND meta_value LIKE %s",
			'_nfinite_following', $needle
		) );
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	private static function bucket_for_type( $post_type, $post_id = 0 ) {
		$access = $post_id ? get_post_meta( $post_id, '_nfinite_membership_access', true ) : '';
		if ( $access && 'public' !== $access ) { return 'memberships'; }
		if ( in_array( $post_type, array( 'nfinite_release', 'nfinite_track' ), true ) ) { return 'music'; }
		if ( in_array( $post_type, array( 'nfinite_video', 'nfinite_show', 'nfinite_episode' ), true ) ) { return 'video'; }
		if ( 'nfinite_event' === $post_type ) { return 'events'; }
		return 'posts';
	}

	private static function type_label( $post_type, $post_id = 0 ) {
		$access = $post_id ? get_post_meta( $post_id, '_nfinite_membership_access', true ) : '';
		if ( $access && 'public' !== $access ) { return __( 'Member Exclusive', 'nfinite-creators' ); }
		$labels = array(
			'nfinite_release' => __( 'New Release', 'nfinite-creators' ),
			'nfinite_track' => __( 'New Track', 'nfinite-creators' ),
			'nfinite_video' => __( 'New Video', 'nfinite-creators' ),
			'nfinite_show' => __( 'New Show', 'nfinite-creators' ),
			'nfinite_episode' => __( 'New Episode', 'nfinite-creators' ),
			'nfinite_event' => __( 'New Event', 'nfinite-creators' ),
			'nfinite_creator_post' => __( 'New Post', 'nfinite-creators' ),
			'post' => __( 'New Story', 'nfinite-creators' ),
		);
		return isset( $labels[ $post_type ] ) ? $labels[ $post_type ] : __( 'New Content', 'nfinite-creators' );
	}

	public static function maybe_notify_after_meta_save( $post_id, $post, $update ) {
		if ( ! $post || 'publish' !== $post->post_status || get_post_meta( $post_id, '_nfinite_publish_notification_sent', true ) ) { return; }
		// Creator relationship meta is commonly saved after transition_post_status. This second pass
		// ensures first-publish notifications are not missed when ownership is assigned during save_post.
		self::content_published( 'publish', 'pending_meta', $post );
	}

	public static function content_published( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || ! $post || wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) { return; }
		$allowed = array( 'nfinite_release','nfinite_track','nfinite_video','nfinite_show','nfinite_episode','nfinite_event','nfinite_creator_post','post' );
		if ( ! in_array( $post->post_type, $allowed, true ) ) { return; }
		if ( get_post_meta( $post->ID, '_nfinite_publish_notification_sent', true ) ) { return; }
		$creators = self::creator_ids_for( $post->ID );
		if ( ! $creators ) { return; }
		$bucket = self::bucket_for_type( $post->post_type, $post->ID );
		$label = self::type_label( $post->post_type, $post->ID );
		foreach ( $creators as $creator_id ) {
			$creator_name = get_the_title( $creator_id );
			foreach ( self::follower_user_ids( $creator_id ) as $user_id ) {
				if ( ! self::preference_allows( $user_id, $bucket ) ) { continue; }
				self::create( array(
					'user_id' => $user_id,
					'type' => 'content_' . $bucket,
					'actor_creator_id' => $creator_id,
					'object_id' => $post->ID,
					'title' => $label,
					'message' => sprintf( __( '%1$s published %2$s.', 'nfinite-creators' ), $creator_name ?: __( 'A creator you follow', 'nfinite-creators' ), get_the_title( $post ) ),
					'url' => get_permalink( $post ),
					'dedupe_key' => 'publish_' . $post->ID . '_creator_' . $creator_id,
				) );
			}
		}
		update_post_meta( $post->ID, '_nfinite_publish_notification_sent', current_time( 'mysql', true ) );
	}

	public static function notify_new_follow( $follower_user_id, $object_id ) {
		$object_id = absint( $object_id );
		if ( 'nfinite_creator' !== get_post_type( $object_id ) ) { return; }
		$owner = absint( get_post_field( 'post_author', $object_id ) );
		if ( ! $owner || $owner === absint( $follower_user_id ) || ! self::preference_allows( $owner, 'follows' ) ) { return; }
		$follower = get_user_by( 'id', absint( $follower_user_id ) );
		self::create( array(
			'user_id' => $owner,
			'type' => 'new_follower',
			'actor_creator_id' => $object_id,
			'object_id' => $object_id,
			'title' => __( 'New follower', 'nfinite-creators' ),
			'message' => sprintf( __( '%s started following your creator profile.', 'nfinite-creators' ), $follower ? $follower->display_name : __( 'Someone', 'nfinite-creators' ) ),
			'url' => get_permalink( $object_id ),
			'dedupe_key' => 'follow_' . absint( $follower_user_id ) . '_' . $object_id,
		) );
	}

	public static function brand_application_status_changed( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( '_nfinite_app_status' !== $meta_key || 'nfinite_brand_app' !== get_post_type( $object_id ) ) { return; }
		$user_id = absint( get_post_meta( $object_id, '_nfinite_app_user', true ) );
		if ( ! $user_id || ! self::preference_allows( $user_id, 'opportunities' ) ) { return; }
		$opportunity = absint( get_post_meta( $object_id, '_nfinite_app_opportunity', true ) );
		$creator_id = absint( get_post_meta( $object_id, '_nfinite_app_creator', true ) );
		$status = sanitize_key( $meta_value );
		self::create( array(
			'user_id' => $user_id,
			'type' => 'brand_deal',
			'actor_creator_id' => $creator_id,
			'object_id' => $opportunity,
			'title' => __( 'Brand deal update', 'nfinite-creators' ),
			'message' => sprintf( __( 'Your application for %1$s is now %2$s.', 'nfinite-creators' ), get_the_title( $opportunity ), ucwords( str_replace( '_', ' ', $status ) ) ),
			'url' => $opportunity ? get_permalink( $opportunity ) : self::page_url(),
			'dedupe_key' => 'brand_app_' . $object_id . '_' . $status,
		) );
	}

	private static function creator_owner( $creator_id ) {
		return absint( get_post_field( 'post_author', absint( $creator_id ) ) );
	}

	private static function membership_event( $member_user_id, $tier_id, $state, $source_key ) {
		$tier_id = absint( $tier_id );
		$creator_id = absint( get_post_meta( $tier_id, '_nfinite_membership_creator_id', true ) );
		$owner = self::creator_owner( $creator_id );
		if ( ! $owner || ! self::preference_allows( $owner, 'memberships' ) ) { return; }
		$member = get_user_by( 'id', absint( $member_user_id ) );
		$messages = array(
			'active' => __( '%1$s joined %2$s.', 'nfinite-creators' ),
			'inactive' => __( '%1$s no longer has access to %2$s.', 'nfinite-creators' ),
		);
		$template = isset( $messages[ $state ] ) ? $messages[ $state ] : $messages['active'];
		self::create( array(
			'user_id' => $owner,
			'type' => 'membership_' . $state,
			'actor_creator_id' => $creator_id,
			'object_id' => $tier_id,
			'title' => 'active' === $state ? __( 'New member', 'nfinite-creators' ) : __( 'Membership update', 'nfinite-creators' ),
			'message' => sprintf( $template, $member ? $member->display_name : __( 'A fan', 'nfinite-creators' ), get_the_title( $tier_id ) ),
			'url' => $creator_id ? get_permalink( $creator_id ) : self::page_url(),
			'dedupe_key' => 'membership_' . sanitize_key( $state ) . '_' . sanitize_key( $source_key ) . '_' . $tier_id,
		) );
	}

	public static function membership_order_paid( $order_id ) {
		if ( ! class_exists( 'Nfinite_Creators_Memberships' ) || ! function_exists( 'wc_get_order' ) ) { return; }
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_user_id() ) { return; }
		foreach ( $order->get_items() as $item ) {
			$tier = Nfinite_Creators_Memberships::tier_for_product( $item->get_product_id() );
			if ( $tier ) { self::membership_event( $order->get_user_id(), $tier->ID, 'active', 'order_' . $order_id ); }
		}
	}

	public static function membership_subscription_active( $subscription ) {
		if ( ! class_exists( 'Nfinite_Creators_Memberships' ) || ! is_object( $subscription ) ) { return; }
		$source = method_exists( $subscription, 'get_id' ) ? 'subscription_' . $subscription->get_id() : 'subscription_' . md5( serialize( $subscription ) );
		foreach ( $subscription->get_items() as $item ) {
			$tier = Nfinite_Creators_Memberships::tier_for_product( $item->get_product_id() );
			if ( $tier ) { self::membership_event( $subscription->get_user_id(), $tier->ID, 'active', $source ); }
		}
	}

	public static function membership_subscription_inactive( $subscription ) {
		if ( ! class_exists( 'Nfinite_Creators_Memberships' ) || ! is_object( $subscription ) ) { return; }
		$source = method_exists( $subscription, 'get_id' ) ? 'subscription_' . $subscription->get_id() : 'subscription_' . md5( serialize( $subscription ) );
		foreach ( $subscription->get_items() as $item ) {
			$tier = Nfinite_Creators_Memberships::tier_for_product( $item->get_product_id() );
			if ( $tier ) { self::membership_event( $subscription->get_user_id(), $tier->ID, 'inactive', $source ); }
		}
	}

	public static function mark_read( $notification_id, $user_id = 0 ) {
		global $wpdb;
		$user_id = $user_id ?: get_current_user_id();
		return (bool) $wpdb->update( self::table(), array( 'is_read'=>1, 'read_at'=>current_time('mysql',true) ), array( 'id'=>absint($notification_id), 'user_id'=>absint($user_id) ), array('%d','%s'), array('%d','%d') );
	}

	public static function ajax_read() {
		check_ajax_referer( 'nfinite_notifications', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message'=>__('Log in to manage notifications.','nfinite-creators') ), 401 ); }
		self::mark_read( absint( $_POST['notification_id'] ?? 0 ) );
		wp_send_json_success( array( 'unread'=>self::unread_count() ) );
	}

	public static function ajax_read_all() {
		check_ajax_referer( 'nfinite_notifications', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message'=>__('Log in to manage notifications.','nfinite-creators') ), 401 ); }
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET is_read=1,read_at=%s WHERE user_id=%d AND is_read=0', current_time('mysql',true), get_current_user_id() ) );
		wp_send_json_success( array( 'unread'=>0 ) );
	}

	public static function save_preferences() {
		if ( ! is_user_logged_in() || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'nfinite_notification_preferences' ) ) { wp_die( esc_html__( 'Invalid request.', 'nfinite-creators' ) ); }
		$keys = array( 'music','video','posts','events','memberships','opportunities','follows' );
		$raw = isset( $_POST['prefs'] ) ? (array) $_POST['prefs'] : array();
		$prefs = array();
		foreach ( $keys as $key ) { $prefs[ $key ] = isset( $raw[ $key ] ) ? 1 : 0; }
		update_user_meta( get_current_user_id(), self::PREF_META, $prefs );
		wp_safe_redirect( add_query_arg( 'notifications_saved', '1', self::page_url() ) );
		exit;
	}

	private static function icon_for( $type ) {
		if ( false !== strpos( $type, 'music' ) ) { return '♫'; }
		if ( false !== strpos( $type, 'video' ) ) { return '▶'; }
		if ( false !== strpos( $type, 'membership' ) ) { return '★'; }
		if ( false !== strpos( $type, 'brand' ) ) { return '◆'; }
		if ( false !== strpos( $type, 'follower' ) ) { return '＋'; }
		if ( false !== strpos( $type, 'events' ) ) { return '◷'; }
		return '•';
	}

	public static function bell_shortcode() {
		if ( ! is_user_logged_in() ) { return ''; }
		$count = self::unread_count();
		return '<a class="nfinite-notification-bell" href="'.esc_url(self::page_url()).'" aria-label="'.esc_attr__('Notifications','nfinite-creators').'"><span aria-hidden="true">♢</span>'.($count?'<b data-nfinite-unread-count>'.absint($count).'</b>':'<b data-nfinite-unread-count hidden>0</b>').'</a>';
	}

	public static function notifications_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<section class="nfinite-notifications"><h2>'.esc_html__('Notifications','nfinite-creators').'</h2><p>'.sprintf(wp_kses_post(__('Please <a href="%s">log in</a> to view your notifications.','nfinite-creators')),esc_url(wp_login_url(self::page_url()))).'</p></section>';
		}
		$items = self::items( get_current_user_id(), 60 );
		$prefs = self::preferences( get_current_user_id() );
		ob_start();
		?>
		<section class="nfinite-notifications">
			<header class="nfinite-notifications__header">
				<div><span class="nfinite-eyebrow"><?php esc_html_e('PairOfDice','nfinite-creators'); ?></span><h2><?php esc_html_e('Notifications','nfinite-creators'); ?></h2><p><?php esc_html_e('New releases, episodes, events, exclusives and creator activity that matter to you.','nfinite-creators'); ?></p></div>
				<?php if(self::unread_count()): ?><button type="button" class="nfinite-btn nfinite-btn--ghost" data-nfinite-read-all><?php esc_html_e('Mark all read','nfinite-creators'); ?></button><?php endif; ?>
			</header>
			<?php if(isset($_GET['notifications_saved'])): ?><p class="nfinite-notice"><?php esc_html_e('Notification preferences saved.','nfinite-creators'); ?></p><?php endif; ?>
			<div class="nfinite-notification-list">
			<?php if(!$items): ?><div class="nfinite-studio-empty"><strong><?php esc_html_e('You are all caught up','nfinite-creators'); ?></strong><p><?php esc_html_e('Follow creators to get updates when they publish new content.','nfinite-creators'); ?></p></div><?php endif; ?>
			<?php foreach($items as $item): $creator=$item->actor_creator_id?get_post($item->actor_creator_id):null; ?>
				<article class="nfinite-notification<?php echo $item->is_read?'':' is-unread'; ?>" data-notification-id="<?php echo absint($item->id); ?>">
					<div class="nfinite-notification__icon" aria-hidden="true"><?php echo esc_html(self::icon_for($item->type)); ?></div>
					<div class="nfinite-notification__body">
						<div class="nfinite-notification__meta"><?php if($creator): ?><a href="<?php echo esc_url(get_permalink($creator)); ?>"><?php echo esc_html(get_the_title($creator)); ?></a><span> · </span><?php endif; ?><time datetime="<?php echo esc_attr(mysql2date('c',$item->created_at,false)); ?>"><?php echo esc_html(human_time_diff(strtotime($item->created_at.' UTC'),current_time('timestamp',true)).' '.__('ago','nfinite-creators')); ?></time></div>
						<h3><?php echo esc_html($item->title); ?></h3><p><?php echo esc_html($item->message); ?></p>
						<?php if($item->url): ?><a class="nfinite-notification__link" href="<?php echo esc_url($item->url); ?>" data-nfinite-notification-open><?php esc_html_e('View','nfinite-creators'); ?></a><?php endif; ?>
					</div>
					<?php if(!$item->is_read): ?><button type="button" class="nfinite-notification__read" data-nfinite-notification-read aria-label="<?php esc_attr_e('Mark as read','nfinite-creators'); ?>">✓</button><?php endif; ?>
				</article>
			<?php endforeach; ?>
			</div>
			<details class="nfinite-notification-settings"><summary><?php esc_html_e('Notification preferences','nfinite-creators'); ?></summary>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="nfinite_notification_preferences"><?php wp_nonce_field('nfinite_notification_preferences'); ?>
				<?php $labels=array('music'=>__('Music releases','nfinite-creators'),'video'=>__('Videos, shows and episodes','nfinite-creators'),'posts'=>__('Creator posts and stories','nfinite-creators'),'events'=>__('Events','nfinite-creators'),'memberships'=>__('Memberships and exclusives','nfinite-creators'),'opportunities'=>__('Brand deal updates','nfinite-creators'),'follows'=>__('New followers','nfinite-creators')); foreach($labels as $key=>$label): ?><label><input type="checkbox" name="prefs[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($prefs[$key])); ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?>
				<button type="submit" class="nfinite-btn"><?php esc_html_e('Save preferences','nfinite-creators'); ?></button></form>
			</details>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function rest_routes() {
		register_rest_route( 'nfinite/v1', '/notifications', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'rest_list' ),
			'permission_callback' => function(){ return is_user_logged_in(); },
			'args' => array( 'limit'=>array('default'=>30,'sanitize_callback'=>'absint'), 'offset'=>array('default'=>0,'sanitize_callback'=>'absint') ),
		) );
		register_rest_route( 'nfinite/v1', '/notifications/read', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'rest_read' ),
			'permission_callback' => function(){ return is_user_logged_in(); },
		) );
	}

	public static function rest_list( $request ) {
		$rows = self::items( get_current_user_id(), $request->get_param('limit'), $request->get_param('offset') );
		$out = array();
		foreach($rows as $r){$out[]=array('id'=>(int)$r->id,'type'=>$r->type,'creator_id'=>(int)$r->actor_creator_id,'object_id'=>(int)$r->object_id,'title'=>$r->title,'message'=>$r->message,'url'=>$r->url,'read'=>(bool)$r->is_read,'created_at'=>mysql2date('c',$r->created_at,false));}
		return rest_ensure_response(array('unread'=>self::unread_count(),'items'=>$out));
	}

	public static function rest_read( $request ) {
		$id = absint( $request->get_param('id') );
		if($id) self::mark_read($id); else { global $wpdb; $wpdb->query($wpdb->prepare('UPDATE '.self::table().' SET is_read=1,read_at=%s WHERE user_id=%d AND is_read=0',current_time('mysql',true),get_current_user_id())); }
		return rest_ensure_response(array('unread'=>self::unread_count()));
	}
}
