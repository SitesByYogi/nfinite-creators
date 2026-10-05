<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Printful merch intake bridge.
 *
 * V1 intentionally keeps Printful as the fulfillment/configuration system while
 * letting creators request merch from Creator Studio. Admins provision the
 * Printful product through the connected WooCommerce store, then attach the
 * resulting WooCommerce product to the creator/request.
 */
class Nfinite_Creators_Printful {
	const META_KEY = '_nfinite_creator_merch_requests';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_nfinite_printful_attach_product', array( __CLASS__, 'attach_product' ) );
		add_action( 'admin_post_nfinite_printful_mark_pending', array( __CLASS__, 'mark_pending' ) );
		add_action( 'admin_post_nfinite_printful_approve_provision', array( __CLASS__, 'approve_provision' ) );
		add_action( 'admin_post_nfinite_printful_retry_provision', array( __CLASS__, 'retry_provision' ) );
		add_action( 'admin_post_nfinite_printful_save_write_access', array( __CLASS__, 'save_write_access' ) );
		add_action( 'nfinite_printful_sync_provisioned', array( __CLASS__, 'sync_provisioned' ), 10, 2 );
		add_action( 'wp_ajax_nfinite_printful_product', array( __CLASS__, 'ajax_product' ) );
		add_action( 'wp_ajax_nfinite_printful_mockup', array( __CLASS__, 'ajax_mockup' ) );
		add_action( 'wp_ajax_nfinite_printful_mockup_status', array( __CLASS__, 'ajax_mockup_status' ) );
		add_action( 'wp_ajax_nfinite_printful_save_preview', array( __CLASS__, 'ajax_save_preview' ) );
	}


	/** Adapter around Printful's official WooCommerce connection. */
	public static function connection_status() {
		if ( ! class_exists( 'Printful_Integration' ) ) {
			return array( 'connected' => false, 'message' => __( 'Printful for WooCommerce is not active.', 'nfinite-creators' ) );
		}
		try {
			$connected = Printful_Integration::instance()->is_connected();
			return array( 'connected' => (bool) $connected, 'message' => $connected ? __( 'Printful connected', 'nfinite-creators' ) : __( 'Printful is installed but the store connection is not ready.', 'nfinite-creators' ) );
		} catch ( Exception $e ) {
			return array( 'connected' => false, 'message' => __( 'Printful connection could not be verified.', 'nfinite-creators' ) );
		}
	}

	private static function client() {
		if ( ! class_exists( 'Printful_Integration' ) ) { return false; }
		try { return Printful_Integration::instance()->get_client(); } catch ( Exception $e ) { return false; }
	}

	/** Nfinite-owned Printful credential used only for provisioning writes. */
	private static function write_token() {
		if ( defined( 'NFINITE_PRINTFUL_PRIVATE_TOKEN' ) && NFINITE_PRINTFUL_PRIVATE_TOKEN ) {
			return trim( (string) NFINITE_PRINTFUL_PRIVATE_TOKEN );
		}
		return trim( (string) get_option( 'nfinite_printful_private_token', '' ) );
	}

	private static function write_store_id() {
		if ( defined( 'NFINITE_PRINTFUL_STORE_ID' ) && NFINITE_PRINTFUL_STORE_ID ) {
			return trim( (string) NFINITE_PRINTFUL_STORE_ID );
		}
		return trim( (string) get_option( 'nfinite_printful_store_id', '' ) );
	}

	/** Direct Printful request for endpoints that require sync-product write scope. */
	private static function write_request( $method, $endpoint, $body = null ) {
		$token = self::write_token();
		if ( ! $token ) {
			throw new Exception( 'Printful write access is not configured. Add a Printful Private Token with Sync Products read/write permission in Creator Merch Fulfillment.' );
		}
		$headers = array(
			'Authorization' => 'Bearer ' . $token,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
		);
		$store_id = self::write_store_id();
		if ( $store_id ) { $headers['X-PF-Store-Id'] = $store_id; }
		$args = array( 'method' => strtoupper( $method ), 'headers' => $headers, 'timeout' => 30 );
		if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); }
		$response = wp_remote_request( 'https://api.printful.com/' . ltrim( $endpoint, '/' ), $args );
		if ( is_wp_error( $response ) ) { throw new Exception( 'Printful write request failed: ' . $response->get_error_message() ); }
		$code = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$message = '';
			if ( is_array( $decoded ) ) {
				$message = sanitize_text_field( $decoded['error']['message'] ?? $decoded['error']['reason'] ?? $decoded['message'] ?? '' );
			}
			if ( ! $message ) { $message = 'HTTP ' . $code; }
			throw new Exception( 'Printful write API: ' . $message );
		}
		return is_array( $decoded ) && array_key_exists( 'result', $decoded ) ? $decoded['result'] : $decoded;
	}

	/** Verify the saved private token without exposing it back to the browser. */
	private static function write_access_status( $force = false ) {
		if ( ! self::write_token() ) { return array( 'configured' => false, 'valid' => false, 'write' => false, 'message' => __( 'Private token not configured', 'nfinite-creators' ) ); }
		$key = 'nfinite_printful_write_access_status';
		if ( ! $force ) { $cached = get_transient( $key ); if ( is_array( $cached ) ) { return $cached; } }
		try {
			$data = self::write_request( 'GET', 'v2/oauth-scopes' );
			$scopes = array();
			foreach ( (array) ( $data['data'] ?? $data ) as $scope ) {
				$value = is_array( $scope ) ? sanitize_text_field( $scope['value'] ?? '' ) : sanitize_text_field( $scope );
				if ( $value ) { $scopes[] = $value; }
			}
			$write = in_array( 'sync_products', $scopes, true ) || in_array( 'sync_products/write', $scopes, true );
			$status = array( 'configured' => true, 'valid' => true, 'write' => $write, 'scopes' => $scopes, 'message' => $write ? __( 'Printful write access ready', 'nfinite-creators' ) : __( 'Token is valid but Sync Products read/write permission is missing.', 'nfinite-creators' ) );
		} catch ( Exception $e ) {
			$status = array( 'configured' => true, 'valid' => false, 'write' => false, 'message' => sanitize_text_field( $e->getMessage() ) );
		}
		set_transient( $key, $status, 10 * MINUTE_IN_SECONDS );
		return $status;
	}

	public static function save_write_access() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nfinite-creators' ) ); }
		check_admin_referer( 'nfinite_printful_save_write_access' );
		$token = isset( $_POST['private_token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['private_token'] ) ) ) : '';
		$store_id = isset( $_POST['store_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['store_id'] ) ) ) : '';
		if ( $token ) { update_option( 'nfinite_printful_private_token', $token, false ); }
		if ( isset( $_POST['clear_token'] ) && '1' === $_POST['clear_token'] ) { delete_option( 'nfinite_printful_private_token' ); }
		update_option( 'nfinite_printful_store_id', $store_id, false );
		delete_transient( 'nfinite_printful_write_access_status' );
		$status = self::write_access_status( true );
		$url = add_query_arg( array( 'nfinite_printful_access_saved' => '1', 'nfinite_printful_access_ok' => ! empty( $status['write'] ) ? '1' : '0' ), admin_url( 'edit.php?post_type=nfinite_creator&page=nfinite-merch-fulfillment' ) );
		wp_safe_redirect( $url ); exit;
	}

	public static function catalog() {
		$cached = get_transient( 'nfinite_printful_catalog_v2' );
		if ( is_array( $cached ) ) { return $cached; }
		$client = self::client();
		if ( ! $client ) { return array(); }
		try {
			$items = $client->get( 'products' );
			if ( ! is_array( $items ) ) { return array(); }
			$allowed = array( 'shirt', 'tee', 'hoodie', 'sweatshirt', 'hat', 'cap', 'beanie', 'poster', 'tote' );
			$out = array();
			foreach ( $items as $item ) {
				$name = isset( $item['title'] ) ? $item['title'] : ( isset( $item['model'] ) ? $item['model'] : '' );
				$hay = strtolower( $name . ' ' . ( $item['type_name'] ?? '' ) );
				$ok = false; foreach ( $allowed as $word ) { if ( false !== strpos( $hay, $word ) ) { $ok = true; break; } }
				if ( ! $ok || empty( $item['id'] ) ) { continue; }
				$out[] = array( 'id' => absint( $item['id'] ), 'name' => sanitize_text_field( $name ), 'image' => esc_url_raw( $item['image'] ?? '' ), 'type' => sanitize_text_field( $item['type_name'] ?? '' ) );
			}
			set_transient( 'nfinite_printful_catalog_v2', $out, 6 * HOUR_IN_SECONDS );
			return $out;
		} catch ( Exception $e ) { return array(); }
	}


	/** Cache Printful printfile metadata so the creator builder does not repeatedly hit rate limits. */
	private static function printfile_data( $product_id, $force = false ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) { return array(); }
		$key = 'nfinite_printful_printfiles_' . $product_id;
		if ( ! $force ) {
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) { return $cached; }
		}
		$client = self::client();
		if ( ! $client ) { return array(); }
		$data = $client->get( 'mockup-generator/printfiles/' . $product_id );
		if ( is_array( $data ) ) { set_transient( $key, $data, 6 * HOUR_IN_SECONDS ); return $data; }
		return array();
	}

	/** Resolve the correct printfile for a selected variant + placement. */
	private static function resolve_printfile( $printfile_data, $variant_ids, $placement ) {
		$printfile_id = 0;
		$variant_ids = array_map( 'absint', (array) $variant_ids );
		foreach ( (array) ( $printfile_data['variant_printfiles'] ?? array() ) as $mapping ) {
			if ( ! in_array( absint( $mapping['variant_id'] ?? 0 ), $variant_ids, true ) ) { continue; }
			$placements = (array) ( $mapping['placements'] ?? array() );
			if ( isset( $placements[ $placement ] ) ) { $printfile_id = absint( $placements[ $placement ] ); break; }
		}
		if ( ! $printfile_id ) { return array(); }
		foreach ( (array) ( $printfile_data['printfiles'] ?? array() ) as $printfile ) {
			if ( absint( $printfile['printfile_id'] ?? 0 ) === $printfile_id ) { return $printfile; }
		}
		return array();
	}

	/** Build a centered, aspect-ratio-safe default Printful position. */
	private static function default_position( $printfile, $image_url ) {
		$area_w = max( 1, absint( $printfile['width'] ?? 0 ) );
		$area_h = max( 1, absint( $printfile['height'] ?? 0 ) );
		if ( $area_w <= 1 || $area_h <= 1 ) { return array(); }

		$img_w = 1; $img_h = 1;
		$attachment_id = attachment_url_to_postid( $image_url );
		if ( $attachment_id ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				$img_w = max( 1, absint( $meta['width'] ) );
				$img_h = max( 1, absint( $meta['height'] ) );
			}
		}

		// Keep a modest safe margin while preserving the uploaded artwork's ratio.
		$max_w = (int) floor( $area_w * 0.90 );
		$max_h = (int) floor( $area_h * 0.90 );
		$ratio = $img_w / $img_h;
		$width = $max_w;
		$height = (int) round( $width / $ratio );
		if ( $height > $max_h ) {
			$height = $max_h;
			$width = (int) round( $height * $ratio );
		}
		$width = max( 1, min( $width, $area_w ) );
		$height = max( 1, min( $height, $area_h ) );
		return array(
			'area_width'  => $area_w,
			'area_height' => $area_h,
			'width'       => $width,
			'height'      => $height,
			'top'         => max( 0, (int) floor( ( $area_h - $height ) / 2 ) ),
			'left'        => max( 0, (int) floor( ( $area_w - $width ) / 2 ) ),
		);
	}

	private static function is_rate_limit_error( $message ) {
		$message = strtolower( (string) $message );
		return false !== strpos( $message, 'too many requests' ) || false !== strpos( $message, 'try again after' );
	}

	public static function ajax_product() {
		check_ajax_referer( 'nfinite_printful_builder', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => __( 'Please sign in.', 'nfinite-creators' ) ), 403 ); }
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$client = self::client();
		if ( ! $product_id || ! $client ) { wp_send_json_error( array( 'message' => __( 'Printful is unavailable.', 'nfinite-creators' ) ) ); }
		try {
			$data = $client->get( 'products/' . $product_id );
			$variants = array();
			foreach ( (array) ( $data['variants'] ?? array() ) as $v ) {
				$variants[] = array( 'id' => absint( $v['id'] ?? 0 ), 'name' => sanitize_text_field( $v['name'] ?? '' ), 'size' => sanitize_text_field( $v['size'] ?? '' ), 'color' => sanitize_text_field( $v['color'] ?? '' ), 'color_code' => sanitize_hex_color( $v['color_code'] ?? '' ), 'price' => sanitize_text_field( $v['price'] ?? '' ), 'image' => esc_url_raw( $v['image'] ?? '' ) );
			}

			$placements = array();
			try {
				$printfile_data = self::printfile_data( $product_id );
				foreach ( (array) ( $printfile_data['available_placements'] ?? array() ) as $key => $label ) {
					$key = sanitize_key( $key );
					if ( $key ) { $placements[] = array( 'key' => $key, 'label' => sanitize_text_field( $label ) ); }
				}
			} catch ( Exception $placement_error ) {
				// Variant selection should still work even when placement metadata is temporarily unavailable.
			}

			wp_send_json_success( array( 'variants' => $variants, 'placements' => $placements ) );
		} catch ( Exception $e ) { wp_send_json_error( array( 'message' => __( 'Could not load Printful variants.', 'nfinite-creators' ) ) ); }
	}


	/** Create a Printful mockup-generator task for the creator's current configuration. */
	public static function ajax_mockup() {
		check_ajax_referer( 'nfinite_printful_builder', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => __( 'Please sign in.', 'nfinite-creators' ) ), 403 ); }
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variant_ids = isset( $_POST['variant_ids'] ) ? array_values( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['variant_ids'] ) ) ) ) ) ) : array();
		$image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
		$placement = isset( $_POST['placement'] ) ? sanitize_key( wp_unslash( $_POST['placement'] ) ) : 'front';
		$client = self::client();
		if ( ! $client || ! $product_id || ! $variant_ids || ! $image_url ) { wp_send_json_error( array( 'message' => __( 'Choose a product and variants and upload artwork before generating a preview.', 'nfinite-creators' ) ) ); }
		try {
			// Never assume that a catalog product uses the literal placement keys "front" or "back".
			// Printful exposes the supported keys per product (e.g. front, front_large, embroidery_chest_left).
			$printfile_data = self::printfile_data( $product_id );
			$available = array_keys( (array) ( $printfile_data['available_placements'] ?? array() ) );
			$available = array_values( array_filter( array_map( 'sanitize_key', $available ) ) );
			if ( $available ) {
				if ( ! in_array( $placement, $available, true ) ) {
					$matched = '';
					foreach ( $available as $candidate ) {
						if ( false !== strpos( $candidate, $placement ) ) { $matched = $candidate; break; }
					}
					$placement = $matched ? $matched : $available[0];
				}
			}

			$printfile = self::resolve_printfile( $printfile_data, $variant_ids, $placement );
			$position  = self::default_position( $printfile, $image_url );
			if ( ! $position ) { throw new Exception( 'Could not determine the Printful print area for this placement.' ); }

			$task = $client->post( 'mockup-generator/create-task/' . $product_id, array(
				'variant_ids' => array_slice( $variant_ids, 0, 100 ),
				'format'      => 'jpg',
				'width'       => 1000,
				'files'       => array( array(
					'placement' => $placement,
					'image_url' => $image_url,
					'position'  => $position,
				) ),
			) );
			$task_key = sanitize_text_field( $task['task_key'] ?? '' );
			if ( ! $task_key ) { throw new Exception( 'Printful returned no mockup task key.' ); }
			wp_send_json_success( array( 'task_key' => $task_key, 'placement' => $placement ) );
		} catch ( Exception $e ) {
			error_log( '[Nfinite Printful mockup] ' . $e->getMessage() );
			$message = __( 'Printful could not start the mockup preview.', 'nfinite-creators' );
			if ( current_user_can( 'manage_options' ) ) {
				$detail = sanitize_text_field( $e->getMessage() );
				if ( $detail ) { $message .= ' ' . sprintf( __( 'Printful response: %s', 'nfinite-creators' ), $detail ); }
			} else {
				$message .= ' ' . __( 'Please verify the artwork and selected product options.', 'nfinite-creators' );
			}
			wp_send_json_error( array(
				'message' => $message,
				'rate_limited' => self::is_rate_limit_error( $e->getMessage() ),
				'retry_after' => self::is_rate_limit_error( $e->getMessage() ) ? 11 : 0,
			) );
		}
	}

	/** Poll a Printful mockup-generator task without exposing Printful credentials. */
	public static function ajax_mockup_status() {
		check_ajax_referer( 'nfinite_printful_builder', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => __( 'Please sign in.', 'nfinite-creators' ) ), 403 ); }
		$task_key = isset( $_POST['task_key'] ) ? sanitize_text_field( wp_unslash( $_POST['task_key'] ) ) : '';
		$client = self::client();
		if ( ! $client || ! $task_key ) { wp_send_json_error( array( 'message' => __( 'Preview task is unavailable.', 'nfinite-creators' ) ) ); }
		try {
			$data = $client->get( 'mockup-generator/task', array( 'task_key' => $task_key ) );
			$status = sanitize_key( $data['status'] ?? 'pending' );
			$mockups = array();
			foreach ( (array) ( $data['mockups'] ?? array() ) as $mockup ) {
				$url = esc_url_raw( $mockup['mockup_url'] ?? '' );
				if ( $url ) { $mockups[] = array( 'url' => $url, 'variant_ids' => array_map( 'absint', (array) ( $mockup['variant_ids'] ?? array() ) ) ); }
			}
			wp_send_json_success( array( 'status' => $status, 'mockups' => $mockups ) );
		} catch ( Exception $e ) { wp_send_json_error( array( 'message' => __( 'Printful preview generation failed.', 'nfinite-creators' ) ) ); }
	}


	private static function placement_label( $placement ) {
		$placement = sanitize_key( $placement );
		$labels = array( 'front' => __( 'Front print', 'nfinite-creators' ), 'back' => __( 'Back print', 'nfinite-creators' ), 'sleeve_left' => __( 'Left sleeve', 'nfinite-creators' ), 'sleeve_right' => __( 'Right sleeve', 'nfinite-creators' ) );
		if ( isset( $labels[ $placement ] ) ) { return $labels[ $placement ]; }
		return ucwords( str_replace( array( '_', '-' ), ' ', $placement ) );
	}

	/** Persist the selected successful mockup so it survives a page reload. */
	public static function ajax_save_preview() {
		check_ajax_referer( 'nfinite_printful_builder', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => __( 'Please sign in.', 'nfinite-creators' ) ), 403 ); }
		$creator_id = absint( $_POST['creator_id'] ?? 0 );
		$request_id = sanitize_key( wp_unslash( $_POST['request_id'] ?? '' ) );
		$preview_url = esc_url_raw( wp_unslash( $_POST['preview_url'] ?? '' ) );
		if ( ! $creator_id || ! $request_id || ! $preview_url ) { wp_send_json_error( array( 'message' => __( 'Preview could not be saved.', 'nfinite-creators' ) ) ); }
		if ( ! current_user_can( 'manage_options' ) ) {
			$owner = absint( get_post_meta( $creator_id, '_nfinite_creator_owner', true ) );
			if ( $owner && $owner !== get_current_user_id() ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nfinite-creators' ) ), 403 ); }
		}
		$ok = self::update_request( $creator_id, $request_id, function( $request ) use ( $preview_url ) { $request['preview_url'] = $preview_url; $request['preview_updated_at'] = time(); return $request; } );
		$ok ? wp_send_json_success() : wp_send_json_error( array( 'message' => __( 'Preview record was not found.', 'nfinite-creators' ) ) );
	}

	public static function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=nfinite_creator',
			__( 'Merch Fulfillment', 'nfinite-creators' ),
			__( 'Merch Fulfillment', 'nfinite-creators' ),
			'manage_options',
			'nfinite-merch-fulfillment',
			array( __CLASS__, 'admin_page' )
		);
	}

	public static function requests( $creator_id ) {
		$requests = get_post_meta( absint( $creator_id ), self::META_KEY, true );
		return is_array( $requests ) ? array_values( $requests ) : array();
	}

	public static function count( $creator_id ) {
		return count( self::requests( $creator_id ) );
	}

	public static function status_label( $request ) {
		$status = isset( $request['status'] ) ? sanitize_key( $request['status'] ) : 'pending';
		if ( 'ready' === $status ) { return __( 'Live / Printful ready', 'nfinite-creators' ); }
		if ( 'draft' === $status ) { return __( 'Draft', 'nfinite-creators' ); }
		if ( 'provisioning' === $status ) { return __( 'Provisioning', 'nfinite-creators' ); }
		if ( 'failed' === $status ) { return __( 'Provisioning failed', 'nfinite-creators' ); }
		return __( 'Pending review', 'nfinite-creators' );
	}

	public static function studio_panel( $creator_id ) {
		$requests = self::requests( $creator_id );
		$connection = self::connection_status();
		$catalog = $connection['connected'] ? self::catalog() : array();
		$builder_nonce = wp_create_nonce( 'nfinite_printful_builder' );
		ob_start();
		?>
		<div class="nfinite-form-section__head"><span>5</span><div><h3><?php esc_html_e( 'Merch', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Build print-on-demand merch with the connected Printful + WooCommerce store and submit it for PairOfDice review.', 'nfinite-creators' ); ?></p></div></div>
		<div class="nfinite-product-managed-note nfinite-merch-intro">
			<strong><?php esc_html_e( 'No inventory required', 'nfinite-creators' ); ?></strong><span class="nfinite-status-pill <?php echo $connection['connected'] ? 'is-live' : 'is-draft'; ?>"><?php echo esc_html( $connection['message'] ); ?></span>
			<p><?php esc_html_e( 'Choose a real Printful product, configure colors and sizes, upload your design, generate a finished-product preview, and submit it for review. PairOfDice approves products before they go live.', 'nfinite-creators' ); ?></p>
		</div>
		<div class="nfinite-studio-panel-toolbar"><div><strong><?php esc_html_e( 'Your merch', 'nfinite-creators' ); ?></strong><small><?php esc_html_e( 'Preview your finished product before submission. Approved products are fulfilled by Printful with no inventory required.', 'nfinite-creators' ); ?></small></div><button type="button" class="nfinite-btn" data-add-merch><?php esc_html_e( '+ Create Merch', 'nfinite-creators' ); ?></button></div>
		<?php if ( ! $requests ) : ?><div class="nfinite-studio-empty" data-empty-merch><strong><?php esc_html_e( 'No merch yet', 'nfinite-creators' ); ?></strong><p><?php esc_html_e( 'Create your first shirt, hoodie, hat, poster, or other print-on-demand product.', 'nfinite-creators' ); ?></p></div><?php endif; ?>
		<div class="nfinite-studio-list-head nfinite-studio-list-head--product" <?php echo $requests ? '' : 'hidden'; ?> data-merch-list-head><span><?php esc_html_e( 'Merch', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Product', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Price', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Status', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Actions', 'nfinite-creators' ); ?></span></div>
		<div class="nfinite-merch-editor" data-merch-editor>
		<?php foreach ( $requests as $index => $request ) :
			$title = ! empty( $request['title'] ) ? $request['title'] : __( 'Untitled Merch', 'nfinite-creators' );
			$type = ! empty( $request['product_kind'] ) ? $request['product_kind'] : __( 'Merch', 'nfinite-creators' );
			$selected_catalog_id = absint( $request['catalog_product_id'] ?? 0 );
			if ( $selected_catalog_id && $catalog ) {
				foreach ( $catalog as $catalog_item ) {
					if ( absint( $catalog_item['id'] ) === $selected_catalog_id ) { $type = $catalog_item['name']; break; }
				}
			}
			$price = isset( $request['price'] ) && '' !== $request['price'] ? '$' . number_format_i18n( (float) $request['price'], 2 ) : '—';
			$status = self::status_label( $request );
			$ready = isset( $request['status'] ) && 'ready' === $request['status'];
		?>
			<div class="nfinite-product-row" data-merch-row data-creator-id="<?php echo esc_attr( $creator_id ); ?>" data-request-id="<?php echo esc_attr( $request['request_id'] ?? '' ); ?>">
				<div class="nfinite-track-summary nfinite-studio-item-summary nfinite-studio-item-summary--product">
					<div class="nfinite-track-summary__main"><strong data-merch-summary-title><?php echo esc_html( $title ); ?></strong><small><?php echo $ready ? esc_html__( 'Fulfillment connected', 'nfinite-creators' ) : esc_html__( 'Awaiting approval', 'nfinite-creators' ); ?></small></div>
					<span data-merch-summary-kind><?php echo esc_html( $type ); ?></span><b data-merch-summary-price><?php echo esc_html( $price ); ?></b><span class="nfinite-status-pill <?php echo $ready ? 'is-live' : 'is-draft'; ?>"><?php echo esc_html( $status ); ?></span>
					<div class="nfinite-track-summary__actions"><?php if ( $ready && ! empty( $request['product_id'] ) && function_exists( 'wc_get_product' ) ) : $studio_product = wc_get_product( absint( $request['product_id'] ) ); ?><?php if ( $studio_product ) : ?><a class="nfinite-track-edit" href="<?php echo esc_url( get_permalink( $studio_product->get_id() ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View Product', 'nfinite-creators' ); ?></a><?php endif; ?><?php else : ?><button type="button" class="nfinite-track-edit" data-edit-merch><?php esc_html_e( 'Edit', 'nfinite-creators' ); ?></button><button type="button" class="nfinite-track-remove" data-remove-merch><?php esc_html_e( 'Remove', 'nfinite-creators' ); ?></button><?php endif; ?></div>
				</div>
				<div class="nfinite-product-row__body" data-merch-body hidden><div class="nfinite-form-grid">
					<label><span><?php esc_html_e( 'Product Name', 'nfinite-creators' ); ?></span><input type="text" name="merch[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( isset( $request['title'] ) ? $request['title'] : '' ); ?>" <?php disabled( $ready ); ?>></label>
					<label><span><?php esc_html_e( 'Printful Product', 'nfinite-creators' ); ?></span><select name="merch[<?php echo esc_attr( $index ); ?>][catalog_product_id]" data-printful-product data-nonce="<?php echo esc_attr( $builder_nonce ); ?>" <?php disabled( $ready || ! $connection['connected'] ); ?>><option value=""><?php esc_html_e( 'Choose a Printful product', 'nfinite-creators' ); ?></option><?php foreach ( $catalog as $cat ) : ?><option value="<?php echo esc_attr( $cat['id'] ); ?>" <?php selected( absint( $request['catalog_product_id'] ?? 0 ), $cat['id'] ); ?>><?php echo esc_html( $cat['name'] ); ?></option><?php endforeach; ?></select><input type="hidden" name="merch[<?php echo esc_attr( $index ); ?>][product_kind]" value="<?php echo esc_attr( $request['product_kind'] ?? 'Printful Merch' ); ?>"></label>
					<label><span><?php esc_html_e( 'Target Retail Price', 'nfinite-creators' ); ?></span><div class="nfinite-price-input"><b>$</b><input type="number" min="0" step="0.01" name="merch[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( isset( $request['price'] ) ? $request['price'] : '' ); ?>" <?php disabled( $ready ); ?>></div></label>
					<div class="nfinite-form-full nfinite-printful-variants" data-printful-variants data-saved-variants="<?php echo esc_attr( $request['variant_ids'] ?? '' ); ?>"><strong><?php esc_html_e( 'Colors & Sizes', 'nfinite-creators' ); ?></strong><p><small><?php esc_html_e( 'Choose a Printful product to load its real colors and sizes.', 'nfinite-creators' ); ?></small></p><input type="hidden" name="merch[<?php echo esc_attr( $index ); ?>][variant_ids]" value="<?php echo esc_attr( $request['variant_ids'] ?? '' ); ?>" data-printful-variant-values></div>
					<div class="nfinite-form-full nfinite-printful-placement"><strong><?php esc_html_e( 'Print Placement', 'nfinite-creators' ); ?></strong><p><small><?php esc_html_e( 'Choose where this artwork should print. Structured placement is used for previews and future automatic provisioning.', 'nfinite-creators' ); ?></small></p><div class="nfinite-printful-placement__options" data-printful-placement-options data-saved-placement="<?php echo esc_attr( $request['placement'] ?? 'front' ); ?>" data-field-name="merch[<?php echo esc_attr( $index ); ?>][placement]"><label><input type="radio" name="merch[<?php echo esc_attr( $index ); ?>][placement]" value="front" <?php checked( $request['placement'] ?? 'front', 'front' ); ?> <?php disabled( $ready ); ?>> <span><?php esc_html_e( 'Front', 'nfinite-creators' ); ?></span></label><label><input type="radio" name="merch[<?php echo esc_attr( $index ); ?>][placement]" value="back" <?php checked( $request['placement'] ?? '', 'back' ); ?> <?php disabled( $ready ); ?>> <span><?php esc_html_e( 'Back', 'nfinite-creators' ); ?></span></label></div></div>
					<label class="nfinite-form-full"><span><?php esc_html_e( 'Design / Placement Notes', 'nfinite-creators' ); ?></span><textarea name="merch[<?php echo esc_attr( $index ); ?>][notes]" rows="3" placeholder="Optional production notes" <?php disabled( $ready ); ?>><?php echo esc_textarea( isset( $request['notes'] ) ? $request['notes'] : '' ); ?></textarea></label>
					<?php if ( ! empty( $request['design_url'] ) ) : ?><div class="nfinite-form-full"><strong><?php esc_html_e( 'Current design file:', 'nfinite-creators' ); ?></strong> <a href="<?php echo esc_url( $request['design_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View file', 'nfinite-creators' ); ?></a></div><?php endif; ?>
					<?php if ( ! $ready ) : ?><label class="nfinite-form-full"><span><?php esc_html_e( 'Print Design File', 'nfinite-creators' ); ?></span><input type="file" name="merch_design_<?php echo esc_attr( $index ); ?>" accept="image/png,image/jpeg,image/webp,application/pdf"><small><?php esc_html_e( 'High-resolution PNG is preferred. PDF/JPG/WebP are also accepted for review.', 'nfinite-creators' ); ?></small></label><?php endif; ?>
					<div class="nfinite-form-full nfinite-printful-preview" data-printful-preview data-design-url="<?php echo esc_attr( $request['design_url'] ?? '' ); ?>"><div class="nfinite-printful-preview__head"><div><strong><?php esc_html_e( 'Product Preview', 'nfinite-creators' ); ?></strong><small><?php esc_html_e( 'Preview the finished product using your selected color, artwork and placement.', 'nfinite-creators' ); ?></small></div><?php if ( ! $ready ) : ?><button type="button" class="nfinite-btn nfinite-btn--secondary" data-generate-printful-preview data-nonce="<?php echo esc_attr( $builder_nonce ); ?>"><?php echo ! empty( $request['preview_url'] ) ? esc_html__( 'Regenerate Preview', 'nfinite-creators' ) : esc_html__( 'Generate Preview', 'nfinite-creators' ); ?></button><?php endif; ?></div><div class="nfinite-printful-preview__stale" data-printful-preview-stale hidden><?php esc_html_e( 'Your product settings changed. Regenerate the preview to see the latest version.', 'nfinite-creators' ); ?></div><div class="nfinite-printful-preview__stage" data-printful-preview-stage><?php if ( ! empty( $request['preview_url'] ) ) : ?><div class="nfinite-printful-preview__viewer"><div class="nfinite-printful-preview__primary"><img src="<?php echo esc_url( $request['preview_url'] ); ?>" alt="<?php esc_attr_e( 'Printful product preview', 'nfinite-creators' ); ?>"></div><div class="nfinite-printful-preview__details"><strong><?php esc_html_e( 'Saved Printful Preview', 'nfinite-creators' ); ?></strong><span><?php echo esc_html( self::placement_label( $request['placement'] ?? 'front' ) ); ?></span></div></div><p><?php esc_html_e( 'Mockup preview. Actual printed appearance may vary slightly.', 'nfinite-creators' ); ?></p><?php else : ?><p><?php esc_html_e( 'Save your artwork first, then generate a finished-product preview.', 'nfinite-creators' ); ?></p><?php endif; ?></div></div>
					<input type="hidden" name="merch[<?php echo esc_attr( $index ); ?>][request_id]" value="<?php echo esc_attr( isset( $request['request_id'] ) ? $request['request_id'] : '' ); ?>">
					<input type="hidden" name="merch[<?php echo esc_attr( $index ); ?>][status]" value="<?php echo esc_attr( isset( $request['status'] ) ? $request['status'] : 'pending' ); ?>">
					<input type="hidden" name="merch[<?php echo esc_attr( $index ); ?>][product_id]" value="<?php echo esc_attr( isset( $request['product_id'] ) ? absint( $request['product_id'] ) : 0 ); ?>">
					<input type="hidden" name="merch[<?php echo esc_attr( $index ); ?>][design_url]" value="<?php echo esc_attr( isset( $request['design_url'] ) ? $request['design_url'] : '' ); ?>">
				</div></div>
			</div>
		<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function product_kinds() {
		return array( 'T-Shirt', 'Hoodie', 'Sweatshirt', 'Hat', 'Poster', 'Mug', 'Tote Bag', 'Phone Case', 'Other' );
	}

	public static function handle_creator_save( $creator_id ) {
		$submitted = isset( $_POST['merch'] ) && is_array( $_POST['merch'] ) ? wp_unslash( $_POST['merch'] ) : array();
		$existing = self::requests( $creator_id );
		$existing_by_id = array();
		foreach ( $existing as $request ) {
			if ( ! empty( $request['request_id'] ) ) { $existing_by_id[ $request['request_id'] ] = $request; }
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$clean = array();
		foreach ( $submitted as $index => $item ) {
			$request_id = ! empty( $item['request_id'] ) ? sanitize_key( $item['request_id'] ) : 'merch_' . wp_generate_uuid4();
			$previous = isset( $existing_by_id[ $request_id ] ) ? $existing_by_id[ $request_id ] : array();
			$status = isset( $previous['status'] ) ? sanitize_key( $previous['status'] ) : 'pending';
			$product_id = isset( $previous['product_id'] ) ? absint( $previous['product_id'] ) : 0;

			// Fulfillment-ready requests are locked from creator edits so the
			// WooCommerce/Printful configuration cannot drift accidentally.
			if ( 'ready' === $status ) {
				$clean[] = $previous;
				continue;
			}

			$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
			if ( '' === $title ) { continue; }
			$kind = isset( $item['product_kind'] ) ? sanitize_text_field( $item['product_kind'] ) : 'T-Shirt';
			if ( ! in_array( $kind, self::product_kinds(), true ) ) { $kind = 'Other'; }
			$raw_price = isset( $item['price'] ) ? sanitize_text_field( $item['price'] ) : '';
			$price = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $raw_price ) : preg_replace( '/[^0-9.]/', '', $raw_price );
			$design_url = isset( $previous['design_url'] ) ? esc_url_raw( $previous['design_url'] ) : ( isset( $item['design_url'] ) ? esc_url_raw( $item['design_url'] ) : '' );
			$file_key = 'merch_design_' . absint( $index );
			if ( ! empty( $_FILES[ $file_key ]['name'] ) ) {
				$attachment_id = media_handle_upload( $file_key, $creator_id );
				if ( ! is_wp_error( $attachment_id ) ) { $design_url = wp_get_attachment_url( $attachment_id ); }
			}
			$clean[] = array(
				'request_id'   => $request_id,
				'title'        => $title,
				'product_kind' => $kind,
				'catalog_product_id' => absint( $item['catalog_product_id'] ?? 0 ),
				'variant_ids' => sanitize_text_field( $item['variant_ids'] ?? '' ),
				'price'        => $price,
				'colors'       => isset( $item['colors'] ) ? sanitize_text_field( $item['colors'] ) : '',
				'sizes'        => isset( $item['sizes'] ) ? sanitize_text_field( $item['sizes'] ) : '',
				'notes'        => isset( $item['notes'] ) ? sanitize_textarea_field( $item['notes'] ) : '',
				'placement'    => sanitize_key( $item['placement'] ?? 'front' ) ?: 'front',
				'preview_url'  => isset( $previous['preview_url'] ) ? esc_url_raw( $previous['preview_url'] ) : '',
				'design_url'   => $design_url,
				'status'       => 'pending',
				'product_id'   => $product_id,
				'updated_at'   => time(),
			);
		}
		update_post_meta( $creator_id, self::META_KEY, $clean );
	}

	private static function all_requests() {
		$creators = get_posts( array( 'post_type' => 'nfinite_creator', 'post_status' => array( 'publish', 'pending', 'draft', 'private' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$rows = array();
		foreach ( $creators as $creator ) {
			foreach ( self::requests( $creator->ID ) as $request ) {
				$request['creator_id'] = $creator->ID;
				$request['creator_name'] = $creator->post_title;
				$rows[] = $request;
			}
		}
		usort( $rows, function( $a, $b ) { return (int) ( $b['updated_at'] ?? 0 ) <=> (int) ( $a['updated_at'] ?? 0 ); } );
		return $rows;
	}


	private static function provisioning_stage_label( $request ) {
		$stage = sanitize_key( $request['provision_stage'] ?? '' );
		$labels = array(
			'woocommerce_created' => __( 'WooCommerce product created', 'nfinite-creators' ),
			'waiting_for_import' => __( 'Waiting for Printful import', 'nfinite-creators' ),
			'printful_detected' => __( 'Printful product detected', 'nfinite-creators' ),
			'syncing_variants' => __( 'Syncing Printful variants', 'nfinite-creators' ),
			'publishing' => __( 'Publishing product', 'nfinite-creators' ),
			'complete' => __( 'Live / Printful ready', 'nfinite-creators' ),
			'failed' => __( 'Provisioning failed', 'nfinite-creators' ),
		);
		return $labels[ $stage ] ?? __( 'Preparing provisioning', 'nfinite-creators' );
	}

	private static function provisioning_stage_rank( $request ) {
		$stage = sanitize_key( $request['provision_stage'] ?? '' );
		$ranks = array( 'woocommerce_created' => 1, 'waiting_for_import' => 1, 'printful_detected' => 2, 'syncing_variants' => 3, 'publishing' => 4, 'complete' => 5 );
		return absint( $ranks[ $stage ] ?? 0 );
	}

	private static function render_provisioning_progress( $request ) {
		$rank = self::provisioning_stage_rank( $request );
		$failed = 'failed' === sanitize_key( $request['provision_stage'] ?? '' );
		$steps = array(
			1 => __( 'WooCommerce product created', 'nfinite-creators' ),
			2 => __( 'Printful product detected', 'nfinite-creators' ),
			3 => __( 'Variants + artwork synced', 'nfinite-creators' ),
			4 => __( 'Product published', 'nfinite-creators' ),
		);
		echo '<div class="nfinite-printful-progress" style="margin:8px 0 6px;line-height:1.65">';
		foreach ( $steps as $step => $label ) {
			$done = $rank > $step || ( 4 === $step && $rank >= 5 );
			$current = ! $done && ( ( 1 === $step && $rank <= 1 ) || $rank === $step );
			$icon = $done ? '✓' : ( $current && ! $failed ? '…' : '○' );
			$style = $done ? 'color:#008a20' : ( $current ? 'font-weight:600' : 'color:#646970' );
			echo '<div style="' . esc_attr( $style ) . '"><span aria-hidden="true">' . esc_html( $icon ) . '</span> ' . esc_html( $label ) . '</div>';
		}
		echo '</div>';
	}

	/**
	 * Find a WooCommerce product that Printful has imported.
	 * Direct external-id lookup is fastest; list search is the resilience fallback.
	 */
	private static function find_sync_product( $client, $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || ! $client ) { return array(); }
		try {
			$direct = $client->get( 'sync/products/@' . $product_id );
			if ( is_array( $direct ) && ! empty( $direct['id'] ) ) { return $direct; }
			if ( is_array( $direct ) && ! empty( $direct['sync_product']['id'] ) ) { return (array) $direct['sync_product']; }
		} catch ( Exception $e ) {
			// Imported product may not yet resolve via the direct external-id endpoint.
		}

		$offset = 0;
		$limit = 100;
		for ( $page = 0; $page < 5; $page++ ) {
			try {
				$list = $client->get( 'sync/products', array( 'limit' => $limit, 'offset' => $offset ) );
			} catch ( Exception $e ) { break; }
			if ( ! is_array( $list ) || ! $list ) { break; }
			foreach ( $list as $candidate ) {
				if ( ! is_array( $candidate ) ) { continue; }
				$external = (string) ( $candidate['external_id'] ?? $candidate['external_product_id'] ?? '' );
				if ( (string) $product_id === $external && ! empty( $candidate['id'] ) ) { return $candidate; }
			}
			if ( count( $list ) < $limit ) { break; }
			$offset += $limit;
		}
		return array();
	}

	private static function retry_delay_for_attempt( $attempt ) {
		$attempt = max( 1, absint( $attempt ) );
		if ( 1 === $attempt ) { return 30; }
		if ( 2 === $attempt ) { return 60; }
		if ( 3 === $attempt ) { return 120; }
		return 300;
	}

	public static function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$rows = self::all_requests();
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Creator Merch Fulfillment', 'nfinite-creators' ); ?></h1>
		<p><?php esc_html_e( 'Review creator submissions here. Provisioning now reports each WooCommerce → Printful stage, uses resilient Printful import detection, and retries on a graduated schedule before making the product live.', 'nfinite-creators' ); ?></p>
		<?php if ( isset( $_GET['nfinite_merch_updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Merch fulfillment record updated.', 'nfinite-creators' ); ?></p></div><?php endif; ?>
		<?php if ( isset( $_GET['nfinite_merch_provisioning'] ) ) : ?><div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'WooCommerce product created. Nfinite is waiting for Printful to import it and will finish syncing automatically.', 'nfinite-creators' ); ?></p></div><?php endif; ?>
		<?php if ( isset( $_GET['nfinite_merch_error'] ) ) : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['nfinite_merch_error'] ) ) ); ?></p></div><?php endif; ?>
		<?php $write_access = self::write_access_status(); ?>
		<div class="notice <?php echo ! empty( $write_access['write'] ) ? 'notice-success' : 'notice-warning'; ?>" style="padding:10px 12px">
			<p style="margin:0 0 4px"><strong><?php esc_html_e( 'Nfinite Printful write access', 'nfinite-creators' ); ?></strong> — <?php echo esc_html( $write_access['message'] ?? '' ); ?></p>
			<p style="margin:0 0 8px"><?php esc_html_e( 'The official WooCommerce connection can import products, but creator provisioning needs its own Printful Private Token with Sync Products read/write permission to map variants and artwork automatically.', 'nfinite-creators' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
				<input type="hidden" name="action" value="nfinite_printful_save_write_access"><?php wp_nonce_field( 'nfinite_printful_save_write_access' ); ?>
				<label><span style="display:block;font-weight:600"><?php esc_html_e( 'Private Token', 'nfinite-creators' ); ?></span><input type="password" name="private_token" value="" placeholder="<?php echo self::write_token() ? esc_attr__( 'Saved — enter only to replace', 'nfinite-creators' ) : esc_attr__( 'Paste Printful private token', 'nfinite-creators' ); ?>" autocomplete="new-password" style="width:300px"></label>
				<label><span style="display:block;font-weight:600"><?php esc_html_e( 'Store ID (account-level tokens only)', 'nfinite-creators' ); ?></span><input type="text" name="store_id" value="<?php echo esc_attr( self::write_store_id() ); ?>" style="width:180px"></label>
				<button class="button button-primary"><?php esc_html_e( 'Save & Verify Access', 'nfinite-creators' ); ?></button>
				<?php if ( self::write_token() && ! defined( 'NFINITE_PRINTFUL_PRIVATE_TOKEN' ) ) : ?><label style="padding-bottom:5px"><input type="checkbox" name="clear_token" value="1"> <?php esc_html_e( 'Clear saved token', 'nfinite-creators' ); ?></label><?php endif; ?>
			</form>
		</div>
		<div class="notice notice-info" style="padding:10px 12px"><p style="margin:0 0 4px"><strong><?php esc_html_e( 'Printful import readiness', 'nfinite-creators' ); ?></strong></p><p style="margin:0"><?php esc_html_e( 'Keep “Import not synced products” enabled in the connected Printful store. Nfinite will detect the imported WooCommerce product, then use the private token above to finish variant and artwork synchronization.', 'nfinite-creators' ); ?></p></div>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Creator', 'nfinite-creators' ); ?></th><th><?php esc_html_e( 'Request', 'nfinite-creators' ); ?></th><th><?php esc_html_e( 'Design', 'nfinite-creators' ); ?></th><th><?php esc_html_e( 'Preferences', 'nfinite-creators' ); ?></th><th><?php esc_html_e( 'Status / WooCommerce', 'nfinite-creators' ); ?></th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="5"><?php esc_html_e( 'No creator merch requests yet.', 'nfinite-creators' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $rows as $row ) : $ready = isset( $row['status'] ) && 'ready' === $row['status']; ?>
		<tr><td><strong><?php echo esc_html( $row['creator_name'] ); ?></strong><br><a href="<?php echo esc_url( get_edit_post_link( $row['creator_id'] ) ); ?>"><?php esc_html_e( 'Edit creator', 'nfinite-creators' ); ?></a></td>
		<td><strong><?php echo esc_html( $row['title'] ?? '' ); ?></strong><br><?php echo esc_html( $row['product_kind'] ?? '' ); ?> · <?php echo isset( $row['price'] ) && '' !== $row['price'] ? esc_html( '$' . number_format_i18n( (float) $row['price'], 2 ) ) : '—'; ?></td>
		<td><?php if ( ! empty( $row['preview_url'] ) ) : ?><img src="<?php echo esc_url( $row['preview_url'] ); ?>" alt="" style="display:block;width:96px;height:96px;object-fit:contain;background:#fff;border:1px solid #dcdcde;border-radius:6px;margin-bottom:8px"><?php endif; ?><?php if ( ! empty( $row['design_url'] ) ) : ?><a class="button" href="<?php echo esc_url( $row['design_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open design', 'nfinite-creators' ); ?></a><?php else : ?><em><?php esc_html_e( 'No file', 'nfinite-creators' ); ?></em><?php endif; ?></td>
		<td><strong><?php esc_html_e( 'Colors:', 'nfinite-creators' ); ?></strong> <?php echo esc_html( $row['colors'] ?? '—' ); ?><br><strong><?php esc_html_e( 'Sizes:', 'nfinite-creators' ); ?></strong> <?php echo esc_html( $row['sizes'] ?? '—' ); ?><?php if ( ! empty( $row['notes'] ) ) : ?><br><small><?php echo esc_html( $row['notes'] ); ?></small><?php endif; ?></td>
		<td><strong><?php echo esc_html( self::status_label( $row ) ); ?></strong><br>
		<?php if ( $ready && ! empty( $row['product_id'] ) ) : $product = function_exists( 'wc_get_product' ) ? wc_get_product( absint( $row['product_id'] ) ) : false; ?>
			<?php if ( $product ) : ?><a href="<?php echo esc_url( get_edit_post_link( $product->get_id() ) ); ?>">WooCommerce #<?php echo esc_html( $product->get_id() . ' — ' . $product->get_name() ); ?></a> · <a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View product', 'nfinite-creators' ); ?></a><br><?php endif; ?>
			<?php if ( ! empty( $row['printful_sync_product_id'] ) ) : ?><small><?php echo esc_html( sprintf( __( 'Printful sync product #%d', 'nfinite-creators' ), absint( $row['printful_sync_product_id'] ) ) ); ?></small><br><?php endif; ?>
			<small><?php esc_html_e( 'Provisioning complete. WooCommerce is linked to Printful fulfillment.', 'nfinite-creators' ); ?></small>
			<details style="margin-top:8px"><summary><?php esc_html_e( 'Recovery actions', 'nfinite-creators' ); ?></summary><p style="max-width:420px"><small><?php esc_html_e( 'Return to pending keeps the existing WooCommerce and Printful links. Re-approving resumes the same product instead of creating a duplicate.', 'nfinite-creators' ); ?></small></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Return this merch request to pending review? The existing WooCommerce and Printful product links will be preserved.', 'nfinite-creators' ) ); ?>');"><input type="hidden" name="action" value="nfinite_printful_mark_pending"><input type="hidden" name="creator_id" value="<?php echo esc_attr( $row['creator_id'] ); ?>"><input type="hidden" name="request_id" value="<?php echo esc_attr( $row['request_id'] ); ?>"><?php wp_nonce_field( 'nfinite_printful_mark_pending' ); ?><button class="button"><?php esc_html_e( 'Return to pending', 'nfinite-creators' ); ?></button></form></details>
		<?php else : $row_status = sanitize_key( $row['status'] ?? 'pending' ); ?>
			<?php if ( 'provisioning' === $row_status ) : ?>
				<?php if ( ! empty( $row['product_id'] ) ) : ?><a href="<?php echo esc_url( get_edit_post_link( absint( $row['product_id'] ) ) ); ?>">WooCommerce #<?php echo esc_html( absint( $row['product_id'] ) ); ?></a><br><?php endif; ?>
				<div style="margin-top:4px"><strong><?php echo esc_html( self::provisioning_stage_label( $row ) ); ?></strong></div>
				<?php self::render_provisioning_progress( $row ); ?>
				<small><?php echo esc_html( sprintf( __( 'Sync attempt %d.', 'nfinite-creators' ), absint( $row['sync_attempts'] ?? 0 ) ) ); ?><?php if ( ! empty( $row['last_sync_at'] ) ) : ?> <?php echo esc_html( sprintf( __( 'Last checked %s ago.', 'nfinite-creators' ), human_time_diff( absint( $row['last_sync_at'] ), time() ) ) ); ?><?php endif; ?></small>
				<?php if ( ! empty( $row['provision_error'] ) ) : ?><div style="color:#646970;max-width:360px;margin-top:5px"><small><?php echo esc_html( $row['provision_error'] ); ?></small></div><?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px"><input type="hidden" name="action" value="nfinite_printful_retry_provision"><input type="hidden" name="creator_id" value="<?php echo esc_attr( $row['creator_id'] ); ?>"><input type="hidden" name="request_id" value="<?php echo esc_attr( $row['request_id'] ); ?>"><?php wp_nonce_field( 'nfinite_printful_retry_provision' ); ?><button class="button"><?php esc_html_e( 'Check Printful now', 'nfinite-creators' ); ?></button></form>
			<?php else : ?>
				<?php if ( 'failed' === $row_status && ! empty( $row['provision_error'] ) ) : ?><div style="color:#b32d2e;max-width:320px;margin-top:5px"><small><?php echo esc_html( $row['provision_error'] ); ?></small></div><?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px"><input type="hidden" name="action" value="nfinite_printful_approve_provision"><input type="hidden" name="creator_id" value="<?php echo esc_attr( $row['creator_id'] ); ?>"><input type="hidden" name="request_id" value="<?php echo esc_attr( $row['request_id'] ); ?>"><?php wp_nonce_field( 'nfinite_printful_approve_provision' ); ?><button class="button button-primary"><?php esc_html_e( 'Approve & Provision', 'nfinite-creators' ); ?></button></form>
				<details style="margin-top:10px"><summary><?php esc_html_e( 'Manual fallback', 'nfinite-creators' ); ?></summary><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px"><input type="hidden" name="action" value="nfinite_printful_attach_product"><input type="hidden" name="creator_id" value="<?php echo esc_attr( $row['creator_id'] ); ?>"><input type="hidden" name="request_id" value="<?php echo esc_attr( $row['request_id'] ); ?>"><?php wp_nonce_field( 'nfinite_printful_attach_product' ); ?><label><?php esc_html_e( 'Woo product ID', 'nfinite-creators' ); ?> <input type="number" min="1" name="product_id" required style="width:100px"></label> <button class="button"><?php esc_html_e( 'Attach & mark ready', 'nfinite-creators' ); ?></button></form></details>
			<?php endif; ?>
		<?php endif; ?></td></tr>
		<?php endforeach; ?></tbody></table></div>
		<?php if ( array_filter( $rows, function( $r ) { return 'provisioning' === sanitize_key( $r['status'] ?? '' ); } ) ) : ?>
		<script>window.setTimeout(function(){ if(!document.hidden){ window.location.reload(); } }, 15000);</script>
		<?php endif; ?>
		<?php
	}

	private static function update_request( $creator_id, $request_id, $callback ) {
		$requests = self::requests( $creator_id );
		foreach ( $requests as $index => $request ) {
			if ( isset( $request['request_id'] ) && $request_id === $request['request_id'] ) {
				$requests[ $index ] = call_user_func( $callback, $request );
				update_post_meta( $creator_id, self::META_KEY, $requests );
				return true;
			}
		}
		return false;
	}


	private static function request_by_id( $creator_id, $request_id ) {
		foreach ( self::requests( $creator_id ) as $request ) {
			if ( isset( $request['request_id'] ) && $request_id === $request['request_id'] ) { return $request; }
		}
		return array();
	}

	private static function selected_variant_data( $catalog_product_id, $variant_ids ) {
		$client = self::client();
		if ( ! $client ) { throw new Exception( 'Printful is not connected.' ); }
		$data = $client->get( 'products/' . absint( $catalog_product_id ) );
		$wanted = array_values( array_filter( array_map( 'absint', (array) $variant_ids ) ) );
		$out = array();
		foreach ( (array) ( $data['variants'] ?? array() ) as $variant ) {
			$id = absint( $variant['id'] ?? 0 );
			if ( $id && in_array( $id, $wanted, true ) ) {
				$out[] = array(
					'id' => $id,
					'color' => sanitize_text_field( $variant['color'] ?? '' ),
					'size' => sanitize_text_field( $variant['size'] ?? '' ),
					'name' => sanitize_text_field( $variant['name'] ?? '' ),
				);
			}
		}
		return $out;
	}

	private static function sideload_preview( $preview_url, $product_id, $title ) {
		if ( ! $preview_url || has_post_thumbnail( $product_id ) ) { return; }
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = media_sideload_image( $preview_url, $product_id, $title, 'id' );
		if ( ! is_wp_error( $attachment_id ) ) { set_post_thumbnail( $product_id, $attachment_id ); }
	}

	private static function create_woocommerce_product( $creator_id, $request ) {
		if ( ! class_exists( 'WC_Product_Variable' ) || ! class_exists( 'WC_Product_Variation' ) ) { throw new Exception( 'WooCommerce is unavailable.' ); }
		$variant_ids = array_values( array_filter( array_map( 'absint', explode( ',', (string) ( $request['variant_ids'] ?? '' ) ) ) ) );
		if ( empty( $variant_ids ) ) { throw new Exception( 'No Printful variants were selected.' ); }
		$variants = self::selected_variant_data( absint( $request['catalog_product_id'] ?? 0 ), $variant_ids );
		if ( empty( $variants ) ) { throw new Exception( 'Printful did not return the selected variants.' ); }
		$price = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $request['price'] ?? '' ) : (string) ( $request['price'] ?? '' );
		if ( (float) $price <= 0 ) { throw new Exception( 'A valid retail price is required.' ); }

		$product = new WC_Product_Variable();
		$product->set_name( sanitize_text_field( $request['title'] ?? 'Creator Merch' ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_stock_status( 'outofstock' );
		$product->set_description( __( 'Print-on-demand creator merchandise fulfilled by Printful.', 'nfinite-creators' ) );

		$colors = array_values( array_unique( array_filter( wp_list_pluck( $variants, 'color' ) ) ) );
		$sizes = array_values( array_unique( array_filter( wp_list_pluck( $variants, 'size' ) ) ) );
		$attributes = array();
		if ( $colors ) {
			$a = new WC_Product_Attribute(); $a->set_id( 0 ); $a->set_name( 'Color' ); $a->set_options( $colors ); $a->set_visible( true ); $a->set_variation( true ); $attributes[] = $a;
		}
		if ( $sizes ) {
			$a = new WC_Product_Attribute(); $a->set_id( 0 ); $a->set_name( 'Size' ); $a->set_options( $sizes ); $a->set_visible( true ); $a->set_variation( true ); $attributes[] = $a;
		}
		$product->set_attributes( $attributes );
		$product_id = $product->save();
		if ( ! $product_id ) { throw new Exception( 'WooCommerce could not create the product.' ); }

		update_post_meta( $product_id, '_nfinite_creator_id', $creator_id );
		update_post_meta( $product_id, '_nfinite_creator_product_type', 'merch' );
		update_post_meta( $product_id, '_nfinite_printful_merch', '1' );
		update_post_meta( $product_id, '_nfinite_merch_request_id', sanitize_key( $request['request_id'] ?? '' ) );
		update_post_meta( $product_id, '_nfinite_printful_catalog_product_id', absint( $request['catalog_product_id'] ?? 0 ) );
		update_post_meta( $product_id, '_nfinite_printful_placement', sanitize_key( $request['placement'] ?? 'front' ) );

		foreach ( $variants as $variant_data ) {
			$v = new WC_Product_Variation();
			$v->set_parent_id( $product_id );
			$attrs = array();
			if ( $variant_data['color'] ) { $attrs['color'] = $variant_data['color']; }
			if ( $variant_data['size'] ) { $attrs['size'] = $variant_data['size']; }
			$v->set_attributes( $attrs );
			$v->set_regular_price( $price );
			$v->set_price( $price );
			$v->set_status( 'publish' );
			$v->set_manage_stock( false );
			$v->set_stock_status( 'outofstock' );
			$variation_id = $v->save();
			update_post_meta( $variation_id, '_nfinite_printful_variant_id', absint( $variant_data['id'] ) );
		}
		self::sideload_preview( esc_url_raw( $request['preview_url'] ?? '' ), $product_id, $product->get_name() );
		return $product_id;
	}

	private static function schedule_sync( $creator_id, $request_id, $delay = 60 ) {
		$args = array( absint( $creator_id ), sanitize_key( $request_id ) );
		if ( ! wp_next_scheduled( 'nfinite_printful_sync_provisioned', $args ) ) {
			wp_schedule_single_event( time() + max( 10, absint( $delay ) ), 'nfinite_printful_sync_provisioned', $args );
		}
	}

	public static function approve_provision() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nfinite-creators' ) ); }
		check_admin_referer( 'nfinite_printful_approve_provision' );
		$creator_id = absint( $_POST['creator_id'] ?? 0 );
		$request_id = sanitize_key( wp_unslash( $_POST['request_id'] ?? '' ) );
		$request = self::request_by_id( $creator_id, $request_id );
		try {
			if ( ! $creator_id || ! $request_id || empty( $request ) ) { throw new Exception( 'The merch request could not be found.' ); }
			if ( 'provisioning' === sanitize_key( $request['status'] ?? '' ) ) { throw new Exception( 'This merch request is already provisioning. Use Check Printful now instead of starting another provisioning job.' ); }
			if ( 'ready' === sanitize_key( $request['status'] ?? '' ) ) { throw new Exception( 'This merch request is already live and Printful ready.' ); }
			if ( empty( $request['catalog_product_id'] ) || empty( $request['variant_ids'] ) || empty( $request['design_url'] ) ) { throw new Exception( 'Choose a Printful product, variants, and artwork before provisioning.' ); }
			$product_id = absint( $request['product_id'] ?? 0 );
			if ( ! $product_id || ! function_exists( 'wc_get_product' ) || ! wc_get_product( $product_id ) ) { $product_id = self::create_woocommerce_product( $creator_id, $request ); }
			self::update_request( $creator_id, $request_id, function( $item ) use ( $product_id ) {
				$item['product_id'] = $product_id; $item['status'] = 'provisioning'; $item['provision_stage'] = 'woocommerce_created'; $item['sync_attempts'] = 0; $item['last_sync_at'] = 0; $item['provision_error'] = ''; $item['updated_at'] = time(); return $item;
			} );
			self::schedule_sync( $creator_id, $request_id, 30 );
			wp_safe_redirect( add_query_arg( 'nfinite_merch_provisioning', '1', admin_url( 'edit.php?post_type=nfinite_creator&page=nfinite-merch-fulfillment' ) ) ); exit;
		} catch ( Exception $e ) {
			wp_safe_redirect( add_query_arg( 'nfinite_merch_error', rawurlencode( $e->getMessage() ), admin_url( 'edit.php?post_type=nfinite_creator&page=nfinite-merch-fulfillment' ) ) ); exit;
		}
	}

	public static function retry_provision() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nfinite-creators' ) ); }
		check_admin_referer( 'nfinite_printful_retry_provision' );
		$creator_id = absint( $_POST['creator_id'] ?? 0 );
		$request_id = sanitize_key( wp_unslash( $_POST['request_id'] ?? '' ) );
		self::sync_provisioned( $creator_id, $request_id );
		wp_safe_redirect( admin_url( 'edit.php?post_type=nfinite_creator&page=nfinite-merch-fulfillment' ) ); exit;
	}

	public static function sync_provisioned( $creator_id, $request_id ) {
		$creator_id = absint( $creator_id ); $request_id = sanitize_key( $request_id );
		$request = self::request_by_id( $creator_id, $request_id );
		if ( empty( $request ) || 'provisioning' !== sanitize_key( $request['status'] ?? '' ) ) { return; }
		$product_id = absint( $request['product_id'] ?? 0 );
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
		$client = self::client();
		if ( ! $product || ! $client ) { return; }
		$attempts = absint( $request['sync_attempts'] ?? 0 ) + 1;
		self::update_request( $creator_id, $request_id, function( $item ) use ( $attempts ) {
			$item['sync_attempts'] = $attempts;
			$item['last_sync_at'] = time();
			if ( empty( $item['provision_stage'] ) || 'woocommerce_created' === $item['provision_stage'] ) { $item['provision_stage'] = 'waiting_for_import'; }
			$item['updated_at'] = time();
			return $item;
		} );
		try {
			$sync_product = self::find_sync_product( $client, $product_id );
			$sync_product_id = absint( $sync_product['id'] ?? 0 );
			if ( ! $sync_product_id ) {
				throw new Exception( 'Waiting for Printful import. If this persists, enable “Import not synced products” in the connected Printful store.' );
			}

			self::update_request( $creator_id, $request_id, function( $item ) use ( $sync_product_id ) {
				$item['provision_stage'] = 'printful_detected';
				$item['printful_sync_product_id'] = $sync_product_id;
				$item['provision_error'] = '';
				$item['updated_at'] = time();
				return $item;
			} );

			$access = self::write_access_status();
			if ( empty( $access['write'] ) ) {
				throw new Exception( $access['message'] ?? 'Printful Sync Products write access is required.' );
			}

			$placement = sanitize_key( $request['placement'] ?? 'front' ) ?: 'front';
			$design_url = esc_url_raw( $request['design_url'] ?? '' );
			$catalog_product_id = absint( $request['catalog_product_id'] ?? 0 );
			$price = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $request['price'] ?? '' ) : (string) ( $request['price'] ?? '' );
			$printfile_data = self::printfile_data( $catalog_product_id );
			self::update_request( $creator_id, $request_id, function( $item ) { $item['provision_stage'] = 'syncing_variants'; $item['updated_at'] = time(); return $item; } );

			$synced_variations = 0;
			foreach ( $product->get_children() as $variation_id ) {
				$catalog_variant_id = absint( get_post_meta( $variation_id, '_nfinite_printful_variant_id', true ) );
				if ( ! $catalog_variant_id ) { continue; }
				$printfile = self::resolve_printfile( $printfile_data, array( $catalog_variant_id ), $placement );
				$position = self::default_position( $printfile, $design_url );
				$file = array( 'type' => $placement, 'url' => $design_url );
				if ( $position ) { $file['position'] = $position; }
				self::write_request( 'PUT', 'sync/variant/@' . absint( $variation_id ), array(
					'variant_id' => $catalog_variant_id,
					'retail_price' => (string) $price,
					'is_ignored' => false,
					'files' => array( $file ),
				) );
				$synced_variations++;
			}
			if ( ! $synced_variations ) { throw new Exception( 'Printful product was detected, but no WooCommerce variations were available to sync.' ); }

			self::update_request( $creator_id, $request_id, function( $item ) { $item['provision_stage'] = 'publishing'; $item['updated_at'] = time(); return $item; } );
			$product->set_catalog_visibility( 'visible' );
			$product->set_stock_status( 'instock' );
			$product->set_status( 'publish' );
			$product->save();
			foreach ( $product->get_children() as $variation_id ) { $v = wc_get_product( $variation_id ); if ( $v ) { $v->set_stock_status( 'instock' ); $v->save(); } }
			self::update_request( $creator_id, $request_id, function( $item ) use ( $attempts, $sync_product_id ) {
				$item['status'] = 'ready'; $item['provision_stage'] = 'complete'; $item['sync_attempts'] = $attempts; $item['last_sync_at'] = time(); $item['printful_sync_product_id'] = $sync_product_id; $item['provision_error'] = ''; $item['provisioned_at'] = time(); $item['updated_at'] = time(); return $item;
			} );
		} catch ( Exception $e ) {
			$error = sanitize_text_field( $e->getMessage() );
			$is_rate_limit = false !== stripos( $error, 'too many requests' ) || false !== stripos( $error, '429' );
			$max_attempts = 12;
			if ( $attempts >= $max_attempts ) {
				self::update_request( $creator_id, $request_id, function( $item ) use ( $attempts, $error ) { $item['status'] = 'failed'; $item['provision_stage'] = 'failed'; $item['sync_attempts'] = $attempts; $item['last_sync_at'] = time(); $item['provision_error'] = $error; $item['updated_at'] = time(); return $item; } );
			} else {
				self::update_request( $creator_id, $request_id, function( $item ) use ( $attempts, $error ) { $item['sync_attempts'] = $attempts; $item['last_sync_at'] = time(); $item['provision_error'] = $error; $item['updated_at'] = time(); return $item; } );
				$delay = $is_rate_limit ? 30 : self::retry_delay_for_attempt( $attempts );
				self::schedule_sync( $creator_id, $request_id, $delay );
			}
			error_log( '[Nfinite Printful provisioning] ' . $error );
		}
	}

	public static function attach_product() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nfinite-creators' ) ); }
		check_admin_referer( 'nfinite_printful_attach_product' );
		$creator_id = isset( $_POST['creator_id'] ) ? absint( $_POST['creator_id'] ) : 0;
		$request_id = isset( $_POST['request_id'] ) ? sanitize_key( wp_unslash( $_POST['request_id'] ) ) : '';
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
		if ( ! $creator_id || ! $request_id || ! $product ) { wp_die( esc_html__( 'A valid creator, merch request, and WooCommerce product are required.', 'nfinite-creators' ) ); }

		update_post_meta( $product_id, '_nfinite_creator_id', $creator_id );
		update_post_meta( $product_id, '_nfinite_creator_product_type', 'merch' );
		update_post_meta( $product_id, '_nfinite_printful_merch', '1' );
		update_post_meta( $product_id, '_nfinite_merch_request_id', $request_id );

		self::update_request( $creator_id, $request_id, function( $request ) use ( $product_id ) {
			$request['product_id'] = $product_id;
			$request['status'] = 'ready';
			$request['updated_at'] = time();
			return $request;
		} );
		wp_safe_redirect( add_query_arg( 'nfinite_merch_updated', '1', admin_url( 'edit.php?post_type=nfinite_creator&page=nfinite-merch-fulfillment' ) ) );
		exit;
	}

	public static function mark_pending() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'nfinite-creators' ) ); }
		check_admin_referer( 'nfinite_printful_mark_pending' );
		$creator_id = isset( $_POST['creator_id'] ) ? absint( $_POST['creator_id'] ) : 0;
		$request_id = isset( $_POST['request_id'] ) ? sanitize_key( wp_unslash( $_POST['request_id'] ) ) : '';
		self::update_request( $creator_id, $request_id, function( $request ) {
			$request['status'] = 'pending';
			$request['provision_stage'] = 'returned_to_pending';
			$request['provision_error'] = '';
			$request['updated_at'] = time();
			return $request;
		} );
		wp_safe_redirect( add_query_arg( 'nfinite_merch_updated', '1', admin_url( 'edit.php?post_type=nfinite_creator&page=nfinite-merch-fulfillment' ) ) );
		exit;
	}
}
