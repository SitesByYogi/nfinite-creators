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

	$types = Nfinite_Creators_Profile::creator_type_label( $creator_id );

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
					<div class="nfinite-creator-title-row">
						<h1><?php the_title(); ?></h1>
						<?php if ( class_exists( 'Nfinite_Creators_Identity' ) ) { echo Nfinite_Creators_Identity::render_badge( $creator_id ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<?php if ( $tagline ) : ?><p class="nfinite-creator-tagline"><?php echo esc_html( $tagline ); ?></p><?php endif; ?>
					<?php if ( $location ) : ?><p class="nfinite-creator-location"><?php echo esc_html( $location ); ?></p><?php endif; ?>

					<div class="nfinite-creator-links">
						<?php foreach ( $links as $label => $url ) : if ( ! $url ) { continue; } ?>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
						<?php if ( $email ) : ?><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php esc_html_e( 'Contact', 'nfinite-creators' ); ?></a><?php endif; ?>
					</div>

					<?php
					$is_claimable = class_exists( 'Nfinite_Creators_Identity' ) && ! Nfinite_Creators_Identity::is_publisher( $creator_id ) && ! Nfinite_Creators_Identity::is_claimed( $creator_id );
					if ( $is_claimable ) :
						$claim_url = add_query_arg(
							array(
								'claim'        => $creator_id,
								'creator_name' => get_the_title(),
								'creator_url'  => get_permalink( $creator_id ),
							),
							'https://ci.pairofdice.media/'
						);
					?>
						<div class="nfinite-creator-claim">
							<div class="nfinite-creator-claim__copy">
								<strong><?php esc_html_e( 'Is this your profile?', 'nfinite-creators' ); ?></strong>
								<span><?php esc_html_e( 'Claim it with Creator Intelligence to manage your PairOfDice presence and unlock creator insights.', 'nfinite-creators' ); ?></span>
							</div>
							<a class="nfinite-creator-claim__button" href="<?php echo esc_url( $claim_url ); ?>"><?php esc_html_e( 'Claim this profile', 'nfinite-creators' ); ?><span aria-hidden="true"> →</span></a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<div class="nfinite-creator-shell nfinite-creator-content">
			<?php if ( class_exists( 'Nfinite_Creators_Publishing' ) ) { Nfinite_Creators_Publishing::render_profile_modules( $creator_id ); } ?>

			<?php
			/*
			 * Release-based Discography and profile-level Featured Audio are
			 * additive. A formal release should never remove demos, beats,
			 * featured songs, or other creator audio from the profile.
			 */
			$profile_audio = Nfinite_Creators_Audio::render_player(
				$creator_id,
				__( 'Featured Audio', 'nfinite-creators' )
			);

			if ( $profile_audio ) {
				echo '<div class="nfinite-creator-featured-audio">' . $profile_audio . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			$discography = Nfinite_Creators_Music_Player::render_discography( $creator_id );
			if ( $discography ) {
				echo $discography; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>

			<?php
			$videos_markup = Nfinite_Creators_Video::render_gallery( $creator_id, __( 'Videos', 'nfinite-creators' ) );
			if ( $videos_markup ) {
				echo $videos_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>

			<?php if ( trim( (string) get_the_content() ) ) : ?>
				<details class="nfinite-creator-section nfinite-creator-bio nfinite-creator-bio--lower">
					<summary class="nfinite-creator-bio__summary">
						<span>
							<span class="nfinite-eyebrow"><?php esc_html_e( 'About', 'nfinite-creators' ); ?></span>
							<strong><?php printf( esc_html__( 'About %s', 'nfinite-creators' ), esc_html( get_the_title() ) ); ?></strong>
						</span>
						<span class="nfinite-creator-bio__toggle" aria-hidden="true"></span>
					</summary>
					<div class="nfinite-creator-prose nfinite-creator-bio__content"><?php the_content(); ?></div>
				</details>
			<?php endif; ?>

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

			<?php
			$related_creator_ids = Nfinite_Creators_Profile::related_creators( $creator_id );
			if ( $related_creator_ids ) :
			?>
				<section class="nfinite-creator-related" aria-labelledby="nfinite-related-creators-title">
					<header class="nfinite-creator-related__head">
						<div>
							<span class="nfinite-eyebrow"><?php esc_html_e( 'Discover', 'nfinite-creators' ); ?></span>
							<h2 id="nfinite-related-creators-title"><?php esc_html_e( 'Similar Creators', 'nfinite-creators' ); ?></h2>
						</div>
						<a class="nfinite-creator-related__all" href="<?php echo esc_url( get_post_type_archive_link( 'nfinite_creator' ) ); ?>">
							<?php esc_html_e( 'View all creators', 'nfinite-creators' ); ?> →
						</a>
					</header>

					<div class="nfinite-creators-grid nfinite-creators-grid--related">
						<?php foreach ( $related_creator_ids as $related_creator_id ) : ?>
							<?php echo Nfinite_Creators_Frontend::creator_card( $related_creator_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
		</div>
	</main>
	<?php
endwhile;

get_footer();
