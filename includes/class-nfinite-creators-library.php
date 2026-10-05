<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Nfinite_Creators_Library {
	const FOLLOW_META = '_nfinite_following';
	const SAVE_META = '_nfinite_saved_library';
	public static function init(){
		add_action('wp_ajax_nfinite_library_toggle',array(__CLASS__,'ajax_toggle'));
		add_shortcode('nfinite_library',array(__CLASS__,'library_shortcode'));
		add_shortcode('nfinite_follow_button',array(__CLASS__,'follow_shortcode'));
		add_shortcode('nfinite_save_button',array(__CLASS__,'save_shortcode'));
		add_filter('the_content',array(__CLASS__,'append_actions'),35);
	}
	public static function allowed_save_types(){ return array('nfinite_release','nfinite_track','nfinite_show','nfinite_episode','nfinite_video','nfinite_event','nfinite_creator_post','nfinite_playlist','post'); }
	private static function get_ids($user_id,$meta){ $v=get_user_meta($user_id,$meta,true); return is_array($v)?array_values(array_unique(array_map('absint',$v))):array(); }
	private static function set_ids($user_id,$meta,$ids){ update_user_meta($user_id,$meta,array_values(array_unique(array_filter(array_map('absint',$ids))))); }
	public static function is_following($id,$user_id=0){$user_id=$user_id?:get_current_user_id();return $user_id&&in_array(absint($id),self::get_ids($user_id,self::FOLLOW_META),true);}
	public static function is_saved($id,$user_id=0){$user_id=$user_id?:get_current_user_id();return $user_id&&in_array(absint($id),self::get_ids($user_id,self::SAVE_META),true);}
	public static function button($id,$mode='save'){
		$id=absint($id); if(!$id)return '';
		$follow='follow'===$mode; $active=$follow?self::is_following($id):self::is_saved($id);
		$label=$follow?($active?__('Following','nfinite-creators'):__('Follow','nfinite-creators')):($active?__('Saved','nfinite-creators'):__('Save','nfinite-creators'));
		return '<button type="button" class="nfinite-library-action'.($active?' is-active':'').'" data-nfinite-library-action="'.esc_attr($mode).'" data-object-id="'.$id.'" aria-pressed="'.($active?'true':'false').'"><span aria-hidden="true">'.($follow?'＋':'♡').'</span><span data-label>'.esc_html($label).'</span></button>';
	}
	public static function follow_shortcode($atts){$a=shortcode_atts(array('id'=>get_the_ID()),$atts);return self::button($a['id'],'follow');}
	public static function save_shortcode($atts){$a=shortcode_atts(array('id'=>get_the_ID()),$atts);return self::button($a['id'],'save');}
	public static function append_actions($content){
		if(!is_singular()||!in_the_loop()||!is_main_query())return $content; $id=get_the_ID(); $type=get_post_type($id);
		if('nfinite_creator'===$type||'nfinite_org'===$type) return $content.'<div class="nfinite-library-inline">'.self::button($id,'follow').'</div>';
		if(in_array($type,self::allowed_save_types(),true)) return $content.'<div class="nfinite-library-inline">'.self::button($id,'save').'</div>';
		return $content;
	}
	public static function ajax_toggle(){
		check_ajax_referer('nfinite_library','nonce'); if(!is_user_logged_in())wp_send_json_error(array('message'=>__('Log in to use your library.','nfinite-creators')),401);
		$id=absint($_POST['object_id']??0); $mode=sanitize_key($_POST['mode']??''); $type=get_post_type($id);
		if(!$id||!$type)wp_send_json_error(array('message'=>__('Invalid item.','nfinite-creators')),400);
		if('follow'===$mode){ if(!in_array($type,array('nfinite_creator','nfinite_org'),true))wp_send_json_error(array('message'=>__('This item cannot be followed.','nfinite-creators')),400); $meta=self::FOLLOW_META; }
		elseif('save'===$mode){ if(!in_array($type,self::allowed_save_types(),true))wp_send_json_error(array('message'=>__('This item cannot be saved.','nfinite-creators')),400); $meta=self::SAVE_META; }
		else wp_send_json_error(array('message'=>__('Invalid action.','nfinite-creators')),400);
		$uid=get_current_user_id(); $ids=self::get_ids($uid,$meta); $active=in_array($id,$ids,true);
		if($active)$ids=array_values(array_diff($ids,array($id))); else $ids[]=$id; self::set_ids($uid,$meta,$ids); $active=!$active;
		if('follow'===$mode && $active && class_exists('Nfinite_Creators_Notifications')) { Nfinite_Creators_Notifications::notify_new_follow($uid,$id); }
		wp_send_json_success(array('active'=>$active,'label'=>'follow'===$mode?($active?__('Following','nfinite-creators'):__('Follow','nfinite-creators')):($active?__('Saved','nfinite-creators'):__('Save','nfinite-creators'))));
	}
	private static function cards($ids,$empty){ if(!$ids)return '<p>'.esc_html($empty).'</p>'; $posts=get_posts(array('post_type'=>'any','post__in'=>$ids,'orderby'=>'post__in','posts_per_page'=>-1,'post_status'=>'publish')); if(!$posts)return '<p>'.esc_html($empty).'</p>'; ob_start(); echo '<div class="nfinite-library-grid">'; foreach($posts as $p){echo '<article class="nfinite-library-card">'; if(has_post_thumbnail($p))echo '<a href="'.esc_url(get_permalink($p)).'">'.get_the_post_thumbnail($p,'medium').'</a>'; echo '<div><small>'.esc_html(get_post_type_object($p->post_type)->labels->singular_name??$p->post_type).'</small><h3><a href="'.esc_url(get_permalink($p)).'">'.esc_html($p->post_title).'</a></h3>'.self::button($p->ID,in_array($p->post_type,array('nfinite_creator','nfinite_org'),true)?'follow':'save').'</div></article>'; } echo '</div>'; return ob_get_clean(); }
	public static function library_shortcode(){
		if(!is_user_logged_in())return '<section class="nfinite-personal-library"><h2>'.esc_html__('My Library','nfinite-creators').'</h2><p>'.esc_html__('Log in to follow creators and save music, shows, videos, and more.','nfinite-creators').'</p></section>';
		$uid=get_current_user_id(); $follow=self::get_ids($uid,self::FOLLOW_META); $saved=self::get_ids($uid,self::SAVE_META);
		ob_start(); echo '<section class="nfinite-personal-library"><header><span class="nfinite-eyebrow">'.esc_html__('PairOfDice Library','nfinite-creators').'</span><h2>'.esc_html__('My Library','nfinite-creators').'</h2><p>'.esc_html__('Your followed creators and saved content in one place.','nfinite-creators').'</p></header><nav class="nfinite-library-summary"><span><strong>'.count($follow).'</strong> '.esc_html__('Following','nfinite-creators').'</span><span><strong>'.count($saved).'</strong> '.esc_html__('Saved','nfinite-creators').'</span></nav><h3>'.esc_html__('Following','nfinite-creators').'</h3>'.self::cards($follow,__('You are not following anyone yet.','nfinite-creators')).'<h3>'.esc_html__('Saved','nfinite-creators').'</h3>'.self::cards($saved,__('You have not saved any content yet.','nfinite-creators')).'</section>'; return ob_get_clean();
	}
}
