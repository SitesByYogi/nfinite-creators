<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Available variables:
 * $settings, $featured, $releases, $tracks, $beats, $creators, $videos.
 */
?>
<div class="nfinite-music-hub">
	<section class="nfinite-music-hub__intro">
		<div>
			<span class="nfinite-eyebrow"><?php echo esc_html( $settings['eyebrow'] ); ?></span>
			<h1><?php echo esc_html( $settings['title'] ); ?></h1>
			<?php if ( $settings['intro'] ) : ?><p><?php echo esc_html( $settings['intro'] ); ?></p><?php endif; ?>
		</div>

		<nav class="nfinite-music-hub__filters" aria-label="<?php esc_attr_e( 'Explore music', 'nfinite-creators' ); ?>">
			<a href="<?php echo esc_url( class_exists( 'Nfinite_Creators_Release_Library' ) ? Nfinite_Creators_Release_Library::page_url() : get_post_type_archive_link( 'nfinite_release' ) ); ?>"><?php esc_html_e( 'All Releases', 'nfinite-creators' ); ?></a>
			<?php
			$release_types = get_terms(
				array(
					'taxonomy'   => 'nfinite_release_type',
					'hide_empty' => true,
				)
			);
			if ( ! is_wp_error( $release_types ) ) :
				foreach ( $release_types as $release_type ) :
			?>
				<a href="<?php echo esc_url( get_term_link( $release_type ) ); ?>"><?php echo esc_html( $release_type->name ); ?></a>
			<?php
				endforeach;
			endif;
			?>
		</nav>
	</section>

	<?php if ( $featured ) : ?>
		<?php
		$featured_id      = $featured->ID;
		$featured_artwork = get_the_post_thumbnail_url( $featured_id, 'full' );
		$featured_creator = absint( get_post_meta( $featured_id, '_nfinite_release_creator_id', true ) );
		$featured_type    = Nfinite_Creators_Music_Query::release_type_label( $featured_id );
		$featured_queue   = Nfinite_Creators_Music_Query::queue_for_release( $featured_id );
		?>
		<section class="nfinite-music-hub-featured<?php echo $featured_artwork ? ' has-artwork' : ''; ?>"<?php echo $featured_artwork ? ' style="--nfinite-featured-art:url(' . esc_url( $featured_artwork ) . ')"' : ''; ?>>
			<div class="nfinite-music-hub-featured__shade"></div>
			<div class="nfinite-music-hub-featured__content">
				<div class="nfinite-music-hub-featured__art">
					<?php if ( $featured_artwork ) : ?><img src="<?php echo esc_url( $featured_artwork ); ?>" alt=""><?php endif; ?>
				</div>
				<div class="nfinite-music-hub-featured__copy">
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Featured Release', 'nfinite-creators' ); ?></span>
					<span class="nfinite-music-hub-featured__type"><?php echo esc_html( $featured_type ); ?></span>
					<h2><?php echo esc_html( get_the_title( $featured_id ) ); ?></h2>
					<?php if ( $featured_creator ) : ?>
						<p><a href="<?php echo esc_url( get_permalink( $featured_creator ) ); ?>"><?php echo esc_html( get_the_title( $featured_creator ) ); ?></a></p>
					<?php endif; ?>

					<div class="nfinite-music-hub-featured__actions">
						<?php if ( $featured_queue ) : ?>
							<div class="nfinite-music-hub-inline-queue" data-nfinite-track-queue data-queue="<?php echo esc_attr( wp_json_encode( $featured_queue ) ); ?>">
								<button type="button" class="nfinite-music-btn nfinite-music-btn--primary" data-play-queue-index="0"><?php esc_html_e( '▶ Play', 'nfinite-creators' ); ?></button>
							</div>
						<?php endif; ?>
						<a class="nfinite-music-btn" href="<?php echo esc_url( get_permalink( $featured_id ) ); ?>"><?php esc_html_e( 'View Release', 'nfinite-creators' ); ?></a>
					</div>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $releases ) : ?>
		<section class="nfinite-music-hub-section">
			<header class="nfinite-music-hub-section__head">
				<div>
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Fresh', 'nfinite-creators' ); ?></span>
					<h2><?php esc_html_e( 'New Releases', 'nfinite-creators' ); ?></h2>
				</div>
			</header>
			<div class="nfinite-music-hub-release-grid">
				<?php foreach ( $releases as $release ) : ?>
					<?php echo Nfinite_Creators_Music_Hub::release_card( $release->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $tracks ) : ?>
		<section class="nfinite-music-hub-section nfinite-music-hub-tracks">
			<header class="nfinite-music-hub-section__head">
				<div>
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Listen Now', 'nfinite-creators' ); ?></span>
					<h2><?php esc_html_e( 'Latest Tracks', 'nfinite-creators' ); ?></h2>
				</div>
			</header>

			<div class="nfinite-music-hub-tracklist" data-nfinite-track-queue data-queue="<?php echo esc_attr( wp_json_encode( array_values( $tracks ) ) ); ?>">
				<?php foreach ( $tracks as $index => $track ) : ?>
					<button type="button" class="nfinite-music-hub-track" data-play-queue-index="<?php echo esc_attr( $index ); ?>">
						<span class="nfinite-music-hub-track__play">▶</span>
						<span class="nfinite-music-hub-track__art">
							<?php if ( ! empty( $track['artwork'] ) ) : ?><img src="<?php echo esc_url( $track['artwork'] ); ?>" alt=""><?php endif; ?>
						</span>
						<span class="nfinite-music-hub-track__copy">
							<strong><?php echo esc_html( $track['title'] ); ?><?php echo ! empty( $track['explicit'] ) ? ' · E' : ''; ?></strong>
							<small><?php echo esc_html( $track['artist'] ); ?><?php echo ! empty( $track['release'] ) ? ' · ' . esc_html( $track['release'] ) : ''; ?></small>
						</span>
						<span class="nfinite-music-hub-track__duration"><?php echo esc_html( ! empty( $track['duration'] ) ? $track['duration'] : '' ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $beats ) : ?>
		<section class="nfinite-music-hub-section">
			<header class="nfinite-music-hub-section__head">
				<div>
					<span class="nfinite-eyebrow"><?php esc_html_e( 'For Artists', 'nfinite-creators' ); ?></span>
					<h2><?php esc_html_e( 'Beats & Instrumentals', 'nfinite-creators' ); ?></h2>
				</div>
			</header>

			<div class="nfinite-music-hub-beat-grid" data-nfinite-track-queue data-queue="<?php echo esc_attr( wp_json_encode( array_values( $beats ) ) ); ?>">
				<?php foreach ( $beats as $index => $beat ) : ?>
					<article class="nfinite-music-hub-beat-card">
						<button type="button" class="nfinite-music-hub-beat-card__art" data-play-queue-index="<?php echo esc_attr( $index ); ?>">
							<?php if ( ! empty( $beat['artwork'] ) ) : ?><img src="<?php echo esc_url( $beat['artwork'] ); ?>" alt=""><?php endif; ?>
							<span aria-hidden="true">▶</span>
						</button>
						<div class="nfinite-music-hub-beat-card__body">
							<span class="nfinite-eyebrow"><?php esc_html_e( 'Beat', 'nfinite-creators' ); ?></span>
							<h3><?php echo esc_html( $beat['title'] ); ?></h3>
							<?php if ( ! empty( $beat['creatorId'] ) ) : ?>
								<p><a href="<?php echo esc_url( get_permalink( $beat['creatorId'] ) ); ?>"><?php echo esc_html( $beat['artist'] ); ?></a></p>
							<?php else : ?>
								<p><?php echo esc_html( $beat['artist'] ); ?></p>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $creators ) : ?>
		<section class="nfinite-music-hub-section">
			<header class="nfinite-music-hub-section__head">
				<div>
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Discover', 'nfinite-creators' ); ?></span>
					<h2><?php esc_html_e( 'Creators to Know', 'nfinite-creators' ); ?></h2>
				</div>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'nfinite_creator' ) ); ?>"><?php esc_html_e( 'Explore Creators', 'nfinite-creators' ); ?> →</a>
			</header>

			<div class="nfinite-creators-grid nfinite-music-hub-creators">
				<?php foreach ( $creators as $creator ) : ?>
					<?php echo Nfinite_Creators_Frontend::creator_card( $creator->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $videos ) : ?>
		<section class="nfinite-music-hub-section">
			<header class="nfinite-music-hub-section__head">
				<div>
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Watch', 'nfinite-creators' ); ?></span>
					<h2><?php esc_html_e( 'Music Videos', 'nfinite-creators' ); ?></h2>
				</div>
			</header>

			<div class="nfinite-creator-video-grid nfinite-music-hub-video-grid">
				<?php foreach ( $videos as $video ) : ?>
					<?php
					$embed = wp_oembed_get( $video['url'], array( 'width' => 1200 ) );
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
							<span class="nfinite-eyebrow"><?php echo esc_html( $video['type'] ); ?></span>
							<h3><?php echo esc_html( $video['title'] ); ?></h3>
							<p><a href="<?php echo esc_url( get_permalink( $video['creator_id'] ) ); ?>"><?php echo esc_html( $video['creator_name'] ); ?></a></p>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
</div>
