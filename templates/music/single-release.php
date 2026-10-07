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

            <?php if ( ! $is_external ) : ?>
                <?php echo Nfinite_Creators_Music_Player::render_release_player( $release_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </div>
    </main>
    <?php
endwhile;

get_footer();
