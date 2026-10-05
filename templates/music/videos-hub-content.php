<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$video_filter = class_exists('Nfinite_Creators_Discovery_Hubs') ? Nfinite_Creators_Discovery_Hubs::current_video_filter() : array();

// Keep an untouched copy of the hub library before selecting editorial modules.
// On the main Videos hub, Featured / Trending / Fresh to Watch are editorial
// placements and should not remove a video from its programming category row.
$programming_videos = $videos;
$featured = ! empty( $videos ) ? array_shift( $videos ) : null;
$trending = array_splice( $videos, 0, 5 );
$latest   = array_splice( $videos, 0, 8 );
$groups   = Nfinite_Creators_Discovery_Hubs::video_types( $video_filter ? $videos : $programming_videos );
$priority = array( 'Podcast', 'News', 'Commentary', 'Opinion', 'Music Video', 'Documentary', 'Movie', 'Live Stream', 'Vlog', 'Interview', 'Performance', 'Freestyle', 'Gaming', 'Short' );
$ordered = array(); foreach ( $priority as $t ) if ( ! empty( $groups[$t] ) ) { $ordered[$t]=$groups[$t]; unset($groups[$t]); } $ordered += $groups;
function nfinite_video_hub_card_031( $video, $compact=false ) {
    $thumb=!empty($video['thumbnail_url'])?$video['thumbnail_url']:Nfinite_Creators_Discovery_Hubs::video_thumbnail($video['url']); $embed=Nfinite_Creators_Discovery_Hubs::video_embed_url($video['url']); $creator_url=!empty($video['creator_url'])?$video['creator_url']:(!empty($video['creator_id'])?get_permalink($video['creator_id']):''); ?>
    <article class="nfinite-network-card<?php echo $compact?' is-compact':''; ?>">
      <?php if ( $compact ) : ?><div class="nfinite-network-card__media-stack"><?php endif; ?>
      <button class="nfinite-network-card__media" type="button" data-video-embed="<?php echo esc_attr($embed); ?>" aria-label="<?php echo esc_attr(sprintf(__('Play %s','nfinite-creators'),$video['title'])); ?>">
        <?php if($thumb): ?><img loading="lazy" src="<?php echo esc_url($thumb); ?>" alt=""><?php else: ?><span class="nfinite-network-card__placeholder"></span><?php endif; ?>
        <span class="nfinite-network-play" aria-hidden="true">▶</span>
      </button>
      <?php if ( $compact ) : ?>
        <div class="nfinite-network-card__compact-source"><?php if($creator_url): ?><a href="<?php echo esc_url($creator_url); ?>"><?php echo esc_html($video['creator_name']); ?></a><?php else: ?><span class="nfinite-network-card__source"><?php echo esc_html($video['creator_name']); ?></span><?php endif; ?></div>
      </div><?php endif; ?>
      <div class="nfinite-network-card__body"><?php if ( ! $compact ) : ?><span class="nfinite-eyebrow"><?php echo esc_html($video['type']); ?></span><?php endif; ?><h3><?php echo esc_html($video['title']); ?></h3><?php if ( ! $compact ) : ?><?php if($creator_url): ?><a href="<?php echo esc_url($creator_url); ?>"><?php echo esc_html($video['creator_name']); ?></a><?php else: ?><span class="nfinite-network-card__source"><?php echo esc_html($video['creator_name']); ?></span><?php endif; ?><?php endif; ?></div>
    </article><?php
}
?>
<div id="video-library" class="nfinite-discovery-hub nfinite-videos-hub nfinite-video-network">
<section class="nfinite-music-hub__intro nfinite-discovery-hub__intro"><div><span class="nfinite-eyebrow"><?php echo esc_html($s['videos_eyebrow']); ?></span><h1><?php echo esc_html($s['videos_title']); ?></h1><p><?php echo esc_html($s['videos_intro']); ?></p></div></section>
<?php if($featured): $thumb=!empty($featured['thumbnail_url'])?$featured['thumbnail_url']:Nfinite_Creators_Discovery_Hubs::video_thumbnail($featured['url']); $embed=Nfinite_Creators_Discovery_Hubs::video_embed_url($featured['url']); $featured_source_url=!empty($featured['creator_url'])?$featured['creator_url']:(!empty($featured['creator_id'])?get_permalink($featured['creator_id']):''); ?>
<section class="nfinite-network-lead">
 <article class="nfinite-network-feature"><button type="button" class="nfinite-network-feature__media" data-video-embed="<?php echo esc_attr($embed); ?>"><?php if($thumb):?><img src="<?php echo esc_url($thumb); ?>" alt=""><?php endif;?><span class="nfinite-network-play">▶</span></button><div class="nfinite-network-feature__body"><span class="nfinite-eyebrow"><?php echo esc_html(sprintf(__('Featured %s','nfinite-creators'),$featured['type'])); ?></span><h2><?php echo esc_html($featured['title']); ?></h2><?php if($featured_source_url): ?><a href="<?php echo esc_url($featured_source_url); ?>"><?php echo esc_html($featured['creator_name']); ?></a><?php else: ?><span class="nfinite-network-card__source"><?php echo esc_html($featured['creator_name']); ?></span><?php endif; ?><?php if($featured['description']):?><p><?php echo esc_html($featured['description']);?></p><?php endif;?></div></article>
 <?php if($trending): ?><aside class="nfinite-network-trending"><span class="nfinite-eyebrow"><?php esc_html_e('Up Next','nfinite-creators');?></span><h2><?php esc_html_e('Trending Now','nfinite-creators');?></h2><?php foreach($trending as $i=>$v): ?><div class="nfinite-network-trending__item"><b><?php echo esc_html(sprintf('%02d',$i+1));?></b><?php nfinite_video_hub_card_031($v,true);?></div><?php endforeach;?></aside><?php endif;?>
