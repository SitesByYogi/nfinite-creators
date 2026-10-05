<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Shows {
	const SHOW_POST_TYPE    = 'nfinite_show';
	const EPISODE_POST_TYPE = 'nfinite_episode';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes_' . self::SHOW_POST_TYPE, array( __CLASS__, 'add_show_meta_box' ) );
		add_action( 'add_meta_boxes_' . self::EPISODE_POST_TYPE, array( __CLASS__, 'add_episode_meta_box' ) );
		add_action( 'save_post_' . self::SHOW_POST_TYPE, array( __CLASS__, 'save_show_admin' ), 20, 2 );
		add_action( 'save_post_' . self::EPISODE_POST_TYPE, array( __CLASS__, 'save_episode_admin' ), 20, 2 );
		add_action( 'admin_post_nfinite_creator_save_show', array( __CLASS__, 'handle_creator_save_show' ) );
		add_action( 'admin_post_nfinite_creator_delete_show', array( __CLASS__, 'handle_creator_delete_show' ) );
		add_action( 'admin_post_nfinite_creator_save_episode', array( __CLASS__, 'handle_creator_save_episode' ) );
		add_action( 'admin_post_nfinite_creator_delete_episode', array( __CLASS__, 'handle_creator_delete_episode' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 40 );
		add_shortcode( 'nfinite_creator_shows', array( __CLASS__, 'creator_shows_shortcode' ) );
	}

	public static function register() {
		register_post_type(
			self::SHOW_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Shows & Series', 'nfinite-creators' ),
					'singular_name' => __( 'Show / Series', 'nfinite-creators' ),
					'add_new_item'  => __( 'Add Show / Series', 'nfinite-creators' ),
					'edit_item'     => __( 'Edit Show / Series', 'nfinite-creators' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => 'shows',
				'rewrite'      => array( 'slug' => 'shows', 'with_front' => false ),
				'show_in_menu' => 'edit.php?post_type=nfinite_creator',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
				'map_meta_cap' => true,
			)
		);

		register_post_type(
			self::EPISODE_POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Episodes', 'nfinite-creators' ),
					'singular_name' => __( 'Episode', 'nfinite-creators' ),
					'add_new_item'  => __( 'Add Episode', 'nfinite-creators' ),
					'edit_item'     => __( 'Edit Episode', 'nfinite-creators' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => 'episodes',
				'rewrite'      => array( 'slug' => 'episodes', 'with_front' => false ),
				'show_in_menu' => 'edit.php?post_type=nfinite_creator',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
				'map_meta_cap' => true,
			)
		);
	}

	public static function show_types() {
		return array(
			'series'      => __( 'Series', 'nfinite-creators' ),
			'podcast'     => __( 'Podcast', 'nfinite-creators' ),
			'web-series'  => __( 'Web Series', 'nfinite-creators' ),
			'talk-show'   => __( 'Talk / Interview Show', 'nfinite-creators' ),
			'news'        => __( 'News / Commentary', 'nfinite-creators' ),
			'documentary' => __( 'Documentary Series', 'nfinite-creators' ),
			'comedy'      => __( 'Comedy Series', 'nfinite-creators' ),
			'other'       => __( 'Other', 'nfinite-creators' ),
		);
	}

	private static function creator_options() {
		return get_posts( array( 'post_type' => 'nfinite_creator', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) );
	}

	private static function show_options() {
		return get_posts( array( 'post_type' => self::SHOW_POST_TYPE, 'post_status' => array( 'publish', 'pending', 'draft' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) );
	}

	public static function add_show_meta_box() {
		add_meta_box( 'nfinite_show_details', __( 'Nfinite Show / Series Details', 'nfinite-creators' ), array( __CLASS__, 'render_show_meta_box' ), self::SHOW_POST_TYPE, 'normal', 'high' );
	}

	public static function add_episode_meta_box() {
		add_meta_box( 'nfinite_episode_details', __( 'Nfinite Episode Details', 'nfinite-creators' ), array( __CLASS__, 'render_episode_meta_box' ), self::EPISODE_POST_TYPE, 'normal', 'high' );
	}

	public static function render_show_meta_box( $post ) {
		wp_nonce_field( 'nfinite_show_admin_save', 'nfinite_show_admin_nonce' );
		$creator_id = absint( get_post_meta( $post->ID, '_nfinite_show_creator_id', true ) );
		$type       = sanitize_key( get_post_meta( $post->ID, '_nfinite_show_type', true ) ) ?: 'series';
		$hosts      = (array) get_post_meta( $post->ID, '_nfinite_show_hosts', true );
		$network    = sanitize_text_field( get_post_meta( $post->ID, '_nfinite_show_network', true ) );
		$website    = esc_url_raw( get_post_meta( $post->ID, '_nfinite_show_website', true ) );
		$youtube    = esc_url_raw( get_post_meta( $post->ID, '_nfinite_show_youtube', true ) );
		$tv         = (bool) get_post_meta( $post->ID, '_nfinite_show_tv', true );
		$featured   = (bool) get_post_meta( $post->ID, '_nfinite_show_featured', true );
		$creators   = self::creator_options();
		?>
		<div class="nfinite-admin-builder"><section class="nfinite-admin-section"><div class="nfinite-admin-grid">
			<label class="nfinite-admin-field"><span><?php esc_html_e( 'Show Type', 'nfinite-creators' ); ?></span><select class="widefat" name="nfinite_show_type"><?php foreach ( self::show_types() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label class="nfinite-admin-field"><span><?php esc_html_e( 'Owner / Primary Creator', 'nfinite-creators' ); ?></span><select class="widefat" name="nfinite_show_creator_id"><option value="0"><?php esc_html_e( 'PairOfDice / no creator', 'nfinite-creators' ); ?></option><?php foreach ( $creators as $creator ) : ?><option value="<?php echo esc_attr( $creator->ID ); ?>" <?php selected( $creator_id, $creator->ID ); ?>><?php echo esc_html( get_the_title( $creator ) ); ?></option><?php endforeach; ?></select></label>
			<label class="nfinite-admin-field"><span><?php esc_html_e( 'Network / Studio / Label', 'nfinite-creators' ); ?></span><input class="widefat" type="text" name="nfinite_show_network" value="<?php echo esc_attr( $network ); ?>"></label>
			<label class="nfinite-admin-field"><span><?php esc_html_e( 'Website', 'nfinite-creators' ); ?></span><input class="widefat" type="url" name="nfinite_show_website" value="<?php echo esc_attr( $website ); ?>"></label>
			<label class="nfinite-admin-field nfinite-admin-field--full"><span><?php esc_html_e( 'YouTube / Primary Channel URL', 'nfinite-creators' ); ?></span><input class="widefat" type="url" name="nfinite_show_youtube" value="<?php echo esc_attr( $youtube ); ?>"></label>
			<div class="nfinite-admin-field nfinite-admin-field--full"><span><?php esc_html_e( 'Hosts / Regular Creators', 'nfinite-creators' ); ?></span><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;margin-top:8px"><?php foreach ( $creators as $creator ) : ?><label><input type="checkbox" name="nfinite_show_hosts[]" value="<?php echo esc_attr( $creator->ID ); ?>" <?php checked( in_array( (string) $creator->ID, array_map( 'strval', $hosts ), true ) ); ?>> <?php echo esc_html( get_the_title( $creator ) ); ?></label><?php endforeach; ?></div></div>
			<label class="nfinite-admin-field"><input type="checkbox" name="nfinite_show_tv" value="1" <?php checked( $tv ); ?>> <?php esc_html_e( 'Include in PairOfDice TV', 'nfinite-creators' ); ?></label>
			<label class="nfinite-admin-field"><input type="checkbox" name="nfinite_show_featured" value="1" <?php checked( $featured ); ?>> <?php esc_html_e( 'Featured Show', 'nfinite-creators' ); ?></label>
		</div></section></div>
		<?php
	}

	public static function render_episode_meta_box( $post ) {
		wp_nonce_field( 'nfinite_episode_admin_save', 'nfinite_episode_admin_nonce' );
		$show_id    = absint( get_post_meta( $post->ID, '_nfinite_episode_show_id', true ) );
		$creator_id = absint( get_post_meta( $post->ID, '_nfinite_episode_creator_id', true ) );
		$season     = absint( get_post_meta( $post->ID, '_nfinite_episode_season', true ) );
		$number     = absint( get_post_meta( $post->ID, '_nfinite_episode_number', true ) );
		$video      = esc_url_raw( get_post_meta( $post->ID, '_nfinite_episode_video_url', true ) );
		$audio      = esc_url_raw( get_post_meta( $post->ID, '_nfinite_episode_audio_url', true ) );
		$runtime    = sanitize_text_field( get_post_meta( $post->ID, '_nfinite_episode_runtime', true ) );
		$guests     = (array) get_post_meta( $post->ID, '_nfinite_episode_guests', true );
		$tv         = (bool) get_post_meta( $post->ID, '_nfinite_episode_tv', true );
		$featured   = (bool) get_post_meta( $post->ID, '_nfinite_episode_featured', true );
		$shows      = self::show_options();
		$creators   = self::creator_options();
		?>
		<div class="nfinite-admin-builder"><section class="nfinite-admin-section"><div class="nfinite-admin-grid">
			<label class="nfinite-admin-field"><span><?php esc_html_e( 'Show / Series', 'nfinite-creators' ); ?></span><select class="widefat" name="nfinite_episode_show_id"><option value="0"><?php esc_html_e( 'No show', 'nfinite-creators' ); ?></option><?php foreach ( $shows as $show ) : ?><option value="<?php echo esc_attr( $show->ID ); ?>" <?php selected( $show_id, $show->ID ); ?>><?php echo esc_html( get_the_title( $show ) ); ?></option><?php endforeach; ?></select></label>
			<label class="nfinite-admin-field"><span><?php esc_html_e( 'Primary Creator', 'nfinite-creators' ); ?></span><select class="widefat" name="nfinite_episode_creator_id"><option value="0"><?php esc_html_e( 'Inherit show owner / none', 'nfinite-creators' ); ?></option><?php foreach ( $creators as $creator ) : ?><option value="<?php echo esc_attr( $creator->ID ); ?>" <?php selected( $creator_id, $creator->ID ); ?>><?php echo esc_html( get_the_title( $creator ) ); ?></option><?php endforeach; ?></select></label>
			<label class="nfinite-admin-field"><span><?php esc_html_e( 'Season', 'nfinite-creators' ); ?></span><input class="widefat" type="number" min="0" name="nfinite_episode_season" value="<?php echo esc_attr( $season ); ?>"></label>
			<label class="nfinite-admin-field"><span><?php esc_html_e( 'Episode #', 'nfinite-creators' ); ?></span><input class="widefat" type="number" min="0" name="nfinite_episode_number" value="<?php echo esc_attr( $number ); ?>"></label>
			<label class="nfinite-admin-field nfinite-admin-field--full"><span><?php esc_html_e( 'Video URL', 'nfinite-creators' ); ?></span><input class="widefat" type="url" name="nfinite_episode_video_url" value="<?php echo esc_attr( $video ); ?>" placeholder="https://youtube.com/watch?v=..."></label>
			<label class="nfinite-admin-field nfinite-admin-field--full"><span><?php esc_html_e( 'Audio URL', 'nfinite-creators' ); ?></span><input class="widefat" type="url" name="nfinite_episode_audio_url" value="<?php echo esc_attr( $audio ); ?>"></label>
			<label class="nfinite-admin-field"><span><?php esc_html_e( 'Runtime', 'nfinite-creators' ); ?></span><input class="widefat" type="text" name="nfinite_episode_runtime" value="<?php echo esc_attr( $runtime ); ?>" placeholder="42m"></label>
			<div class="nfinite-admin-field nfinite-admin-field--full"><span><?php esc_html_e( 'Guests / Featured Creators', 'nfinite-creators' ); ?></span><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;margin-top:8px"><?php foreach ( $creators as $creator ) : ?><label><input type="checkbox" name="nfinite_episode_guests[]" value="<?php echo esc_attr( $creator->ID ); ?>" <?php checked( in_array( (string) $creator->ID, array_map( 'strval', $guests ), true ) ); ?>> <?php echo esc_html( get_the_title( $creator ) ); ?></label><?php endforeach; ?></div></div>
			<label class="nfinite-admin-field"><input type="checkbox" name="nfinite_episode_tv" value="1" <?php checked( $tv ); ?>> <?php esc_html_e( 'Include in PairOfDice TV', 'nfinite-creators' ); ?></label>
			<label class="nfinite-admin-field"><input type="checkbox" name="nfinite_episode_featured" value="1" <?php checked( $featured ); ?>> <?php esc_html_e( 'Featured Episode', 'nfinite-creators' ); ?></label>
		</div></section></div>
		<?php
	}

	private static function sanitize_creator_ids( $value ) {
		$value = is_array( $value ) ? $value : array();
		return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
	}

	public static function save_show_admin( $post_id, $post ) {
		if ( ! isset( $_POST['nfinite_show_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_show_admin_nonce'] ) ), 'nfinite_show_admin_save' ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) || self::SHOW_POST_TYPE !== $post->post_type ) { return; }
		self::save_show_meta_from_request( $post_id, $_POST );
	}

	private static function save_show_meta_from_request( $post_id, $request ) {
		$type = isset( $request['nfinite_show_type'] ) ? sanitize_key( wp_unslash( $request['nfinite_show_type'] ) ) : 'series';
		if ( ! isset( self::show_types()[ $type ] ) ) { $type = 'series'; }
		update_post_meta( $post_id, '_nfinite_show_type', $type );
		update_post_meta( $post_id, '_nfinite_show_creator_id', isset( $request['nfinite_show_creator_id'] ) ? absint( $request['nfinite_show_creator_id'] ) : 0 );
		update_post_meta( $post_id, '_nfinite_show_network', isset( $request['nfinite_show_network'] ) ? sanitize_text_field( wp_unslash( $request['nfinite_show_network'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_show_website', isset( $request['nfinite_show_website'] ) ? esc_url_raw( wp_unslash( $request['nfinite_show_website'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_show_youtube', isset( $request['nfinite_show_youtube'] ) ? esc_url_raw( wp_unslash( $request['nfinite_show_youtube'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_show_hosts', self::sanitize_creator_ids( isset( $request['nfinite_show_hosts'] ) ? wp_unslash( $request['nfinite_show_hosts'] ) : array() ) );
		update_post_meta( $post_id, '_nfinite_show_tv', empty( $request['nfinite_show_tv'] ) ? 0 : 1 );
		update_post_meta( $post_id, '_nfinite_show_featured', empty( $request['nfinite_show_featured'] ) ? 0 : 1 );
	}

	public static function save_episode_admin( $post_id, $post ) {
		if ( ! isset( $_POST['nfinite_episode_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_episode_admin_nonce'] ) ), 'nfinite_episode_admin_save' ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) || self::EPISODE_POST_TYPE !== $post->post_type ) { return; }
		self::save_episode_meta_from_request( $post_id, $_POST );
	}

	private static function save_episode_meta_from_request( $post_id, $request ) {
		update_post_meta( $post_id, '_nfinite_episode_show_id', isset( $request['nfinite_episode_show_id'] ) ? absint( $request['nfinite_episode_show_id'] ) : 0 );
		update_post_meta( $post_id, '_nfinite_episode_creator_id', isset( $request['nfinite_episode_creator_id'] ) ? absint( $request['nfinite_episode_creator_id'] ) : 0 );
		update_post_meta( $post_id, '_nfinite_episode_season', isset( $request['nfinite_episode_season'] ) ? absint( $request['nfinite_episode_season'] ) : 0 );
		update_post_meta( $post_id, '_nfinite_episode_number', isset( $request['nfinite_episode_number'] ) ? absint( $request['nfinite_episode_number'] ) : 0 );
		update_post_meta( $post_id, '_nfinite_episode_video_url', isset( $request['nfinite_episode_video_url'] ) ? esc_url_raw( wp_unslash( $request['nfinite_episode_video_url'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_episode_audio_url', isset( $request['nfinite_episode_audio_url'] ) ? esc_url_raw( wp_unslash( $request['nfinite_episode_audio_url'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_episode_runtime', isset( $request['nfinite_episode_runtime'] ) ? sanitize_text_field( wp_unslash( $request['nfinite_episode_runtime'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_episode_guests', self::sanitize_creator_ids( isset( $request['nfinite_episode_guests'] ) ? wp_unslash( $request['nfinite_episode_guests'] ) : array() ) );
		update_post_meta( $post_id, '_nfinite_episode_tv', empty( $request['nfinite_episode_tv'] ) ? 0 : 1 );
		update_post_meta( $post_id, '_nfinite_episode_featured', empty( $request['nfinite_episode_featured'] ) ? 0 : 1 );
	}

	private static function can_manage_creator( $creator_id ) {
		if ( ! is_user_logged_in() || self::creator_post_type() !== get_post_type( $creator_id ) ) { return false; }
		return current_user_can( 'manage_options' ) || (int) get_post_field( 'post_author', $creator_id ) === get_current_user_id();
	}

	private static function creator_post_type() { return 'nfinite_creator'; }

	private static function creator_redirect( $creator_id, $notice = '' ) {
		$url = class_exists( 'Nfinite_Creators_Roles' ) ? Nfinite_Creators_Roles::dashboard_url() : home_url( '/' );
		$url = add_query_arg( array( 'creator_id' => $creator_id, 'studio' => 'shows' ), $url );
		if ( $notice ) { $url = add_query_arg( 'nfinite_notice', sanitize_key( $notice ), $url ); }
		wp_safe_redirect( $url );
		exit;
	}

	public static function handle_creator_save_show() {
		$creator_id = isset( $_POST['creator_id'] ) ? absint( $_POST['creator_id'] ) : 0;
		if ( ! self::can_manage_creator( $creator_id ) ) { wp_die( esc_html__( 'You cannot manage this creator.', 'nfinite-creators' ) ); }
		if ( ! isset( $_POST['nfinite_show_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_show_nonce'] ) ), 'nfinite_creator_save_show_' . $creator_id ) ) { wp_die( esc_html__( 'Security check failed.', 'nfinite-creators' ) ); }
		$show_id = isset( $_POST['show_id'] ) ? absint( $_POST['show_id'] ) : 0;
		if ( $show_id && ( self::SHOW_POST_TYPE !== get_post_type( $show_id ) || absint( get_post_meta( $show_id, '_nfinite_show_creator_id', true ) ) !== $creator_id ) ) { wp_die( esc_html__( 'Invalid show.', 'nfinite-creators' ) ); }
		$title = isset( $_POST['show_title'] ) ? sanitize_text_field( wp_unslash( $_POST['show_title'] ) ) : '';
		if ( ! $title ) { self::creator_redirect( $creator_id, 'show-title-required' ); }
		$postarr = array( 'post_type' => self::SHOW_POST_TYPE, 'post_title' => $title, 'post_content' => isset( $_POST['show_description'] ) ? wp_kses_post( wp_unslash( $_POST['show_description'] ) ) : '', 'post_status' => current_user_can( 'manage_options' ) ? 'publish' : 'pending', 'post_author' => get_current_user_id() );
		if ( $show_id ) { $postarr['ID'] = $show_id; $postarr['post_status'] = get_post_status( $show_id ); }
		$result = wp_insert_post( $postarr, true );
		if ( is_wp_error( $result ) ) { self::creator_redirect( $creator_id, 'show-save-failed' ); }
		$_POST['nfinite_show_creator_id'] = $creator_id;
		self::save_show_meta_from_request( $result, $_POST );
		update_post_meta( $result, '_nfinite_show_creator_id', $creator_id );
		self::creator_redirect( $creator_id, 'show-saved' );
	}

	public static function handle_creator_delete_show() {
		$creator_id = isset( $_GET['creator_id'] ) ? absint( $_GET['creator_id'] ) : 0;
		$show_id = isset( $_GET['show_id'] ) ? absint( $_GET['show_id'] ) : 0;
		if ( ! self::can_manage_creator( $creator_id ) || self::SHOW_POST_TYPE !== get_post_type( $show_id ) || absint( get_post_meta( $show_id, '_nfinite_show_creator_id', true ) ) !== $creator_id ) { wp_die( esc_html__( 'Invalid request.', 'nfinite-creators' ) ); }
		check_admin_referer( 'nfinite_creator_delete_show_' . $show_id );
		wp_trash_post( $show_id );
		self::creator_redirect( $creator_id, 'show-deleted' );
	}

	public static function handle_creator_save_episode() {
		$creator_id = isset( $_POST['creator_id'] ) ? absint( $_POST['creator_id'] ) : 0;
		if ( ! self::can_manage_creator( $creator_id ) ) { wp_die( esc_html__( 'You cannot manage this creator.', 'nfinite-creators' ) ); }
		if ( ! isset( $_POST['nfinite_episode_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_episode_nonce'] ) ), 'nfinite_creator_save_episode_' . $creator_id ) ) { wp_die( esc_html__( 'Security check failed.', 'nfinite-creators' ) ); }
		$show_id = isset( $_POST['nfinite_episode_show_id'] ) ? absint( $_POST['nfinite_episode_show_id'] ) : 0;
		if ( ! $show_id || self::SHOW_POST_TYPE !== get_post_type( $show_id ) || absint( get_post_meta( $show_id, '_nfinite_show_creator_id', true ) ) !== $creator_id ) { wp_die( esc_html__( 'Choose one of your shows.', 'nfinite-creators' ) ); }
		$episode_id = isset( $_POST['episode_id'] ) ? absint( $_POST['episode_id'] ) : 0;
		if ( $episode_id && ( self::EPISODE_POST_TYPE !== get_post_type( $episode_id ) || absint( get_post_meta( $episode_id, '_nfinite_episode_creator_id', true ) ) !== $creator_id ) ) { wp_die( esc_html__( 'Invalid episode.', 'nfinite-creators' ) ); }
		$title = isset( $_POST['episode_title'] ) ? sanitize_text_field( wp_unslash( $_POST['episode_title'] ) ) : '';
		if ( ! $title ) { self::creator_redirect( $creator_id, 'episode-title-required' ); }
		$postarr = array( 'post_type' => self::EPISODE_POST_TYPE, 'post_title' => $title, 'post_content' => isset( $_POST['episode_description'] ) ? wp_kses_post( wp_unslash( $_POST['episode_description'] ) ) : '', 'post_status' => current_user_can( 'manage_options' ) ? 'publish' : 'pending', 'post_author' => get_current_user_id() );
		if ( $episode_id ) { $postarr['ID'] = $episode_id; $postarr['post_status'] = get_post_status( $episode_id ); }
		$result = wp_insert_post( $postarr, true );
		if ( is_wp_error( $result ) ) { self::creator_redirect( $creator_id, 'episode-save-failed' ); }
		$_POST['nfinite_episode_creator_id'] = $creator_id;
		self::save_episode_meta_from_request( $result, $_POST );
		update_post_meta( $result, '_nfinite_episode_creator_id', $creator_id );
		if ( ! empty( $_POST['nfinite_episode_revenue_share_request'] ) ) {
			update_post_meta( $result, '_nfinite_episode_video_rights_confirmed', 1 );
			$current_video_status = sanitize_key( get_post_meta( $result, '_nfinite_episode_video_monetization_status', true ) );
			if ( ! in_array( $current_video_status, array( 'monetized', 'ineligible', 'suspended' ), true ) ) { update_post_meta( $result, '_nfinite_episode_video_monetization_status', 'pending_review' ); }
		}
		self::creator_redirect( $creator_id, 'episode-saved' );
	}

	public static function handle_creator_delete_episode() {
		$creator_id = isset( $_GET['creator_id'] ) ? absint( $_GET['creator_id'] ) : 0;
		$episode_id = isset( $_GET['episode_id'] ) ? absint( $_GET['episode_id'] ) : 0;
		if ( ! self::can_manage_creator( $creator_id ) || self::EPISODE_POST_TYPE !== get_post_type( $episode_id ) || absint( get_post_meta( $episode_id, '_nfinite_episode_creator_id', true ) ) !== $creator_id ) { wp_die( esc_html__( 'Invalid request.', 'nfinite-creators' ) ); }
		check_admin_referer( 'nfinite_creator_delete_episode_' . $episode_id );
		wp_trash_post( $episode_id );
		self::creator_redirect( $creator_id, 'episode-deleted' );
	}

	public static function creator_shows( $creator_id, $statuses = array( 'publish', 'pending', 'draft' ) ) {
		return get_posts( array( 'post_type' => self::SHOW_POST_TYPE, 'post_status' => $statuses, 'posts_per_page' => -1, 'meta_key' => '_nfinite_show_creator_id', 'meta_value' => absint( $creator_id ), 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => true ) );
	}

	public static function creator_episodes( $creator_id, $statuses = array( 'publish', 'pending', 'draft' ) ) {
		return get_posts( array( 'post_type' => self::EPISODE_POST_TYPE, 'post_status' => $statuses, 'posts_per_page' => -1, 'meta_key' => '_nfinite_episode_creator_id', 'meta_value' => absint( $creator_id ), 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
	}

	public static function episodes_for_show( $show_id, $status = 'publish' ) {
		return get_posts( array( 'post_type' => self::EPISODE_POST_TYPE, 'post_status' => $status, 'posts_per_page' => -1, 'meta_key' => '_nfinite_episode_show_id', 'meta_value' => absint( $show_id ), 'orderby' => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ), 'no_found_rows' => true ) );
	}

	public static function render_studio_panel( $creator_id ) {
		if ( ! self::can_manage_creator( $creator_id ) ) { return; }
		$shows = self::creator_shows( $creator_id );
		$episodes = self::creator_episodes( $creator_id );
		?>
		<div class="nfinite-form-section nfinite-studio-panel nfinite-shows-studio" data-studio-panel="shows">
			<div class="nfinite-form-section__head"><span>TV</span><div><h3><?php esc_html_e( 'Shows, Series & Episodes', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Build structured programming for PairOfDice TV, podcasts, web series, interviews, films, and creator-led shows.', 'nfinite-creators' ); ?></p></div></div>
			<div class="nfinite-shows-studio__grid">
				<section class="nfinite-shows-studio__card"><h4><?php esc_html_e( 'Create a Show / Series', 'nfinite-creators' ); ?></h4>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="nfinite_creator_save_show"><input type="hidden" name="creator_id" value="<?php echo esc_attr( $creator_id ); ?>"><?php wp_nonce_field( 'nfinite_creator_save_show_' . $creator_id, 'nfinite_show_nonce' ); ?>
						<div class="nfinite-form-grid"><label><span><?php esc_html_e( 'Title', 'nfinite-creators' ); ?></span><input required type="text" name="show_title"></label><label><span><?php esc_html_e( 'Type', 'nfinite-creators' ); ?></span><select name="nfinite_show_type"><?php foreach ( self::show_types() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><label class="nfinite-form-full"><span><?php esc_html_e( 'Description', 'nfinite-creators' ); ?></span><textarea name="show_description" rows="4"></textarea></label><label><span><?php esc_html_e( 'Network / Studio', 'nfinite-creators' ); ?></span><input type="text" name="nfinite_show_network"></label><label><span><?php esc_html_e( 'YouTube / Channel URL', 'nfinite-creators' ); ?></span><input type="url" name="nfinite_show_youtube"></label><label class="nfinite-toggle"><input type="checkbox" name="nfinite_show_tv" value="1" checked><span><?php esc_html_e( 'Submit for PairOfDice TV', 'nfinite-creators' ); ?></span></label></div>
						<button class="nfinite-btn nfinite-btn-primary" type="submit"><?php esc_html_e( 'Create Show', 'nfinite-creators' ); ?></button>
					</form>
				</section>
				<section class="nfinite-shows-studio__card"><h4><?php esc_html_e( 'Add an Episode', 'nfinite-creators' ); ?></h4>
					<?php if ( $shows ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="nfinite_creator_save_episode"><input type="hidden" name="creator_id" value="<?php echo esc_attr( $creator_id ); ?>"><?php wp_nonce_field( 'nfinite_creator_save_episode_' . $creator_id, 'nfinite_episode_nonce' ); ?>
						<div class="nfinite-form-grid"><label><span><?php esc_html_e( 'Show', 'nfinite-creators' ); ?></span><select required name="nfinite_episode_show_id"><?php foreach ( $shows as $show ) : ?><option value="<?php echo esc_attr( $show->ID ); ?>"><?php echo esc_html( get_the_title( $show ) ); ?></option><?php endforeach; ?></select></label><label><span><?php esc_html_e( 'Episode title', 'nfinite-creators' ); ?></span><input required type="text" name="episode_title"></label><label><span><?php esc_html_e( 'Season', 'nfinite-creators' ); ?></span><input type="number" min="0" name="nfinite_episode_season"></label><label><span><?php esc_html_e( 'Episode #', 'nfinite-creators' ); ?></span><input type="number" min="0" name="nfinite_episode_number"></label><label class="nfinite-form-full"><span><?php esc_html_e( 'YouTube / Vimeo video URL', 'nfinite-creators' ); ?></span><input type="url" name="nfinite_episode_video_url"></label><label><span><?php esc_html_e( 'Runtime', 'nfinite-creators' ); ?></span><input type="text" name="nfinite_episode_runtime" placeholder="42m"></label><label class="nfinite-toggle"><input type="checkbox" name="nfinite_episode_tv" value="1" checked><span><?php esc_html_e( 'Submit for PairOfDice TV', 'nfinite-creators' ); ?></span></label><label class="nfinite-toggle nfinite-form-full"><input type="checkbox" name="nfinite_episode_revenue_share_request" value="1"><span><?php esc_html_e( 'Request PairOfDice video revenue share review. I confirm I control the necessary rights to this video and authorize PairOfDice to monetize eligible engagement on its platform.', 'nfinite-creators' ); ?></span></label><label class="nfinite-form-full"><span><?php esc_html_e( 'Episode description', 'nfinite-creators' ); ?></span><textarea name="episode_description" rows="4"></textarea></label></div>
						<button class="nfinite-btn nfinite-btn-primary" type="submit"><?php esc_html_e( 'Add Episode', 'nfinite-creators' ); ?></button>
					</form><?php else : ?><p><?php esc_html_e( 'Create your first show before adding episodes.', 'nfinite-creators' ); ?></p><?php endif; ?>
				</section>
			</div>
			<?php if ( $shows ) : ?><div class="nfinite-show-manager"><h4><?php esc_html_e( 'Your Shows', 'nfinite-creators' ); ?></h4><?php foreach ( $shows as $show ) : $count = count( self::episodes_for_show( $show->ID, array( 'publish', 'pending', 'draft' ) ) ); ?><div class="nfinite-show-manager__row"><div><strong><?php echo esc_html( get_the_title( $show ) ); ?></strong><small><?php echo esc_html( self::show_types()[ get_post_meta( $show->ID, '_nfinite_show_type', true ) ?: 'series' ] ?? __( 'Series', 'nfinite-creators' ) ); ?> · <?php printf( esc_html__( '%d episodes', 'nfinite-creators' ), absint( $count ) ); ?> · <?php echo esc_html( ucfirst( get_post_status( $show ) ) ); ?></small></div><div><?php if ( 'publish' === get_post_status( $show ) ) : ?><a href="<?php echo esc_url( get_permalink( $show ) ); ?>"><?php esc_html_e( 'View', 'nfinite-creators' ); ?></a><?php endif; ?> <a class="nfinite-studio-delete-link" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'nfinite_creator_delete_show', 'creator_id' => $creator_id, 'show_id' => $show->ID ), admin_url( 'admin-post.php' ) ), 'nfinite_creator_delete_show_' . $show->ID ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this show?', 'nfinite-creators' ) ); ?>');"><?php esc_html_e( 'Delete', 'nfinite-creators' ); ?></a></div></div><?php endforeach; ?></div><?php endif; ?>
			<?php if ( $episodes ) : ?><div class="nfinite-show-manager"><h4><?php esc_html_e( 'Recent Episodes', 'nfinite-creators' ); ?></h4><?php foreach ( array_slice( $episodes, 0, 20 ) as $episode ) : $show_id = absint( get_post_meta( $episode->ID, '_nfinite_episode_show_id', true ) ); ?><div class="nfinite-show-manager__row"><div><strong><?php echo esc_html( get_the_title( $episode ) ); ?></strong><small><?php echo esc_html( $show_id ? get_the_title( $show_id ) : __( 'No show', 'nfinite-creators' ) ); ?> · <?php echo esc_html( ucfirst( get_post_status( $episode ) ) ); ?></small></div><div><?php if ( 'publish' === get_post_status( $episode ) ) : ?><a href="<?php echo esc_url( get_permalink( $episode ) ); ?>"><?php esc_html_e( 'View', 'nfinite-creators' ); ?></a><?php endif; ?> <a class="nfinite-studio-delete-link" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'nfinite_creator_delete_episode', 'creator_id' => $creator_id, 'episode_id' => $episode->ID ), admin_url( 'admin-post.php' ) ), 'nfinite_creator_delete_episode_' . $episode->ID ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this episode?', 'nfinite-creators' ) ); ?>');"><?php esc_html_e( 'Delete', 'nfinite-creators' ); ?></a></div></div><?php endforeach; ?></div><?php endif; ?>
		</div>
		<?php
	}

	public static function creator_shows_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'creator_id' => 0 ), $atts, 'nfinite_creator_shows' );
		$creator_id = absint( $atts['creator_id'] );
		if ( ! $creator_id && is_singular( 'nfinite_creator' ) ) { $creator_id = get_the_ID(); }
		$shows = self::creator_shows( $creator_id, 'publish' );
		if ( ! $shows ) { return ''; }
		ob_start(); ?><div class="nfinite-show-grid"><?php foreach ( $shows as $show ) : ?><article class="nfinite-show-card"><a href="<?php echo esc_url( get_permalink( $show ) ); ?>"><?php if ( has_post_thumbnail( $show ) ) { echo get_the_post_thumbnail( $show, 'large' ); } ?><div><span><?php echo esc_html( self::show_types()[ get_post_meta( $show->ID, '_nfinite_show_type', true ) ?: 'series' ] ?? __( 'Series', 'nfinite-creators' ) ); ?></span><h3><?php echo esc_html( get_the_title( $show ) ); ?></h3><p><?php echo esc_html( get_the_excerpt( $show ) ); ?></p></div></a></article><?php endforeach; ?></div><?php return ob_get_clean();
	}

	public static function template_include( $template ) {
		if ( is_singular( self::SHOW_POST_TYPE ) ) { $custom = NFINITE_CREATORS_DIR . 'templates/shows/single-show.php'; if ( file_exists( $custom ) ) { return $custom; } }
		if ( is_singular( self::EPISODE_POST_TYPE ) ) { $custom = NFINITE_CREATORS_DIR . 'templates/shows/single-episode.php'; if ( file_exists( $custom ) ) { return $custom; } }
		if ( is_post_type_archive( self::SHOW_POST_TYPE ) ) { $custom = NFINITE_CREATORS_DIR . 'templates/shows/archive-shows.php'; if ( file_exists( $custom ) ) { return $custom; } }
		return $template;
	}
}
