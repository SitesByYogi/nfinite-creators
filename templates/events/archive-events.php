<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$upcoming = Nfinite_Creators_Events_Query::events( 'upcoming' );
$past     = Nfinite_Creators_Events_Query::events( 'past' );
?>
<main class="nfinite-events-page nfinite-events-archive">
	<div class="nfinite-creator-shell">
		<header class="nfinite-events-head">
			<span class="nfinite-eyebrow"><?php esc_html_e( 'PairOfDice', 'nfinite-creators' ); ?></span>
			<h1><?php esc_html_e( 'Events', 'nfinite-creators' ); ?></h1>
			<p><?php esc_html_e( 'Live experiences, creator showcases, community gatherings, and everything happening around the PairOfDice ecosystem.', 'nfinite-creators' ); ?></p>
		</header>

		<?php if ( $upcoming ) : ?>
			<section class="nfinite-events-section">
				<div class="nfinite-events-section__head"><h2><?php esc_html_e( 'Upcoming Events', 'nfinite-creators' ); ?></h2></div>
				<div class="nfinite-events-grid">
					<?php foreach ( $upcoming as $event ) :
						$is_featured = '1' === get_post_meta( $event->ID, '_nfinite_event_featured', true ); ?>
						<article class="nfinite-event-card<?php echo $is_featured ? ' is-featured' : ''; ?>">
							<a class="nfinite-event-card__image" href="<?php echo esc_url( get_permalink( $event ) ); ?>"><?php echo get_the_post_thumbnail( $event, 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
							<div class="nfinite-event-card__body">
								<span class="nfinite-event-card__status"><?php echo esc_html( 'live' === Nfinite_Creators_Events_Query::status( $event->ID ) ? __( 'Happening Now', 'nfinite-creators' ) : __( 'Upcoming', 'nfinite-creators' ) ); ?></span>
								<h3><a href="<?php echo esc_url( get_permalink( $event ) ); ?>"><?php echo esc_html( get_the_title( $event ) ); ?></a></h3>
								<p class="nfinite-event-card__date"><?php echo esc_html( Nfinite_Creators_Events_Query::date_label( $event->ID ) ); ?></p>
								<?php $location = Nfinite_Creators_Events_Query::location_label( $event->ID ); if ( $location ) : ?><p class="nfinite-event-card__location"><?php echo esc_html( $location ); ?></p><?php endif; ?>
								<?php if ( has_excerpt( $event ) ) : ?><p><?php echo esc_html( get_the_excerpt( $event ) ); ?></p><?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php else : ?>
			<section class="nfinite-events-empty"><h2><?php esc_html_e( 'More events are coming.', 'nfinite-creators' ); ?></h2><p><?php esc_html_e( 'Check back for PairOfDice Weekend updates and new event announcements.', 'nfinite-creators' ); ?></p></section>
		<?php endif; ?>

		<?php if ( $past ) : ?>
			<section class="nfinite-events-section nfinite-events-section--past">
				<div class="nfinite-events-section__head"><h2><?php esc_html_e( 'Past Events', 'nfinite-creators' ); ?></h2></div>
				<div class="nfinite-events-grid nfinite-events-grid--compact">
					<?php foreach ( $past as $event ) : ?>
						<article class="nfinite-event-card">
							<a class="nfinite-event-card__image" href="<?php echo esc_url( get_permalink( $event ) ); ?>"><?php echo get_the_post_thumbnail( $event, 'medium_large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
							<div class="nfinite-event-card__body"><span class="nfinite-event-card__status is-past"><?php esc_html_e( 'Past Event', 'nfinite-creators' ); ?></span><h3><a href="<?php echo esc_url( get_permalink( $event ) ); ?>"><?php echo esc_html( get_the_title( $event ) ); ?></a></h3><p class="nfinite-event-card__date"><?php echo esc_html( Nfinite_Creators_Events_Query::date_label( $event->ID ) ); ?></p></div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
