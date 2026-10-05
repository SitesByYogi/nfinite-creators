<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Unified Search V1.
 * Public, provider-neutral search across the PairOfDice/Nfinite content graph.
 */
class Nfinite_Creators_Search {
	const REST_NS = 'nfinite/v1';

	public static function init() {
		add_shortcode( 'nfinite_search', array( __CLASS__, 'shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
	}

	public static function supported_types() {
		return array( 'nfinite_creator', 'nfinite_org', 'nfinite_release', 'nfinite_track', 'nfinite_playlist', 'nfinite_show', 'nfinite_episode', 'nfinite_video', 'nfinite_event', 'nfinite_creator_post', 'post' );
	}

	public static function groups() {
		return array(
			'all'           => self::supported_types(),
			'creators'      => array( 'nfinite_creator' ),
			'organizations' => array( 'nfinite_org' ),
			'music'         => array( 'nfinite_release', 'nfinite_track', 'nfinite_playlist' ),
			'tv'            => array( 'nfinite_show', 'nfinite_episode', 'nfinite_video' ),
			'events'        => array( 'nfinite_event' ),
			'stories'       => array( 'nfinite_creator_post', 'post' ),
		);
	}

	private static function normalize_group( $group ) {
		$group = sanitize_key( $group );
		return isset( self::groups()[ $group ] ) ? $group : 'all';
	}

	private static function meta_keys() {
		return array(
			'_nfinite_creator_tagline', '_nfinite_creator_location', '_nfinite_creator_genre',
			'_nfinite_org_tagline', '_nfinite_org_location',
			'_nfinite_track_metadata_genre', '_nfinite_track_primary_artist', '_nfinite_release_label',
			'_nfinite_show_hosts', '_nfinite_show_network', '_nfinite_episode_guests',
			'_nfinite_event_venue_name', '_nfinite_event_city', '_nfinite_event_state',
		);
	}

	public static function search( $query, $args = array() ) {
		$query = trim( sanitize_text_field( $query ) );
		$args = wp_parse_args( $args, array( 'group' => 'all', 'limit' => 24, 'page' => 1, 'sort' => 'relevance' ) );
		$group = self::normalize_group( $args['group'] );
		$types = self::groups()[ $group ];
		$limit = min( 50, max( 1, absint( $args['limit'] ) ) );
		$page  = max( 1, absint( $args['page'] ) );
		$sort  = 'newest' === sanitize_key( $args['sort'] ) ? 'newest' : 'relevance';

		if ( '' === $query ) {
			$posts = get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => $limit, 'paged' => $page, 'orderby' => 'date', 'order' => 'DESC', 'suppress_filters' => false ) );
			return array( 'items' => $posts, 'total' => count( $posts ), 'page' => $page, 'limit' => $limit, 'group' => $group, 'query' => $query );
		}

		global $wpdb;
		$like = '%' . $wpdb->esc_like( $query ) . '%';
		$type_placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$meta_keys = self::meta_keys();
		$meta_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
		$sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key IN ({$meta_placeholders}) WHERE p.post_status='publish' AND p.post_type IN ({$type_placeholders}) AND (p.post_title LIKE %s OR p.post_excerpt LIKE %s OR p.post_content LIKE %s OR pm.meta_value LIKE %s) LIMIT 500";
		$params = array_merge( $meta_keys, $types, array( $like, $like, $like, $like ) );
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );

