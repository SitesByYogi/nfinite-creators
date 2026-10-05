<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Internet_Archive {
    public static function init() {
        add_action( 'wp_ajax_nfinite_archive_item', array( __CLASS__, 'ajax_item' ) );
    }

    public static function identifier_from_input( $input ) {
        $input = trim( (string) $input );
        if ( '' === $input ) { return ''; }

        if ( false === strpos( $input, '://' ) && preg_match( '/^[A-Za-z0-9._-]+$/', $input ) ) {
            return sanitize_text_field( $input );
        }

        $parts = wp_parse_url( $input );
        if ( empty( $parts['host'] ) ) { return ''; }
        $host = strtolower( preg_replace( '/^www\./', '', $parts['host'] ) );
        if ( 'archive.org' !== $host ) { return ''; }

        $path = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';
        if ( ! $path ) { return ''; }
        $segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
        if ( count( $segments ) >= 2 && in_array( strtolower( $segments[0] ), array( 'details', 'download', 'metadata' ), true ) ) {
            return sanitize_text_field( rawurldecode( $segments[1] ) );
        }
        return '';
    }

    private static function audio_priority( $file ) {
        $source = isset( $file['source'] ) ? strtolower( (string) $file['source'] ) : '';
        $format = isset( $file['format'] ) ? strtolower( (string) $file['format'] ) : '';
        if ( 'original' === $source ) { return 0; }
        if ( false !== strpos( $format, 'm4a' ) || false !== strpos( $format, 'aac' ) ) { return 5; }
        if ( false !== strpos( $format, 'vbr mp3' ) ) { return 10; }
        if ( false !== strpos( $format, '320' ) ) { return 20; }
        if ( false !== strpos( $format, '256' ) ) { return 30; }
        if ( false !== strpos( $format, '192' ) ) { return 40; }
        if ( false !== strpos( $format, '128' ) ) { return 50; }
        if ( false !== strpos( $format, '64kb' ) ) { return 90; }
        return 60;
    }

    private static function track_number( $file ) {
        $raw = '';
        foreach ( array( 'track', 'tracknumber', 'track_number' ) as $key ) {
            if ( isset( $file[ $key ] ) && '' !== (string) $file[ $key ] ) { $raw = (string) $file[ $key ]; break; }
        }
        if ( $raw && preg_match( '/\d+/', $raw, $m ) ) { return (int) $m[0]; }

        $name = isset( $file['name'] ) ? basename( (string) $file['name'] ) : '';
        if ( preg_match( '/^(?:track[\s._-]*)?(\d{1,3})[\s._-]+/i', $name, $m ) ) { return (int) $m[1]; }
        return 9999;
    }

    private static function clean_title( $file ) {
        if ( ! empty( $file['title'] ) ) { return sanitize_text_field( (string) $file['title'] ); }
        $name = isset( $file['name'] ) ? pathinfo( basename( (string) $file['name'] ), PATHINFO_FILENAME ) : '';
        $name = preg_replace( '/^(?:track[\s._-]*)?\d{1,3}[\s._-]+/i', '', $name );
        $name = preg_replace( '/[_]+/', ' ', $name );
        $name = preg_replace( '/\s+/', ' ', $name );
        return sanitize_text_field( trim( $name ) ?: __( 'Untitled Track', 'nfinite-creators' ) );
    }

    private static function encode_archive_path( $name ) {
        $segments = explode( '/', ltrim( (string) $name, '/' ) );
        return implode( '/', array_map( 'rawurlencode', $segments ) );
    }

    public static function is_direct_audio_url( $url ) {
        $url = trim( (string) $url );
        if ( ! $url ) { return false; }
        $parts = wp_parse_url( $url );
        if ( empty( $parts['host'] ) || empty( $parts['path'] ) ) { return false; }
        $host = strtolower( preg_replace( '/^www\./', '', $parts['host'] ) );
        if ( 'archive.org' !== $host && ! preg_match( '/\.archive\.org$/', $host ) ) { return false; }
        $path = rawurldecode( (string) $parts['path'] );
        if ( preg_match( '#/(?:details|metadata)/#i', $path ) ) { return false; }
        return (bool) preg_match( '/\.(?:mp3|m4a)$/i', $path );
    }

    public static function is_direct_mp3_url( $url ) {
        return self::is_direct_audio_url( $url );
    }

    public static function fetch_item( $input ) {
        $identifier = self::identifier_from_input( $input );
        if ( ! $identifier ) {
            return new WP_Error( 'nfinite_archive_invalid', __( 'Enter a valid Internet Archive item URL or identifier.', 'nfinite-creators' ) );
        }

        $url = 'https://archive.org/metadata/' . rawurlencode( $identifier );
        $response = wp_safe_remote_get( $url, array(
            'timeout'     => 20,
            'redirection' => 3,
            'headers'     => array( 'Accept' => 'application/json' ),
            'user-agent'  => 'Nfinite Creators/' . NFINITE_CREATORS_VERSION . '; ' . home_url( '/' ),
        ) );
        if ( is_wp_error( $response ) ) { return $response; }
        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return new WP_Error( 'nfinite_archive_http', __( 'Internet Archive did not return metadata for that item.', 'nfinite-creators' ) );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) || empty( $data['files'] ) || ! is_array( $data['files'] ) ) {
            return new WP_Error( 'nfinite_archive_empty', __( 'No file metadata was found for that Internet Archive item.', 'nfinite-creators' ) );
        }

        $groups = array();
        foreach ( $data['files'] as $file ) {
            if ( ! is_array( $file ) || empty( $file['name'] ) ) { continue; }
            $name = (string) $file['name'];
            if ( ! preg_match( '/\.(?:mp3|m4a)$/i', $name ) ) { continue; }

            $format = isset( $file['format'] ) ? strtolower( (string) $file['format'] ) : '';
            if ( false !== strpos( $format, 'sample' ) || false !== strpos( $name, '__ia_thumb' ) ) { continue; }

            $canonical = ! empty( $file['original'] ) ? (string) $file['original'] : $name;
            $priority = self::audio_priority( $file );
            if ( ! isset( $groups[ $canonical ] ) || $priority < $groups[ $canonical ]['priority'] ) {
                $groups[ $canonical ] = array( 'priority' => $priority, 'file' => $file );
            }
        }

        $tracks = array();
        foreach ( $groups as $group ) {
            $file = $group['file'];
            $name = (string) $file['name'];
            $tracks[] = array(
                'title'      => self::clean_title( $file ),
                'url'        => 'https://archive.org/download/' . rawurlencode( $identifier ) . '/' . self::encode_archive_path( $name ),
                'filename'   => sanitize_text_field( $name ),
                'artist'     => ! empty( $file['artist'] ) ? sanitize_text_field( (string) $file['artist'] ) : '',
                'album'      => ! empty( $file['album'] ) ? sanitize_text_field( (string) $file['album'] ) : '',
                'duration'   => ! empty( $file['length'] ) ? sanitize_text_field( (string) $file['length'] ) : '',
                'track'      => self::track_number( $file ),
                'format'     => ! empty( $file['format'] ) ? sanitize_text_field( (string) $file['format'] ) : ( preg_match( '/\.m4a$/i', $name ) ? 'M4A' : 'MP3' ),
                'identifier' => $identifier,
            );
        }

        usort( $tracks, function( $a, $b ) {
            if ( $a['track'] !== $b['track'] ) { return $a['track'] <=> $b['track']; }
            return strnatcasecmp( $a['filename'], $b['filename'] );
        } );

        if ( empty( $tracks ) ) {
            return new WP_Error( 'nfinite_archive_no_audio', __( 'No playable MP3 or M4A files were found in that Internet Archive item.', 'nfinite-creators' ) );
        }

        $meta = ! empty( $data['metadata'] ) && is_array( $data['metadata'] ) ? $data['metadata'] : array();
        return array(
            'identifier' => $identifier,
            'item_url'   => 'https://archive.org/details/' . rawurlencode( $identifier ),
            'title'      => ! empty( $meta['title'] ) ? sanitize_text_field( is_array( $meta['title'] ) ? reset( $meta['title'] ) : $meta['title'] ) : '',
            'creator'    => ! empty( $meta['creator'] ) ? sanitize_text_field( is_array( $meta['creator'] ) ? implode( ', ', $meta['creator'] ) : $meta['creator'] ) : '',
            'date'       => ! empty( $meta['date'] ) ? sanitize_text_field( is_array( $meta['date'] ) ? reset( $meta['date'] ) : $meta['date'] ) : '',
            'description'=> ! empty( $meta['description'] ) ? wp_strip_all_tags( is_array( $meta['description'] ) ? implode( "\n", $meta['description'] ) : $meta['description'] ) : '',
            'image_url'  => 'https://archive.org/services/img/' . rawurlencode( $identifier ),
            'tracks'     => array_values( $tracks ),
        );
    }

    public static function ajax_item() {
        check_ajax_referer( 'nfinite_archive_item', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to import Archive.org metadata.', 'nfinite-creators' ) ), 403 );
        }
        $input = isset( $_POST['item'] ) ? esc_url_raw( wp_unslash( $_POST['item'] ) ) : '';
        if ( ! $input && isset( $_POST['item'] ) ) { $input = sanitize_text_field( wp_unslash( $_POST['item'] ) ); }
        $result = self::fetch_item( $input );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
        }
        wp_send_json_success( $result );
    }
}
