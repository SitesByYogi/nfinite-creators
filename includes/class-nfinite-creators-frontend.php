<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Frontend {
	public static function init() {
		add_shortcode( 'nfinite_creator_directory', array( __CLASS__, 'directory_shortcode' ) );
		add_shortcode( 'nfinite_creator_dashboard', array( __CLASS__, 'dashboard_shortcode' ) );
		add_action( 'admin_post_nfinite_save_creator_profile', array( __CLASS__, 'handle_save' ) );
	}

	public static function directory_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'posts' => 24,
				'type'  => '',
			),
			$atts,
			'nfinite_creator_directory'
		);

		$args = array(
			'post_type'      => 'nfinite_creator',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, absint( $atts['posts'] ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $atts['type'] ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'nfinite_creator_type',
					'field'    => 'slug',
					'terms'    => sanitize_title( $atts['type'] ),
				),
			);
		}

		$query = new WP_Query( $args );

		ob_start();
		?>
		<section class="nfinite-creators-directory">
			<div class="nfinite-creators-directory__head">
				<div>
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Community', 'nfinite-creators' ); ?></span>
					<h2><?php esc_html_e( 'Creators', 'nfinite-creators' ); ?></h2>
				</div>
			</div>

			<?php if ( $query->have_posts() ) : ?>
				<div class="nfinite-creators-grid">
					<?php while ( $query->have_posts() ) : $query->the_post(); ?>
						<?php echo self::creator_card( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			<?php else : ?>
				<p class="nfinite-creators-empty"><?php esc_html_e( 'No creator profiles are published yet.', 'nfinite-creators' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function creator_card( $creator_id ) {
		$terms = get_the_terms( $creator_id, 'nfinite_creator_type' );
		$types = $terms && ! is_wp_error( $terms ) ? implode( ' • ', wp_list_pluck( $terms, 'name' ) ) : __( 'Creator', 'nfinite-creators' );
		$location = get_post_meta( $creator_id, '_nfinite_creator_location', true );

		ob_start();
		?>
		<article class="nfinite-creator-card">
			<a class="nfinite-creator-card__media" href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>">
				<?php if ( has_post_thumbnail( $creator_id ) ) : ?>
					<?php echo get_the_post_thumbnail( $creator_id, 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<span class="nfinite-creator-placeholder"><?php echo esc_html( mb_substr( get_the_title( $creator_id ), 0, 1 ) ); ?></span>
				<?php endif; ?>
			</a>
			<div class="nfinite-creator-card__body">
				<span class="nfinite-eyebrow"><?php echo esc_html( $types ); ?></span>
				<div class="nfinite-creator-card__title-row">
					<h3><a href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>"><?php echo esc_html( get_the_title( $creator_id ) ); ?></a></h3>
					<?php if ( class_exists( 'Nfinite_Creators_Identity' ) ) { echo Nfinite_Creators_Identity::render_badge( $creator_id, true ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<?php if ( $location ) : ?><p><?php echo esc_html( $location ); ?></p><?php endif; ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	public static function dashboard_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<div class="nfinite-creators-notice">' . esc_html__( 'Please log in to manage your creator profile.', 'nfinite-creators' ) . '</div>';
		}

		$user_id  = get_current_user_id();
		$is_admin = current_user_can( 'manage_options' );
		$creator_id = 0;
		$admin_creators = array();

		// Administrators can switch between any creator profile from Creator Studio.
		if ( $is_admin ) {
			$admin_creators = get_posts(
				array(
					'post_type'      => 'nfinite_creator',
					'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);

			$requested_creator_id = isset( $_GET['nfinite_creator_id'] ) ? absint( $_GET['nfinite_creator_id'] ) : 0;
			if ( $requested_creator_id && 'nfinite_creator' === get_post_type( $requested_creator_id ) ) {
				$creator_id = $requested_creator_id;
			} else {
				// Keep the current behavior as the default, but provide an obvious profile switcher.
				foreach ( $admin_creators as $admin_creator ) {
					if ( (int) $admin_creator->post_author === (int) $user_id ) {
						$creator_id = (int) $admin_creator->ID;
						break;
					}
				}
			}
		} else {
			$creator = get_posts(
				array(
					'post_type'      => 'nfinite_creator',
					'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
					'author'         => $user_id,
					'posts_per_page' => 1,
				)
			);
			$creator_id = ! empty( $creator ) ? $creator[0]->ID : 0;
		}

		$creator_owner_id = $creator_id ? (int) get_post_field( 'post_author', $creator_id ) : $user_id;

		$title      = $creator_id ? get_the_title( $creator_id ) : '';
		$bio        = $creator_id ? get_post_field( 'post_content', $creator_id ) : '';
		$tagline    = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_tagline', true ) : '';
		$location   = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_location', true ) : '';
		$website    = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_website', true ) : '';
		$instagram  = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_instagram', true ) : '';
		$tiktok     = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_tiktok', true ) : '';
		$youtube    = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_youtube', true ) : '';
		$spotify    = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_spotify', true ) : '';
		$soundcloud = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_soundcloud', true ) : '';
		$email      = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_email', true ) : wp_get_current_user()->user_email;
		$kit        = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_kit_enabled', true ) : '';
		$tracks     = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_tracks', true ) : array();
		$videos     = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_videos', true ) : array();
		$products   = $creator_id ? get_post_meta( $creator_id, '_nfinite_creator_products', true ) : array();
		$merch_requests = ( $creator_id && class_exists( 'Nfinite_Creators_Printful' ) ) ? Nfinite_Creators_Printful::requests( $creator_id ) : array();
		if ( ! is_array( $tracks ) ) { $tracks = array(); }
		if ( ! is_array( $videos ) ) { $videos = array(); }
		if ( ! is_array( $products ) ) { $products = array(); }
		if ( ! is_array( $merch_requests ) ) { $merch_requests = array(); }
		$beat_product_count = count( array_filter( $tracks, function( $track ) { return ! empty( $track['sell_beat'] ) && 'Beat' === ( isset( $track['type'] ) ? $track['type'] : '' ); } ) );
		$commerce_products = class_exists( 'Nfinite_Creators_Track_Commerce' )
			? Nfinite_Creators_Track_Commerce::products()
			: array();

		$selected_types = array();
		if ( $creator_id ) {
			$terms = wp_get_object_terms( $creator_id, 'nfinite_creator_type', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) { $selected_types = array_map( 'absint', $terms ); }
		}

		$all_types = get_terms(
			array(
				'taxonomy'   => 'nfinite_creator_type',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		$has_visuals = $creator_id && ( has_post_thumbnail( $creator_id ) || get_post_meta( $creator_id, '_nfinite_creator_cover_id', true ) );
		$has_social  = (bool) ( $website || $instagram || $tiktok || $youtube || $spotify || $soundcloud );
		$has_content = ! empty( $tracks ) || ! empty( $videos ) || ! empty( $products );
		$onboarding_steps = array(
			array( 'label' => __( 'Creator identity', 'nfinite-creators' ), 'help' => __( 'Add your display name and creator type.', 'nfinite-creators' ), 'done' => (bool) ( $title && ! empty( $selected_types ) ), 'tab' => 'profile' ),
			array( 'label' => __( 'Tell your story', 'nfinite-creators' ), 'help' => __( 'Add a tagline, location, and bio.', 'nfinite-creators' ), 'done' => (bool) ( $tagline && $location && trim( wp_strip_all_tags( $bio ) ) ), 'tab' => 'profile' ),
			array( 'label' => __( 'Add your look', 'nfinite-creators' ), 'help' => __( 'Upload a profile photo or cover image.', 'nfinite-creators' ), 'done' => $has_visuals, 'tab' => 'profile' ),
			array( 'label' => __( 'Add something to feature', 'nfinite-creators' ), 'help' => __( 'Add audio, a video, or a product/service.', 'nfinite-creators' ), 'done' => $has_content, 'tab' => 'audio' ),
			array( 'label' => __( 'Add a way to follow you', 'nfinite-creators' ), 'help' => __( 'Add a website or at least one social/profile link.', 'nfinite-creators' ), 'done' => $has_social, 'tab' => 'profile' ),
		);
		$completed_steps = count( array_filter( $onboarding_steps, function( $step ) { return ! empty( $step['done'] ); } ) );
		$completion = (int) round( ( $completed_steps / count( $onboarding_steps ) ) * 100 );
		$next_step = null;
		foreach ( $onboarding_steps as $step ) { if ( empty( $step['done'] ) ) { $next_step = $step; break; } }

		$is_first_run = ! $is_admin && isset( $_GET['nfinite_auth'] ) && 'registered' === sanitize_key( wp_unslash( $_GET['nfinite_auth'] ) );

		ob_start();
		?>
		<section class="nfinite-creator-dashboard">
			<div class="nfinite-creator-dashboard__head">
				<div>
					<span class="nfinite-eyebrow"><?php esc_html_e( 'Creator Studio', 'nfinite-creators' ); ?></span>
					<h2><?php echo $creator_id ? esc_html__( 'Edit My Profile', 'nfinite-creators' ) : esc_html__( 'Create My Profile', 'nfinite-creators' ); ?></h2>
				</div>
				<?php if ( $creator_id ) : ?>
					<div class="nfinite-creator-status">
						<?php echo esc_html( ucfirst( get_post_status( $creator_id ) ) ); ?>
						<?php if ( 'publish' === get_post_status( $creator_id ) ) : ?>
							<a href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>"><?php esc_html_e( 'View Profile', 'nfinite-creators' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $creator_id && 'publish' === get_post_status( $creator_id ) ) : ?>
				<div class="nfinite-studio-profile-actions" aria-label="<?php esc_attr_e( 'Public profile actions', 'nfinite-creators' ); ?>">
					<span><strong><?php esc_html_e( 'Public profile', 'nfinite-creators' ); ?></strong><small><?php esc_html_e( 'See what visitors see or share your page.', 'nfinite-creators' ); ?></small></span>
					<div><a class="nfinite-btn nfinite-btn-secondary" href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View Public Profile', 'nfinite-creators' ); ?></a><button type="button" class="nfinite-btn nfinite-btn-secondary" data-share-profile data-share-url="<?php echo esc_url( get_permalink( $creator_id ) ); ?>" data-share-title="<?php echo esc_attr( $title ); ?>"><?php esc_html_e( 'Share Profile', 'nfinite-creators' ); ?></button></div>
				</div>
			<?php endif; ?>

			<?php if ( $is_admin && $admin_creators ) : ?>
				<div class="nfinite-admin-creator-switcher">
					<label for="nfinite-admin-creator-select"><?php esc_html_e( 'Admin: Manage creator', 'nfinite-creators' ); ?></label>
					<select id="nfinite-admin-creator-select" onchange="if(this.value){window.location.href=this.value;}">
						<?php foreach ( $admin_creators as $admin_creator ) : ?>
							<?php
							$switch_url = add_query_arg( 'nfinite_creator_id', (int) $admin_creator->ID, remove_query_arg( array( 'nfinite_creator_saved', 'nfinite_creator_id' ) ) );
							$author = get_userdata( (int) $admin_creator->post_author );
							$author_label = $author ? $author->display_name : __( 'No user', 'nfinite-creators' );
							?>
							<option value="<?php echo esc_url( $switch_url ); ?>" <?php selected( $creator_id, (int) $admin_creator->ID ); ?>>
								<?php echo esc_html( $admin_creator->post_title . ' — ' . $author_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p><?php esc_html_e( 'You are viewing Creator Studio as an administrator. Switching profiles does not change the creator owner.', 'nfinite-creators' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['nfinite_creator_saved'] ) ) : ?>
				<div class="nfinite-creators-notice nfinite-creators-notice--success"><?php esc_html_e( 'Your creator profile was saved.', 'nfinite-creators' ); ?></div>
			<?php endif; ?>

			<?php if ( $is_first_run ) : ?>
				<section class="nfinite-first-run" aria-label="<?php esc_attr_e( 'Getting started with Creator Studio', 'nfinite-creators' ); ?>">
					<div class="nfinite-first-run__intro">
						<span class="nfinite-eyebrow"><?php esc_html_e( 'Welcome to PairOfDice', 'nfinite-creators' ); ?></span>
						<h3><?php printf( esc_html__( "Let's build %s.", 'nfinite-creators' ), esc_html( $title ) ); ?></h3>
						<p><?php esc_html_e( 'Your creator account is ready. Start with the essentials below and we will guide you into the rest of Creator Studio as you go.', 'nfinite-creators' ); ?></p>
					</div>
					<div class="nfinite-first-run__path">
						<button type="button" class="nfinite-first-run-step is-primary" data-onboarding-tab="profile"><span>1</span><strong><?php esc_html_e( 'Complete your profile', 'nfinite-creators' ); ?></strong><small><?php esc_html_e( 'Add your creator type, location, bio, photos, and links.', 'nfinite-creators' ); ?></small></button>
						<button type="button" class="nfinite-first-run-step" data-onboarding-tab="audio"><span>2</span><strong><?php esc_html_e( 'Add audio', 'nfinite-creators' ); ?></strong><small><?php esc_html_e( 'Songs, beats, demos, mixes, or production reels.', 'nfinite-creators' ); ?></small></button>
						<button type="button" class="nfinite-first-run-step" data-onboarding-tab="videos"><span>3</span><strong><?php esc_html_e( 'Add a video', 'nfinite-creators' ); ?></strong><small><?php esc_html_e( 'Music videos, performances, interviews, or other visuals.', 'nfinite-creators' ); ?></small></button>
						<button type="button" class="nfinite-first-run-step" data-onboarding-tab="products"><span>4</span><strong><?php esc_html_e( 'Add something you sell', 'nfinite-creators' ); ?></strong><small><?php esc_html_e( 'Optional: products, services, bookings, or creator commerce.', 'nfinite-creators' ); ?></small></button>
					</div>
					<div class="nfinite-first-run__footer"><button type="button" class="nfinite-btn" data-onboarding-tab="profile"><?php esc_html_e( 'Start with my profile', 'nfinite-creators' ); ?></button><small><?php esc_html_e( 'Your profile begins in review and can be published after the essentials are ready.', 'nfinite-creators' ); ?></small></div>
				</section>
			<?php endif; ?>

			<?php if ( 100 === $completion ) : ?>
				<details class="nfinite-onboarding nfinite-onboarding--complete">
					<summary><span class="nfinite-onboarding-complete__mark">&#10003;</span><span><strong><?php esc_html_e( 'Profile complete', 'nfinite-creators' ); ?></strong><small><?php esc_html_e( 'Your creator profile has all of the essentials.', 'nfinite-creators' ); ?></small></span><span class="nfinite-onboarding-complete__toggle"><?php esc_html_e( 'View setup', 'nfinite-creators' ); ?></span></summary>
					<div class="nfinite-onboarding-complete__body">
						<div class="nfinite-onboarding__steps"><?php foreach ( $onboarding_steps as $index => $step ) : ?><button type="button" class="nfinite-onboarding-step is-done" data-onboarding-tab="<?php echo esc_attr( $step['tab'] ); ?>"><span class="nfinite-onboarding-step__mark">&#10003;</span><span><strong><?php echo esc_html( $step['label'] ); ?></strong><small><?php echo esc_html( $step['help'] ); ?></small></span></button><?php endforeach; ?></div>
					</div>
				</details>
			<?php else : ?>
				<section class="nfinite-onboarding" aria-label="<?php esc_attr_e( 'Creator profile setup', 'nfinite-creators' ); ?>">
					<div class="nfinite-onboarding__top"><div><span class="nfinite-eyebrow"><?php esc_html_e( 'Profile setup', 'nfinite-creators' ); ?></span><h3><?php esc_html_e( 'Finish setting up your creator profile', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Complete the essentials first. You can refine everything else later.', 'nfinite-creators' ); ?></p></div><div class="nfinite-onboarding__score"><strong><?php echo esc_html( $completion ); ?>%</strong><span><?php esc_html_e( 'complete', 'nfinite-creators' ); ?></span></div></div>
					<div class="nfinite-onboarding__bar" aria-hidden="true"><span style="width:<?php echo esc_attr( $completion ); ?>%"></span></div>
					<div class="nfinite-onboarding__steps"><?php foreach ( $onboarding_steps as $index => $step ) : ?><button type="button" class="nfinite-onboarding-step <?php echo ! empty( $step['done'] ) ? 'is-done' : ''; ?>" data-onboarding-tab="<?php echo esc_attr( $step['tab'] ); ?>"><span class="nfinite-onboarding-step__mark"><?php echo ! empty( $step['done'] ) ? '&#10003;' : esc_html( $index + 1 ); ?></span><span><strong><?php echo esc_html( $step['label'] ); ?></strong><small><?php echo esc_html( $step['help'] ); ?></small></span></button><?php endforeach; ?></div>
					<?php if ( $next_step ) : ?><button type="button" class="nfinite-btn nfinite-onboarding__next" data-onboarding-tab="<?php echo esc_attr( $next_step['tab'] ); ?>"><?php printf( esc_html__( 'Next: %s', 'nfinite-creators' ), esc_html( $next_step['label'] ) ); ?></button><?php endif; ?>
				</section>
			<?php endif; ?>

			<!-- Nfinite Creators <?php echo esc_html( NFINITE_CREATORS_VERSION ); ?> grouped Creator Studio navigation -->
			<nav class="nfinite-studio-tabs nfinite-studio-nav" data-nfinite-creators-version="<?php echo esc_attr( NFINITE_CREATORS_VERSION ); ?>" data-studio-tabs aria-label="<?php esc_attr_e( 'Creator Studio sections', 'nfinite-creators' ); ?>">
				<button type="button" class="nfinite-studio-nav__item is-active" data-studio-tab="profile"><?php esc_html_e( 'Profile', 'nfinite-creators' ); ?></button>

				<div class="nfinite-studio-nav__group" data-studio-group="content">
					<button type="button" class="nfinite-studio-nav__item nfinite-studio-nav__trigger" data-studio-group-trigger aria-expanded="false">
						<span><?php esc_html_e( 'Content', 'nfinite-creators' ); ?></span><span class="nfinite-studio-nav__chevron" aria-hidden="true">⌄</span>
					</button>
					<div class="nfinite-studio-nav__menu" data-studio-group-menu hidden>
						<button type="button" data-studio-tab="audio"><?php esc_html_e( 'Audio', 'nfinite-creators' ); ?> <span class="nfinite-tab-count"><?php echo esc_html( count( $tracks ) ); ?></span></button>
						<button type="button" data-studio-tab="videos"><?php esc_html_e( 'Videos', 'nfinite-creators' ); ?> <span class="nfinite-tab-count"><?php echo esc_html( count( $videos ) ); ?></span></button>
						<?php if ( ! $is_first_run && $creator_id && class_exists( 'Nfinite_Creators_Shows' ) ) : $creator_show_count = count( Nfinite_Creators_Shows::creator_shows( $creator_id ) ); ?><button type="button" data-studio-tab="shows"><?php esc_html_e( 'Shows & Series', 'nfinite-creators' ); ?> <span class="nfinite-tab-count"><?php echo esc_html( $creator_show_count ); ?></span></button><?php endif; ?>
						<?php if ( ! $is_first_run && $creator_id ) : ?><button type="button" data-studio-tab="publishing"><?php esc_html_e( 'Posts', 'nfinite-creators' ); ?> <span class="nfinite-tab-count"><?php echo esc_html( count( Nfinite_Creators_Publishing::creator_posts( $creator_id, 50 ) ) ); ?></span></button><button type="button" data-studio-tab="portfolio"><?php esc_html_e( 'Portfolio', 'nfinite-creators' ); ?> <span class="nfinite-tab-count"><?php echo esc_html( count( Nfinite_Creators_Publishing::creator_projects( $creator_id, 50 ) ) ); ?></span></button><?php endif; ?>
					</div>
				</div>

				<div class="nfinite-studio-nav__group" data-studio-group="commerce">
					<button type="button" class="nfinite-studio-nav__item nfinite-studio-nav__trigger" data-studio-group-trigger aria-expanded="false">
						<span><?php esc_html_e( 'Commerce', 'nfinite-creators' ); ?></span><span class="nfinite-studio-nav__chevron" aria-hidden="true">⌄</span>
					</button>
					<div class="nfinite-studio-nav__menu" data-studio-group-menu hidden>
						<button type="button" data-studio-tab="products"><?php esc_html_e( 'Products', 'nfinite-creators' ); ?> <span class="nfinite-tab-count"><?php echo esc_html( count( $products ) + $beat_product_count ); ?></span></button>
						<?php if ( $creator_id && class_exists( 'Nfinite_Creators_Printful' ) ) : ?><button type="button" data-studio-tab="merch"><?php esc_html_e( 'Merch', 'nfinite-creators' ); ?> <span class="nfinite-tab-count"><?php echo esc_html( count( $merch_requests ) ); ?></span></button><?php endif; ?>
						<?php if ( ! $is_first_run && $creator_id ) : ?><button type="button" data-studio-tab="orders"><?php esc_html_e( 'Orders', 'nfinite-creators' ); ?></button><button type="button" data-studio-tab="earnings"><?php esc_html_e( 'Earnings', 'nfinite-creators' ); ?></button><button type="button" data-studio-tab="commerce"><?php esc_html_e( 'Commerce Settings', 'nfinite-creators' ); ?></button><?php endif; ?>
					</div>
				</div>

				<?php if ( ! $is_first_run && $creator_id ) : $creator_event_count = count( Nfinite_Creators_Events_Studio::creator_events( $creator_id, $creator_owner_id ) ); ?><button type="button" class="nfinite-studio-nav__item" data-studio-tab="events"><?php esc_html_e( 'Events', 'nfinite-creators' ); ?> <span class="nfinite-tab-count"><?php echo esc_html( $creator_event_count ); ?></span></button><?php endif; ?>
				<?php if ( ! $is_first_run && $creator_id && class_exists( 'Nfinite_Creators_Analytics' ) ) : ?><button type="button" class="nfinite-studio-nav__item" data-studio-tab="analytics"><?php esc_html_e( 'Analytics', 'nfinite-creators' ); ?></button><?php endif; ?>
				<?php if ( ! $is_first_run ) : ?><button type="button" class="nfinite-studio-nav__item" data-studio-tab="epk"><?php esc_html_e( 'EPK', 'nfinite-creators' ); ?></button><?php endif; ?>
			</nav>

			<?php if ( ! $is_first_run && $creator_id && class_exists( 'Nfinite_Creators_Publishing' ) ) : ?>
				<div class="nfinite-studio-panel nfinite-studio-profile-composer-panel is-active" data-studio-panel="profile">
					<?php Nfinite_Creators_Publishing::render_profile_quick_composer( $creator_id ); ?>
				</div>
			<?php endif; ?>

			<form class="nfinite-creator-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nfinite_save_creator_profile">
				<input type="hidden" name="creator_id" value="<?php echo esc_attr( $creator_id ); ?>">
				<?php wp_nonce_field( 'nfinite_save_creator_profile', 'nfinite_creator_nonce' ); ?>

				<div class="nfinite-form-section nfinite-studio-panel is-active" data-studio-panel="profile">
					<div class="nfinite-form-section__head"><span>1</span><div><h3><?php esc_html_e( 'Profile', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Tell visitors who you are and what you do.', 'nfinite-creators' ); ?></p></div></div>

					<div class="nfinite-form-grid">
						<label><span><?php esc_html_e( 'Display Name', 'nfinite-creators' ); ?></span><input required type="text" name="creator_title" value="<?php echo esc_attr( $title ); ?>"></label>
						<label><span><?php esc_html_e( 'Location', 'nfinite-creators' ); ?></span><input type="text" name="creator_location" value="<?php echo esc_attr( $location ); ?>"></label>

						<div class="nfinite-form-full nfinite-creator-types-field">
							<span class="nfinite-field-label"><?php esc_html_e( 'Creator Types', 'nfinite-creators' ); ?></span>
							<div class="nfinite-multiselect" data-nfinite-multiselect>
								<button type="button" class="nfinite-multiselect__trigger" data-nfinite-multiselect-trigger aria-expanded="false">
									<span data-nfinite-multiselect-label><?php
										$selected_names = array();
										if ( ! is_wp_error( $all_types ) ) {
											foreach ( $all_types as $type ) {
												if ( in_array( (int) $type->term_id, $selected_types, true ) ) {
													$selected_names[] = $type->name;
												}
											}
										}
										echo esc_html( $selected_names ? implode( ', ', $selected_names ) : __( 'Choose creator types', 'nfinite-creators' ) );
									?></span>
									<span class="nfinite-multiselect__chevron" aria-hidden="true">⌄</span>
								</button>
								<div class="nfinite-multiselect__menu" data-nfinite-multiselect-menu hidden>
									<div class="nfinite-multiselect__toolbar">
										<label class="nfinite-multiselect__search-label">
											<span class="screen-reader-text"><?php esc_html_e( 'Search creator types', 'nfinite-creators' ); ?></span>
											<input type="search" class="nfinite-multiselect__search" data-nfinite-multiselect-search placeholder="<?php esc_attr_e( 'Search creator types…', 'nfinite-creators' ); ?>" autocomplete="off">
										</label>
										<button type="button" class="nfinite-multiselect__clear" data-nfinite-multiselect-clear><?php esc_html_e( 'Clear', 'nfinite-creators' ); ?></button>
									</div>
									<div class="nfinite-multiselect__options">
									<?php if ( ! is_wp_error( $all_types ) ) : foreach ( $all_types as $type ) : ?>
										<label class="nfinite-multiselect__option">
											<input type="checkbox" name="creator_types[]" value="<?php echo esc_attr( $type->term_id ); ?>" data-nfinite-multiselect-option <?php checked( in_array( (int) $type->term_id, $selected_types, true ) ); ?>>
											<span><?php echo esc_html( $type->name ); ?></span>
										</label>
									<?php endforeach; endif; ?>
									</div>
									<p class="nfinite-multiselect__empty" data-nfinite-multiselect-empty hidden><?php esc_html_e( 'No creator types match your search.', 'nfinite-creators' ); ?></p>
								</div>
							</div>
							<p class="nfinite-field-help"><?php esc_html_e( 'Select one or more roles that describe what you do.', 'nfinite-creators' ); ?></p>
						</div>

						<label class="nfinite-form-full"><span><?php esc_html_e( 'Tagline', 'nfinite-creators' ); ?></span><input type="text" name="creator_tagline" value="<?php echo esc_attr( $tagline ); ?>" placeholder="<?php esc_attr_e( 'Artist, producer and creative director based in Atlanta.', 'nfinite-creators' ); ?>"></label>
						<label class="nfinite-form-full"><span><?php esc_html_e( 'Bio', 'nfinite-creators' ); ?></span><textarea name="creator_bio" rows="8"><?php echo esc_textarea( $bio ); ?></textarea></label>

						<label><span><?php esc_html_e( 'Profile Photo', 'nfinite-creators' ); ?></span><input type="file" name="creator_profile_photo" accept="image/*"></label>
						<label><span><?php esc_html_e( 'Cover Image', 'nfinite-creators' ); ?></span><input type="file" name="creator_cover_image" accept="image/*"></label>
					</div>
				</div>

				<div class="nfinite-form-section nfinite-studio-panel nfinite-studio-panel--linked is-active" data-studio-panel="profile">
					<div class="nfinite-form-section__head"><span>2</span><div><h3><?php esc_html_e( 'Links', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Connect your website, streaming profiles, and social accounts.', 'nfinite-creators' ); ?></p></div></div>
					<div class="nfinite-form-grid">
						<?php
						$link_fields = array(
							'creator_website'    => array( __( 'Website', 'nfinite-creators' ), $website ),
							'creator_instagram'  => array( __( 'Instagram', 'nfinite-creators' ), $instagram ),
							'creator_tiktok'     => array( __( 'TikTok', 'nfinite-creators' ), $tiktok ),
							'creator_youtube'    => array( __( 'YouTube', 'nfinite-creators' ), $youtube ),
							'creator_spotify'    => array( __( 'Spotify', 'nfinite-creators' ), $spotify ),
							'creator_soundcloud' => array( __( 'SoundCloud', 'nfinite-creators' ), $soundcloud ),
						);
						foreach ( $link_fields as $name => $data ) :
						?>
							<label><span><?php echo esc_html( $data[0] ); ?></span><input type="url" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $data[1] ); ?>"></label>
						<?php endforeach; ?>
						<label class="nfinite-form-full"><span><?php esc_html_e( 'Booking / Contact Email', 'nfinite-creators' ); ?></span><input type="email" name="creator_email" value="<?php echo esc_attr( $email ); ?>"></label>
					</div>
				</div>

				<?php if ( ! $is_first_run && $creator_id && class_exists( 'Nfinite_Creators_Analytics' ) ) : ?>
					<div class="nfinite-form-section nfinite-studio-panel" data-studio-panel="analytics"><?php echo Nfinite_Creators_Analytics::studio_panel( $creator_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>

				<div class="nfinite-form-section nfinite-studio-panel" data-studio-panel="audio">
					<div class="nfinite-form-section__head"><span>3</span><div><h3><?php esc_html_e( 'Audio', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Upload songs, beats, demos, mixes, or production reels.', 'nfinite-creators' ); ?></p></div></div>

					<div class="nfinite-studio-panel-toolbar"><div><strong><?php esc_html_e( 'Your audio', 'nfinite-creators' ); ?></strong><small><?php esc_html_e( 'Manage songs, beats, demos, mixes, and production reels.', 'nfinite-creators' ); ?></small></div><button type="button" class="nfinite-btn" data-add-track><?php esc_html_e( '+ Add Audio', 'nfinite-creators' ); ?></button></div>
					<?php if ( ! $tracks ) : ?><div class="nfinite-studio-empty" data-empty-audio><strong><?php esc_html_e( 'No audio yet', 'nfinite-creators' ); ?></strong><p><?php esc_html_e( 'Add your first song, beat, demo, mix, or production reel.', 'nfinite-creators' ); ?></p></div><?php endif; ?>
					<div class="nfinite-studio-list-head nfinite-studio-list-head--audio" <?php echo $tracks ? '' : 'hidden'; ?> data-audio-list-head><span><?php esc_html_e( 'Track', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Type', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Commerce', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Status', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Actions', 'nfinite-creators' ); ?></span></div>
					<div class="nfinite-track-editor" data-track-editor>
						<?php
						$rows = $tracks;
						foreach ( $rows as $index => $track ) :
						?>
							<div class="nfinite-track-row" data-track-row>
								<?php
								$summary_title = ! empty( $track['title'] ) ? $track['title'] : __( 'Untitled Track', 'nfinite-creators' );
								$summary_type  = ! empty( $track['type'] ) ? $track['type'] : __( 'Track', 'nfinite-creators' );
								$enabled_prices = array_filter( isset( $track['licenses'] ) && is_array( $track['licenses'] ) ? $track['licenses'] : array(), function( $price ) { return (float) $price > 0; } );
								$summary_price = $enabled_prices ? '$' . number_format_i18n( min( array_map( 'floatval', $enabled_prices ) ), 2 ) . '+' : '';
                                $saved_source = ! empty( $track['playback_source'] ) ? sanitize_key( $track['playback_source'] ) : 'local';
                                $has_playback = ! empty( $track['audio_url'] ) || ! empty( $track['soundcloud_url'] ) || ! empty( $track['spotify_url'] ) || ! empty( $track['apple_music_url'] );
								?>
								<div class="nfinite-track-summary nfinite-studio-item-summary nfinite-studio-item-summary--audio">
									<div class="nfinite-track-summary__main"><strong data-track-summary-title><?php echo esc_html( $summary_title ); ?></strong><small><?php echo $has_playback ? esc_html__( 'Playback ready', 'nfinite-creators' ) : esc_html__( 'Needs audio source', 'nfinite-creators' ); ?></small></div>
									<span data-track-summary-type><?php echo esc_html( $summary_type ); ?></span>
									<span class="nfinite-studio-commerce-state"><?php if ( ! empty( $track['sell_beat'] ) ) : ?><b><?php echo $summary_price ? esc_html( $summary_price ) : esc_html__( 'For sale', 'nfinite-creators' ); ?></b><?php else : ?><small>—</small><?php endif; ?></span>
									<span class="nfinite-status-pill <?php echo $has_playback ? 'is-live' : 'is-draft'; ?>"><?php echo $has_playback ? esc_html__( 'Ready', 'nfinite-creators' ) : esc_html__( 'Incomplete', 'nfinite-creators' ); ?></span>
									<div class="nfinite-track-summary__actions"><button type="button" class="nfinite-track-edit" data-edit-track><?php esc_html_e( 'Edit', 'nfinite-creators' ); ?></button><button type="button" class="nfinite-track-remove" data-remove-track><?php esc_html_e( 'Remove', 'nfinite-creators' ); ?></button></div>
								</div>
								<div class="nfinite-track-row__body" data-track-body hidden>
								<div class="nfinite-form-grid">
									<label><span><?php esc_html_e( 'Title', 'nfinite-creators' ); ?></span><input type="text" name="tracks[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( isset( $track['title'] ) ? $track['title'] : '' ); ?>"></label>
									<label><span><?php esc_html_e( 'Track Type', 'nfinite-creators' ); ?></span>
										<?php $saved_track_type = isset( $track['type'] ) ? $track['type'] : 'Single'; if ( 'Song' === $saved_track_type ) { $saved_track_type = 'Single'; } ?>
										<select name="tracks[<?php echo esc_attr( $index ); ?>][type]">
											<?php foreach ( array( 'Single','Beat','Demo','Mix','Production Reel','Other' ) as $track_type ) : ?>
												<option value="<?php echo esc_attr( $track_type ); ?>" <?php selected( $saved_track_type, $track_type ); ?>><?php echo esc_html( $track_type ); ?></option>
											<?php endforeach; ?>
										</select>
									</label>
                                    <label><span><?php esc_html_e( 'Playback Source', 'nfinite-creators' ); ?></span><select name="tracks[<?php echo esc_attr( $index ); ?>][playback_source]"><option value="local" <?php selected( $saved_source, 'local' ); ?>>Uploaded / Direct Audio</option><option value="soundcloud" <?php selected( $saved_source, 'soundcloud' ); ?>>SoundCloud</option><option value="spotify" <?php selected( $saved_source, 'spotify' ); ?>>Spotify</option><option value="apple_music" <?php selected( $saved_source, 'apple_music' ); ?>>Apple Music</option></select></label>
                                    <label class="nfinite-form-full"><span>Uploaded / Direct Audio URL</span><input type="url" name="tracks[<?php echo esc_attr( $index ); ?>][audio_url]" value="<?php echo esc_attr( isset( $track['audio_url'] ) ? $track['audio_url'] : '' ); ?>"></label>
                                    <label><span>SoundCloud Track URL</span><input type="url" name="tracks[<?php echo esc_attr( $index ); ?>][soundcloud_url]" value="<?php echo esc_attr( isset( $track['soundcloud_url'] ) ? $track['soundcloud_url'] : '' ); ?>"></label>
                                    <label><span>Spotify Track URL</span><input type="url" name="tracks[<?php echo esc_attr( $index ); ?>][spotify_url]" value="<?php echo esc_attr( isset( $track['spotify_url'] ) ? $track['spotify_url'] : '' ); ?>"></label>
                                    <label><span>Apple Music Track URL</span><input type="url" name="tracks[<?php echo esc_attr( $index ); ?>][apple_music_url]" value="<?php echo esc_attr( isset( $track['apple_music_url'] ) ? $track['apple_music_url'] : '' ); ?>"></label>
									<label><span><?php esc_html_e( 'Upload Audio', 'nfinite-creators' ); ?></span><input type="file" name="track_audio_<?php echo esc_attr( $index ); ?>" accept="audio/*"></label>
									<label><span><?php esc_html_e( 'Cover Art URL', 'nfinite-creators' ); ?></span><input type="url" name="tracks[<?php echo esc_attr( $index ); ?>][cover_url]" value="<?php echo esc_attr( isset( $track['cover_url'] ) ? $track['cover_url'] : '' ); ?>"></label>
									<label><span><?php esc_html_e( 'Credits / Notes', 'nfinite-creators' ); ?></span><textarea name="tracks[<?php echo esc_attr( $index ); ?>][credits]" rows="3"><?php echo esc_textarea( isset( $track['credits'] ) ? $track['credits'] : '' ); ?></textarea></label>
									<?php
									$mon = class_exists( 'Nfinite_Creators_Music_Monetization' ) ? wp_parse_args( isset( $track['monetization'] ) && is_array( $track['monetization'] ) ? $track['monetization'] : array(), Nfinite_Creators_Music_Monetization::default_legacy_monetization() ) : array();
									$mon_status = ! empty( $mon['status'] ) && class_exists( 'Nfinite_Creators_Music_Monetization' ) ? Nfinite_Creators_Music_Monetization::normalize_status( $mon['status'] ) : 'not_enrolled';
									$track_uuid = ! empty( $track['uuid'] ) ? sanitize_key( $track['uuid'] ) : 'legacy-' . absint( $index );
									?>
									<input type="hidden" name="tracks[<?php echo esc_attr( $index ); ?>][uuid]" value="<?php echo esc_attr( $track_uuid ); ?>">
									<div class="nfinite-form-full nfinite-monetization-card">
										<div class="nfinite-monetization-card__head"><div><strong><?php esc_html_e( 'Music Monetization', 'nfinite-creators' ); ?></strong><small><?php esc_html_e( 'Enroll eligible original music for future earnings from qualified PairOfDice listening.', 'nfinite-creators' ); ?></small></div><span class="nfinite-status-pill nfinite-mon-status-<?php echo esc_attr( $mon_status ); ?>"><?php echo esc_html( class_exists( 'Nfinite_Creators_Music_Monetization' ) ? Nfinite_Creators_Music_Monetization::status_label( $mon_status ) : __( 'Not Enrolled', 'nfinite-creators' ) ); ?></span></div>
										<label class="nfinite-toggle"><input type="checkbox" name="tracks[<?php echo esc_attr( $index ); ?>][monetization][enrolled]" value="1" <?php checked( ! empty( $mon['enrolled'] ) ); ?>><span><?php esc_html_e( 'Enroll this track in PairOfDice music monetization', 'nfinite-creators' ); ?></span></label>
										<div class="nfinite-form-grid">
											<label><span><?php esc_html_e( 'Master owner', 'nfinite-creators' ); ?></span><input type="text" name="tracks[<?php echo esc_attr( $index ); ?>][monetization][master_owner]" value="<?php echo esc_attr( isset( $mon['master_owner'] ) ? $mon['master_owner'] : '' ); ?>"></label>
											<label><span><?php esc_html_e( 'Primary artist', 'nfinite-creators' ); ?></span><input type="text" name="tracks[<?php echo esc_attr( $index ); ?>][monetization][primary_artist]" value="<?php echo esc_attr( isset( $mon['primary_artist'] ) ? $mon['primary_artist'] : '' ); ?>"></label>
											<label><span><?php esc_html_e( 'Featured artist(s)', 'nfinite-creators' ); ?></span><input type="text" name="tracks[<?php echo esc_attr( $index ); ?>][monetization][featured_artists]" value="<?php echo esc_attr( isset( $mon['featured_artists'] ) ? $mon['featured_artists'] : '' ); ?>"></label>
											<label><span><?php esc_html_e( 'Producer(s)', 'nfinite-creators' ); ?></span><input type="text" name="tracks[<?php echo esc_attr( $index ); ?>][monetization][producers]" value="<?php echo esc_attr( isset( $mon['producers'] ) ? $mon['producers'] : '' ); ?>"></label>
											<label><span><?php esc_html_e( 'ISRC (if available)', 'nfinite-creators' ); ?></span><input type="text" maxlength="15" name="tracks[<?php echo esc_attr( $index ); ?>][monetization][isrc]" value="<?php echo esc_attr( isset( $mon['isrc'] ) ? $mon['isrc'] : '' ); ?>" placeholder="USABC2600001"></label>
											<label class="nfinite-form-full"><span><?php esc_html_e( 'Songwriter / publishing credits', 'nfinite-creators' ); ?></span><textarea rows="2" name="tracks[<?php echo esc_attr( $index ); ?>][monetization][songwriters]"><?php echo esc_textarea( isset( $mon['songwriters'] ) ? $mon['songwriters'] : '' ); ?></textarea></label>
										</div>
										<label class="nfinite-toggle"><input type="checkbox" name="tracks[<?php echo esc_attr( $index ); ?>][monetization][rights_confirmed]" value="1" <?php checked( ! empty( $mon['rights_confirmed'] ) ); ?>><span><?php esc_html_e( 'I control the rights necessary to authorize PairOfDice to monetize this recording.', 'nfinite-creators' ); ?></span></label>
										<label class="nfinite-toggle"><input type="checkbox" name="tracks[<?php echo esc_attr( $index ); ?>][monetization][monetization_terms]" value="1" <?php checked( ! empty( $mon['monetization_terms'] ) ); ?>><span><?php esc_html_e( 'I agree to the PairOfDice music monetization terms.', 'nfinite-creators' ); ?></span></label>
										<p class="nfinite-field-help"><?php esc_html_e( 'Enrollment is reviewed before a track becomes Monetized. Public play counts remain separate from payable qualified streams.', 'nfinite-creators' ); ?></p>
									</div>
									<div class="nfinite-beat-commerce" data-beat-commerce <?php echo ( 'Beat' === $saved_track_type ) ? '' : 'hidden'; ?>>
										<label class="nfinite-form-full nfinite-toggle"><input type="checkbox" name="tracks[<?php echo esc_attr( $index ); ?>][sell_beat]" value="1" <?php checked( ! empty( $track['sell_beat'] ) ); ?>><span><?php esc_html_e( 'Sell this beat on PairOfDice', 'nfinite-creators' ); ?></span></label>
										<div class="nfinite-license-grid">
										<?php foreach ( array( 'mp3' => 'MP3 Lease', 'wav' => 'WAV Lease', 'trackout' => 'Trackout', 'unlimited' => 'Unlimited', 'exclusive' => 'Exclusive' ) as $license_key => $license_label ) : ?>
											<label><span><?php echo esc_html( $license_label ); ?></span><div class="nfinite-price-input"><b>$</b><input type="number" min="0" step="0.01" name="tracks[<?php echo esc_attr( $index ); ?>][licenses][<?php echo esc_attr( $license_key ); ?>]" value="<?php echo esc_attr( isset( $track['licenses'][ $license_key ] ) ? $track['licenses'][ $license_key ] : '' ); ?>" placeholder="0.00"></div></label>
										<?php endforeach; ?>
										</div>
										<p class="nfinite-field-help"><?php esc_html_e( 'Set only the licenses you want to offer. Nfinite creates and updates the WooCommerce beat product automatically.', 'nfinite-creators' ); ?></p>
										<div class="nfinite-delivery-assets">
											<strong><?php esc_html_e( 'Delivery files', 'nfinite-creators' ); ?></strong>
											<p class="nfinite-field-help"><?php esc_html_e( 'The public preview can stay compressed or tagged. Upload the files buyers should receive after purchase.', 'nfinite-creators' ); ?></p>
											<div class="nfinite-form-grid">
												<label><span><?php esc_html_e( 'MP3 master', 'nfinite-creators' ); ?></span><input type="file" name="beat_mp3_<?php echo esc_attr( $index ); ?>" accept="audio/mpeg,audio/mp3"><input type="hidden" name="tracks[<?php echo esc_attr( $index ); ?>][delivery_assets][mp3]" value="<?php echo esc_attr( isset( $track['delivery_assets']['mp3'] ) ? $track['delivery_assets']['mp3'] : '' ); ?>"><?php if ( ! empty( $track['delivery_assets']['mp3'] ) ) : ?><small class="nfinite-current-file"><?php esc_html_e( 'File saved', 'nfinite-creators' ); ?> ✓</small><?php endif; ?></label>
												<label><span><?php esc_html_e( 'WAV master', 'nfinite-creators' ); ?></span><input type="file" name="beat_wav_<?php echo esc_attr( $index ); ?>" accept="audio/wav,audio/x-wav"><input type="hidden" name="tracks[<?php echo esc_attr( $index ); ?>][delivery_assets][wav]" value="<?php echo esc_attr( isset( $track['delivery_assets']['wav'] ) ? $track['delivery_assets']['wav'] : '' ); ?>"><?php if ( ! empty( $track['delivery_assets']['wav'] ) ) : ?><small class="nfinite-current-file"><?php esc_html_e( 'File saved', 'nfinite-creators' ); ?> ✓</small><?php endif; ?></label>
												<label><span><?php esc_html_e( 'Trackout ZIP', 'nfinite-creators' ); ?></span><input type="file" name="beat_trackout_<?php echo esc_attr( $index ); ?>" accept=".zip,application/zip"><input type="hidden" name="tracks[<?php echo esc_attr( $index ); ?>][delivery_assets][trackout]" value="<?php echo esc_attr( isset( $track['delivery_assets']['trackout'] ) ? $track['delivery_assets']['trackout'] : '' ); ?>"><?php if ( ! empty( $track['delivery_assets']['trackout'] ) ) : ?><small class="nfinite-current-file"><?php esc_html_e( 'File saved', 'nfinite-creators' ); ?> ✓</small><?php endif; ?></label>
											</div>
										</div>
										<input type="hidden" name="tracks[<?php echo esc_attr( $index ); ?>][product_id]" value="<?php echo esc_attr( isset( $track['product_id'] ) ? absint( $track['product_id'] ) : 0 ); ?>">
									</div>
									<label><span><?php esc_html_e( 'External Buy URL (optional)', 'nfinite-creators' ); ?></span><input type="url" name="tracks[<?php echo esc_attr( $index ); ?>][buy_url]" value="<?php echo esc_attr( isset( $track['buy_url'] ) ? $track['buy_url'] : '' ); ?>"></label>
									<label><span><?php esc_html_e( 'Buy Button Label', 'nfinite-creators' ); ?></span><input type="text" name="tracks[<?php echo esc_attr( $index ); ?>][buy_label]" value="<?php echo esc_attr( isset( $track['buy_label'] ) ? $track['buy_label'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Buy Beat', 'nfinite-creators' ); ?>"></label>
									<label class="nfinite-toggle"><input type="checkbox" name="tracks[<?php echo esc_attr( $index ); ?>][show_price]" value="1" <?php checked( ! empty( $track['show_price'] ) ); ?>><span><?php esc_html_e( 'Show starting price', 'nfinite-creators' ); ?></span></label>
								</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>


				<div class="nfinite-form-section nfinite-studio-panel" data-studio-panel="videos">
					<div class="nfinite-form-section__head"><span>4</span><div><h3><?php esc_html_e( 'Videos', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Add YouTube or Vimeo videos for your profile and Creator Kit / EPK.', 'nfinite-creators' ); ?></p></div></div>

					<div class="nfinite-studio-panel-toolbar"><div><strong><?php esc_html_e( 'Your videos', 'nfinite-creators' ); ?></strong><small><?php esc_html_e( 'Manage videos shown on your profile and EPK.', 'nfinite-creators' ); ?></small></div><button type="button" class="nfinite-btn" data-add-video><?php esc_html_e( '+ Add Video', 'nfinite-creators' ); ?></button></div>
					<?php if ( ! $videos ) : ?><div class="nfinite-studio-empty" data-empty-videos><strong><?php esc_html_e( 'No videos yet', 'nfinite-creators' ); ?></strong><p><?php esc_html_e( 'Add a YouTube or Vimeo video to start building your video library.', 'nfinite-creators' ); ?></p></div><?php endif; ?>
					<div class="nfinite-studio-list-head nfinite-studio-list-head--video" <?php echo $videos ? '' : 'hidden'; ?> data-video-list-head><span><?php esc_html_e( 'Video', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Type', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Featured', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Actions', 'nfinite-creators' ); ?></span></div>
					<div class="nfinite-video-editor" data-video-editor>
						<?php
						$video_rows = $videos;
						foreach ( $video_rows as $index => $video ) :
						?>
							<div class="nfinite-video-row" data-video-row>
								<div class="nfinite-track-summary nfinite-studio-item-summary nfinite-studio-item-summary--video">
									<div class="nfinite-track-summary__main"><strong data-video-summary-title><?php echo esc_html( ! empty( $video['title'] ) ? $video['title'] : __( 'Untitled Video', 'nfinite-creators' ) ); ?></strong><small><?php echo ! empty( $video['url'] ) ? esc_html__( 'Video ready', 'nfinite-creators' ) : esc_html__( 'Needs URL', 'nfinite-creators' ); ?></small></div>
									<span data-video-summary-type><?php echo esc_html( ! empty( $video['type'] ) ? $video['type'] : __( 'Video', 'nfinite-creators' ) ); ?></span>
									<span class="nfinite-status-pill <?php echo ! empty( $video['featured'] ) ? 'is-live' : ''; ?>" data-video-summary-featured><?php echo ! empty( $video['featured'] ) ? esc_html__( 'Featured', 'nfinite-creators' ) : '—'; ?></span>
									<div class="nfinite-track-summary__actions"><button type="button" class="nfinite-track-edit" data-edit-video><?php esc_html_e( 'Edit', 'nfinite-creators' ); ?></button><button type="button" class="nfinite-track-remove" data-remove-video><?php esc_html_e( 'Remove', 'nfinite-creators' ); ?></button></div>
								</div>

								<div class="nfinite-video-row__body" data-video-body hidden><div class="nfinite-form-grid">
									<label><span><?php esc_html_e( 'Title', 'nfinite-creators' ); ?></span><input type="text" name="videos[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( isset( $video['title'] ) ? $video['title'] : '' ); ?>"></label>

									<label><span><?php esc_html_e( 'Video Type', 'nfinite-creators' ); ?></span>
										<select name="videos[<?php echo esc_attr( $index ); ?>][type]">
											<?php foreach ( Nfinite_Creators_Video::video_types() as $video_type ) : ?>
												<option value="<?php echo esc_attr( $video_type ); ?>" <?php selected( isset( $video['type'] ) ? $video['type'] : '', $video_type ); ?>><?php echo esc_html( $video_type ); ?></option>
											<?php endforeach; ?>
										</select>
									</label>

									<label><span><?php esc_html_e( 'PairOfDice Channel / Programming', 'nfinite-creators' ); ?></span><select name="videos[<?php echo esc_attr( $index ); ?>][channel]"><?php foreach ( Nfinite_Creators_Video::programming_channels() as $channel_value => $channel_label ) : ?><option value="<?php echo esc_attr( $channel_value ); ?>" <?php selected( isset( $video['channel'] ) ? $video['channel'] : '', $channel_value ); ?>><?php echo esc_html( $channel_label ); ?></option><?php endforeach; ?></select></label>

									<label class="nfinite-form-full"><span><?php esc_html_e( 'YouTube / Vimeo / Internet Archive URL', 'nfinite-creators' ); ?></span><input type="url" name="videos[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( isset( $video['url'] ) ? $video['url'] : '' ); ?>" placeholder="https://www.youtube.com/watch?v=..."></label>

									<label><span><?php esc_html_e( 'Source / Publisher', 'nfinite-creators' ); ?></span><input type="text" name="videos[<?php echo esc_attr( $index ); ?>][source_name]" value="<?php echo esc_attr( isset( $video['source_name'] ) ? $video['source_name'] : '' ); ?>"></label>
									<label><span><?php esc_html_e( 'Source / Publisher URL', 'nfinite-creators' ); ?></span><input type="url" name="videos[<?php echo esc_attr( $index ); ?>][source_url]" value="<?php echo esc_attr( isset( $video['source_url'] ) ? $video['source_url'] : '' ); ?>"></label>
									<label class="nfinite-form-full"><span><?php esc_html_e( 'Original Source / Article URL', 'nfinite-creators' ); ?></span><input type="url" name="videos[<?php echo esc_attr( $index ); ?>][original_url]" value="<?php echo esc_attr( isset( $video['original_url'] ) ? $video['original_url'] : '' ); ?>"></label>

									<label class="nfinite-form-full"><span><?php esc_html_e( 'Description', 'nfinite-creators' ); ?></span><textarea name="videos[<?php echo esc_attr( $index ); ?>][description]" rows="3"><?php echo esc_textarea( isset( $video['description'] ) ? $video['description'] : '' ); ?></textarea></label>

									<label class="nfinite-toggle"><input type="checkbox" name="videos[<?php echo esc_attr( $index ); ?>][tv]" value="1" <?php checked( ! empty( $video['tv'] ) ); ?>><span><?php esc_html_e( 'Submit to PairOfDice TV', 'nfinite-creators' ); ?></span></label>
									<label><span><?php esc_html_e( 'TV Shelf', 'nfinite-creators' ); ?></span><select name="videos[<?php echo esc_attr( $index ); ?>][tv_section]"><?php foreach ( Nfinite_Creators_TV::sections() as $tv_value => $tv_label ) : ?><option value="<?php echo esc_attr( $tv_value ); ?>" <?php selected( ! empty( $video['tv_section'] ) ? $video['tv_section'] : 'independent', $tv_value ); ?>><?php echo esc_html( $tv_label ); ?></option><?php endforeach; ?></select></label>
									<label class="nfinite-toggle"><input type="checkbox" name="videos[<?php echo esc_attr( $index ); ?>][tv_live]" value="1" <?php checked( ! empty( $video['tv_live'] ) ); ?>><span><?php esc_html_e( 'Live programming', 'nfinite-creators' ); ?></span></label>
									<label class="nfinite-toggle"><input type="checkbox" name="videos[<?php echo esc_attr( $index ); ?>][tv_featured]" value="1" <?php checked( ! empty( $video['tv_featured'] ) ); ?>><span><?php esc_html_e( 'TV featured candidate', 'nfinite-creators' ); ?></span></label>
									<label class="nfinite-form-full nfinite-toggle"><input type="checkbox" data-frontend-featured-video name="videos[<?php echo esc_attr( $index ); ?>][featured]" value="1" <?php checked( ! empty( $video['featured'] ) ); ?>><span><?php esc_html_e( 'Featured Video', 'nfinite-creators' ); ?></span></label>
								</div></div>
							</div>
						<?php endforeach; ?>

					</div>
				</div>

				<?php if ( $creator_id && class_exists( 'Nfinite_Creators_Printful' ) ) : ?>
					<div class="nfinite-form-section nfinite-studio-panel" data-studio-panel="merch"><?php echo Nfinite_Creators_Printful::studio_panel( $creator_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>

				<div class="nfinite-form-section nfinite-studio-panel" data-studio-panel="products">
					<div class="nfinite-form-section__head"><span>5</span><div><h3><?php esc_html_e( 'Products', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Sell digital products, physical products, services, and bookings. Beat licenses are managed from the Audio tab.', 'nfinite-creators' ); ?></p></div></div>

					<?php if ( $beat_product_count ) : ?>
						<div class="nfinite-product-managed-note">
							<strong><?php esc_html_e( 'Beat license products', 'nfinite-creators' ); ?></strong>
							<p><?php esc_html_e( 'These products are generated automatically from beats you mark for sale. Edit their licenses and prices in Audio.', 'nfinite-creators' ); ?></p>
							<?php foreach ( $tracks as $track ) : if ( empty( $track['sell_beat'] ) || 'Beat' !== ( isset( $track['type'] ) ? $track['type'] : '' ) ) { continue; } ?>
								<div class="nfinite-product-readonly-row"><span><?php echo esc_html( isset( $track['title'] ) ? $track['title'] : __( 'Beat', 'nfinite-creators' ) ); ?></span><b><?php esc_html_e( 'Beat licenses', 'nfinite-creators' ); ?></b></div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<div class="nfinite-studio-panel-toolbar"><div><strong><?php esc_html_e( 'Your products', 'nfinite-creators' ); ?></strong><small><?php esc_html_e( 'Manage digital products, physical products, services, and bookings.', 'nfinite-creators' ); ?></small></div><button type="button" class="nfinite-btn" data-add-product><?php esc_html_e( '+ Add Product', 'nfinite-creators' ); ?></button></div>
					<?php if ( ! $products && ! $beat_product_count ) : ?><div class="nfinite-studio-empty" data-empty-products><strong><?php esc_html_e( 'No products yet', 'nfinite-creators' ); ?></strong><p><?php esc_html_e( 'Add a product, service, or booking when you are ready to sell.', 'nfinite-creators' ); ?></p></div><?php endif; ?>
					<div class="nfinite-studio-list-head nfinite-studio-list-head--product" <?php echo $products ? '' : 'hidden'; ?> data-product-list-head><span><?php esc_html_e( 'Product', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Type', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Price', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Status', 'nfinite-creators' ); ?></span><span><?php esc_html_e( 'Actions', 'nfinite-creators' ); ?></span></div>
					<div class="nfinite-product-editor" data-product-editor>
						<?php foreach ( $products as $index => $product ) :
							$product_title = ! empty( $product['title'] ) ? $product['title'] : __( 'Untitled Product', 'nfinite-creators' );
							$product_type = ! empty( $product['type'] ) ? $product['type'] : 'digital';
							$product_price = isset( $product['price'] ) && '' !== $product['price'] ? '$' . number_format_i18n( (float) $product['price'], 2 ) : '';
						?>
							<div class="nfinite-product-row" data-product-row>
								<div class="nfinite-track-summary nfinite-studio-item-summary nfinite-studio-item-summary--product">
									<div class="nfinite-track-summary__main"><strong data-product-summary-title><?php echo esc_html( $product_title ); ?></strong><small><?php echo ! empty( $product['product_id'] ) ? esc_html__( 'Synced to store', 'nfinite-creators' ) : esc_html__( 'Creator product', 'nfinite-creators' ); ?></small></div>
									<span data-product-summary-type><?php echo esc_html( self::product_type_label( $product_type ) ); ?></span>
									<b data-product-summary-price><?php echo $product_price ? esc_html( $product_price ) : '—'; ?></b>
									<span class="nfinite-status-pill <?php echo ( ! isset( $product['active'] ) || ! empty( $product['active'] ) ) ? 'is-live' : 'is-draft'; ?>"><?php echo ( ! isset( $product['active'] ) || ! empty( $product['active'] ) ) ? esc_html__( 'Active', 'nfinite-creators' ) : esc_html__( 'Draft', 'nfinite-creators' ); ?></span>
									<div class="nfinite-track-summary__actions"><button type="button" class="nfinite-track-edit" data-edit-product><?php esc_html_e( 'Edit', 'nfinite-creators' ); ?></button><button type="button" class="nfinite-track-edit" data-duplicate-product><?php esc_html_e( 'Duplicate', 'nfinite-creators' ); ?></button><button type="button" class="nfinite-track-remove" data-remove-product><?php esc_html_e( 'Remove', 'nfinite-creators' ); ?></button></div>
								</div>
								<div class="nfinite-product-row__body" data-product-body hidden>
									<div class="nfinite-form-grid">
										<label><span><?php esc_html_e( 'Product Name', 'nfinite-creators' ); ?></span><input type="text" name="products[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( isset( $product['title'] ) ? $product['title'] : '' ); ?>"></label>
										<label><span><?php esc_html_e( 'Product Type', 'nfinite-creators' ); ?></span><select name="products[<?php echo esc_attr( $index ); ?>][type]"><?php foreach ( self::product_types() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $product_type, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
										<label><span><?php esc_html_e( 'Price', 'nfinite-creators' ); ?></span><div class="nfinite-price-input"><b>$</b><input type="number" min="0" step="0.01" name="products[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( isset( $product['price'] ) ? $product['price'] : '' ); ?>" placeholder="0.00"></div></label>
										<label class="nfinite-product-image-field"><span><?php esc_html_e( 'Product Image', 'nfinite-creators' ); ?></span><?php if ( ! empty( $product['image_id'] ) ) : ?><span class="nfinite-upload-preview"><?php echo wp_get_attachment_image( absint( $product['image_id'] ), 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php endif; ?><input type="file" name="product_image_<?php echo esc_attr( $index ); ?>" accept="image/*"><input type="hidden" name="products[<?php echo esc_attr( $index ); ?>][image_id]" value="<?php echo esc_attr( isset( $product['image_id'] ) ? absint( $product['image_id'] ) : 0 ); ?>"><details class="nfinite-advanced-field"><summary><?php esc_html_e( 'Use an image URL instead', 'nfinite-creators' ); ?></summary><input type="url" name="products[<?php echo esc_attr( $index ); ?>][image_url]" value="<?php echo esc_attr( isset( $product['image_url'] ) ? $product['image_url'] : '' ); ?>" placeholder="https://..."></details></label>
									<label class="nfinite-form-full"><span><?php esc_html_e( 'Description', 'nfinite-creators' ); ?></span><textarea name="products[<?php echo esc_attr( $index ); ?>][description]" rows="4"><?php echo esc_textarea( isset( $product['description'] ) ? $product['description'] : '' ); ?></textarea></label>
										<label class="nfinite-form-full nfinite-product-type-field" data-product-field="digital"><span><?php esc_html_e( 'Digital Download File', 'nfinite-creators' ); ?></span><input type="file" name="product_download_<?php echo esc_attr( $index ); ?>"><?php if ( ! empty( $product['download_url'] ) ) : ?><small class="nfinite-current-file"><?php echo esc_html( wp_basename( $product['download_url'] ) ); ?> ✓</small><?php endif; ?><input type="hidden" name="products[<?php echo esc_attr( $index ); ?>][download_url]" value="<?php echo esc_attr( isset( $product['download_url'] ) ? $product['download_url'] : '' ); ?>"></label>
										<label class="nfinite-product-type-field" data-product-field="physical"><span><?php esc_html_e( 'Inventory Quantity', 'nfinite-creators' ); ?></span><input type="number" min="0" step="1" name="products[<?php echo esc_attr( $index ); ?>][stock]" value="<?php echo esc_attr( isset( $product['stock'] ) ? $product['stock'] : '' ); ?>"></label>
										<label class="nfinite-product-type-field" data-product-field="physical"><span><?php esc_html_e( 'Weight (lbs)', 'nfinite-creators' ); ?></span><input type="number" min="0" step="0.01" name="products[<?php echo esc_attr( $index ); ?>][weight]" value="<?php echo esc_attr( isset( $product['weight'] ) ? $product['weight'] : '' ); ?>"></label>
										<label class="nfinite-product-type-field" data-product-field="service booking"><span><?php esc_html_e( 'Duration / Session Length', 'nfinite-creators' ); ?></span><input type="text" name="products[<?php echo esc_attr( $index ); ?>][duration]" value="<?php echo esc_attr( isset( $product['duration'] ) ? $product['duration'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Example: 2 hours', 'nfinite-creators' ); ?>"></label>
										<label class="nfinite-product-type-field" data-product-field="booking"><span><?php esc_html_e( 'Booking Instructions', 'nfinite-creators' ); ?></span><input type="text" name="products[<?php echo esc_attr( $index ); ?>][booking_instructions]" value="<?php echo esc_attr( isset( $product['booking_instructions'] ) ? $product['booking_instructions'] : '' ); ?>" placeholder="<?php esc_attr_e( 'We will contact you to confirm a time.', 'nfinite-creators' ); ?>"></label>
										<label><span><?php esc_html_e( 'Delivery / Turnaround', 'nfinite-creators' ); ?></span><input type="text" name="products[<?php echo esc_attr( $index ); ?>][delivery]" value="<?php echo esc_attr( isset( $product['delivery'] ) ? $product['delivery'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Example: 3–5 business days', 'nfinite-creators' ); ?>"></label>
										<label class="nfinite-toggle"><input type="checkbox" name="products[<?php echo esc_attr( $index ); ?>][active]" value="1" <?php checked( ! isset( $product['active'] ) || ! empty( $product['active'] ) ); ?>><span><?php esc_html_e( 'Available for purchase', 'nfinite-creators' ); ?></span></label>
										<input type="hidden" name="products[<?php echo esc_attr( $index ); ?>][product_id]" value="<?php echo esc_attr( isset( $product['product_id'] ) ? absint( $product['product_id'] ) : 0 ); ?>">
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<?php if ( $creator_id && class_exists( 'Nfinite_Creators_Commerce_Dashboard' ) ) : ?>
					<div class="nfinite-form-section nfinite-studio-panel" data-studio-panel="orders"><?php echo Nfinite_Creators_Commerce_Dashboard::orders_panel( $creator_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div class="nfinite-form-section nfinite-studio-panel" data-studio-panel="earnings"><?php echo Nfinite_Creators_Commerce_Dashboard::earnings_panel( $creator_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>

				<div class="nfinite-form-section nfinite-studio-panel" data-studio-panel="epk">
					<div class="nfinite-form-section__head"><span>6</span><div><h3><?php esc_html_e( 'Creator Kit / EPK', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Enable a press-ready professional presentation using the same profile content.', 'nfinite-creators' ); ?></p></div></div>
					<label class="nfinite-toggle"><input type="checkbox" name="creator_kit_enabled" value="1" <?php checked( $kit, '1' ); ?>><span><?php esc_html_e( 'Enable Creator Kit / EPK', 'nfinite-creators' ); ?></span></label>
				</div>

				<?php if ( class_exists( 'Nfinite_Creators_Commerce' ) && $creator_id ) : ?>
					<div class="nfinite-studio-panel" data-studio-panel="commerce">
						<?php echo Nfinite_Creators_Commerce::dashboard_card( $creator_id, $creator_owner_id, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>

				<div class="nfinite-form-actions">
					<p><?php esc_html_e( 'New profiles are submitted for review before becoming public.', 'nfinite-creators' ); ?></p>
					<button class="nfinite-btn nfinite-btn-primary" type="submit"><?php esc_html_e( 'Save Creator Profile', 'nfinite-creators' ); ?></button>
				</div>
			</form>
			<?php if ( ! $is_first_run && $creator_id ) { Nfinite_Creators_Publishing::render_studio_panels( $creator_id, $creator_owner_id, $is_admin ); } ?>
			<?php if ( ! $is_first_run && $creator_id && class_exists( 'Nfinite_Creators_Shows' ) ) { Nfinite_Creators_Shows::render_studio_panel( $creator_id ); } ?>
			<?php if ( ! $is_first_run && $creator_id ) { Nfinite_Creators_Events_Studio::render_panel( $creator_id, $creator_owner_id, $is_admin ); } ?>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function handle_save() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'nfinite-creators' ) );
		}

		if (
			! isset( $_POST['nfinite_creator_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_creator_nonce'] ) ), 'nfinite_save_creator_profile' )
		) {
			wp_die( esc_html__( 'Security check failed.', 'nfinite-creators' ) );
		}

		$user_id    = get_current_user_id();
		$creator_id = isset( $_POST['creator_id'] ) ? absint( $_POST['creator_id'] ) : 0;

		if ( $creator_id ) {
			$is_owner = (int) get_post_field( 'post_author', $creator_id ) === (int) $user_id;
			if ( 'nfinite_creator' !== get_post_type( $creator_id ) || ( ! $is_owner && ! current_user_can( 'manage_options' ) ) ) {
				wp_die( esc_html__( 'You cannot edit this creator profile.', 'nfinite-creators' ) );
			}
		}

		$title = isset( $_POST['creator_title'] ) ? sanitize_text_field( wp_unslash( $_POST['creator_title'] ) ) : '';
		$bio   = isset( $_POST['creator_bio'] ) ? wp_kses_post( wp_unslash( $_POST['creator_bio'] ) ) : '';

		if ( '' === $title ) {
			wp_die( esc_html__( 'Display name is required.', 'nfinite-creators' ) );
		}

		$post_author = $creator_id ? (int) get_post_field( 'post_author', $creator_id ) : $user_id;
		$postarr = array(
			'post_type'    => 'nfinite_creator',
			'post_title'   => $title,
			'post_content' => $bio,
			'post_excerpt' => wp_trim_words( wp_strip_all_tags( $bio ), 35, '…' ),
			'post_author'  => $post_author,
		);

		if ( $creator_id ) {
			$postarr['ID'] = $creator_id;
			$new_id = wp_update_post( $postarr, true );
		} else {
			$postarr['post_status'] = 'pending';
			$new_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		$creator_id = (int) $new_id;

		$types = isset( $_POST['creator_types'] ) ? array_map( 'absint', (array) $_POST['creator_types'] ) : array();
		wp_set_object_terms( $creator_id, $types, 'nfinite_creator_type', false );

		$text_map = array(
			'_nfinite_creator_tagline'  => 'creator_tagline',
			'_nfinite_creator_location' => 'creator_location',
		);
		foreach ( $text_map as $meta_key => $field ) {
			update_post_meta( $creator_id, $meta_key, isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '' );
		}

		$url_map = array(
			'_nfinite_creator_website'    => 'creator_website',
			'_nfinite_creator_instagram'  => 'creator_instagram',
			'_nfinite_creator_tiktok'     => 'creator_tiktok',
			'_nfinite_creator_youtube'    => 'creator_youtube',
			'_nfinite_creator_spotify'    => 'creator_spotify',
			'_nfinite_creator_soundcloud' => 'creator_soundcloud',
		);
		foreach ( $url_map as $meta_key => $field ) {
			update_post_meta( $creator_id, $meta_key, isset( $_POST[ $field ] ) ? esc_url_raw( wp_unslash( $_POST[ $field ] ) ) : '' );
		}

		update_post_meta(
			$creator_id,
			'_nfinite_creator_email',
			isset( $_POST['creator_email'] ) ? sanitize_email( wp_unslash( $_POST['creator_email'] ) ) : ''
		);
		update_post_meta( $creator_id, '_nfinite_creator_kit_enabled', isset( $_POST['creator_kit_enabled'] ) ? '1' : '' );

		self::handle_images( $creator_id );
		self::handle_tracks( $creator_id );
		self::handle_products( $creator_id );
		if ( class_exists( 'Nfinite_Creators_Printful' ) ) { Nfinite_Creators_Printful::handle_creator_save( $creator_id ); }

		$raw_videos = isset( $_POST['videos'] ) && is_array( $_POST['videos'] ) ? wp_unslash( $_POST['videos'] ) : array();
		update_post_meta( $creator_id, '_nfinite_creator_videos', Nfinite_Creators_Video::sanitize_videos( $raw_videos ) );

		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$redirect_args = array( 'nfinite_creator_saved' => '1' );
		if ( current_user_can( 'manage_options' ) ) {
			$redirect_args['nfinite_creator_id'] = $creator_id;
		}
		wp_safe_redirect( add_query_arg( $redirect_args, $redirect ) );
		exit;
	}

	private static function handle_images( $creator_id ) {
		if ( empty( $_FILES ) ) { return; }

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( ! empty( $_FILES['creator_profile_photo']['name'] ) ) {
			$attachment_id = media_handle_upload( 'creator_profile_photo', $creator_id );
			if ( ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $creator_id, $attachment_id );
			}
		}

		if ( ! empty( $_FILES['creator_cover_image']['name'] ) ) {
			$attachment_id = media_handle_upload( 'creator_cover_image', $creator_id );
			if ( ! is_wp_error( $attachment_id ) ) {
				update_post_meta( $creator_id, '_nfinite_creator_cover_id', (int) $attachment_id );
			}
		}
	}

	private static function handle_tracks( $creator_id ) {
		$submitted = isset( $_POST['tracks'] ) && is_array( $_POST['tracks'] ) ? wp_unslash( $_POST['tracks'] ) : array();
		$tracks    = array();
		$existing_tracks = get_post_meta( $creator_id, '_nfinite_creator_tracks', true );
		if ( ! is_array( $existing_tracks ) ) { $existing_tracks = array(); }
		$existing_by_uuid = array();
		foreach ( $existing_tracks as $existing_index => $existing_track ) {
			$existing_uuid = ! empty( $existing_track['uuid'] ) ? sanitize_key( $existing_track['uuid'] ) : 'legacy-' . absint( $existing_index );
			$existing_by_uuid[ $existing_uuid ] = $existing_track;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		foreach ( $submitted as $index => $track ) {
			$title     = isset( $track['title'] ) ? sanitize_text_field( $track['title'] ) : '';
			$type      = isset( $track['type'] ) ? sanitize_text_field( $track['type'] ) : 'Single';
			if ( 'Song' === $type ) { $type = 'Single'; }
			$audio_url = isset( $track['audio_url'] ) ? esc_url_raw( $track['audio_url'] ) : '';
            $playback_source = isset( $track['playback_source'] ) ? sanitize_key( $track['playback_source'] ) : 'local'; if ( ! in_array( $playback_source, array( 'local','soundcloud','spotify','apple_music' ), true ) ) { $playback_source = 'local'; }
            $soundcloud_url = isset( $track['soundcloud_url'] ) ? esc_url_raw( $track['soundcloud_url'] ) : ''; $spotify_url = isset( $track['spotify_url'] ) ? esc_url_raw( $track['spotify_url'] ) : ''; $apple_music_url = isset( $track['apple_music_url'] ) ? esc_url_raw( $track['apple_music_url'] ) : '';
			$cover_url = isset( $track['cover_url'] ) ? esc_url_raw( $track['cover_url'] ) : '';
			$credits    = isset( $track['credits'] ) ? sanitize_textarea_field( $track['credits'] ) : '';
			$product_id = isset( $track['product_id'] ) ? absint( $track['product_id'] ) : 0;
			$buy_url    = isset( $track['buy_url'] ) ? esc_url_raw( $track['buy_url'] ) : '';
			$buy_label  = isset( $track['buy_label'] ) ? sanitize_text_field( $track['buy_label'] ) : '';
			$show_price = ! empty( $track['show_price'] ) ? '1' : '';
			$sell_beat  = ( 'Beat' === $type && ! empty( $track['sell_beat'] ) ) ? '1' : '';
			$uuid = ! empty( $track['uuid'] ) ? sanitize_key( $track['uuid'] ) : wp_generate_uuid4();
			$existing_track = isset( $existing_by_uuid[ $uuid ] ) ? $existing_by_uuid[ $uuid ] : ( isset( $existing_tracks[ $index ] ) ? $existing_tracks[ $index ] : array() );
			$existing_monetization = ! empty( $existing_track['monetization'] ) && is_array( $existing_track['monetization'] ) ? $existing_track['monetization'] : array();
			$monetization = class_exists( 'Nfinite_Creators_Music_Monetization' )
				? Nfinite_Creators_Music_Monetization::sanitize_legacy_monetization( isset( $track['monetization'] ) ? $track['monetization'] : array(), $existing_monetization )
				: $existing_monetization;
			$licenses   = array();
			foreach ( array( 'mp3', 'wav', 'trackout', 'unlimited', 'exclusive' ) as $license_key ) {
				$raw_license = isset( $track['licenses'][ $license_key ] ) ? sanitize_text_field( $track['licenses'][ $license_key ] ) : '';
				$value = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $raw_license ) : preg_replace( '/[^0-9.]/', '', $raw_license );
				if ( '' !== $value && (float) $value > 0 ) { $licenses[ $license_key ] = $value; }
			}
			$delivery_assets = array();
			if ( ! empty( $track['delivery_assets'] ) && is_array( $track['delivery_assets'] ) ) {
				foreach ( array( 'mp3', 'wav', 'trackout' ) as $asset_key ) {
					if ( ! empty( $track['delivery_assets'][ $asset_key ] ) ) { $delivery_assets[ $asset_key ] = esc_url_raw( $track['delivery_assets'][ $asset_key ] ); }
				}
			}

			$file_key = 'track_audio_' . absint( $index );
			if ( ! empty( $_FILES[ $file_key ]['name'] ) ) {
				$attachment_id = media_handle_upload( $file_key, $creator_id );
				if ( ! is_wp_error( $attachment_id ) ) {
					$uploaded_url = wp_get_attachment_url( $attachment_id );
					if ( $uploaded_url ) { $audio_url = $uploaded_url; }
				}
			}

			foreach ( array( 'mp3', 'wav', 'trackout' ) as $asset_key ) {
				$asset_file_key = 'beat_' . $asset_key . '_' . absint( $index );
				if ( ! empty( $_FILES[ $asset_file_key ]['name'] ) ) {
					$attachment_id = media_handle_upload( $asset_file_key, $creator_id );
					if ( ! is_wp_error( $attachment_id ) ) {
						$asset_url = wp_get_attachment_url( $attachment_id );
						if ( $asset_url ) { $delivery_assets[ $asset_key ] = $asset_url; }
					}
				}
			}

			if ( ! $title && ! $audio_url && ! $soundcloud_url && ! $spotify_url && ! $apple_music_url ) { continue; }

			if ( $product_id && ( ! $sell_beat || 'Beat' !== $type ) && 'product' === get_post_type( $product_id ) && class_exists( 'WC_Product' ) ) {
				$old_product = wc_get_product( $product_id );
				if ( $old_product && 'beat' === get_post_meta( $product_id, '_nfinite_creator_product_type', true ) ) {
					$old_product->set_status( 'draft' );
					$old_product->set_catalog_visibility( 'hidden' );
					$old_product->save();
				}
			}

			$tracks[] = array(
				'uuid'      => $uuid,
				'title'     => $title ?: __( 'Untitled Track', 'nfinite-creators' ),
				'type'      => $type,
				'audio_url' => $audio_url, 'playback_source' => $playback_source, 'soundcloud_url' => $soundcloud_url, 'spotify_url' => $spotify_url, 'apple_music_url' => $apple_music_url,
				'cover_url' => $cover_url,
				'credits'    => $credits,
				'product_id' => $product_id,
				'buy_url'    => $buy_url,
				'buy_label'  => $buy_label ?: ( $sell_beat ? __( 'View Licenses', 'nfinite-creators' ) : '' ),
				'show_price' => $show_price,
				'sell_beat'  => $sell_beat,
				'licenses'   => $licenses,
				'delivery_assets' => $delivery_assets,
				'monetization' => $monetization,
			);

			if ( $sell_beat && 'Beat' === $type && class_exists( 'Nfinite_Creators_Track_Commerce' ) ) {
				$tracks[ count( $tracks ) - 1 ]['product_id'] = Nfinite_Creators_Track_Commerce::sync_beat_product( $creator_id, $tracks[ count( $tracks ) - 1 ], $product_id );
			}
		}

		update_post_meta( $creator_id, '_nfinite_creator_tracks', $tracks );
	}

	private static function product_types() {
		return array(
			'digital'  => __( 'Digital Product', 'nfinite-creators' ),
			'physical' => __( 'Physical Product', 'nfinite-creators' ),
			'service'  => __( 'Service', 'nfinite-creators' ),
			'booking'  => __( 'Booking', 'nfinite-creators' ),
		);
	}

	private static function product_type_label( $type ) {
		$types = self::product_types();
		return isset( $types[ $type ] ) ? $types[ $type ] : __( 'Product', 'nfinite-creators' );
	}

	private static function handle_products( $creator_id ) {
		$submitted = isset( $_POST['products'] ) && is_array( $_POST['products'] ) ? wp_unslash( $_POST['products'] ) : array();
		$existing_records = get_post_meta( $creator_id, '_nfinite_creator_products', true );
		if ( ! is_array( $existing_records ) ) { $existing_records = array(); }
		$submitted_ids = array_filter( array_map( function( $item ) { return isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0; }, $submitted ) );
		foreach ( $existing_records as $old_record ) {
			$old_id = isset( $old_record['product_id'] ) ? absint( $old_record['product_id'] ) : 0;
			if ( $old_id && ! in_array( $old_id, $submitted_ids, true ) && 'product' === get_post_type( $old_id ) && function_exists( 'wc_get_product' ) ) {
				$old_product = wc_get_product( $old_id );
				if ( $old_product ) { $old_product->set_status( 'draft' ); $old_product->set_catalog_visibility( 'hidden' ); $old_product->save(); }
			}
		}
		$products = array();
		$allowed_types = array_keys( self::product_types() );

		foreach ( $submitted as $index => $item ) {
			$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
			if ( '' === $title ) { continue; }
			$type = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : 'digital';
			if ( ! in_array( $type, $allowed_types, true ) ) { $type = 'digital'; }
			$raw_price = isset( $item['price'] ) ? sanitize_text_field( $item['price'] ) : '';
			$price = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $raw_price ) : preg_replace( '/[^0-9.]/', '', $raw_price );
			$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
			$record = array(
				'title'       => $title,
				'type'        => $type,
				'price'       => $price,
				'description' => isset( $item['description'] ) ? wp_kses_post( $item['description'] ) : '',
				'image_id'    => isset( $item['image_id'] ) ? absint( $item['image_id'] ) : 0,
				'image_url'   => isset( $item['image_url'] ) ? esc_url_raw( $item['image_url'] ) : '',
				'download_url'=> isset( $item['download_url'] ) ? esc_url_raw( $item['download_url'] ) : '',
				'stock'       => isset( $item['stock'] ) ? absint( $item['stock'] ) : '',
				'weight'      => isset( $item['weight'] ) ? sanitize_text_field( $item['weight'] ) : '',
				'duration'    => isset( $item['duration'] ) ? sanitize_text_field( $item['duration'] ) : '',
				'booking_instructions' => isset( $item['booking_instructions'] ) ? sanitize_text_field( $item['booking_instructions'] ) : '',
				'delivery'    => isset( $item['delivery'] ) ? sanitize_text_field( $item['delivery'] ) : '',
				'active'      => ! empty( $item['active'] ) ? '1' : '',
				'product_id'  => $product_id,
			);

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$image_file_key = 'product_image_' . absint( $index );
			if ( ! empty( $_FILES[ $image_file_key ]['name'] ) ) {
				$image_id = media_handle_upload( $image_file_key, $creator_id );
				if ( ! is_wp_error( $image_id ) ) { $record['image_id'] = absint( $image_id ); $record['image_url'] = wp_get_attachment_url( $image_id ); }
			}
			$download_file_key = 'product_download_' . absint( $index );
			if ( 'digital' === $type && ! empty( $_FILES[ $download_file_key ]['name'] ) ) {
				$download_id = media_handle_upload( $download_file_key, $creator_id );
				if ( ! is_wp_error( $download_id ) ) { $record['download_url'] = wp_get_attachment_url( $download_id ); }
			}

			if ( class_exists( 'WooCommerce' ) && class_exists( 'WC_Product_Simple' ) ) {
				$product = ( $product_id && 'product' === get_post_type( $product_id ) ) ? new WC_Product_Simple( $product_id ) : new WC_Product_Simple();
				$product->set_name( $title );
				$product->set_status( $record['active'] ? 'publish' : 'draft' );
				$product->set_catalog_visibility( $record['active'] ? 'visible' : 'hidden' );
				$product->set_description( $record['description'] );
				$product->set_short_description( $record['delivery'] ? sprintf( __( 'Delivery / turnaround: %s', 'nfinite-creators' ), $record['delivery'] ) : '' );
				if ( '' !== $price ) { $product->set_regular_price( $price ); $product->set_price( $price ); }
				$product->set_virtual( in_array( $type, array( 'digital', 'service', 'booking' ), true ) );
				$product->set_downloadable( 'digital' === $type && ! empty( $record['download_url'] ) );
				if ( 'digital' === $type && ! empty( $record['download_url'] ) && class_exists( 'WC_Product_Download' ) ) { $download = new WC_Product_Download(); $download->set_id( md5( $record['download_url'] ) ); $download->set_name( $title ); $download->set_file( $record['download_url'] ); $product->set_downloads( array( $download->get_id() => $download ) ); }
				if ( 'physical' === $type ) { $product->set_manage_stock( '' !== $record['stock'] ); if ( '' !== $record['stock'] ) { $product->set_stock_quantity( absint( $record['stock'] ) ); $product->set_stock_status( absint( $record['stock'] ) > 0 ? 'instock' : 'outofstock' ); } if ( '' !== $record['weight'] ) { $product->set_weight( $record['weight'] ); } }
				$product_id = $product->save();
				update_post_meta( $product_id, '_nfinite_creator_id', absint( $creator_id ) );
				update_post_meta( $product_id, '_nfinite_creator_product_type', $type );
				$image_id = absint( $record['image_id'] );
				if ( ! $image_id && $record['image_url'] && function_exists( 'attachment_url_to_postid' ) ) { $image_id = attachment_url_to_postid( $record['image_url'] ); }
				if ( $image_id ) { $product->set_image_id( $image_id ); $product->save(); $record['image_id'] = $image_id; }
				update_post_meta( $product_id, '_nfinite_creator_delivery', $record['delivery'] );
				update_post_meta( $product_id, '_nfinite_creator_duration', $record['duration'] );
				update_post_meta( $product_id, '_nfinite_creator_booking_instructions', $record['booking_instructions'] );
				$record['product_id'] = $product_id;
			}
			$products[] = $record;
		}
		update_post_meta( $creator_id, '_nfinite_creator_products', $products );
	}
}
