<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Audio {
	public static function init() {
		add_shortcode( 'nfinite_creator_audio', array( __CLASS__, 'shortcode' ) );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'creator' => get_the_ID(),
				'title'   => __( 'Featured Audio', 'nfinite-creators' ),
			),
			$atts,
			'nfinite_creator_audio'
		);

		$creator_id = absint( $atts['creator'] );
		if ( 'nfinite_creator' !== get_post_type( $creator_id ) ) { return ''; }

		return self::render_player( $creator_id, $atts['title'] );
	}

	public static function render_player( $creator_id, $title = '' ) {
		$tracks = get_post_meta( $creator_id, '_nfinite_creator_tracks', true );
		if ( ! is_array( $tracks ) || empty( $tracks ) ) { return ''; }

		$valid = array();

		foreach ( $tracks as $track ) {
			$url = isset( $track['audio_url'] ) ? esc_url_raw( $track['audio_url'] ) : '';
			if ( ! $url ) { continue; }

			$valid[] = array(
				'title'     => isset( $track['title'] ) ? sanitize_text_field( $track['title'] ) : __( 'Untitled Track', 'nfinite-creators' ),
				'type'      => isset( $track['type'] ) ? sanitize_text_field( $track['type'] ) : __( 'Track', 'nfinite-creators' ),
				'audio_url' => $url,
				'cover_url' => isset( $track['cover_url'] ) ? esc_url_raw( $track['cover_url'] ) : '',
			);
		}

		if ( empty( $valid ) ) { return ''; }

		$player_id = 'nfinite-player-' . wp_rand( 1000, 99999 );

		ob_start();
		?>
		<section class="nfinite-audio-player" id="<?php echo esc_attr( $player_id ); ?>" data-nfinite-player>
			<div class="nfinite-audio-player__head">
				<div>
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Listen', 'nfinite-creators' ); ?></span>
					<h2><?php echo esc_html( $title ?: __( 'Featured Audio', 'nfinite-creators' ) ); ?></h2>
				</div>
			</div>

			<div class="nfinite-audio-now">
				<div class="nfinite-audio-cover" data-cover>
					<?php if ( ! empty( $valid[0]['cover_url'] ) ) : ?>
						<img src="<?php echo esc_url( $valid[0]['cover_url'] ); ?>" alt="">
					<?php endif; ?>
				</div>

				<div class="nfinite-audio-current">
					<span class="nfinite-audio-current__type" data-track-type><?php echo esc_html( $valid[0]['type'] ); ?></span>
					<strong data-track-title><?php echo esc_html( $valid[0]['title'] ); ?></strong>

					<div class="nfinite-audio-controls">
						<button type="button" class="nfinite-audio-play" data-play aria-label="<?php esc_attr_e( 'Play or pause', 'nfinite-creators' ); ?>">▶</button>
						<div class="nfinite-audio-progress-wrap">
							<input type="range" min="0" max="100" value="0" step="0.1" data-progress aria-label="<?php esc_attr_e( 'Track progress', 'nfinite-creators' ); ?>">
							<div class="nfinite-audio-time"><span data-current-time>0:00</span><span data-duration>0:00</span></div>
						</div>
						<input type="range" min="0" max="1" value="1" step="0.05" data-volume aria-label="<?php esc_attr_e( 'Volume', 'nfinite-creators' ); ?>">
					</div>
				</div>
			</div>

			<audio preload="metadata" data-audio src="<?php echo esc_url( $valid[0]['audio_url'] ); ?>"></audio>

			<div class="nfinite-audio-playlist">
				<?php foreach ( $valid as $index => $track ) : ?>
					<button
						type="button"
						class="nfinite-audio-track<?php echo 0 === $index ? ' is-active' : ''; ?>"
						data-track
						data-src="<?php echo esc_url( $track['audio_url'] ); ?>"
						data-title="<?php echo esc_attr( $track['title'] ); ?>"
						data-type="<?php echo esc_attr( $track['type'] ); ?>"
						data-cover="<?php echo esc_url( $track['cover_url'] ); ?>"
					>
						<span class="nfinite-audio-track__index"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
						<span class="nfinite-audio-track__name"><?php echo esc_html( $track['title'] ); ?></span>
						<span class="nfinite-audio-track__type"><?php echo esc_html( $track['type'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}
}
