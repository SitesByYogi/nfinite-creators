<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Discovery V2.
 *
 * Turns first-party qualified engagement plus explicit fan intent (follow/save)
 * into reusable discovery feeds. Ranking remains provider-neutral and does not
 * alter creator earnings or payable engagement.
 */
class Nfinite_Creators_Discovery {
	const CACHE_GROUP_VERSION = '2';

	public static function init() {
		add_shortcode( 'nfinite_discovery', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'nfinite_discovery_hub', array( __CLASS__, 'hub_shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
		add_action( 'nfinite_engagement_qualified', array( __CLASS__, 'bust_global_cache' ) );
		add_action( 'nfinite_video_engagement_qualified', array( __CLASS__, 'bust_global_cache' ) );
		add_action( 'wp_ajax_nfinite_library_toggle', array( __CLASS__, 'bust_user_cache_late' ), 99 );
	}

	public static function supported_types() {
		return array( 'nfinite_release', 'nfinite_track', 'nfinite_show', 'nfinite_episode', 'nfinite_video', 'nfinite_event', 'nfinite_creator_post', 'nfinite_playlist', 'post' );
	}

	public static function register_rest() {
		register_rest_route( 'nfinite/v1', '/discovery', array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'rest_feed' ),
			'args'                => array(
				'feed'  => array( 'default' => 'trending', 'sanitize_callback' => 'sanitize_key' ),
				'limit' => array( 'default' => 12, 'sanitize_callback' => 'absint' ),
				'types' => array( 'default' => '' ),
			),
		) );
	}

	public static function rest_feed( WP_REST_Request $request ) {
		$items = self::get_feed( $request->get_param( 'feed' ), array(
			'limit' => min( 50, max( 1, absint( $request->get_param( 'limit' ) ) ) ),
			'types' => self::parse_types( $request->get_param( 'types' ) ),
		) );
		return rest_ensure_response( array(
			'feed'  => sanitize_key( $request->get_param( 'feed' ) ),
			'items' => array_map( array( __CLASS__, 'item_payload' ), $items ),
		) );
	}

