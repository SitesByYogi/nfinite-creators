<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Music_Migration {
    public static function init() {
        add_action( 'init', array( __CLASS__, 'legacy_archive_0216' ), 30 );
        add_action( 'add_meta_boxes_nfinite_creator', array( __CLASS__, 'add_box' ) );
        add_action( 'admin_post_nfinite_migrate_legacy_audio', array( __CLASS__, 'handle' ) );
    }

    public static function add_box() {
        add_meta_box(
            'nfinite_creator_music_migration',
            __( 'Music Library Migration', 'nfinite-creators' ),
            array( __CLASS__, 'render' ),
            'nfinite_creator',
            'side',
            'default'
        );
    }

    public static function render( $post ) {
        $legacy = get_post_meta( $post->ID, '_nfinite_creator_tracks', true );
        if ( ! is_array( $legacy ) || empty( $legacy ) ) {
            echo '<p>' . esc_html__( 'No legacy profile audio tracks need migration.', 'nfinite-creators' ) . '</p>';
            return;
        }

        $migrated = get_post_meta( $post->ID, '_nfinite_music_legacy_migrated', true );
        if ( $migrated ) {
            echo '<p><strong>' . esc_html__( 'Legacy audio has already been imported into the Music Library.', 'nfinite-creators' ) . '</strong></p>';
            return;
        }

        $url = wp_nonce_url(
            add_query_arg(
                array(
                    'action'     => 'nfinite_migrate_legacy_audio',
                    'creator_id' => $post->ID,
                ),
                admin_url( 'admin-post.php' )
            ),
            'nfinite_migrate_legacy_audio_' . $post->ID
        );

        echo '<p>' . sprintf(
            esc_html__( '%d legacy profile tracks are available to import.', 'nfinite-creators' ),
            count( $legacy )
        ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Import as Singles', 'nfinite-creators' ) . '</a></p>';
        echo '<p class="description">' . esc_html__( 'This creates Track records for the creator without deleting the existing legacy audio data.', 'nfinite-creators' ) . '</p>';
    }

    public static function handle() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have permission to migrate audio.', 'nfinite-creators' ) );
        }

        $creator_id = isset( $_GET['creator_id'] ) ? absint( $_GET['creator_id'] ) : 0;
        check_admin_referer( 'nfinite_migrate_legacy_audio_' . $creator_id );

        if ( 'nfinite_creator' !== get_post_type( $creator_id ) ) {
            wp_die( esc_html__( 'Invalid creator.', 'nfinite-creators' ) );
        }

        $legacy = get_post_meta( $creator_id, '_nfinite_creator_tracks', true );
        if ( ! is_array( $legacy ) ) {
            $legacy = array();
        }

        $created = 0;
        foreach ( $legacy as $index => $track ) {
            $audio_url = isset( $track['audio_url'] ) ? esc_url_raw( $track['audio_url'] ) : '';
            if ( ! $audio_url ) {
                continue;
            }

            $track_id = wp_insert_post(
                array(
                    'post_type'   => 'nfinite_track',
                    'post_status' => 'publish',
                    'post_title'  => ! empty( $track['title'] ) ? sanitize_text_field( $track['title'] ) : sprintf( __( 'Track %d', 'nfinite-creators' ), $index + 1 ),
                    'post_author' => get_post_field( 'post_author', $creator_id ),
                ),
                true
            );

            if ( is_wp_error( $track_id ) ) {
                continue;
            }

            update_post_meta( $track_id, '_nfinite_track_creator_id', $creator_id );
            update_post_meta( $track_id, '_nfinite_track_release_id', 0 );
            update_post_meta( $track_id, '_nfinite_track_number', $index + 1 );
            update_post_meta( $track_id, '_nfinite_track_disc_number', 1 );
            update_post_meta( $track_id, '_nfinite_track_audio_url', $audio_url );
            update_post_meta( $track_id, '_nfinite_track_credits', isset( $track['credits'] ) ? sanitize_textarea_field( $track['credits'] ) : '' );

            if ( ! empty( $track['cover_url'] ) ) {
                update_post_meta( $track_id, '_nfinite_track_legacy_cover_url', esc_url_raw( $track['cover_url'] ) );
            }

            $created++;
        }

        update_post_meta( $creator_id, '_nfinite_music_legacy_migrated', current_time( 'mysql' ) );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'post'                    => $creator_id,
                    'action'                  => 'edit',
                    'nfinite_music_migrated'  => $created,
                ),
                admin_url( 'post.php' )
            )
        );
        exit;
    }

    public static function legacy_archive_0216() {
        if ( get_option( 'nfinite_creators_legacy_archive_0216' ) ) { return; }

        $release_ids = get_posts( array(
            'post_type' => 'nfinite_release', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids',
            'meta_query' => array( array( 'key' => '_nfinite_release_internet_archive_item', 'compare' => 'EXISTS' ) ),
        ) );
        foreach ( $release_ids as $release_id ) {
            $legacy = trim( (string) get_post_meta( $release_id, '_nfinite_release_internet_archive_item', true ) );
            if ( ! $legacy ) { continue; }
            $identifier = Nfinite_Creators_Internet_Archive::identifier_from_input( $legacy );
            $url = $identifier ? 'https://archive.org/details/' . rawurlencode( $identifier ) : esc_url_raw( $legacy );
            if ( $url ) {
                if ( ! get_post_meta( $release_id, '_nfinite_release_archive_url', true ) ) { update_post_meta( $release_id, '_nfinite_release_archive_url', $url ); }
                if ( ! get_post_meta( $release_id, '_nfinite_release_internet_archive_url', true ) ) { update_post_meta( $release_id, '_nfinite_release_internet_archive_url', $url ); }
                if ( ! get_post_meta( $release_id, '_nfinite_release_archive', true ) ) { update_post_meta( $release_id, '_nfinite_release_archive', $url ); }
            }
        }

        $track_ids = get_posts( array(
            'post_type' => 'nfinite_track', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids',
            'meta_query' => array( array( 'key' => '_nfinite_track_internet_archive', 'compare' => 'EXISTS' ) ),
        ) );
        foreach ( $track_ids as $track_id ) {
            $legacy_url = esc_url_raw( get_post_meta( $track_id, '_nfinite_track_internet_archive', true ) );
            if ( ! $legacy_url ) { continue; }
            $source = sanitize_key( get_post_meta( $track_id, '_nfinite_track_playback_source', true ) );
            if ( ! $source || in_array( $source, array( 'archive', 'archive_org' ), true ) ) { update_post_meta( $track_id, '_nfinite_track_playback_source', 'internet_archive' ); }
            $legacy_file = get_post_meta( $track_id, '_nfinite_track_archive_filename', true );
            if ( $legacy_file && ! get_post_meta( $track_id, '_nfinite_track_archive_file', true ) ) { update_post_meta( $track_id, '_nfinite_track_archive_file', sanitize_text_field( $legacy_file ) ); }
        }

        update_option( 'nfinite_creators_legacy_archive_0216', gmdate( 'c' ), false );
    }
}
