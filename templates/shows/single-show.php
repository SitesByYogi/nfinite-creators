<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
the_post();
$show_id = get_the_ID();
$creator_id = absint( get_post_meta( $show_id, '_nfinite_show_creator_id', true ) );
$hosts = (array) get_post_meta( $show_id, '_nfinite_show_hosts', true );
$episodes = Nfinite_Creators_Shows::episodes_for_show( $show_id );
$type = get_post_meta( $show_id, '_nfinite_show_type', true ) ?: 'series';
?>
<main class="nfinite-show-single"><section class="nfinite-show-hero"><div class="nfinite-show-hero__art"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large' ); } ?></div><div class="nfinite-show-hero__copy"><span class="nfinite-eyebrow"><?php echo esc_html( Nfinite_Creators_Shows::show_types()[ $type ] ?? __( 'Show / Series', 'nfinite-creators' ) ); ?></span><h1><?php the_title(); ?></h1><?php if ( $creator_id ) : ?><p><a href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>"><?php echo esc_html( get_the_title( $creator_id ) ); ?></a></p><?php endif; ?><div class="nfinite-show-description"><?php the_content(); ?></div><?php if ( $hosts ) : ?><p><strong><?php esc_html_e( 'Hosts:', 'nfinite-creators' ); ?></strong> <?php echo esc_html( implode( ', ', array_filter( array_map( 'get_the_title', array_map( 'absint', $hosts ) ) ) ) ); ?></p><?php endif; ?></div></section>
<section class="nfinite-show-episodes"><div class="nfinite-section-heading"><span class="nfinite-eyebrow"><?php esc_html_e( 'Watch', 'nfinite-creators' ); ?></span><h2><?php esc_html_e( 'Episodes', 'nfinite-creators' ); ?></h2></div><?php if ( $episodes ) : ?><div class="nfinite-episode-grid"><?php foreach ( $episodes as $episode ) : $season=absint(get_post_meta($episode->ID,'_nfinite_episode_season',true));$number=absint(get_post_meta($episode->ID,'_nfinite_episode_number',true)); ?><article class="nfinite-episode-card"><a href="<?php echo esc_url( get_permalink( $episode ) ); ?>"><?php if ( has_post_thumbnail( $episode ) ) { echo get_the_post_thumbnail( $episode, 'medium_large' ); } ?><div><small><?php echo esc_html( trim( ( $season ? 'S'.$season.' ' : '' ) . ( $number ? 'E'.$number : '' ) ) ); ?></small><h3><?php echo esc_html( get_the_title( $episode ) ); ?></h3><p><?php echo esc_html( get_the_excerpt( $episode ) ); ?></p></div></a></article><?php endforeach; ?></div><?php else : ?><p><?php esc_html_e( 'Episodes are coming soon.', 'nfinite-creators' ); ?></p><?php endif; ?></section></main>
<?php get_footer(); ?>
