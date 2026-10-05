<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Launch hardening: abuse controls, security headers and integrity diagnostics.
 * Intentionally avoids changing creator workflows or financial calculations.
 */
class Nfinite_Creators_Hardening {
	const OPTION = 'nfinite_creators_hardening_settings';
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 40 );
		add_action( 'admin_post_nfinite_hardening_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_nfinite_hardening_audit', array( __CLASS__, 'run_audit_action' ) );
		add_action( 'send_headers', array( __CLASS__, 'security_headers' ) );
		foreach ( array( 'nfinite_analytics_event'=>240, 'nfinite_engagement_event'=>180, 'nfinite_video_engagement_event'=>180, 'nfinite_monetization_event'=>180 ) as $action => $limit ) {
			add_action( 'wp_ajax_' . $action, function() use ( $action, $limit ) { self::rate_limit( $action, $limit ); }, 0 );
			add_action( 'wp_ajax_nopriv_' . $action, function() use ( $action, $limit ) { self::rate_limit( $action, $limit ); }, 0 );
		}
	}
	public static function settings() {
		$s = get_option( self::OPTION, array() );
		return wp_parse_args( is_array($s)?$s:array(), array('rate_limits'=>1,'security_headers'=>1) );
	}
	private static function request_fingerprint() {
		$ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
		$ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])),0,160) : '';
		return hash_hmac('sha256',$ip.'|'.$ua,wp_salt('auth'));
	}
	public static function rate_limit( $action, $limit ) {
		if ( empty(self::settings()['rate_limits']) ) return;
		$key = 'nfinite_rl_' . substr(hash('sha256',$action.'|'.self::request_fingerprint()),0,32);
		$data = get_transient($key); $now=time();
		if(!is_array($data)||empty($data['start'])||($now-(int)$data['start'])>=MINUTE_IN_SECONDS){$data=array('start'=>$now,'count'=>0);}
		$data['count']=(int)$data['count']+1; set_transient($key,$data,2*MINUTE_IN_SECONDS);
		if($data['count']>$limit){ status_header(429); header('Retry-After: 60'); wp_send_json_error(array('message'=>__('Too many requests. Please try again shortly.','nfinite-creators')),429); }
	}
	public static function security_headers() {
		if ( empty(self::settings()['security_headers']) || headers_sent() ) return;
		header('X-Content-Type-Options: nosniff');
		header('Referrer-Policy: strict-origin-when-cross-origin');
		header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
	}
	public static function admin_menu(){ add_submenu_page('edit.php?post_type=nfinite_creator',__('System Health','nfinite-creators'),__('System Health','nfinite-creators'),'manage_options','nfinite-system-health',array(__CLASS__,'page')); }
	private static function table_exists($table){ global $wpdb; return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table; }
	public static function audit(){
		global $wpdb; $checks=array();
		$tables=array(
			'Creator Earnings'=>class_exists('Nfinite_Creators_Payments')?Nfinite_Creators_Payments::table_name():$wpdb->prefix.'nfinite_creator_transfers',
			'Creator Analytics'=>class_exists('Nfinite_Creators_Analytics')?Nfinite_Creators_Analytics::table_name():$wpdb->prefix.'nfinite_creator_analytics',
			'Audio Engagement'=>class_exists('Nfinite_Creators_Engagement')?Nfinite_Creators_Engagement::table_name():$wpdb->prefix.'nfinite_engagement_audio',
			'Video Engagement'=>class_exists('Nfinite_Creators_Video_Engagement')?Nfinite_Creators_Video_Engagement::table_name():$wpdb->prefix.'nfinite_engagement_video',
			'Notifications'=>class_exists('Nfinite_Creators_Notifications')?Nfinite_Creators_Notifications::table():$wpdb->prefix.'nfinite_notifications',
			'Financial Statements'=>class_exists('Nfinite_Creators_Financial_Admin')?Nfinite_Creators_Financial_Admin::table_name():$wpdb->prefix.'nfinite_creator_statements',
			'Streaming Periods'=>$wpdb->prefix.'nfinite_streaming_periods','Streaming Allocations'=>$wpdb->prefix.'nfinite_streaming_allocations','Streaming Reserve'=>$wpdb->prefix.'nfinite_streaming_reserve',
			'Video Periods'=>$wpdb->prefix.'nfinite_video_periods','Video Allocations'=>$wpdb->prefix.'nfinite_video_allocations','Video Reserve'=>$wpdb->prefix.'nfinite_video_reserve','Campaign Events'=>$wpdb->prefix.'nfinite_campaign_events'
		);
		foreach($tables as $label=>$table){$checks[]=array('label'=>$label.' table','ok'=>self::table_exists($table),'detail'=>$table);}
		$creator_count=(int)wp_count_posts('nfinite_creator')->publish; $checks[]=array('label'=>'Published creators','ok'=>true,'detail'=>(string)$creator_count);
		if(self::table_exists($tables['Creator Earnings'])){
			$t=$tables['Creator Earnings']; $orphan=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t} l LEFT JOIN {$wpdb->posts} p ON p.ID=l.creator_id AND p.post_type='nfinite_creator' WHERE l.creator_id>0 AND p.ID IS NULL");
			$dupes=(int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT entry_key FROM {$t} WHERE entry_key<>'' GROUP BY entry_key HAVING COUNT(*)>1) d");
			$checks[]=array('label'=>'Earnings creator references','ok'=>$orphan===0,'detail'=>$orphan.' orphaned ledger row(s)');
			$checks[]=array('label'=>'Earnings entry keys','ok'=>$dupes===0,'detail'=>$dupes.' duplicate key(s)');
		}
		$owned=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='nfinite_creator' AND post_status NOT IN ('trash','auto-draft') AND post_author=0");
		$checks[]=array('label'=>'Creator ownership','ok'=>$owned===0,'detail'=>$owned.' creator profile(s) without a WordPress owner');
		return $checks;
	}
	public static function save(){ if(!current_user_can('manage_options'))wp_die(esc_html__('Forbidden','nfinite-creators')); check_admin_referer('nfinite_hardening_save'); update_option(self::OPTION,array('rate_limits'=>empty($_POST['rate_limits'])?0:1,'security_headers'=>empty($_POST['security_headers'])?0:1),false); wp_safe_redirect(admin_url('edit.php?post_type=nfinite_creator&page=nfinite-system-health&updated=1')); exit; }
	public static function run_audit_action(){ if(!current_user_can('manage_options'))wp_die(esc_html__('Forbidden','nfinite-creators')); check_admin_referer('nfinite_hardening_audit'); set_transient('nfinite_last_integrity_audit',array('time'=>time(),'checks'=>self::audit()),DAY_IN_SECONDS); wp_safe_redirect(admin_url('edit.php?post_type=nfinite_creator&page=nfinite-system-health&audit=1')); exit; }
	public static function page(){
		if(!current_user_can('manage_options'))return; $s=self::settings(); $saved=get_transient('nfinite_last_integrity_audit'); $checks=is_array($saved)&&!empty($saved['checks'])?$saved['checks']:self::audit();
		echo '<div class="wrap"><h1>'.esc_html__('Nfinite System Health','nfinite-creators').'</h1><p>'.esc_html__('Launch-readiness checks for security controls, required data tables, creator ownership, and earnings-ledger integrity. This audit is read-only.','nfinite-creators').'</p>';
		echo '<h2>'.esc_html__('Hardening Controls','nfinite-creators').'</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="nfinite_hardening_save">';wp_nonce_field('nfinite_hardening_save');echo '<label><input type="checkbox" name="rate_limits" value="1" '.checked(!empty($s['rate_limits']),true,false).'> '.esc_html__('Rate-limit public analytics, engagement, and campaign event endpoints','nfinite-creators').'</label><br><label><input type="checkbox" name="security_headers" value="1" '.checked(!empty($s['security_headers']),true,false).'> '.esc_html__('Send safe baseline security/privacy response headers','nfinite-creators').'</label><p><button class="button button-primary">'.esc_html__('Save Hardening Settings','nfinite-creators').'</button></p></form>';
		echo '<h2>'.esc_html__('Data Integrity Audit','nfinite-creators').'</h2><table class="widefat striped"><thead><tr><th>'.esc_html__('Check','nfinite-creators').'</th><th>'.esc_html__('Status','nfinite-creators').'</th><th>'.esc_html__('Detail','nfinite-creators').'</th></tr></thead><tbody>';foreach($checks as$c){echo '<tr><td>'.esc_html($c['label']).'</td><td><strong>'.($c['ok']?esc_html__('PASS','nfinite-creators'):esc_html__('REVIEW','nfinite-creators')).'</strong></td><td>'.esc_html($c['detail']).'</td></tr>';}echo '</tbody></table><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin-top:16px"><input type="hidden" name="action" value="nfinite_hardening_audit">';wp_nonce_field('nfinite_hardening_audit');echo '<button class="button">'.esc_html__('Run Audit Again','nfinite-creators').'</button></form>';
		echo '<h2>'.esc_html__('Production Safeguards','nfinite-creators').'</h2><ul><li>'.esc_html__('Financial and payout admin actions remain restricted to administrators and nonce-protected.','nfinite-creators').'</li><li>'.esc_html__('Public engagement endpoints retain same-origin/media validation and now receive abuse throttling.','nfinite-creators').'</li><li>'.esc_html__('The integrity audit never edits or deletes financial, engagement, or creator data.','nfinite-creators').'</li></ul></div>';
	}
}
