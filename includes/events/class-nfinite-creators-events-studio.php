<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Events_Studio {
	public static function init() {
		add_action( 'admin_post_nfinite_submit_creator_event', array( __CLASS__, 'handle_submit' ) );
	}

	public static function creator_events( $creator_id, $owner_id ) {
		if ( ! $creator_id ) { return array(); }
		return get_posts( array(
			'post_type'      => 'nfinite_event',
			'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
			'posts_per_page' => -1,
			'author'         => absint( $owner_id ),
			'meta_query'     => array( array( 'key' => '_nfinite_event_submitted_by_creator', 'value' => absint( $creator_id ) ) ),
			'orderby'        => 'meta_value',
			'meta_key'       => '_nfinite_event_start_date',
			'order'          => 'ASC',
		) );
	}

	public static function render_panel( $creator_id, $owner_id, $is_admin = false ) {
		if ( ! $creator_id ) { return; }
		$events = self::creator_events( $creator_id, $owner_id );
		$notice = isset( $_GET['nfinite_event_notice'] ) ? sanitize_key( wp_unslash( $_GET['nfinite_event_notice'] ) ) : '';
		?>
		<div class="nfinite-form-section nfinite-studio-panel nfinite-events-studio" data-studio-panel="events">
			<div class="nfinite-form-section__head"><span>+</span><div><h3><?php esc_html_e( 'Events', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Submit performances, showcases, workshops, appearances, and other events for PairOfDice review.', 'nfinite-creators' ); ?></p></div></div>
			<?php if ( 'submitted' === $notice ) : ?><div class="nfinite-creators-notice"><?php esc_html_e( 'Event submitted. PairOfDice will review it before it appears publicly.', 'nfinite-creators' ); ?></div><?php endif; ?>
			<?php if ( $events ) : ?>
				<div class="nfinite-creator-event-list">
					<?php foreach ( $events as $event ) : $status = get_post_status( $event ); ?>
						<div class="nfinite-creator-event-item"><div><strong><?php echo esc_html( get_the_title( $event ) ); ?></strong><small><?php echo esc_html( Nfinite_Creators_Events_Query::date_label( $event->ID ) ); ?><?php $loc = Nfinite_Creators_Events_Query::location_label( $event->ID ); if ( $loc ) { echo ' • ' . esc_html( $loc ); } ?></small></div><span class="nfinite-status-pill <?php echo 'publish' === $status ? 'is-live' : 'is-draft'; ?>"><?php echo esc_html( 'publish' === $status ? __( 'Approved', 'nfinite-creators' ) : __( 'Pending Review', 'nfinite-creators' ) ); ?></span><?php if ( 'publish' === $status ) : ?><a href="<?php echo esc_url( get_permalink( $event ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'nfinite-creators' ); ?></a><?php endif; ?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="nfinite-event-submit-card">
				<h4><?php esc_html_e( 'Submit an Event', 'nfinite-creators' ); ?></h4>
				<p><?php esc_html_e( 'Creator events are reviewed before publication. PairOfDice controls Featured and Official event placement.', 'nfinite-creators' ); ?></p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nfinite_submit_creator_event"><input type="hidden" name="creator_id" value="<?php echo esc_attr( $creator_id ); ?>">
					<?php wp_nonce_field( 'nfinite_submit_creator_event', 'nfinite_event_studio_nonce' ); ?>
					<div class="nfinite-form-grid">
						<label class="nfinite-form-full"><span><?php esc_html_e( 'Event Name', 'nfinite-creators' ); ?></span><input required type="text" name="event_title"></label>
						<label><span><?php esc_html_e( 'Start Date', 'nfinite-creators' ); ?></span><input required type="date" name="event_start_date"></label>
						<label><span><?php esc_html_e( 'Start Time', 'nfinite-creators' ); ?></span><input type="time" name="event_start_time"></label>
						<label><span><?php esc_html_e( 'End Date', 'nfinite-creators' ); ?></span><input type="date" name="event_end_date"></label>
						<label><span><?php esc_html_e( 'End Time', 'nfinite-creators' ); ?></span><input type="time" name="event_end_time"></label>
						<label><span><?php esc_html_e( 'Venue', 'nfinite-creators' ); ?></span><input type="text" name="event_venue"></label>
						<label><span><?php esc_html_e( 'City', 'nfinite-creators' ); ?></span><input type="text" name="event_city"></label>
						<label><span><?php esc_html_e( 'State / Region', 'nfinite-creators' ); ?></span><input type="text" name="event_state"></label>
						<label><span><?php esc_html_e( 'Price / Admission', 'nfinite-creators' ); ?></span><input type="text" name="event_price" placeholder="Free, $25+, RSVP Required"></label>
						<label class="nfinite-form-full"><span><?php esc_html_e( 'Ticket / RSVP URL', 'nfinite-creators' ); ?></span><input type="url" name="event_ticket_url"></label>
						<label class="nfinite-form-full"><span><?php esc_html_e( 'Event Description', 'nfinite-creators' ); ?></span><textarea required name="event_description" rows="6"></textarea></label>
						<label class="nfinite-form-full"><span><?php esc_html_e( 'Event Artwork', 'nfinite-creators' ); ?></span><input type="file" name="event_artwork" accept="image/*"><small><?php esc_html_e( 'Optional. Use a flyer, poster, or event image.', 'nfinite-creators' ); ?></small></label>
					</div>
					<button class="nfinite-btn" type="submit"><?php esc_html_e( 'Submit for Review', 'nfinite-creators' ); ?></button>
				</form>
			</div>
		</div>
		<?php
	}

	public static function handle_submit() {
		if ( ! is_user_logged_in() ) { wp_die( esc_html__( 'You must be logged in.', 'nfinite-creators' ) ); }
		if ( ! isset( $_POST['nfinite_event_studio_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_event_studio_nonce'] ) ), 'nfinite_submit_creator_event' ) ) { wp_die( esc_html__( 'Security check failed.', 'nfinite-creators' ) ); }
		$user_id = get_current_user_id(); $creator_id = isset( $_POST['creator_id'] ) ? absint( $_POST['creator_id'] ) : 0;
		if ( ! $creator_id || 'nfinite_creator' !== get_post_type( $creator_id ) ) { wp_die( esc_html__( 'Invalid creator profile.', 'nfinite-creators' ) ); }
		if ( ! current_user_can( 'manage_options' ) && (int) get_post_field( 'post_author', $creator_id ) !== $user_id ) { wp_die( esc_html__( 'You cannot submit events for this creator.', 'nfinite-creators' ) ); }
		$title = isset( $_POST['event_title'] ) ? sanitize_text_field( wp_unslash( $_POST['event_title'] ) ) : '';
		$description = isset( $_POST['event_description'] ) ? wp_kses_post( wp_unslash( $_POST['event_description'] ) ) : '';
		$start_date = isset( $_POST['event_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_start_date'] ) ) : '';
		if ( ! $title || ! $description || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) { wp_die( esc_html__( 'Event name, description, and a valid start date are required.', 'nfinite-creators' ) ); }
		$event_id = wp_insert_post( array( 'post_type' => 'nfinite_event', 'post_status' => 'pending', 'post_title' => $title, 'post_content' => $description, 'post_author' => $user_id ), true );
		if ( is_wp_error( $event_id ) ) { wp_die( esc_html( $event_id->get_error_message() ) ); }
		$map = array( 'event_start_date'=>'_nfinite_event_start_date','event_start_time'=>'_nfinite_event_start_time','event_end_date'=>'_nfinite_event_end_date','event_end_time'=>'_nfinite_event_end_time','event_venue'=>'_nfinite_event_venue','event_city'=>'_nfinite_event_city','event_state'=>'_nfinite_event_state','event_price'=>'_nfinite_event_price' );
		foreach ( $map as $input => $meta ) { update_post_meta( $event_id, $meta, isset( $_POST[$input] ) ? sanitize_text_field( wp_unslash( $_POST[$input] ) ) : '' ); }
		update_post_meta( $event_id, '_nfinite_event_ticket_url', isset( $_POST['event_ticket_url'] ) ? esc_url_raw( wp_unslash( $_POST['event_ticket_url'] ) ) : '' );
		update_post_meta( $event_id, '_nfinite_event_ticket_text', 'Get Tickets' ); update_post_meta( $event_id, '_nfinite_event_featured', '0' );
		update_post_meta( $event_id, '_nfinite_event_creator_ids', array( $creator_id ) ); update_post_meta( $event_id, '_nfinite_event_submitted_by_creator', $creator_id ); update_post_meta( $event_id, '_nfinite_event_submission_source', 'creator_studio' );
		if ( ! empty( $_FILES['event_artwork']['name'] ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php'; $attachment_id = media_handle_upload( 'event_artwork', $event_id ); if ( ! is_wp_error( $attachment_id ) ) { set_post_thumbnail( $event_id, $attachment_id ); } }
		$redirect = wp_get_referer() ?: home_url( '/' ); $redirect = add_query_arg( 'nfinite_event_notice', 'submitted', $redirect ); wp_safe_redirect( $redirect ); exit;
	}
}
