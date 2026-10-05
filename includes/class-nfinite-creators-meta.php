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
						<label for="_nfinite_creator_account_class"><?php esc_html_e( 'Account Class', 'nfinite-creators' ); ?></label>
						<select class="widefat" id="_nfinite_creator_account_class" name="_nfinite_creator_account_class">
							<option value="creator" <?php selected( self::value( $post->ID, '_nfinite_creator_account_class', 'creator' ), 'creator' ); ?>><?php esc_html_e( 'Creator', 'nfinite-creators' ); ?></option>
							<option value="publisher" <?php selected( self::value( $post->ID, '_nfinite_creator_account_class', 'creator' ), 'publisher' ); ?>><?php esc_html_e( 'PairOfDice Publisher', 'nfinite-creators' ); ?></option>
						</select>
						<p class="nfinite-admin-help"><?php esc_html_e( 'Publisher is for official PairOfDice editorial/curation channels. Publisher accounts are excluded from creator streaming earnings.', 'nfinite-creators' ); ?></p>
					</div>

					<div class="nfinite-admin-field">
						<label for="_nfinite_creator_claim_status"><?php esc_html_e( 'Claim Status', 'nfinite-creators' ); ?></label>
						<select class="widefat" id="_nfinite_creator_claim_status" name="_nfinite_creator_claim_status">
							<option value="unclaimed" <?php selected( self::value( $post->ID, '_nfinite_creator_claim_status', 'unclaimed' ), 'unclaimed' ); ?>><?php esc_html_e( 'Unclaimed', 'nfinite-creators' ); ?></option>
							<option value="claimed" <?php selected( self::value( $post->ID, '_nfinite_creator_claim_status', 'unclaimed' ), 'claimed' ); ?>><?php esc_html_e( 'Claimed', 'nfinite-creators' ); ?></option>
						</select>
					</div>

					<div class="nfinite-admin-field">
						<label for="_nfinite_creator_verification_status"><?php esc_html_e( 'Verification Status', 'nfinite-creators' ); ?></label>
						<select class="widefat" id="_nfinite_creator_verification_status" name="_nfinite_creator_verification_status">
							<option value="unverified" <?php selected( self::value( $post->ID, '_nfinite_creator_verification_status', 'unverified' ), 'unverified' ); ?>><?php esc_html_e( 'Unverified', 'nfinite-creators' ); ?></option>
							<option value="verified" <?php selected( self::value( $post->ID, '_nfinite_creator_verification_status', 'unverified' ), 'verified' ); ?>><?php esc_html_e( 'PairOfDice Verified', 'nfinite-creators' ); ?></option>
						</select>
						<p class="nfinite-admin-help"><?php esc_html_e( 'Use Verified only after PairOfDice has confirmed identity or authorized representation. Publisher badges take priority over creator badges.', 'nfinite-creators' ); ?></p>
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
			<section class="nfinite-admin-section nfinite-admin-section--discovery">
				<div class="nfinite-admin-section__head">
					<span class="nfinite-admin-section__number">6</span>
					<div>
						<h3><?php esc_html_e( 'Discovery & Similar Creators', 'nfinite-creators' ); ?></h3>
						<p><?php esc_html_e( 'Administrator-only controls for cross-promoting related Creator profiles. These settings are not exposed in Creator Studio.', 'nfinite-creators' ); ?></p>
					</div>
				</div>

				<?php
				$related_enabled_value = get_post_meta( $post->ID, '_nfinite_creator_related_enabled', true );
				$related_enabled       = '' === $related_enabled_value || '1' === $related_enabled_value;
				$related_mode          = sanitize_key( self::value( $post->ID, '_nfinite_creator_related_mode', 'hybrid' ) );
				if ( ! in_array( $related_mode, array( 'automatic', 'manual', 'hybrid' ), true ) ) {
					$related_mode = 'hybrid';
				}
				$related_limit = absint( self::value( $post->ID, '_nfinite_creator_related_limit', 4 ) );
				$related_limit = $related_limit ? max( 1, min( 6, $related_limit ) ) : 4;
				$related_ids   = self::value( $post->ID, '_nfinite_creator_related_ids', array() );
				if ( ! is_array( $related_ids ) ) {
					$related_ids = array();
				}

				$related_creator_options = get_posts(
					array(
						'post_type'      => 'nfinite_creator',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'post__not_in'   => array( $post->ID ),
						'orderby'        => 'title',
						'order'          => 'ASC',
					)
				);
				?>

				<label class="nfinite-admin-toggle">
					<input type="checkbox" name="_nfinite_creator_related_enabled" value="1" <?php checked( $related_enabled ); ?>>
					<span>
						<strong><?php esc_html_e( 'Show Similar Creators', 'nfinite-creators' ); ?></strong><br>
						<?php esc_html_e( 'Display a discovery section near the bottom of this public Creator profile.', 'nfinite-creators' ); ?>
					</span>
				</label>

				<div class="nfinite-admin-grid">
					<div class="nfinite-admin-field">
						<label for="_nfinite_creator_related_mode"><?php esc_html_e( 'Selection Mode', 'nfinite-creators' ); ?></label>
						<select class="widefat" id="_nfinite_creator_related_mode" name="_nfinite_creator_related_mode">
							<option value="hybrid" <?php selected( $related_mode, 'hybrid' ); ?>><?php esc_html_e( 'Automatic + Manual Priority', 'nfinite-creators' ); ?></option>
							<option value="automatic" <?php selected( $related_mode, 'automatic' ); ?>><?php esc_html_e( 'Automatic by Creator Type', 'nfinite-creators' ); ?></option>
							<option value="manual" <?php selected( $related_mode, 'manual' ); ?>><?php esc_html_e( 'Manual Only', 'nfinite-creators' ); ?></option>
						</select>
						<p class="nfinite-admin-help"><?php esc_html_e( 'Hybrid shows manually selected Creators first, then fills remaining spots with Creators sharing at least one Creator Type.', 'nfinite-creators' ); ?></p>
					</div>

					<div class="nfinite-admin-field">
						<label for="_nfinite_creator_related_limit"><?php esc_html_e( 'Maximum Creators', 'nfinite-creators' ); ?></label>
						<select class="widefat" id="_nfinite_creator_related_limit" name="_nfinite_creator_related_limit">
							<?php for ( $related_count = 1; $related_count <= 6; $related_count++ ) : ?>
								<option value="<?php echo esc_attr( $related_count ); ?>" <?php selected( $related_limit, $related_count ); ?>><?php echo esc_html( $related_count ); ?></option>
							<?php endfor; ?>
						</select>
					</div>

					<div class="nfinite-admin-field nfinite-admin-field--full">
						<label for="_nfinite_creator_related_ids"><?php esc_html_e( 'Manual Similar Creators', 'nfinite-creators' ); ?></label>
						<select class="widefat" id="_nfinite_creator_related_ids" name="_nfinite_creator_related_ids[]" multiple size="8">
							<?php foreach ( $related_creator_options as $related_creator_option ) : ?>
								<option value="<?php echo esc_attr( $related_creator_option->ID ); ?>" <?php selected( in_array( (int) $related_creator_option->ID, array_map( 'absint', $related_ids ), true ) ); ?>>
									<?php
									$option_terms = get_the_terms( $related_creator_option->ID, 'nfinite_creator_type' );
									$option_types = $option_terms && ! is_wp_error( $option_terms ) ? implode( ', ', wp_list_pluck( $option_terms, 'name' ) ) : __( 'Creator', 'nfinite-creators' );
									echo esc_html( get_the_title( $related_creator_option ) . ' — ' . $option_types );
									?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="nfinite-admin-help"><?php esc_html_e( 'Hold Ctrl/Command to select multiple Creators. This field is used by Manual and Hybrid modes.', 'nfinite-creators' ); ?></p>
					</div>
				</div>

				<div class="nfinite-admin-callout">
					<strong><?php esc_html_e( 'Profile promotion control', 'nfinite-creators' ); ?></strong>
					<p><?php esc_html_e( 'Keeping Similar Creators enabled creates a built-in discovery network across profiles. Administrators can disable it for profiles that should operate as standalone/non-promotional pages.', 'nfinite-creators' ); ?></p>
				</div>
			</section>

		</div>
		<?php
	}

	private static function render_track_row( $index, $track ) {
		$title     = isset( $track['title'] ) ? $track['title'] : '';
		$type      = isset( $track['type'] ) ? $track['type'] : 'Single';
		if ( 'Song' === $type ) { $type = 'Single'; }
		$audio_url = isset( $track['audio_url'] ) ? $track['audio_url'] : '';
		$cover_url = isset( $track['cover_url'] ) ? $track['cover_url'] : '';
		$credits    = isset( $track['credits'] ) ? $track['credits'] : '';
		$product_id = isset( $track['product_id'] ) ? absint( $track['product_id'] ) : 0;
		$buy_url    = isset( $track['buy_url'] ) ? $track['buy_url'] : '';
		$buy_label  = isset( $track['buy_label'] ) ? $track['buy_label'] : '';
		$show_price = ! empty( $track['show_price'] );
		$products   = class_exists( 'Nfinite_Creators_Track_Commerce' )
			? Nfinite_Creators_Track_Commerce::products()
			: array();
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
							<?php foreach ( array( 'Single', 'Beat', 'Demo', 'Mix', 'Production Reel', 'Other' ) as $track_type ) : ?>
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
					<div class="nfinite-admin-field nfinite-admin-field--full nfinite-admin-commerce">
						<h4><?php esc_html_e( 'Commerce', 'nfinite-creators' ); ?></h4>
						<p class="description"><?php esc_html_e( 'Optional. Connect a WooCommerce product or enter an external licensing / purchase URL.', 'nfinite-creators' ); ?></p>
					</div>
					<div class="nfinite-admin-field">
						<label><?php esc_html_e( 'WooCommerce Product', 'nfinite-creators' ); ?></label>
						<select class="widefat wc-enhanced-select" name="tracks[<?php echo esc_attr( $index ); ?>][product_id]">
							<option value="0"><?php esc_html_e( 'No product selected', 'nfinite-creators' ); ?></option>
							<?php foreach ( $products as $product ) : ?>
								<option value="<?php echo esc_attr( $product->get_id() ); ?>" <?php selected( $product_id, $product->get_id() ); ?>><?php echo esc_html( $product->get_name() ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="nfinite-admin-field">
						<label><?php esc_html_e( 'External Buy URL', 'nfinite-creators' ); ?></label>
						<input class="widefat" type="url" name="tracks[<?php echo esc_attr( $index ); ?>][buy_url]" value="<?php echo esc_attr( $buy_url ); ?>" placeholder="https://...">
					</div>
					<div class="nfinite-admin-field">
						<label><?php esc_html_e( 'Button Label', 'nfinite-creators' ); ?></label>
						<input class="widefat" type="text" name="tracks[<?php echo esc_attr( $index ); ?>][buy_label]" value="<?php echo esc_attr( $buy_label ); ?>" placeholder="<?php esc_attr_e( 'Buy Now', 'nfinite-creators' ); ?>">
					</div>
					<div class="nfinite-admin-field">
						<label class="nfinite-admin-toggle">
							<input type="checkbox" name="tracks[<?php echo esc_attr( $index ); ?>][show_price]" value="1" <?php checked( $show_price ); ?>>
							<span><strong><?php esc_html_e( 'Show product price', 'nfinite-creators' ); ?></strong></span>
						</label>
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

		$account_class = isset( $_POST['_nfinite_creator_account_class'] ) ? sanitize_key( wp_unslash( $_POST['_nfinite_creator_account_class'] ) ) : 'creator';
		if ( ! in_array( $account_class, array( 'creator', 'publisher' ), true ) ) { $account_class = 'creator'; }
		update_post_meta( $post_id, '_nfinite_creator_account_class', $account_class );

		$claim_status = isset( $_POST['_nfinite_creator_claim_status'] ) ? sanitize_key( wp_unslash( $_POST['_nfinite_creator_claim_status'] ) ) : 'unclaimed';
		if ( ! in_array( $claim_status, array( 'unclaimed', 'claimed' ), true ) ) { $claim_status = 'unclaimed'; }
		update_post_meta( $post_id, '_nfinite_creator_claim_status', $claim_status );

		$verification_status = isset( $_POST['_nfinite_creator_verification_status'] ) ? sanitize_key( wp_unslash( $_POST['_nfinite_creator_verification_status'] ) ) : 'unverified';
		if ( ! in_array( $verification_status, array( 'unverified', 'verified' ), true ) ) { $verification_status = 'unverified'; }
		update_post_meta( $post_id, '_nfinite_creator_verification_status', $verification_status );

		update_post_meta( $post_id, '_nfinite_creator_featured', isset( $_POST['_nfinite_creator_featured'] ) ? '1' : '' );
		update_post_meta( $post_id, '_nfinite_creator_kit_enabled', isset( $_POST['_nfinite_creator_kit_enabled'] ) ? '1' : '' );

		update_post_meta(
			$post_id,
			'_nfinite_creator_related_enabled',
			isset( $_POST['_nfinite_creator_related_enabled'] ) ? '1' : '0'
		);

		$related_mode = isset( $_POST['_nfinite_creator_related_mode'] )
			? sanitize_key( wp_unslash( $_POST['_nfinite_creator_related_mode'] ) )
			: 'hybrid';

		if ( ! in_array( $related_mode, array( 'automatic', 'manual', 'hybrid' ), true ) ) {
			$related_mode = 'hybrid';
		}
		update_post_meta( $post_id, '_nfinite_creator_related_mode', $related_mode );

		$related_limit = isset( $_POST['_nfinite_creator_related_limit'] )
			? absint( $_POST['_nfinite_creator_related_limit'] )
			: 4;
		$related_limit = max( 1, min( 6, $related_limit ) );
		update_post_meta( $post_id, '_nfinite_creator_related_limit', $related_limit );

		$related_ids = array();
		if ( isset( $_POST['_nfinite_creator_related_ids'] ) && is_array( $_POST['_nfinite_creator_related_ids'] ) ) {
			$related_ids = array_values(
				array_unique(
					array_filter(
						array_map(
							'absint',
							wp_unslash( $_POST['_nfinite_creator_related_ids'] )
						),
						static function ( $related_id ) use ( $post_id ) {
							return $related_id &&
								$related_id !== $post_id &&
								'nfinite_creator' === get_post_type( $related_id );
						}
					)
				)
			);
		}
		update_post_meta( $post_id, '_nfinite_creator_related_ids', $related_ids );

		$cover_id = isset( $_POST['_nfinite_creator_cover_id'] ) ? absint( $_POST['_nfinite_creator_cover_id'] ) : 0;
		update_post_meta( $post_id, '_nfinite_creator_cover_id', $cover_id );

		$gallery_raw = isset( $_POST['_nfinite_creator_gallery_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['_nfinite_creator_gallery_ids'] ) ) : '';
		$gallery_ids = array_values( array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) );
		update_post_meta( $post_id, '_nfinite_creator_gallery_ids', $gallery_ids );

		$tracks = array();
		$allowed_types = array( 'Single', 'Song', 'Beat', 'Demo', 'Mix', 'Production Reel', 'Other' );

		if ( isset( $_POST['tracks'] ) && is_array( $_POST['tracks'] ) ) {
			foreach ( wp_unslash( $_POST['tracks'] ) as $track ) {
				$title     = isset( $track['title'] ) ? sanitize_text_field( $track['title'] ) : '';
				$type      = isset( $track['type'] ) ? sanitize_text_field( $track['type'] ) : 'Single';
				if ( 'Song' === $type ) { $type = 'Single'; }
				$audio_url = isset( $track['audio_url'] ) ? esc_url_raw( $track['audio_url'] ) : '';
                $playback_source = isset( $track['playback_source'] ) ? sanitize_key( $track['playback_source'] ) : 'local'; if ( ! in_array( $playback_source, array( 'local','soundcloud','spotify','apple_music' ), true ) ) { $playback_source = 'local'; }
                $soundcloud_url = isset( $track['soundcloud_url'] ) ? esc_url_raw( $track['soundcloud_url'] ) : ''; $spotify_url = isset( $track['spotify_url'] ) ? esc_url_raw( $track['spotify_url'] ) : ''; $apple_music_url = isset( $track['apple_music_url'] ) ? esc_url_raw( $track['apple_music_url'] ) : '';
				$cover_url = isset( $track['cover_url'] ) ? esc_url_raw( $track['cover_url'] ) : '';
				$credits    = isset( $track['credits'] ) ? sanitize_textarea_field( $track['credits'] ) : '';
				$product_id = isset( $track['product_id'] ) ? absint( $track['product_id'] ) : 0;
				$buy_url    = isset( $track['buy_url'] ) ? esc_url_raw( $track['buy_url'] ) : '';
				$buy_label  = isset( $track['buy_label'] ) ? sanitize_text_field( $track['buy_label'] ) : '';
				$show_price = ! empty( $track['show_price'] ) ? '1' : '';

				if ( ! in_array( $type, $allowed_types, true ) ) {
					$type = 'Other';
				}

				if ( '' === $title && '' === $audio_url && '' === $soundcloud_url && '' === $spotify_url && '' === $apple_music_url ) {
					continue;
				}

				$tracks[] = array(
					'title'     => $title ?: __( 'Untitled Track', 'nfinite-creators' ),
					'type'      => $type,
					'audio_url' => $audio_url, 'playback_source' => $playback_source, 'soundcloud_url' => $soundcloud_url, 'spotify_url' => $spotify_url, 'apple_music_url' => $apple_music_url,
					'cover_url' => $cover_url,
					'credits'    => $credits,
					'product_id' => $product_id,
					'buy_url'    => $buy_url,
					'buy_label'  => $buy_label,
					'show_price' => $show_price,
				);
			}
		}

		update_post_meta( $post_id, '_nfinite_creator_tracks', $tracks );

		$owner_id = isset( $_POST['_nfinite_creator_owner'] ) ? absint( $_POST['_nfinite_creator_owner'] ) : 0;

		if ( $owner_id && get_user_by( 'id', $owner_id ) ) {
			if ( class_exists( 'Nfinite_Creators_Roles' ) ) {
				Nfinite_Creators_Roles::ensure_creator_role( $owner_id );
			}

			if ( (int) $post->post_author === $owner_id ) {
				return;
			}

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
