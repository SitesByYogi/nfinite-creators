<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PairOfDice monetization infrastructure.
 * Campaigns + advertisers + named inventory + first-party delivery analytics + creator allocations.
 */
class Nfinite_Creators_Monetization {
	const DB_VERSION = '1';
	const CAMPAIGN = 'nfinite_campaign';
	const ADVERTISER = 'nfinite_advertiser';
	const PLACEMENT = 'nfinite_placement';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::CAMPAIGN, array( __CLASS__, 'save_campaign' ) );
		add_action( 'save_post_' . self::PLACEMENT, array( __CLASS__, 'save_placement' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 30 );
		add_action( 'wp_ajax_nfinite_monetization_event', array( __CLASS__, 'ajax_event' ) );
		add_action( 'wp_ajax_nopriv_nfinite_monetization_event', array( __CLASS__, 'ajax_event' ) );
		add_shortcode( 'nfinite_placement', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table = self::events_table();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			placement_id bigint(20) unsigned NOT NULL DEFAULT 0,
			creative_key varchar(100) NOT NULL DEFAULT '',
			event_type varchar(20) NOT NULL DEFAULT 'impression',
			content_id bigint(20) unsigned NOT NULL DEFAULT 0,
			creator_id bigint(20) unsigned NOT NULL DEFAULT 0,
			session_hash varchar(64) NOT NULL DEFAULT '',
			device_class varchar(20) NOT NULL DEFAULT '',
			referrer_host varchar(191) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY campaign_event (campaign_id,event_type),
			KEY placement_event (placement_id,event_type),
			KEY creator_event (creator_id,event_type),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );
		update_option( 'nfinite_monetization_db_version', self::DB_VERSION );
		self::register();
	}

	public static function maybe_install() {
		if ( get_option( 'nfinite_monetization_db_version' ) !== self::DB_VERSION && current_user_can( 'manage_options' ) ) { self::install(); }
	}
	private static function events_table() { global $wpdb; return $wpdb->prefix . 'nfinite_campaign_events'; }

	public static function register() {
		$common = array( 'public' => false, 'show_ui' => true, 'show_in_menu' => 'nfinite-monetization', 'supports' => array( 'title' ), 'show_in_rest' => true );
		register_post_type( self::CAMPAIGN, array_merge( $common, array( 'labels' => array( 'name' => __( 'Campaigns', 'nfinite-creators' ), 'singular_name' => __( 'Campaign', 'nfinite-creators' ), 'add_new_item' => __( 'Add Campaign', 'nfinite-creators' ), 'edit_item' => __( 'Edit Campaign', 'nfinite-creators' ) ) ) ) );
		register_post_type( self::ADVERTISER, array_merge( $common, array( 'labels' => array( 'name' => __( 'Advertisers', 'nfinite-creators' ), 'singular_name' => __( 'Advertiser', 'nfinite-creators' ) ) ) ) );
		register_post_type( self::PLACEMENT, array_merge( $common, array( 'labels' => array( 'name' => __( 'Placements', 'nfinite-creators' ), 'singular_name' => __( 'Placement', 'nfinite-creators' ) ) ) ) );
	}

	public static function admin_menu() {
		add_menu_page( __( 'Monetization', 'nfinite-creators' ), __( 'Monetization', 'nfinite-creators' ), 'manage_options', 'nfinite-monetization', array( __CLASS__, 'dashboard' ), 'dashicons-chart-area', 27 );
	}

	public static function meta_boxes() {
		add_meta_box( 'nfinite_campaign_settings', __( 'Campaign Setup', 'nfinite-creators' ), array( __CLASS__, 'campaign_box' ), self::CAMPAIGN, 'normal', 'high' );
		add_meta_box( 'nfinite_campaign_revenue', __( 'Revenue & Creator Share', 'nfinite-creators' ), array( __CLASS__, 'revenue_box' ), self::CAMPAIGN, 'side', 'default' );
		add_meta_box( 'nfinite_placement_settings', __( 'Placement Inventory', 'nfinite-creators' ), array( __CLASS__, 'placement_box' ), self::PLACEMENT, 'normal', 'high' );
	}
	private static function field( $id, $label, $type = 'text', $placeholder = '' ) { $v = get_post_meta( get_the_ID(), $id, true ); echo '<p><label><strong>'.esc_html($label).'</strong></label><br><input style="width:100%" type="'.esc_attr($type).'" name="'.esc_attr($id).'" value="'.esc_attr($v).'" placeholder="'.esc_attr($placeholder).'"></p>'; }

	public static function campaign_box( $post ) {
		wp_nonce_field( 'nfinite_campaign_save', 'nfinite_campaign_nonce' );
		$advertisers = get_posts( array( 'post_type' => self::ADVERTISER, 'numberposts' => -1, 'post_status' => 'publish' ) );
		$placements = get_posts( array( 'post_type' => self::PLACEMENT, 'numberposts' => -1, 'post_status' => 'publish' ) );
		$status = get_post_meta( $post->ID, '_nfinite_campaign_status', true ) ?: 'draft';
		echo '<p><label><strong>Status</strong></label><br><select name="_nfinite_campaign_status">'; foreach ( array('draft','scheduled','active','paused','completed') as $s ) echo '<option '.selected($status,$s,false).' value="'.esc_attr($s).'">'.esc_html(ucwords($s)).'</option>'; echo '</select></p>';
		echo '<p><label><strong>Advertiser / Sponsor</strong></label><br><select name="_nfinite_campaign_advertiser"><option value="0">—</option>'; $cur=absint(get_post_meta($post->ID,'_nfinite_campaign_advertiser',true)); foreach($advertisers as $a) echo '<option '.selected($cur,$a->ID,false).' value="'.absint($a->ID).'">'.esc_html($a->post_title).'</option>'; echo '</select></p>';
		self::field('_nfinite_campaign_start','Start','datetime-local'); self::field('_nfinite_campaign_end','End','datetime-local'); self::field('_nfinite_campaign_destination','Destination URL','url','https://');
		self::field('_nfinite_campaign_creative_image','Creative image URL','url','https://'); self::field('_nfinite_campaign_creative_copy','Creative copy');
		$selected=(array)get_post_meta($post->ID,'_nfinite_campaign_placements',true); echo '<p><strong>Placements</strong><br>'; foreach($placements as $p){ echo '<label style="display:block"><input type="checkbox" name="_nfinite_campaign_placements[]" value="'.absint($p->ID).'" '.checked(in_array((string)$p->ID,array_map('strval',$selected),true),true,false).'> '.esc_html($p->post_title).' <code>'.esc_html(get_post_meta($p->ID,'_nfinite_placement_key',true)).'</code></label>'; } echo '</p>';
	}
	public static function revenue_box() { self::field('_nfinite_campaign_budget','Booked revenue (USD)','number','0.00'); self::field('_nfinite_campaign_creator_pool','Creator pool (USD)','number','0.00'); echo '<p class="description">Creator allocations are finalized from the Monetization dashboard and written to the unified Creator Earnings ledger as sponsorship earnings.</p>'; }
	public static function placement_box( $post ) { wp_nonce_field('nfinite_placement_save','nfinite_placement_nonce'); self::field('_nfinite_placement_key','Placement key','text','rotation_featured'); self::field('_nfinite_placement_description','Description'); echo '<p><code>[nfinite_placement key="'.esc_attr(get_post_meta($post->ID,'_nfinite_placement_key',true)).'"]</code></p>'; }

	public static function save_campaign( $id ) {
		if ( ! isset($_POST['nfinite_campaign_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nfinite_campaign_nonce'])),'nfinite_campaign_save') || ! current_user_can('edit_post',$id) ) return;
		$keys=array('_nfinite_campaign_status','_nfinite_campaign_advertiser','_nfinite_campaign_start','_nfinite_campaign_end','_nfinite_campaign_destination','_nfinite_campaign_creative_image','_nfinite_campaign_creative_copy','_nfinite_campaign_budget','_nfinite_campaign_creator_pool');
		foreach($keys as $k){ if(!isset($_POST[$k])) continue; $v=wp_unslash($_POST[$k]); if(strpos($k,'destination')!==false||strpos($k,'image')!==false)$v=esc_url_raw($v); elseif(strpos($k,'budget')!==false||strpos($k,'pool')!==false)$v=number_format(max(0,(float)$v),2,'.',''); elseif('_nfinite_campaign_advertiser'===$k)$v=absint($v); else $v=sanitize_text_field($v); update_post_meta($id,$k,$v); }
		$placements=isset($_POST['_nfinite_campaign_placements'])?array_values(array_filter(array_map('absint',(array)wp_unslash($_POST['_nfinite_campaign_placements'])))):array(); update_post_meta($id,'_nfinite_campaign_placements',$placements);
	}
	public static function save_placement( $id ) { if(!isset($_POST['nfinite_placement_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nfinite_placement_nonce'])),'nfinite_placement_save')||!current_user_can('edit_post',$id))return; $key=sanitize_key(wp_unslash($_POST['_nfinite_placement_key']??'')); if($key)update_post_meta($id,'_nfinite_placement_key',$key); update_post_meta($id,'_nfinite_placement_description',sanitize_text_field(wp_unslash($_POST['_nfinite_placement_description']??''))); }

	public static function assets() { wp_register_script('nfinite-monetization',NFINITE_CREATORS_URL.'public/js/nfinite-monetization.js',array(),NFINITE_CREATORS_VERSION,true); wp_localize_script('nfinite-monetization','NfiniteMonetization',array('ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('nfinite_monetization_event'))); }
	public static function shortcode( $atts ) { $a=shortcode_atts(array('key'=>'','creator_id'=>0),$atts); return self::render_placement(sanitize_key($a['key']),absint($a['creator_id'])); }
	public static function render_placement( $key, $creator_id=0 ) {
		if(!$key)return '';
		$placements=get_posts(array('post_type'=>self::PLACEMENT,'post_status'=>'publish','numberposts'=>1,'meta_key'=>'_nfinite_placement_key','meta_value'=>$key)); if(!$placements)return '';
		$pid=$placements[0]->ID; $content_id=absint(get_queried_object_id());
		$candidates=get_posts(array('post_type'=>self::CAMPAIGN,'post_status'=>'publish','numberposts'=>100,'meta_key'=>'_nfinite_campaign_status','meta_value'=>'active'));
		if(class_exists('Nfinite_Creators_Ad_Delivery')) $campaign=Nfinite_Creators_Ad_Delivery::choose_campaign($candidates,$pid,absint($creator_id),$content_id); else $campaign=$candidates?reset($candidates):null;
		if(!$campaign)return '';
		$creative=class_exists('Nfinite_Creators_Ad_Delivery')?Nfinite_Creators_Ad_Delivery::choose_creative($campaign->ID):array('key'=>'default','url'=>get_post_meta($campaign->ID,'_nfinite_campaign_destination',true),'image'=>get_post_meta($campaign->ID,'_nfinite_campaign_creative_image',true),'copy'=>get_post_meta($campaign->ID,'_nfinite_campaign_creative_copy',true));
		$url=isset($creative['url'])&&$creative['url']?$creative['url']:get_post_meta($campaign->ID,'_nfinite_campaign_destination',true); $img=$creative['image']??''; $copy=$creative['copy']??''; $ck=sanitize_key($creative['key']??'default');
		wp_enqueue_script('nfinite-monetization');
		$out='<aside class="nfinite-monetization-placement" data-nfinite-campaign="'.absint($campaign->ID).'" data-nfinite-placement="'.absint($pid).'" data-nfinite-creator="'.absint($creator_id).'" data-nfinite-content="'.$content_id.'" data-nfinite-creative="'.esc_attr($ck).'">'; if($url)$out.='<a class="nfinite-campaign-link" href="'.esc_url($url).'" target="_blank" rel="sponsored noopener">'; if($img)$out.='<img src="'.esc_url($img).'" alt="">'; if($copy)$out.='<span>'.esc_html($copy).'</span>'; if($url)$out.='</a>'; return $out.'</aside>';
	}

	public static function ajax_event() {
		check_ajax_referer('nfinite_monetization_event','nonce'); $type=sanitize_key(wp_unslash($_POST['event_type']??'')); if(!in_array($type,array('impression','click'),true))wp_send_json_error();
		$cid=absint($_POST['campaign_id']??0); $pid=absint($_POST['placement_id']??0); if(self::CAMPAIGN!==get_post_type($cid)||self::PLACEMENT!==get_post_type($pid))wp_send_json_error();
		$hash=class_exists('Nfinite_Creators_Ad_Delivery')?Nfinite_Creators_Ad_Delivery::session_hash():hash('sha256',wp_get_session_token().'|'.($_SERVER['REMOTE_ADDR']??'').'|'.wp_salt('nonce'));
		$creative=sanitize_key(wp_unslash($_POST['creative_key']??'default'))?:'default';
		global $wpdb;
		if('impression'===$type){$recent=gmdate('Y-m-d H:i:s',time()-1800);$dup=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".self::events_table()." WHERE campaign_id=%d AND placement_id=%d AND event_type='impression' AND session_hash=%s AND creative_key=%s AND created_at >= %s",$cid,$pid,$hash,$creative,$recent));if($dup)wp_send_json_success(array('deduped'=>true));}
		$wpdb->insert(self::events_table(),array('campaign_id'=>$cid,'placement_id'=>$pid,'creative_key'=>$creative,'event_type'=>$type,'content_id'=>absint($_POST['content_id']??0),'creator_id'=>absint($_POST['creator_id']??0),'session_hash'=>$hash,'device_class'=>wp_is_mobile()?'mobile':'desktop','referrer_host'=>sanitize_text_field(wp_parse_url(wp_get_referer(),PHP_URL_HOST)?:''),'created_at'=>current_time('mysql',true)));
		wp_send_json_success();
	}

	private static function money_minor( $value ) { return (int) round( max(0,(float)$value) * 100 ); }
	public static function dashboard() {
		if(!current_user_can('manage_options'))return; global $wpdb; $table=self::events_table();
		if(isset($_POST['nfinite_allocate_campaign'])&&check_admin_referer('nfinite_allocate_campaign')) self::allocate(absint($_POST['campaign_id']??0));
		$active=count(get_posts(array('post_type'=>self::CAMPAIGN,'post_status'=>'publish','numberposts'=>-1,'meta_key'=>'_nfinite_campaign_status','meta_value'=>'active','fields'=>'ids'))); $campaigns=get_posts(array('post_type'=>self::CAMPAIGN,'post_status'=>'publish','numberposts'=>100));
		$booked=0;$pools=0;foreach($campaigns as $c){$booked+=self::money_minor(get_post_meta($c->ID,'_nfinite_campaign_budget',true));$pools+=self::money_minor(get_post_meta($c->ID,'_nfinite_campaign_creator_pool',true));}
		$impressions=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE event_type='impression'"); $clicks=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE event_type='click'");
		echo '<div class="wrap"><h1>Nfinite Monetization</h1><p>Direct campaign revenue, inventory and first-party delivery reporting for PairOfDice.</p><div style="display:flex;gap:12px;flex-wrap:wrap">'; foreach(array('Active campaigns'=>$active,'Booked revenue'=>'$'.number_format($booked/100,2),'Creator pools'=>'$'.number_format($pools/100,2),'Impressions'=>number_format($impressions),'Clicks'=>number_format($clicks),'CTR'=>$impressions?number_format(($clicks/$impressions)*100,2).'%':'0.00%') as $l=>$v)echo '<div style="background:#fff;border:1px solid #ddd;padding:16px;min-width:150px"><strong>'.esc_html($l).'</strong><br><span style="font-size:22px">'.esc_html($v).'</span></div>'; echo '</div><h2>Campaign reporting</h2><table class="widefat striped"><thead><tr><th>Campaign</th><th>Status</th><th>Revenue</th><th>Creator pool</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Goal / Pace</th><th>Allocation</th></tr></thead><tbody>';
		foreach($campaigns as $c){$i=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE campaign_id=%d AND event_type='impression'",$c->ID));$cl=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE campaign_id=%d AND event_type='click'",$c->ID));$allocated=get_post_meta($c->ID,'_nfinite_campaign_allocated_at',true);echo '<tr><td><a href="'.esc_url(get_edit_post_link($c->ID)).'">'.esc_html($c->post_title).'</a></td><td>'.esc_html(get_post_meta($c->ID,'_nfinite_campaign_status',true)).'</td><td>$'.esc_html(number_format((float)get_post_meta($c->ID,'_nfinite_campaign_budget',true),2)).'</td><td>$'.esc_html(number_format((float)get_post_meta($c->ID,'_nfinite_campaign_creator_pool',true),2)).'</td><td>'.number_format($i).'</td><td>'.number_format($cl).'</td><td>'.($i?number_format(($cl/$i)*100,2):'0.00').'%</td><td>'; if(class_exists('Nfinite_Creators_Ad_Delivery')){$pr=Nfinite_Creators_Ad_Delivery::progress($c->ID);echo esc_html(($pr['goal_impressions']?number_format($pr['impressions']).' / '.number_format($pr['goal_impressions']):number_format($pr['impressions'])).' impressions · '.Nfinite_Creators_Ad_Delivery::pace_label($c->ID));}else echo '—'; echo '</td><td>'; if($allocated)echo 'Finalized '.esc_html($allocated); else {echo '<form method="post">';wp_nonce_field('nfinite_allocate_campaign');echo '<input type="hidden" name="campaign_id" value="'.absint($c->ID).'"><button class="button" name="nfinite_allocate_campaign" value="1">Finalize creator pool</button></form>';} echo '</td></tr>';}
		echo '</tbody></table>'; if(class_exists('Nfinite_Creators_Ad_Delivery'))Nfinite_Creators_Ad_Delivery::render_inventory_report(); echo '<p><a class="button button-primary" href="'.esc_url(admin_url('post-new.php?post_type='.self::CAMPAIGN)).'">Add Campaign</a> <a class="button" href="'.esc_url(admin_url('edit.php?post_type='.self::PLACEMENT)).'">Manage Placements</a> <a class="button" href="'.esc_url(admin_url('edit.php?post_type='.self::ADVERTISER)).'">Advertisers</a></p></div>';
	}

	private static function allocate( $campaign_id ) {
		if(self::CAMPAIGN!==get_post_type($campaign_id)||get_post_meta($campaign_id,'_nfinite_campaign_allocated_at',true))return; $pool=self::money_minor(get_post_meta($campaign_id,'_nfinite_campaign_creator_pool',true)); if($pool<1)return;
		global $wpdb; $rows=$wpdb->get_results($wpdb->prepare("SELECT creator_id,COUNT(*) weight FROM ".self::events_table()." WHERE campaign_id=%d AND event_type='impression' AND creator_id>0 GROUP BY creator_id",$campaign_id),ARRAY_A); $total=array_sum(array_map(function($r){return(int)$r['weight'];},$rows)); if(!$total)return;
		$remaining=$pool;$last=count($rows)-1; foreach($rows as $idx=>$r){$amount=$idx===$last?$remaining:(int)floor($pool*((int)$r['weight']/$total));$remaining-=$amount;if($amount<1)continue;Nfinite_Creators_Payments::record_earning(array('entry_key'=>'sponsorship:campaign:'.$campaign_id.':creator:'.absint($r['creator_id']),'creator_id'=>absint($r['creator_id']),'source_type'=>'sponsorship','source_id'=>(string)$campaign_id,'source_label'=>get_the_title($campaign_id),'earning_kind'=>'sponsorship','accounting_period'=>gmdate('Y-m'),'eligible_amount'=>$amount,'creator_amount'=>$amount,'status'=>'finalized','earning_status'=>'finalized','is_provisional'=>0,'finalized_at'=>current_time('mysql',true)));}
		update_post_meta($campaign_id,'_nfinite_campaign_allocated_at',current_time('mysql',true));
	}
}
