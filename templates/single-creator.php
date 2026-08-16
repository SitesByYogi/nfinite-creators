<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

while ( have_posts() ) :
	the_post();

	$creator_id = get_the_ID();
	$cover_id   = absint( get_post_meta( $creator_id, '_nfinite_creator_cover_id', true ) );
	$cover_url  = $cover_id ? wp_get_attachment_image_url( $cover_id, 'full' ) : '';
	$tagline    = get_post_meta( $creator_id, '_nfinite_creator_tagline', true );
	$location   = get_post_meta( $creator_id, '_nfinite_creator_location', true );
	$email      = get_post_meta( $creator_id, '_nfinite_creator_email', true );
	$kit          = get_post_meta( $creator_id, '_nfinite_creator_kit_enabled', true );
	$gallery_ids  = get_post_meta( $creator_id, '_nfinite_creator_gallery_ids', true );
	$short_bio    = get_post_meta( $creator_id, '_nfinite_creator_short_bio', true );
	$credits      = get_post_meta( $creator_id, '_nfinite_creator_credits', true );
	$achievements = get_post_meta( $creator_id, '_nfinite_creator_achievements', true );
	$booking_name = get_post_meta( $creator_id, '_nfinite_creator_booking_name', true );
	$booking_phone = get_post_meta( $creator_id, '_nfinite_creator_booking_phone', true );
	$press_download = get_post_meta( $creator_id, '_nfinite_creator_press_download', true );

	if ( ! is_array( $gallery_ids ) ) {
		$gallery_ids = array();
	}

	$terms = get_the_terms( $creator_id, 'nfinite_creator_type' );
	$types = $terms && ! is_wp_error( $terms ) ? implode( ' • ', wp_list_pluck( $terms, 'name' ) ) : __( 'Creator', 'nfinite-creators' );

	$links = array(
		'Website'    => get_post_meta( $creator_id, '_nfinite_creator_website', true ),
		'Instagram'  => get_post_meta( $creator_id, '_nfinite_creator_instagram', true ),
		'TikTok'     => get_post_meta( $creator_id, '_nfinite_creator_tiktok', true ),
		'YouTube'    => get_post_meta( $creator_id, '_nfinite_creator_youtube', true ),
		'Spotify'    => get_post_meta( $creator_id, '_nfinite_creator_spotify', true ),
		'SoundCloud' => get_post_meta( $creator_id, '_nfinite_creator_soundcloud', true ),
	);
	?>
	<main class="nfinite-creator-single">
		<section class="nfinite-creator-hero"<?php echo $cover_url ? ' style="--nfinite-cover:url(' . esc_url( $cover_url ) . ')"' : ''; ?>>
			<div class="nfinite-creator-hero__overlay"></div>
			<div class="nfinite-creator-shell nfinite-creator-hero__inner">
				<div class="nfinite-creator-avatar">
					<?php
					if ( has_post_thumbnail() ) {
						the_post_thumbnail( 'large' );
					} else {
						echo '<span class="nfinite-creator-placeholder">' . esc_html( mb_substr( get_the_title(), 0, 1 ) ) . '</span>';
					}
					?>
				</div>

				<div class="nfinite-creator-hero__copy">
					<span class="nfinite-eyebrow"><?php echo esc_html( $types ); ?></span>
					<h1><?php the_title(); ?></h1>
					<?php if ( $tagline ) : ?><p class="nfinite-creator-tagline"><?php echo esc_html( $tagline ); ?></p><?php endif; ?>
					<?php if ( $location ) : ?><p class="nfinite-creator-location"><?php echo esc_html( $location ); ?></p><?php endif; ?>

					<div class="nfinite-creator-links">
						<?php foreach ( $links as $label => $url ) : if ( ! $url ) { continue; } ?>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
						<?php if ( $email ) : ?><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php esc_html_e( 'Contact', 'nfinite-creators' ); ?></a><?php endif; ?>
					</div>
				</div>
			</div>
		</section>

		<div class="nfinite-creator-shell nfinite-creator-content">
			<section class="nfinite-creator-section">
				<span class="nfinite-eyebrow"><?php esc_html_e( 'About', 'nfinite-creators' ); ?></span>
				<h2><?php esc_html_e( 'Bio', 'nfinite-creators' ); ?></h2>
				<div class="nfinite-creator-prose"><?php the_content(); ?></div>
			</section>

			<?php
			$player = Nfinite_Creators_Audio::render_player( $creator_id, __( 'Featured Audio', 'nfinite-creators' ) );
			if ( $player ) {
				echo $player; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>

			<?php
			$videos_markup = Nfinite_Creators_Video::render_gallery( $creator_id, __( 'Videos', 'nfinite-creators' ) );
			if ( $videos_markup ) {
				echo $videos_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>

			<?php if ( ! empty( $gallery_ids ) ) : ?>
				<section class="nfinite-creator-section nfinite-creator-gallery-section">
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Portfolio', 'nfinite-creators' ); ?></span>
					<h2><?php esc_html_e( 'Gallery', 'nfinite-creators' ); ?></h2>
					<div class="nfinite-creator-gallery">
						<?php foreach ( $gallery_ids as $image_id ) : ?>
							<?php
							$full = wp_get_attachment_image_url( absint( $image_id ), 'full' );
							if ( ! $full ) { continue; }
							?>
							<a href="<?php echo esc_url( $full ); ?>" target="_blank" rel="noopener">
								<?php echo wp_get_attachment_image( absint( $image_id ), 'large' ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $kit ) : ?>
				<section class="nfinite-creator-section nfinite-creator-kit">
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Press & Booking', 'nfinite-creators' ); ?></span>
					<h2><?php esc_html_e( 'Creator Kit / EPK', 'nfinite-creators' ); ?></h2>

					<?php if ( $short_bio ) : ?>
						<div class="nfinite-creator-kit__block">
							<h3><?php esc_html_e( 'Press Bio', 'nfinite-creators' ); ?></h3>
							<p><?php echo nl2br( esc_html( $short_bio ) ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $credits ) : ?>
						<div class="nfinite-creator-kit__block">
							<h3><?php esc_html_e( 'Notable Credits / Clients', 'nfinite-creators' ); ?></h3>
							<p><?php echo nl2br( esc_html( $credits ) ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $achievements ) : ?>
						<div class="nfinite-creator-kit__block">
							<h3><?php esc_html_e( 'Achievements / Press', 'nfinite-creators' ); ?></h3>
							<p><?php echo nl2br( esc_html( $achievements ) ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $booking_name || $booking_phone || $email ) : ?>
						<div class="nfinite-creator-kit__block">
							<h3><?php esc_html_e( 'Booking', 'nfinite-creators' ); ?></h3>
							<?php if ( $booking_name ) : ?><p><strong><?php echo esc_html( $booking_name ); ?></strong></p><?php endif; ?>
							<?php if ( $booking_phone ) : ?><p><?php echo esc_html( $booking_phone ); ?></p><?php endif; ?>
							<?php if ( $email ) : ?><p><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></p><?php endif; ?>
						</div>
					<?php endif; ?>

					<div class="nfinite-creator-kit__actions">
						<?php if ( $email ) : ?>
							<a class="nfinite-btn nfinite-btn-primary" href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php esc_html_e( 'Contact / Booking', 'nfinite-creators' ); ?></a>
						<?php endif; ?>
						<?php if ( $press_download ) : ?>
							<a class="nfinite-btn nfinite-btn-secondary" href="<?php echo esc_url( $press_download ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download Press Kit', 'nfinite-creators' ); ?></a>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>
		</div>
	</main>
	<?php
endwhile;

get_footer();