</section><?php endif;?>
<?php if($latest): ?><section class="nfinite-network-section"><header><div><span class="nfinite-eyebrow">LATEST</span><h2><?php echo esc_html($video_filter ? $video_filter['label'] : __('Fresh to Watch','nfinite-creators'));?></h2></div></header><div class="nfinite-network-grid"><?php foreach($latest as $v)nfinite_video_hub_card_031($v);?></div></section><?php endif;?>
<?php if($creators): ?><section class="nfinite-network-section nfinite-discovery-creators"><header><div><span class="nfinite-eyebrow"><?php esc_html_e('Discover','nfinite-creators');?></span><h2><?php esc_html_e('Creators to Watch','nfinite-creators');?></h2></div><a href="<?php echo esc_url(get_post_type_archive_link('nfinite_creator'));?>"><?php esc_html_e('Explore Creators →','nfinite-creators');?></a></header><div class="nfinite-creators-grid nfinite-music-hub-creators"><?php foreach($creators as $creator) echo Nfinite_Creators_Frontend::creator_card($creator->ID); ?></div></section><?php endif;?>
<?php
$video_type_labels = array(
    'Podcast' => 'Podcasts',
    'News' => 'News',
    'Commentary' => 'Commentaries',
    'Opinion' => 'Opinions',
    'Music Video' => 'Music Videos',
    'Documentary' => 'Documentaries',
    'Movie' => 'Movies',
    'Live Stream' => 'Live Streams',
    'Vlog' => 'Vlogs',
    'Interview' => 'Interviews',
    'Performance' => 'Performances',
    'Freestyle' => 'Freestyles',
    'Gaming' => 'Gaming',
    'Short' => 'Shorts',
);
foreach($ordered as $type=>$items):
    $label = count($items)>1 ? ($video_type_labels[$type] ?? $type) : $type;
    $id=sanitize_title($video_type_labels[$type] ?? $type); ?><section id="<?php echo esc_attr($id);?>" class="nfinite-network-section"><header><div><span class="nfinite-eyebrow"><?php esc_html_e('Watch','nfinite-creators');?></span><h2><?php echo esc_html($label);?></h2></div></header><div class="nfinite-network-grid"><?php foreach(array_slice($items,0,8) as $v)nfinite_video_hub_card_031($v);?></div></section><?php endforeach;?>
</div>

