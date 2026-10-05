<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Music_Query {
    public static function release_tracks( $release_id ) {
        $ordered_ids = get_post_meta( $release_id, '_nfinite_release_track_ids', true );

        if ( is_array( $ordered_ids ) && ! empty( $ordered_ids ) ) {
            $ordered_ids = array_values( array_filter( array_map( 'absint', $ordered_ids ) ) );
            if ( $ordered_ids ) {
                return get_posts( array(
                    'post_type' => 'nfinite_track',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'post__in' => $ordered_ids,
                    'orderby' => 'post__in',
                ) );
            }
        }

        return get_posts( array(
            'post_type' => 'nfinite_track',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_nfinite_track_release_id',
                    'value' => absint( $release_id ),
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ),
            ),
            'meta_key' => '_nfinite_track_number',
            'orderby' => array( 'meta_value_num' => 'ASC', 'date' => 'ASC' ),
            'order' => 'ASC',
        ) );
    }

    public static function creator_releases( $creator_id, $limit = -1 ) {
        return get_posts( array(
            'post_type' => 'nfinite_release',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => '_nfinite_release_creator_id',
                    'value' => absint( $creator_id ),
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ),
            ),
            'orderby' => 'date',
            'order' => 'DESC',
        ) );
    }

    public static function release_type_label( $release_id ) {
        $terms = get_the_terms( $release_id, 'nfinite_release_type' );
        return $terms && ! is_wp_error( $terms ) ? $terms[0]->name : __( 'Release', 'nfinite-creators' );
    }

    public static function playback_source( $track_id ) {
        $source = sanitize_key( get_post_meta( $track_id, '_nfinite_track_playback_source', true ) );
        $allowed = array( 'local', 'soundcloud', 'spotify', 'apple_music', 'internet_archive' );
        // Direct external audio (including archive.org) is played by the native audio element.
        // Normalize legacy/custom source labels back to local whenever an audio URL exists.
        if ( in_array( $source, array( 'archive', 'archive_org' ), true ) && get_post_meta( $track_id, '_nfinite_track_internet_archive', true ) ) {
            return 'internet_archive';
        }
        if ( in_array( $source, array( 'direct', 'direct_audio', 'external_audio' ), true ) && get_post_meta( $track_id, '_nfinite_track_audio_url', true ) ) {
            return 'local';
        }
        if ( in_array( $source, $allowed, true ) ) { return $source; }
        if ( get_post_meta( $track_id, '_nfinite_track_audio_url', true ) ) { return 'local'; }
        if ( get_post_meta( $track_id, '_nfinite_track_soundcloud', true ) ) { return 'soundcloud'; }
        if ( get_post_meta( $track_id, '_nfinite_track_spotify', true ) ) { return 'spotify'; }
        if ( get_post_meta( $track_id, '_nfinite_track_apple_music', true ) ) { return 'apple_music'; }
        if ( get_post_meta( $track_id, '_nfinite_track_internet_archive', true ) ) { return 'internet_archive'; }
        return 'local';
    }

    public static function source_url( $track_id, $source = '' ) {
        $source = $source ?: self::playback_source( $track_id );
        $keys = array(
            'local'       => '_nfinite_track_audio_url',
            'soundcloud'  => '_nfinite_track_soundcloud',
            'spotify'     => '_nfinite_track_spotify',
            'apple_music' => '_nfinite_track_apple_music',
            'internet_archive' => '_nfinite_track_internet_archive',
        );
        return isset( $keys[ $source ] ) ? esc_url_raw( get_post_meta( $track_id, $keys[ $source ], true ) ) : '';
    }

    /**
     * Whether a Track is eligible for global Latest/Featured track discovery.
     *
     * Backward compatible: Tracks created before this setting existed have no
     * meta value and remain visible by default. Only an explicit "0" hides
     * the Track from discovery. Release tracklists are intentionally unaffected.
     */
    public static function show_in_track_discovery( $track_id ) {
        return '0' !== (string) get_post_meta( $track_id, '_nfinite_track_show_discovery', true );
    }

    /**
     * PairOfDice internal playback is reserved for full-stream sources.
     * Local audio must be served by this WordPress host; Internet Archive
     * remains explicitly eligible. Preview/embed providers stay catalog-only.
     */
    public static function internal_playable( $track_id, $source = '', $source_url = '' ) {
        $source = $source ?: self::playback_source( $track_id );
        $source_url = $source_url ?: self::source_url( $track_id, $source );
        if ( ! $source_url ) { return false; }
        $path = strtolower( (string) wp_parse_url( $source_url, PHP_URL_PATH ) );
        if ( ! preg_match( '/\.(mp3|m4a|aac|ogg|oga|wav|flac)$/i', $path ) ) { return false; }
        $source_host = strtolower( (string) wp_parse_url( $source_url, PHP_URL_HOST ) );

        // Archive imports intentionally store the direct Archive.org file in
        // _nfinite_track_audio_url and may retain the legacy `local` source label.
        // Treat the URL host as authoritative so those full streams remain eligible.
        if ( 'internet_archive' === $source || preg_match( '/(^|\.)archive\.org$/i', $source_host ) ) {
            return (bool) preg_match( '/(^|\.)archive\.org$/i', $source_host );
        }
        if ( 'local' !== $source ) { return false; }
        $site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
        return $source_host && $site_host && $source_host === $site_host;
    }

    public static function track_payload( $track_id ) {
        $release_id = absint( get_post_meta( $track_id, '_nfinite_track_release_id', true ) );
        $creator_id = absint( get_post_meta( $track_id, '_nfinite_track_creator_id', true ) );
        $artwork = get_the_post_thumbnail_url( $track_id, 'large' );
        if ( ! $artwork && $release_id ) { $artwork = get_the_post_thumbnail_url( $release_id, 'large' ); }
        if ( ! $artwork && $creator_id ) { $artwork = get_the_post_thumbnail_url( $creator_id, 'large' ); }

        $commerce = class_exists( 'Nfinite_Creators_Track_Commerce' )
            ? Nfinite_Creators_Track_Commerce::nfinite_track( $track_id )
            : array();

        $source = self::playback_source( $track_id );
        $source_url = self::source_url( $track_id, $source );

        return array(
            'id' => absint( $track_id ),
            'title' => get_the_title( $track_id ),
            'artist' => $creator_id ? get_the_title( $creator_id ) : sanitize_text_field( get_post_meta( $track_id, '_nfinite_track_metadata_artist', true ) ),
            'creatorId' => $creator_id,
            'releaseId' => $release_id,
            'release' => $release_id ? get_the_title( $release_id ) : '',
            'audioUrl' => esc_url_raw( get_post_meta( $track_id, '_nfinite_track_audio_url', true ) ),
            'source' => $source,
            'sourceUrl' => $source_url,
            'playable' => (bool) $source_url,
            'internalPlayable' => self::internal_playable( $track_id, $source, $source_url ),
            'playbackClass' => self::internal_playable( $track_id, $source, $source_url ) ? ( ( 'internet_archive' === $source || preg_match( '/(^|\.)archive\.org$/i', strtolower( (string) wp_parse_url( $source_url, PHP_URL_HOST ) ) ) ) ? 'archive_full' : 'native_full' ) : ( in_array( $source, array( 'spotify', 'apple_music', 'soundcloud' ), true ) ? 'external_embed' : 'external_preview' ),
            'showInDiscovery' => self::show_in_track_discovery( $track_id ),
            'soundcloudUrl' => esc_url_raw( get_post_meta( $track_id, '_nfinite_track_soundcloud', true ) ),
            'spotifyUrl' => esc_url_raw( get_post_meta( $track_id, '_nfinite_track_spotify', true ) ),
            'appleMusicUrl' => esc_url_raw( get_post_meta( $track_id, '_nfinite_track_apple_music', true ) ),
            'youtubeUrl' => esc_url_raw( get_post_meta( $track_id, '_nfinite_track_youtube', true ) ),
            'internetArchiveUrl' => esc_url_raw( get_post_meta( $track_id, '_nfinite_track_internet_archive', true ) ),
            'artwork' => esc_url_raw( $artwork ),
            'explicit' => (bool) get_post_meta( $track_id, '_nfinite_track_explicit', true ),
            'monetizationStatus' => class_exists( 'Nfinite_Creators_Music_Monetization' ) ? Nfinite_Creators_Music_Monetization::normalize_status( get_post_meta( $track_id, '_nfinite_track_monetization_status', true ) ) : 'not_enrolled',
            'monetized' => class_exists( 'Nfinite_Creators_Music_Monetization' ) ? Nfinite_Creators_Music_Monetization::track_is_monetized( $track_id ) : false,
            'isrc' => sanitize_text_field( get_post_meta( $track_id, '_nfinite_track_isrc', true ) ),
            'trackNumber' => absint( get_post_meta( $track_id, '_nfinite_track_number', true ) ),
            'duration' => sanitize_text_field( get_post_meta( $track_id, '_nfinite_track_duration_formatted', true ) ),
            'productUrl' => ! empty( $commerce['url'] ) ? esc_url_raw( $commerce['url'] ) : '',
            'buyLabel' => ! empty( $commerce['label'] ) ? sanitize_text_field( $commerce['label'] ) : '',
            'priceHtml' => ! empty( $commerce['price_html'] ) ? $commerce['price_html'] : '',
            'priceText' => ! empty( $commerce['price_text'] ) ? sanitize_text_field( $commerce['price_text'] ) : '',
        );
    }

    public static function queue_for_release( $release_id ) {
        $queue = array();
        foreach ( self::release_tracks( $release_id ) as $track ) {
            $payload = self::track_payload( $track->ID );
            if ( ! empty( $payload['internalPlayable'] ) ) { $queue[] = $payload; }
        }
        return $queue;
    }
}
