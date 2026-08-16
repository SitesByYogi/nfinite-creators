<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

$current_term = is_tax( 'nfinite_creator_type' ) ? get_queried_object() : null;
?>
<main class="nfinite-creators-archive">
	<div class="nfinite-creator-shell">
		<header class="nfinite-creators-archive__head">
			<span class="nfinite-eyebrow"><?php esc_html_e( 'Directory', 'nfinite-creators' ); ?></span>
			<h1><?php echo $current_term ? esc_html( $current_term->name ) : esc_html__( 'Creators', 'nfinite-creators' ); ?></h1>
			<p><?php esc_html_e( 'Discover artists, producers, designers, developers, engineers, photographers, videographers, and other creatives.', 'nfinite-creators' ); ?></p>
		</header>

		<?php
		$types = get_terms(
			array(
				'taxonomy'   => 'nfinite_creator_type',
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);
		if ( ! is_wp_error( $types ) && $types ) :
		?>
			<nav class="nfinite-creator-filters" aria-label="<?php esc_attr_e( 'Creator types', 'nfinite-creators' ); ?>">
				<a class="<?php echo $current_term ? '' : 'is-active'; ?>" href="<?php echo esc_url( get_post_type_archive_link( 'nfinite_creator' ) ); ?>"><?php esc_html_e( 'All', 'nfinite-creators' ); ?></a>
				<?php foreach ( $types as $type ) : ?>
					<a class="<?php echo $current_term && (int) $current_term->term_id === (int) $type->term_id ? 'is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $type ) ); ?>"><?php echo esc_html( $type->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<div class="nfinite-creators-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<?php echo Nfinite_Creators_Frontend::creator_card( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endwhile; ?>
			</div>

			<div class="nfinite-creators-pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p class="nfinite-creators-empty"><?php esc_html_e( 'No creators found.', 'nfinite-creators' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
