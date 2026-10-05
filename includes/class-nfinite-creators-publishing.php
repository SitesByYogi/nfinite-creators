<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Creator publishing and portfolio modules.
 *
 * Nfinite owns creator-authored short posts and portfolio/project data. The
 * active theme remains responsible for the broader site/editorial presentation.
 */
class Nfinite_Creators_Publishing {
	const POST_TYPE_POST    = 'nfinite_creator_post';
	const POST_TYPE_PROJECT = 'nfinite_project';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_nfinite_creator_publish_post', array( __CLASS__, 'handle_publish_post' ) );
		add_action( 'admin_post_nfinite_creator_delete_post', array( __CLASS__, 'handle_delete_post' ) );
		add_action( 'admin_post_nfinite_creator_edit_post', array( __CLASS__, 'handle_edit_post' ) );
		add_action( 'admin_post_nfinite_creator_save_project', array( __CLASS__, 'handle_save_project' ) );
		add_action( 'admin_post_nfinite_creator_delete_project', array( __CLASS__, 'handle_delete_project' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 99 );
		add_action( 'wp_ajax_nfinite_toggle_creator_post_like', array( __CLASS__, 'ajax_toggle_like' ) );
		add_action( 'wp_ajax_nopriv_nfinite_toggle_creator_post_like', array( __CLASS__, 'ajax_toggle_like' ) );
		add_action( 'wp_ajax_nfinite_creator_poll_vote', array( __CLASS__, 'ajax_poll_vote' ) );
		add_action( 'wp_ajax_nopriv_nfinite_creator_poll_vote', array( __CLASS__, 'ajax_poll_vote' ) );
		add_action( 'wp_ajax_nfinite_load_creator_posts', array( __CLASS__, 'ajax_load_posts' ) );
		add_action( 'wp_ajax_nfinite_delete_creator_post', array( __CLASS__, 'ajax_delete_post' ) );
		add_action( 'wp_ajax_nopriv_nfinite_load_creator_posts', array( __CLASS__, 'ajax_load_posts' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'init', array( __CLASS__, 'register_wall_rewrite' ), 20 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_wall_rewrite' ), 99 );
		add_filter( 'body_class', array( __CLASS__, 'wall_body_class' ) );
	}



	public static function wall_body_class( $classes ) {
		if ( is_singular( 'nfinite_creator' ) && get_query_var( 'nfinite_creator_wall' ) ) {
			$classes[] = 'nfinite-creator-wall-view';
		}
		return $classes;
	}

	public static function query_vars( $vars ) {
		$vars[] = 'nfinite_creator_wall';
		return $vars;
	}

	public static function register_wall_rewrite() {
		add_rewrite_rule(
			'^creators/([^/]+)/wall/?$',
			'index.php?post_type=nfinite_creator&name=$matches[1]&nfinite_creator_wall=1',
			'top'
		);
	}

	public static function maybe_flush_wall_rewrite() {
		$key = 'nfinite_creator_wall_rewrite_version';
		if ( get_option( $key ) !== NFINITE_CREATORS_VERSION ) {
			flush_rewrite_rules( false );
			update_option( $key, NFINITE_CREATORS_VERSION, false );
		}
	}

	public static function creator_wall_url( $creator_id ) {
		$base = trailingslashit( get_permalink( $creator_id ) );
		return $base . 'wall/';
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE_POST,
			array(
				'labels' => array(
					'name'          => __( 'Creator Posts', 'nfinite-creators' ),
					'singular_name' => __( 'Creator Post', 'nfinite-creators' ),
				),
				'public'              => true,
				'publicly_queryable'   => true,
				'has_archive'          => 'community',
				'rewrite'              => array( 'slug' => 'updates', 'with_front' => false ),
				'show_ui'             => current_user_can( 'manage_options' ),
				'show_in_rest'        => true,
				'exclude_from_search' => false,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'author', 'revisions', 'comments' ),
				'menu_icon'           => 'dashicons-format-status',
			)
		);

		register_post_type(
			self::POST_TYPE_PROJECT,
			array(
				'labels' => array(
					'name'          => __( 'Creator Projects', 'nfinite-creators' ),
					'singular_name' => __( 'Creator Project', 'nfinite-creators' ),
				),
				'public'              => false,
				'show_ui'             => current_user_can( 'manage_options' ),
				'show_in_rest'        => true,
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'author', 'revisions' ),
				'menu_icon'           => 'dashicons-portfolio',
			)
		);
	}

	public static function creator_posts( $creator_id, $limit = 12 ) {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE_POST,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, absint( $limit ) ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_key'       => '_nfinite_creator_id',
				'meta_value'     => absint( $creator_id ),
			)
		);
	}

	public static function creator_projects( $creator_id, $limit = 12 ) {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE_PROJECT,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, absint( $limit ) ),
				'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
				'meta_key'       => '_nfinite_creator_id',
				'meta_value'     => absint( $creator_id ),
			)
		);
	}

	public static function creator_type_slugs( $creator_id ) {
		$terms = wp_get_object_terms( $creator_id, 'nfinite_creator_type', array( 'fields' => 'slugs' ) );
		return is_wp_error( $terms ) ? array() : array_map( 'sanitize_key', $terms );
	}

	public static function profile_mode( $creator_id ) {
		$types = self::creator_type_slugs( $creator_id );
		if ( array_intersect( $types, array( 'designer', 'developer', 'photographer', 'videographer' ) ) ) {
			return 'portfolio';
		}
		if ( array_intersect( $types, array( 'writer', 'blogger', 'journalist', 'podcaster' ) ) ) {
			return 'publisher';
		}
		return 'media';
	}


	public static function render_profile_quick_composer( $creator_id ) {
		$creator_id = absint( $creator_id );
		if ( ! is_user_logged_in() || 'nfinite_creator' !== get_post_type( $creator_id ) ) { return; }
		$can_publish = current_user_can( 'manage_options' ) || (int) get_post_field( 'post_author', $creator_id ) === (int) get_current_user_id();
		if ( ! $can_publish ) { return; }
		?>
		<details class="nfinite-profile-composer"<?php echo isset( $_GET['nfinite_post_published'] ) ? '' : ''; ?>>
			<summary><span class="nfinite-profile-composer__avatar">P</span><span><strong><?php esc_html_e( 'Share something with your audience…', 'nfinite-creators' ); ?></strong><small><?php esc_html_e( 'Post a thought, announcement, link, poll, image, video, or audio.', 'nfinite-creators' ); ?></small></span><span class="nfinite-profile-composer__plus" aria-hidden="true">+</span></summary>
			<form class="nfinite-profile-composer__form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nfinite_creator_publish_post"><input type="hidden" name="creator_id" value="<?php echo esc_attr( $creator_id ); ?>"><input type="hidden" name="publish_source" value="profile">
				<?php wp_nonce_field( 'nfinite_creator_publish_post_' . $creator_id, 'nfinite_publish_nonce' ); ?>
				<div class="nfinite-profile-composer__top"><select name="post_format" aria-label="<?php esc_attr_e( 'Post type', 'nfinite-creators' ); ?>"><option value="update">Update</option><option value="announcement">Announcement</option><option value="link">Link</option><option value="poll">Poll</option><option value="video">Video</option><option value="audio">Audio</option></select><input type="text" name="post_title" maxlength="120" placeholder="<?php esc_attr_e( 'Headline (optional)', 'nfinite-creators' ); ?>"></div>
				<textarea name="post_content" rows="4" maxlength="3000" placeholder="<?php esc_attr_e( 'What’s on your mind?', 'nfinite-creators' ); ?>"></textarea>
				<details class="nfinite-profile-composer__extras"><summary><?php esc_html_e( 'Add media, link or poll', 'nfinite-creators' ); ?></summary><div class="nfinite-profile-composer__extras-grid"><input type="url" name="post_url" placeholder="Link URL"><input type="url" name="post_video_url" placeholder="Video URL"><input type="url" name="post_audio_url" placeholder="Direct audio URL"><label>Image<input type="file" name="post_image" accept="image/*"></label><label>Audio file<input type="file" name="post_audio" accept="audio/*,.mp3,.m4a,.wav,.ogg,.aac"></label><textarea name="poll_options" rows="3" placeholder="Poll options — one per line"></textarea></div></details>
				<div class="nfinite-profile-composer__actions"><a href="<?php echo esc_url( add_query_arg( 'tab', 'publishing', home_url( '/my-creator-profile/' ) ) ); ?>"><?php esc_html_e( 'Open full post tools', 'nfinite-creators' ); ?></a><button class="nfinite-btn nfinite-btn-primary" type="submit"><?php esc_html_e( 'Publish', 'nfinite-creators' ); ?></button></div>
			</form>
		</details>
		<?php
	}

	public static function render_profile_modules( $creator_id ) {
		$mode = self::profile_mode( $creator_id );
		if ( 'portfolio' === $mode ) {
			echo self::render_projects( $creator_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo self::render_posts( $creator_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo self::render_posts( $creator_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo self::render_projects( $creator_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public static function render_posts( $creator_id ) {
		$posts = self::creator_posts( $creator_id, 3 );
		if ( ! $posts ) { return ''; }
		$count = count( $posts );
		$title = 1 === $count ? __( 'Latest Post', 'nfinite-creators' ) : __( 'Latest Posts', 'nfinite-creators' );
		$layout_class = 'nfinite-creator-post-feed--count-' . min( 3, max( 1, $count ) );
		ob_start(); ?>
		<section class="nfinite-creator-section nfinite-creator-updates" aria-labelledby="nfinite-creator-updates-title">
			<header class="nfinite-publishing-section__head"><div><span class="nfinite-eyebrow"><?php esc_html_e( 'From the creator', 'nfinite-creators' ); ?></span><h2 id="nfinite-creator-updates-title"><?php echo esc_html( $title ); ?></h2></div><a href="<?php echo esc_url( self::creator_wall_url( $creator_id ) ); ?>"><?php esc_html_e( 'View full wall', 'nfinite-creators' ); ?> →</a></header>
			<div class="nfinite-creator-post-feed <?php echo esc_attr( $layout_class ); ?>"><?php foreach ( $posts as $index => $post ) { echo self::render_feed_card( $post->ID, false, array( 'preview_position' => $index + 1 ) ); /* phpcs:ignore */ } ?></div>
		</section><?php return ob_get_clean();
	}

	public static function render_projects( $creator_id ) {
		$projects = self::creator_projects( $creator_id );
		if ( ! $projects ) { return ''; }
		ob_start();
		?>
		<section class="nfinite-creator-section nfinite-creator-projects" aria-labelledby="nfinite-creator-projects-title">
			<header class="nfinite-publishing-section__head"><div><span class="nfinite-eyebrow"><?php esc_html_e( 'Selected work', 'nfinite-creators' ); ?></span><h2 id="nfinite-creator-projects-title"><?php esc_html_e( 'Projects & Portfolio', 'nfinite-creators' ); ?></h2></div></header>
			<div class="nfinite-project-grid">
				<?php foreach ( $projects as $project ) :
					$type   = get_post_meta( $project->ID, '_nfinite_project_type', true );
					$client = get_post_meta( $project->ID, '_nfinite_project_client', true );
					$url    = get_post_meta( $project->ID, '_nfinite_project_url', true );
				?>
				<article class="nfinite-project-card">
					<?php if ( has_post_thumbnail( $project->ID ) ) : ?><div class="nfinite-project-card__media"><?php echo get_the_post_thumbnail( $project->ID, 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endif; ?>
					<div class="nfinite-project-card__body">
						<?php if ( $type || $client ) : ?><div class="nfinite-project-card__meta"><?php echo esc_html( implode( ' • ', array_filter( array( $type, $client ) ) ) ); ?></div><?php endif; ?>
						<h3><?php echo esc_html( get_the_title( $project ) ); ?></h3>
						<?php if ( $project->post_content ) : ?><div class="nfinite-project-card__copy"><?php echo wpautop( wp_kses_post( $project->post_content ) ); ?></div><?php endif; ?>
						<?php if ( $url ) : ?><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View project', 'nfinite-creators' ); ?> →</a><?php endif; ?>
					</div>
				</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function render_studio_panels( $creator_id, $creator_owner_id, $is_admin ) {
		if ( ! $creator_id ) { return; }
		$posts    = self::creator_posts( $creator_id, 50 );
		$projects = self::creator_projects( $creator_id, 50 );
		?>
		<div class="nfinite-form-section nfinite-studio-panel" data-studio-panel="publishing">
			<div class="nfinite-form-section__head"><span>P</span><div><h3><?php esc_html_e( 'Posts & Updates', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Publish short updates, announcements, links, images, and ideas directly to your creator profile.', 'nfinite-creators' ); ?></p></div></div>
			<form class="nfinite-publishing-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nfinite_creator_publish_post"><input type="hidden" name="creator_id" value="<?php echo esc_attr( $creator_id ); ?>">
				<?php wp_nonce_field( 'nfinite_creator_publish_post_' . $creator_id, 'nfinite_publish_nonce' ); ?>
				<div class="nfinite-form-grid">
					<label><span><?php esc_html_e( 'Post type', 'nfinite-creators' ); ?></span><select name="post_format"><option value="update"><?php esc_html_e( 'Update', 'nfinite-creators' ); ?></option><option value="announcement"><?php esc_html_e( 'Announcement', 'nfinite-creators' ); ?></option><option value="link"><?php esc_html_e( 'Link', 'nfinite-creators' ); ?></option><option value="project"><?php esc_html_e( 'Project note', 'nfinite-creators' ); ?></option><option value="poll"><?php esc_html_e( 'Poll', 'nfinite-creators' ); ?></option><option value="video"><?php esc_html_e( 'Video', 'nfinite-creators' ); ?></option><option value="audio"><?php esc_html_e( 'Audio', 'nfinite-creators' ); ?></option></select></label>
					<label><span><?php esc_html_e( 'Headline (optional)', 'nfinite-creators' ); ?></span><input type="text" name="post_title" maxlength="120"></label>
					<label class="nfinite-form-full"><span><?php esc_html_e( 'What’s new?', 'nfinite-creators' ); ?></span><textarea required name="post_content" rows="5" maxlength="3000" placeholder="<?php esc_attr_e( 'Share an update with people visiting your profile…', 'nfinite-creators' ); ?>"></textarea></label>
					<label class="nfinite-form-full"><span><?php esc_html_e( 'Poll options (poll posts only)', 'nfinite-creators' ); ?></span><textarea name="poll_options" rows="4" placeholder="Option one&#10;Option two&#10;Option three"></textarea></label>
					<label><span><?php esc_html_e( 'Link (optional)', 'nfinite-creators' ); ?></span><input type="url" name="post_url"></label>
					<label><span><?php esc_html_e( 'Image (optional)', 'nfinite-creators' ); ?></span><input type="file" name="post_image" accept="image/*"></label>
					<label><span><?php esc_html_e( 'Video link (YouTube, Vimeo, or direct video)', 'nfinite-creators' ); ?></span><input type="url" name="post_video_url" placeholder="https://youtube.com/watch?v=…"></label>
					<label><span><?php esc_html_e( 'Audio link (optional)', 'nfinite-creators' ); ?></span><input type="url" name="post_audio_url" placeholder="https://…/track.mp3"></label>
					<label><span><?php esc_html_e( 'Audio file (optional)', 'nfinite-creators' ); ?></span><input type="file" name="post_audio" accept="audio/*,.mp3,.m4a,.wav,.ogg,.aac"></label>
				</div>
				<button class="nfinite-btn nfinite-btn-primary" type="submit"><?php esc_html_e( 'Publish Update', 'nfinite-creators' ); ?></button>
			</form>
			<?php if ( $posts ) : ?>
			<div class="nfinite-publishing-manage"><h4><?php esc_html_e( 'Published updates', 'nfinite-creators' ); ?></h4>
			<?php foreach ( $posts as $post ) :
				$format = get_post_meta( $post->ID, '_nfinite_creator_post_format', true ) ?: 'update';
				$url = get_post_meta( $post->ID, '_nfinite_creator_post_url', true );
				$video_url = get_post_meta( $post->ID, '_nfinite_creator_post_video_url', true );
				$audio_url = get_post_meta( $post->ID, '_nfinite_creator_post_audio_url', true );
				$poll_options = get_post_meta( $post->ID, '_nfinite_poll_options', true );
			?>
			<details class="nfinite-publishing-manage__item">
				<summary class="nfinite-publishing-manage__row"><div><strong><?php echo esc_html( $post->post_title ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 8, '…' ) ); ?></strong><small><?php echo esc_html( get_the_date( '', $post ) ); ?></small></div><span class="nfinite-publishing-manage__actions"><span class="nfinite-studio-edit-link"><?php esc_html_e( 'Edit', 'nfinite-creators' ); ?></span><a class="nfinite-studio-delete-link" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'nfinite_creator_delete_post', 'creator_id' => $creator_id, 'post_id' => $post->ID ), admin_url( 'admin-post.php' ) ), 'nfinite_creator_delete_post_' . $post->ID ) ); ?>" onclick="event.stopPropagation(); return confirm('<?php echo esc_js( __( 'Delete this update?', 'nfinite-creators' ) ); ?>');"><?php esc_html_e( 'Delete', 'nfinite-creators' ); ?></a></span></summary>
				<form class="nfinite-publishing-form nfinite-publishing-edit-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nfinite_creator_edit_post"><input type="hidden" name="creator_id" value="<?php echo esc_attr( $creator_id ); ?>"><input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>">
					<?php wp_nonce_field( 'nfinite_creator_edit_post_' . $post->ID, 'nfinite_edit_nonce' ); ?>
					<div class="nfinite-form-grid">
						<label><span><?php esc_html_e( 'Post type', 'nfinite-creators' ); ?></span><select name="post_format"><?php foreach ( array( 'update'=>'Update','announcement'=>'Announcement','link'=>'Link','project'=>'Project note','poll'=>'Poll','video'=>'Video','audio'=>'Audio' ) as $value=>$label ) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($format,$value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
						<label><span><?php esc_html_e( 'Headline (optional)', 'nfinite-creators' ); ?></span><input type="text" name="post_title" maxlength="120" value="<?php echo esc_attr( $post->post_title ); ?>"></label>
						<label class="nfinite-form-full"><span><?php esc_html_e( 'Post text', 'nfinite-creators' ); ?></span><textarea name="post_content" rows="5" maxlength="3000"><?php echo esc_textarea( $post->post_content ); ?></textarea></label>
						<label class="nfinite-form-full"><span><?php esc_html_e( 'Poll options', 'nfinite-creators' ); ?></span><textarea name="poll_options" rows="4"><?php echo esc_textarea( is_array($poll_options) ? implode("\n",$poll_options) : '' ); ?></textarea></label>
						<label><span><?php esc_html_e( 'Link', 'nfinite-creators' ); ?></span><input type="url" name="post_url" value="<?php echo esc_attr($url); ?>"></label>
						<label><span><?php esc_html_e( 'Replace image', 'nfinite-creators' ); ?></span><input type="file" name="post_image" accept="image/*"></label>
						<label><span><?php esc_html_e( 'Video link', 'nfinite-creators' ); ?></span><input type="url" name="post_video_url" value="<?php echo esc_attr($video_url); ?>"></label>
						<label><span><?php esc_html_e( 'Audio link', 'nfinite-creators' ); ?></span><input type="url" name="post_audio_url" value="<?php echo esc_attr($audio_url); ?>"></label>
						<label><span><?php esc_html_e( 'Replace audio file', 'nfinite-creators' ); ?></span><input type="file" name="post_audio" accept="audio/*,.mp3,.m4a,.wav,.ogg,.aac"></label>
					</div>
					<button class="nfinite-btn nfinite-btn-primary" type="submit"><?php esc_html_e( 'Save Changes', 'nfinite-creators' ); ?></button>
				</form>
			</details>
			<?php endforeach; ?></div><?php endif; ?>
		</div>

		<div class="nfinite-form-section nfinite-studio-panel" data-studio-panel="portfolio">
			<div class="nfinite-form-section__head"><span>W</span><div><h3><?php esc_html_e( 'Portfolio & Projects', 'nfinite-creators' ); ?></h3><p><?php esc_html_e( 'Showcase websites, design work, photography, video projects, client work, campaigns, and other selected work.', 'nfinite-creators' ); ?></p></div></div>
			<form class="nfinite-publishing-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nfinite_creator_save_project"><input type="hidden" name="creator_id" value="<?php echo esc_attr( $creator_id ); ?>">
				<?php wp_nonce_field( 'nfinite_creator_save_project_' . $creator_id, 'nfinite_project_nonce' ); ?>
				<div class="nfinite-form-grid">
					<label><span><?php esc_html_e( 'Project title', 'nfinite-creators' ); ?></span><input required type="text" name="project_title"></label>
					<label><span><?php esc_html_e( 'Project type', 'nfinite-creators' ); ?></span><input type="text" name="project_type" placeholder="<?php esc_attr_e( 'Website, Branding, Photography…', 'nfinite-creators' ); ?>"></label>
					<label><span><?php esc_html_e( 'Client / brand', 'nfinite-creators' ); ?></span><input type="text" name="project_client"></label>
					<label><span><?php esc_html_e( 'Project URL', 'nfinite-creators' ); ?></span><input type="url" name="project_url"></label>
					<label class="nfinite-form-full"><span><?php esc_html_e( 'Project description', 'nfinite-creators' ); ?></span><textarea name="project_content" rows="5"></textarea></label>
					<label><span><?php esc_html_e( 'Project image', 'nfinite-creators' ); ?></span><input type="file" name="project_image" accept="image/*"></label>
				</div>
				<button class="nfinite-btn nfinite-btn-primary" type="submit"><?php esc_html_e( 'Add Project', 'nfinite-creators' ); ?></button>
			</form>
			<?php if ( $projects ) : ?><div class="nfinite-publishing-manage"><h4><?php esc_html_e( 'Portfolio projects', 'nfinite-creators' ); ?></h4><?php foreach ( $projects as $project ) : ?><div class="nfinite-publishing-manage__row"><div><strong><?php echo esc_html( get_the_title( $project ) ); ?></strong><small><?php echo esc_html( get_post_meta( $project->ID, '_nfinite_project_type', true ) ); ?></small></div><a class="nfinite-studio-delete-link" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'nfinite_creator_delete_project', 'creator_id' => $creator_id, 'project_id' => $project->ID ), admin_url( 'admin-post.php' ) ), 'nfinite_creator_delete_project_' . $project->ID ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this project?', 'nfinite-creators' ) ); ?>');"><?php esc_html_e( 'Delete', 'nfinite-creators' ); ?></a></div><?php endforeach; ?></div><?php endif; ?>
		</div>
		<?php
	}


	public static function template_include( $template ) {
		if ( is_singular( 'nfinite_creator' ) && get_query_var( 'nfinite_creator_wall' ) ) {
			$custom = NFINITE_CREATORS_DIR . 'templates/creator-wall.php';
			if ( file_exists( $custom ) ) { return $custom; }
		}
		if ( is_post_type_archive( self::POST_TYPE_POST ) ) {
			$custom = NFINITE_CREATORS_DIR . 'templates/archive-creator-posts.php';
			if ( file_exists( $custom ) ) { return $custom; }
		}
		if ( is_singular( self::POST_TYPE_POST ) ) {
			$custom = NFINITE_CREATORS_DIR . 'templates/single-creator-post.php';
			if ( file_exists( $custom ) ) { return $custom; }
		}
		return $template;
	}

	public static function creator_for_post( $post_id ) {
		return absint( get_post_meta( $post_id, '_nfinite_creator_id', true ) );
	}

	public static function creator_identity( $post_id ) {
		$creator_id = self::creator_for_post( $post_id );
		if ( $creator_id ) {
			$types = wp_get_post_terms( $creator_id, 'nfinite_creator_type', array( 'fields' => 'names' ) );
			return array(
				'id'     => $creator_id,
				'name'   => get_the_title( $creator_id ),
				'url'    => get_permalink( $creator_id ),
				'avatar' => get_the_post_thumbnail( $creator_id, 'thumbnail', array( 'class' => 'nfinite-feed-avatar__img' ) ),
				'type'   => ( ! is_wp_error( $types ) && $types ) ? $types[0] : __( 'Creator', 'nfinite-creators' ),
			);
		}

		// Community posts can also come from ordinary logged-in WordPress users.
		$post = get_post( $post_id );
		$user_id = $post ? absint( $post->post_author ) : 0;
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user ) { return array(); }
		return array(
			'id'     => 0,
			'user_id'=> $user_id,
			'name'   => $user->display_name ?: $user->user_login,
			'url'    => get_author_posts_url( $user_id ),
			'avatar' => get_avatar( $user_id, 96, '', $user->display_name, array( 'class' => 'nfinite-feed-avatar__img' ) ),
			'type'   => __( 'Community Member', 'nfinite-creators' ),
		);
	}

	public static function like_count( $post_id ) { return max( 0, absint( get_post_meta( $post_id, '_nfinite_like_count', true ) ) ); }

	private static function can_manage_feed_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE_POST !== $post->post_type || ! is_user_logged_in() ) { return false; }
		if ( current_user_can( 'manage_options' ) ) { return true; }
		$user_id = (int) get_current_user_id();
		$creator_id = self::creator_for_post( $post_id );
		if ( $creator_id ) { return (int) get_post_field( 'post_author', $creator_id ) === $user_id; }
		return (int) $post->post_author === $user_id;
	}

	public static function ajax_delete_post() {
		check_ajax_referer( 'nfinite_creator_engagement', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! self::can_manage_feed_post( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot remove this post.', 'nfinite-creators' ) ), 403 );
		}
		$post = get_post( $post_id );
		$is_admin = current_user_can( 'manage_options' );
		if ( $is_admin && (int) $post->post_author !== (int) get_current_user_id() ) {
			$log = get_option( 'nfinite_community_moderation_log', array() );
			if ( ! is_array( $log ) ) { $log = array(); }
			$log[] = array( 'post_id' => $post_id, 'post_title' => $post->post_title, 'post_author' => (int) $post->post_author, 'removed_by' => (int) get_current_user_id(), 'removed_at' => current_time( 'mysql' ) );
			if ( count( $log ) > 500 ) { $log = array_slice( $log, -500 ); }
			update_option( 'nfinite_community_moderation_log', $log, false );
		}
		$result = wp_trash_post( $post_id );
		if ( ! $result ) { wp_send_json_error( array( 'message' => __( 'The post could not be removed.', 'nfinite-creators' ) ), 500 ); }
		wp_send_json_success( array( 'post_id' => $post_id, 'redirect' => get_post_type_archive_link( self::POST_TYPE_POST ) ?: home_url( '/community/' ) ) );
	}

	public static function ajax_toggle_like() {
		check_ajax_referer( 'nfinite_creator_engagement', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$liked   = ! empty( $_POST['liked'] );
		if ( self::POST_TYPE_POST !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Post unavailable.', 'nfinite-creators' ) ), 404 );
		}
		$count = self::like_count( $post_id );
		$count = $liked ? $count + 1 : max( 0, $count - 1 );
		update_post_meta( $post_id, '_nfinite_like_count', $count );
		wp_send_json_success( array( 'count' => $count ) );
	}

	public static function render_feed_card( $post_id, $single = false, $args = array() ) {
		$post = get_post( $post_id ); if ( ! $post ) { return ''; }
		$creator = self::creator_identity( $post_id );
		$preview_position = isset( $args['preview_position'] ) ? absint( $args['preview_position'] ) : 0;
		$format  = sanitize_key( get_post_meta( $post_id, '_nfinite_creator_post_format', true ) ?: 'update' );
		$link    = get_post_meta( $post_id, '_nfinite_creator_post_url', true );
		$video_url = get_post_meta( $post_id, '_nfinite_creator_post_video_url', true );
		$audio_url = get_post_meta( $post_id, '_nfinite_creator_post_audio_url', true );
		$audio_id  = absint( get_post_meta( $post_id, '_nfinite_creator_post_audio_id', true ) );
		if ( $audio_id ) { $attached_audio = wp_get_attachment_url( $audio_id ); if ( $attached_audio ) { $audio_url = $attached_audio; } }
		$labels  = array( 'update' => '💬 ' . __( 'Update', 'nfinite-creators' ), 'announcement' => '📣 ' . __( 'Announcement', 'nfinite-creators' ), 'link' => '🔗 ' . __( 'Link', 'nfinite-creators' ), 'project' => '🎨 ' . __( 'Project', 'nfinite-creators' ), 'poll' => '📊 ' . __( 'Poll', 'nfinite-creators' ), 'video' => '▶ ' . __( 'Video', 'nfinite-creators' ), 'audio' => '♫ ' . __( 'Audio', 'nfinite-creators' ) );
		ob_start(); ?>
		<article class="nfinite-feed-post nfinite-feed-post--<?php echo esc_attr( $format ); ?><?php echo $single ? ' is-single' : ''; ?><?php echo $preview_position ? ' nfinite-feed-post--preview-' . esc_attr( $preview_position ) : ''; ?>" data-creator-post="<?php echo esc_attr( $post_id ); ?>">
			<header class="nfinite-feed-post__header">
				<a class="nfinite-feed-avatar" href="<?php echo esc_url( $creator['url'] ?? '#' ); ?>"><?php echo $creator['avatar'] ?: '<span>' . esc_html( strtoupper( substr( $creator['name'] ?? 'C', 0, 1 ) ) ) . '</span>'; // phpcs:ignore ?></a>
				<div class="nfinite-feed-identity"><a href="<?php echo esc_url( $creator['url'] ?? '#' ); ?>"><strong><?php echo esc_html( $creator['name'] ?? __( 'Creator', 'nfinite-creators' ) ); ?></strong></a><div><span><?php echo esc_html( $creator['type'] ?? __( 'Creator', 'nfinite-creators' ) ); ?></span><span aria-hidden="true"> · </span><a class="nfinite-feed-time" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post ) ); ?>"><?php echo esc_html( human_time_diff( get_post_time( 'U', true, $post ), current_time( 'timestamp', true ) ) . ' ' . __( 'ago', 'nfinite-creators' ) ); ?></time></a></div></div>
				<span class="nfinite-feed-type"><?php echo esc_html( $labels[ $format ] ?? $labels['update'] ); ?></span>
				<?php if ( self::can_manage_feed_post( $post_id ) ) : $is_admin_removal = current_user_can( 'manage_options' ) && (int) $post->post_author !== (int) get_current_user_id(); ?>
				<div class="nfinite-feed-manage">
					<button type="button" class="nfinite-feed-manage__toggle" data-nfinite-post-menu aria-expanded="false" aria-label="<?php esc_attr_e( 'Post options', 'nfinite-creators' ); ?>">•••</button>
					<div class="nfinite-feed-manage__menu" data-nfinite-post-menu-panel hidden>
						<button type="button" class="nfinite-feed-manage__delete" data-nfinite-delete-post data-post-id="<?php echo esc_attr( $post_id ); ?>" data-post-title="<?php echo esc_attr( $post->post_title ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 8, '…' ) ); ?>" data-admin-removal="<?php echo $is_admin_removal ? '1' : '0'; ?>"><?php echo $is_admin_removal ? esc_html__( 'Remove post', 'nfinite-creators' ) : esc_html__( 'Delete post', 'nfinite-creators' ); ?></button>
					</div>
				</div>
				<?php endif; ?>
			</header>
			<div class="nfinite-feed-post__content"><?php if ( $post->post_title ) : ?><h2><?php echo esc_html( $post->post_title ); ?></h2><?php endif; ?><?php echo wpautop( wp_kses_post( $post->post_content ) ); ?></div>
			<?php if ( $video_url ) : ?><?php echo self::render_video_media( $video_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php elseif ( $audio_url ) : ?><?php echo self::render_audio_media( $audio_url, $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php elseif ( has_post_thumbnail( $post_id ) ) : ?><a class="nfinite-feed-post__media" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo get_the_post_thumbnail( $post_id, 'large' ); // phpcs:ignore ?></a><?php endif; ?>
			<?php if ( 'poll' === $format ) : echo self::render_poll( $post_id ); endif; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $link ) : ?><a class="nfinite-feed-link-preview" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer"><span><?php esc_html_e( 'Shared link', 'nfinite-creators' ); ?></span><strong><?php echo esc_html( wp_parse_url( $link, PHP_URL_HOST ) ?: $link ); ?></strong><b><?php esc_html_e( 'Open →', 'nfinite-creators' ); ?></b></a><?php endif; ?>
			<footer class="nfinite-feed-actions">
				<button type="button" class="nfinite-feed-action" data-nfinite-like data-post-id="<?php echo esc_attr( $post_id ); ?>" aria-pressed="false"><span aria-hidden="true">♡</span><b data-like-count><?php echo esc_html( self::like_count( $post_id ) ); ?></b><em><?php esc_html_e( 'Like', 'nfinite-creators' ); ?></em></button>
				<a class="nfinite-feed-action" href="<?php echo esc_url( get_permalink( $post_id ) . '#comments' ); ?>"><span aria-hidden="true">💬</span><b><?php echo esc_html( get_comments_number( $post_id ) ); ?></b><em><?php esc_html_e( 'Comment', 'nfinite-creators' ); ?></em></a>
				<button type="button" class="nfinite-feed-action" data-nfinite-share data-share-url="<?php echo esc_url( get_permalink( $post_id ) ); ?>" data-share-title="<?php echo esc_attr( $creator['name'] ?? get_the_title( $post_id ) ); ?>"><span aria-hidden="true">↗</span><em><?php esc_html_e( 'Share', 'nfinite-creators' ); ?></em></button>
			</footer>
			<?php if ( ! $single ) : echo self::render_comment_preview( $post_id ); endif; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</article><?php return ob_get_clean();
	}


	private static function youtube_embed_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) { return ''; }
		$host = strtolower( preg_replace( '/^www\./', '', $parts['host'] ) );
		$id = '';
		if ( 'youtu.be' === $host ) { $id = trim( $parts['path'] ?? '', '/' ); }
		elseif ( in_array( $host, array( 'youtube.com', 'm.youtube.com', 'music.youtube.com' ), true ) ) {
			$path = trim( $parts['path'] ?? '', '/' );
			if ( 'watch' === $path && ! empty( $parts['query'] ) ) { parse_str( $parts['query'], $q ); $id = $q['v'] ?? ''; }
			elseif ( preg_match( '#^(?:shorts|embed)/([^/?]+)#', $path, $m ) ) { $id = $m[1]; }
		}
		$id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $id );
		return $id ? 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0&enablejsapi=1&playsinline=1&origin=' . rawurlencode( home_url() ) : '';
	}

	public static function render_video_media( $url ) {
		$url = esc_url_raw( $url ); if ( ! $url ) { return ''; }
		$embed = self::youtube_embed_url( $url );
		if ( $embed ) { return '<div class="nfinite-feed-video"><iframe src="' . esc_url( $embed ) . '" title="' . esc_attr__( 'Shared video', 'nfinite-creators' ) . '" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>'; }
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( preg_match( '/\.(?:mp4|m4v|webm|ogv|mov)$/i', $path ) ) { return '<div class="nfinite-feed-video"><video controls playsinline preload="metadata" src="' . esc_url( $url ) . '"></video></div>'; }
		$oembed = wp_oembed_get( $url, array( 'width' => 960 ) );
		if ( $oembed ) { return '<div class="nfinite-feed-video nfinite-feed-video--oembed">' . $oembed . '</div>'; }
		return '<a class="nfinite-feed-link-preview" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer"><span>' . esc_html__( 'Shared video', 'nfinite-creators' ) . '</span><strong>' . esc_html( wp_parse_url( $url, PHP_URL_HOST ) ?: $url ) . '</strong><b>' . esc_html__( 'Watch →', 'nfinite-creators' ) . '</b></a>';
	}

	public static function render_audio_media( $url, $post_id = 0 ) {
		$url = esc_url_raw( $url ); if ( ! $url ) { return ''; }
		$title = $post_id ? get_the_title( $post_id ) : '';
		return '<div class="nfinite-feed-audio"><div class="nfinite-feed-audio__mark" aria-hidden="true">♫</div><div class="nfinite-feed-audio__body">' . ( $title ? '<strong>' . esc_html( $title ) . '</strong>' : '<strong>' . esc_html__( 'Shared audio', 'nfinite-creators' ) . '</strong>' ) . '<audio controls preload="metadata" src="' . esc_url( $url ) . '"></audio></div></div>';
	}


	public static function current_user_creator_id() {
		if ( ! is_user_logged_in() ) { return 0; }
		$posts = get_posts( array( 'post_type' => 'nfinite_creator', 'post_status' => array( 'publish', 'pending', 'draft' ), 'author' => get_current_user_id(), 'posts_per_page' => 1, 'fields' => 'ids' ) );
		return $posts ? absint( $posts[0] ) : 0;
	}

	public static function active_creators( $limit = 8 ) {
		$posts = get_posts( array( 'post_type' => self::POST_TYPE_POST, 'post_status' => 'publish', 'posts_per_page' => 60, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids' ) );
		$out = array();
		foreach ( $posts as $post_id ) { $cid = self::creator_for_post( $post_id ); if ( $cid && ! isset( $out[$cid] ) ) { $out[$cid] = self::creator_identity( $post_id ); } if ( count($out) >= $limit ) break; }
		return array_values( $out );
	}

	public static function trending_post_ids( $limit = 30 ) {
		$posts = get_posts( array( 'post_type' => self::POST_TYPE_POST, 'post_status' => 'publish', 'posts_per_page' => 80, 'date_query' => array( array( 'after' => '14 days ago' ) ) ) );
		$scores = array(); $now = current_time( 'timestamp', true );
		foreach ( $posts as $post ) { $hours = max(1, ($now - get_post_time('U', true, $post))/3600); $eng = self::like_count($post->ID) + (get_comments_number($post->ID)*3); $scores[$post->ID] = ($eng + 1) / pow($hours + 2, .55); }
		arsort($scores); return array_slice(array_keys($scores),0,$limit);
	}

	public static function render_comment_preview( $post_id ) {
		$comments = get_comments( array( 'post_id' => $post_id, 'status' => 'approve', 'number' => 1, 'order' => 'DESC' ) );
		if ( ! $comments ) { return ''; }
		$c = $comments[0]; ob_start(); ?>
		<div class="nfinite-feed-comment-preview"><a href="<?php echo esc_url( get_permalink($post_id).'#comments' ); ?>"><strong><?php echo esc_html($c->comment_author); ?></strong> <?php echo esc_html( wp_trim_words(wp_strip_all_tags($c->comment_content),18,'…') ); ?></a><span><?php printf( esc_html__( 'View all %s comments', 'nfinite-creators' ), number_format_i18n(get_comments_number($post_id)) ); ?></span></div>
		<?php return ob_get_clean();
	}

	public static function render_poll( $post_id ) {
		$options = get_post_meta( $post_id, '_nfinite_poll_options', true ); $votes = get_post_meta( $post_id, '_nfinite_poll_votes', true );
		if ( ! is_array($options) || count($options)<2 ) return '';
		if ( ! is_array($votes) ) $votes=array_fill(0,count($options),0); $total=array_sum(array_map('absint',$votes));
		ob_start(); ?><div class="nfinite-feed-poll" data-nfinite-poll data-post-id="<?php echo esc_attr($post_id); ?>"><?php foreach($options as $i=>$option): $count=absint($votes[$i]??0); $pct=$total?round(($count/$total)*100):0; ?><button type="button" class="nfinite-poll-option" data-poll-option="<?php echo esc_attr($i); ?>"><span><?php echo esc_html($option); ?></span><b><?php echo esc_html($pct); ?>%</b><i style="--nfinite-poll-pct:<?php echo esc_attr($pct); ?>%"></i></button><?php endforeach; ?><small><b data-poll-total><?php echo esc_html(number_format_i18n($total)); ?></b> <?php esc_html_e('votes','nfinite-creators'); ?></small></div><?php return ob_get_clean();
	}

	public static function ajax_poll_vote() {
		check_ajax_referer( 'nfinite_creator_engagement', 'nonce' ); $post_id=absint($_POST['post_id']??0); $option=absint($_POST['option']??-1);
		$options=get_post_meta($post_id,'_nfinite_poll_options',true); $votes=get_post_meta($post_id,'_nfinite_poll_votes',true);
		if ( self::POST_TYPE_POST!==get_post_type($post_id) || !is_array($options) || !isset($options[$option]) ) wp_send_json_error(array('message'=>__('Poll unavailable.','nfinite-creators')),404);
		if(!is_array($votes)) $votes=array_fill(0,count($options),0); $votes[$option]=absint($votes[$option]??0)+1; update_post_meta($post_id,'_nfinite_poll_votes',$votes); $total=array_sum($votes); $percentages=array_map(function($v)use($total){return $total?round(($v/$total)*100):0;},$votes); wp_send_json_success(array('votes'=>$votes,'percentages'=>$percentages,'total'=>$total));
	}


	public static function ajax_load_posts() {
		check_ajax_referer( 'nfinite_creator_engagement', 'nonce' );
		$context    = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : 'community';
		$creator_id = isset( $_POST['creator_id'] ) ? absint( $_POST['creator_id'] ) : 0;
		$view       = isset( $_POST['view'] ) && 'trending' === sanitize_key( wp_unslash( $_POST['view'] ) ) ? 'trending' : 'latest';
		$offset     = isset( $_POST['offset'] ) ? max( 0, absint( $_POST['offset'] ) ) : 0;
		$limit      = 10;
		$ids        = array();
		$total      = 0;

		if ( 'creator' === $context ) {
			if ( ! $creator_id || 'nfinite_creator' !== get_post_type( $creator_id ) || 'publish' !== get_post_status( $creator_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Creator unavailable.', 'nfinite-creators' ) ), 404 );
			}
			$count_query = new WP_Query( array(
				'post_type' => self::POST_TYPE_POST, 'post_status' => 'publish', 'posts_per_page' => 1,
				'meta_key' => '_nfinite_creator_id', 'meta_value' => $creator_id, 'fields' => 'ids',
			) );
			$total = (int) $count_query->found_posts;
			$ids = get_posts( array(
				'post_type' => self::POST_TYPE_POST, 'post_status' => 'publish', 'posts_per_page' => $limit,
				'offset' => $offset, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids',
				'meta_key' => '_nfinite_creator_id', 'meta_value' => $creator_id,
			) );
		} elseif ( 'trending' === $view ) {
			$all = self::trending_post_ids( 250 );
			$total = count( $all );
			$ids = array_slice( $all, $offset, $limit );
		} else {
			$q = new WP_Query( array(
				'post_type' => self::POST_TYPE_POST, 'post_status' => 'publish', 'posts_per_page' => $limit,
				'offset' => $offset, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids',
			) );
			$ids = $q->posts;
			$total = (int) $q->found_posts;
		}

		$html = '';
		foreach ( $ids as $id ) { $html .= self::render_feed_card( $id ); }
		$next_offset = $offset + count( $ids );
		wp_send_json_success( array(
			'html' => $html,
			'loaded' => count( $ids ),
			'offset' => $next_offset,
			'total' => $total,
			'hasMore' => $next_offset < $total,
		) );
	}

	private static function authorize_creator( $creator_id ) {
		if ( ! is_user_logged_in() || 'nfinite_creator' !== get_post_type( $creator_id ) ) { return false; }
		return current_user_can( 'manage_options' ) || (int) get_post_field( 'post_author', $creator_id ) === (int) get_current_user_id();
	}

	private static function redirect_back( $creator_id, $tab, $flag ) {
		$url = wp_get_referer() ?: home_url( '/' );
		if ( current_user_can( 'manage_options' ) ) { $url = add_query_arg( 'nfinite_creator_id', absint( $creator_id ), $url ); }
		$url = add_query_arg( $flag, '1', $url );
		wp_safe_redirect( $url . '#nfinite-studio-' . sanitize_key( $tab ) );
		exit;
	}

	private static function handle_upload( $field, $post_id ) {
		if ( empty( $_FILES[ $field ]['name'] ) ) { return 0; }
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = media_handle_upload( $field, $post_id );
		return is_wp_error( $attachment_id ) ? 0 : absint( $attachment_id );
	}

	public static function handle_publish_post() {
		$creator_id = isset( $_POST['creator_id'] ) ? absint( $_POST['creator_id'] ) : 0;
		$source = isset( $_POST['publish_source'] ) ? sanitize_key( wp_unslash( $_POST['publish_source'] ) ) : 'studio';
		if ( ! is_user_logged_in() ) { wp_die( esc_html__( 'You must be logged in to publish.', 'nfinite-creators' ) ); }

		if ( $creator_id ) {
			if ( ! self::authorize_creator( $creator_id ) ) { wp_die( esc_html__( 'You cannot publish for this creator.', 'nfinite-creators' ) ); }
			$nonce_action = 'nfinite_creator_publish_post_' . $creator_id;
		} else {
			$nonce_action = 'nfinite_community_publish_post';
		}
		if ( ! isset( $_POST['nfinite_publish_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_publish_nonce'] ) ), $nonce_action ) ) { wp_die( esc_html__( 'Security check failed.', 'nfinite-creators' ) ); }
		$content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
		$video_candidate = isset( $_POST['post_video_url'] ) ? esc_url_raw( wp_unslash( $_POST['post_video_url'] ) ) : '';
		$audio_candidate = isset( $_POST['post_audio_url'] ) ? esc_url_raw( wp_unslash( $_POST['post_audio_url'] ) ) : '';
		$has_upload = ! empty( $_FILES['post_image']['name'] ) || ! empty( $_FILES['post_audio']['name'] );
		if ( '' === trim( wp_strip_all_tags( $content ) ) && ! $video_candidate && ! $audio_candidate && ! $has_upload ) { wp_die( esc_html__( 'Add a message, image, video, or audio before publishing.', 'nfinite-creators' ) ); }
		$title  = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$format = isset( $_POST['post_format'] ) ? sanitize_key( wp_unslash( $_POST['post_format'] ) ) : 'update';
		if ( ! in_array( $format, array( 'update', 'announcement', 'link', 'project', 'poll', 'video', 'audio' ), true ) ) { $format = 'update'; }
		$post_author = $creator_id ? (int) get_post_field( 'post_author', $creator_id ) : get_current_user_id();
		$post_id = wp_insert_post( array( 'post_type' => self::POST_TYPE_POST, 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $content, 'post_author' => $post_author ), true );
		if ( is_wp_error( $post_id ) ) { wp_die( esc_html( $post_id->get_error_message() ) ); }
		if ( $creator_id ) { update_post_meta( $post_id, '_nfinite_creator_id', $creator_id ); } else { delete_post_meta( $post_id, '_nfinite_creator_id' ); }
		update_post_meta( $post_id, '_nfinite_creator_post_format', $format );
		update_post_meta( $post_id, '_nfinite_creator_post_url', isset( $_POST['post_url'] ) ? esc_url_raw( wp_unslash( $_POST['post_url'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_creator_post_video_url', $video_candidate );
		update_post_meta( $post_id, '_nfinite_creator_post_audio_url', $audio_candidate );
		if ( 'poll' === $format ) {
			$options = isset( $_POST['poll_options'] ) ? preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( wp_unslash( $_POST['poll_options'] ) ) ) : array();
			$options = array_values( array_filter( array_map( 'trim', $options ) ) );
			$options = array_slice( $options, 0, 6 );
			if ( count( $options ) >= 2 ) { update_post_meta( $post_id, '_nfinite_poll_options', $options ); update_post_meta( $post_id, '_nfinite_poll_votes', array_fill( 0, count( $options ), 0 ) ); }
		}
		$image_id = self::handle_upload( 'post_image', $post_id ); if ( $image_id ) { set_post_thumbnail( $post_id, $image_id ); }
		$audio_id = self::handle_upload( 'post_audio', $post_id ); if ( $audio_id ) { update_post_meta( $post_id, '_nfinite_creator_post_audio_id', $audio_id ); }
		if ( 'community' === $source ) {
			$url = wp_get_referer() ?: get_post_type_archive_link( self::POST_TYPE_POST );
			wp_safe_redirect( add_query_arg( 'nfinite_post_published', '1', $url ) . '#community-feed' );
			exit;
		}
		if ( 'profile' === $source ) {
			$url = get_permalink( $creator_id );
			wp_safe_redirect( add_query_arg( 'nfinite_post_published', '1', $url ) . '#nfinite-profile-composer' );
			exit;
		}
		self::redirect_back( $creator_id, 'publishing', 'nfinite_post_published' );
	}


	public static function handle_edit_post() {
		$creator_id = isset( $_POST['creator_id'] ) ? absint( $_POST['creator_id'] ) : 0;
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! self::authorize_creator( $creator_id ) || self::POST_TYPE_POST !== get_post_type( $post_id ) || (int) get_post_meta( $post_id, '_nfinite_creator_id', true ) !== $creator_id ) { wp_die( esc_html__( 'You cannot edit this update.', 'nfinite-creators' ) ); }
		if ( ! isset( $_POST['nfinite_edit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_edit_nonce'] ) ), 'nfinite_creator_edit_post_' . $post_id ) ) { wp_die( esc_html__( 'Security check failed.', 'nfinite-creators' ) ); }
		$title = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
		$format = isset( $_POST['post_format'] ) ? sanitize_key( wp_unslash( $_POST['post_format'] ) ) : 'update';
		if ( ! in_array( $format, array( 'update', 'announcement', 'link', 'project', 'poll', 'video', 'audio' ), true ) ) { $format = 'update'; }
		$result = wp_update_post( array( 'ID' => $post_id, 'post_title' => $title, 'post_content' => $content ), true );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
		update_post_meta( $post_id, '_nfinite_creator_post_format', $format );
		update_post_meta( $post_id, '_nfinite_creator_post_url', isset( $_POST['post_url'] ) ? esc_url_raw( wp_unslash( $_POST['post_url'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_creator_post_video_url', isset( $_POST['post_video_url'] ) ? esc_url_raw( wp_unslash( $_POST['post_video_url'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_creator_post_audio_url', isset( $_POST['post_audio_url'] ) ? esc_url_raw( wp_unslash( $_POST['post_audio_url'] ) ) : '' );
		if ( 'poll' === $format ) {
			$options = isset( $_POST['poll_options'] ) ? preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( wp_unslash( $_POST['poll_options'] ) ) ) : array();
			$options = array_slice( array_values( array_filter( array_map( 'trim', $options ) ) ), 0, 6 );
			$old_options = get_post_meta( $post_id, '_nfinite_poll_options', true );
			if ( count( $options ) >= 2 ) {
				update_post_meta( $post_id, '_nfinite_poll_options', $options );
				if ( $options !== $old_options ) { update_post_meta( $post_id, '_nfinite_poll_votes', array_fill( 0, count( $options ), 0 ) ); delete_post_meta( $post_id, '_nfinite_poll_voters' ); }
			}
		} else { delete_post_meta( $post_id, '_nfinite_poll_options' ); delete_post_meta( $post_id, '_nfinite_poll_votes' ); delete_post_meta( $post_id, '_nfinite_poll_voters' ); }
		$image_id = self::handle_upload( 'post_image', $post_id ); if ( $image_id ) { set_post_thumbnail( $post_id, $image_id ); }
		$audio_id = self::handle_upload( 'post_audio', $post_id ); if ( $audio_id ) { update_post_meta( $post_id, '_nfinite_creator_post_audio_id', $audio_id ); }
		update_post_meta( $post_id, '_nfinite_creator_post_edited', current_time( 'mysql' ) );
		self::redirect_back( $creator_id, 'publishing', 'nfinite_post_updated' );
	}

	public static function handle_delete_post() {
		$creator_id = isset( $_GET['creator_id'] ) ? absint( $_GET['creator_id'] ) : 0; $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( ! self::authorize_creator( $creator_id ) || self::POST_TYPE_POST !== get_post_type( $post_id ) || (int) get_post_meta( $post_id, '_nfinite_creator_id', true ) !== $creator_id ) { wp_die( esc_html__( 'You cannot delete this update.', 'nfinite-creators' ) ); }
		check_admin_referer( 'nfinite_creator_delete_post_' . $post_id ); wp_trash_post( $post_id ); self::redirect_back( $creator_id, 'publishing', 'nfinite_post_deleted' );
	}

	public static function handle_save_project() {
		$creator_id = isset( $_POST['creator_id'] ) ? absint( $_POST['creator_id'] ) : 0;
		if ( ! self::authorize_creator( $creator_id ) ) { wp_die( esc_html__( 'You cannot add projects for this creator.', 'nfinite-creators' ) ); }
		if ( ! isset( $_POST['nfinite_project_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nfinite_project_nonce'] ) ), 'nfinite_creator_save_project_' . $creator_id ) ) { wp_die( esc_html__( 'Security check failed.', 'nfinite-creators' ) ); }
		$title = isset( $_POST['project_title'] ) ? sanitize_text_field( wp_unslash( $_POST['project_title'] ) ) : ''; if ( ! $title ) { wp_die( esc_html__( 'Project title is required.', 'nfinite-creators' ) ); }
		$post_id = wp_insert_post( array( 'post_type' => self::POST_TYPE_PROJECT, 'post_status' => 'publish', 'post_title' => $title, 'post_content' => isset( $_POST['project_content'] ) ? wp_kses_post( wp_unslash( $_POST['project_content'] ) ) : '', 'post_author' => (int) get_post_field( 'post_author', $creator_id ) ), true );
		if ( is_wp_error( $post_id ) ) { wp_die( esc_html( $post_id->get_error_message() ) ); }
		if ( $creator_id ) { update_post_meta( $post_id, '_nfinite_creator_id', $creator_id ); } else { delete_post_meta( $post_id, '_nfinite_creator_id' ); }
		update_post_meta( $post_id, '_nfinite_project_type', isset( $_POST['project_type'] ) ? sanitize_text_field( wp_unslash( $_POST['project_type'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_project_client', isset( $_POST['project_client'] ) ? sanitize_text_field( wp_unslash( $_POST['project_client'] ) ) : '' );
		update_post_meta( $post_id, '_nfinite_project_url', isset( $_POST['project_url'] ) ? esc_url_raw( wp_unslash( $_POST['project_url'] ) ) : '' );
		$image_id = self::handle_upload( 'project_image', $post_id ); if ( $image_id ) { set_post_thumbnail( $post_id, $image_id ); }
		self::redirect_back( $creator_id, 'portfolio', 'nfinite_project_saved' );
	}

	public static function handle_delete_project() {
		$creator_id = isset( $_GET['creator_id'] ) ? absint( $_GET['creator_id'] ) : 0; $project_id = isset( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;
		if ( ! self::authorize_creator( $creator_id ) || self::POST_TYPE_PROJECT !== get_post_type( $project_id ) || (int) get_post_meta( $project_id, '_nfinite_creator_id', true ) !== $creator_id ) { wp_die( esc_html__( 'You cannot delete this project.', 'nfinite-creators' ) ); }
		check_admin_referer( 'nfinite_creator_delete_project_' . $project_id ); wp_trash_post( $project_id ); self::redirect_back( $creator_id, 'portfolio', 'nfinite_project_deleted' );
	}
}
