<?php if ( ! defined( 'ABSPATH' ) ) exit; get_header(); ?>
<main id="primary" class="site-main nfinite-videos-page">
    <div class="nfinite-creators-shell nfinite-videos-page__shell">
        <?php echo Nfinite_Creators_Discovery_Hubs::render_videos(); // phpcs:ignore ?>
    </div>
</main>
<?php get_footer();
