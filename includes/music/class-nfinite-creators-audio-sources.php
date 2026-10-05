<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Nfinite_Creators_Audio_Sources {
    public static function allowed_sources() { return array( 'local', 'soundcloud', 'spotify', 'apple_music', 'internet_archive' ); }
    public static function provider_label( $source ) {
        $labels = array( 'local' => __( 'PairOfDice', 'nfinite-creators' ), 'soundcloud' => __( 'SoundCloud', 'nfinite-creators' ), 'spotify' => __( 'Spotify', 'nfinite-creators' ), 'apple_music' => __( 'Apple Music', 'nfinite-creators' ), 'internet_archive' => __( 'Internet Archive', 'nfinite-creators' ) );
        return isset( $labels[ $source ] ) ? $labels[ $source ] : __( 'Audio', 'nfinite-creators' );
    }
    public static function embed_url( $source, $url, $autoplay = false ) {
        $source = sanitize_key( (string) $source ); $url = esc_url_raw( (string) $url ); if ( ! $url ) { return ''; }
        if ( 'spotify' === $source ) {
            $parts = wp_parse_url( $url ); if ( empty( $parts['host'] ) || false === strpos( strtolower( $parts['host'] ), 'spotify.com' ) ) { return ''; }
            $path = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : ''; if ( ! $path ) { return ''; }
            return 0 === strpos( $path, 'embed/' ) ? 'https://open.spotify.com/' . $path : 'https://open.spotify.com/embed/' . $path;
        }
        if ( 'apple_music' === $source ) {
            $parts = wp_parse_url( $url ); if ( empty( $parts['host'] ) || false === strpos( strtolower( $parts['host'] ), 'music.apple.com' ) ) { return ''; }
            return 'https://embed.music.apple.com' . ( isset( $parts['path'] ) ? $parts['path'] : '' ) . ( ! empty( $parts['query'] ) ? '?' . $parts['query'] : '' );
        }
        if ( 'soundcloud' === $source ) {
            return 'https://w.soundcloud.com/player/?' . http_build_query( array( 'url' => $url, 'auto_play' => $autoplay ? 'true' : 'false', 'hide_related' => 'true', 'show_comments' => 'false', 'show_user' => 'true', 'show_reposts' => 'false', 'visual' => 'false' ), '', '&', PHP_QUERY_RFC3986 );
        }
        return '';
    }
    public static function payload( $source, $audio_url, $links = array() ) {
        $links = array( 'soundcloud' => ! empty( $links['soundcloud'] ) ? esc_url_raw( $links['soundcloud'] ) : '', 'spotify' => ! empty( $links['spotify'] ) ? esc_url_raw( $links['spotify'] ) : '', 'apple_music' => ! empty( $links['apple_music'] ) ? esc_url_raw( $links['apple_music'] ) : '', 'internet_archive' => ! empty( $links['internet_archive'] ) ? esc_url_raw( $links['internet_archive'] ) : '' );
        $source = sanitize_key( (string) $source ); if ( ! in_array( $source, self::allowed_sources(), true ) ) { $source = 'local'; }
        $source_url = 'local' === $source ? esc_url_raw( $audio_url ) : ( isset( $links[ $source ] ) ? $links[ $source ] : '' );
        if ( ! $source_url ) {
            if ( $audio_url ) { $source = 'local'; $source_url = esc_url_raw( $audio_url ); }
            else { foreach ( array( 'soundcloud','spotify','apple_music','internet_archive' ) as $candidate ) { if ( ! empty( $links[ $candidate ] ) ) { $source = $candidate; $source_url = $links[ $candidate ]; break; } } }
        }
        return array( 'sourceType' => $source, 'sourceLabel' => self::provider_label( $source ), 'sourceUrl' => $source_url, 'embedUrl' => in_array( $source, array( 'local', 'internet_archive' ), true ) ? '' : self::embed_url( $source, $source_url, true ), 'streamingLinks' => $links, 'playable' => (bool) $source_url );
    }
}
