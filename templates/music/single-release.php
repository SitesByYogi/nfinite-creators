<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

while ( have_posts() ) :
    the_post();
    $release_id = get_the_ID();
    $creator_id = absint( get_post_meta( $release_id, '_nfinite_release_creator_id', true ) );
    $type       = Nfinite_Creators_Music_Query::release_type_label( $release_id );
    $date       = get_post_meta( $release_id, '_nfinite_release_date', true );
    $internal_queue = Nfinite_Creators_Music_Query::queue_for_release( $release_id );
    $external_sources = Nfinite_Creators_Music_Player::release_external_sources( $release_id );
    $is_external = empty( $internal_queue ) && ! empty( $external_sources );
    ?>
    <main class="nfinite-music-page<?php echo $is_external ? ' nfinite-music-page--external' : ''; ?>">
        <div class="nfinite-creator-shell">
            <div class="nfinite-music-breadcrumb">
                <a href="<?php echo esc_url( get_post_type_archive_link( 'nfinite_release' ) ); ?>"><?php esc_html_e( 'Music', 'nfinite-creators' ); ?></a>
                <?php if ( $creator_id ) : ?> <span>→</span> <a href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>"><?php echo esc_html( get_the_title( $creator_id ) ); ?></a><?php endif; ?>
            </div>

            <section class="nfinite-release-hero">
                <div class="nfinite-release-hero__art">
                    <?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'large' ); endif; ?>
                </div>
                <div class="nfinite-release-hero__copy">
                    <span class="nfinite-eyebrow"><?php echo esc_html( $type ); ?></span>
                    <h1><?php the_title(); ?></h1>
                    <p>
                        <?php if ( $creator_id ) : ?><a href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>"><?php echo esc_html( get_the_title( $creator_id ) ); ?></a><?php endif; ?>
                        <?php if ( $date ) : ?> · <?php echo esc_html( wp_date( 'F j, Y', strtotime( $date ) ) ); ?><?php endif; ?>
                    </p>
                    <div class="nfinite-release-description"><?php the_content(); ?></div>
                    <a class="nfinite-open-player" href="<?php echo esc_url( home_url( '/?pairofdice_player=1#release/' . $release_id ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Open %s in PairOfDice Player', 'nfinite-creators' ), get_the_title() ) ); ?>">▶ <?php esc_html_e( 'Open in PairOfDice Player', 'nfinite-creators' ); ?> <span aria-hidden="true">↗</span></a>
                    <?php if ( $is_external ) : ?>
                        <?php echo Nfinite_Creators_Music_Player::render_external_release( $release_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                </div>
            </section>

            <?php
            $other_releases = $creator_id ? get_posts( array(
                'post_type'      => 'nfinite_release',
                'post_status'    => 'publish',
                'posts_per_page' => 4,
                'post__not_in'   => array( $release_id ),
                'meta_query'     => array( array(
                    'key'     => '_nfinite_release_creator_id',
                    'value'   => $creator_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ) ),
                'orderby' => 'date',
                'order'   => 'DESC',
            ) ) : array();
            ?>
            <?php if ( ! $is_external ) : ?>
                <?php echo Nfinite_Creators_Music_Player::render_release_player( $release_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
            <?php if ( $other_releases ) : ?>
                <section class="nfinite-more-releases" aria-labelledby="nfinite-more-releases-title">
                    <div class="nfinite-more-releases__heading">
                        <h2 id="nfinite-more-releases-title"><?php echo esc_html( sprintf( __( 'More From %s', 'nfinite-creators' ), get_the_title( $creator_id ) ) ); ?></h2>
                        <a href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>"><?php esc_html_e( 'View All Releases', 'nfinite-creators' ); ?> <span aria-hidden="true">↗</span></a>
                    </div>
                    <div class="nfinite-more-releases__rail">
                        <?php foreach ( $other_releases as $other ) : ?>
                            <a class="nfinite-more-releases__card" href="<?php echo esc_url( get_permalink( $other->ID ) ); ?>">
                                <span class="nfinite-more-releases__cover">
                                    <?php if ( has_post_thumbnail( $other->ID ) ) : ?>
                                        <?php echo get_the_post_thumbnail( $other->ID, 'medium_large', array( 'loading' => 'lazy' ) ); ?>
                                    <?php else : ?>
                                        <span class="nfinite-more-releases__placeholder" aria-hidden="true">♫</span>
                                    <?php endif; ?>
                                </span>
                                <strong><?php echo esc_html( get_the_title( $other->ID ) ); ?></strong>
                                <span class="nfinite-more-releases__meta"><?php echo esc_html( Nfinite_Creators_Music_Query::release_type_label( $other->ID ) ); ?><?php $other_date = get_post_meta( $other->ID, '_nfinite_release_date', true ); if ( $other_date && strtotime( $other_date ) ) : ?> · <?php echo esc_html( wp_date( 'Y', strtotime( $other_date ) ) ); ?><?php endif; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>
    <?php
endwhile;

get_footer();
