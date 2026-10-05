<?php
// CLI fixture tests. No live database or WordPress installation is modified.
if ( PHP_SAPI !== 'cli' ) { exit; }
define( 'ABSPATH', __DIR__ );
$posts = $meta = $payloads = $options = array(); $can_manage = true; $nonce_ok = true; $checks = 0;
function check( $condition, $message ) { global $checks; if ( ! $condition ) { throw new Exception( $message ); } $checks++; }
function absint( $v ) { return abs( (int) $v ); }
function get_post( $id ) { global $posts; return is_object( $id ) ? $id : ( $posts[$id] ?? null ); }
function get_post_type( $id ) { $p = get_post( $id ); return $p ? $p->post_type : false; }
function get_post_meta( $id, $key, $single = true ) { global $meta; return $meta[$id][$key] ?? ''; }
function get_option( $key, $default = false ) { global $options; return $options[$key] ?? $default; }
function update_option( $key, $value, $autoload = null ) { global $options; $options[$key] = $value; }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) ); }
function wp_unslash( $v ) { return stripslashes( $v ); }
function current_user_can( $cap ) { global $can_manage; return $can_manage; }
function check_admin_referer( $action ) { global $nonce_ok; if ( ! $nonce_ok ) { throw new Exception( 'nonce rejected' ); } }
function check_ajax_referer( $action, $name ) { check_admin_referer( $action ); }
function wp_die( $message, $title = '', $args = array() ) { throw new Exception( $message ); }
function wp_send_json_error( $message, $status ) { throw new Exception( $message . ':' . $status ); }
function wp_safe_redirect( $url ) { throw new Exception( 'redirect after save' ); }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function get_posts( $args ) {
    global $posts;
    $found = array_values( array_filter( $posts, static function( $p ) use ( $args ) {
        if ( ! in_array( $p->post_type, (array) $args['post_type'], true ) || 'publish' !== $p->post_status ) { return false; }
        if ( isset( $args['has_password'] ) && ! $args['has_password'] && $p->post_password ) { return false; }
        if ( isset( $args['meta_key'] ) && get_post_meta( $p->ID, $args['meta_key'] ) !== $args['meta_value'] ) { return false; }
        return true;
    } ) );
    usort( $found, static function( $a, $b ) { return $b->ID <=> $a->ID; } );
    $count = $args['posts_per_page'] ?? 5;
    return -1 === $count ? $found : array_slice( $found, ( ( $args['paged'] ?? 1 ) - 1 ) * $count, $count );
}
class Nfinite_Creators_Music_Query {
    static function track_payload( $id ) { global $payloads; return $payloads[$id]; }
}
require dirname( __DIR__ ) . '/includes/class-nfinite-creators-programming.php';
function post_fixture( $id, $type, $creator = 0, $release = 0 ) {
    global $posts, $meta, $payloads;
    $posts[$id] = (object) array( 'ID' => $id, 'post_type' => $type, 'post_status' => 'publish', 'post_password' => '' );
    $meta[$id] = array( '_' . $type . '_creator_id' => $creator, '_' . $type . '_release_id' => $release );
    if ( 'nfinite_track' === $type ) { $payloads[$id] = array( 'id' => $id, 'creatorId' => $creator, 'artist' => 'Artist ' . $creator, 'playable' => true, 'showInDiscovery' => true, 'sourceUrl' => 'https://example.test/' . $id . '.mp3' ); }
}
function rules( $surface, $rule ) { global $options; $options[Nfinite_Creators_Programming::OPTION][$surface] = $rule; }
function ids_of( $items ) { return array_map( static function( $p ) { return is_object( $p ) ? $p->ID : $p['id']; }, $items ); }
foreach ( array( 1, 2, 3, 4 ) as $id ) { post_fixture( $id, 'nfinite_creator' ); }
foreach ( array( 11 => 1, 12 => 1, 13 => 1, 14 => 2, 15 => 3, 16 => 4 ) as $id => $creator ) { post_fixture( $id, 'nfinite_release', $creator ); }
foreach ( array( 101 => array( 1, 11 ), 102 => array( 1, 11 ), 103 => array( 1, 12 ), 104 => array( 1, 13 ), 105 => array( 2, 14 ), 106 => array( 3, 15 ), 107 => array( 4, 16 ) ) as $id => $parents ) { post_fixture( $id, 'nfinite_track', $parents[0], $parents[1] ); }
for ( $id = 1000; $id < 1250; $id++ ) { post_fixture( $id, 'nfinite_track', 1, 11 ); $payloads[$id]['showInDiscovery'] = false; }
$items = Nfinite_Creators_Programming::select( 'latest_tracks', 10 );
check( ids_of( $items ) === array( 107, 106, 105, 104, 103 ), 'Fill past 250 hidden uploads and enforce artist/release caps' );
rules( 'latest_tracks', array( 'mode' => 'mixed', 'picks' => array( 102, 101, 106 ), 'exclude' => array( 106 ) ) );
check( ids_of( Nfinite_Creators_Programming::select( 'latest_tracks', 10 ) ) === array( 102, 107, 105, 104 ), 'Mixed picks first, exclusions win, caps count picks' );
rules( 'latest_tracks', array( 'mode' => 'handpicked', 'picks' => array( 102, 101, 1000 ), 'override' => true ) );
check( ids_of( Nfinite_Creators_Programming::select( 'latest_tracks', 10 ) ) === array( 102, 101 ), 'Override only diversity, never hidden discovery checkbox' );
rules( 'latest_tracks', array( 'mode' => 'handpicked', 'picks' => array() ) );
check( array() === Nfinite_Creators_Programming::select( 'latest_tracks', 10 ), 'Empty handpicked section stays empty' );
$posts[1]->post_password = 'protected';
rules( 'latest_tracks', array( 'mode' => 'handpicked', 'picks' => array( 102 ), 'override' => true ) );
check( array() === Nfinite_Creators_Programming::select( 'latest_tracks', 10 ), 'Cannot promote track with protected parent' );
$posts[1]->post_password = '';
$posts[11]->post_status = 'draft';
check( ! Nfinite_Creators_Programming::public_item( 102 ), 'Draft release blocks track promotion' );
$posts[11]->post_status = 'publish';
rules( 'new_releases', array( 'mode' => 'handpicked', 'picks' => array( 11, 16, 14 ), 'exclude' => array( 16 ) ) );
check( ids_of( Nfinite_Creators_Programming::select( 'new_releases', 12 ) ) === array( 11, 14 ), 'Ordered release picks exclude correctly' );
rules( 'creators', array( 'mode' => 'handpicked', 'picks' => array( 3, 1 ) ) );
check( ids_of( Nfinite_Creators_Programming::select( 'creators', 8 ) ) === array( 3, 1 ), 'Creator spotlight order' );
rules( 'radio', array( 'mode' => 'handpicked', 'picks' => array( 1000, 101, 105, 106 ), 'exclude' => array( 106 ) ) );
$queue = Nfinite_Creators_Programming::radio( array( $payloads[101], $payloads[105], $payloads[106], $payloads[1000] ) );
check( ids_of( $queue ) === array( 1000, 105, 101 ), 'Radio independent of discovery; spaces artists and honors exclusions' );
$payloads[101]['sourceUrl'] = $payloads[1000]['sourceUrl'];
check( ids_of( Nfinite_Creators_Programming::radio( array( $payloads[101], $payloads[1000] ) ) ) === array( 1000 ), 'Duplicate audio respects selected priority' );
post_fixture( 201, 'nfinite_show', 1 ); post_fixture( 202, 'nfinite_episode', 1 ); post_fixture( 203, 'nfinite_video', 2 );
$meta[202]['_nfinite_episode_show_id'] = 201;
$tv = array( array( 'object_id' => 203, 'creator_id' => 2 ), array( 'object_id' => 202, 'show_id' => 201, 'creator_id' => 1 ) );
rules( 'tv', array( 'mode' => 'handpicked', 'picks' => array( 201 ) ) );
check( Nfinite_Creators_Programming::tv( $tv, 160 ) === array( $tv[1] ), 'Whole show selection expands to eligible episodes' );
rules( 'tv', array( 'mode' => 'mixed', 'picks' => array( 1 ), 'exclude' => array( 201 ) ) );
check( Nfinite_Creators_Programming::tv( $tv, 160 ) === array( $tv[0] ), 'Show exclusion overrides creator pick' );
rules( 'tv', array( 'mode' => 'mixed', 'picks' => array( 202 ) ) );
check( Nfinite_Creators_Programming::tv( $tv, 1 ) === array( $tv[1] ), 'TV hero follows oldest explicit pick before limiting' );
$posts[201]->post_status = 'private';
check( Nfinite_Creators_Programming::tv( $tv, 160 ) === array( $tv[0] ), 'Private show cannot leak into TV' );
$can_manage = false;
try { Nfinite_Creators_Programming::save(); throw new Exception( 'save allowed' ); } catch ( Exception $e ) { check( $e->getMessage() === 'You cannot change programming.', 'Reject non-admin save' ); }
try { Nfinite_Creators_Programming::search(); throw new Exception( 'search allowed' ); } catch ( Exception $e ) { check( $e->getMessage() === 'Forbidden:403', 'Reject non-admin picker access' ); }
$can_manage = true; $nonce_ok = false;
try { Nfinite_Creators_Programming::save(); throw new Exception( 'nonce ignored' ); } catch ( Exception $e ) { check( $e->getMessage() === 'nonce rejected', 'Reject missing/invalid save nonce' ); }
$nonce_ok = true;
$_POST = array( 'surface' => 'new_releases', 'mode' => 'handpicked', 'rules' => json_encode( array( 'picks' => array( 11, 11, 101, 999999 ), 'exclude' => array( 14 ) ) ), 'artist_cap' => 1000 );
$previous_radio = Nfinite_Creators_Programming::rule( 'radio' );
try { Nfinite_Creators_Programming::save(); } catch ( Exception $e ) { check( $e->getMessage() === 'redirect after save', 'Admin save completes' ); }
$saved = Nfinite_Creators_Programming::rule( 'new_releases' );
check( $saved['picks'] === array( 11 ) && $saved['artist_cap'] === 50, 'Save validates types, deduplicates, bounds caps' );
check( Nfinite_Creators_Programming::rule( 'radio' ) === $previous_radio, 'Saving one section preserves others' );
echo $checks . " programming fixture checks passed.\n";
