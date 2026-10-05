<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Video {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_video_post_type' ) );
		add_action( 'add_meta_boxes_nfinite_creator', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'add_meta_boxes_nfinite_video', array( __CLASS__, 'add_library_meta_box' ) );
		add_action( 'save_post_nfinite_video', array( __CLASS__, 'save_library_video' ), 30, 2 );
		add_action( 'save_post_nfinite_creator', array( __CLASS__, 'save_admin' ), 30, 2 );
		add_shortcode( 'nfinite_creator_videos', array( __CLASS__, 'shortcode' ) );
	}


	public static function register_video_post_type() {
		register_post_type(
			'nfinite_video',
			array(
				'labels' => array(
					'name'          => __( 'Video Library', 'nfinite-creators' ),
					'singular_name' => __( 'Video', 'nfinite-creators' ),
					'add_new_item'  => __( 'Add Video', 'nfinite-creators' ),
					'edit_item'     => __( 'Edit Video', 'nfinite-creators' ),
					'menu_name'     => __( 'Video Library', 'nfinite-creators' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=nfinite_creator',
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'map_meta_cap' => true,
				'capability_type' => 'post',
			)
		);
	}

	public static function add_library_meta_box() {
		add_meta_box(
			'nfinite_video_details',
			__( 'PairOfDice Video Details', 'nfinite-creators' ),
			array( __CLASS__, 'render_library_meta_box' ),
			'nfinite_video',
			'normal',
			'high'
		);
	}

	public static function render_library_meta_box( $post ) {
		wp_nonce_field( 'nfinite_video_library_save', 'nfinite_video_library_nonce' );
		$url         = get_post_meta( $post->ID, '_nfinite_video_url', true );
		$type        = get_post_meta( $post->ID, '_nfinite_video_type', true );
		$source_name = get_post_meta( $post->ID, '_nfinite_video_source_name', true );
		$source_url  = get_post_meta( $post->ID, '_nfinite_video_source_url', true );
		$creator_id  = absint( get_post_meta( $post->ID, '_nfinite_video_creator_id', true ) );
		$channel     = sanitize_text_field( get_post_meta( $post->ID, '_nfinite_video_channel', true ) );
		$original_url= esc_url_raw( get_post_meta( $post->ID, '_nfinite_video_original_url', true ) );
		$featured    = (bool) get_post_meta( $post->ID, '_nfinite_video_featured', true );
		$tv          = (bool) get_post_meta( $post->ID, '_nfinite_video_tv', true );
		$tv_section  = sanitize_key( get_post_meta( $post->ID, '_nfinite_video_tv_section', true ) );
		$tv_featured = (bool) get_post_meta( $post->ID, '_nfinite_video_tv_featured', true );
		$tv_live     = (bool) get_post_meta( $post->ID, '_nfinite_video_tv_live', true );
		$tv_year     = sanitize_text_field( get_post_meta( $post->ID, '_nfinite_video_tv_year', true ) );
		$tv_runtime  = sanitize_text_field( get_post_meta( $post->ID, '_nfinite_video_tv_runtime', true ) );
		$tv_rating   = sanitize_text_field( get_post_meta( $post->ID, '_nfinite_video_tv_rating', true ) );
		$tv_backdrop = esc_url_raw( get_post_meta( $post->ID, '_nfinite_video_tv_backdrop', true ) );
		if ( ! $type ) { $type = 'Podcast'; }
		$creators = get_posts( array( 'post_type' => 'nfinite_creator', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		?>
		<div class="nfinite-admin-builder"><section class="nfinite-admin-section"><div class="nfinite-admin-grid">
			<div class="nfinite-admin-field nfinite-admin-field--full"><label><?php esc_html_e( 'YouTube / Vimeo / Internet Archive URL', 'nfinite-creators' ); ?></label><input class="widefat" type="url" name="nfinite_video_url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://www.youtube.com/watch?v=..."></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Video Type', 'nfinite-creators' ); ?></label><select class="widefat" name="nfinite_video_type"><?php self::render_video_type_options( $type ); ?></select></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'PairOfDice Creator (optional)', 'nfinite-creators' ); ?></label><select class="widefat" name="nfinite_video_creator_id"><option value="0"><?php esc_html_e( 'No linked creator', 'nfinite-creators' ); ?></option><?php foreach ( $creators as $creator ) : ?><option value="<?php echo esc_attr( $creator->ID ); ?>" <?php selected( $creator_id, $creator->ID ); ?>><?php echo esc_html( get_the_title( $creator ) ); ?></option><?php endforeach; ?></select></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'PairOfDice Channel / Programming', 'nfinite-creators' ); ?></label><select class="widefat" name="nfinite_video_channel"><?php foreach ( self::programming_channels() as $channel_value => $channel_label ) : ?><option value="<?php echo esc_attr( $channel_value ); ?>" <?php selected( $channel, $channel_value ); ?>><?php echo esc_html( $channel_label ); ?></option><?php endforeach; ?></select></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Source / Channel Name', 'nfinite-creators' ); ?></label><input class="widefat" type="text" name="nfinite_video_source_name" value="<?php echo esc_attr( $source_name ); ?>" placeholder="PBS, The Rotation, creator channel..."></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Source / Channel URL', 'nfinite-creators' ); ?></label><input class="widefat" type="url" name="nfinite_video_source_url" value="<?php echo esc_attr( $source_url ); ?>" placeholder="https://youtube.com/@channel"></div>
			<div class="nfinite-admin-field nfinite-admin-field--full"><label><?php esc_html_e( 'Original Source / Article URL', 'nfinite-creators' ); ?></label><input class="widefat" type="url" name="nfinite_video_original_url" value="<?php echo esc_attr( $original_url ); ?>" placeholder="https://publisher.com/story-or-original-post"><p class="description"><?php esc_html_e( 'Optional attribution link for news clips, podcast episodes, documentaries, or other curated media.', 'nfinite-creators' ); ?></p></div>
			<div class="nfinite-admin-field nfinite-admin-field--full"><label class="nfinite-admin-toggle"><input type="checkbox" name="nfinite_video_tv" value="1" <?php checked( $tv ); ?>><span><strong><?php esc_html_e( 'Include in PairOfDice TV', 'nfinite-creators' ); ?></strong><br><?php esc_html_e( 'Make this title eligible for the /tv/ streaming-style hub.', 'nfinite-creators' ); ?></span></label></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'TV Shelf', 'nfinite-creators' ); ?></label><select class="widefat" name="nfinite_video_tv_section"><?php foreach ( Nfinite_Creators_TV::sections() as $tv_value => $tv_label ) : ?><option value="<?php echo esc_attr( $tv_value ); ?>" <?php selected( $tv_section ?: 'featured', $tv_value ); ?>><?php echo esc_html( $tv_label ); ?></option><?php endforeach; ?></select></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Year', 'nfinite-creators' ); ?></label><input class="widefat" type="text" name="nfinite_video_tv_year" value="<?php echo esc_attr( $tv_year ); ?>" placeholder="2026"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Runtime', 'nfinite-creators' ); ?></label><input class="widefat" type="text" name="nfinite_video_tv_runtime" value="<?php echo esc_attr( $tv_runtime ); ?>" placeholder="1h 42m"></div>
			<div class="nfinite-admin-field"><label><?php esc_html_e( 'Rating / Label', 'nfinite-creators' ); ?></label><input class="widefat" type="text" name="nfinite_video_tv_rating" value="<?php echo esc_attr( $tv_rating ); ?>" placeholder="TV-14, NR, All Ages"></div>
			<div class="nfinite-admin-field nfinite-admin-field--full"><label><?php esc_html_e( 'TV Backdrop URL', 'nfinite-creators' ); ?></label><input class="widefat" type="url" name="nfinite_video_tv_backdrop" value="<?php echo esc_attr( $tv_backdrop ); ?>" placeholder="https://..."><p class="description"><?php esc_html_e( 'Optional wide hero artwork. Featured Image remains the poster/card artwork.', 'nfinite-creators' ); ?></p></div>
			<div class="nfinite-admin-field"><label class="nfinite-admin-toggle"><input type="checkbox" name="nfinite_video_tv_live" value="1" <?php checked( $tv_live ); ?>><span><strong><?php esc_html_e( 'Live Channel', 'nfinite-creators' ); ?></strong><br><?php esc_html_e( 'Use for live news and other continuous streams.', 'nfinite-creators' ); ?></span></label></div>
			<div class="nfinite-admin-field"><label class="nfinite-admin-toggle"><input type="checkbox" name="nfinite_video_tv_featured" value="1" <?php checked( $tv_featured ); ?>><span><strong><?php esc_html_e( 'Featured on PairOfDice TV', 'nfinite-creators' ); ?></strong><br><?php esc_html_e( 'Prioritize this title for the TV hero.', 'nfinite-creators' ); ?></span></label></div>
			<div class="nfinite-admin-field nfinite-admin-field--full"><label class="nfinite-admin-toggle"><input type="checkbox" name="nfinite_video_featured" value="1" <?php checked( $featured ); ?>><span><strong><?php esc_html_e( 'Featured Video', 'nfinite-creators' ); ?></strong><br><?php esc_html_e( 'Prioritize this video in the Videos hub hero/trending programming.', 'nfinite-creators' ); ?></span></label></div>
		</div><p class="nfinite-admin-help"><?php esc_html_e( 'Use the main editor for a short editorial description. The featured image is optional; YouTube/Vimeo artwork is used automatically when no image is supplied.', 'nfinite-creators' ); ?></p></section></div>
		<?php
	}

	public static function save_library_video( $post_id, $post ) {
		if ( ! isset( $_POST['nfinite_video_library_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_video_library_nonce'] ) ), 'nfinite_video_library_save' ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) || 'nfinite_video' !== $post->post_type ) { return; }
		$url = isset( $_POST['nfinite_video_url'] ) ? esc_url_raw( wp_unslash( $_POST['nfinite_video_url'] ) ) : '';
		if ( $url && ! self::is_supported_url( $url ) ) { $url = ''; }
		$type = isset( $_POST['nfinite_video_type'] ) ? sanitize_text_field( wp_unslash( $_POST['nfinite_video_type'] ) ) : 'Other';
		if ( ! in_array( $type, self::video_types(), true ) ) { $type = 'Other'; }
		update_post_meta( $post_id, '_nfinite_video_url', $url );
		update_post_meta( $post_id, '_nfinite_video_type', $type );
		update_post_meta( $post_id, '_nfinite_video_source_name', isset( $_POST['nfinite_video_source_name'] ) ? sanitize_text_field( wp_unslash( $_POST['nfinite_video_source_name'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_video_source_url', isset( $_POST['nfinite_video_source_url'] ) ? esc_url_raw( wp_unslash( $_POST['nfinite_video_source_url'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_video_creator_id', isset( $_POST['nfinite_video_creator_id'] ) ? absint( $_POST['nfinite_video_creator_id'] ) : 0 );
		$channel = isset( $_POST['nfinite_video_channel'] ) ? sanitize_text_field( wp_unslash( $_POST['nfinite_video_channel'] ) ) : '';
		if ( ! array_key_exists( $channel, self::programming_channels() ) ) { $channel = ''; }
		update_post_meta( $post_id, '_nfinite_video_channel', $channel );
		update_post_meta( $post_id, '_nfinite_video_original_url', isset( $_POST['nfinite_video_original_url'] ) ? esc_url_raw( wp_unslash( $_POST['nfinite_video_original_url'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_video_tv', ! empty( $_POST['nfinite_video_tv'] ) ? '1' : '' );
		$tv_section = isset( $_POST['nfinite_video_tv_section'] ) ? sanitize_key( wp_unslash( $_POST['nfinite_video_tv_section'] ) ) : 'featured';
		if ( ! array_key_exists( $tv_section, Nfinite_Creators_TV::sections() ) ) { $tv_section = 'featured'; }
		update_post_meta( $post_id, '_nfinite_video_tv_section', $tv_section );
		update_post_meta( $post_id, '_nfinite_video_tv_featured', ! empty( $_POST['nfinite_video_tv_featured'] ) ? '1' : '' );
		update_post_meta( $post_id, '_nfinite_video_tv_live', ! empty( $_POST['nfinite_video_tv_live'] ) ? '1' : '' );
		update_post_meta( $post_id, '_nfinite_video_tv_year', isset( $_POST['nfinite_video_tv_year'] ) ? sanitize_text_field( wp_unslash( $_POST['nfinite_video_tv_year'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_video_tv_runtime', isset( $_POST['nfinite_video_tv_runtime'] ) ? sanitize_text_field( wp_unslash( $_POST['nfinite_video_tv_runtime'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_video_tv_rating', isset( $_POST['nfinite_video_tv_rating'] ) ? sanitize_text_field( wp_unslash( $_POST['nfinite_video_tv_rating'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_video_tv_backdrop', isset( $_POST['nfinite_video_tv_backdrop'] ) ? esc_url_raw( wp_unslash( $_POST['nfinite_video_tv_backdrop'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_video_featured', ! empty( $_POST['nfinite_video_featured'] ) ? '1' : '' );
	}

	public static function add_meta_box() {
		add_meta_box(
			'nfinite_creator_videos',
			__( 'Videos & Media', 'nfinite-creators' ),
			array( __CLASS__, 'render_admin' ),
			'nfinite_creator',
			'normal',
			'default'
		);
	}

	public static function video_type_groups() {
		return array(
			__( 'Music & Performance', 'nfinite-creators' ) => array( 'Music Video', 'Performance', 'Live Performance', 'Freestyle' ),
			__( 'Shows & Conversations', 'nfinite-creators' ) => array( 'Podcast', 'Podcast Episode', 'Interview', 'Talk / Discussion', 'Live Stream', 'Vlog' ),
			__( 'News & Editorial', 'nfinite-creators' ) => array( 'News Clip', 'Breaking News', 'News Report', 'Commentary', 'Opinion', 'Analysis', 'Explainer' ),
			__( 'Long Form', 'nfinite-creators' ) => array( 'Documentary', 'Mini Documentary', 'Movie / Film', 'Feature / Story', 'Web Series', 'TV Episode', 'Short Film', 'Concert Film', 'Special' ),
			__( 'Entertainment & Culture', 'nfinite-creators' ) => array( 'Gaming', 'Sports', 'Fashion', 'Comedy', 'Trailer', 'Behind the Scenes', 'Short / Clip', 'Tutorial', 'Showreel', 'Other' ),
		);
	}

	public static function video_types() {
		$types = array();
		foreach ( self::video_type_groups() as $group ) {
			$types = array_merge( $types, $group );
		}
		// Preserve legacy values so existing saved videos remain editable and sortable.
		$types = array_merge( $types, array( 'News', 'Opinion', 'Movie', 'Short', 'Reel' ) );
		return array_values( array_unique( $types ) );
	}

	public static function programming_channels() {
		return array(
			'' => __( 'No PairOfDice channel', 'nfinite-creators' ),
			'The Wire' => 'The Wire',
			'The Rotation' => 'The Rotation',
			'Mic Check' => 'Mic Check',
			'757 Radar' => '757 Radar',
			'PairOfDice Gaming' => 'PairOfDice Gaming',
			'PairOfDice Originals' => 'PairOfDice Originals',
			'PairOfDice TV' => 'PairOfDice TV',
		);
	}

	public static function render_video_type_options( $selected = '' ) {
		foreach ( self::video_type_groups() as $label => $types ) {
			echo '<optgroup label="' . esc_attr( $label ) . '">';
			foreach ( $types as $type ) {
				echo '<option value="' . esc_attr( $type ) . '"' . selected( $selected, $type, false ) . '>' . esc_html( $type ) . '</option>';
			}
			echo '</optgroup>';
		}
		$grouped_types = array();
		foreach ( self::video_type_groups() as $group_types ) { $grouped_types = array_merge( $grouped_types, $group_types ); }
		if ( $selected && ! in_array( $selected, $grouped_types, true ) ) {
			echo '<option value="' . esc_attr( $selected ) . '" selected>' . esc_html( $selected . ' (legacy)' ) . '</option>';
		}
	}

	public static function hub_category( $type ) {
		$map = array(
			'Music Video' => 'Music Video', 'Performance' => 'Performance', 'Live Performance' => 'Performance', 'Freestyle' => 'Freestyle',
			'Podcast' => 'Podcast', 'Podcast Episode' => 'Podcast', 'Interview' => 'Interview', 'Talk / Discussion' => 'Interview',
			'Live Stream' => 'Live Stream', 'Vlog' => 'Vlog',
			'News Clip' => 'News', 'Breaking News' => 'News', 'News Report' => 'News', 'News' => 'News',
			'Commentary' => 'Commentary', 'Opinion' => 'Commentary', 'Analysis' => 'Commentary', 'Explainer' => 'Commentary',
			'Documentary' => 'Documentary', 'Mini Documentary' => 'Documentary',
			'Movie / Film' => 'Movie', 'Movie' => 'Movie', 'Feature / Story' => 'Documentary',
			'Gaming' => 'Gaming', 'Sports' => 'Sports', 'Fashion' => 'Fashion', 'Comedy' => 'Comedy', 'Trailer' => 'Trailer',
			'Behind the Scenes' => 'Behind the Scenes', 'Short / Clip' => 'Short', 'Short' => 'Short', 'Reel' => 'Short',
			'Tutorial' => 'Tutorial', 'Showreel' => 'Showreel', 'Other' => 'Other',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : ( $type ? $type : 'Other' );
	}

	public static function is_supported_url( $url ) {
		$url  = esc_url_raw( $url );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$host = preg_replace( '/^www\./', '', $host );

		return in_array(
			$host,
			array( 'youtube.com', 'm.youtube.com', 'youtu.be', 'vimeo.com', 'player.vimeo.com', 'archive.org', 'www.archive.org' ),
			true
		);
	}

	public static function sanitize_videos( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$videos          = array();
		$featured_found = false;
		$allowed_types   = self::video_types();

		foreach ( $raw as $video ) {
			if ( ! is_array( $video ) ) {
				continue;
			}

			$url = isset( $video['url'] ) ? esc_url_raw( $video['url'] ) : '';
			if ( ! $url || ! self::is_supported_url( $url ) ) {
				continue;
			}

			$type = isset( $video['type'] ) ? sanitize_text_field( $video['type'] ) : 'Other';
			if ( ! in_array( $type, $allowed_types, true ) ) {
				$type = 'Other';
			}

			$is_featured = ! $featured_found && ! empty( $video['featured'] );
			if ( $is_featured ) {
				$featured_found = true;
			}

			$channel = isset( $video['channel'] ) ? sanitize_text_field( $video['channel'] ) : '';
			if ( ! array_key_exists( $channel, self::programming_channels() ) ) { $channel = ''; }

			$videos[] = array(
				'title'        => isset( $video['title'] ) ? sanitize_text_field( $video['title'] ) : '',
				'url'          => $url,
				'type'         => $type,
				'channel'      => $channel,
				'source_name'  => isset( $video['source_name'] ) ? sanitize_text_field( $video['source_name'] ) : '',
				'source_url'   => isset( $video['source_url'] ) ? esc_url_raw( $video['source_url'] ) : '',
				'original_url' => isset( $video['original_url'] ) ? esc_url_raw( $video['original_url'] ) : '',
				'description'  => isset( $video['description'] ) ? sanitize_textarea_field( $video['description'] ) : '',
				'featured'     => $is_featured ? '1' : '',
				'tv'           => ! empty( $video['tv'] ) ? '1' : '',
				'tv_section'   => isset( $video['tv_section'] ) && array_key_exists( sanitize_key( $video['tv_section'] ), Nfinite_Creators_TV::sections() ) ? sanitize_key( $video['tv_section'] ) : 'independent',
				'tv_live'      => ! empty( $video['tv_live'] ) ? '1' : '',
				'tv_featured'  => ! empty( $video['tv_featured'] ) ? '1' : '',
			);
		}

		return $videos;
	}

	public static function render_admin( $post ) {
		wp_nonce_field( 'nfinite_creator_video_save', 'nfinite_creator_video_nonce' );

		$videos = get_post_meta( $post->ID, '_nfinite_creator_videos', true );
		if ( ! is_array( $videos ) ) {
			$videos = array();
		}
		?>
		<div class="nfinite-admin-builder nfinite-admin-video-builder">
			<section class="nfinite-admin-section">
				<div class="nfinite-admin-section__head">
					<span class="nfinite-admin-section__number">▶</span>
					<div>
						<h3><?php esc_html_e( 'Profile Videos', 'nfinite-creators' ); ?></h3>
						<p><?php esc_html_e( 'Add YouTube or Vimeo videos for the public creator profile and Creator Kit / EPK. Drag to reorder and select one Featured Video.', 'nfinite-creators' ); ?></p>
					</div>
				</div>

				<div class="nfinite-admin-video-list" data-admin-video-list>
					<?php foreach ( $videos as $index => $video ) : ?>
						<?php self::render_admin_row( $index, $video ); ?>
					<?php endforeach; ?>
				</div>

				<p>
					<button type="button" class="button button-secondary" data-add-admin-video><?php esc_html_e( '+ Add Video', 'nfinite-creators' ); ?></button>
				</p>

				<p class="nfinite-admin-help"><?php esc_html_e( 'Supported providers in this release: YouTube and Vimeo. Video files are not uploaded to WordPress; the profile embeds the hosted video.', 'nfinite-creators' ); ?></p>
			</section>
		</div>
		<?php
	}

	private static function render_admin_row( $index, $video ) {
		$title       = isset( $video['title'] ) ? $video['title'] : '';
		$url         = isset( $video['url'] ) ? $video['url'] : '';
		$type        = isset( $video['type'] ) ? $video['type'] : 'Music Video';
		$description = isset( $video['description'] ) ? $video['description'] : '';
		$featured    = ! empty( $video['featured'] );
		?>
		<div class="nfinite-admin-track nfinite-admin-video" data-admin-video>
			<div class="nfinite-admin-track__head">
				<strong><span class="dashicons dashicons-menu nfinite-admin-track__handle"></span><?php esc_html_e( 'Video', 'nfinite-creators' ); ?> <span data-video-number><?php echo esc_html( $index + 1 ); ?></span></strong>
				<button type="button" class="button-link-delete" data-remove-admin-video><?php esc_html_e( 'Remove', 'nfinite-creators' ); ?></button>
			</div>

			<div class="nfinite-admin-track__body">
				<div class="nfinite-admin-grid">
					<div class="nfinite-admin-field">
						<label><?php esc_html_e( 'Title', 'nfinite-creators' ); ?></label>
						<input class="widefat" type="text" name="videos[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $title ); ?>">
					</div>

					<div class="nfinite-admin-field">
						<label><?php esc_html_e( 'Video Type', 'nfinite-creators' ); ?></label>
						<select class="widefat" name="videos[<?php echo esc_attr( $index ); ?>][type]">
							<?php self::render_video_type_options( $type ); ?>
						</select>
					</div>

					<div class="nfinite-admin-field">
						<label><?php esc_html_e( 'PairOfDice Channel / Programming', 'nfinite-creators' ); ?></label>
						<select class="widefat" name="videos[<?php echo esc_attr( $index ); ?>][channel]">
							<?php foreach ( self::programming_channels() as $channel_value => $channel_label ) : ?>
								<option value="<?php echo esc_attr( $channel_value ); ?>" <?php selected( isset( $video['channel'] ) ? $video['channel'] : '', $channel_value ); ?>><?php echo esc_html( $channel_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="nfinite-admin-field nfinite-admin-field--full">
						<label><?php esc_html_e( 'YouTube / Vimeo / Internet Archive URL', 'nfinite-creators' ); ?></label>
						<input class="widefat" type="url" name="videos[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://www.youtube.com/watch?v=...">
					</div>

					<div class="nfinite-admin-field"><label><?php esc_html_e( 'Source / Publisher', 'nfinite-creators' ); ?></label><input class="widefat" type="text" name="videos[<?php echo esc_attr( $index ); ?>][source_name]" value="<?php echo esc_attr( isset( $video['source_name'] ) ? $video['source_name'] : '' ); ?>" placeholder="Channel, publisher, podcast..."></div>
					<div class="nfinite-admin-field"><label><?php esc_html_e( 'Source / Publisher URL', 'nfinite-creators' ); ?></label><input class="widefat" type="url" name="videos[<?php echo esc_attr( $index ); ?>][source_url]" value="<?php echo esc_attr( isset( $video['source_url'] ) ? $video['source_url'] : '' ); ?>"></div>
					<div class="nfinite-admin-field nfinite-admin-field--full"><label><?php esc_html_e( 'Original Source / Article URL', 'nfinite-creators' ); ?></label><input class="widefat" type="url" name="videos[<?php echo esc_attr( $index ); ?>][original_url]" value="<?php echo esc_attr( isset( $video['original_url'] ) ? $video['original_url'] : '' ); ?>" placeholder="https://publisher.com/original-story"></div>

					<div class="nfinite-admin-field nfinite-admin-field--full">
						<label><?php esc_html_e( 'Description', 'nfinite-creators' ); ?></label>
						<textarea class="widefat" rows="3" name="videos[<?php echo esc_attr( $index ); ?>][description]"><?php echo esc_textarea( $description ); ?></textarea>
					</div>

					<div class="nfinite-admin-field"><label class="nfinite-admin-toggle"><input type="checkbox" name="videos[<?php echo esc_attr( $index ); ?>][tv]" value="1" <?php checked( ! empty( $video['tv'] ) ); ?>><span><strong><?php esc_html_e( 'Submit to PairOfDice TV', 'nfinite-creators' ); ?></strong><br><?php esc_html_e( 'Makes this creator-owned video eligible for /tv/.', 'nfinite-creators' ); ?></span></label></div>
					<div class="nfinite-admin-field"><label><?php esc_html_e( 'TV Shelf', 'nfinite-creators' ); ?></label><select class="widefat" name="videos[<?php echo esc_attr( $index ); ?>][tv_section]"><?php foreach ( Nfinite_Creators_TV::sections() as $tv_value => $tv_label ) : ?><option value="<?php echo esc_attr( $tv_value ); ?>" <?php selected( ! empty( $video['tv_section'] ) ? $video['tv_section'] : 'independent', $tv_value ); ?>><?php echo esc_html( $tv_label ); ?></option><?php endforeach; ?></select></div>
					<div class="nfinite-admin-field"><label class="nfinite-admin-toggle"><input type="checkbox" name="videos[<?php echo esc_attr( $index ); ?>][tv_live]" value="1" <?php checked( ! empty( $video['tv_live'] ) ); ?>><span><?php esc_html_e( 'Live programming', 'nfinite-creators' ); ?></span></label></div>
					<div class="nfinite-admin-field"><label class="nfinite-admin-toggle"><input type="checkbox" name="videos[<?php echo esc_attr( $index ); ?>][tv_featured]" value="1" <?php checked( ! empty( $video['tv_featured'] ) ); ?>><span><?php esc_html_e( 'TV featured candidate', 'nfinite-creators' ); ?></span></label></div>
					<div class="nfinite-admin-field nfinite-admin-field--full">
						<label class="nfinite-admin-toggle">
							<input type="checkbox" data-featured-video name="videos[<?php echo esc_attr( $index ); ?>][featured]" value="1" <?php checked( $featured ); ?>>
							<span><strong><?php esc_html_e( 'Featured Video', 'nfinite-creators' ); ?></strong><br><?php esc_html_e( 'Show this video first and give it the primary video treatment.', 'nfinite-creators' ); ?></span>
						</label>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function save_admin( $post_id, $post ) {
		if (
			! isset( $_POST['nfinite_creator_video_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_creator_video_nonce'] ) ), 'nfinite_creator_video_save' )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || 'nfinite_creator' !== $post->post_type ) {
			return;
		}

		$raw = isset( $_POST['videos'] ) && is_array( $_POST['videos'] ) ? wp_unslash( $_POST['videos'] ) : array();
		update_post_meta( $post_id, '_nfinite_creator_videos', self::sanitize_videos( $raw ) );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'creator' => get_the_ID(),
				'title'   => __( 'Videos', 'nfinite-creators' ),
			),
			$atts,
			'nfinite_creator_videos'
		);

		return self::render_gallery( absint( $atts['creator'] ), $atts['title'] );
	}

	public static function render_gallery( $creator_id, $title = '' ) {
		if ( 'nfinite_creator' !== get_post_type( $creator_id ) ) {
			return '';
		}

		$videos = get_post_meta( $creator_id, '_nfinite_creator_videos', true );
		if ( ! is_array( $videos ) || empty( $videos ) ) {
			return '';
		}

		usort(
			$videos,
			function ( $a, $b ) {
				return ( ! empty( $b['featured'] ) ? 1 : 0 ) <=> ( ! empty( $a['featured'] ) ? 1 : 0 );
			}
		);

		ob_start();
		?>
		<section class="nfinite-creator-video-section">
			<div class="nfinite-creator-video-section__head">
				<span class="nfinite-eyebrow"><?php esc_html_e( 'Watch', 'nfinite-creators' ); ?></span>
				<h2><?php echo esc_html( $title ?: __( 'Videos', 'nfinite-creators' ) ); ?></h2>
			</div>

			<div class="nfinite-creator-video-grid nfinite-creator-video-grid--count-<?php echo esc_attr( min( 3, count( $videos ) ) ); ?>">
				<?php foreach ( $videos as $video ) : ?>
					<?php
					$embed = wp_oembed_get(
						$video['url'],
						array(
							'width' => 1200,
						)
					);
					if ( ! $embed ) {
						continue;
					}
					$allowed_embed_html = array(
						'iframe' => array(
							'src'             => true,
							'width'           => true,
							'height'          => true,
							'frameborder'     => true,
							'allow'           => true,
							'allowfullscreen' => true,
							'title'           => true,
							'loading'         => true,
							'referrerpolicy'  => true,
						),
					);
					?>
					<article class="nfinite-creator-video-card<?php echo ! empty( $video['featured'] ) ? ' is-featured' : ''; ?>">
						<div class="nfinite-creator-video-embed"><?php echo wp_kses( $embed, $allowed_embed_html ); ?></div>
						<div class="nfinite-creator-video-card__body">
							<span class="nfinite-eyebrow"><?php echo esc_html( isset( $video['type'] ) ? $video['type'] : __( 'Video', 'nfinite-creators' ) ); ?></span>
							<?php if ( ! empty( $video['title'] ) ) : ?><h3><?php echo esc_html( $video['title'] ); ?></h3><?php endif; ?>
							<?php if ( ! empty( $video['description'] ) ) : ?><p><?php echo esc_html( $video['description'] ); ?></p><?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}
}
