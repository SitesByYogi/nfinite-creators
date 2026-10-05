<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
while ( have_posts() ) : the_post();
	$event_id   = get_the_ID();
	$status     = Nfinite_Creators_Events_Query::status( $event_id );
	$location   = Nfinite_Creators_Events_Query::location_label( $event_id );
	$address    = get_post_meta( $event_id, '_nfinite_event_address', true );
	$ticket_url = get_post_meta( $event_id, '_nfinite_event_ticket_url', true );
	$ticket_txt = get_post_meta( $event_id, '_nfinite_event_ticket_text', true ) ?: __( 'Get Tickets', 'nfinite-creators' );
	$price      = get_post_meta( $event_id, '_nfinite_event_price', true );
	$creator_ids = Nfinite_Creators_Events_Query::creator_ids( $event_id );
	?>
	<main class="nfinite-events-page nfinite-event-single">
		<div class="nfinite-creator-shell">
			<div class="nfinite-event-breadcrumb"><a href="<?php echo esc_url( get_post_type_archive_link( 'nfinite_event' ) ); ?>"><?php esc_html_e( 'Events', 'nfinite-creators' ); ?></a><span>→</span><span><?php the_title(); ?></span></div>
			<section class="nfinite-event-hero">
				<div class="nfinite-event-hero__media"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'full' ); } ?></div>
				<div class="nfinite-event-hero__copy">
					<span class="nfinite-event-status nfinite-event-status--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( 'live' === $status ? __( 'Happening Now', 'nfinite-creators' ) : ( 'past' === $status ? __( 'Past Event', 'nfinite-creators' ) : __( 'Upcoming Event', 'nfinite-creators' ) ) ); ?></span>
					<h1><?php the_title(); ?></h1>
					<p class="nfinite-event-hero__date"><?php echo esc_html( Nfinite_Creators_Events_Query::date_label( $event_id ) ); ?></p>
					<?php if ( $location ) : ?><p class="nfinite-event-hero__location"><?php echo esc_html( $location ); ?></p><?php endif; ?>
					<?php if ( $price ) : ?><p class="nfinite-event-hero__price"><?php echo esc_html( $price ); ?></p><?php endif; ?>
					<?php if ( $ticket_url && 'past' !== $status ) : ?><a class="nfinite-btn nfinite-btn--primary" href="<?php echo esc_url( $ticket_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $ticket_txt ); ?></a><?php endif; ?>
				</div>
			</section>

			<div class="nfinite-event-layout">
				<article class="nfinite-event-content"><?php the_content(); ?></article>
				<aside class="nfinite-event-details">
					<h2><?php esc_html_e( 'Event Details', 'nfinite-creators' ); ?></h2>
					<dl><div><dt><?php esc_html_e( 'Date', 'nfinite-creators' ); ?></dt><dd><?php echo esc_html( Nfinite_Creators_Events_Query::date_label( $event_id ) ); ?></dd></div>
					<?php if ( $location ) : ?><div><dt><?php esc_html_e( 'Location', 'nfinite-creators' ); ?></dt><dd><?php echo esc_html( $location ); ?><?php if ( $address ) : ?><br><?php echo esc_html( $address ); ?><?php endif; ?></dd></div><?php endif; ?>
					<?php if ( $price ) : ?><div><dt><?php esc_html_e( 'Admission', 'nfinite-creators' ); ?></dt><dd><?php echo esc_html( $price ); ?></dd></div><?php endif; ?></dl>
				</aside>
			</div>

			<?php if ( $creator_ids ) : ?>
				<section class="nfinite-event-creators"><div class="nfinite-events-section__head"><h2><?php esc_html_e( 'Featured Creators', 'nfinite-creators' ); ?></h2></div><div class="nfinite-event-creators__grid">
				<?php foreach ( $creator_ids as $creator_id ) : if ( 'publish' !== get_post_status( $creator_id ) ) { continue; } ?>
					<a class="nfinite-event-creator" href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>"><span class="nfinite-event-creator__image"><?php echo get_the_post_thumbnail( $creator_id, 'medium' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><strong><?php echo esc_html( get_the_title( $creator_id ) ); ?></strong></a>
				<?php endforeach; ?>
				</div></section>
			<?php endif; ?>
		</div>
	</main>
	<?php
endwhile;
get_footer();