		// Also surface content belonging to a creator or organization whose name matches.
		$parent_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('nfinite_creator','nfinite_org') AND post_title LIKE %s LIMIT 100", $like ) );
		if ( $parent_ids ) {
			$relation_keys = array( '_nfinite_release_creator_id','_nfinite_track_creator_id','_nfinite_show_creator_id','_nfinite_episode_creator_id','_nfinite_video_creator_id','_nfinite_creator_id','_nfinite_org_id' );
			foreach ( $parent_ids as $pid ) {
				foreach ( $relation_keys as $key ) {
					$related = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_value=%d LIMIT 150", $key, absint( $pid ) ) );
					$ids = array_merge( $ids, $related );
				}
			}
		}
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		$posts = $ids ? get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'post__in' => $ids, 'posts_per_page' => -1, 'suppress_filters' => false ) ) : array();

		$q = remove_accents( strtolower( $query ) );
		$tokens = array_values( array_filter( preg_split( '/\s+/', $q ) ) );
		$scored = array();
		foreach ( $posts as $post ) {
			$title = remove_accents( strtolower( wp_strip_all_tags( $post->post_title ) ) );
			$body  = remove_accents( strtolower( wp_strip_all_tags( $post->post_excerpt . ' ' . $post->post_content ) ) );
			$score = 0;
			if ( $title === $q ) { $score += 120; }
			elseif ( 0 === strpos( $title, $q ) ) { $score += 80; }
			elseif ( false !== strpos( $title, $q ) ) { $score += 55; }
			if ( false !== strpos( $body, $q ) ) { $score += 18; }
			foreach ( $tokens as $token ) { if ( false !== strpos( $title, $token ) ) $score += 12; if ( false !== strpos( $body, $token ) ) $score += 3; }
			if ( in_array( $post->post_type, array( 'nfinite_creator', 'nfinite_org' ), true ) ) { $score += 8; }
			$age = max( 0, ( time() - get_post_time( 'U', true, $post ) ) / DAY_IN_SECONDS );
			$score += max( 0, 8 - min( 8, $age / 30 ) );
			$scored[ $post->ID ] = $score;
		}
		if ( 'newest' === $sort ) {
			usort( $posts, function( $a, $b ) { return get_post_time( 'U', true, $b ) <=> get_post_time( 'U', true, $a ); } );
		} else {
			usort( $posts, function( $a, $b ) use ( $scored ) { return ( $scored[ $b->ID ] ?? 0 ) <=> ( $scored[ $a->ID ] ?? 0 ); } );
		}
		$total = count( $posts );
		$posts = array_slice( $posts, ( $page - 1 ) * $limit, $limit );
		return array( 'items' => $posts, 'total' => $total, 'page' => $page, 'limit' => $limit, 'group' => $group, 'query' => $query );
	}

	public static function payload( $post ) {
		if ( is_numeric( $post ) ) $post = get_post( absint( $post ) );
		if ( ! $post ) return array();
		$obj = get_post_type_object( $post->post_type );
		return array(
			'id' => $post->ID, 'type' => $post->post_type,
			'type_label' => $obj ? $obj->labels->singular_name : $post->post_type,
			'title' => get_the_title( $post ), 'url' => get_permalink( $post ),
			'image' => get_the_post_thumbnail_url( $post, 'medium_large' ) ?: '',
			'excerpt' => wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 22 ),
			'date' => get_post_time( 'c', true, $post ),
		);
	}

	public static function register_rest() {
		register_rest_route( self::REST_NS, '/search', array(
			'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'rest' ),
			'args' => array(
				'q' => array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
				'group' => array( 'default' => 'all', 'sanitize_callback' => 'sanitize_key' ),
				'limit' => array( 'default' => 24, 'sanitize_callback' => 'absint' ),
				'page' => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
				'sort' => array( 'default' => 'relevance', 'sanitize_callback' => 'sanitize_key' ),
			),
		) );
	}

	public static function rest( WP_REST_Request $request ) {
		$result = self::search( $request->get_param( 'q' ), array( 'group' => $request->get_param( 'group' ), 'limit' => $request->get_param( 'limit' ), 'page' => $request->get_param( 'page' ), 'sort' => $request->get_param( 'sort' ) ) );
		$result['items'] = array_map( array( __CLASS__, 'payload' ), $result['items'] );
		return rest_ensure_response( $result );
	}

	private static function cards( $posts ) {
		if ( ! $posts ) return '<div class="nfinite-search-empty"><h3>' . esc_html__( 'No results found', 'nfinite-creators' ) . '</h3><p>' . esc_html__( 'Try another name, title, keyword, or category.', 'nfinite-creators' ) . '</p></div>';
		ob_start(); echo '<div class="nfinite-search-grid">';
		foreach ( $posts as $post ) { $p = self::payload( $post ); echo '<article class="nfinite-search-card">';
			if ( $p['image'] ) echo '<a class="nfinite-search-card__art" href="' . esc_url( $p['url'] ) . '"><img src="' . esc_url( $p['image'] ) . '" alt="" loading="lazy"></a>';
			echo '<div class="nfinite-search-card__body"><span class="nfinite-search-card__type">' . esc_html( $p['type_label'] ) . '</span><h3><a href="' . esc_url( $p['url'] ) . '">' . esc_html( $p['title'] ) . '</a></h3>';
			if ( $p['excerpt'] ) echo '<p>' . esc_html( $p['excerpt'] ) . '</p>';
			if ( class_exists( 'Nfinite_Creators_Library' ) && ! in_array( $p['type'], array( 'nfinite_creator', 'nfinite_org' ), true ) ) echo Nfinite_Creators_Library::button( $p['id'], 'save' );
			echo '</div></article>'; }
		echo '</div>'; return ob_get_clean();
	}

	public static function shortcode( $atts ) {
		$a = shortcode_atts( array( 'limit' => 24, 'placeholder' => 'Search PairOfDice' ), $atts, 'nfinite_search' );
		$q = isset( $_GET['nfinite_q'] ) ? sanitize_text_field( wp_unslash( $_GET['nfinite_q'] ) ) : '';
		$group = isset( $_GET['nfinite_group'] ) ? self::normalize_group( wp_unslash( $_GET['nfinite_group'] ) ) : 'all';
		$sort = isset( $_GET['nfinite_sort'] ) && 'newest' === sanitize_key( wp_unslash( $_GET['nfinite_sort'] ) ) ? 'newest' : 'relevance';
		$page = isset( $_GET['nfinite_page'] ) ? max( 1, absint( $_GET['nfinite_page'] ) ) : 1;
		$r = self::search( $q, array( 'group' => $group, 'sort' => $sort, 'page' => $page, 'limit' => absint( $a['limit'] ) ) );
		$labels = array( 'all'=>'All', 'creators'=>'Creators', 'organizations'=>'Organizations', 'music'=>'Music', 'tv'=>'TV & Video', 'events'=>'Events', 'stories'=>'Stories' );
		ob_start(); ?>
		<section class="nfinite-unified-search" data-nfinite-search>
			<header class="nfinite-search-hero"><span class="nfinite-eyebrow"><?php esc_html_e( 'PairOfDice Search', 'nfinite-creators' ); ?></span><h1><?php esc_html_e( 'Search everything', 'nfinite-creators' ); ?></h1></header>
			<form class="nfinite-search-form" method="get" action="">
				<label class="screen-reader-text" for="nfinite-search-input"><?php esc_html_e( 'Search', 'nfinite-creators' ); ?></label>
				<input id="nfinite-search-input" type="search" name="nfinite_q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php echo esc_attr( $a['placeholder'] ); ?>" autocomplete="off">
				<input type="hidden" name="nfinite_group" value="<?php echo esc_attr( $group ); ?>"><button type="submit"><?php esc_html_e( 'Search', 'nfinite-creators' ); ?></button>
			</form>
			<nav class="nfinite-search-tabs" aria-label="<?php esc_attr_e( 'Search categories', 'nfinite-creators' ); ?>">
			<?php foreach ( $labels as $key=>$label ) { $url = add_query_arg( array( 'nfinite_q'=>$q, 'nfinite_group'=>$key, 'nfinite_sort'=>$sort ), remove_query_arg( array('nfinite_page') ) ); echo '<a class="' . ( $key === $group ? 'is-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>'; } ?>
			</nav>
			<div class="nfinite-search-summary"><strong><?php echo esc_html( sprintf( _n( '%d result', '%d results', $r['total'], 'nfinite-creators' ), $r['total'] ) ); ?></strong><div><a class="<?php echo 'relevance'===$sort?'is-active':''; ?>" href="<?php echo esc_url(add_query_arg(array('nfinite_q'=>$q,'nfinite_group'=>$group,'nfinite_sort'=>'relevance'),remove_query_arg('nfinite_page'))); ?>"><?php esc_html_e('Relevance','nfinite-creators'); ?></a><a class="<?php echo 'newest'===$sort?'is-active':''; ?>" href="<?php echo esc_url(add_query_arg(array('nfinite_q'=>$q,'nfinite_group'=>$group,'nfinite_sort'=>'newest'),remove_query_arg('nfinite_page'))); ?>"><?php esc_html_e('Newest','nfinite-creators'); ?></a></div></div>
			<?php echo self::cards( $r['items'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php $pages=(int)ceil($r['total']/$r['limit']); if($pages>1){ echo '<nav class="nfinite-search-pagination">'; for($i=1;$i<=$pages;$i++){ echo '<a class="'.($i===$page?'is-active':'').'" href="'.esc_url(add_query_arg(array('nfinite_q'=>$q,'nfinite_group'=>$group,'nfinite_sort'=>$sort,'nfinite_page'=>$i))).'">'.(int)$i.'</a>'; } echo '</nav>'; } ?>
		</section>
		<?php return ob_get_clean();
	}
}
