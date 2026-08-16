<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Meta {
	private static $saving = false;

	public static function init() {
		add_action( 'add_meta_boxes_nfinite_creator', array( __CLASS__, 'add_boxes' ) );
		add_action( 'save_post_nfinite_creator', array( __CLASS__, 'save' ), 20, 2 );
	}

	public static function add_boxes() {
		add_meta_box(
			'nfinite_creator_builder',
			__( 'Nfinite Creator Profile Builder', 'nfinite-creators' ),
			array( __CLASS__, 'render_builder' ),
			'nfinite_creator',
			'normal',
			'high'
		);

		// Remove the old simplified boxes when upgrading from 0.1.x.
		remove_meta_box( 'nfinite_creator_details', 'nfinite_creator', 'normal' );
		remove_meta_box( 'nfinite_creator_audio', 'nfinite_creator', 'normal' );
	}

	private static function value( $post_id, $key, $default = '' ) {
		$value = get_post_meta( $post_id, $key, true );
		return '' === $value ? $default : $value;
	}

	public static function render_builder( $post ) {
		wp_nonce_field( 'nfinite_creator_admin_save', 'nfinite_creator_admin_nonce' );

		$owner_id    = (int) $post->post_author;
		$cover_id    = absint( self::value( $post->ID, '_nfinite_creator_cover_id' ) );
		$gallery_ids = self::value( $post->ID, '_nfinite_creator_gallery_ids', array() );
		$tracks      = self::value( $post->ID, '_nfinite_creator_tracks', array() );

		if ( ! is_array( $gallery_ids ) ) {
			$gallery_ids = array_filter( array_map( 'absint', explode( ',', (string) $gallery_ids ) ) );
		}
		if ( ! is_array( $tracks ) ) {
			$tracks = array();
		}

		$users = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name', 'user_email' ),
			)
		);
		?>
		<div class="nfinite-admin-builder">
			<section class="nfinite-admin-section">
				<div class="nfinite-admin-section__head">
					<span class="nfinite-admin-section__number">1</span>
					<div>
						<h3><?php esc_html_e( 'Profile & Ownership', 'nfinite-creators' ); ?></h3>
						<p><?php esc_html_e( 'Core identity, account ownership, and publication controls.', 'nfinite-creators' ); ?></p>
					</div>
				</div>

				<div class="nfinite-admin-grid">
					<div class="nfinite-admin-field">
						<label for="nfinite_creator_owner"><?php esc_html_e( 'Profile Owner', 'nfinite-creators' ); ?></label>
						<select class="widefat" id="nfinite_creator_owner" name="_nfinite_creator_owner">
							<?php foreach ( $users as $user ) : ?>
								<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $owner_id, $user->ID ); ?>>
									<?php echo esc_html( $user->display_name . ' — ' . $user->user_email ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="nfinite-admin-help"><?php esc_html_e( 'Assign the profile to a WordPress user. That user can then manage it through Creator Studio.', 'nfinite-creators' ); ?></p>
					</div>

					<div class="nfinite-admin-field">
						<span class="nfinite-admin-label"><?php esc_html_e( 'Homepage Visibility', 'nfinite-creators' ); ?></span>
						<label class="nfinite-admin-toggle">
							<input type="checkbox" name="_nfinite_creator_featured" value="1" <?php checked( self::value( $post->ID, '_nfinite_creator_featured' ), '1' ); ?>>
							<span><strong><?php esc_html_e( 'Featured Creator', 'nfinite-creators' ); ?></strong><br><?php esc_html_e( 'Prioritize this creator for WPNfinite Spotlight sections.', 'nfinite-creators' ); ?></span>
						</label>
					</div>

					<div class="nfinite-admin-field">
						<label for="_nfinite_creator_tagline"><?php esc_html_e( 'Tagline', 'nfinite-creators' ); ?></label>
						<input class="widefat" id="_nfinite_creator_tagline" type="text" name="_nfinite_creator_tagline" value="<?php echo esc_attr( self::value( $post->ID, '_nfinite_creator_tagline' ) ); ?>">
					</div>

					<div class="nfinite-admin-field">
						<label for="_nfinite_creator_location"><?php esc_html_e( 'Location', 'nfinite-creators' ); ?></label>
						<input class="widefat" id="_nfinite_creator_location" type="text" name="_nfinite_creator_location" value="<?php echo esc_attr( self::value( $post->ID, '_nfinite_creator_location' ) ); ?>">
					</div>
				</div>

				<p class="nfinite-admin-owner-note"><?php esc_html_e( 'Use the normal WordPress title for the creator name, the main editor for the full bio, the Featured Image for the profile photo, and Creator Types for roles such as Artist, Producer, Photographer, or Developer.', 'nfinite-creators' ); ?></p>
			</section>

			<section class="nfinite-admin-section">
				<div class="nfinite-admin-section__head">
					<span class="nfinite-admin-section__number">2</span>
					<div>
						<h3><?php esc_html_e( 'Media', 'nfinite-creators' ); ?></h3>
						<p><?php esc_html_e( 'Cover imagery and portfolio/gallery assets.', 'nfinite-creators' ); ?></p>
					</div>
				</div>

				<div class="nfinite-admin-grid">
					<div class="nfinite-admin-field">
						<span class="nfinite-admin-label"><?php esc_html_e( 'Cover Image', 'nfinite-creators' ); ?></span>
						<input type="hidden" name="_nfinite_creator_cover_id" value="<?php echo esc_attr( $cover_id ); ?>">
						<div class="nfinite-admin-actions">
							<button type="button" class="button" data-select-cover-image><?php esc_html_e( 'Select Cover Image', 'nfinite-creators' ); ?></button>
							<button type="button" class="button-link-delete" data-remove-cover-image><?php esc_html_e( 'Remove', 'nfinite-creators' ); ?></button>
						</div>
						<div class="nfinite-admin-media-preview" data-cover-image-preview>
							<?php if ( $cover_id ) : echo wp_get_attachment_image( $cover_id, 'thumbnail' ); endif; ?>
						</div>
					</div>

					<div class="nfinite-admin-field">
						<span class="nfinite-admin-label"><?php esc_html_e( 'Portfolio / Gallery', 'nfinite-creators' ); ?></span>
						<input type="hidden" name="_nfinite_creator_gallery_ids" data-gallery-ids value="<?php echo esc_attr( implode( ',', array_map( 'absint', $gallery_ids ) ) ); ?>">
						<div class="nfinite-admin-actions">
							<button type="button" class="button" data-select-gallery><?php esc_html_e( 'Manage Gallery', 'nfinite-creators' ); ?></button>
							<button type="button" class="button-link-delete" data-clear-gallery><?php esc_html_e( 'Clear', 'nfinite-creators' ); ?></button>
						</div>
						<div class="nfinite-admin-media-preview nfinite-admin-gallery-preview" data-gallery-preview>
							<?php foreach ( $gallery_ids as $image_id ) : echo wp_get_attachment_image( absint( $image_id ), 'thumbnail' ); endforeach; ?>
						</div>
					</div>
				</div>
			</section>

			<section class="nfinite-admin-section">
				<div class="nfinite-admin-section__head">
					<span class="nfinite-admin-section__number">3</span>
					<div>
						<h3><?php esc_html_e( 'Links & Booking', 'nfinite-creators' ); ?></h3>
						<p><?php esc_html_e( 'Website, social, streaming, and professional contact information.', 'nfinite-creators' ); ?></p>
					</div>
				</div>

				<div class="nfinite-admin-grid">
					<?php
					$link_fields = array(
						'_nfinite_creator_website'    => __( 'Website', 'nfinite-creators' ),
						'_nfinite_creator_instagram'  => __( 'Instagram', 'nfinite-creators' ),
						'_nfinite_creator_tiktok'     => __( 'TikTok', 'nfinite-creators' ),
						'_nfinite_creator_youtube'    => __( 'YouTube', 'nfinite-creators' ),
						'_nfinite_creator_spotify'    => __( 'Spotify', 'nfinite-creators' ),
						'_nfinite_creator_soundcloud' => __( 'SoundCloud', 'nfinite-creators' ),
					);
					foreach ( $link_fields as $key => $label ) :
					?>
						<div class="nfinite-admin-field">
							<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
							<input class="widefat" id="<?php echo esc_attr( $key ); ?>" type="url" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( self::value( $post->ID, $key ) ); ?>">
						</div>
					<?php endforeach; ?>

					<div class="nfinite-admin-field nfinite-admin-field--full">
						<label for="_nfinite_creator_email"><?php esc_html_e( 'Booking / Contact Email', 'nfinite-creators' ); ?></label>
						<input class="widefat" id="_nfinite_creator_email" type="email" name="_nfinite_creator_email" value="<?php echo esc_attr( self::value( $post->ID, '_nfinite_creator_email' ) ); ?>">
					</div>
				</div>
			</section>

			<section class="nfinite-admin-section">
				<div class="nfinite-admin-section__head">
					<span class="nfinite-admin-section__number">4</span>
					<div>
						<h3><?php esc_html_e( 'Music & Audio', 'nfinite-creators' ); ?></h3>
						<p><?php esc_html_e( 'Build the public playlist with WordPress Media Library audio, cover art, track type, credits, and drag-and-drop ordering.', 'nfinite-creators' ); ?></p>
					</div>
				</div>

				<div class="nfinite-admin-track-list">
					<?php foreach ( $tracks as $index => $track ) : self::render_track_row( $index, $track ); endforeach; ?>
				</div>

				<p><button type="button" class="button button-secondary" data-add-admin-track><?php esc_html_e( '+ Add Track', 'nfinite-creators' ); ?></button></p>
			</section>

			<section class="nfinite-admin-section">
				<div class="nfinite-admin-section__head">
					<span class="nfinite-admin-section__number">5</span>
					<div>
						<h3><?php esc_html_e( 'Creator Kit / EPK', 'nfinite-creators' ); ?></h3>
						<p><?php esc_html_e( 'Press-ready information for booking, media, partnerships, and professional outreach.', 'nfinite-creators' ); ?></p>
					</div>
				</div>

				<label class="nfinite-admin-toggle">
					<input type="checkbox" name="_nfinite_creator_kit_enabled" value="1" <?php checked( self::value( $post->ID, '_nfinite_creator_kit_enabled' ), '1' ); ?>>
					<span><strong><?php esc_html_e( 'Enable Creator Kit / EPK', 'nfinite-creators' ); ?></strong><br><?php esc_html_e( 'Display the public Creator Kit presentation on this profile.', 'nfinite-creators' ); ?></span>
				</label>

				<div class="nfinite-admin-kit-grid">
					<div class="nfinite-admin-field nfinite-admin-field--full">
						<label for="_nfinite_creator_short_bio"><?php esc_html_e( 'Short Press Bio', 'nfinite-creators' ); ?></label>
						<textarea class="widefat" id="_nfinite_creator_short_bio" name="_nfinite_creator_short_bio" rows="4"><?php echo esc_textarea( self::value( $post->ID, '_nfinite_creator_short_bio' ) ); ?></textarea>
					</div>

					<div class="nfinite-admin-field nfinite-admin-field--full">
						<label for="_nfinite_creator_credits"><?php esc_html_e( 'Notable Credits / Clients', 'nfinite-creators' ); ?></label>
						<textarea class="widefat" id="_nfinite_creator_credits" name="_nfinite_creator_credits" rows="5"><?php echo esc_textarea( self::value( $post->ID, '_nfinite_creator_credits' ) ); ?></textarea>
					</div>

					<div class="nfinite-admin-field nfinite-admin-field--full">
						<label for="_nfinite_creator_achievements"><?php esc_html_e( 'Achievements / Press / Highlights', 'nfinite-creators' ); ?></label>
						<textarea class="widefat" id="_nfinite_creator_achievements" name="_nfinite_creator_achievements" rows="5"><?php echo esc_textarea( self::value( $post->ID, '_nfinite_creator_achievements' ) ); ?></textarea>
					</div>

					<div class="nfinite-admin-field">
						<label for="_nfinite_creator_booking_name"><?php esc_html_e( 'Booking Contact Name', 'nfinite-creators' ); ?></label>
						<input class="widefat" id="_nfinite_creator_booking_name" type="text" name="_nfinite_creator_booking_name" value="<?php echo esc_attr( self::value( $post->ID, '_nfinite_creator_booking_name' ) ); ?>">
					</div>

					<div class="nfinite-admin-field">
						<label for="_nfinite_creator_booking_phone"><?php esc_html_e( 'Booking Phone', 'nfinite-creators' ); ?></label>
						<input class="widefat" id="_nfinite_creator_booking_phone" type="text" name="_nfinite_creator_booking_phone" value="<?php echo esc_attr( self::value( $post->ID, '_nfinite_creator_booking_phone' ) ); ?>">
					</div>

					<div class="nfinite-admin-field nfinite-admin-field--full">
						<label for="_nfinite_creator_press_download"><?php esc_html_e( 'Press Kit / Download URL', 'nfinite-creators' ); ?></label>
						<input class="widefat" id="_nfinite_creator_press_download" type="url" name="_nfinite_creator_press_download" value="<?php echo esc_attr( self::value( $post->ID, '_nfinite_creator_press_download' ) ); ?>">
						<p class="nfinite-admin-help"><?php esc_html_e( 'Optional ZIP, PDF, Google Drive, Dropbox, or other downloadable press asset URL.', 'nfinite-creators' ); ?></p>
					</div>
				</div>
			</section>
		</div>
		<?php
	}

	private static function render_track_row( $index, $track ) {
		$title     = isset( $track['title'] ) ? $track['title'] : '';
		$type      = isset( $track['type'] ) ? $track['type'] : 'Song';
		$audio_url = isset( $track['audio_url'] ) ? $track['audio_url'] : '';
		$cover_url = isset( $track['cover_url'] ) ? $track['cover_url'] : '';
		$credits   = isset( $track['credits'] ) ? $track['credits'] : '';
		?>
		<div class="nfinite-admin-track" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="nfinite-admin-track__head">
				<strong><span class="dashicons dashicons-menu nfinite-admin-track__handle"></span><?php esc_html_e( 'Track', 'nfinite-creators' ); ?> <span data-track-number><?php echo esc_html( $index + 1 ); ?></span></strong>
				<button type="button" class="button-link-delete" data-remove-admin-track><?php esc_html_e( 'Remove', 'nfinite-creators' ); ?></button>
			</div>
			<div class="nfinite-admin-track__body">
				<div class="nfinite-admin-grid">
					<div class="nfinite-admin-field">
						<label><?php esc_html_e( 'Title', 'nfinite-creators' ); ?></label>
						<input class="widefat" type="text" name="tracks[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $title ); ?>">
					</div>

					<div class="nfinite-admin-field">
						<label><?php esc_html_e( 'Track Type', 'nfinite-creators' ); ?></label>
						<select class="widefat" name="tracks[<?php echo esc_attr( $index ); ?>][type]">
							<?php foreach ( array( 'Song', 'Beat', 'Demo', 'Mix', 'Production Reel', 'Other' ) as $track_type ) : ?>
								<option value="<?php echo esc_attr( $track_type ); ?>" <?php selected( $type, $track_type ); ?>><?php echo esc_html( $track_type ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="nfinite-admin-field nfinite-admin-field--full">
						<label><?php esc_html_e( 'Audio', 'nfinite-creators' ); ?></label>
						<input class="widefat" type="url" data-audio-url name="tracks[<?php echo esc_attr( $index ); ?>][audio_url]" value="<?php echo esc_attr( $audio_url ); ?>">
						<div class="nfinite-admin-actions">
							<button type="button" class="button" data-select-audio><?php esc_html_e( 'Select Audio', 'nfinite-creators' ); ?></button>
							<?php if ( $audio_url ) : ?><a class="button-link" href="<?php echo esc_url( $audio_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open file', 'nfinite-creators' ); ?></a><?php endif; ?>
						</div>
					</div>

					<div class="nfinite-admin-field">
						<label><?php esc_html_e( 'Cover Art', 'nfinite-creators' ); ?></label>
						<input class="widefat" type="url" data-cover-url name="tracks[<?php echo esc_attr( $index ); ?>][cover_url]" value="<?php echo esc_attr( $cover_url ); ?>">
						<div class="nfinite-admin-actions">
							<button type="button" class="button" data-select-cover><?php esc_html_e( 'Select Cover Art', 'nfinite-creators' ); ?></button>
						</div>
						<div class="nfinite-admin-media-preview" data-cover-preview>
							<?php if ( $cover_url ) : ?><img src="<?php echo esc_url( $cover_url ); ?>" alt=""><?php endif; ?>
						</div>
					</div>

					<div class="nfinite-admin-field">
						<label><?php esc_html_e( 'Credits / Notes', 'nfinite-creators' ); ?></label>
						<textarea class="widefat" rows="4" name="tracks[<?php echo esc_attr( $index ); ?>][credits]"><?php echo esc_textarea( $credits ); ?></textarea>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function save( $post_id, $post ) {
		if ( self::$saving ) { return; }

		if (
			! isset( $_POST['nfinite_creator_admin_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_creator_admin_nonce'] ) ), 'nfinite_creator_admin_save' )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		$text_fields = array(
			'_nfinite_creator_tagline',
			'_nfinite_creator_location',
			'_nfinite_creator_booking_name',
			'_nfinite_creator_booking_phone',
		);

		$textarea_fields = array(
			'_nfinite_creator_short_bio',
			'_nfinite_creator_credits',
			'_nfinite_creator_achievements',
		);

		$url_fields = array(
			'_nfinite_creator_website',
			'_nfinite_creator_instagram',
			'_nfinite_creator_tiktok',
			'_nfinite_creator_youtube',
			'_nfinite_creator_spotify',
			'_nfinite_creator_soundcloud',
			'_nfinite_creator_press_download',
		);

		foreach ( $text_fields as $key ) {
			update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '' );
		}

		foreach ( $textarea_fields as $key ) {
			update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '' );
		}

		foreach ( $url_fields as $key ) {
			update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : '' );
		}

		update_post_meta(
			$post_id,
			'_nfinite_creator_email',
			isset( $_POST['_nfinite_creator_email'] ) ? sanitize_email( wp_unslash( $_POST['_nfinite_creator_email'] ) ) : ''
		);

		update_post_meta( $post_id, '_nfinite_creator_featured', isset( $_POST['_nfinite_creator_featured'] ) ? '1' : '' );
		update_post_meta( $post_id, '_nfinite_creator_kit_enabled', isset( $_POST['_nfinite_creator_kit_enabled'] ) ? '1' : '' );

		$cover_id = isset( $_POST['_nfinite_creator_cover_id'] ) ? absint( $_POST['_nfinite_creator_cover_id'] ) : 0;
		update_post_meta( $post_id, '_nfinite_creator_cover_id', $cover_id );

		$gallery_raw = isset( $_POST['_nfinite_creator_gallery_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['_nfinite_creator_gallery_ids'] ) ) : '';
		$gallery_ids = array_values( array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) );
		update_post_meta( $post_id, '_nfinite_creator_gallery_ids', $gallery_ids );

		$tracks = array();
		$allowed_types = array( 'Song', 'Beat', 'Demo', 'Mix', 'Production Reel', 'Other' );

		if ( isset( $_POST['tracks'] ) && is_array( $_POST['tracks'] ) ) {
			foreach ( wp_unslash( $_POST['tracks'] ) as $track ) {
				$title     = isset( $track['title'] ) ? sanitize_text_field( $track['title'] ) : '';
				$type      = isset( $track['type'] ) ? sanitize_text_field( $track['type'] ) : 'Song';
				$audio_url = isset( $track['audio_url'] ) ? esc_url_raw( $track['audio_url'] ) : '';
				$cover_url = isset( $track['cover_url'] ) ? esc_url_raw( $track['cover_url'] ) : '';
				$credits   = isset( $track['credits'] ) ? sanitize_textarea_field( $track['credits'] ) : '';

				if ( ! in_array( $type, $allowed_types, true ) ) {
					$type = 'Other';
				}

				if ( '' === $title && '' === $audio_url ) {
					continue;
				}

				$tracks[] = array(
					'title'     => $title ?: __( 'Untitled Track', 'nfinite-creators' ),
					'type'      => $type,
					'audio_url' => $audio_url,
					'cover_url' => $cover_url,
					'credits'   => $credits,
				);
			}
		}

		update_post_meta( $post_id, '_nfinite_creator_tracks', $tracks );

		$owner_id = isset( $_POST['_nfinite_creator_owner'] ) ? absint( $_POST['_nfinite_creator_owner'] ) : 0;

		if ( $owner_id && get_user_by( 'id', $owner_id ) && (int) $post->post_author !== $owner_id ) {
			self::$saving = true;
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_author' => $owner_id,
				)
			);
			self::$saving = false;
		}
	}
}
