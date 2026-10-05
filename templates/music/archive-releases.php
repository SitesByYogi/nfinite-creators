<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
?>
<main class="nfinite-music-page nfinite-music-archive">
	<div class="nfinite-creator-shell">
		<?php if ( is_tax( 'nfinite_release_type' ) ) : ?>
			<header class="nfinite-music-archive__head">
				<span class="nfinite-eyebrow"><?php esc_html_e( 'Browse', 'nfinite-creators' ); ?></span>
				<h1><?php single_term_title(); ?></h1>
				<?php if ( term_description() ) : ?><div><?php echo wp_kses_post( term_description() ); ?></div><?php endif; ?>
			</header>

			<?php if ( have_posts() ) : ?>
				<div class="nfinite-music-hub-release-grid">
					<?php while ( have_posts() ) : the_post(); ?>
						<?php echo Nfinite_Creators_Music_Hub::release_card( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endwhile; ?>
				</div>
				<div class="nfinite-creators-pagination"><?php the_posts_pagination(); ?></div>
			<?php else : ?>
				<p><?php esc_html_e( 'No releases are published in this category yet.', 'nfinite-creators' ); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<?php echo Nfinite_Creators_Music_Hub::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
