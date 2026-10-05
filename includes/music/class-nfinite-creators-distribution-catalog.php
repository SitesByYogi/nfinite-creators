<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Distribution-ready catalog layer.
 * Stores provider-neutral metadata, validates catalog readiness and exposes a stable export payload
 * for future white-label/B2B distribution integrations. No DSP delivery occurs in this release.
 */
class Nfinite_Creators_Distribution_Catalog {
	const META_STATUS = '_nfinite_distribution_status';
	const META_NOTE   = '_nfinite_distribution_note';

	public static function init() {
		add_action( 'add_meta_boxes_nfinite_release', array( __CLASS__, 'add_release_box' ) );
		add_action( 'add_meta_boxes_nfinite_track', array( __CLASS__, 'add_track_box' ) );
		add_action( 'save_post_nfinite_release', array( __CLASS__, 'save_release' ), 40, 2 );
		add_action( 'save_post_nfinite_track', array( __CLASS__, 'save_track' ), 40, 2 );
		add_filter( 'manage_nfinite_release_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_nfinite_release_posts_custom_column', array( __CLASS__, 'column' ), 20, 2 );
		add_action( 'admin_post_nfinite_distribution_export', array( __CLASS__, 'download_export' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
	}

	public static function statuses() {
		return array(
			'draft'        => __( 'Draft', 'nfinite-creators' ),
			'needs_data'   => __( 'Needs Metadata', 'nfinite-creators' ),
			'ready'        => __( 'Distribution Ready', 'nfinite-creators' ),
			'submitted'    => __( 'Submitted to Provider', 'nfinite-creators' ),
			'delivered'    => __( 'Delivered', 'nfinite-creators' ),
			'takedown'     => __( 'Takedown Requested', 'nfinite-creators' ),
			'error'        => __( 'Provider Error', 'nfinite-creators' ),
		);
	}

	public static function normalize_status( $value ) {
		$value = sanitize_key( (string) $value );
		return isset( self::statuses()[ $value ] ) ? $value : 'draft';
	}

	public static function add_release_box() {
		add_meta_box( 'nfinite_distribution_catalog', __( 'Nfinite Distribution Catalog', 'nfinite-creators' ), array( __CLASS__, 'render_release_box' ), 'nfinite_release', 'normal', 'high' );
	}

	public static function add_track_box() {
		add_meta_box( 'nfinite_distribution_track', __( 'Distribution Metadata', 'nfinite-creators' ), array( __CLASS__, 'render_track_box' ), 'nfinite_track', 'normal', 'default' );
	}

	public static function release_required_fields( $release_id ) {
		$missing = array();
		if ( ! get_the_title( $release_id ) ) { $missing[] = 'Release title'; }
		if ( ! has_post_thumbnail( $release_id ) ) { $missing[] = 'Cover artwork'; }
		if ( ! get_post_meta( $release_id, '_nfinite_release_creator_id', true ) ) { $missing[] = 'Primary creator / artist'; }
		if ( ! get_post_meta( $release_id, '_nfinite_release_date', true ) ) { $missing[] = 'Release date'; }
		if ( ! get_post_meta( $release_id, '_nfinite_release_label', true ) ) { $missing[] = 'Label / imprint'; }
		if ( ! get_post_meta( $release_id, '_nfinite_release_copyright', true ) ) { $missing[] = '© copyright line'; }
		if ( ! get_post_meta( $release_id, '_nfinite_release_master_line', true ) ) { $missing[] = '℗ master line'; }
		if ( ! get_post_meta( $release_id, '_nfinite_release_territories', true ) ) { $missing[] = 'Territories'; }
		$tracks = self::release_track_ids( $release_id );
		if ( empty( $tracks ) ) { $missing[] = 'At least one track'; }
		foreach ( $tracks as $track_id ) {
			$tmissing = self::track_required_fields( $track_id );
			foreach ( $tmissing as $item ) { $missing[] = get_the_title( $track_id ) . ': ' . $item; }
		}
		return array_values( array_unique( $missing ) );
	}

	public static function track_required_fields( $track_id ) {
		$missing = array();
		if ( ! get_the_title( $track_id ) ) { $missing[] = 'Track title'; }
		if ( ! get_post_meta( $track_id, '_nfinite_track_isrc', true ) ) { $missing[] = 'ISRC'; }
		if ( ! get_post_meta( $track_id, '_nfinite_track_primary_artist', true ) ) { $missing[] = 'Primary artist'; }
		if ( ! get_post_meta( $track_id, '_nfinite_track_master_owner', true ) ) { $missing[] = 'Master owner'; }
		if ( ! get_post_meta( $track_id, '_nfinite_track_songwriters', true ) ) { $missing[] = 'Songwriter / publishing credits'; }
		if ( '' === (string) get_post_meta( $track_id, '_nfinite_track_explicit', true ) ) { $missing[] = 'Explicit flag'; }
		return $missing;
	}

	private static function release_track_ids( $release_id ) {
		$ids = get_post_meta( $release_id, '_nfinite_release_track_ids', true );
		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	}

	public static function readiness( $release_id ) {
		$missing = self::release_required_fields( $release_id );
		return array( 'ready' => empty( $missing ), 'missing' => $missing );
	}

	public static function render_release_box( $post ) {
		wp_nonce_field( 'nfinite_distribution_catalog_save', 'nfinite_distribution_catalog_nonce' );
		$readiness = self::readiness( $post->ID );
		$status = self::normalize_status( get_post_meta( $post->ID, self::META_STATUS, true ) );
		$provider = get_post_meta( $post->ID, '_nfinite_distribution_provider', true );
		$provider_release_id = get_post_meta( $post->ID, '_nfinite_distribution_provider_release_id', true );
		$genre = get_post_meta( $post->ID, '_nfinite_distribution_primary_genre', true );
		$secondary = get_post_meta( $post->ID, '_nfinite_distribution_secondary_genre', true );
		$language = get_post_meta( $post->ID, '_nfinite_distribution_language', true );
		$country = get_post_meta( $post->ID, '_nfinite_distribution_country', true );
		$preorder = get_post_meta( $post->ID, '_nfinite_distribution_preorder_date', true );
		$original = get_post_meta( $post->ID, '_nfinite_distribution_original_release_date', true );
		?>
		<p><strong><?php esc_html_e( 'Catalog readiness:', 'nfinite-creators' ); ?></strong> <?php echo $readiness['ready'] ? '<span style="color:#147a31">' . esc_html__( 'Ready', 'nfinite-creators' ) . '</span>' : '<span style="color:#b32d2e">' . esc_html__( 'Needs metadata', 'nfinite-creators' ) . '</span>'; ?></p>
		<?php if ( ! $readiness['ready'] ) : ?><p class="description"><strong><?php esc_html_e( 'Missing:', 'nfinite-creators' ); ?></strong> <?php echo esc_html( implode( '; ', $readiness['missing'] ) ); ?></p><?php endif; ?>
		<div class="nfinite-admin-grid">
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Distribution status', 'nfinite-creators' ); ?></label><select class="widefat" name="_nfinite_distribution_status"><?php foreach ( self::statuses() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Primary genre', 'nfinite-creators' ); ?></label><input class="widefat" name="_nfinite_distribution_primary_genre" value="<?php echo esc_attr( $genre ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Secondary genre', 'nfinite-creators' ); ?></label><input class="widefat" name="_nfinite_distribution_secondary_genre" value="<?php echo esc_attr( $secondary ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Metadata language', 'nfinite-creators' ); ?></label><input class="widefat" name="_nfinite_distribution_language" value="<?php echo esc_attr( $language ); ?>" placeholder="en"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Country of origin', 'nfinite-creators' ); ?></label><input class="widefat" name="_nfinite_distribution_country" value="<?php echo esc_attr( $country ); ?>" placeholder="US"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Original release date', 'nfinite-creators' ); ?></label><input class="widefat" type="date" name="_nfinite_distribution_original_release_date" value="<?php echo esc_attr( $original ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Pre-order date', 'nfinite-creators' ); ?></label><input class="widefat" type="date" name="_nfinite_distribution_preorder_date" value="<?php echo esc_attr( $preorder ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Provider', 'nfinite-creators' ); ?></label><input class="widefat" name="_nfinite_distribution_provider" value="<?php echo esc_attr( $provider ); ?>" placeholder="Future provider"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Provider release ID', 'nfinite-creators' ); ?></label><input class="widefat" name="_nfinite_distribution_provider_release_id" value="<?php echo esc_attr( $provider_release_id ); ?>"></div>
			<div class="nfinite-admin-field nfinite-admin-field--full"><label><?php esc_html_e( 'Internal distribution note', 'nfinite-creators' ); ?></label><textarea class="widefat" rows="2" name="_nfinite_distribution_note"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_NOTE, true ) ); ?></textarea></div>
		</div>
		<p><a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nfinite_distribution_export&release_id=' . $post->ID ), 'nfinite_distribution_export_' . $post->ID ) ); ?>"><?php esc_html_e( 'Download provider-neutral JSON', 'nfinite-creators' ); ?></a></p>
		<p class="description"><?php esc_html_e( '0.43.0 prepares a normalized catalog payload for a future distributor. It does not submit, deliver, takedown, or modify releases at any DSP.', 'nfinite-creators' ); ?></p>
		<?php
	}

	public static function render_track_box( $post ) {
		wp_nonce_field( 'nfinite_distribution_track_save', 'nfinite_distribution_track_nonce' );
		$version = get_post_meta( $post->ID, '_nfinite_distribution_track_version', true );
		$language = get_post_meta( $post->ID, '_nfinite_distribution_track_language', true );
		$explicit = get_post_meta( $post->ID, '_nfinite_track_explicit', true );
		$preview = get_post_meta( $post->ID, '_nfinite_distribution_preview_start', true );
		?>
		<div class="nfinite-admin-grid">
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Version / mix', 'nfinite-creators' ); ?></label><input class="widefat" name="_nfinite_distribution_track_version" value="<?php echo esc_attr( $version ); ?>" placeholder="Radio Edit, Remix, Instrumental"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Lyrics language', 'nfinite-creators' ); ?></label><input class="widefat" name="_nfinite_distribution_track_language" value="<?php echo esc_attr( $language ); ?>" placeholder="en"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Explicit', 'nfinite-creators' ); ?></label><select class="widefat" name="_nfinite_distribution_track_explicit"><option value=""><?php esc_html_e( 'Select', 'nfinite-creators' ); ?></option><option value="0" <?php selected( $explicit, '0' ); ?>><?php esc_html_e( 'Clean / Not Explicit', 'nfinite-creators' ); ?></option><option value="1" <?php selected( $explicit, '1' ); ?>><?php esc_html_e( 'Explicit', 'nfinite-creators' ); ?></option></select></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Preview start (seconds)', 'nfinite-creators' ); ?></label><input class="widefat" type="number" min="0" step="1" name="_nfinite_distribution_preview_start" value="<?php echo esc_attr( $preview ); ?>"></div>
		</div>
		<p class="description"><?php echo esc_html( empty( self::track_required_fields( $post->ID ) ) ? __( 'This track has the core metadata required by the Nfinite distribution catalog.', 'nfinite-creators' ) : __( 'Complete ISRC, artist, master owner, songwriter credits and explicit status before distribution.', 'nfinite-creators' ) ); ?></p>
		<?php
	}

	private static function can_save( $post_id, $nonce_name, $action ) {
		return isset( $_POST[ $nonce_name ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ), $action ) && current_user_can( 'edit_post', $post_id ) && ! ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE );
	}

	public static function save_release( $post_id ) {
		if ( ! self::can_save( $post_id, 'nfinite_distribution_catalog_nonce', 'nfinite_distribution_catalog_save' ) ) { return; }
		$text_fields = array( '_nfinite_distribution_primary_genre', '_nfinite_distribution_secondary_genre', '_nfinite_distribution_language', '_nfinite_distribution_country', '_nfinite_distribution_original_release_date', '_nfinite_distribution_preorder_date', '_nfinite_distribution_provider', '_nfinite_distribution_provider_release_id' );
		foreach ( $text_fields as $key ) { update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '' ); }
		update_post_meta( $post_id, self::META_NOTE, isset( $_POST['_nfinite_distribution_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['_nfinite_distribution_note'] ) ) : '' );
		$status = isset( $_POST['_nfinite_distribution_status'] ) ? self::normalize_status( wp_unslash( $_POST['_nfinite_distribution_status'] ) ) : 'draft';
		if ( 'ready' === $status && ! self::readiness( $post_id )['ready'] ) { $status = 'needs_data'; }
		update_post_meta( $post_id, self::META_STATUS, $status );
		update_post_meta( $post_id, '_nfinite_distribution_readiness_checked_at', current_time( 'mysql' ) );
	}

	public static function save_track( $post_id ) {
		if ( ! self::can_save( $post_id, 'nfinite_distribution_track_nonce', 'nfinite_distribution_track_save' ) ) { return; }
		foreach ( array( '_nfinite_distribution_track_version', '_nfinite_distribution_track_language' ) as $key ) { update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '' ); }
		$explicit = isset( $_POST['_nfinite_distribution_track_explicit'] ) ? (string) wp_unslash( $_POST['_nfinite_distribution_track_explicit'] ) : '';
		if ( in_array( $explicit, array( '0', '1' ), true ) ) { update_post_meta( $post_id, '_nfinite_track_explicit', $explicit ); } else { delete_post_meta( $post_id, '_nfinite_track_explicit' ); }
		$preview = isset( $_POST['_nfinite_distribution_preview_start'] ) ? max( 0, absint( $_POST['_nfinite_distribution_preview_start'] ) ) : 0;
		update_post_meta( $post_id, '_nfinite_distribution_preview_start', $preview );
	}

	public static function columns( $columns ) {
		$columns['nfinite_distribution'] = __( 'Distribution', 'nfinite-creators' );
		return $columns;
	}
	public static function column( $column, $post_id ) {
		if ( 'nfinite_distribution' !== $column ) { return; }
		$readiness = self::readiness( $post_id );
		$status = self::normalize_status( get_post_meta( $post_id, self::META_STATUS, true ) );
		echo esc_html( self::statuses()[ $status ] );
		if ( ! $readiness['ready'] ) { echo '<br><small>' . esc_html( count( $readiness['missing'] ) . ' missing item(s)' ) . '</small>'; }
	}

	public static function payload( $release_id ) {
		$creator_id = absint( get_post_meta( $release_id, '_nfinite_release_creator_id', true ) );
		$types = wp_get_post_terms( $release_id, 'nfinite_release_type', array( 'fields' => 'names' ) );
		$tracks = array();
		foreach ( self::release_track_ids( $release_id ) as $position => $track_id ) {
			$tracks[] = array(
				'id' => $track_id, 'position' => $position + 1, 'title' => get_the_title( $track_id ),
				'isrc' => get_post_meta( $track_id, '_nfinite_track_isrc', true ),
				'primary_artist' => get_post_meta( $track_id, '_nfinite_track_primary_artist', true ),
				'featured_artists' => get_post_meta( $track_id, '_nfinite_track_featured_artists', true ),
				'producers' => get_post_meta( $track_id, '_nfinite_track_producers', true ),
				'songwriters' => get_post_meta( $track_id, '_nfinite_track_songwriters', true ),
				'master_owner' => get_post_meta( $track_id, '_nfinite_track_master_owner', true ),
				'explicit' => '1' === (string) get_post_meta( $track_id, '_nfinite_track_explicit', true ),
				'version' => get_post_meta( $track_id, '_nfinite_distribution_track_version', true ),
				'language' => get_post_meta( $track_id, '_nfinite_distribution_track_language', true ),
				'preview_start_seconds' => absint( get_post_meta( $track_id, '_nfinite_distribution_preview_start', true ) ),
			);
		}
		$readiness = self::readiness( $release_id );
		return array(
			'schema' => 'nfinite.distribution.catalog.v1', 'generated_at' => gmdate( 'c' ),
			'release' => array(
				'id' => $release_id, 'title' => get_the_title( $release_id ), 'release_type' => is_wp_error( $types ) ? array() : $types,
				'artist' => $creator_id ? get_the_title( $creator_id ) : '', 'creator_id' => $creator_id,
				'release_date' => get_post_meta( $release_id, '_nfinite_release_date', true ),
				'original_release_date' => get_post_meta( $release_id, '_nfinite_distribution_original_release_date', true ),
				'preorder_date' => get_post_meta( $release_id, '_nfinite_distribution_preorder_date', true ),
				'upc_ean' => get_post_meta( $release_id, '_nfinite_release_upc', true ), 'label' => get_post_meta( $release_id, '_nfinite_release_label', true ),
				'copyright' => get_post_meta( $release_id, '_nfinite_release_copyright', true ), 'master_line' => get_post_meta( $release_id, '_nfinite_release_master_line', true ),
				'territories' => get_post_meta( $release_id, '_nfinite_release_territories', true ), 'primary_genre' => get_post_meta( $release_id, '_nfinite_distribution_primary_genre', true ),
				'secondary_genre' => get_post_meta( $release_id, '_nfinite_distribution_secondary_genre', true ), 'language' => get_post_meta( $release_id, '_nfinite_distribution_language', true ),
				'country' => get_post_meta( $release_id, '_nfinite_distribution_country', true ), 'artwork_url' => get_the_post_thumbnail_url( $release_id, 'full' ) ?: '',
				'distribution_status' => self::normalize_status( get_post_meta( $release_id, self::META_STATUS, true ) ),
				'provider' => get_post_meta( $release_id, '_nfinite_distribution_provider', true ), 'provider_release_id' => get_post_meta( $release_id, '_nfinite_distribution_provider_release_id', true ),
			),
			'tracks' => $tracks, 'readiness' => $readiness,
		);
	}

	public static function download_export() {
		$release_id = isset( $_GET['release_id'] ) ? absint( $_GET['release_id'] ) : 0;
		if ( ! $release_id || 'nfinite_release' !== get_post_type( $release_id ) || ! current_user_can( 'edit_post', $release_id ) ) { wp_die( esc_html__( 'Invalid release.', 'nfinite-creators' ) ); }
		check_admin_referer( 'nfinite_distribution_export_' . $release_id );
		$payload = self::payload( $release_id );
		nocache_headers(); header( 'Content-Type: application/json; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="nfinite-release-' . $release_id . '-distribution.json"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); exit;
	}

	public static function register_rest() {
		register_rest_route( 'nfinite/v1', '/distribution/releases/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_payload' ), 'permission_callback' => array( __CLASS__, 'rest_permission' ), 'args' => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ) ) );
	}
	public static function rest_permission( $request ) { return current_user_can( 'edit_post', absint( $request['id'] ) ); }
	public static function rest_payload( $request ) {
		$id = absint( $request['id'] ); if ( 'nfinite_release' !== get_post_type( $id ) ) { return new WP_Error( 'nfinite_invalid_release', __( 'Invalid release.', 'nfinite-creators' ), array( 'status' => 404 ) ); }
		return rest_ensure_response( self::payload( $id ) );
	}
}
