<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Events_Meta {
	public static function init() {
		add_action( 'add_meta_boxes_nfinite_event', array( __CLASS__, 'boxes' ) );
		add_action( 'save_post_nfinite_event', array( __CLASS__, 'save' ), 20, 2 );
		add_filter( 'manage_nfinite_event_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_nfinite_event_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	private static function creators() {
		return get_posts( array(
			'post_type'      => 'nfinite_creator',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
	}

	public static function boxes() {
		add_meta_box( 'nfinite_event_details', __( 'Event Details', 'nfinite-creators' ), array( __CLASS__, 'render_details' ), 'nfinite_event', 'normal', 'high' );
		add_meta_box( 'nfinite_event_creators', __( 'Featured Creators / Participants', 'nfinite-creators' ), array( __CLASS__, 'render_creators' ), 'nfinite_event', 'normal', 'default' );
	}

	public static function render_details( $post ) {
		wp_nonce_field( 'nfinite_event_save', 'nfinite_event_nonce' );
		$fields = array(
			'_nfinite_event_start_date'  => get_post_meta( $post->ID, '_nfinite_event_start_date', true ),
			'_nfinite_event_start_time'  => get_post_meta( $post->ID, '_nfinite_event_start_time', true ),
			'_nfinite_event_end_date'    => get_post_meta( $post->ID, '_nfinite_event_end_date', true ),
			'_nfinite_event_end_time'    => get_post_meta( $post->ID, '_nfinite_event_end_time', true ),
			'_nfinite_event_venue'       => get_post_meta( $post->ID, '_nfinite_event_venue', true ),
			'_nfinite_event_address'     => get_post_meta( $post->ID, '_nfinite_event_address', true ),
			'_nfinite_event_city'        => get_post_meta( $post->ID, '_nfinite_event_city', true ),
			'_nfinite_event_state'       => get_post_meta( $post->ID, '_nfinite_event_state', true ),
			'_nfinite_event_ticket_url'  => get_post_meta( $post->ID, '_nfinite_event_ticket_url', true ),
			'_nfinite_event_ticket_text' => get_post_meta( $post->ID, '_nfinite_event_ticket_text', true ),
			'_nfinite_event_price'       => get_post_meta( $post->ID, '_nfinite_event_price', true ),
			'_nfinite_event_featured'    => get_post_meta( $post->ID, '_nfinite_event_featured', true ),
		);
		?>
		<div class="nfinite-admin-grid">
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Start Date', 'nfinite-creators' ); ?></label><input class="widefat" type="date" name="_nfinite_event_start_date" value="<?php echo esc_attr( $fields['_nfinite_event_start_date'] ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Start Time', 'nfinite-creators' ); ?></label><input class="widefat" type="time" name="_nfinite_event_start_time" value="<?php echo esc_attr( $fields['_nfinite_event_start_time'] ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'End Date', 'nfinite-creators' ); ?></label><input class="widefat" type="date" name="_nfinite_event_end_date" value="<?php echo esc_attr( $fields['_nfinite_event_end_date'] ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'End Time', 'nfinite-creators' ); ?></label><input class="widefat" type="time" name="_nfinite_event_end_time" value="<?php echo esc_attr( $fields['_nfinite_event_end_time'] ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Venue', 'nfinite-creators' ); ?></label><input class="widefat" type="text" name="_nfinite_event_venue" value="<?php echo esc_attr( $fields['_nfinite_event_venue'] ); ?>" placeholder="Virginia Beach Convention Center"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Street Address', 'nfinite-creators' ); ?></label><input class="widefat" type="text" name="_nfinite_event_address" value="<?php echo esc_attr( $fields['_nfinite_event_address'] ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'City', 'nfinite-creators' ); ?></label><input class="widefat" type="text" name="_nfinite_event_city" value="<?php echo esc_attr( $fields['_nfinite_event_city'] ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'State / Region', 'nfinite-creators' ); ?></label><input class="widefat" type="text" name="_nfinite_event_state" value="<?php echo esc_attr( $fields['_nfinite_event_state'] ); ?>" placeholder="VA"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Ticket / RSVP URL', 'nfinite-creators' ); ?></label><input class="widefat" type="url" name="_nfinite_event_ticket_url" value="<?php echo esc_attr( $fields['_nfinite_event_ticket_url'] ); ?>"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Ticket Button Text', 'nfinite-creators' ); ?></label><input class="widefat" type="text" name="_nfinite_event_ticket_text" value="<?php echo esc_attr( $fields['_nfinite_event_ticket_text'] ); ?>" placeholder="Get Tickets"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Price / Admission Label', 'nfinite-creators' ); ?></label><input class="widefat" type="text" name="_nfinite_event_price" value="<?php echo esc_attr( $fields['_nfinite_event_price'] ); ?>" placeholder="Free, $25+, RSVP Required"></div>
			<div class="nfinite-admin-field"><label class="nfinite-admin-toggle"><input type="checkbox" name="_nfinite_event_featured" value="1" <?php checked( $fields['_nfinite_event_featured'], '1' ); ?>><span><strong><?php esc_html_e( 'Featured Event', 'nfinite-creators' ); ?></strong><br><?php esc_html_e( 'Prioritize this event on the Events hub.', 'nfinite-creators' ); ?></span></label></div>
		</div>
		<p class="description"><?php esc_html_e( 'Use the Featured Image as event artwork. Event status is calculated automatically from the dates.', 'nfinite-creators' ); ?></p>
		<?php
	}

	public static function render_creators( $post ) {
		$selected = get_post_meta( $post->ID, '_nfinite_event_creator_ids', true );
		$selected = is_array( $selected ) ? array_map( 'absint', $selected ) : array();
		?>
		<p><?php esc_html_e( 'Connect existing creator profiles to this event. These can represent performers, hosts, DJs, speakers, photographers, vendors, or other participants.', 'nfinite-creators' ); ?></p>
		<select class="widefat" name="_nfinite_event_creator_ids[]" multiple size="10" style="min-height:220px;">
			<?php foreach ( self::creators() as $creator ) : ?>
				<option value="<?php echo esc_attr( $creator->ID ); ?>" <?php selected( in_array( $creator->ID, $selected, true ) ); ?>><?php echo esc_html( $creator->post_title ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Hold Ctrl (Windows) or Command (Mac) to select multiple creators.', 'nfinite-creators' ); ?></p>
		<?php
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['nfinite_event_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_event_nonce'] ) ), 'nfinite_event_save' ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		$text_fields = array(
			'_nfinite_event_start_date', '_nfinite_event_start_time', '_nfinite_event_end_date', '_nfinite_event_end_time',
			'_nfinite_event_venue', '_nfinite_event_address', '_nfinite_event_city', '_nfinite_event_state',
			'_nfinite_event_ticket_text', '_nfinite_event_price',
		);
		foreach ( $text_fields as $key ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			update_post_meta( $post_id, $key, $value );
		}
		$url = isset( $_POST['_nfinite_event_ticket_url'] ) ? esc_url_raw( wp_unslash( $_POST['_nfinite_event_ticket_url'] ) ) : '';
		update_post_meta( $post_id, '_nfinite_event_ticket_url', $url );
		update_post_meta( $post_id, '_nfinite_event_featured', isset( $_POST['_nfinite_event_featured'] ) ? '1' : '0' );

		$creator_ids = isset( $_POST['_nfinite_event_creator_ids'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['_nfinite_event_creator_ids'] ) ) ) ) : array();
		update_post_meta( $post_id, '_nfinite_event_creator_ids', $creator_ids );
	}

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['nfinite_event_date'] = __( 'Event Date', 'nfinite-creators' );
				$new['nfinite_event_location'] = __( 'Location', 'nfinite-creators' );
				$new['nfinite_event_status'] = __( 'Status', 'nfinite-creators' );
			}
		}
		return $new;
	}

	public static function column_content( $column, $post_id ) {
		if ( 'nfinite_event_date' === $column ) {
			echo esc_html( Nfinite_Creators_Events_Query::date_label( $post_id ) );
		} elseif ( 'nfinite_event_location' === $column ) {
			echo esc_html( Nfinite_Creators_Events_Query::location_label( $post_id ) ?: '—' );
		} elseif ( 'nfinite_event_status' === $column ) {
			echo esc_html( ucfirst( Nfinite_Creators_Events_Query::status( $post_id ) ) );
		}
	}
}