	private static function parse_types( $value ) {
		if ( is_array( $value ) ) { $types = $value; }
		else { $types = preg_split( '/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY ); }
		$types = array_values( array_intersect( array_map( 'sanitize_key', (array) $types ), self::supported_types() ) );
		return $types ?: self::supported_types();
	}

	private static function normalize_feed( $feed ) {
		$feed = sanitize_key( $feed );
		return in_array( $feed, array( 'trending', 'popular', 'new', 'following', 'recommended' ), true ) ? $feed : 'trending';
	}

	private static function creator_id_for( $post_id ) {
		$post_id = absint( $post_id );
		$type = get_post_type( $post_id );
		if ( 'nfinite_creator' === $type ) { return $post_id; }
		$keys = array(
			'nfinite_release'      => '_nfinite_release_creator_id',
			'nfinite_track'        => '_nfinite_track_creator_id',
			'nfinite_show'         => '_nfinite_show_creator_id',
			'nfinite_episode'      => '_nfinite_episode_creator_id',
			'nfinite_video'        => '_nfinite_video_creator_id',
			'nfinite_creator_post' => '_nfinite_creator_id',
			'nfinite_playlist'     => '_nfinite_playlist_creator_id',
		);
		if ( isset( $keys[ $type ] ) ) {
			$creator = absint( get_post_meta( $post_id, $keys[ $type ], true ) );
			if ( $creator ) { return $creator; }
		}
		if ( 'nfinite_episode' === $type ) {
			$show = absint( get_post_meta( $post_id, '_nfinite_episode_show_id', true ) );
			if ( $show ) { return absint( get_post_meta( $show, '_nfinite_show_creator_id', true ) ); }
		}
		if ( 'nfinite_event' === $type ) {
			$ids = get_post_meta( $post_id, '_nfinite_event_creator_ids', true );
			return is_array( $ids ) && $ids ? absint( reset( $ids ) ) : 0;
		}
		return absint( get_post_meta( $post_id, '_nfinite_creator_id', true ) );
	}

	private static function release_for_track( $track_id ) {
		$release = absint( get_post_meta( $track_id, '_nfinite_track_release_id', true ) );
		if ( $release ) { return $release; }
		global $wpdb;
		$like = '%i:' . absint( $track_id ) . ';%';
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_nfinite_release_track_ids' AND meta_value LIKE %s LIMIT 1", $like ) ) );
	}

	private static function global_scores( $days = 7 ) {
		$days = max( 1, absint( $days ) );
		$key = 'nfinite_discovery_scores_v' . self::CACHE_GROUP_VERSION . '_' . $days . '_' . (string) get_option( 'nfinite_discovery_cache_bust', '1' );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) { return $cached; }
		global $wpdb;
		$scores = array();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		if ( class_exists( 'Nfinite_Creators_Engagement' ) ) {
			$table = Nfinite_Creators_Engagement::table_name();
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT object_id, COUNT(*) raw_count, SUM(qualified) qualified_count, SUM(listened_ms) time_ms, COUNT(DISTINCT visitor_hash) people FROM {$table} WHERE started_at >= %s AND fraud_score < 70 GROUP BY object_id",
					$since
				), ARRAY_A );
				foreach ( (array) $rows as $row ) {
					$id = absint( $row['object_id'] ); if ( ! $id || 'publish' !== get_post_status( $id ) ) { continue; }
					$score = ( (int) $row['qualified_count'] * 8 ) + ( (int) $row['people'] * 3 ) + min( 100, (int) floor( (int) $row['time_ms'] / 60000 ) ) + min( 40, (int) $row['raw_count'] );
					$scores[ $id ] = ( $scores[ $id ] ?? 0 ) + $score;
					$release = self::release_for_track( $id );
					if ( $release && 'publish' === get_post_status( $release ) ) { $scores[ $release ] = ( $scores[ $release ] ?? 0 ) + (int) round( $score * .65 ); }
				}
			}
		}
		if ( class_exists( 'Nfinite_Creators_Video_Engagement' ) ) {
			$table = Nfinite_Creators_Video_Engagement::table_name();
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT object_id, object_type, COUNT(*) raw_count, SUM(qualified) qualified_count, SUM(watched_ms) time_ms, COUNT(DISTINCT visitor_hash) people FROM {$table} WHERE started_at >= %s AND fraud_score < 70 GROUP BY object_type, object_id",
					$since
				), ARRAY_A );
				foreach ( (array) $rows as $row ) {
					$id = absint( $row['object_id'] ); if ( ! $id || 'publish' !== get_post_status( $id ) ) { continue; }
					$score = ( (int) $row['qualified_count'] * 10 ) + ( (int) $row['people'] * 4 ) + min( 140, (int) floor( (int) $row['time_ms'] / 60000 ) ) + min( 40, (int) $row['raw_count'] );
					$scores[ $id ] = ( $scores[ $id ] ?? 0 ) + $score;
					if ( 'episode' === $row['object_type'] ) {
						$show = absint( get_post_meta( $id, '_nfinite_episode_show_id', true ) );
						if ( $show && 'publish' === get_post_status( $show ) ) { $scores[ $show ] = ( $scores[ $show ] ?? 0 ) + (int) round( $score * .6 ); }
					}
				}
			}
		}
		arsort( $scores, SORT_NUMERIC );
		set_transient( $key, $scores, 15 * MINUTE_IN_SECONDS );
		return $scores;
	}

	private static function explicit_signal_counts() {
		$key = 'nfinite_discovery_intent_v2_' . (string) get_option( 'nfinite_discovery_cache_bust', '1' );
		$cached = get_transient( $key ); if ( is_array( $cached ) ) { return $cached; }
		global $wpdb;
		$out = array();
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key=%s", Nfinite_Creators_Library::SAVE_META ) );
		foreach ( (array) $rows as $serialized ) {
			$ids = maybe_unserialize( $serialized );
			if ( ! is_array( $ids ) ) { continue; }
			foreach ( $ids as $id ) { $id=absint($id); if($id) $out[$id]=($out[$id]??0)+1; }
		}
		set_transient( $key, $out, 15 * MINUTE_IN_SECONDS ); return $out;
	}

	private static function recent_candidates( $types, $limit = 80 ) {
		return get_posts( array(
			'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => max( 20, absint( $limit ) ),
			'orderby' => 'date', 'order' => 'DESC', 'suppress_filters' => false,
		) );
	}

	private static function ranked_global( $feed, $types, $limit ) {
		$days = 'popular' === $feed ? 30 : 7;
		$scores = self::global_scores( $days ); $saves = self::explicit_signal_counts();
		$candidates = self::recent_candidates( $types, 120 ); $rank = array(); $now = time();
		foreach ( $candidates as $post ) {
			$age_days = max( 0, ( $now - get_post_time( 'U', true, $post ) ) / DAY_IN_SECONDS );
			$engagement = (float) ( $scores[ $post->ID ] ?? 0 ); $intent = (int) ( $saves[ $post->ID ] ?? 0 );
			$recency = max( 0, 30 - min( 30, $age_days ) );
			$rank[ $post->ID ] = $engagement + ( $intent * 7 ) + ( 'trending' === $feed ? $recency * 1.6 : $recency * .35 );
		}
		foreach ( $scores as $id => $score ) {
			if ( ! in_array( get_post_type( $id ), $types, true ) || 'publish' !== get_post_status( $id ) ) { continue; }
			if ( ! isset( $rank[$id] ) ) { $rank[$id]=(float)$score + (int)($saves[$id]??0)*7; }
		}
		arsort( $rank, SORT_NUMERIC ); $ids = array_slice( array_keys( $rank ), 0, $limit );
		return self::posts_in_rank_order( $ids );
	}

	private static function following_feed( $types, $limit, $user_id ) {
		if ( ! $user_id ) { return array(); }
		$follows = get_user_meta( $user_id, Nfinite_Creators_Library::FOLLOW_META, true );
		$follows = is_array( $follows ) ? array_values( array_filter( array_map( 'absint', $follows ) ) ) : array();
		if ( ! $follows ) { return array(); }
		$candidates = self::recent_candidates( $types, 160 ); $out=array();
		foreach ( $candidates as $post ) { if ( in_array( self::creator_id_for( $post->ID ), $follows, true ) ) { $out[]=$post; if(count($out)>=$limit)break; } }
		return $out;
	}

	private static function recommended_feed( $types, $limit, $user_id ) {
		if ( ! $user_id ) { return self::ranked_global( 'trending', $types, $limit ); }
		$follows = get_user_meta( $user_id, Nfinite_Creators_Library::FOLLOW_META, true ); $follows=is_array($follows)?array_filter(array_map('absint',$follows)):array();
		$saved = get_user_meta( $user_id, Nfinite_Creators_Library::SAVE_META, true ); $saved=is_array($saved)?array_filter(array_map('absint',$saved)):array();
		$affinity=array(); foreach($follows as $cid){$affinity[$cid]=($affinity[$cid]??0)+18;} foreach($saved as $sid){$cid=self::creator_id_for($sid);if($cid)$affinity[$cid]=($affinity[$cid]??0)+8;}
		$scores=self::global_scores(14); $global_saves=self::explicit_signal_counts(); $candidates=self::recent_candidates($types,180); $rank=array(); $now=time();
		foreach($candidates as $post){
			if(in_array($post->ID,$saved,true))continue;
			$cid=self::creator_id_for($post->ID); $age=max(0,($now-get_post_time('U',true,$post))/DAY_IN_SECONDS);
			$rank[$post->ID]=(float)($scores[$post->ID]??0)+(int)($global_saves[$post->ID]??0)*5+(float)($affinity[$cid]??0)+max(0,20-min(20,$age));
		}
		arsort($rank,SORT_NUMERIC); return self::posts_in_rank_order(array_slice(array_keys($rank),0,$limit));
	}

	private static function posts_in_rank_order( $ids ) {
		if(!$ids)return array(); $posts=get_posts(array('post_type'=>'any','post_status'=>'publish','post__in'=>$ids,'orderby'=>'post__in','posts_per_page'=>count($ids)));
		return is_array($posts)?$posts:array();
	}

	public static function get_feed( $feed = 'trending', $args = array() ) {
		$feed=self::normalize_feed($feed); $args=wp_parse_args($args,array('limit'=>12,'types'=>self::supported_types(),'user_id'=>get_current_user_id()));
		$limit=min(50,max(1,absint($args['limit']))); $types=self::parse_types($args['types']); $uid=absint($args['user_id']);
		if('new'===$feed)return array_slice(self::recent_candidates($types,$limit),0,$limit);
		if('following'===$feed){$items=self::following_feed($types,$limit,$uid);return $items?:self::ranked_global('trending',$types,$limit);}
		if('recommended'===$feed)return self::recommended_feed($types,$limit,$uid);
		return self::ranked_global($feed,$types,$limit);
	}

	public static function item_payload( $post ) {
		if ( is_numeric( $post ) ) { $post=get_post(absint($post)); } if(!$post)return array();
		$creator=self::creator_id_for($post->ID); $type_obj=get_post_type_object($post->post_type);
		$image=get_the_post_thumbnail_url($post,'medium_large')?:'';
		// Discovery cards should never look unfinished simply because the content
		// object has no dedicated artwork. Fall back to the owning creator's
		// profile image while keeping the source content URL/title unchanged.
		if(!$image && $creator){ $image=get_the_post_thumbnail_url($creator,'medium_large')?:''; }
		return array('id'=>$post->ID,'type'=>$post->post_type,'type_label'=>$type_obj?$type_obj->labels->singular_name:$post->post_type,'title'=>get_the_title($post),'url'=>get_permalink($post),'image'=>$image,'creator_id'=>$creator,'creator'=>$creator?get_the_title($creator):'','date'=>get_post_time('c',true,$post));
	}

	private static function render_cards( $items ) {
		if(!$items)return '<p class="nfinite-discovery-empty">'.esc_html__('Discovery gets smarter as creators publish and fans engage.','nfinite-creators').'</p>';
		ob_start(); echo '<div class="nfinite-discovery-grid">'; foreach($items as $post){$p=self::item_payload($post);echo '<article class="nfinite-discovery-card" data-discovery-type="'.esc_attr($p['type']).'">';
		if($p['image'])echo '<a class="nfinite-discovery-art" href="'.esc_url($p['url']).'"><img src="'.esc_url($p['image']).'" alt="" loading="lazy"></a>';
		echo '<div class="nfinite-discovery-card-body"><small>'.esc_html($p['type_label']).'</small><h3><a href="'.esc_url($p['url']).'">'.esc_html($p['title']).'</a></h3>'; if($p['creator'])echo '<p>'.esc_html($p['creator']).'</p>';
		if(class_exists('Nfinite_Creators_Library'))echo Nfinite_Creators_Library::button($p['id'],'save'); echo '</div></article>'; } echo '</div>'; return ob_get_clean();
	}

	private static function mobile_single_row_carousel_style() {
		static $printed = false;
		if ( $printed ) { return ''; }
		$printed = true;

		// Render this after theme styles so WPNfinite's mobile grid rules cannot
		// turn discovery back into two columns. The body-level style is scoped to
		// the platform discovery block and only activates on small screens.
		return '<style id="nfinite-discovery-mobile-single-row-v0569">
		@media (max-width: 760px) {
		  body .wpnfinite-platform-discovery .nfinite-discovery-grid,
		  body.home .nfinite-discovery-feed .nfinite-discovery-grid {
		    display:flex !important;
		    flex-flow:row nowrap !important;
		    grid-template-columns:none !important;
		    grid-auto-flow:initial !important;
		    width:100% !important;
		    max-width:100% !important;
		    gap:12px !important;
		    overflow-x:auto !important;
		    overflow-y:hidden !important;
		    scroll-snap-type:x mandatory;
		    -webkit-overflow-scrolling:touch;
		    overscroll-behavior-x:contain;
		    scrollbar-width:none;
		    padding:2px 18px 12px 0 !important;
		    margin:0 !important;
		  }
		  body .wpnfinite-platform-discovery .nfinite-discovery-grid::-webkit-scrollbar,
		  body.home .nfinite-discovery-feed .nfinite-discovery-grid::-webkit-scrollbar { display:none !important; }
		  body .wpnfinite-platform-discovery .nfinite-discovery-grid > .nfinite-discovery-card,
		  body.home .nfinite-discovery-feed .nfinite-discovery-grid > .nfinite-discovery-card {
		    display:block !important;
		    flex:0 0 78vw !important;
		    width:78vw !important;
		    min-width:78vw !important;
		    max-width:260px !important;
		    scroll-snap-align:start;
		  }
		}
		</style>';
	}

	public static function shortcode( $atts ) {
		$a=shortcode_atts(array('feed'=>'trending','limit'=>12,'types'=>'','title'=>''),$atts,'nfinite_discovery'); $feed=self::normalize_feed($a['feed']); $items=self::get_feed($feed,array('limit'=>$a['limit'],'types'=>self::parse_types($a['types'])));
		$labels=array('trending'=>__('Trending Now','nfinite-creators'),'popular'=>__('Popular','nfinite-creators'),'new'=>__('New & Fresh','nfinite-creators'),'following'=>__('From Creators You Follow','nfinite-creators'),'recommended'=>__('Recommended For You','nfinite-creators'));
		$title=$a['title']!==''?sanitize_text_field($a['title']):$labels[$feed];
		return '<section class="nfinite-discovery-feed nfinite-discovery-feed--'.esc_attr($feed).'"><header><span class="nfinite-eyebrow">'.esc_html__('PairOfDice Discovery','nfinite-creators').'</span><h2>'.esc_html($title).'</h2></header>'.self::render_cards($items).'</section>'.self::mobile_single_row_carousel_style();
	}

	public static function hub_shortcode( $atts ) {
		$a=shortcode_atts(array('limit'=>8,'types'=>''),$atts,'nfinite_discovery_hub'); $types=self::parse_types($a['types']);
		ob_start(); echo '<div class="nfinite-discovery-hub" data-nfinite-discovery-v2><header class="nfinite-discovery-hero"><span class="nfinite-eyebrow">'.esc_html__('Discover PairOfDice','nfinite-creators').'</span><h1>'.esc_html__('What to watch, hear, and follow next','nfinite-creators').'</h1><p>'.esc_html__('Discovery blends what is new, what people are genuinely engaging with, and the creators you choose to follow.','nfinite-creators').'</p></header>';
		foreach(array('recommended','following','trending','new','popular') as $feed){echo self::shortcode(array('feed'=>$feed,'limit'=>$a['limit'],'types'=>$types));} echo '</div>'; return ob_get_clean();
	}

	public static function bust_global_cache() { update_option('nfinite_discovery_cache_bust',(string)microtime(true),false); }
	public static function bust_user_cache_late() { self::bust_global_cache(); }
}
