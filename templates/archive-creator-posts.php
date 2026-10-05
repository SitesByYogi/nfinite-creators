<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$view = isset($_GET['view']) && 'trending' === sanitize_key(wp_unslash($_GET['view'])) ? 'trending' : 'latest';
$args = array('post_type'=>Nfinite_Creators_Publishing::POST_TYPE_POST,'post_status'=>'publish','posts_per_page'=>10,'orderby'=>'date','order'=>'DESC');
if ('trending' === $view) { $ids=Nfinite_Creators_Publishing::trending_post_ids(60); $args['post__in']=$ids?:array(0); $args['orderby']='post__in'; }
$feed = new WP_Query($args);
$creator_id = Nfinite_Creators_Publishing::current_user_creator_id();
$active = Nfinite_Creators_Publishing::active_creators(7);
?>
<main class="nfinite-community-shell nfinite-community-v2">
 <header class="nfinite-community-hero nfinite-community-hero--alive">
  <span class="nfinite-eyebrow">PairOfDice Community</span>
  <h1>What’s happening right now.</h1>
  <p>Music, ideas, projects and conversations directly from the creators building what’s next.</p>
  <nav class="nfinite-community-tabs" aria-label="Community feed views">
   <a class="<?php echo 'latest'===$view?'is-active':''; ?>" href="<?php echo esc_url(get_post_type_archive_link(Nfinite_Creators_Publishing::POST_TYPE_POST)); ?>">Latest</a>
   <a class="<?php echo 'trending'===$view?'is-active':''; ?>" href="<?php echo esc_url(add_query_arg('view','trending',get_post_type_archive_link(Nfinite_Creators_Publishing::POST_TYPE_POST))); ?>">Trending</a>
   <span title="Coming later">Following</span>
  </nav>
 </header>
 <?php if ($active): ?><section class="nfinite-active-creators"><div class="nfinite-community-section-head"><strong>Active creators</strong><a href="<?php echo esc_url(home_url('/creators/')); ?>">Discover all →</a></div><div class="nfinite-active-creators__rail"><?php foreach($active as $creator): ?><a href="<?php echo esc_url($creator['url']); ?>"><span class="nfinite-active-avatar"><?php echo $creator['avatar'] ?: '<b>'.esc_html(strtoupper(substr($creator['name'],0,1))).'</b>'; // phpcs:ignore ?></span><strong><?php echo esc_html($creator['name']); ?></strong><small><?php echo esc_html($creator['type']); ?></small></a><?php endforeach; ?></div></section><?php endif; ?>
 <div class="nfinite-community-layout nfinite-community-layout--alive">
  <aside class="nfinite-community-discover">
   <div class="nfinite-community-mini-card"><span class="nfinite-eyebrow">Discover</span><h2>Find your people.</h2><a href="<?php echo esc_url(home_url('/creators/?type=artist')); ?>">Artists</a><a href="<?php echo esc_url(home_url('/creators/?type=producer')); ?>">Producers</a><a href="<?php echo esc_url(home_url('/creators/?type=designer')); ?>">Designers</a><a href="<?php echo esc_url(home_url('/creators/?type=developer')); ?>">Developers</a><a href="<?php echo esc_url(home_url('/creators/?type=writer')); ?>">Writers</a></div>
  </aside>
  <section class="nfinite-community-feed" id="community-feed" aria-label="Community posts">
   <?php if (is_user_logged_in()):
   $community_user = wp_get_current_user();
   $community_avatar = $creator_id ? get_the_post_thumbnail($creator_id,'thumbnail',array('class'=>'nfinite-feed-avatar__img')) : get_avatar($community_user->ID,96,'',$community_user->display_name,array('class'=>'nfinite-feed-avatar__img'));
   ?>
   <details class="nfinite-community-composer" id="community-composer">
    <summary><span class="nfinite-feed-avatar"><?php echo $community_avatar; // phpcs:ignore ?></span><strong>Share something with the community…</strong><em>＋</em></summary>
    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
     <input type="hidden" name="action" value="nfinite_creator_publish_post"><input type="hidden" name="creator_id" value="<?php echo esc_attr($creator_id); ?>"><input type="hidden" name="publish_source" value="community"><?php $community_nonce_action=$creator_id?'nfinite_creator_publish_post_'.$creator_id:'nfinite_community_publish_post'; wp_nonce_field($community_nonce_action,'nfinite_publish_nonce'); ?>
     <textarea required name="post_content" rows="4" maxlength="3000" placeholder="What’s happening?"></textarea>
     <div class="nfinite-community-composer__options"><select name="post_format"><option value="update">💬 Update</option><option value="announcement">📣 Announcement</option><option value="link">🔗 Link</option><option value="project">🎨 Project</option><option value="poll">📊 Poll</option><option value="video">▶ Video</option><option value="audio">♫ Audio</option></select><input type="url" name="post_url" placeholder="Link (optional)"><input type="file" name="post_image" accept="image/*"></div>
     <textarea name="poll_options" rows="3" placeholder="Poll options, one per line (polls only)"></textarea><button class="nfinite-btn nfinite-btn-primary" type="submit">Post to Community</button>
    </form>
   </details>
   <?php else: ?><div class="nfinite-community-join"><strong>Have something to share?</strong><span>Sign in or join PairOfDice to publish directly into the community.</span><a href="<?php echo esc_url( Nfinite_Creators_Auth::creator_intelligence_signup_url() ); ?>">Join PairOfDice →</a></div><?php endif; ?>
   <?php if ($feed->have_posts()): while($feed->have_posts()): $feed->the_post(); echo Nfinite_Creators_Publishing::render_feed_card(get_the_ID()); endwhile; wp_reset_postdata();
   if ($feed->found_posts > 10): ?><div class="nfinite-load-more-wrap"><button type="button" class="nfinite-load-more-bar" data-nfinite-load-more data-context="community" data-view="<?php echo esc_attr($view); ?>" data-offset="10" data-total="<?php echo esc_attr($feed->found_posts); ?>"><span>Load 10 more posts</span><small><b data-load-count>10</b> of <?php echo esc_html(number_format_i18n($feed->found_posts)); ?> loaded</small></button></div><?php endif;
   else: ?><div class="nfinite-feed-empty"><h2>No community posts yet.</h2><p>Check back as members and creators start sharing.</p></div><?php endif; ?>
  </section>
  <aside class="nfinite-community-rail">
   <div class="nfinite-community-rail__card"><span class="nfinite-eyebrow">Trending now</span><h2>Community pulse</h2><p>Posts rise here when fresh conversations pick up likes and comments.</p><a href="<?php echo esc_url(add_query_arg('view','trending',get_post_type_archive_link(Nfinite_Creators_Publishing::POST_TYPE_POST))); ?>">See what’s moving →</a></div>
   <div class="nfinite-community-rail__card"><span class="nfinite-eyebrow">Explore</span><h2>More than posts.</h2><p>Discover creators, music, beats, projects and events across PairOfDice.</p><a href="<?php echo esc_url(home_url('/creators/')); ?>">Browse creators →</a></div>
  </aside>
 </div>
</main>
<?php get_footer(); ?>
