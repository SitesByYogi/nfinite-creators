<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Sponsorships + Promoted Content.
 * Connects the 0.44 campaign engine to first-class PairOfDice content objects.
 */
class Nfinite_Creators_Promoted_Content {
	const META_OBJECTS = '_nfinite_campaign_promoted_objects';
	const META_TYPE = '_nfinite_campaign_promotion_type';
	const META_DISCLOSURE = '_nfinite_campaign_sponsor_disclosure';
	const META_CTA = '_nfinite_campaign_promotion_cta';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ), 20 );
		add_action( 'save_post_nfinite_campaign', array( __CLASS__, 'save_campaign' ), 20 );
		add_shortcode( 'nfinite_promoted_content', array( __CLASS__, 'shortcode' ) );
		add_filter( 'the_content', array( __CLASS__, 'content_disclosure' ), 8 );
		add_filter( 'manage_nfinite_campaign_posts_columns', array( __CLASS__, 'campaign_columns' ) );
		add_action( 'manage_nfinite_campaign_posts_custom_column', array( __CLASS__, 'campaign_column' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_default_placements' ) );
	}

	public static function promotion_types() {
		return array(
			'featured_creator' => 'Featured Creator',
			'sponsored_release' => 'Sponsored Release',
			'sponsored_video' => 'Sponsored Video / Episode',
			'sponsored_show' => 'Sponsored Show / Series',
			'promoted_event' => 'Promoted Event',
			'editorial' => 'Sponsored Editorial',
			'network_sponsorship' => 'Channel / Network Sponsorship',
		);
	}

	public static function surfaces() {
		return array(
			'home_featured' => 'Home Featured',
			'rotation_featured' => 'The Rotation',
			'radio_sponsor' => 'PairOfDice Radio',
			'tv_featured' => 'PairOfDice TV',
			'creator_profile_featured' => 'Creator Profiles',
			'editorial_inline' => 'Editorial',
			'events_featured' => 'Events',
		);
	}

	public static function ensure_default_placements() {
		if ( ! current_user_can( 'manage_options' ) || ! post_type_exists( Nfinite_Creators_Monetization::PLACEMENT ) ) return;
		if ( get_option( 'nfinite_promoted_default_placements_v1' ) ) return;
		foreach ( self::surfaces() as $key => $label ) {
			$found = get_posts( array( 'post_type' => Nfinite_Creators_Monetization::PLACEMENT, 'post_status' => 'any', 'numberposts' => 1, 'meta_key' => '_nfinite_placement_key', 'meta_value' => $key ) );
			if ( $found ) continue;
			$id = wp_insert_post( array( 'post_type' => Nfinite_Creators_Monetization::PLACEMENT, 'post_status' => 'publish', 'post_title' => $label ) );
			if ( ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_nfinite_placement_key', $key );
				update_post_meta( $id, '_nfinite_placement_description', 'Default PairOfDice sponsored/promoted inventory created by Nfinite Creators.' );
			}
		}
		update_option( 'nfinite_promoted_default_placements_v1', 1 );
	}

	public static function meta_boxes() {
		add_meta_box( 'nfinite_campaign_promoted_content', __( 'Sponsorship & Promoted Content', 'nfinite-creators' ), array( __CLASS__, 'campaign_box' ), Nfinite_Creators_Monetization::CAMPAIGN, 'normal', 'high' );
	}

	private static function supported_post_types() {
		$types = array( 'nfinite_creator', 'nfinite_release', 'nfinite_show', 'nfinite_episode', 'nfinite_video', 'nfinite_event', 'post' );
		return array_values( array_filter( $types, 'post_type_exists' ) );
	}

	public static function campaign_box( $post ) {
		wp_nonce_field( 'nfinite_promoted_content_save', 'nfinite_promoted_content_nonce' );
		$type = get_post_meta( $post->ID, self::META_TYPE, true );
		$disclosure = get_post_meta( $post->ID, self::META_DISCLOSURE, true );
		$cta = get_post_meta( $post->ID, self::META_CTA, true );
		$selected = array_map( 'absint', (array) get_post_meta( $post->ID, self::META_OBJECTS, true ) );
		echo '<p><label><strong>Promotion type</strong></label><br><select name="'.esc_attr(self::META_TYPE).'"><option value="">Standard campaign</option>';
		foreach ( self::promotion_types() as $value => $label ) echo '<option value="'.esc_attr($value).'" '.selected($type,$value,false).'>'.esc_html($label).'</option>';
		echo '</select></p>';
		echo '<p><label><strong>Disclosure label</strong></label><br><input style="width:100%" type="text" name="'.esc_attr(self::META_DISCLOSURE).'" value="'.esc_attr($disclosure ?: 'Sponsored').'" placeholder="Sponsored"></p>';
		echo '<p><label><strong>CTA label</strong></label><br><input style="width:100%" type="text" name="'.esc_attr(self::META_CTA).'" value="'.esc_attr($cta ?: 'Learn More').'" placeholder="Learn More"></p>';
		echo '<p><strong>Promoted content</strong><br><span class="description">Associate this campaign with existing PairOfDice content. Creator attribution on delivery can feed the creator sponsorship pool.</span></p>';
		foreach ( self::supported_post_types() as $pt ) {
			$obj = get_post_type_object( $pt );
			$items = get_posts( array( 'post_type'=>$pt, 'post_status'=>'publish', 'numberposts'=>100, 'orderby'=>'date', 'order'=>'DESC' ) );
			if ( ! $items ) continue;
			echo '<details style="margin:8px 0"><summary><strong>'.esc_html($obj ? $obj->labels->name : $pt).'</strong></summary><div style="max-height:180px;overflow:auto;border:1px solid #ddd;padding:8px;margin-top:6px">';
			foreach ( $items as $item ) echo '<label style="display:block;margin:3px 0"><input type="checkbox" name="'.esc_attr(self::META_OBJECTS).'[]" value="'.absint($item->ID).'" '.checked(in_array($item->ID,$selected,true),true,false).'> '.esc_html($item->post_title ?: '(Untitled)').'</label>';
			echo '</div></details>';
		}
	}

	public static function save_campaign( $id ) {
		if ( ! isset($_POST['nfinite_promoted_content_nonce']) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nfinite_promoted_content_nonce'])), 'nfinite_promoted_content_save' ) || ! current_user_can('edit_post',$id) ) return;
		$type = sanitize_key( wp_unslash($_POST[self::META_TYPE] ?? '') );
		if ( ! array_key_exists( $type, self::promotion_types() ) ) $type = '';
		update_post_meta( $id, self::META_TYPE, $type );
		update_post_meta( $id, self::META_DISCLOSURE, sanitize_text_field( wp_unslash($_POST[self::META_DISCLOSURE] ?? 'Sponsored') ) );
		update_post_meta( $id, self::META_CTA, sanitize_text_field( wp_unslash($_POST[self::META_CTA] ?? 'Learn More') ) );
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash($_POST[self::META_OBJECTS] ?? array()) ) ) ) );
		$ids = array_values( array_filter( $ids, function($pid){ return get_post($pid) && 'publish' === get_post_status($pid); } ) );
		update_post_meta( $id, self::META_OBJECTS, $ids );
	}

	private static function active_campaigns_for_surface( $surface ) {
		$placements = get_posts( array( 'post_type'=>Nfinite_Creators_Monetization::PLACEMENT, 'post_status'=>'publish', 'numberposts'=>1, 'meta_key'=>'_nfinite_placement_key', 'meta_value'=>$surface ) );
		if ( ! $placements ) return array();
		$pid = $placements[0]->ID; $now = current_time('Y-m-d\TH:i'); $out = array();
		$campaigns = get_posts( array( 'post_type'=>Nfinite_Creators_Monetization::CAMPAIGN, 'post_status'=>'publish', 'numberposts'=>100, 'meta_key'=>'_nfinite_campaign_status', 'meta_value'=>'active' ) );
		foreach ( $campaigns as $campaign ) {
			$ps = array_map('absint',(array)get_post_meta($campaign->ID,'_nfinite_campaign_placements',true)); if(!in_array($pid,$ps,true)) continue;
			$start=get_post_meta($campaign->ID,'_nfinite_campaign_start',true); $end=get_post_meta($campaign->ID,'_nfinite_campaign_end',true); if($start&&$now<$start)continue; if($end&&$now>$end)continue;
			if ( ! get_post_meta($campaign->ID,self::META_TYPE,true) ) continue; $out[]=$campaign;
		}
		return array($pid,$out);
	}

	private static function creator_for_object( $post_id ) {
		foreach ( array('_nfinite_creator_id','_nfinite_episode_creator','_nfinite_release_creator','nfinite_creator_id','creator_id') as $key ) { $v=absint(get_post_meta($post_id,$key,true)); if($v)return $v; }
		if ( 'nfinite_creator' === get_post_type($post_id) ) return $post_id;
		return 0;
	}

	public static function shortcode( $atts ) {
		$a = shortcode_atts( array('surface'=>'home_featured','limit'=>4,'campaign_id'=>0), $atts );
		$surface=sanitize_key($a['surface']); $limit=max(1,min(12,absint($a['limit']))); $campaign_id=absint($a['campaign_id']);
		$data=self::active_campaigns_for_surface($surface); if(!$data)return ''; list($placement_id,$campaigns)=$data;
		if($campaign_id)$campaigns=array_values(array_filter($campaigns,function($c)use($campaign_id){return $c->ID===$campaign_id;})); if(!$campaigns)return '';
		$cards=array(); foreach($campaigns as $campaign){ foreach((array)get_post_meta($campaign->ID,self::META_OBJECTS,true) as $oid){$oid=absint($oid);if(!$oid||'publish'!==get_post_status($oid))continue;$creator=self::creator_for_object($oid);if(class_exists('Nfinite_Creators_Ad_Delivery')&&!Nfinite_Creators_Ad_Delivery::is_campaign_eligible($campaign,$placement_id,$creator,$oid))continue;$cards[]=array($campaign,$oid);if(count($cards)>=$limit)break 2;} }
		if(!$cards)return '';
		wp_enqueue_script('nfinite-monetization'); $html='<section class="nfinite-promoted-content nfinite-promoted-'.esc_attr($surface).'">';
		foreach($cards as $row){list($campaign,$oid)=$row;$label=get_post_meta($campaign->ID,self::META_DISCLOSURE,true)?:'Sponsored';$cta=get_post_meta($campaign->ID,self::META_CTA,true)?:'Learn More';$destination=get_post_meta($campaign->ID,'_nfinite_campaign_destination',true);$url=$destination?:get_permalink($oid);$thumb=get_the_post_thumbnail_url($oid,'large');$creator=self::creator_for_object($oid);
			$html.='<article class="nfinite-promoted-card nfinite-monetization-placement" data-nfinite-campaign="'.absint($campaign->ID).'" data-nfinite-placement="'.absint($placement_id).'" data-nfinite-creator="'.absint($creator).'" data-nfinite-content="'.absint($oid).'" data-nfinite-creative="native_'.absint($oid).'">';
			$html.='<span class="nfinite-sponsored-label">'.esc_html($label).'</span>'; if($thumb)$html.='<a class="nfinite-campaign-link" href="'.esc_url($url).'"'.($destination?' target="_blank" rel="sponsored noopener"':'').'><img src="'.esc_url($thumb).'" alt=""></a>';
			$html.='<h3><a class="nfinite-campaign-link" href="'.esc_url($url).'"'.($destination?' target="_blank" rel="sponsored noopener"':'').'>'.esc_html(get_the_title($oid)).'</a></h3><span class="nfinite-promoted-cta">'.esc_html($cta).'</span></article>';
		}
		return $html.'</section>';
	}

	public static function content_disclosure( $content ) {
		if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) return $content;
		$id=get_the_ID(); if(!$id)return $content;
		$campaigns=get_posts(array('post_type'=>Nfinite_Creators_Monetization::CAMPAIGN,'post_status'=>'publish','numberposts'=>50,'meta_key'=>'_nfinite_campaign_status','meta_value'=>'active'));
		foreach($campaigns as $campaign){$ids=array_map('absint',(array)get_post_meta($campaign->ID,self::META_OBJECTS,true));if(!in_array($id,$ids,true))continue;$label=get_post_meta($campaign->ID,self::META_DISCLOSURE,true)?:'Sponsored';return '<div class="nfinite-sponsorship-disclosure"><strong>'.esc_html($label).'</strong></div>'.$content;}
		return $content;
	}

	public static function campaign_columns( $columns ) { $columns['nfinite_promotion']='Promotion'; return $columns; }
	public static function campaign_column( $column, $post_id ) { if('nfinite_promotion'!==$column)return;$type=get_post_meta($post_id,self::META_TYPE,true);$types=self::promotion_types();$count=count((array)get_post_meta($post_id,self::META_OBJECTS,true));echo $type?esc_html(($types[$type]??$type).' · '.$count.' item'.($count===1?'':'s')):'—'; }
}
