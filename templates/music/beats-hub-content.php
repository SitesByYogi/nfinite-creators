<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="nfinite-discovery-hub nfinite-beats-hub">

	<section class="nfinite-music-hub__intro nfinite-discovery-hub__intro">
		<div>
			<span class="nfinite-eyebrow"><?php echo esc_html( $s['beats_eyebrow'] ); ?></span>
			<h1><?php echo esc_html( $s['beats_title'] ); ?></h1>
			<p><?php echo esc_html( $s['beats_intro'] ); ?></p>
		</div>

		<nav class="nfinite-music-hub__filters" aria-label="<?php esc_attr_e( 'Music discovery', 'nfinite-creators' ); ?>">
			<a href="<?php echo esc_url( get_post_type_archive_link( 'nfinite_release' ) ); ?>"><?php esc_html_e( 'Music', 'nfinite-creators' ); ?></a>
			<a class="is-active" href="<?php echo esc_url( home_url( '/beats/' ) ); ?>"><?php esc_html_e( 'Beats', 'nfinite-creators' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/videos/' ) ); ?>"><?php esc_html_e( 'Videos', 'nfinite-creators' ); ?></a>
		</nav>
	</section>

	<?php if ( $beats ) : ?>
		<?php
		$featured        = $beats[0];
		$playable_beats  = array_values( array_filter( $beats, static function( $beat ) { return ! empty( $beat['audioUrl'] ); } ) );
		$featured_index  = false;

		foreach ( $playable_beats as $index => $playable ) {
			if ( $playable['id'] === $featured['id'] ) {
				$featured_index = $index;
				break;
			}
		}
		?>

		<section class="nfinite-beats-featured">
			<div class="nfinite-beats-featured__art">
				<?php if ( ! empty( $featured['artwork'] ) ) : ?>
					<img src="<?php echo esc_url( $featured['artwork'] ); ?>" alt="">
				<?php endif; ?>

				<?php if ( false !== $featured_index ) : ?>
					<div
						class="nfinite-beats-featured__queue"
						data-nfinite-track-queue
						data-queue="<?php echo esc_attr( wp_json_encode( $playable_beats ) ); ?>"
					>
						<button
							class="nfinite-beats-featured__play"
							type="button"
							data-play-queue-index="<?php echo esc_attr( $featured_index ); ?>"
							aria-label="<?php esc_attr_e( 'Preview featured beat', 'nfinite-creators' ); ?>"
						>▶</button>
					</div>
				<?php endif; ?>
			</div>

			<div class="nfinite-beats-featured__copy">
				<span class="nfinite-eyebrow"><?php esc_html_e( 'Featured Beat', 'nfinite-creators' ); ?></span>
				<h2><?php echo esc_html( $featured['title'] ); ?></h2>

				<?php if ( ! empty( $featured['artist'] ) ) : ?>
					<p class="nfinite-beats-featured__artist"><?php echo esc_html( $featured['artist'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $featured['priceHtml'] ) ) : ?>
					<div class="nfinite-beats-featured__price"><?php echo wp_kses_post( $featured['priceHtml'] ); ?></div>
				<?php endif; ?>

				<div class="nfinite-beats-featured__actions">
					<?php if ( false !== $featured_index ) : ?>
						<div data-nfinite-track-queue data-queue="<?php echo esc_attr( wp_json_encode( $playable_beats ) ); ?>">
							<button class="nfinite-music-btn nfinite-music-btn--primary" type="button" data-play-queue-index="<?php echo esc_attr( $featured_index ); ?>">
								<?php esc_html_e( '▶ Preview Beat', 'nfinite-creators' ); ?>
							</button>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $featured['productUrl'] ) ) : ?>
						<a class="nfinite-music-btn" href="<?php echo esc_url( $featured['productUrl'] ); ?>"><?php echo esc_html( ! empty( $featured['buyLabel'] ) ? $featured['buyLabel'] : __( 'Buy Now', 'nfinite-creators' ) ); ?> →</a>
					<?php elseif ( ! empty( $featured['creatorId'] ) ) : ?>
						<a class="nfinite-music-btn" href="<?php echo esc_url( get_permalink( $featured['creatorId'] ) ); ?>"><?php esc_html_e( 'View Producer', 'nfinite-creators' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<section class="nfinite-music-hub-section">
			<header class="nfinite-music-hub-section__head">
				<div>
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Discover', 'nfinite-creators' ); ?></span>
					<h2><?php esc_html_e( 'Latest Beats', 'nfinite-creators' ); ?></h2>
				</div>
			</header>

			<div class="nfinite-beats-grid">
				<?php
				$queue_index = 0;
				foreach ( $beats as $beat ) :
					$has_audio = ! empty( $beat['audioUrl'] );
				?>
					<article class="nfinite-beat-card">
						<div class="nfinite-beat-card__art">
							<?php if ( ! empty( $beat['artwork'] ) ) : ?>
								<img src="<?php echo esc_url( $beat['artwork'] ); ?>" alt="">
							<?php endif; ?>

							<span class="nfinite-beat-card__type"><?php esc_html_e( 'Beat', 'nfinite-creators' ); ?></span>

							<?php if ( $has_audio ) : ?>
								<div data-nfinite-track-queue data-queue="<?php echo esc_attr( wp_json_encode( $playable_beats ) ); ?>">
									<button
										type="button"
										class="nfinite-beat-card__play"
										data-play-queue-index="<?php echo esc_attr( $queue_index ); ?>"
										aria-label="<?php echo esc_attr( sprintf( __( 'Preview %s', 'nfinite-creators' ), $beat['title'] ) ); ?>"
									>▶</button>
								</div>
								<?php $queue_index++; ?>
							<?php endif; ?>
						</div>

						<div class="nfinite-beat-card__body">
							<h3><?php echo esc_html( $beat['title'] ); ?></h3>

							<?php if ( ! empty( $beat['artist'] ) ) : ?>
								<p class="nfinite-beat-card__artist"><?php echo esc_html( $beat['artist'] ); ?></p>
							<?php endif; ?>

							<div class="nfinite-beat-card__footer">
								<?php if ( ! empty( $beat['priceHtml'] ) ) : ?>
									<div class="nfinite-beat-card__price"><?php echo wp_kses_post( $beat['priceHtml'] ); ?></div>
								<?php endif; ?>

								<?php if ( ! empty( $beat['productUrl'] ) ) : ?>
									<a class="nfinite-beat-card__cta" href="<?php echo esc_url( $beat['productUrl'] ); ?>"><?php echo esc_html( ! empty( $beat['buyLabel'] ) ? $beat['buyLabel'] : __( 'Buy Now', 'nfinite-creators' ) ); ?> →</a>
								<?php elseif ( ! empty( $beat['creatorId'] ) ) : ?>
									<a class="nfinite-beat-card__cta" href="<?php echo esc_url( get_permalink( $beat['creatorId'] ) ); ?>"><?php esc_html_e( 'View Producer', 'nfinite-creators' ); ?> →</a>
								<?php endif; ?>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php else : ?>
		<section class="nfinite-discovery-empty">
			<span class="nfinite-eyebrow"><?php esc_html_e( 'Beats', 'nfinite-creators' ); ?></span>
			<h2><?php esc_html_e( 'No beats are published yet.', 'nfinite-creators' ); ?></h2>
			<p><?php esc_html_e( 'Add an audio preview to a WooCommerce Beat/Instrumental product, publish an Nfinite track/release identified as a beat, or add a Creator track with the Beat type.', 'nfinite-creators' ); ?></p>
		</section>
	<?php endif; ?>

	<?php if ( $creators ) : ?>
		<section class="nfinite-music-hub-section nfinite-discovery-creators">
			<header class="nfinite-music-hub-section__head">
				<div>
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Discover', 'nfinite-creators' ); ?></span>
					<h2><?php esc_html_e( 'Producers & Creators', 'nfinite-creators' ); ?></h2>
				</div>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'nfinite_creator' ) ); ?>"><?php esc_html_e( 'Explore Creators', 'nfinite-creators' ); ?> →</a>
			</header>

			<div class="nfinite-creators-grid nfinite-music-hub-creators">
				<?php foreach ( $creators as $creator ) echo Nfinite_Creators_Frontend::creator_card( $creator->ID ); // phpcs:ignore ?>
			</div>
		</section>
	<?php endif; ?>
</div>
