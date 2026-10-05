<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Music_Player {
    public static function init() {
        add_action( 'wp_footer', array( __CLASS__, 'render_global_player' ), 40 );
        add_shortcode( 'nfinite_release_player', array( __CLASS__, 'release_shortcode' ) );
        add_shortcode( 'nfinite_creator_discography', array( __CLASS__, 'discography_shortcode' ) );
    }

    public static function release_shortcode( $atts ) {
        $atts = shortcode_atts(
            array( 'release' => get_the_ID() ),
            $atts,
            'nfinite_release_player'
        );

        return self::render_release_player( absint( $atts['release'] ) );
    }

    public static function discography_shortcode( $atts ) {
        $atts = shortcode_atts(
            array( 'creator' => get_the_ID(), 'limit' => -1 ),
            $atts,
            'nfinite_creator_discography'
        );

        return self::render_discography( absint( $atts['creator'] ), intval( $atts['limit'] ) );
    }

    public static function release_external_sources( $release_id ) {
        $sources = array();
        $map = array(
            'spotify' => array( '_nfinite_release_spotify', __( 'Spotify', 'nfinite-creators' ) ),
            'apple_music' => array( '_nfinite_release_apple_music', __( 'Apple Music', 'nfinite-creators' ) ),
            'youtube_music' => array( '_nfinite_release_youtube_music', __( 'YouTube Music', 'nfinite-creators' ) ),
        );
        foreach ( $map as $key => $config ) {
            $url = esc_url_raw( get_post_meta( $release_id, $config[0], true ) );
            if ( $url ) { $sources[ $key ] = array( 'label' => $config[1], 'url' => $url ); }
        }
        return $sources;
    }

    public static function render_external_release( $release_id ) {
        $sources = self::release_external_sources( $release_id );
        if ( empty( $sources ) ) { return ''; }

        $primary_key = isset( $sources['spotify'] ) ? 'spotify' : ( isset( $sources['apple_music'] ) ? 'apple_music' : array_key_first( $sources ) );
        $primary = $sources[ $primary_key ];
        $embed = '';
        if ( in_array( $primary_key, array( 'spotify', 'apple_music' ), true ) && class_exists( 'Nfinite_Creators_Audio_Sources' ) ) {
            $embed = Nfinite_Creators_Audio_Sources::embed_url( $primary_key, $primary['url'], false );
        }
        $tracks = Nfinite_Creators_Music_Query::release_tracks( $release_id );
        // A full provider embed already supplies its own browsable track list. Avoid
        // rendering the same release track list twice. Keep Nfinite's track list as
        // the fallback when the provider only gives us an outbound link.
        $has_full_provider_embed = ! empty( $embed ) && in_array( $primary_key, array( 'spotify', 'apple_music' ), true );

        ob_start(); ?>
        <section class="nfinite-external-release" data-nfinite-external-release>
            <div class="nfinite-external-release__head">
                <div>
                    <span class="nfinite-external-release__kicker"><?php esc_html_e( 'Listen externally', 'nfinite-creators' ); ?></span>
                    <h2><?php echo esc_html( sprintf( __( 'Stream on %s', 'nfinite-creators' ), $primary['label'] ) ); ?></h2>
                    <p><?php esc_html_e( 'This release is available through an external music service. Preview and playback stay with the provider and are not added to PairOfDice Radio or your PairOfDice queue.', 'nfinite-creators' ); ?></p>
                </div>
                <a class="nfinite-external-release__open" href="<?php echo esc_url( $primary['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( sprintf( __( 'Open in %s ↗', 'nfinite-creators' ), $primary['label'] ) ); ?></a>
            </div>
            <?php if ( $embed ) : ?>
                <div class="nfinite-external-release__embed nfinite-external-release__embed--<?php echo esc_attr( $primary_key ); ?>">
                    <iframe src="<?php echo esc_url( $embed ); ?>" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" title="<?php echo esc_attr( sprintf( __( '%s player', 'nfinite-creators' ), $primary['label'] ) ); ?>"></iframe>
                </div>
            <?php endif; ?>
            <?php if ( count( $sources ) > 1 ) : ?>
                <div class="nfinite-external-release__services"><span><?php esc_html_e( 'Also available on', 'nfinite-creators' ); ?></span><?php foreach ( $sources as $key => $source ) : if ( $key === $primary_key ) { continue; } ?><a href="<?php echo esc_url( $source['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $source['label'] ); ?> ↗</a><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if ( $tracks && ! $has_full_provider_embed ) : ?>
                <div class="nfinite-external-release__tracks">
                    <div class="nfinite-external-release__tracks-title"><span><?php esc_html_e( 'Tracklist', 'nfinite-creators' ); ?></span><small><?php echo esc_html( count( $tracks ) ); ?> <?php esc_html_e( 'tracks', 'nfinite-creators' ); ?></small></div>
                    <?php foreach ( $tracks as $index => $track ) : ?><div class="nfinite-external-release__track"><span><?php echo esc_html( $index + 1 ); ?></span><strong><?php echo esc_html( get_the_title( $track ) ); ?></strong></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php return ob_get_clean();
    }

    public static function render_release_player( $release_id ) {
        if ( 'nfinite_release' !== get_post_type( $release_id ) ) {
            return '';
        }

        $queue = Nfinite_Creators_Music_Query::queue_for_release( $release_id );
        if ( empty( $queue ) ) {
            return self::render_external_release( $release_id );
        }

        $creator_id = absint( get_post_meta( $release_id, '_nfinite_release_creator_id', true ) );
        $type       = Nfinite_Creators_Music_Query::release_type_label( $release_id );
        $date       = get_post_meta( $release_id, '_nfinite_release_date', true );
        $artwork    = get_the_post_thumbnail_url( $release_id, 'large' );
        $json       = wp_json_encode( $queue );

        ob_start();
        ?>
        <section class="nfinite-release-player" data-nfinite-release-player data-queue="<?php echo esc_attr( $json ); ?>">
            <div class="nfinite-release-player__header">
                <div class="nfinite-release-player__art">
                    <?php if ( $artwork ) : ?><img src="<?php echo esc_url( $artwork ); ?>" alt=""><?php endif; ?>
                </div>
                <div class="nfinite-release-player__meta">
                    <span class="nfinite-eyebrow"><?php echo esc_html( $type ); ?></span>
                    <h2><?php echo esc_html( get_the_title( $release_id ) ); ?></h2>
                    <p>
                        <?php if ( $creator_id ) : ?><a href="<?php echo esc_url( get_permalink( $creator_id ) ); ?>"><?php echo esc_html( get_the_title( $creator_id ) ); ?></a><?php endif; ?>
                        <?php if ( $date ) : ?> · <?php echo esc_html( wp_date( 'Y', strtotime( $date ) ) ); ?><?php endif; ?>
                        · <?php echo esc_html( count( $queue ) ); ?> <?php esc_html_e( 'tracks', 'nfinite-creators' ); ?>
                    </p>
                    <div class="nfinite-release-player__actions">
                        <button type="button" class="nfinite-music-btn nfinite-music-btn--primary" data-play-release><?php esc_html_e( '▶ Play', 'nfinite-creators' ); ?></button>
                        <button type="button" class="nfinite-music-btn" data-shuffle-release><?php esc_html_e( 'Shuffle', 'nfinite-creators' ); ?></button>
                    </div>
                </div>
            </div>

            <div class="nfinite-release-tracklist">
                <?php foreach ( $queue as $index => $track ) : ?>
                    <button type="button" class="nfinite-release-track" data-play-track-index="<?php echo esc_attr( $index ); ?>">
                        <span class="nfinite-release-track__number"><?php echo esc_html( $index + 1 ); ?></span>
                        <span class="nfinite-release-track__main">
                            <strong><?php echo esc_html( $track['title'] ); ?></strong>
                            <small><?php echo esc_html( $track['artist'] ); ?><?php echo $track['explicit'] ? ' · E' : ''; ?></small>
                        </span>
                        <span class="nfinite-release-track__duration"><?php echo esc_html( 'local' === $track['source'] ? ( ! empty( $track['duration'] ) ? $track['duration'] : '' ) : ucwords( str_replace( '_', ' ', $track['source'] ) ) ); ?></span>
                        <span class="nfinite-release-track__play" aria-hidden="true">▶</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function render_discography( $creator_id, $limit = -1 ) {
        if ( 'nfinite_creator' !== get_post_type( $creator_id ) ) {
            return '';
        }

        $releases = Nfinite_Creators_Music_Query::creator_releases( $creator_id, $limit );
        if ( empty( $releases ) ) {
            return '';
        }

        ob_start();
        ?>
        <section class="nfinite-discography">
            <div class="nfinite-discography__head">
                <span class="nfinite-eyebrow"><?php esc_html_e( 'Music', 'nfinite-creators' ); ?></span>
                <h2><?php esc_html_e( 'Discography', 'nfinite-creators' ); ?></h2>
            </div>
            <div class="nfinite-discography__grid">
                <?php foreach ( $releases as $release ) : ?>
                    <?php
                    $artwork = get_the_post_thumbnail_url( $release->ID, 'large' );
                    $type    = Nfinite_Creators_Music_Query::release_type_label( $release->ID );
                    ?>
                    <article class="nfinite-release-card">
                        <a class="nfinite-release-card__art" href="<?php echo esc_url( get_permalink( $release->ID ) ); ?>">
                            <?php if ( $artwork ) : ?><img src="<?php echo esc_url( $artwork ); ?>" alt=""><?php else : ?><span></span><?php endif; ?>
                        </a>
                        <div class="nfinite-release-card__body">
                            <span class="nfinite-eyebrow"><?php echo esc_html( $type ); ?></span>
                            <h3><a href="<?php echo esc_url( get_permalink( $release->ID ) ); ?>"><?php echo esc_html( $release->post_title ); ?></a></h3>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function render_global_player() {
        if ( is_admin() ) {
            return;
        }
        ?>
        <div class="nfinite-global-player" data-nfinite-global-player hidden>
            <audio preload="auto" data-global-audio></audio>

            <button type="button" class="nfinite-global-player__dismiss" data-player-dismiss aria-label="<?php esc_attr_e( 'Stop and close player', 'nfinite-creators' ); ?>" title="<?php esc_attr_e( 'Stop and close player', 'nfinite-creators' ); ?>">×</button>

            <button type="button" class="nfinite-global-player__compact" data-player-expand aria-label="<?php esc_attr_e( 'Open player', 'nfinite-creators' ); ?>">
                <span class="nfinite-global-player__thumb" data-global-art></span>
                <span class="nfinite-global-player__compact-copy">
                    <strong data-global-title><?php esc_html_e( 'Nothing playing', 'nfinite-creators' ); ?></strong>
                    <small data-global-artist></small>
                </span>
                <span class="nfinite-global-player__compact-play" data-global-compact-play>▶</span>
            </button>

            <div class="nfinite-global-player__panel" data-player-panel>
                <div class="nfinite-global-player__now">
                    <div class="nfinite-global-player__art" data-global-art-large></div>
                    <div>
                        <strong data-global-title-large><?php esc_html_e( 'Nothing playing', 'nfinite-creators' ); ?></strong>
                        <span data-global-artist-large></span>
                        <small data-global-release></small>
                    </div>
                </div>

                <div class="nfinite-global-player__provider" data-global-provider hidden>
                    <div class="nfinite-global-player__provider-frame" data-global-provider-frame></div>
                    <a class="nfinite-global-player__provider-link" data-global-provider-link href="#" target="_blank" rel="noopener"></a>
                </div>

                <div class="nfinite-global-player__controls">
                    <div class="nfinite-global-player__buttons">
                        <button type="button" data-global-shuffle aria-label="<?php esc_attr_e( 'Shuffle', 'nfinite-creators' ); ?>">⤨</button>
                        <button type="button" data-global-prev aria-label="<?php esc_attr_e( 'Previous track', 'nfinite-creators' ); ?>">◀</button>
                        <button type="button" class="nfinite-global-player__play" data-global-play aria-label="<?php esc_attr_e( 'Play or pause', 'nfinite-creators' ); ?>">▶</button>
                        <button type="button" data-global-next aria-label="<?php esc_attr_e( 'Next track', 'nfinite-creators' ); ?>">▶</button>
                        <button type="button" data-global-repeat aria-label="<?php esc_attr_e( 'Repeat', 'nfinite-creators' ); ?>">↻</button>
                    </div>
                    <div class="nfinite-global-player__progress">
                        <span data-global-current>0:00</span>
                        <input type="range" min="0" max="100" step="0.1" value="0" data-global-progress aria-label="<?php esc_attr_e( 'Track progress', 'nfinite-creators' ); ?>">
                        <span data-global-duration>0:00</span>
                    </div>
                </div>

                <div class="nfinite-global-player__tools">
                    <label><span aria-hidden="true">🔊</span><input type="range" min="0" max="1" step="0.05" value="1" data-global-volume aria-label="<?php esc_attr_e( 'Volume', 'nfinite-creators' ); ?>"></label>
                    <button type="button" data-global-queue-toggle><?php esc_html_e( 'Up Next', 'nfinite-creators' ); ?></button>
                    <button type="button" data-player-collapse><?php esc_html_e( 'Minimize', 'nfinite-creators' ); ?></button>
                </div>

                <div class="nfinite-global-player__queue" data-global-queue hidden></div>
            </div>
        </div>
        <?php
    }
}
