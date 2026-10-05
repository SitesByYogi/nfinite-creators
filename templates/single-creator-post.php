<?php if ( ! defined( 'ABSPATH' ) ) { exit; } get_header(); ?>
<main class="nfinite-community-shell nfinite-community-shell--single"><div class="nfinite-single-post-wrap">
<a class="nfinite-feed-back" href="<?php echo esc_url( get_post_type_archive_link( Nfinite_Creators_Publishing::POST_TYPE_POST ) ); ?>">← Back to Creator Feed</a>
<?php while ( have_posts() ) : the_post(); echo Nfinite_Creators_Publishing::render_feed_card( get_the_ID(), true ); ?>
<section id="comments" class="nfinite-feed-comments"><h2>Conversation <span><?php echo esc_html( get_comments_number() ); ?></span></h2><?php comments_template(); ?></section>
<?php endwhile; ?></div></main><?php get_footer(); ?>
