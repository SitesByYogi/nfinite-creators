<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Music rights declarations, monetization enrollment and distribution-ready metadata.
 *
 * This class intentionally does not calculate earnings. It only determines whether
 * music is enrolled/approved to participate in future qualified-listening earnings.
 */
class Nfinite_Creators_Music_Monetization {
	const TERMS_VERSION = '2026-09-draft';

	public static function init() {
		add_action( 'add_meta_boxes_nfinite_track', array( __CLASS__, 'add_track_box' ) );
		add_action( 'add_meta_boxes_nfinite_release', array( __CLASS__, 'add_release_box' ) );
		add_action( 'add_meta_boxes_nfinite_creator', array( __CLASS__, 'add_creator_review_box' ) );
		add_action( 'save_post_nfinite_track', array( __CLASS__, 'save_track' ), 30, 2 );
		add_action( 'save_post_nfinite_release', array( __CLASS__, 'save_release' ), 30, 2 );
		add_action( 'save_post_nfinite_creator', array( __CLASS__, 'save_creator_review' ), 30, 2 );
		add_action( 'init', array( __CLASS__, 'register_rest_meta' ) );
	}

	public static function statuses() {
		return array(
			'not_enrolled'  => __( 'Not Enrolled', 'nfinite-creators' ),
			'pending_review'=> __( 'Pending Review', 'nfinite-creators' ),
			'monetized'     => __( 'Monetized', 'nfinite-creators' ),
			'ineligible'    => __( 'Ineligible', 'nfinite-creators' ),
			'suspended'     => __( 'Suspended', 'nfinite-creators' ),
		);
	}

	public static function status_label( $status ) {
		$statuses = self::statuses();
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $statuses['not_enrolled'];
	}

	public static function normalize_status( $status ) {
		$status = sanitize_key( (string) $status );
		return array_key_exists( $status, self::statuses() ) ? $status : 'not_enrolled';
	}

