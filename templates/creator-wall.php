<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$creator = get_queried_object();
if ( ! $creator instanceof WP_Post || 'nfinite_creator' !== $creator->post_type ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	get_header();
	get_template_part( '404' );
	get_footer();
	return;
}

$creator_id = (int) $creator->ID;
$creator_title = get_the_title( $creator_id );
$types = Nfinite_Creators_Profile::creator_type_label( $creator_id );
$posts = new WP_Query( array(
	'post_type'           => Nfinite_Creators_Publishing::POST_TYPE_POST,
	'post_status'         => 'publish',
	'posts_per_page'      => 10,
	'orderby'             => 'date',
	'order'               => 'DESC',
	'meta_key'            => '_nfinite_creator_id',
	'meta_value'          => $creator_id,
	'ignore_sticky_posts' => true,
) );
$total_posts = (int) $posts->found_posts;

get_header();
?>
<main class="nfinite-creator-wall-page" id="nfinite-creator-wall">
	<section class="nfinite-creator-wall-hero">
		<div class="nfinite-creator-wall-shell nfinite-creator-wall-shell--wide">
			<a class="nfinite-creator-wall-back" href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>">← <?php esc_html_e( 'Back to profile', 'nfinite-creators' ); ?></a>
			<div class="nfinite-creator-wall-identity">
				<a class="nfinite-feed-avatar nfinite-creator-wall-avatar" href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>">
					<?php
					if ( has_post_thumbnail( $creator_id ) ) {
						echo get_the_post_thumbnail( $creator_id, 'thumbnail', array( 'class' => 'nfinite-feed-avatar__img' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						echo '<span>' . esc_html( strtoupper( mb_substr( $creator_title, 0, 1 ) ) ) . '</span>';
					}
					?>
				</a>
				<div class="nfinite-creator-wall-identity__copy">
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Creator Wall', 'nfinite-creators' ); ?></span>
					<h1><?php echo esc_html( $creator_title ); ?></h1>
					<p><?php echo esc_html( $types ); ?> · <?php printf( esc_html( _n( '%s public post', '%s public posts', $total_posts, 'nfinite-creators' ) ), esc_html( number_format_i18n( $total_posts ) ) ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<div class="nfinite-creator-wall-shell nfinite-creator-wall-content">
		<section class="nfinite-creator-wall-feed" data-creator-wall-feed aria-label="<?php esc_attr_e( 'Creator posts', 'nfinite-creators' ); ?>">
			<?php if ( $posts->have_posts() ) : ?>
				<?php while ( $posts->have_posts() ) : $posts->the_post(); ?>
					<?php echo Nfinite_Creators_Publishing::render_feed_card( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endwhile; ?>
				<?php if ( $total_posts > 10 ) : ?>
					<div class="nfinite-load-more-wrap">
						<button type="button" class="nfinite-load-more-bar" data-nfinite-load-more data-context="creator" data-creator-id="<?php echo esc_attr( $creator_id ); ?>" data-offset="10" data-total="<?php echo esc_attr( $total_posts ); ?>">
							<span><?php esc_html_e( 'Load 10 more posts', 'nfinite-creators' ); ?></span>
							<small><b data-load-count>10</b> of <?php echo esc_html( number_format_i18n( $total_posts ) ); ?> loaded</small>
						</button>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<div class="nfinite-feed-empty">
					<h2><?php esc_html_e( 'Nothing on the wall yet.', 'nfinite-creators' ); ?></h2>
					<p><?php esc_html_e( 'This creator has not published a public post yet.', 'nfinite-creators' ); ?></p>
				</div>
			<?php endif; ?>
		</section>
	</div>
</main>
<?php
wp_reset_postdata();
$GLOBALS['post'] = $creator;
setup_postdata( $creator );
get_footer();
wp_reset_postdata();
