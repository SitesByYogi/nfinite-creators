<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Advertising inventory + campaign delivery controls.
 * Adds pacing, goals, caps, targeting and weighted creative rotation to the 0.44 campaign engine.
 */
class Nfinite_Creators_Ad_Delivery {
	const META_GOAL_IMPRESSIONS = '_nfinite_campaign_goal_impressions';
	const META_GOAL_CLICKS = '_nfinite_campaign_goal_clicks';
	const META_DAILY_CAP = '_nfinite_campaign_daily_impression_cap';
	const META_FREQUENCY_CAP = '_nfinite_campaign_frequency_cap';
	const META_PACING = '_nfinite_campaign_pacing';
	const META_PRIORITY = '_nfinite_campaign_priority';
	const META_DEVICE = '_nfinite_campaign_target_device';
	const META_AUDIENCE = '_nfinite_campaign_target_audience';
	const META_POST_TYPES = '_nfinite_campaign_target_post_types';
	const META_CREATORS = '_nfinite_campaign_target_creators';
	const META_CREATIVES = '_nfinite_campaign_creatives';
	const PLACEMENT_DAILY_CAP = '_nfinite_placement_daily_cap';
	const PLACEMENT_FORMAT = '_nfinite_placement_format';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ), 25 );
		add_action( 'save_post_nfinite_campaign', array( __CLASS__, 'save_campaign' ), 25 );
		add_action( 'save_post_nfinite_placement', array( __CLASS__, 'save_placement' ), 25 );
		add_filter( 'manage_nfinite_campaign_posts_columns', array( __CLASS__, 'campaign_columns' ) );
		add_action( 'manage_nfinite_campaign_posts_custom_column', array( __CLASS__, 'campaign_column' ), 10, 2 );
	}

	public static function meta_boxes() {
		add_meta_box( 'nfinite_campaign_delivery', __( 'Inventory & Delivery', 'nfinite-creators' ), array( __CLASS__, 'campaign_box' ), Nfinite_Creators_Monetization::CAMPAIGN, 'normal', 'default' );
		add_meta_box( 'nfinite_campaign_creatives', __( 'Creative Rotation', 'nfinite-creators' ), array( __CLASS__, 'creative_box' ), Nfinite_Creators_Monetization::CAMPAIGN, 'normal', 'default' );
		add_meta_box( 'nfinite_placement_delivery', __( 'Delivery Capacity', 'nfinite-creators' ), array( __CLASS__, 'placement_box' ), Nfinite_Creators_Monetization::PLACEMENT, 'side', 'default' );
	}

	private static function input( $id, $label, $type = 'number', $min = 0, $step = 1 ) {
		$v = get_post_meta( get_the_ID(), $id, true );
		echo '<p><label><strong>'.esc_html($label).'</strong></label><br><input style="width:100%" type="'.esc_attr($type).'" name="'.esc_attr($id).'" value="'.esc_attr($v).'" min="'.esc_attr($min).'" step="'.esc_attr($step).'"></p>';
	}

	public static function campaign_box( $post ) {
		wp_nonce_field( 'nfinite_ad_delivery_save', 'nfinite_ad_delivery_nonce' );
		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">';
		self::input( self::META_GOAL_IMPRESSIONS, 'Impression goal' );
		self::input( self::META_GOAL_CLICKS, 'Click goal' );
		self::input( self::META_DAILY_CAP, 'Daily impression cap' );
		self::input( self::META_FREQUENCY_CAP, 'Frequency cap / visitor / day' );
		echo '</div>';

		$pacing = get_post_meta( $post->ID, self::META_PACING, true ) ?: 'even';
		$priority = absint( get_post_meta( $post->ID, self::META_PRIORITY, true ) ?: 5 );
		$device = get_post_meta( $post->ID, self::META_DEVICE, true ) ?: 'all';
		$audience = get_post_meta( $post->ID, self::META_AUDIENCE, true ) ?: 'all';
		echo '<p><label><strong>Pacing</strong></label><br><select name="'.esc_attr(self::META_PACING).'">';
		foreach ( array( 'even'=>'Even delivery', 'asap'=>'Deliver as available' ) as $v=>$l ) echo '<option value="'.esc_attr($v).'" '.selected($pacing,$v,false).'>'.esc_html($l).'</option>';
		echo '</select> &nbsp; <label><strong>Priority</strong> <input type="number" name="'.esc_attr(self::META_PRIORITY).'" min="1" max="10" value="'.esc_attr($priority).'" style="width:70px"></label></p>';

		echo '<p><label><strong>Device targeting</strong></label><br><select name="'.esc_attr(self::META_DEVICE).'">';
		foreach ( array( 'all'=>'All devices','desktop'=>'Desktop only','mobile'=>'Mobile only' ) as $v=>$l ) echo '<option value="'.esc_attr($v).'" '.selected($device,$v,false).'>'.esc_html($l).'</option>';
		echo '</select> &nbsp; <label><strong>Audience</strong> <select name="'.esc_attr(self::META_AUDIENCE).'">';
		foreach ( array( 'all'=>'Everyone','logged_in'=>'Logged-in only','logged_out'=>'Logged-out only' ) as $v=>$l ) echo '<option value="'.esc_attr($v).'" '.selected($audience,$v,false).'>'.esc_html($l).'</option>';
		echo '</select></label></p>';

		$selected = array_map( 'sanitize_key', (array) get_post_meta( $post->ID, self::META_POST_TYPES, true ) );
		$pts = get_post_types( array( 'public'=>true ), 'objects' );
		echo '<p><strong>Content targeting</strong><br><span class="description">Leave all unchecked to allow every page type.</span><br>';
		foreach ( $pts as $pt ) {
			if ( 'attachment' === $pt->name ) continue;
			echo '<label style="display:inline-block;margin:4px 12px 4px 0"><input type="checkbox" name="'.esc_attr(self::META_POST_TYPES).'[]" value="'.esc_attr($pt->name).'" '.checked(in_array($pt->name,$selected,true),true,false).'> '.esc_html($pt->labels->singular_name).'</label>';
		}
		echo '</p>';

		$creator_ids = implode( ',', array_map( 'absint', (array) get_post_meta( $post->ID, self::META_CREATORS, true ) ) );
		echo '<p><label><strong>Creator targeting</strong></label><br><input style="width:100%" type="text" name="'.esc_attr(self::META_CREATORS).'" value="'.esc_attr($creator_ids).'" placeholder="Creator post IDs, comma-separated"><br><span class="description">Optional. When set, delivery is limited to placements rendered with one of these creator IDs.</span></p>';
	}

	public static function creative_box( $post ) {
		$creatives = (array) get_post_meta( $post->ID, self::META_CREATIVES, true );
		if ( ! $creatives ) $creatives = array();
		echo '<p class="description">Optional variants rotate inside standard ad placements. If none are configured, the original 0.44 campaign creative fields remain the fallback.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Key</th><th>Image URL</th><th>Destination URL</th><th>Copy</th><th style="width:80px">Weight</th></tr></thead><tbody>';
		for ( $i=0; $i<5; $i++ ) {
			$c = isset($creatives[$i]) && is_array($creatives[$i]) ? $creatives[$i] : array();
			echo '<tr>';
			foreach ( array('key'=>'text','image'=>'url','url'=>'url','copy'=>'text','weight'=>'number') as $field=>$type ) {
				$v = $c[$field] ?? ( 'weight' === $field ? 1 : '' );
				echo '<td><input style="width:100%" type="'.esc_attr($type).'" name="nfinite_creatives['.absint($i).']['.esc_attr($field).']" value="'.esc_attr($v).'"'.('weight'===$field?' min="1" max="100"':'').'></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public static function placement_box( $post ) {
		wp_nonce_field( 'nfinite_placement_delivery_save', 'nfinite_placement_delivery_nonce' );
		self::input( self::PLACEMENT_DAILY_CAP, 'Daily placement impression cap' );
		$format = get_post_meta( $post->ID, self::PLACEMENT_FORMAT, true ) ?: 'responsive';
		echo '<p><label><strong>Format</strong></label><br><select name="'.esc_attr(self::PLACEMENT_FORMAT).'">';
		foreach ( array('responsive'=>'Responsive','display'=>'Display/banner','native'=>'Native/promoted content','audio'=>'Audio sponsorship','video'=>'Video sponsorship') as $v=>$l ) echo '<option value="'.esc_attr($v).'" '.selected($format,$v,false).'>'.esc_html($l).'</option>';
		echo '</select></p>';
	}

	public static function save_campaign( $id ) {
		if ( ! isset($_POST['nfinite_ad_delivery_nonce']) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nfinite_ad_delivery_nonce'])), 'nfinite_ad_delivery_save' ) || ! current_user_can('edit_post',$id) ) return;
		foreach ( array(self::META_GOAL_IMPRESSIONS,self::META_GOAL_CLICKS,self::META_DAILY_CAP,self::META_FREQUENCY_CAP) as $key ) update_post_meta( $id, $key, max(0,absint($_POST[$key]??0)) );
		$pacing=sanitize_key(wp_unslash($_POST[self::META_PACING]??'even')); update_post_meta($id,self::META_PACING,in_array($pacing,array('even','asap'),true)?$pacing:'even');
		update_post_meta($id,self::META_PRIORITY,max(1,min(10,absint($_POST[self::META_PRIORITY]??5))));
		$device=sanitize_key(wp_unslash($_POST[self::META_DEVICE]??'all')); update_post_meta($id,self::META_DEVICE,in_array($device,array('all','desktop','mobile'),true)?$device:'all');
		$audience=sanitize_key(wp_unslash($_POST[self::META_AUDIENCE]??'all')); update_post_meta($id,self::META_AUDIENCE,in_array($audience,array('all','logged_in','logged_out'),true)?$audience:'all');
		$pts=array_values(array_unique(array_filter(array_map('sanitize_key',(array)wp_unslash($_POST[self::META_POST_TYPES]??array()))))); update_post_meta($id,self::META_POST_TYPES,$pts);
		$raw=sanitize_text_field(wp_unslash($_POST[self::META_CREATORS]??'')); $creators=array_values(array_unique(array_filter(array_map('absint',preg_split('/\s*,\s*/',$raw,-1,PREG_SPLIT_NO_EMPTY))))); update_post_meta($id,self::META_CREATORS,$creators);

		$out=array();
		foreach ( (array) wp_unslash($_POST['nfinite_creatives']??array()) as $row ) {
			if(!is_array($row))continue;
			$key=sanitize_key($row['key']??''); $image=esc_url_raw($row['image']??''); $url=esc_url_raw($row['url']??''); $copy=sanitize_text_field($row['copy']??''); $weight=max(1,min(100,absint($row['weight']??1)));
			if(!$key&&!$image&&!$url&&!$copy)continue;
			if(!$key)$key='creative_'.(count($out)+1);
			$out[]=array('key'=>$key,'image'=>$image,'url'=>$url,'copy'=>$copy,'weight'=>$weight);
		}
		update_post_meta($id,self::META_CREATIVES,$out);
	}

	public static function save_placement( $id ) {
		if ( ! isset($_POST['nfinite_placement_delivery_nonce']) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nfinite_placement_delivery_nonce'])), 'nfinite_placement_delivery_save' ) || ! current_user_can('edit_post',$id) ) return;
		update_post_meta($id,self::PLACEMENT_DAILY_CAP,max(0,absint($_POST[self::PLACEMENT_DAILY_CAP]??0)));
		$format=sanitize_key(wp_unslash($_POST[self::PLACEMENT_FORMAT]??'responsive')); if(!in_array($format,array('responsive','display','native','audio','video'),true))$format='responsive'; update_post_meta($id,self::PLACEMENT_FORMAT,$format);
	}

	public static function session_hash() {
		$session=isset($_COOKIE['nfinite_session'])?sanitize_text_field(wp_unslash($_COOKIE['nfinite_session'])):'';
		if(!$session && is_user_logged_in()) $session=wp_get_session_token();
		if(!$session) $session='anon|'.($_SERVER['REMOTE_ADDR']??'').'|'.($_SERVER['HTTP_USER_AGENT']??'');
		return hash('sha256',$session.'|'.wp_salt('nonce'));
	}

	private static function event_count( $campaign_id, $type='impression', $since='' ) {
		global $wpdb; $table=$wpdb->prefix.'nfinite_campaign_events';
		$sql="SELECT COUNT(*) FROM {$table} WHERE campaign_id=%d AND event_type=%s"; $args=array($campaign_id,$type);
		if($since){$sql.=' AND created_at >= %s';$args[]=$since;}
		return (int)$wpdb->get_var($wpdb->prepare($sql,$args));
	}

	private static function placement_count( $placement_id, $since='' ) {
		global $wpdb; $table=$wpdb->prefix.'nfinite_campaign_events'; $sql="SELECT COUNT(*) FROM {$table} WHERE placement_id=%d AND event_type='impression'"; $args=array($placement_id);
		if($since){$sql.=' AND created_at >= %s';$args[]=$since;}
		return (int)$wpdb->get_var($wpdb->prepare($sql,$args));
	}

	public static function is_campaign_eligible( $campaign, $placement_id=0, $creator_id=0, $content_id=0 ) {
		if(is_numeric($campaign))$campaign=get_post(absint($campaign)); if(!$campaign||Nfinite_Creators_Monetization::CAMPAIGN!==$campaign->post_type)return false;
		$cid=$campaign->ID; $now=current_time('Y-m-d\TH:i');
		if('active'!==get_post_meta($cid,'_nfinite_campaign_status',true))return false;
		$start=get_post_meta($cid,'_nfinite_campaign_start',true); $end=get_post_meta($cid,'_nfinite_campaign_end',true); if($start&&$now<$start)return false; if($end&&$now>$end)return false;
		if($placement_id){$ps=array_map('absint',(array)get_post_meta($cid,'_nfinite_campaign_placements',true));if(!in_array(absint($placement_id),$ps,true))return false;}

		$device=get_post_meta($cid,self::META_DEVICE,true)?:'all'; if('mobile'===$device&&!wp_is_mobile())return false; if('desktop'===$device&&wp_is_mobile())return false;
		$audience=get_post_meta($cid,self::META_AUDIENCE,true)?:'all'; if('logged_in'===$audience&&!is_user_logged_in())return false; if('logged_out'===$audience&&is_user_logged_in())return false;
		$pts=(array)get_post_meta($cid,self::META_POST_TYPES,true); if($pts&&$content_id){$pt=get_post_type($content_id);if($pt&&!in_array($pt,$pts,true))return false;}
		$creators=array_map('absint',(array)get_post_meta($cid,self::META_CREATORS,true)); if($creators&&(! $creator_id || !in_array(absint($creator_id),$creators,true)))return false;

		$day_start=gmdate('Y-m-d 00:00:00',current_time('timestamp',true));
		$daily=max(0,absint(get_post_meta($cid,self::META_DAILY_CAP,true))); if($daily&&self::event_count($cid,'impression',$day_start)>=$daily)return false;
		$goal_i=max(0,absint(get_post_meta($cid,self::META_GOAL_IMPRESSIONS,true))); $delivered_i=self::event_count($cid,'impression'); if($goal_i&&$delivered_i>=$goal_i)return false;
		$goal_c=max(0,absint(get_post_meta($cid,self::META_GOAL_CLICKS,true))); if($goal_c&&self::event_count($cid,'click')>=$goal_c)return false;
		if($placement_id){$pcap=max(0,absint(get_post_meta($placement_id,self::PLACEMENT_DAILY_CAP,true)));if($pcap&&self::placement_count($placement_id,$day_start)>=$pcap)return false;}

		$freq=max(0,absint(get_post_meta($cid,self::META_FREQUENCY_CAP,true))); if($freq){global $wpdb;$table=$wpdb->prefix.'nfinite_campaign_events';$count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE campaign_id=%d AND event_type='impression' AND session_hash=%s AND created_at >= %s",$cid,self::session_hash(),$day_start));if($count>=$freq)return false;}

		if('even'===(get_post_meta($cid,self::META_PACING,true)?:'even')&&$goal_i&&$start&&$end){$s=strtotime($start);$e=strtotime($end);$n=current_time('timestamp');if($e>$s&&$n>$s&&$n<$e){$fraction=max(0,min(1,($n-$s)/($e-$s)));$target=(int)ceil($goal_i*$fraction);if($delivered_i>$target+max(5,(int)ceil($goal_i*0.01)))return false;}}
		return true;
	}

	public static function campaign_score( $campaign_id ) {
		$priority=max(1,min(10,absint(get_post_meta($campaign_id,self::META_PRIORITY,true)?:5))); $goal=max(0,absint(get_post_meta($campaign_id,self::META_GOAL_IMPRESSIONS,true))); if(!$goal)return $priority;
		$delivered=self::event_count($campaign_id,'impression'); $remaining=max(0,$goal-$delivered); return $priority + (($remaining/max(1,$goal))*10);
	}

	public static function choose_campaign( $campaigns, $placement_id=0, $creator_id=0, $content_id=0 ) {
		$eligible=array(); foreach((array)$campaigns as $c){if(self::is_campaign_eligible($c,$placement_id,$creator_id,$content_id))$eligible[]=$c;} if(!$eligible)return null;
		usort($eligible,function($a,$b){$sa=self::campaign_score($a->ID);$sb=self::campaign_score($b->ID);if($sa===$sb)return wp_rand(-1,1);return $sa>$sb?-1:1;}); return $eligible[0];
	}

	public static function choose_creative( $campaign_id ) {
		$rows=array_values(array_filter((array)get_post_meta($campaign_id,self::META_CREATIVES,true),'is_array')); if(!$rows)return array('key'=>'default','image'=>get_post_meta($campaign_id,'_nfinite_campaign_creative_image',true),'url'=>get_post_meta($campaign_id,'_nfinite_campaign_destination',true),'copy'=>get_post_meta($campaign_id,'_nfinite_campaign_creative_copy',true),'weight'=>1);
		$total=0;foreach($rows as $r)$total+=max(1,absint($r['weight']??1));$pick=wp_rand(1,max(1,$total));$cursor=0;foreach($rows as $r){$cursor+=max(1,absint($r['weight']??1));if($pick<=$cursor)return $r;}return $rows[0];
	}

	public static function progress( $campaign_id ) {
		$i=self::event_count($campaign_id,'impression');$c=self::event_count($campaign_id,'click');$gi=max(0,absint(get_post_meta($campaign_id,self::META_GOAL_IMPRESSIONS,true)));$gc=max(0,absint(get_post_meta($campaign_id,self::META_GOAL_CLICKS,true)));
		return array('impressions'=>$i,'clicks'=>$c,'goal_impressions'=>$gi,'goal_clicks'=>$gc,'impression_pct'=>$gi?min(100,($i/$gi)*100):0,'click_pct'=>$gc?min(100,($c/$gc)*100):0);
	}

	public static function pace_label( $campaign_id ) {
		$p=self::progress($campaign_id);$start=get_post_meta($campaign_id,'_nfinite_campaign_start',true);$end=get_post_meta($campaign_id,'_nfinite_campaign_end',true);if(!$p['goal_impressions']||!$start||!$end)return 'No paced goal';$s=strtotime($start);$e=strtotime($end);$n=current_time('timestamp');if($e<=$s)return 'Invalid schedule';$time=max(0,min(1,($n-$s)/($e-$s)));$delivery=$p['impressions']/$p['goal_impressions'];if($delivery+0.05<$time)return 'Behind';if($delivery>$time+0.05)return 'Ahead';return 'On pace';
	}


	public static function render_inventory_report() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$placements=get_posts(array('post_type'=>Nfinite_Creators_Monetization::PLACEMENT,'post_status'=>'publish','numberposts'=>100,'orderby'=>'title','order'=>'ASC'));
		if(!$placements)return;
		$day_start=gmdate('Y-m-d 00:00:00',current_time('timestamp',true));
		echo '<h2>Inventory delivery today</h2><table class="widefat striped"><thead><tr><th>Placement</th><th>Key</th><th>Format</th><th>Impressions</th><th>Daily cap</th><th>Utilization</th></tr></thead><tbody>';
		foreach($placements as $placement){$count=self::placement_count($placement->ID,$day_start);$cap=max(0,absint(get_post_meta($placement->ID,self::PLACEMENT_DAILY_CAP,true)));$util=$cap?min(100,($count/$cap)*100):0;echo '<tr><td><a href="'.esc_url(get_edit_post_link($placement->ID)).'">'.esc_html($placement->post_title).'</a></td><td><code>'.esc_html(get_post_meta($placement->ID,'_nfinite_placement_key',true)).'</code></td><td>'.esc_html(ucfirst(get_post_meta($placement->ID,self::PLACEMENT_FORMAT,true)?:'responsive')).'</td><td>'.number_format($count).'</td><td>'.($cap?number_format($cap):'Unlimited').'</td><td>'.($cap?esc_html(number_format($util,1).'%'):'—').'</td></tr>';}
		echo '</tbody></table>';
	}

	public static function campaign_columns( $columns ) { $columns['nfinite_delivery']='Delivery'; return $columns; }
	public static function campaign_column( $column, $post_id ) { if('nfinite_delivery'!==$column)return;$p=self::progress($post_id);$goal=$p['goal_impressions']?number_format($p['impressions']).' / '.number_format($p['goal_impressions']).' imp':number_format($p['impressions']).' imp';echo esc_html($goal.' · '.self::pace_label($post_id)); }
}
