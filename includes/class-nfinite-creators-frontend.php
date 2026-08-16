<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Frontend {
	public static function init() {
		add_shortcode( 'nfinite_creator_directory', array( __CLASS__, 'directory_shortcode' ) );
		add_shortcode( 'nfinite_creator_dashboard', array( __CLASS__, 'dashboard_shortcode' ) );
		add_action( 'admin_post_nfinite_save_creator_profile', array( __CLASS__, 'handle_save' ) );
	}

	public static function directory_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'posts' => 24,
				'type'  => '',
			),
			$atts,
			'nfinite_creator_directory'
		);

		$args = array(
			'post_type'      => 'nfinite_creator',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, absint( $atts['posts'] ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $atts['type'] ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'nfinite_creator_type',
					'field'    => 'slug',
					'terms'    => sanitize_title( $atts['type'] ),
				),
			);
		}

		$query = new WP_Query( $args );

		ob_start();
		?>
		<section class="nfinite-creators-directory">
			<div class="nfinite-creators-directory__head">
				<div>
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Community', 'nfinite-creators' ); ?></span>
					<h2><?php esc_html_e( 'Creators', 'nfinite-creators' ); ?></h2>
				</div>
			</div>

			<?php if ( $query->have_posts() ) : ?>
				<div class="nfinite-creators-grid">
					<?php while ( $query->have_posts() ) : $query->the_post(); ?>
						<?php echo self::creator_card( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			<?php else : ?>
				<p class="nfinite-creators-empty"><?php esc_html_e( 'No creator profiles are published yet.', 'nfinite-creators' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function creator_card( $creator_id ) {
		$terms = get_the_terms( $creator_id, 'nfinite_creator_type' );
		$types = $terms && ! is_wp_error( $terms ) ? implode( ' • ', wp_list_pluck( $terms, 'name' ) ) : __( 'Creator', 'nfinite-creators' );
		$location = get_post_meta( $creator_id, '_nfinite_creator_location', true );

		ob_start();
		?>
		<article class="nfinite-creator-card">
			<a class="nfinite-creator-card__media" href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>">
				<?php if ( has_post_thumbnail( $creator_id ) ) : ?>
					<?php echo get_the_post_thumbnail( $creator_id, 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<span class="nfinite-creator-placeholder"><?php echo esc_html( mb_substr( get_the_title( $creator_id ), 0, 1 ) ); ?></span>
				<?php endif; ?>
			</a>
			<div class="nfinite-creator-card__body">
				<span class="nfinite-eyebrow"><?php echo esc_html( $types ); ?></span>
				<h3><a href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>"><?php echo esc_html( get_the_title( $creator_id ) ); ?></a></h3>
				<?php if ( $location ) : ?><p><?php echo esc_html( $location ); ?></p><?php endif; ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	public static function dashboard_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<div class="nfinite-creators-notice">' . esc_html__( 'Please log in to manage your creator profile.', 'nfinite-creators' ) . '</div>';
		}

		$user_id = get_current_user_id();
		$creator = get_posts(
			array(
				'post_type'      => 'nfinite_creator',
				'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
				'author'         => $user_id,
				'posts_per_page' => 1,
			)
		);

		$creator_id = ! empty( $creator ) ? $creator[0]->ID : 0;

		$title      = $creator_id ? get_the_title( $creator_id ) : '';
		$bio        = $creator_id ? get_post_field( 'post_content', $creator_id ) : '';
		$tagline    = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_tagline', true ) : '';
		$location   = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_location', true ) : '';
		$website    = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_website', true ) : '';
		$instagram  = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_instagram', true ) : '';
		$tiktok     = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_tiktok', true ) : '';
		$youtube    = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_youtube', true ) : '';
		$spotify    = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_spotify', true ) : '';
		$soundcloud = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_soundcloud', true ) : '';
		$email      = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_email', true ) : wp_get_current_user()->user_email;
		$kit        = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_kit_enabled', true ) : '';
		$tracks     = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_tracks', true ) : array();
		$videos     = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_videos', true ) : array();
		if ( ! is_array( $tracks ) ) { $tracks = array(); }
		if ( ! is_array( $videos ) ) { $videos = array(); }

		$selected_types = array();
		if ( $creator_id ) {
			$terms = wp_get_object_terms( $creator_id, 'nfinite_creator_type', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) { $selected_types = array_map( 'absint', $terms ); }
		}

		$all_types = get_terms(
			array(
				'taxonomy'   => 'nfinite_creator_type',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		ob_start();
		?>
		<section class="nfinite-creator-dashboard">
			<div class="nfinite-creator-dashboard__head">
				<div>
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Creator Studio', 'nfinite-creators' ); ?></span>
					<h2><?php echo $creator_id ? esc_html__( 'Edit My Profile', 'nfinite-creators' ) : esc_html__( 'Create My Profile', 'nfinite-creators' ); ?></h2>
				</div>
				<?php if ( $creator_id ) : ?>
					<div class="nfinite-creator-status">
						<?php echo esc_html( ucfirst( get_post_status( $creator_id ) ) ); ?>
						<?php if ( 'publish' === get_post_status( $creator_id ) ) : ?>
							<a href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>"><?php esc_html_e( 'View Profile', 'nfinite-creators' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( isset( $_GET['nfinite_creator_saved'] ) ) : ?>
				<div class="nfinite-creators-notice nfinite-creators-notice--success"><?php esc_html_e( 'Your creator profile was saved.', 'nfinite-creators' ); ?></div>
			<?php endif; ?>

			<form class="nfinite-creator-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nfinite_save_creator_profile">
				<input type="hidden" name="creator_id" value="<?php echo esc_attr( $creator_id ); ?>">
				<?php wp_nonce_field( 'nfinite_save_creator_profile', 'nfinite_creator_nonce' ); ?>

				<div class="nfinite-form-section">
					<div class="nfinite-form-section__head"><span>1</span><div><h3><?php esc_html_e( 'Profile', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Tell visitors who you are and what you do.', 'nfinite-creators' ); ?></p></div></div>

					<div class="nfinite-form-grid">
						<label><span><?php esc_html_e( 'Display Name', 'nfinite-creators' ); ?></span><input required type="text" name="creator_title" value="<?php echo esc_attr( $title ); ?>"></label>
						<label><span><?php esc_html_e( 'Location', 'nfinite-creators' ); ?></span><input type="text" name="creator_location" value="<?php echo esc_attr( $location ); ?>"></label>

						<label class="nfinite-form-full"><span><?php esc_html_e( 'Creator Types', 'nfinite-creators' ); ?></span>
							<select name="creator_types[]" multiple size="5">
								<?php if ( ! is_wp_error( $all_types ) ) : foreach ( $all_types as $type ) : ?>
									<option value="<?php echo esc_attr( $type->term_id ); ?>" <?php selected( in_array( (int) $type->term_id, $selected_types, true ) ); ?>><?php echo esc_html( $type->name ); ?></option>
								<?php endforeach; endif; ?>
							</select>
						</label>

						<label class="nfinite-form-full"><span><?php esc_html_e( 'Tagline', 'nfinite-creators' ); ?></span><input type="text" name="creator_tagline" value="<?php echo esc_attr( $tagline ); ?>" placeholder="<?php esc_attr_e( 'Artist, producer and creative director based in Atlanta.', 'nfinite-creators' ); ?>"></label>
						<label class="nfinite-form-full"><span><?php esc_html_e( 'Bio', 'nfinite-creators' ); ?></span><textarea name="creator_bio" rows="8"><?php echo esc_textarea( $bio ); ?></textarea></label>

						<label><span><?php esc_html_e( 'Profile Photo', 'nfinite-creators' ); ?></span><input type="file" name="creator_profile_photo" accept="image/*"></label>
						<label><span><?php esc_html_e( 'Cover Image', 'nfinite-creators' ); ?></span><input type="file" name="creator_cover_image" accept="image/*"></label>
					</div>
				</div>

				<div class="nfinite-form-section">
					<div class="nfinite-form-section__head"><span>2</span><div><h3><?php esc_html_e( 'Links', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Connect your website, streaming profiles, and social accounts.', 'nfinite-creators' ); ?></p></div></div>
					<div class="nfinite-form-grid">
						<?php
						$link_fields = array(
							'creator_website'    => array( __( 'Website', 'nfinite-creators' ), $website ),
							'creator_instagram'  => array( __( 'Instagram', 'nfinite-creators' ), $instagram ),
							'creator_tiktok'     => array( __( 'TikTok', 'nfinite-creators' ), $tiktok ),
							'creator_youtube'    => array( __( 'YouTube', 'nfinite-creators' ), $youtube ),
							'creator_spotify'    => array( __( 'Spotify', 'nfinite-creators' ), $spotify ),
							'creator_soundcloud' => array( __( 'SoundCloud', 'nfinite-creators' ), $soundcloud ),
						);
						foreach ( $link_fields as $name => $data ) :
						?>
							<label><span><?php echo esc_html( $data[0] ); ?></span><input type="url" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $data[1] ); ?>"></label>
						<?php endforeach; ?>
						<label class="nfinite-form-full"><span><?php esc_html_e( 'Booking / Contact Email', 'nfinite-creators' ); ?></span><input type="email" name="creator_email" value="<?php echo esc_attr( $email ); ?>"></label>
					</div>
				</div>

				<div class="nfinite-form-section">
					<div class="nfinite-form-section__head"><span>3</span><div><h3><?php esc_html_e( 'Audio', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Upload songs, beats, demos, mixes, or production reels.', 'nfinite-creators' ); ?></p></div></div>

					<div class="nfinite-track-editor" data-track-editor>
						<?php
						$rows = ! empty( $tracks ) ? $tracks : array( array() );
						foreach ( $rows as $index => $track ) :
						?>
							<div class="nfinite-track-row" data-track-row>
								<div class="nfinite-track-row__head"><strong><?php esc_html_e( 'Track', 'nfinite-creators' ); ?></strong><button type="button" class="nfinite-track-remove" data-remove-track><?php esc_html_e( 'Remove', 'nfinite-creators' ); ?></button></div>
								<div class="nfinite-form-grid">
									<label><span><?php esc_html_e( 'Title', 'nfinite-creators' ); ?></span><input type="text" name="tracks[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( isset( $track['title'] ) ? $track['title'] : '' ); ?>"></label>
									<label><span><?php esc_html_e( 'Track Type', 'nfinite-creators' ); ?></span>
										<select name="tracks[<?php echo esc_attr( $index ); ?>][type]">
											<?php foreach ( array( 'Song','Beat','Demo','Mix','Production Reel','Other' ) as $track_type ) : ?>
												<option value="<?php echo esc_attr( $track_type ); ?>" <?php selected( isset( $track['type'] ) ? $track['type'] : '', $track_type ); ?>><?php echo esc_html( $track_type ); ?></option>
											<?php endforeach; ?>
										</select>
									</label>
									<label class="nfinite-form-full"><span><?php esc_html_e( 'Existing Audio URL', 'nfinite-creators' ); ?></span><input type="url" name="tracks[<?php echo esc_attr( $index ); ?>][audio_url]" value="<?php echo esc_attr( isset( $track['audio_url'] ) ? $track['audio_url'] : '' ); ?>"></label>
									<label><span><?php esc_html_e( 'Upload Audio', 'nfinite-creators' ); ?></span><input type="file" name="track_audio_<?php echo esc_attr( $index ); ?>" accept="audio/*"></label>
									<label><span><?php esc_html_e( 'Cover Art URL', 'nfinite-creators' ); ?></span><input type="url" name="tracks[<?php echo esc_attr( $index ); ?>][cover_url]" value="<?php echo esc_attr( isset( $track['cover_url'] ) ? $track['cover_url'] : '' ); ?>"></label>
									<label><span><?php esc_html_e( 'Credits / Notes', 'nfinite-creators' ); ?></span><textarea name="tracks[<?php echo esc_attr( $index ); ?>][credits]" rows="3"><?php echo esc_textarea( isset( $track['credits'] ) ? $track['credits'] : '' ); ?></textarea></label>
								</div>
							</div>
						<?php endforeach; ?>
						<button type="button" class="nfinite-btn nfinite-btn-secondary" data-add-track><?php esc_html_e( '+ Add Track', 'nfinite-creators' ); ?></button>
					</div>
				</div>


				<div class="nfinite-form-section">
					<div class="nfinite-form-section__head"><span>4</span><div><h3><?php esc_html_e( 'Videos', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Add YouTube or Vimeo videos for your profile and Creator Kit / EPK.', 'nfinite-creators' ); ?></p></div></div>

					<div class="nfinite-video-editor" data-video-editor>
						<?php
						$video_rows = ! empty( $videos ) ? $videos : array( array() );
						foreach ( $video_rows as $index => $video ) :
						?>
							<div class="nfinite-video-row" data-video-row>
								<div class="nfinite-track-row__head">
									<strong><?php esc_html_e( 'Video', 'nfinite-creators' ); ?> <span data-video-number><?php echo esc_html( $index + 1 ); ?></span></strong>
									<button type="button" class="nfinite-track-remove" data-remove-video><?php esc_html_e( 'Remove', 'nfinite-creators' ); ?></button>
								</div>

								<div class="nfinite-form-grid">
									<label><span><?php esc_html_e( 'Title', 'nfinite-creators' ); ?></span><input type="text" name="videos[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( isset( $video['title'] ) ? $video['title'] : '' ); ?>"></label>

									<label><span><?php esc_html_e( 'Video Type', 'nfinite-creators' ); ?></span>
										<select name="videos[<?php echo esc_attr( $index ); ?>][type]">
											<?php foreach ( Nfinite_Creators_Video::video_types() as $video_type ) : ?>
												<option value="<?php echo esc_attr( $video_type ); ?>" <?php selected( isset( $video['type'] ) ? $video['type'] : '', $video_type ); ?>><?php echo esc_html( $video_type ); ?></option>
											<?php endforeach; ?>
										</select>
									</label>

									<label class="nfinite-form-full"><span><?php esc_html_e( 'YouTube / Vimeo URL', 'nfinite-creators' ); ?></span><input type="url" name="videos[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( isset( $video['url'] ) ? $video['url'] : '' ); ?>" placeholder="https://www.youtube.com/watch?v=..."></label>

									<label class="nfinite-form-full"><span><?php esc_html_e( 'Description', 'nfinite-creators' ); ?></span><textarea name="videos[<?php echo esc_attr( $index ); ?>][description]" rows="3"><?php echo esc_textarea( isset( $video['description'] ) ? $video['description'] : '' ); ?></textarea></label>

									<label class="nfinite-form-full nfinite-toggle"><input type="checkbox" data-frontend-featured-video name="videos[<?php echo esc_attr( $index ); ?>][featured]" value="1" <?php checked( ! empty( $video['featured'] ) ); ?>><span><?php esc_html_e( 'Featured Video', 'nfinite-creators' ); ?></span></label>
								</div>
							</div>
						<?php endforeach; ?>

						<button type="button" class="nfinite-btn nfinite-btn-secondary" data-add-video><?php esc_html_e( '+ Add Video', 'nfinite-creators' ); ?></button>
					</div>
				</div>

				<div class="nfinite-form-section">
					<div class="nfinite-form-section__head"><span>5</span><div><h3><?php esc_html_e( 'Creator Kit / EPK', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Enable a press-ready professional presentation using the same profile content.', 'nfinite-creators' ); ?></p></div></div>
					<label class="nfinite-toggle"><input type="checkbox" name="creator_kit_enabled" value="1" <?php checked( $kit, '1' ); ?>><span><?php esc_html_e( 'Enable Creator Kit / EPK', 'nfinite-creators' ); ?></span></label>
				</div>

				<div class="nfinite-form-actions">
					<p><?php esc_html_e( 'New profiles are submitted for review before becoming public.', 'nfinite-creators' ); ?></p>
					<button class="nfinite-btn nfinite-btn-primary" type="submit"><?php esc_html_e( 'Save Creator Profile', 'nfinite-creators' ); ?></button>
				</div>
			</form>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function handle_save() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'nfinite-creators' ) );
		}

		if (
			! isset( $_POST['nfinite_creator_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_creator_nonce'] ) ), 'nfinite_save_creator_profile' )
		) {
			wp_die( esc_html__( 'Security check failed.', 'nfinite-creators' ) );
		}

		$user_id    = get_current_user_id();
		$creator_id = isset( $_POST['creator_id'] ) ? absint( $_POST['creator_id'] ) : 0;

		if ( $creator_id ) {
			if ( 'nfinite_creator' !== get_post_type( $creator_id ) || (int) get_post_field( 'post_author', $creator_id ) !== $user_id ) {
				wp_die( esc_html__( 'You cannot edit this creator profile.', 'nfinite-creators' ) );
			}
		}

		$title = isset( $_POST['creator_title'] ) ? sanitize_text_field( wp_unslash( $_POST['creator_title'] ) ) : '';
		$bio   = isset( $_POST['creator_bio'] ) ? wp_kses_post( wp_unslash( $_POST['creator_bio'] ) ) : '';

		if ( '' === $title ) {
			wp_die( esc_html__( 'Display name is required.', 'nfinite-creators' ) );
		}

		$postarr = array(
			'post_type'    => 'nfinite_creator',
			'post_title'   => $title,
			'post_content' => $bio,
			'post_excerpt' => wp_trim_words( wp_strip_all_tags( $bio ), 35, '…' ),
			'post_author'  => $user_id,
		);

		if ( $creator_id ) {
			$postarr['ID'] = $creator_id;
			$new_id = wp_update_post( $postarr, true );
		} else {
			$postarr['post_status'] = 'pending';
			$new_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		$creator_id = (int) $new_id;

		$types = isset( $_POST['creator_types'] ) ? array_map( 'absint', (array) $_POST['creator_types'] ) : array();
		wp_set_object_terms( $creator_id, $types, 'nfinite_creator_type', false );

		$text_map = array(
			'_nfinite_creator_tagline'  => 'creator_tagline',
			'_nfinite_creator_location' => 'creator_location',
		);
		foreach ( $text_map as $meta_key => $field ) {
			update_post_meta( $creator_id, $meta_key, isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '' );
		}

		$url_map = array(
			'_nfinite_creator_website'    => 'creator_website',
			'_nfinite_creator_instagram'  => 'creator_instagram',
			'_nfinite_creator_tiktok'     => 'creator_tiktok',
			'_nfinite_creator_youtube'    => 'creator_youtube',
			'_nfinite_creator_spotify'    => 'creator_spotify',
			'_nfinite_creator_soundcloud' => 'creator_soundcloud',
		);
		foreach ( $url_map as $meta_key => $field ) {
			update_post_meta( $creator_id, $meta_key, isset( $_POST[ $field ] ) ? esc_url_raw( wp_unslash( $_POST[ $field ] ) ) : '' );
		}

		update_post_meta(
			$creator_id,
			'_nfinite_creator_email',
			isset( $_POST['creator_email'] ) ? sanitize_email( wp_unslash( $_POST['creator_email'] ) ) : ''
		);
		update_post_meta( $creator_id, '_nfinite_creator_kit_enabled', isset( $_POST['creator_kit_enabled'] ) ? '1' : '' );

		self::handle_images( $creator_id );
		self::handle_tracks( $creator_id );

		$raw_videos = isset( $_POST['videos'] ) && is_array( $_POST['videos'] ) ? wp_unslash( $_POST['videos'] ) : array();
		update_post_meta( $creator_id, '_nfinite_creator_videos', Nfinite_Creators_Video::sanitize_videos( $raw_videos ) );

		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'nfinite_creator_saved', '1', $redirect ) );
		exit;
	}

	private static function handle_images( $creator_id ) {
		if ( empty( $_FILES ) ) { return; }

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( ! empty( $_FILES['creator_profile_photo']['name'] ) ) {
			$attachment_id = media_handle_upload( 'creator_profile_photo', $creator_id );
			if ( ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $creator_id, $attachment_id );
			}
		}

		if ( ! empty( $_FILES['creator_cover_image']['name'] ) ) {
			$attachment_id = media_handle_upload( 'creator_cover_image', $creator_id );
			if ( ! is_wp_error( $attachment_id ) ) {
				update_post_meta( $creator_id, '_nfinite_creator_cover_id', (int) $attachment_id );
			}
		}
	}

	private static function handle_tracks( $creator_id ) {
		$submitted = isset( $_POST['tracks'] ) && is_array( $_POST['tracks'] ) ? wp_unslash( $_POST['tracks'] ) : array();
		$tracks    = array();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		foreach ( $submitted as $index => $track ) {
			$title     = isset( $track['title'] ) ? sanitize_text_field( $track['title'] ) : '';
			$type      = isset( $track['type'] ) ? sanitize_text_field( $track['type'] ) : 'Song';
			$audio_url = isset( $track['audio_url'] ) ? esc_url_raw( $track['audio_url'] ) : '';
			$cover_url = isset( $track['cover_url'] ) ? esc_url_raw( $track['cover_url'] ) : '';
			$credits   = isset( $track['credits'] ) ? sanitize_textarea_field( $track['credits'] ) : '';

			$file_key = 'track_audio_' . absint( $index );
			if ( ! empty( $_FILES[ $file_key ]['name'] ) ) {
				$attachment_id = media_handle_upload( $file_key, $creator_id );
				if ( ! is_wp_error( $attachment_id ) ) {
					$uploaded_url = wp_get_attachment_url( $attachment_id );
					if ( $uploaded_url ) { $audio_url = $uploaded_url; }
				}
			}

			if ( ! $title && ! $audio_url ) { continue; }

			$tracks[] = array(
				'title'     => $title ?: __( 'Untitled Track', 'nfinite-creators' ),
				'type'      => $type,
				'audio_url' => $audio_url,
				'cover_url' => $cover_url,
				'credits'   => $credits,
			);
		}

		update_post_meta( $creator_id, '_nfinite_creator_tracks', $tracks );
	}
}
