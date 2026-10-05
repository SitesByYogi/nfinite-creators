<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Music_Meta {
    public static function init() {
        add_action( 'add_meta_boxes_nfinite_release', array( __CLASS__, 'release_boxes' ) );
        add_action( 'add_meta_boxes_nfinite_track', array( __CLASS__, 'track_boxes' ) );
        add_action( 'save_post_nfinite_release', array( __CLASS__, 'save_release' ), 20, 2 );
        add_action( 'save_post_nfinite_track', array( __CLASS__, 'save_track' ), 20, 2 );

        add_filter( 'manage_nfinite_release_posts_columns', array( __CLASS__, 'release_columns' ) );
        add_action( 'manage_nfinite_release_posts_custom_column', array( __CLASS__, 'release_column_content' ), 10, 2 );
        add_filter( 'manage_nfinite_track_posts_columns', array( __CLASS__, 'track_columns' ) );
        add_action( 'manage_nfinite_track_posts_custom_column', array( __CLASS__, 'track_column_content' ), 10, 2 );
    }

    private static function creators() {
        return get_posts( array(
            'post_type' => 'nfinite_creator',
            'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ) );
    }

    private static function releases() {
        return get_posts( array(
            'post_type' => 'nfinite_release',
            'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );
    }

    public static function release_boxes() {
        add_meta_box( 'nfinite_release_details', __( 'Release Details', 'nfinite-creators' ), array( __CLASS__, 'render_release_details' ), 'nfinite_release', 'normal', 'high' );
        add_meta_box( 'nfinite_release_tracks', __( 'Tracklist', 'nfinite-creators' ), array( __CLASS__, 'render_release_tracks' ), 'nfinite_release', 'normal', 'default' );
    }

    public static function track_boxes() {
        add_meta_box( 'nfinite_track_details', __( 'Track Details', 'nfinite-creators' ), array( __CLASS__, 'render_track_details' ), 'nfinite_track', 'normal', 'high' );
    }

    public static function render_release_details( $post ) {
        wp_nonce_field( 'nfinite_release_save', 'nfinite_release_nonce' );

        $creator_id   = absint( get_post_meta( $post->ID, '_nfinite_release_creator_id', true ) );
        $release_date = get_post_meta( $post->ID, '_nfinite_release_date', true );
        $featured     = get_post_meta( $post->ID, '_nfinite_release_featured', true );
        $archive_url  = get_post_meta( $post->ID, '_nfinite_release_internet_archive_item', true );
        if ( ! $archive_url ) { $archive_url = get_post_meta( $post->ID, '_nfinite_release_archive_url', true ); }
        if ( ! $archive_url ) { $archive_url = get_post_meta( $post->ID, '_nfinite_release_internet_archive_url', true ); }
        if ( ! $archive_url ) { $archive_url = get_post_meta( $post->ID, '_nfinite_release_archive', true ); }
        ?>
        <div class="nfinite-admin-grid">
            <div class="nfinite-admin-field">
                <label><?php esc_html_e( 'Creator / Artist', 'nfinite-creators' ); ?></label>
                <select class="widefat" name="_nfinite_release_creator_id">
                    <option value="0"><?php esc_html_e( 'Select creator', 'nfinite-creators' ); ?></option>
                    <?php foreach ( self::creators() as $creator ) : ?>
                        <option value="<?php echo esc_attr( $creator->ID ); ?>" <?php selected( $creator_id, $creator->ID ); ?>><?php echo esc_html( $creator->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="nfinite-admin-field">
                <label><?php esc_html_e( 'Release Date', 'nfinite-creators' ); ?></label>
                <input class="widefat" type="date" name="_nfinite_release_date" value="<?php echo esc_attr( $release_date ); ?>">
            </div>
            <?php foreach ( array(
                '_nfinite_release_spotify' => __( 'Spotify URL', 'nfinite-creators' ),
                '_nfinite_release_apple_music' => __( 'Apple Music URL', 'nfinite-creators' ),
                '_nfinite_release_youtube_music' => __( 'YouTube Music URL', 'nfinite-creators' ),
            ) as $key => $label ) : ?>
                <div class="nfinite-admin-field">
                    <label><?php echo esc_html( $label ); ?></label>
                    <input class="widefat" type="url" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( get_post_meta( $post->ID, $key, true ) ); ?>">
                </div>
            <?php endforeach; ?>
            <div class="nfinite-admin-field nfinite-admin-field--full" data-archive-release-source>
                <label><?php esc_html_e( 'Internet Archive Item', 'nfinite-creators' ); ?></label>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input class="widefat" style="flex:1 1 420px;" type="text" name="_nfinite_release_internet_archive_item" value="<?php echo esc_attr( $archive_url ); ?>" placeholder="https://archive.org/details/item-identifier or item-identifier" data-archive-item-url>
                    <button type="button" class="button button-secondary" data-import-archive-item><?php esc_html_e( 'Import / Refresh Archive Data', 'nfinite-creators' ); ?></button>
                </div>
                <p class="description"><?php esc_html_e( 'Legacy Archive.org release field restored from Nfinite 0.20.5. Existing saved item links are recovered automatically. Paste one item URL/identifier and use Import / Refresh Archive Data to load the full tracklist.', 'nfinite-creators' ); ?></p>
                <label style="display:inline-flex;align-items:center;gap:8px;margin-top:8px;">
                    <input type="checkbox" data-archive-fill-meta checked>
                    <span><?php esc_html_e( 'Fill empty release title/date from Archive metadata', 'nfinite-creators' ); ?></span>
                </label>
                <p class="description" data-archive-import-status style="margin-top:8px;"></p>
            </div>
            <div class="nfinite-admin-field nfinite-admin-field--full">
                <label class="nfinite-admin-toggle">
                    <input type="checkbox" name="_nfinite_release_featured" value="1" <?php checked( $featured, '1' ); ?>>
                    <span><strong><?php esc_html_e( 'Featured Release', 'nfinite-creators' ); ?></strong><br><?php esc_html_e( 'Prioritize this release in WPNfinite music sections.', 'nfinite-creators' ); ?></span>
                </label>
            </div>
        </div>
        <p class="description"><?php esc_html_e( 'Use the Featured Image for cover artwork and Release Types for Album, EP, Mixtape, Single, Compilation, or Playlist.', 'nfinite-creators' ); ?></p>
        <?php
    }

    public static function render_release_tracks( $post ) {
        $ordered_ids = get_post_meta( $post->ID, '_nfinite_release_track_ids', true );

        if ( is_array( $ordered_ids ) && ! empty( $ordered_ids ) ) {
            $tracks = get_posts( array(
                'post_type'      => 'nfinite_track',
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => -1,
                'post__in'       => array_values( array_filter( array_map( 'absint', $ordered_ids ) ) ),
                'orderby'        => 'post__in',
            ) );
        } else {
            $tracks = Nfinite_Creators_Music_Query::release_tracks( $post->ID );
        }

        $all_tracks = get_posts( array(
            'post_type'      => 'nfinite_track',
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        ?>
        <div class="nfinite-release-track-manager" data-release-track-manager>
            <div class="nfinite-release-track-toolbar">
                <button type="button" class="button button-primary button-large" data-add-audio-files>
                    <?php esc_html_e( '+ Add Audio Files', 'nfinite-creators' ); ?>
                </button>
                <button type="button" class="button button-large" data-add-streaming-track>
                    <?php esc_html_e( '+ Add Streaming Track', 'nfinite-creators' ); ?>
                </button>

                <div class="nfinite-release-track-toolbar__existing">
                    <select data-existing-track-select>
                        <option value=""><?php esc_html_e( 'Add existing track…', 'nfinite-creators' ); ?></option>
                        <?php foreach ( $all_tracks as $candidate ) : ?>
                            <?php
                            $candidate_creator = absint( get_post_meta( $candidate->ID, '_nfinite_track_creator_id', true ) );
                            $label = $candidate->post_title;
                            if ( $candidate_creator ) {
                                $label .= ' — ' . get_the_title( $candidate_creator );
                            }
                            ?>
                            <option
                                value="<?php echo esc_attr( $candidate->ID ); ?>"
                                data-title="<?php echo esc_attr( $candidate->post_title ); ?>"
                            ><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button" data-add-existing-track><?php esc_html_e( 'Add', 'nfinite-creators' ); ?></button>
                </div>
            </div>

            <p class="description">
                <?php esc_html_e( 'Add uploaded audio or paste a direct Archive.org/audio, Spotify, Apple Music, or SoundCloud track URL. Nfinite creates/reuses Track records automatically. Drag tracks to set the release order — that order becomes the public track numbering.', 'nfinite-creators' ); ?>
            </p>

            <div class="nfinite-release-quick-link" style="margin:16px 0;padding:16px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7;">
                <strong style="display:block;margin-bottom:10px;"><?php esc_html_e( 'Add Track by URL', 'nfinite-creators' ); ?></strong>
                <div class="nfinite-admin-grid">
                    <div class="nfinite-admin-field">
                        <label><?php esc_html_e( 'Track title', 'nfinite-creators' ); ?></label>
                        <input class="widefat" type="text" placeholder="<?php esc_attr_e( 'Track title', 'nfinite-creators' ); ?>" data-quick-track-title>
                    </div>
                    <div class="nfinite-admin-field">
                        <label><?php esc_html_e( 'Playback source', 'nfinite-creators' ); ?></label>
                        <select class="widefat" data-quick-track-source>
                            <option value="direct_audio"><?php esc_html_e( 'Direct Audio / Archive.org', 'nfinite-creators' ); ?></option>
                            <option value="spotify"><?php esc_html_e( 'Spotify', 'nfinite-creators' ); ?></option>
                            <option value="apple_music"><?php esc_html_e( 'Apple Music', 'nfinite-creators' ); ?></option>
                            <option value="soundcloud"><?php esc_html_e( 'SoundCloud', 'nfinite-creators' ); ?></option>
                        </select>
                    </div>
                    <div class="nfinite-admin-field nfinite-admin-field--full">
                        <label><?php esc_html_e( 'Track URL', 'nfinite-creators' ); ?></label>
                        <input class="widefat" type="url" placeholder="https://archive.org/download/.../track.mp3" data-quick-track-url>
                        <p class="description"><?php esc_html_e( 'For Archive.org, paste the direct .mp3, .m4a, .wav, .ogg, or .aac file URL. For streaming services, paste the normal track URL.', 'nfinite-creators' ); ?></p>
                    </div>
                </div>
                <p style="margin:10px 0 0;"><button type="button" class="button button-primary" data-quick-add-track><?php esc_html_e( 'Add Track to Release', 'nfinite-creators' ); ?></button></p>
            </div>

            <div class="nfinite-release-track-sortable" data-release-track-sortable>
                <?php foreach ( $tracks as $index => $track ) : ?>
                    <?php self::render_release_track_row( $track, $index ); ?>
                <?php endforeach; ?>
            </div>

            <div class="nfinite-release-track-empty<?php echo empty( $tracks ) ? '' : ' is-hidden'; ?>" data-release-track-empty>
                <span class="dashicons dashicons-format-audio"></span>
                <strong><?php esc_html_e( 'No tracks yet', 'nfinite-creators' ); ?></strong>
                <p><?php esc_html_e( 'Add audio files or create a streaming track for this release.', 'nfinite-creators' ); ?></p>
            </div>
        </div>
        <?php
    }

    private static function render_release_track_row( $track, $index ) {
        $track_id      = absint( $track->ID );
        $audio_url     = get_post_meta( $track_id, '_nfinite_track_audio_url', true );
        $archive_audio = get_post_meta( $track_id, '_nfinite_track_internet_archive', true );
        if ( ! $audio_url && $archive_audio ) { $audio_url = $archive_audio; }
        $attachment_id = absint( get_post_meta( $track_id, '_nfinite_track_attachment_id', true ) );
        $creator_id    = absint( get_post_meta( $track_id, '_nfinite_track_creator_id', true ) );
        $credits       = get_post_meta( $track_id, '_nfinite_track_credits', true );
        $source        = Nfinite_Creators_Music_Query::playback_source( $track_id );
        $soundcloud    = get_post_meta( $track_id, '_nfinite_track_soundcloud', true );
        $spotify       = get_post_meta( $track_id, '_nfinite_track_spotify', true );
        $apple         = get_post_meta( $track_id, '_nfinite_track_apple_music', true );
        $youtube       = get_post_meta( $track_id, '_nfinite_track_youtube', true );
        $explicit      = get_post_meta( $track_id, '_nfinite_track_explicit', true );
        $show_discovery = Nfinite_Creators_Music_Query::show_in_track_discovery( $track_id );

        $duration = '';
        if ( $attachment_id ) {
            $metadata = wp_get_attachment_metadata( $attachment_id );
            if ( is_array( $metadata ) && ! empty( $metadata['length_formatted'] ) ) {
                $duration = $metadata['length_formatted'];
            }
        }
        ?>
        <div
            class="nfinite-release-track-admin-row"
            data-release-track-row
            data-track-id="<?php echo esc_attr( $track_id ); ?>"
        >
            <input type="hidden" name="_nfinite_release_track_items[<?php echo esc_attr( $index ); ?>][track_id]" value="<?php echo esc_attr( $track_id ); ?>">
            <input type="hidden" name="_nfinite_release_track_items[<?php echo esc_attr( $index ); ?>][attachment_id]" value="0">

            <div class="nfinite-release-track-admin-row__summary">
                <span class="dashicons dashicons-menu nfinite-release-track-admin-row__handle"></span>

                <span class="nfinite-release-track-admin-row__number" data-release-order><?php echo esc_html( $index + 1 ); ?></span>

                <div class="nfinite-release-track-admin-row__main">
                    <strong data-release-track-title><?php echo esc_html( $track->post_title ); ?></strong>
                    <span>
                        <?php if ( $creator_id ) : echo esc_html( get_the_title( $creator_id ) ); endif; ?>
                        <?php if ( $duration ) : ?> · <?php echo esc_html( $duration ); ?><?php endif; ?>
                    </span>
                </div>

                <button type="button" class="button-link" data-toggle-release-track><?php esc_html_e( 'Edit', 'nfinite-creators' ); ?></button>
                <button type="button" class="button-link-delete" data-remove-release-track><?php esc_html_e( 'Remove', 'nfinite-creators' ); ?></button>
            </div>

            <div class="nfinite-release-track-admin-row__details" data-release-track-details hidden>
                <div class="nfinite-admin-grid">
                    <div class="nfinite-admin-field">
                        <label><?php esc_html_e( 'Title', 'nfinite-creators' ); ?></label>
                        <input class="widefat" type="text" name="_nfinite_release_track_overrides[<?php echo esc_attr( $track_id ); ?>][title]" value="<?php echo esc_attr( $track->post_title ); ?>">
                    </div>

                    <div class="nfinite-admin-field">
                        <label><?php esc_html_e( 'Audio / Direct URL', 'nfinite-creators' ); ?></label>
                        <input class="widefat" type="url" name="_nfinite_release_track_overrides[<?php echo esc_attr( $track_id ); ?>][audio_url]" value="<?php echo esc_attr( $audio_url ); ?>" placeholder="https://archive.org/download/.../track.mp3">
                    </div>

                    <div class="nfinite-admin-field nfinite-admin-field--full">
                        <label><?php esc_html_e( 'Credits / Notes', 'nfinite-creators' ); ?></label>
                        <textarea class="widefat" rows="3" name="_nfinite_release_track_overrides[<?php echo esc_attr( $track_id ); ?>][credits]"><?php echo esc_textarea( $credits ); ?></textarea>
                    </div>

                    <div class="nfinite-admin-field">
                        <label><?php esc_html_e( 'Playback Source', 'nfinite-creators' ); ?></label>
                        <select class="widefat" name="_nfinite_release_track_overrides[<?php echo esc_attr( $track_id ); ?>][source]">
                            <option value="local" <?php selected( $source, 'local' ); ?>><?php esc_html_e( 'Uploaded / Direct Audio URL', 'nfinite-creators' ); ?></option>
                            <option value="soundcloud" <?php selected( $source, 'soundcloud' ); ?>><?php esc_html_e( 'SoundCloud', 'nfinite-creators' ); ?></option>
                            <option value="spotify" <?php selected( $source, 'spotify' ); ?>><?php esc_html_e( 'Spotify', 'nfinite-creators' ); ?></option>
                            <option value="apple_music" <?php selected( $source, 'apple_music' ); ?>><?php esc_html_e( 'Apple Music', 'nfinite-creators' ); ?></option>
                            <option value="internet_archive" <?php selected( $source, 'internet_archive' ); ?>><?php esc_html_e( 'Internet Archive', 'nfinite-creators' ); ?></option>
                        </select>
                    </div>
                    <div class="nfinite-admin-field">
                        <label><?php esc_html_e( 'SoundCloud URL', 'nfinite-creators' ); ?></label>
                        <input class="widefat" type="url" name="_nfinite_release_track_overrides[<?php echo esc_attr( $track_id ); ?>][soundcloud]" value="<?php echo esc_attr( $soundcloud ); ?>">
                    </div>
                    <div class="nfinite-admin-field">
                        <label><?php esc_html_e( 'Spotify URL', 'nfinite-creators' ); ?></label>
                        <input class="widefat" type="url" name="_nfinite_release_track_overrides[<?php echo esc_attr( $track_id ); ?>][spotify]" value="<?php echo esc_attr( $spotify ); ?>">
                    </div>

                    <div class="nfinite-admin-field">
                        <label><?php esc_html_e( 'Apple Music URL', 'nfinite-creators' ); ?></label>
                        <input class="widefat" type="url" name="_nfinite_release_track_overrides[<?php echo esc_attr( $track_id ); ?>][apple]" value="<?php echo esc_attr( $apple ); ?>">
                    </div>

                    <div class="nfinite-admin-field">
                        <label><?php esc_html_e( 'YouTube URL', 'nfinite-creators' ); ?></label>
                        <input class="widefat" type="url" name="_nfinite_release_track_overrides[<?php echo esc_attr( $track_id ); ?>][youtube]" value="<?php echo esc_attr( $youtube ); ?>">
                    </div>

                    <div class="nfinite-admin-field">
                        <label class="nfinite-admin-toggle">
                            <input type="checkbox" name="_nfinite_release_track_overrides[<?php echo esc_attr( $track_id ); ?>][explicit]" value="1" <?php checked( $explicit, '1' ); ?>>
                            <span><strong><?php esc_html_e( 'Explicit', 'nfinite-creators' ); ?></strong></span>
                        </label>
                    </div>

                    <div class="nfinite-admin-field nfinite-admin-field--full">
                        <label class="nfinite-admin-toggle">
                            <input type="checkbox" name="_nfinite_release_track_overrides[<?php echo esc_attr( $track_id ); ?>][show_discovery]" value="1" <?php checked( $show_discovery ); ?>>
                            <span>
                                <strong><?php esc_html_e( 'Show in Latest / Featured Tracks', 'nfinite-creators' ); ?></strong><br>
                                <?php esc_html_e( 'Uncheck to keep this track on its Release but exclude it from global Latest/Featured track discovery players.', 'nfinite-creators' ); ?>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_track_details( $post ) {
        wp_nonce_field( 'nfinite_track_save', 'nfinite_track_nonce' );

        $requested_release = isset( $_GET['nfinite_release_id'] ) ? absint( $_GET['nfinite_release_id'] ) : 0;
        $release_id = absint( get_post_meta( $post->ID, '_nfinite_track_release_id', true ) );
        if ( ! $release_id && $requested_release ) { $release_id = $requested_release; }

        $creator_id = absint( get_post_meta( $post->ID, '_nfinite_track_creator_id', true ) );
        if ( ! $creator_id && $release_id ) {
            $creator_id = absint( get_post_meta( $release_id, '_nfinite_release_creator_id', true ) );
        }

        $audio_url  = get_post_meta( $post->ID, '_nfinite_track_audio_url', true );
        $source     = Nfinite_Creators_Music_Query::playback_source( $post->ID );
        $product_id = absint( get_post_meta( $post->ID, '_nfinite_track_product_id', true ) );
        $buy_url    = get_post_meta( $post->ID, '_nfinite_track_buy_url', true );
        $buy_label  = get_post_meta( $post->ID, '_nfinite_track_buy_label', true );
        $show_price = get_post_meta( $post->ID, '_nfinite_track_show_price', true );
        $show_discovery = Nfinite_Creators_Music_Query::show_in_track_discovery( $post->ID );
        $track_format = sanitize_key( get_post_meta( $post->ID, '_nfinite_track_format', true ) );
        if ( ! in_array( $track_format, array( 'song', 'beat', 'instrumental' ), true ) ) { $track_format = 'song'; }
        $products   = class_exists( 'Nfinite_Creators_Track_Commerce' )
            ? Nfinite_Creators_Track_Commerce::products()
            : array();
        ?>
        <div class="nfinite-admin-grid">
            <div class="nfinite-admin-field">
                <label><?php esc_html_e( 'Creator / Artist', 'nfinite-creators' ); ?></label>
                <select class="widefat" name="_nfinite_track_creator_id">
                    <option value="0"><?php esc_html_e( 'Select creator', 'nfinite-creators' ); ?></option>
                    <?php foreach ( self::creators() as $creator ) : ?>
                        <option value="<?php echo esc_attr( $creator->ID ); ?>" <?php selected( $creator_id, $creator->ID ); ?>><?php echo esc_html( $creator->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="nfinite-admin-field">
                <label><?php esc_html_e( 'Release', 'nfinite-creators' ); ?></label>
                <select class="widefat" name="_nfinite_track_release_id">
                    <option value="0"><?php esc_html_e( 'Standalone / no release', 'nfinite-creators' ); ?></option>
                    <?php foreach ( self::releases() as $release ) : ?>
                        <option value="<?php echo esc_attr( $release->ID ); ?>" <?php selected( $release_id, $release->ID ); ?>><?php echo esc_html( $release->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="nfinite-admin-field">
                <label><?php esc_html_e( 'Track Format', 'nfinite-creators' ); ?></label>
                <select class="widefat" name="_nfinite_track_format">
                    <option value="song" <?php selected( $track_format, 'song' ); ?>><?php esc_html_e( 'Song / Recording', 'nfinite-creators' ); ?></option>
                    <option value="beat" <?php selected( $track_format, 'beat' ); ?>><?php esc_html_e( 'Beat', 'nfinite-creators' ); ?></option>
                    <option value="instrumental" <?php selected( $track_format, 'instrumental' ); ?>><?php esc_html_e( 'Instrumental', 'nfinite-creators' ); ?></option>
                </select>
                <p class="description"><?php esc_html_e( 'Controls whether this track can appear in Beats & Instrumentals. Producer credits or words in the title are not used for classification.', 'nfinite-creators' ); ?></p>
            </div>
            <div class="nfinite-admin-field nfinite-admin-field--full">
                <label><?php esc_html_e( 'Playback Source', 'nfinite-creators' ); ?></label>
                <select class="widefat" name="_nfinite_track_playback_source">
                    <option value="local" <?php selected( $source, 'local' ); ?>><?php esc_html_e( 'Uploaded / Direct Audio URL', 'nfinite-creators' ); ?></option>
                    <option value="soundcloud" <?php selected( $source, 'soundcloud' ); ?>><?php esc_html_e( 'SoundCloud', 'nfinite-creators' ); ?></option>
                    <option value="spotify" <?php selected( $source, 'spotify' ); ?>><?php esc_html_e( 'Spotify', 'nfinite-creators' ); ?></option>
                    <option value="apple_music" <?php selected( $source, 'apple_music' ); ?>><?php esc_html_e( 'Apple Music', 'nfinite-creators' ); ?></option>
                            <option value="internet_archive" <?php selected( $source, 'internet_archive' ); ?>><?php esc_html_e( 'Internet Archive', 'nfinite-creators' ); ?></option>
                </select>
                <p class="description"><?php esc_html_e( 'Uploaded audio uses the Nfinite player. External sources use the streaming provider’s official web player.', 'nfinite-creators' ); ?></p>
            </div>
            <div class="nfinite-admin-field nfinite-admin-field--full">
                <label><?php esc_html_e( 'Audio File URL', 'nfinite-creators' ); ?></label>
                <input class="widefat" data-nfinite-track-audio-url type="url" name="_nfinite_track_audio_url" value="<?php echo esc_attr( $audio_url ); ?>">
                <p><button type="button" class="button" data-nfinite-select-track-audio><?php esc_html_e( 'Select Audio from Media Library', 'nfinite-creators' ); ?></button></p>
            </div>
            <?php foreach ( array(
                '_nfinite_track_soundcloud' => __( 'SoundCloud URL', 'nfinite-creators' ),
                '_nfinite_track_spotify' => __( 'Spotify URL', 'nfinite-creators' ),
                '_nfinite_track_apple_music' => __( 'Apple Music URL', 'nfinite-creators' ),
                '_nfinite_track_youtube' => __( 'YouTube URL', 'nfinite-creators' ),
            ) as $key => $label ) : ?>
                <div class="nfinite-admin-field">
                    <label><?php echo esc_html( $label ); ?></label>
                    <input class="widefat" type="url" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( get_post_meta( $post->ID, $key, true ) ); ?>">
                </div>
            <?php endforeach; ?>
            <div class="nfinite-admin-field nfinite-admin-field--full nfinite-admin-commerce">
                <h3><?php esc_html_e( 'Track Commerce', 'nfinite-creators' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Connect this track to a WooCommerce product, or use an external Buy URL. The WooCommerce product takes priority when both are supplied.', 'nfinite-creators' ); ?></p>
            </div>
            <div class="nfinite-admin-field">
                <label><?php esc_html_e( 'WooCommerce Product', 'nfinite-creators' ); ?></label>
                <select class="widefat wc-enhanced-select" name="_nfinite_track_product_id">
                    <option value="0"><?php esc_html_e( 'No product selected', 'nfinite-creators' ); ?></option>
                    <?php foreach ( $products as $product ) : ?>
                        <option value="<?php echo esc_attr( $product->get_id() ); ?>" <?php selected( $product_id, $product->get_id() ); ?>>
                            <?php echo esc_html( $product->get_name() ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="nfinite-admin-field">
                <label><?php esc_html_e( 'External Buy URL', 'nfinite-creators' ); ?></label>
                <input class="widefat" type="url" name="_nfinite_track_buy_url" value="<?php echo esc_attr( $buy_url ); ?>" placeholder="https://...">
            </div>
            <div class="nfinite-admin-field">
                <label><?php esc_html_e( 'Button Label', 'nfinite-creators' ); ?></label>
                <input class="widefat" type="text" name="_nfinite_track_buy_label" value="<?php echo esc_attr( $buy_label ); ?>" placeholder="<?php esc_attr_e( 'Buy Now', 'nfinite-creators' ); ?>">
            </div>
            <div class="nfinite-admin-field">
                <label class="nfinite-admin-toggle">
                    <input type="checkbox" name="_nfinite_track_show_price" value="1" <?php checked( $show_price, '1' ); ?>>
                    <span><strong><?php esc_html_e( 'Show product price', 'nfinite-creators' ); ?></strong><br><?php esc_html_e( 'Pull the current WooCommerce price into public track and beat cards.', 'nfinite-creators' ); ?></span>
                </label>
            </div>

            <div class="nfinite-admin-field nfinite-admin-field--full">
                <label><?php esc_html_e( 'Credits', 'nfinite-creators' ); ?></label>
                <textarea class="widefat" rows="4" name="_nfinite_track_credits"><?php echo esc_textarea( get_post_meta( $post->ID, '_nfinite_track_credits', true ) ); ?></textarea>
            </div>
            <div class="nfinite-admin-field nfinite-admin-field--full">
                <label><?php esc_html_e( 'Lyrics', 'nfinite-creators' ); ?></label>
                <textarea class="widefat" rows="7" name="_nfinite_track_lyrics"><?php echo esc_textarea( get_post_meta( $post->ID, '_nfinite_track_lyrics', true ) ); ?></textarea>
            </div>
            <div class="nfinite-admin-field nfinite-admin-field--full">
                <label class="nfinite-admin-toggle">
                    <input type="checkbox" name="_nfinite_track_show_discovery" value="1" <?php checked( $show_discovery ); ?>>
                    <span>
                        <strong><?php esc_html_e( 'Show in Latest / Featured Tracks', 'nfinite-creators' ); ?></strong><br>
                        <?php esc_html_e( 'Uncheck to keep this track available on its Release while excluding it from global track discovery players.', 'nfinite-creators' ); ?>
                    </span>
                </label>
            </div>
            <div class="nfinite-admin-field nfinite-admin-field--full">
                <label class="nfinite-admin-toggle"><input type="checkbox" name="_nfinite_track_explicit" value="1" <?php checked( get_post_meta( $post->ID, '_nfinite_track_explicit', true ), '1' ); ?>><span><strong><?php esc_html_e( 'Explicit', 'nfinite-creators' ); ?></strong></span></label>
            </div>
        </div>
        <p class="description"><?php esc_html_e( 'Use the Featured Image only when this track needs artwork different from its Release.', 'nfinite-creators' ); ?></p>
        <?php
    }

    private static function allowed_save( $nonce_field, $action, $post_id ) {
        return isset( $_POST[ $nonce_field ] )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $action )
            && ( ! defined( 'DOING_AUTOSAVE' ) || ! DOING_AUTOSAVE )
            && current_user_can( 'edit_post', $post_id );
    }

    public static function save_release( $post_id, $post ) {
        if ( ! self::allowed_save( 'nfinite_release_nonce', 'nfinite_release_save', $post_id ) ) {
            return;
        }

        $creator_id = isset( $_POST['_nfinite_release_creator_id'] ) ? absint( $_POST['_nfinite_release_creator_id'] ) : 0;

        update_post_meta( $post_id, '_nfinite_release_creator_id', $creator_id );
        update_post_meta( $post_id, '_nfinite_release_date', isset( $_POST['_nfinite_release_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_nfinite_release_date'] ) ) : '' );

        foreach ( array( '_nfinite_release_spotify', '_nfinite_release_apple_music', '_nfinite_release_youtube_music' ) as $key ) {
            update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : '' );
        }

        $old_archive_url = (string) get_post_meta( $post_id, '_nfinite_release_internet_archive_item', true );
        if ( ! $old_archive_url ) { $old_archive_url = (string) get_post_meta( $post_id, '_nfinite_release_archive_url', true ); }
        $archive_input = isset( $_POST['_nfinite_release_internet_archive_item'] ) ? trim( (string) wp_unslash( $_POST['_nfinite_release_internet_archive_item'] ) ) : '';
        $archive_identifier = Nfinite_Creators_Internet_Archive::identifier_from_input( $archive_input );
        $archive_url = $archive_identifier ? 'https://archive.org/details/' . rawurlencode( $archive_identifier ) : '';
        update_post_meta( $post_id, '_nfinite_release_internet_archive_item', $archive_url );
        update_post_meta( $post_id, '_nfinite_release_archive_url', $archive_url );

        // Preserve compatibility with older builds that used alternate Archive.org meta keys.
        if ( $archive_url ) {
            update_post_meta( $post_id, '_nfinite_release_archive', $archive_url );
            update_post_meta( $post_id, '_nfinite_release_internet_archive_url', $archive_url );
            update_post_meta( $post_id, '_nfinite_release_internet_archive_item', $archive_url );
        }

        update_post_meta( $post_id, '_nfinite_release_featured', isset( $_POST['_nfinite_release_featured'] ) ? '1' : '' );

        $items     = isset( $_POST['_nfinite_release_track_items'] ) && is_array( $_POST['_nfinite_release_track_items'] )
            ? wp_unslash( $_POST['_nfinite_release_track_items'] )
            : array();
        $track_ids = array();

        foreach ( $items as $position => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $track_id      = isset( $item['track_id'] ) ? absint( $item['track_id'] ) : 0;
            $attachment_id = isset( $item['attachment_id'] ) ? absint( $item['attachment_id'] ) : 0;

            if ( ! $track_id && $attachment_id ) {
                $track_id = self::track_from_attachment(
                    $attachment_id,
                    $creator_id,
                    isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : ''
                );
            }

            if ( ! $track_id && ! empty( $item['external_source'] ) && ! empty( $item['source_url'] ) ) {
                $track_id = self::track_from_external(
                    isset( $item['external_source'] ) ? sanitize_key( $item['external_source'] ) : '',
                    isset( $item['source_url'] ) ? esc_url_raw( $item['source_url'] ) : '',
                    $creator_id,
                    isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
                    array(
                        'identifier' => isset( $item['archive_identifier'] ) ? sanitize_text_field( $item['archive_identifier'] ) : '',
                        'filename'   => isset( $item['archive_filename'] ) ? sanitize_text_field( $item['archive_filename'] ) : '',
                    )
                );
            }

            if ( ! $track_id || 'nfinite_track' !== get_post_type( $track_id ) ) {
                continue;
            }

            $track_ids[] = $track_id;

            /*
             * Track numbering is derived from this Release's order.
             * This legacy meta remains useful for old integrations, but the
             * public Release player uses queue position so reusable Tracks can
             * have different positions on different Releases/Playlists.
             */
            update_post_meta( $track_id, '_nfinite_track_number', count( $track_ids ) );

            if ( $creator_id && ! get_post_meta( $track_id, '_nfinite_track_creator_id', true ) ) {
                update_post_meta( $track_id, '_nfinite_track_creator_id', $creator_id );
            }

            if ( ! get_post_meta( $track_id, '_nfinite_track_release_id', true ) ) {
                update_post_meta( $track_id, '_nfinite_track_release_id', $post_id );
            }
        }

        $track_ids = array_values( array_unique( array_map( 'absint', $track_ids ) ) );
        update_post_meta( $post_id, '_nfinite_release_track_ids', $track_ids );

        self::save_release_track_overrides();

        if ( $archive_url ) {
            $refresh_requested = ! empty( $_POST['_nfinite_release_archive_refresh'] );
            $archive_changed   = untrailingslashit( $archive_url ) !== untrailingslashit( $old_archive_url );
            $needs_repair      = ! self::release_has_playable_tracks( $post_id );

            if ( $archive_changed || $refresh_requested || $needs_repair ) {
                self::sync_archive_release( $post_id, $archive_url, $creator_id );
            }
        }
    }

    private static function release_has_playable_tracks( $release_id ) {
        foreach ( Nfinite_Creators_Music_Query::release_tracks( $release_id ) as $track ) {
            if ( Nfinite_Creators_Music_Query::source_url( $track->ID ) ) {
                return true;
            }
        }
        return false;
    }

    private static function archive_identifier_from_url( $url ) {
        $url = trim( (string) $url );
        if ( ! $url ) { return ''; }

        $parts = wp_parse_url( $url );
        $host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
        $path  = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';
        if ( false === strpos( $host, 'archive.org' ) || ! $path ) { return ''; }

        $segments = array_values( array_filter( explode( '/', $path ) ) );
        if ( count( $segments ) >= 2 && in_array( $segments[0], array( 'details', 'download', 'metadata' ), true ) ) {
            return sanitize_text_field( rawurldecode( $segments[1] ) );
        }
        return '';
    }

    private static function archive_audio_files( $metadata ) {
        if ( empty( $metadata['files'] ) || ! is_array( $metadata['files'] ) ) { return array(); }

        $audio_exts = array( 'mp3', 'm4a', 'aac', 'ogg', 'oga', 'wav', 'flac' );
        $candidates = array();
        foreach ( $metadata['files'] as $file ) {
            if ( ! is_array( $file ) || empty( $file['name'] ) ) { continue; }
            $name = (string) $file['name'];
            $ext = strtolower( pathinfo( wp_parse_url( $name, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, $audio_exts, true ) ) { continue; }

            $format = isset( $file['format'] ) ? strtolower( (string) $file['format'] ) : '';
            if ( false !== strpos( $format, 'spectrogram' ) || false !== strpos( $format, 'metadata' ) ) { continue; }

            $source = isset( $file['source'] ) ? strtolower( (string) $file['source'] ) : '';
            $priority = 'original' === $source ? 0 : 1;
            $track = 0;
            if ( isset( $file['track'] ) && preg_match( '/\d+/', (string) $file['track'], $m ) ) { $track = absint( $m[0] ); }
            if ( ! $track && preg_match( '/^\s*(\d{1,3})[ ._-]+/', rawurldecode( basename( $name ) ), $m ) ) { $track = absint( $m[1] ); }

            $file['_nfinite_priority'] = $priority;
            $file['_nfinite_track_no'] = $track;
            $candidates[] = $file;
        }

        // Prefer original files when Archive.org exposes both originals and derivatives for the same song.
        $deduped = array();
        foreach ( $candidates as $file ) {
            $base = strtolower( preg_replace( '/\.(mp3|m4a|aac|ogg|oga|wav|flac)$/i', '', rawurldecode( basename( $file['name'] ) ) ) );
            $base = preg_replace( '/(?:_vbr|_64kb|_128kb|_256kb|_320kb)$/i', '', $base );
            if ( ! isset( $deduped[ $base ] ) || $file['_nfinite_priority'] < $deduped[ $base ]['_nfinite_priority'] ) {
                $deduped[ $base ] = $file;
            }
        }

        $files = array_values( $deduped );
        usort( $files, function( $a, $b ) {
            $ta = ! empty( $a['_nfinite_track_no'] ) ? absint( $a['_nfinite_track_no'] ) : 99999;
            $tb = ! empty( $b['_nfinite_track_no'] ) ? absint( $b['_nfinite_track_no'] ) : 99999;
            if ( $ta !== $tb ) { return $ta <=> $tb; }
            return strnatcasecmp( (string) $a['name'], (string) $b['name'] );
        } );
        return $files;
    }

    private static function archive_file_url( $identifier, $name ) {
        $segments = array_map( 'rawurlencode', explode( '/', ltrim( (string) $name, '/' ) ) );
        return 'https://archive.org/download/' . rawurlencode( $identifier ) . '/' . implode( '/', $segments );
    }

    private static function archive_track_title( $file ) {
        foreach ( array( 'title', 'tracktitle' ) as $key ) {
            if ( ! empty( $file[ $key ] ) && is_scalar( $file[ $key ] ) ) { return sanitize_text_field( (string) $file[ $key ] ); }
        }
        $name = rawurldecode( pathinfo( basename( (string) $file['name'] ), PATHINFO_FILENAME ) );
        $name = preg_replace( '/^\s*\d{1,3}[ ._-]+/', '', $name );
        $name = preg_replace( '/[_]+/', ' ', $name );
        return sanitize_text_field( trim( $name ) );
    }

    private static function sync_archive_release( $release_id, $archive_url, $creator_id = 0 ) {
        $identifier = self::archive_identifier_from_url( $archive_url );
        if ( ! $identifier ) {
            update_post_meta( $release_id, '_nfinite_release_archive_sync_error', __( 'Could not read the Archive.org identifier from that URL.', 'nfinite-creators' ) );
            return false;
        }

        $response = wp_remote_get( 'https://archive.org/metadata/' . rawurlencode( $identifier ), array( 'timeout' => 20, 'redirection' => 3 ) );
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            update_post_meta( $release_id, '_nfinite_release_archive_sync_error', __( 'Archive.org metadata could not be loaded. Try Update again in a moment.', 'nfinite-creators' ) );
            return false;
        }

        $metadata = json_decode( wp_remote_retrieve_body( $response ), true );
        $files = is_array( $metadata ) ? self::archive_audio_files( $metadata ) : array();
        if ( ! $files ) {
            update_post_meta( $release_id, '_nfinite_release_archive_sync_error', __( 'No playable audio files were found in that Archive.org item.', 'nfinite-creators' ) );
            return false;
        }

        $existing_ids = get_post_meta( $release_id, '_nfinite_release_track_ids', true );
        if ( ! is_array( $existing_ids ) ) { $existing_ids = array(); }
        $existing_ids = array_values( array_filter( array_map( 'absint', $existing_ids ) ) );
        $by_title = array();
        foreach ( $existing_ids as $existing_id ) {
            $key = sanitize_title( get_the_title( $existing_id ) );
            if ( $key ) { $by_title[ $key ] = $existing_id; }
        }

        $track_ids = array();
        foreach ( $files as $index => $file ) {
            $name = (string) $file['name'];
            $url  = self::archive_file_url( $identifier, $name );
            $title = self::archive_track_title( $file );
            $track_id = 0;

            $matches = get_posts( array(
                'post_type' => 'nfinite_track',
                'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => array(
                    'relation' => 'AND',
                    array( 'key' => '_nfinite_track_archive_identifier', 'value' => $identifier, 'compare' => '=' ),
                    array( 'key' => '_nfinite_track_archive_file', 'value' => $name, 'compare' => '=' ),
                ),
            ) );
            if ( $matches ) { $track_id = absint( $matches[0] ); }

            if ( ! $track_id && $title ) {
                $key = sanitize_title( $title );
                if ( isset( $by_title[ $key ] ) ) { $track_id = absint( $by_title[ $key ] ); }
            }

            // Legacy releases often retained Track rows after the release-level Archive URL disappeared.
            // Reuse those rows by position before creating duplicates.
            if ( ! $track_id && isset( $existing_ids[ $index ] ) ) {
                $candidate = absint( $existing_ids[ $index ] );
                if ( $candidate && ! Nfinite_Creators_Music_Query::source_url( $candidate ) ) { $track_id = $candidate; }
            }

            if ( ! $track_id ) {
                $track_id = wp_insert_post( array(
                    'post_type' => 'nfinite_track',
                    'post_status' => 'publish',
                    'post_title' => $title ?: sprintf( __( 'Track %d', 'nfinite-creators' ), $index + 1 ),
                    'post_author' => get_current_user_id(),
                ), true );
                if ( is_wp_error( $track_id ) ) { continue; }
            } else if ( $title && get_the_title( $track_id ) !== $title ) {
                wp_update_post( array( 'ID' => $track_id, 'post_title' => $title ) );
            }

            update_post_meta( $track_id, '_nfinite_track_creator_id', absint( $creator_id ) );
            update_post_meta( $track_id, '_nfinite_track_release_id', absint( $release_id ) );
            update_post_meta( $track_id, '_nfinite_track_number', $index + 1 );
            update_post_meta( $track_id, '_nfinite_track_playback_source', 'local' );
            update_post_meta( $track_id, '_nfinite_track_audio_url', esc_url_raw( $url ) );
            update_post_meta( $track_id, '_nfinite_track_archive_identifier', $identifier );
            update_post_meta( $track_id, '_nfinite_track_archive_file', $name );
            if ( ! empty( $file['length'] ) ) {
                update_post_meta( $track_id, '_nfinite_track_duration_formatted', sanitize_text_field( (string) $file['length'] ) );
            }
            $track_ids[] = absint( $track_id );
        }

        if ( $track_ids ) {
            update_post_meta( $release_id, '_nfinite_release_track_ids', array_values( array_unique( $track_ids ) ) );
            update_post_meta( $release_id, '_nfinite_release_archive_identifier', $identifier );
            update_post_meta( $release_id, '_nfinite_release_archive_last_sync', current_time( 'mysql' ) );
            delete_post_meta( $release_id, '_nfinite_release_archive_sync_error' );
            return true;
        }
        return false;
    }

    private static function track_from_attachment( $attachment_id, $creator_id = 0, $override_title = '' ) {
        if ( 'attachment' !== get_post_type( $attachment_id ) ) {
            return 0;
        }

        $mime = (string) get_post_mime_type( $attachment_id );
        if ( 0 !== strpos( $mime, 'audio/' ) ) {
            return 0;
        }

        $existing = get_posts( array(
            'post_type'      => 'nfinite_track',
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => '_nfinite_track_attachment_id',
                    'value'   => $attachment_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
        ) );

        if ( ! empty( $existing ) ) {
            return absint( $existing[0] );
        }

        $metadata   = wp_get_attachment_metadata( $attachment_id );
        $attachment = get_post( $attachment_id );
        $audio_url  = wp_get_attachment_url( $attachment_id );

        $metadata_title = '';
        if ( is_array( $metadata ) && ! empty( $metadata['title'] ) ) {
            $metadata_title = sanitize_text_field( $metadata['title'] );
        }

        $attachment_title = $attachment ? sanitize_text_field( $attachment->post_title ) : '';
        $filename         = $audio_url ? pathinfo( wp_parse_url( $audio_url, PHP_URL_PATH ), PATHINFO_FILENAME ) : '';
        $title            = $override_title ?: $metadata_title ?: $attachment_title ?: $filename ?: __( 'Untitled Track', 'nfinite-creators' );

        $track_id = wp_insert_post(
            array(
                'post_type'   => 'nfinite_track',
                'post_status' => 'publish',
                'post_title'  => $title,
                'post_author' => get_current_user_id(),
            ),
            true
        );

        if ( is_wp_error( $track_id ) ) {
            return 0;
        }

        update_post_meta( $track_id, '_nfinite_track_attachment_id', $attachment_id );
        update_post_meta( $track_id, '_nfinite_track_audio_url', esc_url_raw( $audio_url ) );
        update_post_meta( $track_id, '_nfinite_track_creator_id', absint( $creator_id ) );

        if ( is_array( $metadata ) ) {
            $map = array(
                'artist'           => '_nfinite_track_metadata_artist',
                'album'            => '_nfinite_track_metadata_album',
                'year'             => '_nfinite_track_metadata_year',
                'genre'            => '_nfinite_track_metadata_genre',
                'length'           => '_nfinite_track_duration',
                'length_formatted' => '_nfinite_track_duration_formatted',
                'bitrate'          => '_nfinite_track_bitrate',
            );

            foreach ( $map as $source => $meta_key ) {
                if ( isset( $metadata[ $source ] ) && '' !== $metadata[ $source ] ) {
                    update_post_meta( $track_id, $meta_key, sanitize_text_field( (string) $metadata[ $source ] ) );
                }
            }
        }

        return absint( $track_id );
    }

    private static function track_from_external( $source, $url, $creator_id = 0, $title = '', $source_meta = array() ) {
        $allowed = array( 'direct_audio', 'soundcloud', 'spotify', 'apple_music', 'internet_archive' );
        if ( ! in_array( $source, $allowed, true ) || ! $url ) {
            return 0;
        }

        $meta_key = array(
            'direct_audio' => '_nfinite_track_audio_url',
            'soundcloud'   => '_nfinite_track_soundcloud',
            'spotify'      => '_nfinite_track_spotify',
            'apple_music'  => '_nfinite_track_apple_music',
            'internet_archive' => '_nfinite_track_internet_archive',
        )[ $source ];

        $existing = get_posts( array(
            'post_type'      => 'nfinite_track',
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array( array( 'key' => $meta_key, 'value' => $url, 'compare' => '=' ) ),
        ) );
        if ( ! empty( $existing ) ) {
            $existing_id = absint( $existing[0] );
            if ( 'internet_archive' === $source && is_array( $source_meta ) ) {
                update_post_meta( $existing_id, '_nfinite_track_playback_source', 'internet_archive' );
                if ( ! empty( $source_meta['identifier'] ) ) { update_post_meta( $existing_id, '_nfinite_track_archive_identifier', sanitize_text_field( $source_meta['identifier'] ) ); }
                if ( ! empty( $source_meta['filename'] ) ) {
                    update_post_meta( $existing_id, '_nfinite_track_archive_filename', sanitize_text_field( $source_meta['filename'] ) );
                    update_post_meta( $existing_id, '_nfinite_track_archive_file', sanitize_text_field( $source_meta['filename'] ) );
                }
            }
            return $existing_id;
        }

        $track_id = wp_insert_post( array(
            'post_type'   => 'nfinite_track',
            'post_status' => 'publish',
            'post_title'  => $title ?: __( 'Untitled Streaming Track', 'nfinite-creators' ),
            'post_author' => get_current_user_id(),
        ), true );

        if ( is_wp_error( $track_id ) ) { return 0; }

        update_post_meta( $track_id, '_nfinite_track_creator_id', absint( $creator_id ) );
        update_post_meta( $track_id, '_nfinite_track_playback_source', 'direct_audio' === $source ? 'local' : $source );
        update_post_meta( $track_id, $meta_key, esc_url_raw( $url ) );
        if ( 'internet_archive' === $source && is_array( $source_meta ) ) {
            if ( ! empty( $source_meta['identifier'] ) ) { update_post_meta( $track_id, '_nfinite_track_archive_identifier', sanitize_text_field( $source_meta['identifier'] ) ); }
            if ( ! empty( $source_meta['filename'] ) ) {
                update_post_meta( $track_id, '_nfinite_track_archive_filename', sanitize_text_field( $source_meta['filename'] ) );
                update_post_meta( $track_id, '_nfinite_track_archive_file', sanitize_text_field( $source_meta['filename'] ) );
            }
        }
        return absint( $track_id );
    }

    private static function save_release_track_overrides() {
        $overrides = isset( $_POST['_nfinite_release_track_overrides'] ) && is_array( $_POST['_nfinite_release_track_overrides'] )
            ? wp_unslash( $_POST['_nfinite_release_track_overrides'] )
            : array();

        foreach ( $overrides as $track_id => $fields ) {
            $track_id = absint( $track_id );

            if ( ! $track_id || 'nfinite_track' !== get_post_type( $track_id ) || ! is_array( $fields ) ) {
                continue;
            }

            $title = isset( $fields['title'] ) ? sanitize_text_field( $fields['title'] ) : '';
            if ( $title && $title !== get_the_title( $track_id ) ) {
                wp_update_post( array(
                    'ID'         => $track_id,
                    'post_title' => $title,
                ) );
            }

            $source = isset( $fields['source'] ) ? sanitize_key( $fields['source'] ) : 'local';
            if ( ! in_array( $source, array( 'local', 'soundcloud', 'spotify', 'apple_music', 'internet_archive' ), true ) ) { $source = 'local'; }
            update_post_meta( $track_id, '_nfinite_track_playback_source', $source );
            update_post_meta( $track_id, '_nfinite_track_credits', isset( $fields['credits'] ) ? sanitize_textarea_field( $fields['credits'] ) : '' );
            if ( isset( $fields['audio_url'] ) ) {
                $override_audio = esc_url_raw( $fields['audio_url'] );
                if ( 'internet_archive' === $source ) {
                    update_post_meta( $track_id, '_nfinite_track_internet_archive', $override_audio );
                } else {
                    update_post_meta( $track_id, '_nfinite_track_audio_url', $override_audio );
                }
            }
            update_post_meta( $track_id, '_nfinite_track_soundcloud', isset( $fields['soundcloud'] ) ? esc_url_raw( $fields['soundcloud'] ) : '' );
            update_post_meta( $track_id, '_nfinite_track_spotify', isset( $fields['spotify'] ) ? esc_url_raw( $fields['spotify'] ) : '' );
            update_post_meta( $track_id, '_nfinite_track_apple_music', isset( $fields['apple'] ) ? esc_url_raw( $fields['apple'] ) : '' );
            update_post_meta( $track_id, '_nfinite_track_youtube', isset( $fields['youtube'] ) ? esc_url_raw( $fields['youtube'] ) : '' );
            update_post_meta( $track_id, '_nfinite_track_explicit', ! empty( $fields['explicit'] ) ? '1' : '' );
            update_post_meta( $track_id, '_nfinite_track_show_discovery', ! empty( $fields['show_discovery'] ) ? '1' : '0' );
        }
    }

    public static function save_track( $post_id, $post ) {
        if ( ! self::allowed_save( 'nfinite_track_nonce', 'nfinite_track_save', $post_id ) ) { return; }

        $release_id = isset( $_POST['_nfinite_track_release_id'] ) ? absint( $_POST['_nfinite_track_release_id'] ) : 0;
        $creator_id = isset( $_POST['_nfinite_track_creator_id'] ) ? absint( $_POST['_nfinite_track_creator_id'] ) : 0;
        if ( ! $creator_id && $release_id ) {
            $creator_id = absint( get_post_meta( $release_id, '_nfinite_release_creator_id', true ) );
        }

        $source = isset( $_POST['_nfinite_track_playback_source'] ) ? sanitize_key( wp_unslash( $_POST['_nfinite_track_playback_source'] ) ) : 'local';
        if ( ! in_array( $source, array( 'local', 'soundcloud', 'spotify', 'apple_music', 'internet_archive' ), true ) ) { $source = 'local'; }

        update_post_meta( $post_id, '_nfinite_track_release_id', $release_id );
        update_post_meta( $post_id, '_nfinite_track_playback_source', $source );
        update_post_meta( $post_id, '_nfinite_track_creator_id', $creator_id );
        $track_format = isset( $_POST['_nfinite_track_format'] ) ? sanitize_key( wp_unslash( $_POST['_nfinite_track_format'] ) ) : 'song';
        if ( ! in_array( $track_format, array( 'song', 'beat', 'instrumental' ), true ) ) { $track_format = 'song'; }
        update_post_meta( $post_id, '_nfinite_track_format', $track_format );
        // Track order is managed by drag/drop on each Release, not manually here.
        update_post_meta( $post_id, '_nfinite_track_audio_url', isset( $_POST['_nfinite_track_audio_url'] ) ? esc_url_raw( wp_unslash( $_POST['_nfinite_track_audio_url'] ) ) : '' );
        update_post_meta( $post_id, '_nfinite_track_credits', isset( $_POST['_nfinite_track_credits'] ) ? sanitize_textarea_field( wp_unslash( $_POST['_nfinite_track_credits'] ) ) : '' );
        update_post_meta( $post_id, '_nfinite_track_lyrics', isset( $_POST['_nfinite_track_lyrics'] ) ? sanitize_textarea_field( wp_unslash( $_POST['_nfinite_track_lyrics'] ) ) : '' );
        update_post_meta( $post_id, '_nfinite_track_explicit', isset( $_POST['_nfinite_track_explicit'] ) ? '1' : '' );
        update_post_meta( $post_id, '_nfinite_track_show_discovery', isset( $_POST['_nfinite_track_show_discovery'] ) ? '1' : '0' );
        update_post_meta( $post_id, '_nfinite_track_product_id', isset( $_POST['_nfinite_track_product_id'] ) ? absint( $_POST['_nfinite_track_product_id'] ) : 0 );
        update_post_meta( $post_id, '_nfinite_track_buy_url', isset( $_POST['_nfinite_track_buy_url'] ) ? esc_url_raw( wp_unslash( $_POST['_nfinite_track_buy_url'] ) ) : '' );
        update_post_meta( $post_id, '_nfinite_track_buy_label', isset( $_POST['_nfinite_track_buy_label'] ) ? sanitize_text_field( wp_unslash( $_POST['_nfinite_track_buy_label'] ) ) : '' );
        update_post_meta( $post_id, '_nfinite_track_show_price', isset( $_POST['_nfinite_track_show_price'] ) ? '1' : '' );
        foreach ( array( '_nfinite_track_soundcloud', '_nfinite_track_spotify', '_nfinite_track_apple_music', '_nfinite_track_youtube' ) as $key ) {
            update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : '' );
        }

        if ( $release_id ) {
            $ordered = get_post_meta( $release_id, '_nfinite_release_track_ids', true );
            if ( ! is_array( $ordered ) ) { $ordered = array(); }
            $ordered = array_values( array_filter( array_map( 'absint', $ordered ) ) );
            if ( ! in_array( $post_id, $ordered, true ) ) {
                $ordered[] = $post_id;
                update_post_meta( $release_id, '_nfinite_release_track_ids', $ordered );
            }
        }
    }

    public static function release_columns( $columns ) {
        $columns['nfinite_creator'] = __( 'Creator', 'nfinite-creators' );
        $columns['nfinite_type'] = __( 'Type', 'nfinite-creators' );
        $columns['nfinite_tracks'] = __( 'Tracks', 'nfinite-creators' );
        $columns['nfinite_date'] = __( 'Release Date', 'nfinite-creators' );
        return $columns;
    }

    public static function release_column_content( $column, $post_id ) {
        if ( 'nfinite_creator' === $column ) {
            $id = absint( get_post_meta( $post_id, '_nfinite_release_creator_id', true ) );
            echo $id ? esc_html( get_the_title( $id ) ) : '—';
        } elseif ( 'nfinite_type' === $column ) {
            $terms = get_the_terms( $post_id, 'nfinite_release_type' );
            echo esc_html( $terms && ! is_wp_error( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '—' );
        } elseif ( 'nfinite_tracks' === $column ) {
            echo esc_html( count( Nfinite_Creators_Music_Query::release_tracks( $post_id ) ) );
        } elseif ( 'nfinite_date' === $column ) {
            echo esc_html( get_post_meta( $post_id, '_nfinite_release_date', true ) ?: '—' );
        }
    }

    public static function track_columns( $columns ) {
        $columns['nfinite_release'] = __( 'Release', 'nfinite-creators' );
        $columns['nfinite_creator'] = __( 'Creator', 'nfinite-creators' );
        $columns['nfinite_audio'] = __( 'Audio', 'nfinite-creators' );
        return $columns;
    }

    public static function track_column_content( $column, $post_id ) {
        if ( 'nfinite_release' === $column ) {
            $id = absint( get_post_meta( $post_id, '_nfinite_track_release_id', true ) );
            echo $id ? esc_html( get_the_title( $id ) ) : '—';
        } elseif ( 'nfinite_creator' === $column ) {
            $id = absint( get_post_meta( $post_id, '_nfinite_track_creator_id', true ) );
            echo $id ? esc_html( get_the_title( $id ) ) : '—';
        } elseif ( 'nfinite_audio' === $column ) {
            echo get_post_meta( $post_id, '_nfinite_track_audio_url', true ) ? esc_html__( 'Ready', 'nfinite-creators' ) : esc_html__( 'Missing', 'nfinite-creators' );
        }
    }
}