	public static function sanitize_isrc( $value ) {
		$value = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $value ) );
		return substr( $value, 0, 12 );
	}

	public static function sanitize_upc( $value ) {
		return substr( preg_replace( '/\D+/', '', (string) $value ), 0, 14 );
	}

	public static function default_legacy_monetization() {
		return array(
			'enrolled'            => '',
			'status'              => 'not_enrolled',
			'master_owner'        => '',
			'primary_artist'      => '',
			'featured_artists'    => '',
			'producers'           => '',
			'songwriters'         => '',
			'isrc'                => '',
			'rights_confirmed'    => '',
			'monetization_terms'  => '',
			'terms_version'       => '',
			'enrolled_at'         => '',
			'reviewed_at'         => '',
			'review_note'         => '',
		);
	}

	/**
	 * Sanitize creator-submitted monetization data while preserving admin-only states.
	 */
	public static function sanitize_legacy_monetization( $submitted, $existing = array() ) {
		$defaults = self::default_legacy_monetization();
		$existing = wp_parse_args( is_array( $existing ) ? $existing : array(), $defaults );
		$submitted = is_array( $submitted ) ? $submitted : array();

		$data = $defaults;
		$data['enrolled']           = ! empty( $submitted['enrolled'] ) ? '1' : '';
		$data['master_owner']       = isset( $submitted['master_owner'] ) ? sanitize_text_field( $submitted['master_owner'] ) : '';
		$data['primary_artist']     = isset( $submitted['primary_artist'] ) ? sanitize_text_field( $submitted['primary_artist'] ) : '';
		$data['featured_artists']   = isset( $submitted['featured_artists'] ) ? sanitize_text_field( $submitted['featured_artists'] ) : '';
		$data['producers']          = isset( $submitted['producers'] ) ? sanitize_text_field( $submitted['producers'] ) : '';
		$data['songwriters']        = isset( $submitted['songwriters'] ) ? sanitize_textarea_field( $submitted['songwriters'] ) : '';
		$data['isrc']               = isset( $submitted['isrc'] ) ? self::sanitize_isrc( $submitted['isrc'] ) : '';
		$data['rights_confirmed']   = ! empty( $submitted['rights_confirmed'] ) ? '1' : '';
		$data['monetization_terms'] = ! empty( $submitted['monetization_terms'] ) ? '1' : '';

		$locked_status = in_array( self::normalize_status( $existing['status'] ), array( 'monetized', 'ineligible', 'suspended' ), true )
			? self::normalize_status( $existing['status'] )
			: '';

		if ( ! $data['enrolled'] ) {
			$data['status'] = 'not_enrolled';
		} elseif ( ! $data['rights_confirmed'] || ! $data['monetization_terms'] || ! $data['master_owner'] ) {
			$data['status'] = 'not_enrolled';
		} elseif ( $locked_status ) {
			$data['status'] = $locked_status;
		} else {
			$data['status'] = 'pending_review';
		}

		$data['terms_version'] = $data['monetization_terms'] ? self::TERMS_VERSION : '';
		$data['enrolled_at']   = $existing['enrolled_at'];
		if ( 'pending_review' === $data['status'] && ! $data['enrolled_at'] ) {
			$data['enrolled_at'] = current_time( 'mysql' );
		}
		$data['reviewed_at'] = $existing['reviewed_at'];
		$data['review_note'] = isset( $existing['review_note'] ) ? sanitize_textarea_field( $existing['review_note'] ) : '';
		return $data;
	}

	public static function legacy_track_is_monetized( $track ) {
		$status = isset( $track['monetization']['status'] ) ? self::normalize_status( $track['monetization']['status'] ) : 'not_enrolled';
		return 'monetized' === $status;
	}

	public static function track_is_monetized( $track_id ) {
		return 'monetized' === self::normalize_status( get_post_meta( $track_id, '_nfinite_track_monetization_status', true ) );
	}

	public static function add_track_box() {
		add_meta_box(
			'nfinite_track_monetization',
			__( 'Rights & Monetization', 'nfinite-creators' ),
			array( __CLASS__, 'render_track_box' ),
			'nfinite_track',
			'normal',
			'high'
		);
	}

	public static function add_release_box() {
		add_meta_box(
			'nfinite_release_distribution_metadata',
			__( 'Rights & Distribution Metadata', 'nfinite-creators' ),
			array( __CLASS__, 'render_release_box' ),
			'nfinite_release',
			'normal',
			'default'
		);
	}

	public static function add_creator_review_box() {
		if ( current_user_can( 'manage_options' ) ) {
			add_meta_box(
				'nfinite_creator_music_monetization_review',
				__( 'Music Monetization Review', 'nfinite-creators' ),
				array( __CLASS__, 'render_creator_review_box' ),
				'nfinite_creator',
				'normal',
				'default'
			);
		}
	}

	public static function render_track_box( $post ) {
		wp_nonce_field( 'nfinite_track_monetization_save', 'nfinite_track_monetization_nonce' );
		$status = self::normalize_status( get_post_meta( $post->ID, '_nfinite_track_monetization_status', true ) );
		$fields = array();
		foreach ( array( 'master_owner','primary_artist','featured_artists','producers','songwriters','isrc' ) as $key ) {
			$fields[$key] = (string) get_post_meta( $post->ID, '_nfinite_track_' . $key, true );
		}
		$creator_id = absint( get_post_meta( $post->ID, '_nfinite_track_creator_id', true ) );
		$creator_name = $creator_id ? get_the_title( $creator_id ) : '';
		$artist = $fields['primary_artist'] ?: ( $creator_name ?: get_the_author_meta( 'display_name', $post->post_author ) );
		$owner = $fields['master_owner'] ?: $artist;
		?>
		<div class="nfinite-monetization-v2" style="max-width:920px">
			<h3><?php esc_html_e( 'Earn from qualified streams', 'nfinite-creators' ); ?></h3>
			<p><?php esc_html_e( 'Request monetization for this track. Approval is required before streams can earn revenue.', 'nfinite-creators' ); ?></p>
			<p><strong><?php esc_html_e( 'Current status:', 'nfinite-creators' ); ?></strong> <?php echo esc_html( self::status_label( $status ) ); ?></p>
			<p><label><strong><?php esc_html_e( 'Master rights holder', 'nfinite-creators' ); ?></strong><br>
			<input class="widefat" type="text" name="_nfinite_track_master_owner" value="<?php echo esc_attr( $owner ); ?>" placeholder="<?php esc_attr_e( 'Name of the person or company that controls this recording', 'nfinite-creators' ); ?>"></label>
			<small><?php esc_html_e( 'Suggested from existing creator information. Confirm or correct before submitting.', 'nfinite-creators' ); ?></small></p>
			<p><label><input type="checkbox" name="_nfinite_track_rights_confirmed" value="1" <?php checked( get_post_meta( $post->ID, '_nfinite_track_rights_confirmed', true ), '1' ); ?>> <?php esc_html_e( 'I confirm that I own or control the rights necessary to monetize this recording.', 'nfinite-creators' ); ?></label></p>
			<p><label><input type="checkbox" name="_nfinite_track_monetization_terms" value="1" <?php checked( get_post_meta( $post->ID, '_nfinite_track_monetization_terms', true ), '1' ); ?>> <?php esc_html_e( 'I have accepted the applicable PairOfDice monetization agreement.', 'nfinite-creators' ); ?></label></p>
			<p><label><input type="checkbox" name="_nfinite_track_request_monetization" value="1" <?php checked( 'pending_review', $status ); ?>> <strong><?php esc_html_e( 'Request monetization review', 'nfinite-creators' ); ?></strong></label></p>
			<p class="description"><?php esc_html_e( 'Save or Update the track to submit. Missing confirmations leave the track unenrolled. No automatic approval or retroactive earnings are created.', 'nfinite-creators' ); ?></p>
			<details style="margin:18px 0"><summary style="cursor:pointer;font-weight:600"><?php esc_html_e( 'Advanced music credits (optional)', 'nfinite-creators' ); ?></summary>
			<div class="nfinite-admin-grid" style="margin-top:12px">
			<?php foreach ( array( 'primary_artist'=>'Primary artist','featured_artists'=>'Featured artists','producers'=>'Producer(s)','isrc'=>'ISRC' ) as $key=>$label ) : ?>
			<div class="nfinite-admin-field"><label><?php echo esc_html( $label ); ?></label><input class="widefat" type="text" name="_nfinite_track_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr( 'primary_artist' === $key ? $artist : $fields[$key] ); ?>"></div>
			<?php endforeach; ?>
			<div class="nfinite-admin-field nfinite-admin-field--full"><label><?php esc_html_e( 'Songwriter / publishing credits', 'nfinite-creators' ); ?></label><textarea class="widefat" rows="3" name="_nfinite_track_songwriters"><?php echo esc_textarea($fields['songwriters']); ?></textarea></div>
			</div></details>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<details><summary style="cursor:pointer;font-weight:600"><?php esc_html_e( 'Administrator review', 'nfinite-creators' ); ?></summary>
			<p><label><?php esc_html_e( 'Admin status', 'nfinite-creators' ); ?><select class="widefat" name="_nfinite_track_monetization_status"><?php foreach ( self::statuses() as $key=>$label ) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($status,$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label></p>
			<p><label><?php esc_html_e( 'Review note', 'nfinite-creators' ); ?><textarea class="widefat" rows="2" name="_nfinite_track_monetization_review_note"><?php echo esc_textarea( get_post_meta($post->ID,'_nfinite_track_monetization_review_note',true) ); ?></textarea></label></p>
			</details>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_release_box( $post ) {
		wp_nonce_field( 'nfinite_release_distribution_save', 'nfinite_release_distribution_nonce' );
		$values = array(
			'upc'          => get_post_meta( $post->ID, '_nfinite_release_upc', true ),
			'label'        => get_post_meta( $post->ID, '_nfinite_release_label', true ),
			'copyright'    => get_post_meta( $post->ID, '_nfinite_release_copyright', true ),
			'master_line'  => get_post_meta( $post->ID, '_nfinite_release_master_line', true ),
			'territories'  => get_post_meta( $post->ID, '_nfinite_release_territories', true ),
			'release_date' => get_post_meta( $post->ID, '_nfinite_release_date', true ),
		);
		?>
		<p class="description"><?php esc_html_e( 'These fields prepare the Nfinite release model for future distribution integrations. They do not send music to DSPs.', 'nfinite-creators' ); ?></p>
		<div class="nfinite-admin-grid">
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'UPC / EAN', 'nfinite-creators' ); ?></label><input class="widefat" name="_nfinite_release_upc" value="<?php echo esc_attr( $values['upc'] ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Label / imprint', 'nfinite-creators' ); ?></label><input class="widefat" name="_nfinite_release_label" value="<?php echo esc_attr( $values['label'] ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( '© copyright line', 'nfinite-creators' ); ?></label><input class="widefat" name="_nfinite_release_copyright" value="<?php echo esc_attr( $values['copyright'] ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( '℗ master ownership line', 'nfinite-creators' ); ?></label><input class="widefat" name="_nfinite_release_master_line" value="<?php echo esc_attr( $values['master_line'] ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Territories', 'nfinite-creators' ); ?></label><input class="widefat" name="_nfinite_release_territories" value="<?php echo esc_attr( $values['territories'] ); ?>" placeholder="Worldwide"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Release date', 'nfinite-creators' ); ?></label><input class="widefat" type="date" name="_nfinite_release_distribution_date" value="<?php echo esc_attr( $values['release_date'] ); ?>"></div>
		</div>
		<?php
	}

	public static function render_creator_review_box( $post ) {
		wp_nonce_field( 'nfinite_creator_monetization_review_save', 'nfinite_creator_monetization_review_nonce' );
		$tracks = get_post_meta( $post->ID, '_nfinite_creator_tracks', true );
		if ( ! is_array( $tracks ) || ! $tracks ) {
			echo '<p>' . esc_html__( 'No Creator Studio audio to review yet.', 'nfinite-creators' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Track</th><th>Rights</th><th>ISRC</th><th>Status</th><th>Review note</th></tr></thead><tbody>';
		foreach ( $tracks as $index => $track ) {
			$uuid = ! empty( $track['uuid'] ) ? sanitize_key( $track['uuid'] ) : 'legacy-' . absint( $index );
			$m = wp_parse_args( isset( $track['monetization'] ) && is_array( $track['monetization'] ) ? $track['monetization'] : array(), self::default_legacy_monetization() );
			echo '<tr>';
			echo '<td><strong>' . esc_html( ! empty( $track['title'] ) ? $track['title'] : __( 'Untitled Track', 'nfinite-creators' ) ) . '</strong><br><small>' . esc_html( $m['master_owner'] ) . '</small></td>';
			echo '<td>' . ( ! empty( $m['rights_confirmed'] ) && ! empty( $m['monetization_terms'] ) ? '✓' : '—' ) . '</td>';
			echo '<td>' . esc_html( $m['isrc'] ) . '</td>';
			echo '<td><select name="nfinite_creator_monetization_status[' . esc_attr( $uuid ) . ']">';
			foreach ( self::statuses() as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '" ' . selected( self::normalize_status( $m['status'] ), $key, false ) . '>' . esc_html( $label ) . '</option>'; }
			echo '</select></td>';
			echo '<td><textarea name="nfinite_creator_monetization_note[' . esc_attr( $uuid ) . ']" rows="2" style="width:100%">' . esc_textarea( $m['review_note'] ) . '</textarea></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function can_save( $post_id, $nonce_name, $action ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return false; }
		if ( wp_is_post_revision( $post_id ) ) { return false; }
		if ( ! isset( $_POST[ $nonce_name ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ), $action ) ) { return false; }
		return current_user_can( 'edit_post', $post_id );
	}

	public static function save_track( $post_id, $post ) {
		if ( ! self::can_save( $post_id, 'nfinite_track_monetization_nonce', 'nfinite_track_monetization_save' ) ) { return; }
		$map = array(
			'_nfinite_track_master_owner'      => 'text',
			'_nfinite_track_primary_artist'    => 'text',
			'_nfinite_track_featured_artists'  => 'text',
			'_nfinite_track_producers'         => 'text',
			'_nfinite_track_songwriters'       => 'textarea',
		);
		foreach ( $map as $key => $kind ) {
			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			update_post_meta( $post_id, $key, 'textarea' === $kind ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ) );
		}
		update_post_meta( $post_id, '_nfinite_track_isrc', isset( $_POST['_nfinite_track_isrc'] ) ? self::sanitize_isrc( wp_unslash( $_POST['_nfinite_track_isrc'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_track_rights_confirmed', isset( $_POST['_nfinite_track_rights_confirmed'] ) ? '1' : '' );
		update_post_meta( $post_id, '_nfinite_track_monetization_terms', isset( $_POST['_nfinite_track_monetization_terms'] ) ? '1' : '' );
		if ( isset( $_POST['_nfinite_track_monetization_terms'] ) ) { update_post_meta( $post_id, '_nfinite_track_monetization_terms_version', self::TERMS_VERSION ); }

		$current = self::normalize_status( get_post_meta( $post_id, '_nfinite_track_monetization_status', true ) );
		if ( current_user_can( 'manage_options' ) && isset( $_POST['_nfinite_track_monetization_status'] ) ) {
			$new_status = self::normalize_status( wp_unslash( $_POST['_nfinite_track_monetization_status'] ) );
			if ( 'not_enrolled' === $new_status && isset( $_POST['_nfinite_track_request_monetization'], $_POST['_nfinite_track_rights_confirmed'], $_POST['_nfinite_track_monetization_terms'] ) && get_post_meta( $post_id, '_nfinite_track_master_owner', true ) ) {
				$new_status = 'pending_review';
			}
			if ( 'monetized' === $new_status && ( ! get_post_meta( $post_id, '_nfinite_track_rights_confirmed', true ) || ! get_post_meta( $post_id, '_nfinite_track_monetization_terms', true ) || ! get_post_meta( $post_id, '_nfinite_track_master_owner', true ) ) ) {
				$new_status = 'pending_review';
			}
			update_post_meta( $post_id, '_nfinite_track_monetization_status', $new_status );
			update_post_meta( $post_id, '_nfinite_track_monetization_reviewed_at', current_time( 'mysql' ) );
			update_post_meta( $post_id, '_nfinite_track_monetization_review_note', isset( $_POST['_nfinite_track_monetization_review_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['_nfinite_track_monetization_review_note'] ) ) : '' );
		} elseif ( ! in_array( $current, array( 'monetized', 'ineligible', 'suspended' ), true ) ) {
			$ready = isset( $_POST['_nfinite_track_request_monetization'], $_POST['_nfinite_track_rights_confirmed'], $_POST['_nfinite_track_monetization_terms'] ) && ! empty( trim( (string) ( $_POST['_nfinite_track_master_owner'] ?? '' ) ) );
			update_post_meta( $post_id, '_nfinite_track_monetization_status', $ready ? 'pending_review' : 'not_enrolled' );
		}
	}

	public static function save_release( $post_id, $post ) {
		if ( ! self::can_save( $post_id, 'nfinite_release_distribution_nonce', 'nfinite_release_distribution_save' ) ) { return; }
		update_post_meta( $post_id, '_nfinite_release_upc', isset( $_POST['_nfinite_release_upc'] ) ? self::sanitize_upc( wp_unslash( $_POST['_nfinite_release_upc'] ) ) : '' );
		foreach ( array( '_nfinite_release_label', '_nfinite_release_copyright', '_nfinite_release_master_line', '_nfinite_release_territories' ) as $key ) {
			update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '' );
		}
		$date = isset( $_POST['_nfinite_release_distribution_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_nfinite_release_distribution_date'] ) ) : '';
		if ( $date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) { $date = ''; }
		update_post_meta( $post_id, '_nfinite_release_date', $date );
	}

	public static function save_creator_review( $post_id, $post ) {
		if ( ! current_user_can( 'manage_options' ) || ! self::can_save( $post_id, 'nfinite_creator_monetization_review_nonce', 'nfinite_creator_monetization_review_save' ) ) { return; }
		$statuses = isset( $_POST['nfinite_creator_monetization_status'] ) && is_array( $_POST['nfinite_creator_monetization_status'] ) ? wp_unslash( $_POST['nfinite_creator_monetization_status'] ) : array();
		$notes = isset( $_POST['nfinite_creator_monetization_note'] ) && is_array( $_POST['nfinite_creator_monetization_note'] ) ? wp_unslash( $_POST['nfinite_creator_monetization_note'] ) : array();
		$tracks = get_post_meta( $post_id, '_nfinite_creator_tracks', true );
		if ( ! is_array( $tracks ) ) { return; }
		foreach ( $tracks as $index => &$track ) {
			$uuid = ! empty( $track['uuid'] ) ? sanitize_key( $track['uuid'] ) : 'legacy-' . absint( $index );
			if ( ! isset( $track['monetization'] ) || ! is_array( $track['monetization'] ) ) { $track['monetization'] = self::default_legacy_monetization(); }
			if ( isset( $statuses[ $uuid ] ) ) {
				$track['monetization']['status'] = self::normalize_status( $statuses[ $uuid ] );
				$track['monetization']['reviewed_at'] = current_time( 'mysql' );
			}
			$track['monetization']['review_note'] = isset( $notes[ $uuid ] ) ? sanitize_textarea_field( $notes[ $uuid ] ) : '';
		}
		unset( $track );
		update_post_meta( $post_id, '_nfinite_creator_tracks', $tracks );
	}

	public static function register_rest_meta() {
		$track_text = array(
			'_nfinite_track_master_owner', '_nfinite_track_primary_artist', '_nfinite_track_featured_artists',
			'_nfinite_track_producers', '_nfinite_track_songwriters', '_nfinite_track_isrc', '_nfinite_track_monetization_status',
		);
		foreach ( $track_text as $key ) {
			register_post_meta( 'nfinite_track', $key, array( 'single' => true, 'type' => 'string', 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => function() { return current_user_can( 'edit_posts' ); } ) );
		}
		foreach ( array( '_nfinite_release_upc', '_nfinite_release_label', '_nfinite_release_copyright', '_nfinite_release_master_line', '_nfinite_release_territories', '_nfinite_release_date' ) as $key ) {
			register_post_meta( 'nfinite_release', $key, array( 'single' => true, 'type' => 'string', 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => function() { return current_user_can( 'edit_posts' ); } ) );
		}
	}
}
