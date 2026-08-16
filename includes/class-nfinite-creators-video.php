<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Video {
	public static function init() {
		add_action( 'add_meta_boxes_nfinite_creator', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_nfinite_creator', array( __CLASS__, 'save_admin' ), 30, 2 );
		add_shortcode( 'nfinite_creator_videos', array( __CLASS__, 'shortcode' ) );
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

	public static function video_types() {
		return array(
			'Music Video',
			'Performance',
			'Interview',
			'Reel',
			'Behind the Scenes',
			'Tutorial',
			'Showreel',
			'Other',
		);
	}

	public static function is_supported_url( $url ) {
		$url  = esc_url_raw( $url );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$host = preg_replace( '/^www\./', '', $host );

		return in_array(
			$host,
			array( 'youtube.com', 'm.youtube.com', 'youtu.be', 'vimeo.com', 'player.vimeo.com' ),
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

			$videos[] = array(
				'title'       => isset( $video['title'] ) ? sanitize_text_field( $video['title'] ) : '',
				'url'         => $url,
				'type'        => $type,
				'description' => isset( $video['description'] ) ? sanitize_textarea_field( $video['description'] ) : '',
				'featured'    => $is_featured ? '1' : '',
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
							<?php foreach ( self::video_types() as $video_type ) : ?>
								<option value="<?php echo esc_attr( $video_type ); ?>" <?php selected( $type, $video_type ); ?>><?php echo esc_html( $video_type ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="nfinite-admin-field nfinite-admin-field--full">
						<label><?php esc_html_e( 'YouTube / Vimeo URL', 'nfinite-creators' ); ?></label>
						<input class="widefat" type="url" name="videos[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://www.youtube.com/watch?v=...">
					</div>

					<div class="nfinite-admin-field nfinite-admin-field--full">
						<label><?php esc_html_e( 'Description', 'nfinite-creators' ); ?></label>
						<textarea class="widefat" rows="3" name="videos[<?php echo esc_attr( $index ); ?>][description]"><?php echo esc_textarea( $description ); ?></textarea>
					</div>

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
