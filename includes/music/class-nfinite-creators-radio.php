<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Radio {
	const PAGE_SLUG = 'radio';
	const PAGE_OPTION = 'nfinite_radio_page_id';

	public static function init() {
		add_shortcode( 'nfinite_radio', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'ensure_page' ), 30 );
		add_filter( 'wp_nav_menu_items', array( __CLASS__, 'topics_nav_link' ), 20, 2 );
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
		$page_id = wp_insert_post( array(
			'post_title'   => 'PairOfDice Radio',
			'post_name'    => self::PAGE_SLUG,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '[nfinite_radio]',
		) );
		if ( $page_id && ! is_wp_error( $page_id ) ) { update_option( self::PAGE_OPTION, $page_id, false ); }
	}

	public static function page_url() {
		$page_id = absint( get_option( self::PAGE_OPTION ) );
		return $page_id ? get_permalink( $page_id ) : home_url( '/radio/' );
	}

	public static function topics_nav_link( $items, $args ) {
		if ( empty( $args->theme_location ) || 'topics' !== $args->theme_location ) { return $items; }
		if ( preg_match( '/>\s*(?:<[^>]+>\s*)*Radio\s*(?:<\/[^>]+>\s*)*</i', $items ) ) { return $items; }

		// The topics location is also used for the general Explore bar. Only alter
		// the Music/Radio context so Radio does not leak into unrelated sections.
		$is_music_context = is_post_type_archive( 'nfinite_release' ) || is_singular( 'nfinite_release' ) || is_page( 'music' ) || is_page( self::PAGE_SLUG );
		if ( ! $is_music_context ) { return $items; }

		$active = is_page( self::PAGE_SLUG ) ? ' current-menu-item' : '';
		$link = '<li class="menu-item nfinite-radio-menu-item' . esc_attr( $active ) . '"><a href="' . esc_url( self::page_url() ) . '">Radio</a></li>';

		// WordPress/theme walkers may wrap menu labels in spans. Match the whole
		// New Music <li> rather than assuming the anchor contains text directly.
		$pattern = '/(<li\b[^>]*>.*?<a\b[^>]*>.*?New\s+Music.*?<\/a>.*?<\/li>)/is';
		if ( preg_match( $pattern, $items ) ) {
			return preg_replace( $pattern, '$1' . $link, $items, 1 );
		}

		// Defensive fallback for custom walkers: on Music/Radio pages put Radio
		// immediately after the first secondary-nav item.
		if ( preg_match( '/(<li\b[^>]*>.*?<\/li>)/is', $items ) ) {
			return preg_replace( '/(<li\b[^>]*>.*?<\/li>)/is', '$1' . $link, $items, 1 );
		}
		return $items . $link;
	}

	private static function is_direct_audio_url( $url ) {
		$url = trim( (string) $url );
		if ( ! $url ) { return false; }
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( preg_match( '/(^|\.)(youtube\.com|youtu\.be|spotify\.com|music\.apple\.com|soundcloud\.com)$/i', $host ) ) { return false; }
		$path = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		return (bool) preg_match( '/\.(mp3|m4a|aac|ogg|oga|wav|flac)$/i', $path );
	}

	public static function radio_queue() {
		$queue = array();
		$tracks = get_posts( array(
			'post_type'      => 'nfinite_track',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		foreach ( $tracks as $track ) {
			if ( ! Nfinite_Creators_Programming::public_item( $track->ID ) ) { continue; }
			$track_format = sanitize_key( get_post_meta( $track->ID, '_nfinite_track_format', true ) );
			if ( in_array( $track_format, array( 'beat', 'instrumental' ), true ) ) { continue; }
			$payload = Nfinite_Creators_Music_Query::track_payload( $track->ID );
			$source  = isset( $payload['source'] ) ? $payload['source'] : 'local';
			$url     = isset( $payload['sourceUrl'] ) ? $payload['sourceUrl'] : '';
			if ( empty( $payload['internalPlayable'] ) || ! self::is_direct_audio_url( $url ) ) { continue; }
			if ( ! empty( $payload['creatorId'] ) ) { $payload['creatorUrl'] = get_permalink( absint( $payload['creatorId'] ) ); }
			$queue[] = $payload;
		}

		return Nfinite_Creators_Programming::radio( $queue );
	}

	public static function shortcode() {
		$queue = self::radio_queue();
		$json  = wp_json_encode( $queue );
		ob_start();
		?>
		<section class="nfinite-radio" data-nfinite-radio data-nfinite-track-queue data-queue="<?php echo esc_attr( $json ); ?>">
			<div class="nfinite-radio__hero">
				<div class="nfinite-radio__copy">
					<span class="nfinite-radio__eyebrow">PAIR OF DICE RADIO</span>
					<h1>Press play. Stay in the rotation.</h1>
					<p>Independent music, new discoveries, and standout records from across PairOfDice. Playing nonstop.</p>
					<?php if ( $queue ) : ?>
						<div class="nfinite-radio__actions">
							<button type="button" class="nfinite-radio__play" data-radio-play>▶ Start Radio</button>
						</div>
					<?php endif; ?>
				</div>
				<div class="nfinite-radio__live"><span></span> LIVE ROTATION</div>
			</div>

			<?php if ( empty( $queue ) ) : ?>
				<div class="nfinite-radio__empty"><h2>Radio is warming up.</h2><p>New music is being added to the rotation. Check back soon.</p></div>
			<?php else : ?>
				<div class="nfinite-radio__player-card">
					<div class="nfinite-radio__art" data-radio-art><?php if ( ! empty( $queue[0]['artwork'] ) ) : ?><img src="<?php echo esc_url( $queue[0]['artwork'] ); ?>" alt=""><?php endif; ?></div>
					<div class="nfinite-radio__now">
						<span class="nfinite-radio__label">NOW PLAYING</span>
						<h2 data-radio-title><?php echo esc_html( $queue[0]['title'] ); ?></h2>
						<p data-radio-artist><?php echo esc_html( $queue[0]['artist'] ); ?></p>
						<p class="nfinite-radio__release" data-radio-release><?php echo esc_html( $queue[0]['release'] ); ?></p>
						<div class="nfinite-radio__transport">
							<button type="button" class="nfinite-radio__transport-button nfinite-radio__transport-button--prev" data-radio-prev aria-label="Previous track"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 5.75a1 1 0 0 1 1 1v3.43l8.45-4.88A1.7 1.7 0 0 1 18 6.77v10.46a1.7 1.7 0 0 1-2.55 1.47L7 13.82v3.43a1 1 0 1 1-2 0V6.75a1 1 0 0 1 1-1Z"/></svg></button>
							<button type="button" class="nfinite-radio__main-play" data-radio-toggle aria-label="Play or pause"><span aria-hidden="true">▶</span></button>
							<button type="button" class="nfinite-radio__transport-button nfinite-radio__transport-button--next" data-radio-next aria-label="Next track"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18 5.75a1 1 0 0 0-1 1v3.43L8.55 5.3A1.7 1.7 0 0 0 6 6.77v10.46a1.7 1.7 0 0 0 2.55 1.47L17 13.82v3.43a1 1 0 1 0 2 0V6.75a1 1 0 0 0-1-1Z"/></svg></button>
						</div>
						<a class="nfinite-radio__creator" data-radio-creator href="<?php echo ! empty( $queue[0]['creatorUrl'] ) ? esc_url( $queue[0]['creatorUrl'] ) : '#'; ?>"<?php echo empty( $queue[0]['creatorUrl'] ) ? ' hidden' : ''; ?>>View Artist</a>
					</div>
				</div>
				<div class="nfinite-radio__recent-wrap">
					<div class="nfinite-radio__section-head"><span>ON AIR HISTORY</span><h2>Recently Played</h2></div>
					<div class="nfinite-radio__recent" data-radio-recent><p>Start the station to build your listening history.</p></div>
				</div>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}
}
