<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Playlists + Collections V2.
 *
 * First-class fan playlists and creator-curated collections. The object stores
 * ordered references to existing Nfinite content so media ownership, access,
 * engagement and earnings continue to live on the source objects.
 */
class Nfinite_Creators_Playlists {
	const POST_TYPE = 'nfinite_playlist';
	const ITEMS_META = '_nfinite_playlist_items';
	const OWNER_META = '_nfinite_playlist_owner_user_id';
	const CREATOR_META = '_nfinite_playlist_creator_id';
	const ORG_META = '_nfinite_playlist_org_id';
	const VISIBILITY_META = '_nfinite_playlist_visibility';
	const KIND_META = '_nfinite_playlist_kind';
	const CURATOR_META = '_nfinite_playlist_curator_type';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_admin_meta' ), 10, 2 );
		add_filter( 'the_content', array( __CLASS__, 'render_single' ), 30 );
		add_shortcode( 'nfinite_playlists', array( __CLASS__, 'directory_shortcode' ) );
		add_shortcode( 'nfinite_playlist', array( __CLASS__, 'playlist_shortcode' ) );
		add_shortcode( 'nfinite_playlist_builder', array( __CLASS__, 'builder_shortcode' ) );
		add_shortcode( 'nfinite_add_to_playlist', array( __CLASS__, 'add_to_playlist_shortcode' ) );
		add_filter( 'the_content', array( __CLASS__, 'append_add_control' ), 37 );
		add_action( 'wp_ajax_nfinite_playlist_create', array( __CLASS__, 'ajax_create' ) );
		add_action( 'wp_ajax_nfinite_playlist_update', array( __CLASS__, 'ajax_update' ) );
		add_action( 'wp_ajax_nfinite_playlist_add_item', array( __CLASS__, 'ajax_add_item' ) );
		add_action( 'wp_ajax_nfinite_playlist_remove_item', array( __CLASS__, 'ajax_remove_item' ) );
		add_action( 'wp_ajax_nfinite_playlist_reorder', array( __CLASS__, 'ajax_reorder' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function register() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name' => __( 'Playlists & Collections', 'nfinite-creators' ),
				'singular_name' => __( 'Playlist / Collection', 'nfinite-creators' ),
				'add_new_item' => __( 'Add Playlist / Collection', 'nfinite-creators' ),
				'edit_item' => __( 'Edit Playlist / Collection', 'nfinite-creators' ),
			),
			'public' => true,
			'show_in_rest' => true,
			'show_ui' => true,
			'show_in_menu' => 'edit.php?post_type=nfinite_creator',
			'has_archive' => 'playlists',
			'rewrite' => array( 'slug' => 'playlist' ),
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author' ),
			'menu_icon' => 'dashicons-playlist-audio',
		) );
	}

	public static function allowed_item_types() {
		return array( 'nfinite_track', 'nfinite_release', 'nfinite_show', 'nfinite_episode', 'nfinite_video', 'nfinite_event', 'nfinite_creator_post', 'post' );
	}

	public static function enqueue() {
		wp_enqueue_script( 'nfinite-playlists', NFINITE_CREATORS_URL . 'public/js/nfinite-playlists.js', array( 'nfinite-creators' ), NFINITE_CREATORS_VERSION, true );
		wp_localize_script( 'nfinite-playlists', 'NfinitePlaylists', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'nfinite_playlists' ),
			'loginUrl' => wp_login_url( home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) ) ),
		) );
	}

	public static function meta_boxes() {
		add_meta_box( 'nfinite_playlist_settings', __( 'Nfinite Playlist / Collection', 'nfinite-creators' ), array( __CLASS__, 'meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public static function meta_box( $post ) {
		wp_nonce_field( 'nfinite_playlist_admin', 'nfinite_playlist_admin_nonce' );
		$visibility = self::visibility( $post->ID );
		$kind = self::kind( $post->ID );
		$curator = self::curator_type( $post->ID );
		$creator = absint( get_post_meta( $post->ID, self::CREATOR_META, true ) );
		$org = absint( get_post_meta( $post->ID, self::ORG_META, true ) );
		$items = self::items( $post->ID );
		?>
		<p><label><strong><?php esc_html_e( 'Type', 'nfinite-creators' ); ?></strong><br>
		<select name="nfinite_playlist_kind"><option value="music" <?php selected( $kind, 'music' ); ?>><?php esc_html_e( 'Music Playlist', 'nfinite-creators' ); ?></option><option value="mixed" <?php selected( $kind, 'mixed' ); ?>><?php esc_html_e( 'Mixed Media Collection', 'nfinite-creators' ); ?></option></select></label></p>
		<p><label><strong><?php esc_html_e( 'Visibility', 'nfinite-creators' ); ?></strong><br>
		<select name="nfinite_playlist_visibility"><option value="public" <?php selected( $visibility, 'public' ); ?>><?php esc_html_e( 'Public', 'nfinite-creators' ); ?></option><option value="unlisted" <?php selected( $visibility, 'unlisted' ); ?>><?php esc_html_e( 'Unlisted', 'nfinite-creators' ); ?></option><option value="private" <?php selected( $visibility, 'private' ); ?>><?php esc_html_e( 'Private', 'nfinite-creators' ); ?></option></select></label></p>
		<p><label><strong><?php esc_html_e( 'Curator', 'nfinite-creators' ); ?></strong><br>
		<select name="nfinite_playlist_curator_type"><option value="fan" <?php selected( $curator, 'fan' ); ?>><?php esc_html_e( 'Fan / User', 'nfinite-creators' ); ?></option><option value="creator" <?php selected( $curator, 'creator' ); ?>><?php esc_html_e( 'Creator', 'nfinite-creators' ); ?></option><option value="organization" <?php selected( $curator, 'organization' ); ?>><?php esc_html_e( 'Organization', 'nfinite-creators' ); ?></option><option value="editorial" <?php selected( $curator, 'editorial' ); ?>><?php esc_html_e( 'PairOfDice Editorial', 'nfinite-creators' ); ?></option></select></label></p>
		<p><label><strong><?php esc_html_e( 'Creator ID', 'nfinite-creators' ); ?></strong><br><input type="number" min="0" name="nfinite_playlist_creator_id" value="<?php echo esc_attr( $creator ); ?>"></label></p>
		<p><label><strong><?php esc_html_e( 'Organization ID', 'nfinite-creators' ); ?></strong><br><input type="number" min="0" name="nfinite_playlist_org_id" value="<?php echo esc_attr( $org ); ?>"></label></p>
		<p><strong><?php esc_html_e( 'Ordered Items', 'nfinite-creators' ); ?></strong></p>
		<textarea name="nfinite_playlist_items" rows="6" style="width:100%" placeholder="101, 205, 309"><?php echo esc_textarea( implode( ', ', $items ) ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Enter post IDs in playback/display order. Music playlists accept tracks and releases; mixed collections can include music, video, shows, episodes, events and stories.', 'nfinite-creators' ); ?></p>
		<?php
	}

	public static function save_admin_meta( $post_id, $post ) {
		if ( ! isset( $_POST['nfinite_playlist_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_playlist_admin_nonce'] ) ), 'nfinite_playlist_admin' ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
		update_post_meta( $post_id, self::KIND_META, self::sanitize_kind( $_POST['nfinite_playlist_kind'] ?? 'music' ) );
		update_post_meta( $post_id, self::VISIBILITY_META, self::sanitize_visibility( $_POST['nfinite_playlist_visibility'] ?? 'public' ) );
		update_post_meta( $post_id, self::CURATOR_META, self::sanitize_curator( $_POST['nfinite_playlist_curator_type'] ?? 'editorial' ) );
		update_post_meta( $post_id, self::CREATOR_META, absint( $_POST['nfinite_playlist_creator_id'] ?? 0 ) );
		update_post_meta( $post_id, self::ORG_META, absint( $_POST['nfinite_playlist_org_id'] ?? 0 ) );
		$raw = sanitize_text_field( wp_unslash( $_POST['nfinite_playlist_items'] ?? '' ) );
		self::set_items( $post_id, preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY ) );
		if ( ! get_post_meta( $post_id, self::OWNER_META, true ) ) { update_post_meta( $post_id, self::OWNER_META, get_current_user_id() ); }
	}

	private static function sanitize_kind( $v ) { return in_array( sanitize_key( $v ), array( 'music', 'mixed' ), true ) ? sanitize_key( $v ) : 'music'; }
	private static function sanitize_visibility( $v ) { return in_array( sanitize_key( $v ), array( 'public', 'unlisted', 'private' ), true ) ? sanitize_key( $v ) : 'public'; }
	private static function sanitize_curator( $v ) { return in_array( sanitize_key( $v ), array( 'fan', 'creator', 'organization', 'editorial' ), true ) ? sanitize_key( $v ) : 'fan'; }
	public static function visibility( $id ) { return self::sanitize_visibility( get_post_meta( $id, self::VISIBILITY_META, true ) ?: 'public' ); }
	public static function kind( $id ) { return self::sanitize_kind( get_post_meta( $id, self::KIND_META, true ) ?: 'music' ); }
	public static function curator_type( $id ) { return self::sanitize_curator( get_post_meta( $id, self::CURATOR_META, true ) ?: 'fan' ); }

	public static function items( $playlist_id ) {
		$items = get_post_meta( absint( $playlist_id ), self::ITEMS_META, true );
		return is_array( $items ) ? array_values( array_unique( array_filter( array_map( 'absint', $items ) ) ) ) : array();
	}
	public static function set_items( $playlist_id, $ids ) {
		$kind = self::kind( $playlist_id ); $valid = array();
		foreach ( (array) $ids as $id ) {
			$id = absint( $id ); $type = get_post_type( $id );
			if ( ! $id || 'publish' !== get_post_status( $id ) || ! in_array( $type, self::allowed_item_types(), true ) ) { continue; }
			if ( 'music' === $kind && ! in_array( $type, array( 'nfinite_track', 'nfinite_release' ), true ) ) { continue; }
			$valid[] = $id;
		}
		update_post_meta( $playlist_id, self::ITEMS_META, array_values( array_unique( $valid ) ) );
		return $valid;
	}

	public static function can_view( $playlist_id, $user_id = 0 ) {
		$visibility = self::visibility( $playlist_id );
		if ( 'private' !== $visibility ) { return true; }
		$user_id = $user_id ?: get_current_user_id();
		return $user_id && ( absint( get_post_meta( $playlist_id, self::OWNER_META, true ) ) === $user_id || user_can( $user_id, 'edit_post', $playlist_id ) );
	}
	public static function can_manage( $playlist_id, $user_id = 0 ) {
		$user_id = $user_id ?: get_current_user_id(); if ( ! $user_id ) { return false; }
		if ( absint( get_post_meta( $playlist_id, self::OWNER_META, true ) ) === $user_id ) { return true; }
		return user_can( $user_id, 'edit_post', $playlist_id );
	}

	private static function curator_label( $id ) {
		$type = self::curator_type( $id );
		if ( 'creator' === $type ) { $cid=absint(get_post_meta($id,self::CREATOR_META,true)); return $cid?get_the_title($cid):__('Creator','nfinite-creators'); }
		if ( 'organization' === $type ) { $oid=absint(get_post_meta($id,self::ORG_META,true)); return $oid?get_the_title($oid):__('Organization','nfinite-creators'); }
		if ( 'editorial' === $type ) { return __( 'PairOfDice Editorial', 'nfinite-creators' ); }
		$owner=absint(get_post_meta($id,self::OWNER_META,true)); $u=$owner?get_userdata($owner):false; return $u?$u->display_name:__('Community','nfinite-creators');
	}

	private static function track_queue( $playlist_id ) {
		if ( ! class_exists( 'Nfinite_Creators_Music_Query' ) ) { return array(); }
		$queue=array();
		foreach(self::items($playlist_id) as $id){
			$type=get_post_type($id);
			if('nfinite_track'===$type){$p=Nfinite_Creators_Music_Query::track_payload($id);if(!empty($p['internalPlayable']))$queue[]=$p;}
			elseif('nfinite_release'===$type){
				$track_ids=get_post_meta($id,'_nfinite_release_track_ids',true); if(!is_array($track_ids))$track_ids=array();
				foreach($track_ids as $tid){$p=Nfinite_Creators_Music_Query::track_payload(absint($tid));if(!empty($p['internalPlayable']))$queue[]=$p;}
			}
		}
		return $queue;
	}

	public static function render_playlist( $playlist_id, $compact = false ) {
		$playlist_id=absint($playlist_id); $post=get_post($playlist_id);
		if(!$post||self::POST_TYPE!==$post->post_type||'publish'!==$post->post_status||!self::can_view($playlist_id))return '';
		$items=self::items($playlist_id); $queue=self::track_queue($playlist_id); $kind=self::kind($playlist_id);
		ob_start(); ?>
		<section class="nfinite-playlist-v2 <?php echo $compact?'is-compact':''; ?>" data-nfinite-playlist-id="<?php echo esc_attr($playlist_id); ?>">
			<header class="nfinite-playlist-header">
				<?php if(has_post_thumbnail($playlist_id)): ?><a class="nfinite-playlist-cover" href="<?php echo esc_url(get_permalink($playlist_id)); ?>"><?php echo get_the_post_thumbnail($playlist_id,'medium_large'); ?></a><?php endif; ?>
				<div><span class="nfinite-eyebrow"><?php echo esc_html('music'===$kind?__('Playlist','nfinite-creators'):__('Collection','nfinite-creators')); ?></span><h2><a href="<?php echo esc_url(get_permalink($playlist_id)); ?>"><?php echo esc_html(get_the_title($playlist_id)); ?></a></h2><p><?php echo esc_html(sprintf(__('Curated by %s · %d items','nfinite-creators'),self::curator_label($playlist_id),count($items))); ?></p>
				<?php if($queue): ?><div class="nfinite-playlist-controls" data-nfinite-track-queue data-queue="<?php echo esc_attr(wp_json_encode($queue)); ?>"><button type="button" class="button" data-play-queue-index="0"><?php esc_html_e('Play','nfinite-creators'); ?></button></div><?php endif; ?>
				<?php if(class_exists('Nfinite_Creators_Library')) echo Nfinite_Creators_Library::button($playlist_id,'save'); ?>
				</div>
			</header>
			<?php if(!$compact): ?><div class="nfinite-playlist-items">
			<?php foreach($items as $index=>$id): $item=get_post($id); if(!$item)continue; $type_obj=get_post_type_object($item->post_type); ?>
				<article class="nfinite-playlist-item" data-item-id="<?php echo esc_attr($id); ?>"><span class="nfinite-playlist-index"><?php echo esc_html($index+1); ?></span><?php if(has_post_thumbnail($id))echo '<a class="nfinite-playlist-thumb" href="'.esc_url(get_permalink($id)).'">'.get_the_post_thumbnail($id,'thumbnail').'</a>'; ?><div><small><?php echo esc_html($type_obj?$type_obj->labels->singular_name:$item->post_type); ?></small><h3><a href="<?php echo esc_url(get_permalink($id)); ?>"><?php echo esc_html(get_the_title($id)); ?></a></h3></div></article>
			<?php endforeach; ?></div><?php endif; ?>
		</section>
		<?php return ob_get_clean();
	}

	public static function render_single( $content ) {
		if(!is_singular(self::POST_TYPE)||!in_the_loop()||!is_main_query())return $content;
		$id=get_the_ID(); if(!self::can_view($id))return '<div class="nfinite-playlist-private"><h2>'.esc_html__('Private playlist','nfinite-creators').'</h2><p>'.esc_html__('This playlist is only visible to its owner.','nfinite-creators').'</p></div>';
		return $content.self::render_playlist($id,false);
	}
	public static function playlist_shortcode($atts){$a=shortcode_atts(array('id'=>get_the_ID(),'compact'=>'0'),$atts);return self::render_playlist(absint($a['id']),'1'===(string)$a['compact']);}

	public static function public_query_args($args=array()){
		return array_merge(array('post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>12,'meta_query'=>array(array('key'=>self::VISIBILITY_META,'value'=>'public','compare'=>'=')),'orderby'=>'date','order'=>'DESC'),$args);
	}
	public static function directory_shortcode($atts){
		$a=shortcode_atts(array('limit'=>12,'kind'=>'','creator_id'=>0),$atts); $meta=array(array('key'=>self::VISIBILITY_META,'value'=>'public'));
		if(in_array($a['kind'],array('music','mixed'),true))$meta[]=array('key'=>self::KIND_META,'value'=>$a['kind']); if(absint($a['creator_id']))$meta[]=array('key'=>self::CREATOR_META,'value'=>absint($a['creator_id']),'type'=>'NUMERIC');
		$q=get_posts(array('post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>min(50,max(1,absint($a['limit']))),'meta_query'=>$meta,'orderby'=>'date','order'=>'DESC'));
		ob_start(); echo '<section class="nfinite-playlists-directory"><header><span class="nfinite-eyebrow">'.esc_html__('PairOfDice Playlists','nfinite-creators').'</span><h2>'.esc_html__('Playlists & Collections','nfinite-creators').'</h2></header><div class="nfinite-playlist-grid">'; foreach($q as $p)echo self::render_playlist($p->ID,true); if(!$q)echo '<p>'.esc_html__('No public playlists yet.','nfinite-creators').'</p>'; echo '</div></section>'; return ob_get_clean();
	}

	private static function owned_playlists($uid){return get_posts(array('post_type'=>self::POST_TYPE,'post_status'=>array('publish','draft','private'),'posts_per_page'=>-1,'meta_key'=>self::OWNER_META,'meta_value'=>absint($uid),'orderby'=>'date','order'=>'DESC'));}
	public static function builder_shortcode(){
		if(!is_user_logged_in())return '<section class="nfinite-playlist-builder"><h2>'.esc_html__('Create playlists','nfinite-creators').'</h2><p>'.esc_html__('Log in to create and organize your own playlists and collections.','nfinite-creators').'</p></section>';
		$owned=self::owned_playlists(get_current_user_id()); ob_start(); ?>
		<section class="nfinite-playlist-builder" data-nfinite-playlist-builder><header><span class="nfinite-eyebrow"><?php esc_html_e('Your Library','nfinite-creators'); ?></span><h2><?php esc_html_e('My Playlists & Collections','nfinite-creators'); ?></h2><p><?php esc_html_e('Build music queues or mixed-media collections and choose whether they are public, unlisted, or private.','nfinite-creators'); ?></p></header>
		<form class="nfinite-playlist-create" data-playlist-create><input type="text" name="title" required maxlength="120" placeholder="<?php esc_attr_e('Playlist name','nfinite-creators'); ?>"><select name="kind"><option value="music"><?php esc_html_e('Music Playlist','nfinite-creators'); ?></option><option value="mixed"><?php esc_html_e('Mixed Media Collection','nfinite-creators'); ?></option></select><select name="visibility"><option value="public"><?php esc_html_e('Public','nfinite-creators'); ?></option><option value="unlisted"><?php esc_html_e('Unlisted','nfinite-creators'); ?></option><option value="private"><?php esc_html_e('Private','nfinite-creators'); ?></option></select><button type="submit"><?php esc_html_e('Create','nfinite-creators'); ?></button><span data-playlist-message></span></form>
		<div class="nfinite-playlist-owned"><?php foreach($owned as $p): ?><article><h3><a href="<?php echo esc_url(get_permalink($p)); ?>"><?php echo esc_html(get_the_title($p)); ?></a></h3><p><?php echo esc_html(ucfirst(self::visibility($p->ID)).' · '.count(self::items($p->ID)).' items'); ?></p></article><?php endforeach; if(!$owned): ?><p><?php esc_html_e('You have not created a playlist yet.','nfinite-creators'); ?></p><?php endif; ?></div></section>
		<?php return ob_get_clean();
	}

	public static function add_to_playlist_shortcode($atts){
		$a=shortcode_atts(array('id'=>get_the_ID()),$atts);$item=absint($a['id']);if(!is_user_logged_in()||!in_array(get_post_type($item),self::allowed_item_types(),true))return '';
		$owned=self::owned_playlists(get_current_user_id());if(!$owned)return '<a class="nfinite-add-playlist-link" href="#nfinite-playlist-builder">'.esc_html__('Create a playlist to add this item','nfinite-creators').'</a>';
		$options='';foreach($owned as $p){$kind=self::kind($p->ID);$type=get_post_type($item);if('music'===$kind&&!in_array($type,array('nfinite_track','nfinite_release'),true))continue;$options.='<option value="'.absint($p->ID).'">'.esc_html(get_the_title($p)).'</option>';}
		if(!$options)return '';
		return '<span class="nfinite-add-to-playlist"><select data-playlist-select aria-label="'.esc_attr__('Choose playlist','nfinite-creators').'">'.$options.'</select><button type="button" data-nfinite-add-to-playlist data-item-id="'.$item.'">'.esc_html__('Add to playlist','nfinite-creators').'</button><span data-playlist-add-message></span></span>';
	}
	public static function append_add_control($content){if(!is_singular()||!in_the_loop()||!is_main_query())return $content;$id=get_the_ID();$type=get_post_type($id);if(self::POST_TYPE===$type||!in_array($type,self::allowed_item_types(),true))return $content;if('nfinite_release'===$type&&class_exists('Nfinite_Creators_Music_Query')&&empty(Nfinite_Creators_Music_Query::queue_for_release($id)))return $content;$control=self::add_to_playlist_shortcode(array('id'=>$id));return $control?$content.'<div class="nfinite-add-to-playlist-inline">'.$control.'</div>':$content;}
	private static function ajax_auth(){check_ajax_referer('nfinite_playlists','nonce');if(!is_user_logged_in())wp_send_json_error(array('message'=>__('Log in to manage playlists.','nfinite-creators')),401);}
	public static function ajax_create(){self::ajax_auth(); $uid=get_current_user_id(); $title=sanitize_text_field(wp_unslash($_POST['title']??'')); if(!$title)wp_send_json_error(array('message'=>__('Enter a playlist name.','nfinite-creators')),400); $id=wp_insert_post(array('post_type'=>self::POST_TYPE,'post_status'=>'publish','post_title'=>$title,'post_author'=>$uid)); if(is_wp_error($id))wp_send_json_error(array('message'=>$id->get_error_message()),500); $creator=absint(get_user_meta($uid,'_nfinite_creator_profile_id',true)); update_post_meta($id,self::OWNER_META,$uid);update_post_meta($id,self::KIND_META,self::sanitize_kind($_POST['kind']??'music'));update_post_meta($id,self::VISIBILITY_META,self::sanitize_visibility($_POST['visibility']??'public'));update_post_meta($id,self::CURATOR_META,$creator?'creator':'fan');if($creator)update_post_meta($id,self::CREATOR_META,$creator);update_post_meta($id,self::ITEMS_META,array()); self::bust_discovery(); wp_send_json_success(array('id'=>$id,'url'=>get_permalink($id),'title'=>get_the_title($id)));}
	public static function ajax_update(){self::ajax_auth();$id=absint($_POST['playlist_id']??0);if(!self::can_manage($id))wp_send_json_error(array('message'=>__('You cannot edit this playlist.','nfinite-creators')),403); if(isset($_POST['title']))wp_update_post(array('ID'=>$id,'post_title'=>sanitize_text_field(wp_unslash($_POST['title'])))); if(isset($_POST['visibility']))update_post_meta($id,self::VISIBILITY_META,self::sanitize_visibility($_POST['visibility'])); self::bust_discovery();wp_send_json_success(array('url'=>get_permalink($id)));}
	public static function ajax_add_item(){self::ajax_auth();$id=absint($_POST['playlist_id']??0);$item=absint($_POST['item_id']??0);if(!self::can_manage($id))wp_send_json_error(array('message'=>__('You cannot edit this playlist.','nfinite-creators')),403);$items=self::items($id);$items[]=$item;$items=self::set_items($id,$items);self::bust_discovery();wp_send_json_success(array('count'=>count($items)));}
	public static function ajax_remove_item(){self::ajax_auth();$id=absint($_POST['playlist_id']??0);$item=absint($_POST['item_id']??0);if(!self::can_manage($id))wp_send_json_error(array('message'=>__('You cannot edit this playlist.','nfinite-creators')),403);$items=array_values(array_diff(self::items($id),array($item)));self::set_items($id,$items);self::bust_discovery();wp_send_json_success(array('count'=>count($items)));}
	public static function ajax_reorder(){self::ajax_auth();$id=absint($_POST['playlist_id']??0);if(!self::can_manage($id))wp_send_json_error(array('message'=>__('You cannot edit this playlist.','nfinite-creators')),403);$ids=isset($_POST['items'])?(array)$_POST['items']:array();$current=self::items($id);$ids=array_values(array_filter(array_map('absint',$ids),function($x) use ($current){ return in_array($x,$current,true); }));foreach($current as $x)if(!in_array($x,$ids,true))$ids[]=$x;self::set_items($id,$ids);wp_send_json_success(array('items'=>$ids));}

	public static function register_rest(){register_rest_route('nfinite/v1','/playlists',array('methods'=>WP_REST_Server::READABLE,'permission_callback'=>'__return_true','callback'=>array(__CLASS__,'rest_list'),'args'=>array('limit'=>array('default'=>12,'sanitize_callback'=>'absint'),'kind'=>array('default'=>'','sanitize_callback'=>'sanitize_key'))));}
	public static function rest_list(WP_REST_Request $r){$kind=sanitize_key($r->get_param('kind'));$meta=array(array('key'=>self::VISIBILITY_META,'value'=>'public'));if(in_array($kind,array('music','mixed'),true))$meta[]=array('key'=>self::KIND_META,'value'=>$kind);$posts=get_posts(array('post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>min(50,max(1,absint($r->get_param('limit')))),'meta_query'=>$meta));$out=array();foreach($posts as $p)$out[]=array('id'=>$p->ID,'title'=>get_the_title($p),'url'=>get_permalink($p),'kind'=>self::kind($p->ID),'curator'=>self::curator_label($p->ID),'items'=>self::items($p->ID),'image'=>get_the_post_thumbnail_url($p,'medium_large')?:'');return rest_ensure_response(array('items'=>$out));}
	private static function bust_discovery(){if(class_exists('Nfinite_Creators_Discovery'))Nfinite_Creators_Discovery::bust_global_cache();}
}
