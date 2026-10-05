<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Creator Memberships V1.
 * Creator-owned tiers, WooCommerce/Subscriptions checkout integration, entitlements,
 * and reusable content access rules including timed early access.
 */
class Nfinite_Creators_Memberships {
	const TIER = 'nfinite_member_tier';
	const META_ACCESS = '_nfinite_membership_access';
	const META_TIERS = '_nfinite_membership_tiers';
	const META_PUBLIC_AT = '_nfinite_membership_public_at';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_access' ), 20, 2 );
		add_action( 'save_post_' . self::TIER, array( __CLASS__, 'save_tier' ), 20, 2 );
		add_filter( 'the_content', array( __CLASS__, 'gate_content' ), 6 );
		add_shortcode( 'nfinite_memberships', array( __CLASS__, 'shortcode' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'order_paid' ), 55 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'order_paid' ), 55 );
		add_action( 'woocommerce_subscription_status_active', array( __CLASS__, 'subscription_active' ) );
		add_action( 'woocommerce_subscription_status_cancelled', array( __CLASS__, 'subscription_inactive' ) );
		add_action( 'woocommerce_subscription_status_expired', array( __CLASS__, 'subscription_inactive' ) );
		add_action( 'woocommerce_subscription_status_on-hold', array( __CLASS__, 'subscription_inactive' ) );
	}

	public static function register() {
		register_post_type( self::TIER, array(
			'labels' => array( 'name' => __( 'Membership Tiers', 'nfinite-creators' ), 'singular_name' => __( 'Membership Tier', 'nfinite-creators' ), 'add_new_item' => __( 'Add Membership Tier', 'nfinite-creators' ), 'edit_item' => __( 'Edit Membership Tier', 'nfinite-creators' ) ),
			'public' => false, 'show_ui' => true, 'show_in_menu' => 'edit.php?post_type=nfinite_creator', 'show_in_rest' => true,
			'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
		) );
	}

	public static function meta_boxes() {
		add_meta_box( 'nfinite_membership_tier', __( 'Membership Tier Settings', 'nfinite-creators' ), array( __CLASS__, 'tier_box' ), self::TIER, 'normal', 'high' );
		foreach ( array( 'nfinite_track', 'nfinite_release', 'nfinite_video', 'nfinite_episode', 'nfinite_show', 'nfinite_creator_post', 'post', 'nfinite_event' ) as $type ) {
			if ( post_type_exists( $type ) ) { add_meta_box( 'nfinite_membership_access', __( 'Nfinite Membership Access', 'nfinite-creators' ), array( __CLASS__, 'access_box' ), $type, 'side', 'default' ); }
		}
	}

	public static function creators() { return get_posts( array( 'post_type'=>'nfinite_creator','post_status'=>'publish','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC' ) ); }
	public static function tiers( $creator_id = 0 ) {
		$args = array( 'post_type'=>self::TIER,'post_status'=>'publish','posts_per_page'=>-1,'orderby'=>'menu_order title','order'=>'ASC' );
		if ( $creator_id ) { $args['meta_key'] = '_nfinite_membership_creator_id'; $args['meta_value'] = absint( $creator_id ); }
		return get_posts( $args );
	}

	public static function tier_box( $post ) {
		wp_nonce_field( 'nfinite_membership_tier_' . $post->ID, 'nfinite_membership_tier_nonce' );
		$creator = absint( get_post_meta( $post->ID, '_nfinite_membership_creator_id', true ) );
		$price = get_post_meta( $post->ID, '_nfinite_membership_price', true );
		$product = absint( get_post_meta( $post->ID, '_nfinite_membership_product_id', true ) );
		$benefits = get_post_meta( $post->ID, '_nfinite_membership_benefits', true );
		?><p><label><strong><?php esc_html_e('Creator','nfinite-creators'); ?></strong></label><select class="widefat" name="nfinite_membership_creator_id"><option value="0">—</option><?php foreach(self::creators() as $c): ?><option value="<?php echo esc_attr($c->ID); ?>" <?php selected($creator,$c->ID); ?>><?php echo esc_html(get_the_title($c)); ?></option><?php endforeach; ?></select></p>
		<p><label><strong><?php esc_html_e('Monthly price','nfinite-creators'); ?></strong></label><input class="widefat" type="number" min="0" step="0.01" name="nfinite_membership_price" value="<?php echo esc_attr($price); ?>"></p>
		<p><label><strong><?php esc_html_e('Benefits (one per line)','nfinite-creators'); ?></strong></label><textarea class="widefat" rows="6" name="nfinite_membership_benefits"><?php echo esc_textarea($benefits); ?></textarea></p>
		<p><label><strong><?php esc_html_e('WooCommerce product ID','nfinite-creators'); ?></strong></label><input class="widefat" type="number" min="0" name="nfinite_membership_product_id" value="<?php echo esc_attr($product); ?>"><small><?php esc_html_e('Use a WooCommerce Subscription product for automatic recurring access. Standard paid orders grant access without automatic renewal/expiry.', 'nfinite-creators'); ?></small></p><?php
	}

	public static function save_tier( $post_id, $post ) {
		if ( ! isset($_POST['nfinite_membership_tier_nonce']) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nfinite_membership_tier_nonce'])), 'nfinite_membership_tier_'.$post_id ) || ! current_user_can('edit_post',$post_id) ) return;
		update_post_meta($post_id,'_nfinite_membership_creator_id',isset($_POST['nfinite_membership_creator_id'])?absint($_POST['nfinite_membership_creator_id']):0);
		update_post_meta($post_id,'_nfinite_membership_price',isset($_POST['nfinite_membership_price'])?wc_format_decimal(wp_unslash($_POST['nfinite_membership_price'])):'');
		update_post_meta($post_id,'_nfinite_membership_product_id',isset($_POST['nfinite_membership_product_id'])?absint($_POST['nfinite_membership_product_id']):0);
		update_post_meta($post_id,'_nfinite_membership_benefits',isset($_POST['nfinite_membership_benefits'])?sanitize_textarea_field(wp_unslash($_POST['nfinite_membership_benefits'])):'');
	}

	public static function access_box( $post ) {
		wp_nonce_field('nfinite_membership_access_'.$post->ID,'nfinite_membership_access_nonce');
		$access = get_post_meta($post->ID,self::META_ACCESS,true) ?: 'public';
		$selected = array_map('absint',(array)get_post_meta($post->ID,self::META_TIERS,true));
		$public_at = get_post_meta($post->ID,self::META_PUBLIC_AT,true);
		?><p><select class="widefat" name="nfinite_membership_access"><option value="public" <?php selected($access,'public'); ?>><?php esc_html_e('Public','nfinite-creators'); ?></option><option value="members" <?php selected($access,'members'); ?>><?php esc_html_e('Any member','nfinite-creators'); ?></option><option value="tiers" <?php selected($access,'tiers'); ?>><?php esc_html_e('Specific tiers','nfinite-creators'); ?></option></select></p>
		<p><strong><?php esc_html_e('Allowed tiers','nfinite-creators'); ?></strong></p><?php foreach(self::tiers() as $tier): ?><label style="display:block;margin:4px 0"><input type="checkbox" name="nfinite_membership_tiers[]" value="<?php echo esc_attr($tier->ID); ?>" <?php checked(in_array($tier->ID,$selected,true)); ?>> <?php echo esc_html(get_the_title($tier)); ?></label><?php endforeach; ?>
		<p><label><strong><?php esc_html_e('Become public at','nfinite-creators'); ?></strong></label><input class="widefat" type="datetime-local" name="nfinite_membership_public_at" value="<?php echo esc_attr($public_at); ?>"><small><?php esc_html_e('Optional early-access cutoff. After this time the content becomes public automatically.','nfinite-creators'); ?></small></p><?php
	}

	public static function save_access( $post_id, $post ) {
		if ( self::TIER === $post->post_type || ! isset($_POST['nfinite_membership_access_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nfinite_membership_access_nonce'])),'nfinite_membership_access_'.$post_id) || ! current_user_can('edit_post',$post_id) ) return;
		$access = isset($_POST['nfinite_membership_access']) ? sanitize_key($_POST['nfinite_membership_access']) : 'public';
		if(!in_array($access,array('public','members','tiers'),true)) $access='public';
		update_post_meta($post_id,self::META_ACCESS,$access);
		update_post_meta($post_id,self::META_TIERS,isset($_POST['nfinite_membership_tiers'])?array_map('absint',(array)$_POST['nfinite_membership_tiers']):array());
		update_post_meta($post_id,self::META_PUBLIC_AT,isset($_POST['nfinite_membership_public_at'])?sanitize_text_field(wp_unslash($_POST['nfinite_membership_public_at'])):'');
	}

	public static function user_tiers( $user_id ) { return array_values(array_unique(array_filter(array_map('absint',(array)get_user_meta($user_id,'_nfinite_membership_tiers',true))))); }
	public static function grant( $user_id, $tier_id ) { $t=self::user_tiers($user_id); if(!in_array(absint($tier_id),$t,true)){$t[]=absint($tier_id);update_user_meta($user_id,'_nfinite_membership_tiers',$t);} }
	public static function revoke( $user_id, $tier_id ) { update_user_meta($user_id,'_nfinite_membership_tiers',array_values(array_diff(self::user_tiers($user_id),array(absint($tier_id))))); }

	public static function can_access( $post_id, $user_id = 0 ) {
		$access = get_post_meta($post_id,self::META_ACCESS,true) ?: 'public';
		$public_at = get_post_meta($post_id,self::META_PUBLIC_AT,true);
		if($public_at && strtotime($public_at) && current_time('timestamp') >= strtotime($public_at)) return true;
		if('public'===$access) return true;
		$user_id = $user_id ?: get_current_user_id(); if(!$user_id) return false;
		$owned=self::user_tiers($user_id); if(!$owned) return false;
		if('members'===$access) return true;
		$allowed=array_map('absint',(array)get_post_meta($post_id,self::META_TIERS,true)); return (bool)array_intersect($owned,$allowed);
	}

	public static function gate_content( $content ) {
		if(is_admin() || !is_singular()) return $content; $id=get_the_ID(); if(!$id || self::can_access($id)) return $content;
		$tiers=array_map('absint',(array)get_post_meta($id,self::META_TIERS,true)); $creator=0;
		foreach($tiers as $tid){$creator=absint(get_post_meta($tid,'_nfinite_membership_creator_id',true));if($creator)break;}
		$cta=$creator?do_shortcode('[nfinite_memberships creator_id="'.$creator.'"]'):'';
		return '<div class="nfinite-membership-lock"><h3>'.esc_html__('Members-only content','nfinite-creators').'</h3><p>'.esc_html__('Join this creator’s membership to unlock this content and other exclusives.','nfinite-creators').'</p>'.$cta.'</div>';
	}

	public static function shortcode( $atts ) {
		$a=shortcode_atts(array('creator_id'=>0),$atts,'nfinite_memberships'); $creator=absint($a['creator_id']); if(!$creator && is_singular('nfinite_creator'))$creator=get_the_ID(); if(!$creator)return '';
		$tiers=self::tiers($creator); if(!$tiers)return '<div class="nfinite-memberships-empty">'.esc_html__('Memberships are not available yet.','nfinite-creators').'</div>';
		ob_start(); ?><div class="nfinite-memberships"><div class="nfinite-memberships__grid"><?php foreach($tiers as $tier): $price=get_post_meta($tier->ID,'_nfinite_membership_price',true);$product=absint(get_post_meta($tier->ID,'_nfinite_membership_product_id',true));$benefits=array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',(string)get_post_meta($tier->ID,'_nfinite_membership_benefits',true)))); ?><article class="nfinite-membership-tier"><h3><?php echo esc_html(get_the_title($tier)); ?></h3><?php if($price!==''): ?><div class="nfinite-membership-tier__price"><?php echo function_exists('wc_price')?wp_kses_post(wc_price((float)$price)):esc_html('$'.number_format((float)$price,2)); ?><span>/<?php esc_html_e('month','nfinite-creators'); ?></span></div><?php endif; ?><div><?php echo wp_kses_post(wpautop($tier->post_content)); ?></div><?php if($benefits): ?><ul><?php foreach($benefits as $b): ?><li><?php echo esc_html($b); ?></li><?php endforeach; ?></ul><?php endif; ?><?php if($product && function_exists('wc_get_cart_url')): ?><a class="nfinite-btn" href="<?php echo esc_url(add_query_arg('add-to-cart',$product,wc_get_cart_url())); ?>"><?php esc_html_e('Join Membership','nfinite-creators'); ?></a><?php endif; ?></article><?php endforeach; ?></div></div><?php return ob_get_clean();
	}

	public static function tier_for_product($product_id){ foreach(self::tiers() as $t){if(absint(get_post_meta($t->ID,'_nfinite_membership_product_id',true))===absint($product_id))return $t;} return null; }
	public static function order_paid($order_id){ if(!function_exists('wc_get_order'))return;$o=wc_get_order($order_id);if(!$o||!$o->get_user_id())return;foreach($o->get_items() as $item){$tier=self::tier_for_product($item->get_product_id());if($tier)self::grant($o->get_user_id(),$tier->ID);} }
	public static function subscription_active($subscription){ if(!is_object($subscription))return;$uid=absint($subscription->get_user_id());foreach($subscription->get_items() as $item){$tier=self::tier_for_product($item->get_product_id());if($tier)self::grant($uid,$tier->ID);} }
	public static function subscription_inactive($subscription){ if(!is_object($subscription))return;$uid=absint($subscription->get_user_id());foreach($subscription->get_items() as $item){$tier=self::tier_for_product($item->get_product_id());if($tier)self::revoke($uid,$tier->ID);} }
}
