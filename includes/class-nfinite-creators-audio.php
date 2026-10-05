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

		foreach ( $tracks as $index => $track ) {
            $url = isset( $track['audio_url'] ) ? esc_url_raw( $track['audio_url'] ) : '';
            $links = array( 'soundcloud' => isset( $track['soundcloud_url'] ) ? esc_url_raw( $track['soundcloud_url'] ) : '', 'spotify' => isset( $track['spotify_url'] ) ? esc_url_raw( $track['spotify_url'] ) : '', 'apple_music' => isset( $track['apple_music_url'] ) ? esc_url_raw( $track['apple_music_url'] ) : '' );
            $source_payload = Nfinite_Creators_Audio_Sources::payload( isset( $track['playback_source'] ) ? $track['playback_source'] : 'local', $url, $links ); if ( empty( $source_payload['playable'] ) ) { continue; }

			$track_type = isset( $track['type'] ) ? sanitize_text_field( $track['type'] ) : 'Single';
			if ( 'Song' === $track_type ) {
				$track_type = 'Single';
			}

			$commerce = class_exists( 'Nfinite_Creators_Track_Commerce' )
				? Nfinite_Creators_Track_Commerce::legacy_track( $track )
				: array();

			$legacy_key          = 'creator-beat-' . absint( $creator_id ) . '-' . absint( $index );
			$legacy_analytics_id = absint( sprintf( '%u', crc32( $legacy_key ) ) );

			$valid[] = array_merge( array(
				'creator_id'            => absint( $creator_id ),
				'analytics_object_id'   => $legacy_analytics_id,
				'analytics_object_type' => 'legacy_track',
				'title'      => isset( $track['title'] ) ? sanitize_text_field( $track['title'] ) : __( 'Untitled Track', 'nfinite-creators' ),
				'type'       => $track_type,
				'audio_url'  => $url,
				'cover_url'  => isset( $track['cover_url'] ) ? esc_url_raw( $track['cover_url'] ) : '',
				'buy_url'    => ! empty( $commerce['url'] ) ? esc_url_raw( $commerce['url'] ) : '',
				'buy_label'  => ! empty( $commerce['label'] ) ? sanitize_text_field( $commerce['label'] ) : __( 'Buy Now', 'nfinite-creators' ),
				'price_html' => ! empty( $commerce['price_html'] ) ? $commerce['price_html'] : '',
				'price_text' => ! empty( $commerce['price_text'] ) ? sanitize_text_field( $commerce['price_text'] ) : '',
				'licenses'   => ! empty( $commerce['licenses'] ) && is_array( $commerce['licenses'] ) ? $commerce['licenses'] : array(),
			), $source_payload );
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
					<strong data-track-title><?php echo esc_html( $valid[0]['title'] ); ?></strong><small class="nfinite-audio-source-label" data-source-label><?php echo esc_html( $valid[0]['sourceLabel'] ); ?></small>

					<div class="nfinite-audio-controls">
						<button type="button" class="nfinite-audio-play" data-play aria-label="<?php esc_attr_e( 'Play or pause', 'nfinite-creators' ); ?>">▶</button>
						<div class="nfinite-audio-progress-wrap">
							<input type="range" min="0" max="100" value="0" step="0.1" data-progress aria-label="<?php esc_attr_e( 'Track progress', 'nfinite-creators' ); ?>">
							<div class="nfinite-audio-time"><span data-current-time>0:00</span><span data-duration>0:00</span></div>
						</div>
					</div>

					<div class="nfinite-audio-current__commerce" data-current-commerce <?php echo empty( $valid[0]['buy_url'] ) ? 'hidden' : ''; ?>>
						<span class="nfinite-audio-current__price" data-current-price <?php echo empty( $valid[0]['price_text'] ) ? 'hidden' : ''; ?>><?php echo esc_html( $valid[0]['price_text'] ); ?></span>
						<a
							class="nfinite-audio-buy"
							data-current-buy
							href="<?php echo esc_url( $valid[0]['buy_url'] ?: '#' ); ?>"
						><?php echo esc_html( $valid[0]['buy_label'] ); ?> →</a>
					</div>
					<?php if ( ! empty( $valid[0]['licenses'] ) ) : ?>
						<div class="nfinite-audio-license-list">
							<?php foreach ( $valid[0]['licenses'] as $license ) : ?>
								<span><b><?php echo esc_html( $license['label'] ); ?></b> <?php echo wp_kses_post( function_exists( 'wc_price' ) ? wc_price( $license['price'] ) : '$' . number_format_i18n( $license['price'], 2 ) ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<audio preload="metadata" data-audio <?php echo 'local' === $valid[0]['sourceType'] ? 'src="' . esc_url( $valid[0]['audio_url'] ) . '"' : ''; ?>></audio><div class="nfinite-audio-provider" data-provider-player <?php echo 'local' === $valid[0]['sourceType'] ? 'hidden' : ''; ?>><?php if ( 'local' !== $valid[0]['sourceType'] && ! empty( $valid[0]['embedUrl'] ) ) : ?><iframe src="<?php echo esc_url( $valid[0]['embedUrl'] ); ?>" title="<?php echo esc_attr( $valid[0]['sourceLabel'] ); ?>" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"></iframe><?php endif; ?></div>

			<div class="nfinite-audio-playlist">
				<?php foreach ( $valid as $index => $track ) : ?>
					<div
						class="nfinite-audio-track<?php echo 0 === $index ? ' is-active' : ''; ?>"
						data-track
					>
						<button
							type="button"
							class="nfinite-audio-track__play-area"
							data-track-play
							data-src="<?php echo esc_url( $track['audio_url'] ); ?>"
							data-title="<?php echo esc_attr( $track['title'] ); ?>"
							data-type="<?php echo esc_attr( $track['type'] ); ?>"
							data-cover="<?php echo esc_url( $track['cover_url'] ); ?>"
							data-buy-url="<?php echo esc_url( $track['buy_url'] ); ?>"
							data-buy-label="<?php echo esc_attr( $track['buy_label'] ); ?>"
							data-price-text="<?php echo esc_attr( $track['price_text'] ); ?>"
							data-source-type="<?php echo esc_attr( $track['sourceType'] ); ?>"
							data-source-label="<?php echo esc_attr( $track['sourceLabel'] ); ?>"
							data-embed-url="<?php echo esc_url( $track['embedUrl'] ); ?>"
							data-analytics-creator-id="<?php echo esc_attr( $track['creator_id'] ); ?>"
							data-analytics-object-id="<?php echo esc_attr( $track['analytics_object_id'] ); ?>"
							data-analytics-object-type="<?php echo esc_attr( $track['analytics_object_type'] ); ?>"
						>
							<span class="nfinite-audio-track__index"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
							<span class="nfinite-audio-track__name"><?php echo esc_html( $track['title'] ); ?></span>
							<span class="nfinite-audio-track__type"><?php echo esc_html( $track['type'] ); ?> · <?php echo esc_html( $track['sourceLabel'] ); ?></span>
						</button>

						<?php if ( $track['buy_url'] ) : ?>
							<div class="nfinite-audio-track__commerce">
								<?php if ( ! empty( $track['licenses'] ) ) : ?>
									<span class="nfinite-audio-track__license-count"><?php echo esc_html( count( $track['licenses'] ) ); ?> <?php echo esc_html( _n( 'license', 'licenses', count( $track['licenses'] ), 'nfinite-creators' ) ); ?></span>
								<?php endif; ?>
								<?php if ( $track['price_text'] ) : ?>
									<span class="nfinite-audio-track__price"><?php echo esc_html( $track['price_text'] ); ?></span>
								<?php endif; ?>
								<a class="nfinite-audio-track__buy" href="<?php echo esc_url( $track['buy_url'] ); ?>">
									<?php echo esc_html( $track['buy_label'] ); ?> →
								</a>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}
}
