<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Release_Library {
	const PAGE_SLUG   = 'all-releases';
	const PAGE_OPTION = 'nfinite_all_releases_page_id';

	public static function init() {
		add_shortcode( 'nfinite_all_releases', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'ensure_page' ), 31 );
	}

	public static function ensure_page() {
		$page_id = absint( get_option( self::PAGE_OPTION ) );
		if ( $page_id && 'trash' !== get_post_status( $page_id ) ) { return; }

		$page = get_page_by_path( self::PAGE_SLUG );
		if ( $page ) {
			update_option( self::PAGE_OPTION, $page->ID, false );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) && ! wp_doing_cron() ) { return; }

		$page_id = wp_insert_post(
			array(
				'post_title'   => 'All Releases',
				'post_name'    => self::PAGE_SLUG,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[nfinite_all_releases]',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( self::PAGE_OPTION, $page_id, false );
		}
	}

	public static function page_url() {
		$page_id = absint( get_option( self::PAGE_OPTION ) );
		return $page_id ? get_permalink( $page_id ) : home_url( '/all-releases/' );
	}

	private static function selected_type() {
		if ( empty( $_GET['release_type'] ) ) { return ''; }
		return sanitize_title( wp_unslash( $_GET['release_type'] ) );
	}

	private static function pagination_url( $page ) {
		$url = self::page_url();
		$type = self::selected_type();
		$args = array();
		if ( $type ) { $args['release_type'] = $type; }
		if ( $page > 1 ) { $args['release_page'] = absint( $page ); }
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	public static function shortcode() {
		$type       = self::selected_type();
		$page       = isset( $_GET['release_page'] ) ? max( 1, absint( $_GET['release_page'] ) ) : 1;
		$per_page   = 24;
		$query_args = array(
			'post_type'      => 'nfinite_release',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $type ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'nfinite_release_type',
					'field'    => 'slug',
					'terms'    => $type,
				),
			);
		}

		$releases = new WP_Query( $query_args );
		$terms    = get_terms(
			array(
				'taxonomy'   => 'nfinite_release_type',
				'hide_empty' => true,
			)
		);

		ob_start();
		?>
		<section class="nfinite-release-library">
			<header class="nfinite-release-library__hero">
				<span class="nfinite-eyebrow"><?php esc_html_e( 'PAIR OF DICE MUSIC', 'nfinite-creators' ); ?></span>
				<h1><?php esc_html_e( 'All Releases', 'nfinite-creators' ); ?></h1>
				<p><?php esc_html_e( 'Browse the full PairOfDice release library — albums, EPs, mixtapes, singles, compilations and playlists.', 'nfinite-creators' ); ?></p>
			</header>

			<nav class="nfinite-release-library__filters" aria-label="<?php esc_attr_e( 'Filter releases', 'nfinite-creators' ); ?>">
				<a class="<?php echo '' === $type ? 'is-active' : ''; ?>" href="<?php echo esc_url( self::page_url() ); ?>"><?php esc_html_e( 'All', 'nfinite-creators' ); ?></a>
				<?php if ( ! is_wp_error( $terms ) ) : ?>
					<?php foreach ( $terms as $term ) : ?>
						<a class="<?php echo $type === $term->slug ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'release_type', $term->slug, self::page_url() ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
					<?php endforeach; ?>
				<?php endif; ?>
			</nav>

			<div class="nfinite-release-library__meta">
				<strong><?php echo esc_html( number_format_i18n( $releases->found_posts ) ); ?></strong>
				<span><?php echo 1 === (int) $releases->found_posts ? esc_html__( 'release', 'nfinite-creators' ) : esc_html__( 'releases', 'nfinite-creators' ); ?></span>
			</div>

			<?php if ( $releases->have_posts() ) : ?>
				<div class="nfinite-music-hub-release-grid nfinite-release-library__grid">
					<?php while ( $releases->have_posts() ) : $releases->the_post(); ?>
						<?php echo Nfinite_Creators_Music_Hub::release_card( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endwhile; ?>
				</div>

				<?php if ( $releases->max_num_pages > 1 ) : ?>
					<nav class="nfinite-release-library__pagination" aria-label="<?php esc_attr_e( 'Release pages', 'nfinite-creators' ); ?>">
						<?php if ( $page > 1 ) : ?><a class="nfinite-release-library__page" href="<?php echo esc_url( self::pagination_url( $page - 1 ) ); ?>">← <?php esc_html_e( 'Previous', 'nfinite-creators' ); ?></a><?php endif; ?>
						<span><?php echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'nfinite-creators' ), $page, $releases->max_num_pages ) ); ?></span>
						<?php if ( $page < $releases->max_num_pages ) : ?><a class="nfinite-release-library__page" href="<?php echo esc_url( self::pagination_url( $page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'nfinite-creators' ); ?> →</a><?php endif; ?>
					</nav>
				<?php endif; ?>
			<?php else : ?>
				<div class="nfinite-release-library__empty">
					<h2><?php esc_html_e( 'No releases found.', 'nfinite-creators' ); ?></h2>
					<p><?php esc_html_e( 'Try another release type or return to the full library.', 'nfinite-creators' ); ?></p>
				</div>
			<?php endif; ?>
			<?php wp_reset_postdata(); ?>
		</section>
		<?php
		return ob_get_clean();
	}
}
