<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * First-party creator analytics.
 *
 * Stores anonymous, creator-scoped audience and media engagement data.
 * No IP address, email address, account ID, or browser fingerprint is stored.
 */
class Nfinite_Creators_Analytics {
	const DB_VERSION = '1.2.0';
	const DB_OPTION  = 'nfinite_creators_analytics_db_version';
	const TABLE_SLUG = 'nfinite_creator_analytics';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 30 );
		add_action( 'wp_ajax_nfinite_analytics_event', array( __CLASS__, 'handle_event' ) );
		add_action( 'wp_ajax_nopriv_nfinite_analytics_event', array( __CLASS__, 'handle_event' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attach_visitor_to_order' ), 10, 2 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'record_purchase' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'record_purchase' ) );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SLUG;
	}

	public static function maybe_install() {
		if ( self::DB_VERSION !== get_option( self::DB_OPTION ) ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(40) NOT NULL,
			creator_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_type varchar(40) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			visitor_hash char(64) NOT NULL,
			session_hash char(64) NOT NULL,
			occurred_at datetime NOT NULL,
			engagement_ms bigint(20) unsigned NOT NULL DEFAULT 0,
			source varchar(50) NOT NULL DEFAULT 'direct',
			referrer_host varchar(191) NOT NULL DEFAULT '',
			page_path varchar(191) NOT NULL DEFAULT '',
			device_type varchar(20) NOT NULL DEFAULT 'unknown',
			metadata longtext NULL,
			PRIMARY KEY  (id),
			KEY creator_date (creator_id, occurred_at),
			KEY creator_event_date (creator_id, event_type, occurred_at),
			KEY creator_object_date (creator_id, object_id, occurred_at),
			KEY visitor_creator (visitor_hash, creator_id),
			KEY session_creator (session_hash, creator_id)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	public static function enqueue_assets() {
		if ( is_admin() || ! self::tracking_enabled() ) {
			return;
		}

		// V1.1 listens for media events site-wide. Creator page metrics are still
		// emitted only when current_creator_id() resolves to a public creator.
		wp_enqueue_script(
			'nfinite-creators-analytics',
			NFINITE_CREATORS_URL . 'public/js/nfinite-analytics.js',
			array(),
			NFINITE_CREATORS_VERSION,
			true
		);

		$creator_id = self::current_creator_id();
		$product_context = self::current_product_context();
		wp_localize_script(
			'nfinite-creators-analytics',
			'NfiniteCreatorAnalytics',
			array(
				'endpoint'          => admin_url( 'admin-ajax.php' ),
				'action'            => 'nfinite_analytics_event',
				'creatorId'         => $creator_id,
				'objectType'        => $creator_id ? 'creator' : '',
				'objectId'          => $creator_id,
				'sessionTimeoutMs'  => 30 * MINUTE_IN_SECONDS * 1000,
				'engagementPingMs'  => 15 * 1000,
				'minEngagementMs'   => 1000,
				'meaningfulPlayMs'  => 5000,
				'productCreatorId'  => $product_context['creator_id'],
				'productId'         => $product_context['product_id'],
			)
		);
	}

	public static function tracking_enabled() {
		$enabled = ! is_feed() && ! is_robots() && ! wp_doing_cron();
		return (bool) apply_filters( 'nfinite_creator_analytics_tracking_enabled', $enabled );
	}

	public static function current_creator_id() {
		if ( is_singular( 'nfinite_creator' ) ) {
			return absint( get_queried_object_id() );
		}
		return 0;
	}

	private static function is_same_origin_request() {
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $home_host ) {
			return true;
		}

		$candidate = '';
		if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) {
			$candidate = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$candidate = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		if ( ! $candidate ) {
			return true;
		}

		$request_host = wp_parse_url( $candidate, PHP_URL_HOST );
		return $request_host && strtolower( $request_host ) === strtolower( $home_host );
	}

	private static function valid_uuid( $value ) {
		return is_string( $value ) && (bool) preg_match( '/^[a-f0-9-]{16,64}$/i', $value );
	}

	private static function hash_identifier( $value ) {
		return hash_hmac( 'sha256', strtolower( trim( $value ) ), wp_salt( 'auth' ) );
	}

	private static function classify_source( $page_url, $referrer ) {
		$page_query = wp_parse_url( $page_url, PHP_URL_QUERY );
		if ( $page_query ) {
			parse_str( $page_query, $query );
			if ( ! empty( $query['utm_source'] ) ) {
				return substr( sanitize_key( $query['utm_source'] ), 0, 50 );
			}
		}

		$host = strtolower( (string) wp_parse_url( $referrer, PHP_URL_HOST ) );
		if ( ! $host ) {
			return 'direct';
		}

		$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( $home_host && $host === $home_host ) {
			return 'internal';
		}

		$map = array(
			'instagram'  => 'instagram',
			'facebook'   => 'facebook',
			'fb.com'     => 'facebook',
			'tiktok'     => 'tiktok',
			'youtube'    => 'youtube',
			'youtu.be'   => 'youtube',
			'google'     => 'google',
			'bing'       => 'bing',
			'twitter'    => 'x',
			'x.com'      => 'x',
			'spotify'    => 'spotify',
			'soundcloud' => 'soundcloud',
		);
		foreach ( $map as $needle => $source ) {
			if ( false !== strpos( $host, $needle ) ) {
				return $source;
			}
		}
		return 'referral';
	}

	private static function device_type( $value ) {
		$value = sanitize_key( $value );
		return in_array( $value, array( 'mobile', 'tablet', 'desktop', 'unknown' ), true ) ? $value : 'unknown';
	}


	private static function current_product_context() {
		if ( function_exists( 'is_product' ) && is_product() ) {
			$product_id = absint( get_queried_object_id() );
			$creator_id = absint( get_post_meta( $product_id, '_nfinite_creator_id', true ) );
			if ( $creator_id && 'publish' === get_post_status( $creator_id ) ) {
				return array( 'creator_id' => $creator_id, 'product_id' => $product_id );
			}
		}
		return array( 'creator_id' => 0, 'product_id' => 0 );
	}

	private static function commerce_event_types() {
		return array( 'product_view', 'add_to_cart' );
	}

	private static function validate_product_object( $creator_id, $object_id ) {
		if ( ! $object_id || 'product' !== get_post_type( $object_id ) || 'publish' !== get_post_status( $object_id ) ) { return false; }
		return absint( get_post_meta( $object_id, '_nfinite_creator_id', true ) ) === absint( $creator_id );
	}

	private static function media_event_types() {
		return array( 'media_play', 'media_25', 'media_50', 'media_75', 'media_complete' );
	}

	private static function legacy_media_object_id( $creator_id, $index ) {
		$key = 'creator-beat-' . absint( $creator_id ) . '-' . absint( $index );
		return absint( sprintf( '%u', crc32( $key ) ) );
	}

	private static function validate_media_object( $creator_id, $object_id, $declared_type = 'track' ) {
		$creator_id   = absint( $creator_id );
		$object_id    = absint( $object_id );
		$declared_type = sanitize_key( $declared_type );

		if ( 'product_preview' === $declared_type ) {
			if ( ! $object_id || 'product' !== get_post_type( $object_id ) || 'publish' !== get_post_status( $object_id ) ) { return false; }
			return absint( get_post_meta( $object_id, '_nfinite_creator_id', true ) ) === $creator_id;
		}

		if ( 'legacy_track' === $declared_type ) {
			$tracks = get_post_meta( $creator_id, '_nfinite_creator_tracks', true );
			if ( ! is_array( $tracks ) ) { return false; }
			foreach ( array_keys( $tracks ) as $index ) {
				if ( self::legacy_media_object_id( $creator_id, $index ) === $object_id ) { return true; }
			}
			return false;
		}

		if ( ! $object_id || 'nfinite_track' !== get_post_type( $object_id ) || 'publish' !== get_post_status( $object_id ) ) {
			return false;
		}
		$owner = absint( get_post_meta( $object_id, '_nfinite_track_creator_id', true ) );
		return $owner && $owner === $creator_id;
	}

	private static function media_object_type( $object_id, $declared_type = 'track' ) {
		$declared_type = sanitize_key( $declared_type );
		if ( in_array( $declared_type, array( 'product_preview', 'legacy_track' ), true ) ) { return 'beat'; }
		$product_id = absint( get_post_meta( $object_id, '_nfinite_track_product_id', true ) );
		if ( $product_id && 'beat' === get_post_meta( $product_id, '_nfinite_creator_product_type', true ) ) {
			return 'beat';
		}
		return 'track';
	}

	private static function event_metadata( $event_type ) {
		if ( ! in_array( $event_type, self::media_event_types(), true ) ) {
			return null;
		}

		$source = isset( $_POST['media_source'] ) ? sanitize_key( wp_unslash( $_POST['media_source'] ) ) : 'local';
		if ( ! in_array( $source, array( 'local', 'spotify', 'soundcloud', 'apple_music', 'unknown' ), true ) ) {
			$source = 'unknown';
		}
		$position_ms = isset( $_POST['position_ms'] ) ? min( DAY_IN_SECONDS * 1000, absint( $_POST['position_ms'] ) ) : 0;
		$duration_ms = isset( $_POST['duration_ms'] ) ? min( DAY_IN_SECONDS * 1000, absint( $_POST['duration_ms'] ) ) : 0;
		$media_title = isset( $_POST['media_title'] ) ? sanitize_text_field( wp_unslash( $_POST['media_title'] ) ) : '';

		return wp_json_encode(
			array(
				'media_source' => $source,
				'position_ms'  => $position_ms,
				'duration_ms'  => $duration_ms,
				'media_title'  => substr( $media_title, 0, 191 ),
			)
		);
	}

	public static function handle_event() {
		if ( ! self::tracking_enabled() || ! self::is_same_origin_request() ) {
			wp_send_json_error( array( 'message' => 'Tracking unavailable.' ), 403 );
		}

		$event_type = isset( $_POST['event_type'] ) ? sanitize_key( wp_unslash( $_POST['event_type'] ) ) : '';
		$allowed    = array_merge( array( 'creator_view', 'engagement', 'page_view', 'site_engagement' ), self::media_event_types(), self::commerce_event_types() );
		if ( ! in_array( $event_type, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid event.' ), 400 );
		}

		$creator_id = isset( $_POST['creator_id'] ) ? absint( $_POST['creator_id'] ) : 0;
		$site_event = in_array( $event_type, array( 'page_view', 'site_engagement' ), true );
		if ( ! $site_event && ( ! $creator_id || 'nfinite_creator' !== get_post_type( $creator_id ) || 'publish' !== get_post_status( $creator_id ) ) ) {
			wp_send_json_error( array( 'message' => 'Invalid creator.' ), 400 );
		}

		$object_id   = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;
		$object_type = 'creator';
		if ( in_array( $event_type, self::commerce_event_types(), true ) ) {
			if ( ! self::validate_product_object( $creator_id, $object_id ) ) {
				wp_send_json_error( array( 'message' => 'Invalid product object.' ), 400 );
			}
			$object_type = 'product';
		} elseif ( in_array( $event_type, self::media_event_types(), true ) ) {
			$declared_media_type = isset( $_POST['object_type'] ) ? sanitize_key( wp_unslash( $_POST['object_type'] ) ) : 'track';
			if ( ! in_array( $declared_media_type, array( 'track', 'product_preview', 'legacy_track' ), true ) ) { $declared_media_type = 'track'; }
			if ( ! self::validate_media_object( $creator_id, $object_id, $declared_media_type ) ) {
				wp_send_json_error( array( 'message' => 'Invalid media object.' ), 400 );
			}
			$object_type = self::media_object_type( $object_id, $declared_media_type );
		} elseif ( $site_event ) {
			$creator_id = 0; $object_id = 0; $object_type = 'site';
		} else {
			$object_id = $creator_id;
		}

		$visitor_id = isset( $_POST['visitor_id'] ) ? sanitize_text_field( wp_unslash( $_POST['visitor_id'] ) ) : '';
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		if ( ! self::valid_uuid( $visitor_id ) || ! self::valid_uuid( $session_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid analytics identifiers.' ), 400 );
		}

		$engagement_ms = isset( $_POST['engagement_ms'] ) ? min( 300000, absint( $_POST['engagement_ms'] ) ) : 0;
		if ( in_array( $event_type, array( 'engagement', 'site_engagement' ), true ) && $engagement_ms < 250 ) {
			wp_send_json_success();
		}

		$visitor_hash = self::hash_identifier( $visitor_id );
		$session_hash = self::hash_identifier( $session_id );

		// Page events may repeat over time; media milestones should occur only once
		// per track/session. Transients provide a cheap duplicate/rate guard.
		if ( in_array( $event_type, self::media_event_types(), true ) ) {
			$rate_seconds = 12 * HOUR_IN_SECONDS;
			$rate_scope   = $object_id;
		} elseif ( in_array( $event_type, self::commerce_event_types(), true ) ) {
			$rate_seconds = 'product_view' === $event_type ? 60 : 2;
			$rate_scope   = $object_id;
		} else {
			$rate_seconds = in_array( $event_type, array( 'creator_view', 'page_view' ), true ) ? 60 : 2;
			$rate_scope   = $creator_id;
		}
		$rate_key = 'nfinite_an_' . substr( md5( $event_type . '|' . $creator_id . '|' . $rate_scope . '|' . $session_hash ), 0, 24 );
		if ( get_transient( $rate_key ) ) {
			wp_send_json_success();
		}
		set_transient( $rate_key, 1, $rate_seconds );

		$page_url      = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
		$referrer      = isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : '';
		$referrer_host = sanitize_text_field( (string) wp_parse_url( $referrer, PHP_URL_HOST ) );
		$page_path     = sanitize_text_field( (string) wp_parse_url( $page_url, PHP_URL_PATH ) );
		$source        = self::classify_source( $page_url, $referrer );

		global $wpdb;
		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'event_type'     => $event_type,
				'creator_id'     => $creator_id,
				'object_type'    => $object_type,
				'object_id'      => $object_id,
				'visitor_hash'   => $visitor_hash,
				'session_hash'   => $session_hash,
				'occurred_at'    => current_time( 'mysql', true ),
				'engagement_ms'  => $engagement_ms,
				'source'         => substr( $source, 0, 50 ),
				'referrer_host'  => substr( $referrer_host, 0, 191 ),
				'page_path'      => substr( $page_path, 0, 191 ),
				'device_type'    => self::device_type( isset( $_POST['device_type'] ) ? wp_unslash( $_POST['device_type'] ) : 'unknown' ),
				'metadata'       => self::event_metadata( $event_type ),
			),
			array( '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( array( 'message' => 'Could not record event.' ), 500 );
		}

		wp_send_json_success();
	}

	public static function attach_visitor_to_order( $order, $data ) {
		if ( ! is_a( $order, 'WC_Order' ) ) { return; }
		$visitor = isset( $_COOKIE['nfinite_analytics_visitor'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['nfinite_analytics_visitor'] ) ) : '';
		$session = isset( $_COOKIE['nfinite_analytics_session'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['nfinite_analytics_session'] ) ) : '';
		if ( self::valid_uuid( $visitor ) ) { $order->update_meta_data( '_nfinite_analytics_visitor_hash', self::hash_identifier( $visitor ) ); }
		if ( self::valid_uuid( $session ) ) { $order->update_meta_data( '_nfinite_analytics_session_hash', self::hash_identifier( $session ) ); }
	}

	public static function record_purchase( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) { return; }
		$order = wc_get_order( $order_id );
		if ( ! $order ) { return; }
		$by_creator = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product_id = absint( $item->get_product_id() );
			$creator_id = absint( get_post_meta( $product_id, '_nfinite_creator_id', true ) );
			if ( ! $creator_id ) { continue; }
			if ( ! isset( $by_creator[ $creator_id ] ) ) { $by_creator[ $creator_id ] = array( 'revenue' => 0.0, 'items' => 0 ); }
			$by_creator[ $creator_id ]['revenue'] += (float) $item->get_total();
			$by_creator[ $creator_id ]['items'] += (int) $item->get_quantity();
		}
		if ( ! $by_creator ) { return; }
		$visitor_hash = sanitize_text_field( (string) $order->get_meta( '_nfinite_analytics_visitor_hash', true ) );
		$session_hash = sanitize_text_field( (string) $order->get_meta( '_nfinite_analytics_session_hash', true ) );
		if ( ! $visitor_hash ) { $visitor_hash = hash_hmac( 'sha256', 'order:' . $order_id, wp_salt( 'auth' ) ); }
		if ( ! $session_hash ) { $session_hash = $visitor_hash; }
		global $wpdb;
		foreach ( $by_creator as $creator_id => $totals ) {
			$flag = '_nfinite_analytics_purchase_' . $creator_id;
			if ( $order->get_meta( $flag, true ) ) { continue; }
			$wpdb->insert( self::table_name(), array(
				'event_type' => 'purchase', 'creator_id' => $creator_id, 'object_type' => 'order', 'object_id' => $order_id,
				'visitor_hash' => $visitor_hash, 'session_hash' => $session_hash, 'occurred_at' => current_time( 'mysql', true ),
				'engagement_ms' => 0, 'source' => 'checkout', 'referrer_host' => '', 'page_path' => '/checkout/order-received/', 'device_type' => 'unknown',
				'metadata' => wp_json_encode( array( 'revenue' => round( $totals['revenue'], wc_get_price_decimals() ), 'items' => $totals['items'], 'currency' => $order->get_currency() ) ),
			), array( '%s','%d','%s','%d','%s','%s','%s','%d','%s','%s','%s','%s','%s' ) );
			$order->update_meta_data( $flag, gmdate( 'c' ) );
		}
		$order->save();
	}

	public static function summary( $creator_id, $days = 30 ) {
		global $wpdb;
		$creator_id = absint( $creator_id );
		$days       = max( 1, min( 3650, absint( $days ) ) );
		$table      = self::table_name();
		$posts      = $wpdb->posts;
		$start      = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$base = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(CASE WHEN event_type = 'creator_view' THEN 1 END) AS views,
					COUNT(DISTINCT CASE WHEN event_type = 'creator_view' THEN visitor_hash END) AS visitors,
					COUNT(DISTINCT CASE WHEN event_type IN ('creator_view','engagement') THEN session_hash END) AS sessions,
					COALESCE(SUM(CASE WHEN event_type = 'engagement' THEN engagement_ms ELSE 0 END),0) AS engagement_ms,
					COUNT(CASE WHEN event_type = 'media_play' THEN 1 END) AS media_plays,
					COUNT(DISTINCT CASE WHEN event_type = 'media_play' THEN visitor_hash END) AS unique_listeners,
					COUNT(CASE WHEN event_type = 'media_complete' THEN 1 END) AS media_completes,
					COUNT(CASE WHEN event_type = 'product_view' THEN 1 END) AS product_views,
					COUNT(CASE WHEN event_type = 'add_to_cart' THEN 1 END) AS add_to_carts,
					COUNT(CASE WHEN event_type = 'purchase' THEN 1 END) AS purchases
				FROM {$table}
				WHERE creator_id = %d AND occurred_at >= %s",
				$creator_id,
				$start
			),
			ARRAY_A
		);

		$returning = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT current_period.visitor_hash)
				FROM {$table} current_period
				WHERE current_period.creator_id = %d
				AND current_period.event_type = 'creator_view'
				AND current_period.occurred_at >= %s
				AND EXISTS (
					SELECT 1 FROM {$table} history
					WHERE history.creator_id = current_period.creator_id
					AND history.visitor_hash = current_period.visitor_hash
					AND history.event_type = 'creator_view'
					AND history.session_hash <> current_period.session_hash
				)",
				$creator_id,
				$start
			)
		);

		$sources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source, COUNT(*) AS views
				FROM {$table}
				WHERE creator_id = %d AND event_type = 'creator_view' AND occurred_at >= %s
				GROUP BY source
				ORDER BY views DESC
				LIMIT 6",
				$creator_id,
				$start
			),
			ARRAY_A
		);

		$top_media = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.object_id, MAX(a.object_type) AS object_type, MAX(p.post_title) AS title, MAX(a.metadata) AS event_metadata,
					COUNT(CASE WHEN a.event_type = 'media_play' THEN 1 END) AS plays,
					COUNT(DISTINCT CASE WHEN a.event_type = 'media_play' THEN a.visitor_hash END) AS listeners,
					COUNT(CASE WHEN a.event_type = 'media_complete' THEN 1 END) AS completes
				FROM {$table} a
				LEFT JOIN {$posts} p ON p.ID = a.object_id
				WHERE a.creator_id = %d
				AND a.object_id > 0
				AND a.event_type IN ('media_play','media_complete')
				AND a.occurred_at >= %s
				GROUP BY a.object_id
				HAVING plays > 0
				ORDER BY plays DESC, listeners DESC
				LIMIT 8",
				$creator_id,
				$start
			),
			ARRAY_A
		);

		$purchase_rows = $wpdb->get_results( $wpdb->prepare( "SELECT metadata FROM {$table} WHERE creator_id = %d AND event_type = 'purchase' AND occurred_at >= %s", $creator_id, $start ), ARRAY_A );
		$revenue = 0.0;
		foreach ( $purchase_rows as $row ) { $meta = json_decode( $row['metadata'], true ); if ( is_array( $meta ) ) { $revenue += (float) ( $meta['revenue'] ?? 0 ); } }
		$top_products = $wpdb->get_results( $wpdb->prepare( "SELECT a.object_id, MAX(p.post_title) AS title, COUNT(*) AS views, COUNT(DISTINCT a.visitor_hash) AS visitors FROM {$table} a LEFT JOIN {$posts} p ON p.ID=a.object_id WHERE a.creator_id=%d AND a.event_type='product_view' AND a.occurred_at >= %s GROUP BY a.object_id ORDER BY views DESC LIMIT 8", $creator_id, $start ), ARRAY_A );

		$views           = isset( $base['views'] ) ? (int) $base['views'] : 0;
		$visitors        = isset( $base['visitors'] ) ? (int) $base['visitors'] : 0;
		$sessions        = isset( $base['sessions'] ) ? (int) $base['sessions'] : 0;
		$engagement_ms   = isset( $base['engagement_ms'] ) ? (int) $base['engagement_ms'] : 0;
		$media_plays     = isset( $base['media_plays'] ) ? (int) $base['media_plays'] : 0;
		$unique_listeners= isset( $base['unique_listeners'] ) ? (int) $base['unique_listeners'] : 0;
		$media_completes = isset( $base['media_completes'] ) ? (int) $base['media_completes'] : 0;
		$product_views = isset( $base['product_views'] ) ? (int) $base['product_views'] : 0;
		$add_to_carts = isset( $base['add_to_carts'] ) ? (int) $base['add_to_carts'] : 0;
		$purchases = isset( $base['purchases'] ) ? (int) $base['purchases'] : 0;

		foreach ( $top_media as &$item ) {
			$plays = (int) $item['plays'];
			$item['completion_rate'] = $plays ? round( ( (int) $item['completes'] / $plays ) * 100, 1 ) : 0;
			if ( empty( $item['title'] ) && ! empty( $item['event_metadata'] ) ) {
				$event_meta = json_decode( $item['event_metadata'], true );
				if ( is_array( $event_meta ) && ! empty( $event_meta['media_title'] ) ) { $item['title'] = sanitize_text_field( $event_meta['media_title'] ); }
			}
			unset( $item['event_metadata'] );
		}
		unset( $item );

		// Daily activity series powers the Creator Studio visualization without a third-party analytics/chart dependency.
		$trend_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(occurred_at) AS day,
					COUNT(CASE WHEN event_type = 'creator_view' THEN 1 END) AS views,
					COUNT(CASE WHEN event_type = 'media_play' THEN 1 END) AS plays,
					COUNT(CASE WHEN event_type = 'product_view' THEN 1 END) AS product_views,
					COUNT(CASE WHEN event_type = 'purchase' THEN 1 END) AS purchases
				FROM {$table}
				WHERE creator_id = %d AND occurred_at >= %s
				GROUP BY DATE(occurred_at)
				ORDER BY day ASC",
				$creator_id,
				$start
			),
			ARRAY_A
		);

		$trend_index = array();
		foreach ( (array) $trend_rows as $row ) {
			$trend_index[ $row['day'] ] = array(
				'views'         => (int) $row['views'],
				'plays'         => (int) $row['plays'],
				'product_views' => (int) $row['product_views'],
				'purchases'     => (int) $row['purchases'],
			);
		}
		$trend = array();
		for ( $offset = $days - 1; $offset >= 0; $offset-- ) {
			$day = gmdate( 'Y-m-d', time() - ( $offset * DAY_IN_SECONDS ) );
			$values = isset( $trend_index[ $day ] ) ? $trend_index[ $day ] : array( 'views' => 0, 'plays' => 0, 'product_views' => 0, 'purchases' => 0 );
			$trend[] = array_merge( array( 'day' => $day ), $values );
		}

		// Previous equal-length period for useful at-a-glance change indicators.
		$previous_end   = $start;
		$previous_start = gmdate( 'Y-m-d H:i:s', time() - ( $days * 2 * DAY_IN_SECONDS ) );
		$previous = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(CASE WHEN event_type = 'creator_view' THEN 1 END) AS views,
					COUNT(DISTINCT CASE WHEN event_type = 'creator_view' THEN visitor_hash END) AS visitors,
					COUNT(CASE WHEN event_type = 'media_play' THEN 1 END) AS plays,
					COUNT(CASE WHEN event_type = 'product_view' THEN 1 END) AS product_views,
					COUNT(CASE WHEN event_type = 'purchase' THEN 1 END) AS purchases
				FROM {$table}
				WHERE creator_id = %d AND occurred_at >= %s AND occurred_at < %s",
				$creator_id,
				$previous_start,
				$previous_end
			),
			ARRAY_A
		);

		return array(
			'days'               => $days,
			'views'              => $views,
			'visitors'           => $visitors,
			'sessions'           => $sessions,
			'returning_visitors' => $returning,
			'return_rate'        => $visitors ? round( ( $returning / $visitors ) * 100, 1 ) : 0,
			'engagement_seconds' => $sessions ? (int) round( ( $engagement_ms / 1000 ) / $sessions ) : 0,
			'sources'            => is_array( $sources ) ? $sources : array(),
			'media_plays'        => $media_plays,
			'unique_listeners'   => $unique_listeners,
			'media_completes'    => $media_completes,
			'completion_rate'    => $media_plays ? round( ( $media_completes / $media_plays ) * 100, 1 ) : 0,
			'top_media'          => is_array( $top_media ) ? $top_media : array(),
			'product_views'      => $product_views,
			'add_to_carts'       => $add_to_carts,
			'purchases'          => $purchases,
			'conversion_rate'    => $product_views ? round( ( $purchases / $product_views ) * 100, 1 ) : 0,
			'revenue'            => $revenue,
			'top_products'       => is_array( $top_products ) ? $top_products : array(),
			'trend'              => $trend,
			'previous'           => array(
				'views'         => isset( $previous['views'] ) ? (int) $previous['views'] : 0,
				'visitors'      => isset( $previous['visitors'] ) ? (int) $previous['visitors'] : 0,
				'plays'         => isset( $previous['plays'] ) ? (int) $previous['plays'] : 0,
				'product_views' => isset( $previous['product_views'] ) ? (int) $previous['product_views'] : 0,
				'purchases'     => isset( $previous['purchases'] ) ? (int) $previous['purchases'] : 0,
			),
		);
	}

	public static function format_duration( $seconds ) {
		$seconds = max( 0, absint( $seconds ) );
		$minutes = floor( $seconds / 60 );
		$remain  = $seconds % 60;
		return sprintf( '%d:%02d', $minutes, $remain );
	}

	private static function selected_range() {
		$allowed = array( 7, 30, 90, 365 );
		$days = isset( $_GET['nfinite_analytics_range'] ) ? absint( wp_unslash( $_GET['nfinite_analytics_range'] ) ) : 30;
		return in_array( $days, $allowed, true ) ? $days : 30;
	}

	private static function range_label( $days ) {
		if ( 365 === (int) $days ) { return __( 'Last 12 months', 'nfinite-creators' ); }
		return sprintf( __( 'Last %d days', 'nfinite-creators' ), (int) $days );
	}

	private static function percent_change( $current, $previous ) {
		$current = (float) $current;
		$previous = (float) $previous;
		if ( $previous <= 0 ) { return $current > 0 ? null : 0.0; }
		return round( ( ( $current - $previous ) / $previous ) * 100, 1 );
	}

	private static function change_badge( $current, $previous ) {
		$change = self::percent_change( $current, $previous );
		if ( null === $change ) { return '<span class="nfinite-analytics-change is-new">' . esc_html__( 'New activity', 'nfinite-creators' ) . '</span>'; }
		$class = $change > 0 ? 'is-up' : ( $change < 0 ? 'is-down' : 'is-flat' );
		$prefix = $change > 0 ? '↑ ' : ( $change < 0 ? '↓ ' : '→ ' );
		return '<span class="nfinite-analytics-change ' . esc_attr( $class ) . '">' . esc_html( $prefix . number_format_i18n( abs( $change ), 1 ) . '%' ) . '</span>';
	}

	private static function activity_chart( $trend ) {
		$trend = is_array( $trend ) ? $trend : array();
		if ( ! $trend ) { return ''; }
		$max = 1;
		foreach ( $trend as $row ) { $max = max( $max, (int) $row['views'], (int) $row['plays'], (int) $row['product_views'] ); }
		$width = 720; $height = 190; $pad = 12; $count = count( $trend );
		$build = static function( $key ) use ( $trend, $max, $width, $height, $pad, $count ) {
			$points = array();
			foreach ( $trend as $i => $row ) {
				$x = $count > 1 ? $pad + ( ( $width - ( 2 * $pad ) ) * $i / ( $count - 1 ) ) : $width / 2;
				$y = $height - $pad - ( ( $height - ( 2 * $pad ) ) * (int) $row[ $key ] / $max );
				$points[] = round( $x, 2 ) . ',' . round( $y, 2 );
			}
			return implode( ' ', $points );
		};
		ob_start(); ?>
		<div class="nfinite-analytics-chart-wrap" aria-label="<?php esc_attr_e( 'Activity trend chart', 'nfinite-creators' ); ?>">
			<svg class="nfinite-analytics-chart" viewBox="0 0 <?php echo esc_attr( $width ); ?> <?php echo esc_attr( $height ); ?>" role="img" preserveAspectRatio="none">
				<line x1="12" y1="178" x2="708" y2="178" class="grid"/><line x1="12" y1="95" x2="708" y2="95" class="grid"/><line x1="12" y1="12" x2="708" y2="12" class="grid"/>
				<polyline points="<?php echo esc_attr( $build( 'views' ) ); ?>" class="series series-views"/><polyline points="<?php echo esc_attr( $build( 'plays' ) ); ?>" class="series series-plays"/><polyline points="<?php echo esc_attr( $build( 'product_views' ) ); ?>" class="series series-products"/>
			</svg>
		</div>
		<?php return ob_get_clean();
	}


	private static function money_minor( $minor, $currency = 'USD' ) {
		$major = class_exists( 'Nfinite_Creators_Payments' ) ? Nfinite_Creators_Payments::from_minor( (int) $minor, $currency ) : ( (int) $minor / 100 );
		return function_exists( 'wc_price' ) ? wc_price( $major, array( 'currency' => $currency ) ) : '$' . number_format_i18n( $major, 2 );
	}

	private static function earnings_summary( $creator_id ) {
		$rows = class_exists( 'Nfinite_Creators_Payments' ) ? Nfinite_Creators_Payments::creator_ledger( $creator_id, 250 ) : array();
		$out = array(
			'provisional' => 0, 'finalized' => 0, 'paid' => 0, 'reversed' => 0, 'currency' => 'USD',
			'sources' => array(), 'statements' => array(), 'payouts' => array(),
		);
		foreach ( $rows as $row ) {
			$currency = ! empty( $row['currency'] ) ? strtoupper( $row['currency'] ) : 'USD';
			$out['currency'] = $currency;
			$amount = (int) ( $row['creator_amount'] ?? 0 );
			$reversed = (int) ( $row['reversed_amount'] ?? 0 );
			$type = sanitize_key( $row['source_type'] ?? 'adjustment' );
			if ( ! isset( $out['sources'][ $type ] ) ) { $out['sources'][ $type ] = array( 'amount' => 0, 'count' => 0 ); }
			$out['sources'][ $type ]['amount'] += max( 0, $amount - $reversed );
			$out['sources'][ $type ]['count']++;
			if ( ! empty( $row['is_provisional'] ) || 'provisional' === ( $row['earning_status'] ?? '' ) ) { $out['provisional'] += max( 0, $amount - $reversed ); }
			else { $out['finalized'] += max( 0, $amount - $reversed ); }
			if ( 'transferred' === ( $row['payout_status'] ?? '' ) || 'transferred' === ( $row['status'] ?? '' ) ) { $out['paid'] += max( 0, $amount - $reversed ); }
			$out['reversed'] += $reversed;
			if ( in_array( $type, array( 'music_streaming', 'video_engagement' ), true ) && ! empty( $row['accounting_period'] ) ) {
				$out['statements'][] = $row;
			}
			if ( ! empty( $row['transfer_id'] ) || in_array( ( $row['payout_status'] ?? '' ), array( 'transferred', 'failed', 'blocked', 'reversed' ), true ) ) {
				$out['payouts'][] = $row;
			}
		}
		uasort( $out['sources'], static function( $a, $b ) { return (int) $b['amount'] <=> (int) $a['amount']; } );
		$out['statements'] = array_slice( $out['statements'], 0, 8 );
		$out['payouts'] = array_slice( $out['payouts'], 0, 8 );
		return $out;
	}

	private static function source_label( $type ) {
		$labels = array(
			'music_streaming' => __( 'Music streaming', 'nfinite-creators' ), 'beat_sale' => __( 'Beat sales', 'nfinite-creators' ),
			'product_sale' => __( 'Product sales', 'nfinite-creators' ), 'service_sale' => __( 'Services', 'nfinite-creators' ),
			'video_engagement' => __( 'Video engagement', 'nfinite-creators' ), 'membership' => __( 'Memberships', 'nfinite-creators' ),
			'distribution' => __( 'Distribution', 'nfinite-creators' ), 'event' => __( 'Events', 'nfinite-creators' ),
			'sponsorship' => __( 'Sponsorships', 'nfinite-creators' ), 'affiliate' => __( 'Affiliate', 'nfinite-creators' ),
			'editorial' => __( 'Editorial', 'nfinite-creators' ), 'adjustment' => __( 'Adjustments', 'nfinite-creators' ),
		);
		return $labels[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );
	}

	public static function studio_panel( $creator_id ) {
		$creator_id = absint( $creator_id );
		$days       = self::selected_range();
		$summary    = self::summary( $creator_id, $days );
		$qualified  = class_exists( 'Nfinite_Creators_Engagement' ) ? Nfinite_Creators_Engagement::creator_summary( $creator_id, $days ) : array( 'raw_plays'=>0, 'qualified_streams'=>0, 'qualified_listeners'=>0, 'listening_ms'=>0, 'avg_completion'=>0, 'skips'=>0, 'flagged_plays'=>0, 'surfaces'=>array(), 'top_media'=>array() );
		$previous   = $summary['previous'];
		$earnings   = self::earnings_summary( $creator_id );
		$streaming  = class_exists( 'Nfinite_Creators_Streaming_Earnings' ) ? Nfinite_Creators_Streaming_Earnings::creator_streaming_summary( $creator_id ) : array( 'period'=>'', 'qualified_streams'=>0, 'amount_minor'=>0, 'status'=>'none', 'allocations'=>array() );
		$video_quality = class_exists( 'Nfinite_Creators_Video_Engagement' ) ? Nfinite_Creators_Video_Engagement::creator_summary( $creator_id ) : array( 'raw_sessions'=>0, 'qualified_views'=>0, 'qualified_viewers'=>0, 'watched_ms'=>0, 'avg_completion'=>0, 'flagged'=>0 );
		$video_earnings = class_exists( 'Nfinite_Creators_Video_Earnings' ) ? Nfinite_Creators_Video_Earnings::creator_summary( $creator_id ) : array( 'period'=>'', 'qualified_views'=>0, 'watch_ms'=>0, 'amount_minor'=>0, 'status'=>'none' );
		$range_url  = remove_query_arg( 'nfinite_analytics_range' );
		$source_max = 1;
		foreach ( $summary['sources'] as $source ) { $source_max = max( $source_max, (int) $source['views'] ); }

		ob_start(); ?>
		<div class="nfinite-analytics-panel">
			<div class="nfinite-form-section__head"><span>◎</span><div><h3><?php esc_html_e( 'Analytics', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Understand your audience, qualified listening, discovery, earnings, statements, and payouts in one place.', 'nfinite-creators' ); ?></p></div></div>

			<div class="nfinite-analytics-toolbar"><div><strong><?php echo esc_html( self::range_label( $days ) ); ?></strong><span><?php esc_html_e( 'Compared with the previous equal period', 'nfinite-creators' ); ?></span></div><nav class="nfinite-analytics-ranges" aria-label="<?php esc_attr_e( 'Analytics date range', 'nfinite-creators' ); ?>"><?php foreach ( array( 7 => '7D', 30 => '30D', 90 => '90D', 365 => '12M' ) as $range => $label ) : ?><a class="<?php echo $days === $range ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'nfinite_analytics_range', $range, $range_url ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></nav></div>

			<div class="nfinite-analytics-cards nfinite-analytics-cards--overview">
				<div class="nfinite-analytics-card"><div class="nfinite-analytics-card__label"><span><?php esc_html_e( 'Profile Views', 'nfinite-creators' ); ?></span><?php echo wp_kses_post( self::change_badge( $summary['views'], $previous['views'] ) ); ?></div><strong><?php echo esc_html( number_format_i18n( $summary['views'] ) ); ?></strong><small><?php esc_html_e( 'Total profile loads', 'nfinite-creators' ); ?></small></div>
				<div class="nfinite-analytics-card"><div class="nfinite-analytics-card__label"><span><?php esc_html_e( 'Unique Visitors', 'nfinite-creators' ); ?></span><?php echo wp_kses_post( self::change_badge( $summary['visitors'], $previous['visitors'] ) ); ?></div><strong><?php echo esc_html( number_format_i18n( $summary['visitors'] ) ); ?></strong><small><?php echo esc_html( $summary['return_rate'] ); ?>% <?php esc_html_e( 'return rate', 'nfinite-creators' ); ?></small></div>
				<div class="nfinite-analytics-card"><div class="nfinite-analytics-card__label"><span><?php esc_html_e( 'Media Plays', 'nfinite-creators' ); ?></span><?php echo wp_kses_post( self::change_badge( $summary['media_plays'], $previous['plays'] ) ); ?></div><strong><?php echo esc_html( number_format_i18n( $summary['media_plays'] ) ); ?></strong><small><?php echo esc_html( number_format_i18n( $summary['unique_listeners'] ) ); ?> <?php esc_html_e( 'unique listeners', 'nfinite-creators' ); ?></small></div>
				<div class="nfinite-analytics-card"><div class="nfinite-analytics-card__label"><span><?php esc_html_e( 'Purchases', 'nfinite-creators' ); ?></span><?php echo wp_kses_post( self::change_badge( $summary['purchases'], $previous['purchases'] ) ); ?></div><strong><?php echo esc_html( number_format_i18n( $summary['purchases'] ) ); ?></strong><small><?php echo esc_html( $summary['conversion_rate'] ); ?>% <?php esc_html_e( 'view-to-purchase', 'nfinite-creators' ); ?></small></div>
			</div>

			<section class="nfinite-analytics-visual"><div class="nfinite-analytics-section-head nfinite-analytics-section-head--split"><div><h4><?php esc_html_e( 'Activity', 'nfinite-creators' ); ?></h4><p><?php esc_html_e( 'Profile views, meaningful media plays, and product views over time.', 'nfinite-creators' ); ?></p></div><div class="nfinite-analytics-legend"><span class="views"><?php esc_html_e( 'Views', 'nfinite-creators' ); ?></span><span class="plays"><?php esc_html_e( 'Plays', 'nfinite-creators' ); ?></span><span class="products"><?php esc_html_e( 'Product views', 'nfinite-creators' ); ?></span></div></div><?php echo self::activity_chart( $summary['trend'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>

			<div class="nfinite-analytics-grid-2">
				<section class="nfinite-analytics-block"><div class="nfinite-analytics-section-head"><div><h4><?php esc_html_e( 'Audience Quality', 'nfinite-creators' ); ?></h4><p><?php esc_html_e( 'A quick read on repeat attention and active engagement.', 'nfinite-creators' ); ?></p></div></div><div class="nfinite-analytics-mini-grid"><div><span><?php esc_html_e( 'Returning Visitors', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( number_format_i18n( $summary['returning_visitors'] ) ); ?></strong><small><?php echo esc_html( $summary['return_rate'] ); ?>%</small></div><div><span><?php esc_html_e( 'Avg. Engaged Time', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( self::format_duration( $summary['engagement_seconds'] ) ); ?></strong><small><?php esc_html_e( 'per active session', 'nfinite-creators' ); ?></small></div></div></section>
				<section class="nfinite-analytics-block"><div class="nfinite-analytics-section-head"><div><h4><?php esc_html_e( 'Traffic Sources', 'nfinite-creators' ); ?></h4><p><?php esc_html_e( 'Where profile visitors came from.', 'nfinite-creators' ); ?></p></div></div><?php if ( $summary['sources'] ) : ?><div class="nfinite-analytics-source-bars"><?php foreach ( $summary['sources'] as $source ) : $pct = min( 100, round( ( (int) $source['views'] / $source_max ) * 100, 1 ) ); ?><div class="nfinite-analytics-source-bar"><div><span><?php echo esc_html( ucwords( str_replace( array( '_', '-' ), ' ', $source['source'] ) ) ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $source['views'] ) ); ?></strong></div><i><b style="width:<?php echo esc_attr( $pct ); ?>%"></b></i></div><?php endforeach; ?></div><?php else : ?><div class="nfinite-studio-empty"><strong><?php esc_html_e( 'Traffic sources will appear here', 'nfinite-creators' ); ?></strong></div><?php endif; ?></section>
			</div>

			<section class="nfinite-analytics-block nfinite-qualified-audio">
				<div class="nfinite-analytics-section-head nfinite-analytics-section-head--split"><div><h4><?php esc_html_e( 'Qualified Audio', 'nfinite-creators' ); ?></h4><p><?php esc_html_e( 'Raw starts and qualified streams are measured separately. Qualification requires sustained listening and must pass basic abuse checks.', 'nfinite-creators' ); ?></p></div><div class="nfinite-analytics-inline-stats"><span><strong><?php echo esc_html( number_format_i18n( $qualified['qualified_streams'] ) ); ?></strong> <?php esc_html_e( 'qualified streams', 'nfinite-creators' ); ?></span><span><strong><?php echo esc_html( number_format_i18n( $qualified['qualified_listeners'] ) ); ?></strong> <?php esc_html_e( 'listeners', 'nfinite-creators' ); ?></span></div></div>
				<div class="nfinite-analytics-mini-grid"><div><span><?php esc_html_e( 'Raw Plays', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( number_format_i18n( $qualified['raw_plays'] ) ); ?></strong><small><?php esc_html_e( 'playback sessions started', 'nfinite-creators' ); ?></small></div><div><span><?php esc_html_e( 'Listening Time', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( self::format_duration( (int) round( $qualified['listening_ms'] / 1000 ) ) ); ?></strong><small><?php esc_html_e( 'validated listening', 'nfinite-creators' ); ?></small></div><div><span><?php esc_html_e( 'Avg. Completion', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( $qualified['avg_completion'] ); ?>%</strong><small><?php esc_html_e( 'position-based completion', 'nfinite-creators' ); ?></small></div><div><span><?php esc_html_e( 'Skips', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( number_format_i18n( $qualified['skips'] ) ); ?></strong><small><?php esc_html_e( 'manual track exits before 95%', 'nfinite-creators' ); ?></small></div><div><span><?php esc_html_e( 'Flagged Sessions', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( number_format_i18n( $qualified['flagged_plays'] ) ); ?></strong><small><?php esc_html_e( 'excluded from qualification', 'nfinite-creators' ); ?></small></div></div>
				<?php if ( ! empty( $qualified['surfaces'] ) ) : ?><div class="nfinite-analytics-media-table"><div class="nfinite-analytics-media-row is-head"><span><?php esc_html_e( 'Discovery Surface', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Raw', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Qualified', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Listen Time', 'nfinite-creators' ); ?></span></div><?php foreach ( $qualified['surfaces'] as $surface ) : ?><div class="nfinite-analytics-media-row"><span><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $surface['surface'] ) ) ); ?></strong><small><?php esc_html_e( 'PairOfDice surface', 'nfinite-creators' ); ?></small></span><span><?php echo esc_html( number_format_i18n( (int) $surface['raw_plays'] ) ); ?></span><span><?php echo esc_html( number_format_i18n( (int) $surface['qualified_streams'] ) ); ?></span><span><?php echo esc_html( self::format_duration( (int) round( (int) $surface['listening_ms'] / 1000 ) ) ); ?></span></div><?php endforeach; ?></div><?php endif; ?>
				<?php if ( ! empty( $qualified['top_media'] ) ) : ?><div class="nfinite-analytics-subhead"><strong><?php esc_html_e( 'Top tracks by qualified listening', 'nfinite-creators' ); ?></strong></div><div class="nfinite-analytics-media-table"><div class="nfinite-analytics-media-row is-head"><span><?php esc_html_e( 'Track', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Qualified', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Listeners', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Completion', 'nfinite-creators' ); ?></span></div><?php foreach ( $qualified['top_media'] as $item ) : ?><div class="nfinite-analytics-media-row"><span><strong><?php echo esc_html( $item['title'] ?: __( 'Untitled Track', 'nfinite-creators' ) ); ?></strong><small><?php echo esc_html( ucwords( str_replace( '_', ' ', $item['object_type'] ?: 'audio' ) ) ); ?></small></span><span><?php echo esc_html( number_format_i18n( (int) $item['qualified_streams'] ) ); ?></span><span><?php echo esc_html( number_format_i18n( (int) $item['listeners'] ) ); ?></span><span><?php echo esc_html( $item['completion_rate'] ); ?>%</span></div><?php endforeach; ?></div><?php endif; ?>
			</section>


			<section class="nfinite-analytics-block nfinite-qualified-video">
				<div class="nfinite-analytics-section-head nfinite-analytics-section-head--split"><div><h4><?php esc_html_e( 'Qualified Video', 'nfinite-creators' ); ?></h4><p><?php esc_html_e( 'Qualified watch sessions are measured separately from simple video opens. Revenue share currently uses approved YouTube content and validated watch time.', 'nfinite-creators' ); ?></p></div><div class="nfinite-analytics-inline-stats"><span><strong><?php echo esc_html( number_format_i18n( (int) $video_quality['qualified_views'] ) ); ?></strong> <?php esc_html_e( 'qualified views', 'nfinite-creators' ); ?></span><span><strong><?php echo esc_html( number_format_i18n( (int) $video_quality['qualified_viewers'] ) ); ?></strong> <?php esc_html_e( 'viewers', 'nfinite-creators' ); ?></span></div></div>
				<div class="nfinite-analytics-mini-grid"><div><span><?php esc_html_e( 'Raw Sessions', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $video_quality['raw_sessions'] ) ); ?></strong><small><?php esc_html_e( 'video sessions started', 'nfinite-creators' ); ?></small></div><div><span><?php esc_html_e( 'Watch Time', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( self::format_duration( (int) round( (int) $video_quality['watched_ms'] / 1000 ) ) ); ?></strong><small><?php esc_html_e( 'validated watch time', 'nfinite-creators' ); ?></small></div><div><span><?php esc_html_e( 'Avg. Completion', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( number_format_i18n( (float) $video_quality['avg_completion'], 1 ) ); ?>%</strong><small><?php esc_html_e( 'position-based completion', 'nfinite-creators' ); ?></small></div><div><span><?php esc_html_e( 'Flagged Sessions', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $video_quality['flagged'] ) ); ?></strong><small><?php esc_html_e( 'excluded from revenue share', 'nfinite-creators' ); ?></small></div></div>
			</section>


			<section class="nfinite-analytics-block nfinite-analytics-earnings-v2">
				<div class="nfinite-analytics-section-head nfinite-analytics-section-head--split"><div><h4><?php esc_html_e( 'Creator Earnings', 'nfinite-creators' ); ?></h4><p><?php esc_html_e( 'Estimated, finalized, and paid earnings from the unified Nfinite Creator Earnings ledger.', 'nfinite-creators' ); ?></p></div><div class="nfinite-analytics-revenue"><span><?php esc_html_e( 'Finalized earnings', 'nfinite-creators' ); ?></span><strong><?php echo wp_kses_post( self::money_minor( $earnings['finalized'], $earnings['currency'] ) ); ?></strong></div></div>
				<div class="nfinite-analytics-mini-grid nfinite-analytics-money-grid"><div><span><?php esc_html_e( 'Estimated / Provisional', 'nfinite-creators' ); ?></span><strong><?php echo wp_kses_post( self::money_minor( $earnings['provisional'], $earnings['currency'] ) ); ?></strong><small><?php esc_html_e( 'may change before finalization', 'nfinite-creators' ); ?></small></div><div><span><?php esc_html_e( 'Paid to Stripe', 'nfinite-creators' ); ?></span><strong><?php echo wp_kses_post( self::money_minor( $earnings['paid'], $earnings['currency'] ) ); ?></strong><small><?php esc_html_e( 'successfully transferred', 'nfinite-creators' ); ?></small></div><div><span><?php esc_html_e( 'Current Streaming Period', 'nfinite-creators' ); ?></span><strong><?php echo wp_kses_post( self::money_minor( $streaming['amount_minor'], 'USD' ) ); ?></strong><small><?php echo esc_html( $streaming['period'] . ' · ' . number_format_i18n( $streaming['qualified_streams'] ) . ' eligible streams' ); ?></small></div><div><span><?php esc_html_e( 'Current Video Period', 'nfinite-creators' ); ?></span><strong><?php echo wp_kses_post( self::money_minor( $video_earnings['amount_minor'], 'USD' ) ); ?></strong><small><?php echo esc_html( $video_earnings['period'] . ' · ' . number_format_i18n( $video_earnings['qualified_views'] ) . ' eligible views' ); ?></small></div><div><span><?php esc_html_e( 'Reversed / Refunded', 'nfinite-creators' ); ?></span><strong><?php echo wp_kses_post( self::money_minor( $earnings['reversed'], $earnings['currency'] ) ); ?></strong><small><?php esc_html_e( 'refunds, reversals, and adjustments', 'nfinite-creators' ); ?></small></div></div>
				<?php if ( $earnings['sources'] ) : ?><div class="nfinite-analytics-earnings-sources"><?php foreach ( $earnings['sources'] as $type => $source ) : ?><div><span><?php echo esc_html( self::source_label( $type ) ); ?></span><strong><?php echo wp_kses_post( self::money_minor( $source['amount'], $earnings['currency'] ) ); ?></strong><small><?php echo esc_html( sprintf( _n( '%s ledger entry', '%s ledger entries', (int) $source['count'], 'nfinite-creators' ), number_format_i18n( (int) $source['count'] ) ) ); ?></small></div><?php endforeach; ?></div><?php endif; ?>
			</section>

			<div class="nfinite-analytics-grid-2 nfinite-analytics-financial-grid">
				<section class="nfinite-analytics-block"><div class="nfinite-analytics-section-head"><div><h4><?php esc_html_e( 'Content Statements', 'nfinite-creators' ); ?></h4><p><?php esc_html_e( 'Recent finalized streaming and video revenue-share statements.', 'nfinite-creators' ); ?></p></div></div><?php if ( $earnings['statements'] ) : ?><div class="nfinite-analytics-finance-list"><?php foreach ( $earnings['statements'] as $row ) : ?><div><span><strong><?php echo esc_html( $row['accounting_period'] ); ?></strong><small><?php echo esc_html( ucfirst( $row['earning_status'] ?: 'finalized' ) ); ?></small></span><span><strong><?php echo wp_kses_post( self::money_minor( (int) $row['creator_amount'] - (int) $row['reversed_amount'], $row['currency'] ?: 'USD' ) ); ?></strong><small><?php echo esc_html( ucfirst( str_replace( '_', ' ', $row['payout_status'] ?: 'not scheduled' ) ) ); ?></small></span></div><?php endforeach; ?></div><?php else : ?><div class="nfinite-studio-empty"><strong><?php esc_html_e( 'No finalized content statements yet', 'nfinite-creators' ); ?></strong><p><?php esc_html_e( 'Monthly statements will appear after PairOfDice finalizes an eligible streaming or video period.', 'nfinite-creators' ); ?></p></div><?php endif; ?></section>
				<section class="nfinite-analytics-block"><div class="nfinite-analytics-section-head"><div><h4><?php esc_html_e( 'Payout History', 'nfinite-creators' ); ?></h4><p><?php esc_html_e( 'Recent Stripe transfer and payout states across creator earnings.', 'nfinite-creators' ); ?></p></div></div><?php if ( $earnings['payouts'] ) : ?><div class="nfinite-analytics-finance-list"><?php foreach ( $earnings['payouts'] as $row ) : ?><div><span><strong><?php echo esc_html( self::source_label( $row['source_type'] ?? 'adjustment' ) ); ?></strong><small><?php echo esc_html( $row['transfer_id'] ?: ucfirst( str_replace( '_', ' ', $row['payout_status'] ?: 'pending' ) ) ); ?></small></span><span><strong><?php echo wp_kses_post( self::money_minor( max( 0, (int) $row['creator_amount'] - (int) $row['reversed_amount'] ), $row['currency'] ?: 'USD' ) ); ?></strong><small><?php echo esc_html( ucfirst( str_replace( '_', ' ', $row['payout_status'] ?: $row['status'] ) ) ); ?></small></span></div><?php endforeach; ?></div><?php else : ?><div class="nfinite-studio-empty"><strong><?php esc_html_e( 'No payout activity yet', 'nfinite-creators' ); ?></strong><p><?php esc_html_e( 'Completed, blocked, failed, or reversed transfers will appear here.', 'nfinite-creators' ); ?></p></div><?php endif; ?></section>
			</div>

			<section class="nfinite-analytics-block"><div class="nfinite-analytics-section-head nfinite-analytics-section-head--split"><div><h4><?php esc_html_e( 'Media Performance', 'nfinite-creators' ); ?></h4><p><?php esc_html_e( 'A play counts after five seconds of actual playback.', 'nfinite-creators' ); ?></p></div><div class="nfinite-analytics-inline-stats"><span><strong><?php echo esc_html( number_format_i18n( $summary['unique_listeners'] ) ); ?></strong> <?php esc_html_e( 'listeners', 'nfinite-creators' ); ?></span><span><strong><?php echo esc_html( $summary['completion_rate'] ); ?>%</strong> <?php esc_html_e( 'complete', 'nfinite-creators' ); ?></span></div></div><?php if ( $summary['top_media'] ) : ?><div class="nfinite-analytics-media-table"><div class="nfinite-analytics-media-row is-head"><span><?php esc_html_e( 'Media', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Plays', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Listeners', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Complete', 'nfinite-creators' ); ?></span></div><?php foreach ( $summary['top_media'] as $item ) : ?><div class="nfinite-analytics-media-row"><span><strong><?php echo esc_html( $item['title'] ?: __( 'Untitled Track', 'nfinite-creators' ) ); ?></strong><small><?php echo esc_html( 'beat' === $item['object_type'] ? __( 'Beat', 'nfinite-creators' ) : __( 'Track', 'nfinite-creators' ) ); ?></small></span><span><?php echo esc_html( number_format_i18n( (int) $item['plays'] ) ); ?></span><span><?php echo esc_html( number_format_i18n( (int) $item['listeners'] ) ); ?></span><span><?php echo esc_html( $item['completion_rate'] ); ?>%</span></div><?php endforeach; ?></div><?php else : ?><div class="nfinite-studio-empty"><strong><?php esc_html_e( 'Media analytics are ready', 'nfinite-creators' ); ?></strong><p><?php esc_html_e( 'Your most-played tracks and beats will appear here.', 'nfinite-creators' ); ?></p></div><?php endif; ?></section>

			<section class="nfinite-analytics-block"><div class="nfinite-analytics-section-head nfinite-analytics-section-head--split"><div><h4><?php esc_html_e( 'Commercial Funnel', 'nfinite-creators' ); ?></h4><p><?php esc_html_e( 'See how product attention turns into orders and revenue.', 'nfinite-creators' ); ?></p></div><div class="nfinite-analytics-revenue"><span><?php esc_html_e( 'Revenue', 'nfinite-creators' ); ?></span><strong><?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $summary['revenue'] ) ) : esc_html( number_format_i18n( $summary['revenue'], 2 ) ); ?></strong></div></div><div class="nfinite-analytics-funnel"><div><span><?php esc_html_e( 'Product Views', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( number_format_i18n( $summary['product_views'] ) ); ?></strong></div><b>→</b><div><span><?php esc_html_e( 'Add to Carts', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( number_format_i18n( $summary['add_to_carts'] ) ); ?></strong><small><?php echo $summary['product_views'] ? esc_html( round( ( $summary['add_to_carts'] / $summary['product_views'] ) * 100, 1 ) ) . '%' : '0%'; ?></small></div><b>→</b><div><span><?php esc_html_e( 'Purchases', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( number_format_i18n( $summary['purchases'] ) ); ?></strong><small><?php echo esc_html( $summary['conversion_rate'] ); ?>%</small></div></div><?php if ( $summary['top_products'] ) : ?><div class="nfinite-analytics-media-table nfinite-analytics-product-table"><div class="nfinite-analytics-media-row is-head"><span><?php esc_html_e( 'Top Product', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Views', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Visitors', 'nfinite-creators' ); ?></span><span></span></div><?php foreach ( $summary['top_products'] as $item ) : ?><div class="nfinite-analytics-media-row"><span><strong><?php echo esc_html( $item['title'] ?: __( 'Untitled Product', 'nfinite-creators' ) ); ?></strong><small><?php esc_html_e( 'Product', 'nfinite-creators' ); ?></small></span><span><?php echo esc_html( number_format_i18n( (int) $item['views'] ) ); ?></span><span><?php echo esc_html( number_format_i18n( (int) $item['visitors'] ) ); ?></span><span></span></div><?php endforeach; ?></div><?php endif; ?></section>

			<div class="nfinite-analytics-note"><strong><?php esc_html_e( 'Creator Analytics V2', 'nfinite-creators' ); ?></strong><p><?php esc_html_e( 'Qualified audio and video engagement, discovery performance, provisional and finalized earnings, statements, and payout history now live together in Creator Studio Analytics.', 'nfinite-creators' ); ?></p></div>
		</div>
		<?php return ob_get_clean();
	}

}
