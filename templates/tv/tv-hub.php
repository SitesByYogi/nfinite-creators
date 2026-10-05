<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function nfinite_tv_embed_url_035( $item ) {
	return Nfinite_Creators_Discovery_Hubs::video_embed_url( $item['url'] );
}

function nfinite_tv_art_035( $item ) {
	if ( ! empty( $item['thumbnail_url'] ) ) { return $item['thumbnail_url']; }
	return Nfinite_Creators_Discovery_Hubs::video_thumbnail( $item['url'] );
}

function nfinite_tv_meta_035( $item ) {
	$meta = array();
	if ( ! empty( $item['live'] ) ) { $meta[] = 'LIVE'; }
	if ( ! empty( $item['year'] ) ) { $meta[] = $item['year']; }
	if ( ! empty( $item['runtime'] ) ) { $meta[] = $item['runtime']; }
	if ( ! empty( $item['rating'] ) ) { $meta[] = $item['rating']; }
	return $meta;
}
?>
<section class="nfinite-tv">
	<?php if ( $featured ) :
		$featured_embed = nfinite_tv_embed_url_035( $featured );
		$featured_art   = ! empty( $featured['backdrop_url'] ) ? $featured['backdrop_url'] : nfinite_tv_art_035( $featured );
		$featured_meta  = nfinite_tv_meta_035( $featured );
	?>
		<header class="nfinite-tv__hero"<?php echo $featured_art ? ' style="--nfinite-tv-hero:url(' . esc_url( $featured_art ) . ')"' : ''; ?>>
			<div class="nfinite-tv__hero-shade"></div>
			<div class="nfinite-tv__hero-content">
				<span class="nfinite-tv__brand">PAIROFDICE TV</span>
				<?php if ( ! empty( $featured['live'] ) ) : ?><span class="nfinite-tv__live-badge"><i></i> LIVE</span><?php endif; ?>
				<h1><?php echo esc_html( $featured['title'] ); ?></h1>
				<?php if ( $featured_meta ) : ?><div class="nfinite-tv__meta"><?php foreach ( $featured_meta as $meta ) : ?><span><?php echo esc_html( $meta ); ?></span><?php endforeach; ?></div><?php endif; ?>
				<?php if ( ! empty( $featured['description'] ) ) : ?><p><?php echo esc_html( $featured['description'] ); ?></p><?php endif; ?>
				<div class="nfinite-tv__hero-actions">
					<?php if ( $featured_embed ) : ?><button type="button" class="nfinite-tv__watch" data-video-embed="<?php echo esc_attr( $featured_embed ); ?>" data-video-creator-id="<?php echo esc_attr( absint( $featured['creator_id'] ?? 0 ) ); ?>" data-video-object-type="<?php echo esc_attr( sanitize_key( $featured['object_type'] ?? '' ) ); ?>" data-video-object-id="<?php echo esc_attr( absint( $featured['object_id'] ?? 0 ) ); ?>" data-video-surface="tv" data-video-title="<?php echo esc_attr( $featured['title'] ?? '' ); ?>">▶ Watch Now</button><?php endif; ?>
					<?php if ( ! empty( $featured['creator_url'] ) ) : ?><a class="nfinite-tv__creator-link" href="<?php echo esc_url( $featured['creator_url'] ); ?>">View Creator</a><?php endif; ?>
				</div>
			</div>
		</header>
	<?php else : ?>
		<header class="nfinite-tv__empty-hero">
			<span class="nfinite-tv__brand">PAIROFDICE TV</span>
			<h1>Watch something worth finding.</h1>
			<p>Movies, shows, documentaries, live channels and independent programming are coming to PairOfDice TV.</p>
		</header>
	<?php endif; ?>

	<nav class="nfinite-tv__quick-nav" aria-label="PairOfDice TV categories">
		<?php foreach ( $rows as $slug => $row ) : ?>
			<?php if ( empty( $row['items'] ) ) { continue; } ?>
			<a href="#tv-<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $row['label'] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<div class="nfinite-tv__catalog">
		<?php foreach ( $rows as $slug => $row ) :
			if ( empty( $row['items'] ) ) { continue; }
		?>
			<section id="tv-<?php echo esc_attr( $slug ); ?>" class="nfinite-tv__row-section">
				<header class="nfinite-tv__row-head">
					<div>
						<?php if ( 'live' === $slug ) : ?><span class="nfinite-tv__row-kicker"><i></i> ON NOW</span><?php endif; ?>
						<h2><?php echo esc_html( $row['label'] ); ?></h2>
					</div>
				</header>

				<div class="nfinite-tv__rail" tabindex="0">
					<?php foreach ( array_slice( $row['items'], 0, 20 ) as $item ) :
						$embed = nfinite_tv_embed_url_035( $item );
						$art   = nfinite_tv_art_035( $item );
						$meta  = nfinite_tv_meta_035( $item );
						if ( ! $embed ) { continue; }
					?>
						<article class="nfinite-tv-card">
							<button type="button" class="nfinite-tv-card__media" data-video-embed="<?php echo esc_attr( $embed ); ?>" data-video-creator-id="<?php echo esc_attr( absint( $item['creator_id'] ?? 0 ) ); ?>" data-video-object-type="<?php echo esc_attr( sanitize_key( $item['object_type'] ?? '' ) ); ?>" data-video-object-id="<?php echo esc_attr( absint( $item['object_id'] ?? 0 ) ); ?>" data-video-surface="tv" data-video-title="<?php echo esc_attr( $item['title'] ?? '' ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Play %s', $item['title'] ) ); ?>">
								<?php if ( $art ) : ?><img loading="lazy" src="<?php echo esc_url( $art ); ?>" alt=""><?php else : ?><span class="nfinite-tv-card__placeholder"></span><?php endif; ?>
								<span class="nfinite-tv-card__play">▶</span>
								<?php if ( ! empty( $item['live'] ) ) : ?><span class="nfinite-tv-card__live">LIVE</span><?php endif; ?>
							</button>
							<div class="nfinite-tv-card__body">
								<h3><?php echo esc_html( $item['title'] ); ?></h3>
								<?php if ( $meta ) : ?><div class="nfinite-tv-card__meta"><?php echo esc_html( implode( ' • ', $meta ) ); ?></div><?php endif; ?>
								<?php if ( ! empty( $item['creator_url'] ) ) : ?><a href="<?php echo esc_url( $item['creator_url'] ); ?>"><?php echo esc_html( $item['creator_name'] ); ?></a><?php else : ?><span><?php echo esc_html( $item['creator_name'] ); ?></span><?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>
</section>
