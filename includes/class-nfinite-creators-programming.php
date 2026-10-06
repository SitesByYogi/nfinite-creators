<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Shared editorial selections. These rules never change catalog publication. */
final class Nfinite_Creators_Programming {
    const OPTION = 'nfinite_programming';
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_nfinite_programming', array( __CLASS__, 'save' ) );
        add_action( 'wp_ajax_nfinite_programming_search', array( __CLASS__, 'search' ) );
    }
    public static function surfaces() {
        return array( 'hero' => 'Featured release', 'new_releases' => 'New Releases', 'latest_tracks' => 'Latest Tracks', 'creators' => 'Music creator spotlights', 'radio' => 'PairOfDice Radio', 'tv' => 'PairOfDice TV' );
    }
    public static function rule( $surface ) {
        $all = get_option( self::OPTION, array() );
        return array_merge( array( 'mode' => 'automatic', 'picks' => array(), 'exclude' => array(), 'artist_cap' => 'latest_tracks' === $surface ? 2 : 0, 'release_cap' => 'latest_tracks' === $surface ? 1 : 0, 'override' => false, 'weights' => array(), 'rotation' => array() ), isset( $all[$surface] ) && is_array( $all[$surface] ) ? $all[$surface] : array() );
    }
    public static function types( $surface ) {
        if ( 'tv' === $surface ) { return array( 'nfinite_video', 'nfinite_episode', 'nfinite_show', 'nfinite_creator' ); }
        return array( 'creators' === $surface ? 'nfinite_creator' : ( in_array( $surface, array( 'latest_tracks', 'radio' ), true ) ? 'nfinite_track' : 'nfinite_release' ) );
    }
    public static function visible( $id, $type = '' ) {
        $p = get_post( $id );
        return $p && 'publish' === $p->post_status && '' === $p->post_password && ( ! $type || $p->post_type === $type );
    }
    public static function public_item( $id ) {
        if ( ! self::visible( $id ) ) { return false; }
        $type = get_post_type( $id );
        foreach ( array( 'creator', 'release', 'show' ) as $relation ) {
            $parent = absint( get_post_meta( $id, '_' . $type . '_' . $relation . '_id', true ) );
            if ( $parent && ! self::visible( $parent, 'nfinite_' . $relation ) ) { return false; }
        }
        return true;
    }
    private static function ids( $values, $surface ) {
        if ( ! is_array( $values ) ) { return array(); }
        return array_values( array_filter( array_unique( array_map( 'absint', $values ) ), static function( $id ) use ( $surface ) { return $id && in_array( get_post_type( $id ), self::types( $surface ), true ); } ) );
    }
    /** Page through candidates until a surface fills, even after many exclusions. */
    public static function candidates( $type, $featured = false ) {
        if ( $featured ) {
            foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => -1, 'meta_key' => '_nfinite_creator_featured', 'meta_value' => '1' ) ) as $post ) { yield $post; }
        }
        for ( $page = 1; ; $page++ ) {
            $posts = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 100, 'paged' => $page, 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ) ) );
            foreach ( $posts as $post ) { yield $post; }
            if ( count( $posts ) < 100 ) { break; }
        }
    }
    public static function select( $surface, $limit ) {
        $rule = self::rule( $surface ); $types = self::types( $surface );
        $out = array(); $seen = array(); $artists = array(); $releases = array();
        $append = static function( $p, $picked ) use ( &$out, &$seen, &$artists, &$releases, $rule, $types, $surface ) {
            if ( ! $p || isset( $seen[$p->ID] ) || ! in_array( $p->post_type, $types, true ) || ! self::public_item( $p->ID ) || in_array( $p->ID, $rule['exclude'], true ) ) { return; }
            $seen[$p->ID] = true;
            $track = 'nfinite_track' === $p->post_type;
            $payload = $track ? Nfinite_Creators_Music_Query::track_payload( $p->ID ) : null;
            if ( $track && ( empty( $payload['internalPlayable'] ) || empty( $payload['showInDiscovery'] ) ) ) { return; }
            $creator = absint( get_post_meta( $p->ID, '_' . $p->post_type . '_creator_id', true ) );
            $artist = $creator ? 'id:' . $creator : ( $track && ! empty( $payload['artist'] ) ? 'name:' . strtolower( trim( $payload['artist'] ) ) : 'item:' . $p->ID );
            $release = $track ? absint( get_post_meta( $p->ID, '_nfinite_track_release_id', true ) ) : 0;
            if ( ! ( $picked && $rule['override'] ) ) {
                if ( $rule['artist_cap'] && ( $artists[$artist] ?? 0 ) >= $rule['artist_cap'] ) { return; }
                if ( $release && $rule['release_cap'] && ( $releases[$release] ?? 0 ) >= $rule['release_cap'] ) { return; }
            }
            $artists[$artist] = ( $artists[$artist] ?? 0 ) + 1;
            if ( $release ) { $releases[$release] = ( $releases[$release] ?? 0 ) + 1; }
            $out[] = $track ? $payload : $p;
        };
        if ( 'automatic' !== $rule['mode'] ) {
            foreach ( $rule['picks'] as $id ) { $append( get_post( $id ), true ); if ( count( $out ) >= $limit ) { return $out; } }
        }
        if ( 'handpicked' !== $rule['mode'] ) {
            foreach ( self::candidates( $types[0], 'creators' === $surface ) as $p ) { $append( $p, false ); if ( count( $out ) >= $limit ) { break; } }
        }
        return $out;
    }
    /** Radio selections ignore Latest Tracks. Rotation tiers control real station frequency. */
    public static function radio( $queue ) {
        $rule = self::rule( 'radio' ); $map = array(); $url_seen = array();
        foreach ( $queue as $item ) {
            $id = absint( $item['id'] ?? 0 ); $url = strtolower( $item['sourceUrl'] ?? '' );
            if ( ! $id || isset( $url_seen[$url] ) || ! self::public_item( $id ) || in_array( $id, $rule['exclude'], true ) ) { continue; }
            $url_seen[$url] = true; $map[$id] = $item;
        }
        $eligible = array();
        if ( 'automatic' !== $rule['mode'] ) {
            foreach ( $rule['picks'] as $id ) { if ( isset( $map[$id] ) ) { $eligible[$id] = $map[$id]; } }
        }
        if ( 'handpicked' !== $rule['mode'] ) {
            foreach ( $map as $id => $item ) { $eligible[$id] = $item; }
        }
        if ( ! $eligible ) { return array(); }

        $tier_weights = array( 'heavy' => 10, 'medium' => 5, 'light' => 2, 'discovery' => 1 );
        $pool = array();
        foreach ( $eligible as $id => $item ) {
            $tier = sanitize_key( $rule['rotation'][$id] ?? ( in_array( $id, $rule['picks'], true ) ? 'medium' : 'light' ) );
            if ( 'disabled' === $tier ) { continue; }
            $weight = absint( $rule['weights'][$id] ?? ( $tier_weights[$tier] ?? 2 ) );
            $weight = max( 1, min( 25, $weight ) );
            $item['radioRotation'] = $tier; $item['radioWeight'] = $weight;
            $pool[$id] = $item;
        }
        if ( ! $pool ) { return array(); }

        // Build a bounded weighted station cycle. Sampling with replacement makes
        // Heavy records recur more often than Light/Discovery while artist spacing
        // prevents avoidable back-to-back plays.
        $target = min( 240, max( 50, count( $pool ) * 4 ) ); $weighted = array();
        $last_artist = '';
        for ( $n = 0; $n < $target; $n++ ) {
            $choices = $pool;
            if ( count( $choices ) > 1 && $last_artist ) {
                $spaced = array_filter( $choices, static function( $item ) use ( $last_artist ) {
                    $artist = ! empty( $item['creatorId'] ) ? 'id:' . absint( $item['creatorId'] ) : 'name:' . strtolower( trim( $item['artist'] ?? '' ) );
                    return $artist !== $last_artist;
                } );
                if ( $spaced ) { $choices = $spaced; }
            }
            $total = array_sum( array_map( static function( $item ) { return max( 1, absint( $item['radioWeight'] ?? 1 ) ); }, $choices ) );
            $roll = wp_rand( 1, max( 1, $total ) ); $chosen = reset( $choices );
            foreach ( $choices as $item ) {
                $roll -= max( 1, absint( $item['radioWeight'] ?? 1 ) );
                if ( $roll <= 0 ) { $chosen = $item; break; }
            }
            $weighted[] = $chosen;
            $last_artist = ! empty( $chosen['creatorId'] ) ? 'id:' . absint( $chosen['creatorId'] ) : 'name:' . strtolower( trim( $chosen['artist'] ?? '' ) );
        }
        return $weighted;
    }
    public static function space_artists( $queue ) {
        $out = array(); $last = null;
        $key = static function( $item ) { return ! empty( $item['creatorId'] ) ? 'id:' . $item['creatorId'] : strtolower( trim( $item['artist'] ?? '' ) ); };
        while ( $queue ) {
            $index = 0;
            foreach ( $queue as $i => $item ) { if ( $key( $item ) !== $last ) { $index = $i; break; } }
            $next = array_splice( $queue, $index, 1 )[0]; $out[] = $next; $last = $key( $next );
        }
        return $out;
    }
    public static function tv( $items, $limit ) {
        $rule = self::rule( 'tv' ); $picked = array(); $rest = array();
        foreach ( $items as $item ) {
            $ids = array_values( array_filter( array_map( 'absint', array( $item['object_id'] ?? 0, $item['show_id'] ?? 0, $item['creator_id'] ?? 0 ) ) ) );
            if ( array_intersect( $ids, $rule['exclude'] ) ) { continue; }
            foreach ( $ids as $id ) { if ( ! self::public_item( $id ) ) { continue 2; } }
            $rank = null;
            if ( 'automatic' !== $rule['mode'] ) { foreach ( $rule['picks'] as $i => $id ) { if ( in_array( $id, $ids, true ) ) { $rank = $i; break; } } }
            if ( null !== $rank ) { $picked[$rank][] = $item; }
            elseif ( 'handpicked' !== $rule['mode'] ) { $rest[] = $item; }
        }
        ksort( $picked ); $out = array();
        foreach ( $picked as $group ) { $out = array_merge( $out, $group ); }
        return array_slice( array_merge( $out, $rest ), 0, max( 1, absint( $limit ) ) );
    }
    public static function menu() {
        add_menu_page( 'PairOfDice Programming', 'Programming', 'manage_options', 'nfinite-programming', array( __CLASS__, 'page' ), 'dashicons-playlist-audio', 26 );
    }
    public static function save() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'You cannot change programming.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'nfinite_programming' );
        $surface = sanitize_key( $_POST['surface'] ?? '' );
        if ( ! isset( self::surfaces()[$surface] ) ) { wp_die( 'Unknown programming surface.' ); }
        $input = json_decode( wp_unslash( $_POST['rules'] ?? '' ), true );
        if ( ! is_array( $input ) ) { wp_die( 'Invalid selections. No changes were saved.' ); }
        $mode = sanitize_key( $_POST['mode'] ?? '' );
        $rule = array( 'mode' => in_array( $mode, array( 'automatic', 'handpicked', 'mixed' ), true ) ? $mode : 'automatic', 'picks' => self::ids( $input['picks'] ?? array(), $surface ), 'exclude' => self::ids( $input['exclude'] ?? array(), $surface ), 'artist_cap' => min( 50, absint( $_POST['artist_cap'] ?? 0 ) ), 'release_cap' => min( 50, absint( $_POST['release_cap'] ?? 0 ) ), 'override' => ! empty( $_POST['override'] ) );
        $all = get_option( self::OPTION, array() );
        if ( 'radio' === $surface && isset( $all['radio'] ) && is_array( $all['radio'] ) ) {
            $rule['rotation'] = $all['radio']['rotation'] ?? array();
            $rule['weights'] = $all['radio']['weights'] ?? array();
        }
        $all[$surface] = $rule;
        update_option( self::OPTION, $all, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'nfinite-programming', 'surface' => $surface, 'saved' => 1 ), admin_url( 'admin.php' ) ) ); exit;
    }
    private static function label( $id ) {
        $type = get_post_type( $id ); $owner = absint( get_post_meta( $id, '_' . $type . '_creator_id', true ) );
        return html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) . ( $owner ? ' · ' . html_entity_decode( get_the_title( $owner ), ENT_QUOTES, 'UTF-8' ) : '' ) . ' [' . str_replace( 'nfinite_', '', $type ?: 'missing' ) . ' #' . $id . ']';
    }
    public static function search() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Forbidden', 403 ); }
        check_ajax_referer( 'nfinite_programming', 'nonce' );
        $surface = sanitize_key( $_GET['surface'] ?? '' );
        if ( ! isset( self::surfaces()[$surface] ) ) { wp_send_json_error( 'Invalid surface', 400 ); }
        $args = array( 'post_type' => self::types( $surface ), 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 30, 'paged' => max( 1, absint( $_GET['paged'] ?? 1 ) ), 's' => sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ), 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ) );
        $release = absint( $_GET['release'] ?? 0 );
        if ( $release && in_array( $surface, array( 'radio', 'latest_tracks' ), true ) ) { $args['meta_query'] = array( array( 'key' => '_nfinite_track_release_id', 'value' => $release, 'type' => 'NUMERIC' ) ); }
        $query = new WP_Query( $args ); $items = array();
        foreach ( $query->posts as $p ) { $items[] = array( 'id' => $p->ID, 'label' => self::label( $p->ID ) ); }
        wp_send_json_success( array( 'items' => $items, 'more' => $args['paged'] < $query->max_num_pages ) );
    }
    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $surface = sanitize_key( $_GET['surface'] ?? 'latest_tracks' );
        if ( ! isset( self::surfaces()[$surface] ) ) { $surface = 'latest_tracks'; }
        $rule = self::rule( $surface ); $labels = array();
        foreach ( array_merge( $rule['picks'], $rule['exclude'] ) as $id ) { $labels[$id] = self::label( $id ); }
        wp_enqueue_script( 'nfinite-programming', NFINITE_CREATORS_URL . 'admin/js/programming.js', array(), NFINITE_CREATORS_VERSION, true );
        wp_localize_script( 'nfinite-programming', 'NfiniteProgramming', array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'nfinite_programming' ), 'surface' => $surface, 'rule' => $rule, 'labels' => $labels ) );
        ?>
        <div class="wrap"><h1>PairOfDice Programming</h1><p>Choose what gets promoted across the website and player. Catalog browsing, search and release tracklists stay available. Only administrators can save these controls.</p>
        <?php if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success"><p>Programming saved. Clear your site/page cache to refresh cached pages.</p></div>'; } ?>
        <nav class="nav-tab-wrapper" aria-label="Programming sections"><?php foreach ( self::surfaces() as $key => $name ) { echo '<a class="nav-tab ' . ( $key === $surface ? 'nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( array( 'page' => 'nfinite-programming', 'surface' => $key ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $name ) . '</a>'; } ?></nav>
        <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="pod-programming-form">
        <input type="hidden" name="action" value="nfinite_programming"><input type="hidden" name="surface" value="<?php echo esc_attr( $surface ); ?>"><input type="hidden" name="rules" id="pod-rules" value="<?php echo esc_attr( wp_json_encode( $rule ) ); ?>"><?php wp_nonce_field( 'nfinite_programming' ); ?>
        <p><label>Selection mode <select name="mode"><?php foreach ( array( 'automatic' => 'Automatic', 'handpicked' => 'Handpicked only', 'mixed' => 'Mixed: picks then automatic' ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $rule['mode'], $value, false ) . '>' . esc_html( $label ) . '</option>'; } ?></select></label></p>
        <p>Automatic ignores the picks list. Handpicked only can intentionally leave a section empty. Exclusions apply in every mode and always win.</p>
        <?php if ( in_array( $surface, array( 'latest_tracks', 'new_releases' ), true ) ) : ?>
        <p><label>Maximum per artist <input type="number" min="0" max="50" name="artist_cap" value="<?php echo esc_attr( $rule['artist_cap'] ); ?>"></label> <?php if ( 'latest_tracks' === $surface ) : ?><label>Maximum per release <input type="number" min="0" max="50" name="release_cap" value="<?php echo esc_attr( $rule['release_cap'] ); ?>"></label><?php endif; ?> (0 = unlimited)</p>
        <p><label><input type="checkbox" name="override" value="1" <?php checked( $rule['override'] ); ?>> Allow explicit picks to exceed these diversity limits</label></p>
        <?php endif; ?>
        <?php if ( 'latest_tracks' === $surface ) { echo '<p>The existing “Show in Latest / Featured Tracks” checkbox still applies, including to picks. A hidden track remains in its release and the full player catalog.</p>'; } ?>
        <?php if ( 'hero' === $surface ) { echo '<p>The first eligible pick becomes the featured release. Automatic uses the existing Music Hub selection and featured-release flags.</p>'; } ?>
        <?php if ( 'radio' === $surface ) { echo '<p>Radio uses playable direct audio, excluding beats and instrumentals. Picks enter first, with artist spacing when alternatives are available. Latest Tracks visibility does not affect Radio.</p>'; } ?>
        <?php if ( 'tv' === $surface ) { echo '<p>Pick videos or episodes individually, or pick a show/creator to include their eligible TV items. Excluding a show/creator removes their TV items. Items must still have their existing Include on TV flag enabled. The first eligible item becomes the TV hero. Legacy creator videos are controlled through their creator.</p>'; } ?>
        <h2>Find content</h2><p><label>Title <input type="search" id="pod-search" placeholder="Search titles"></label> <?php if ( in_array( $surface, array( 'latest_tracks', 'radio' ), true ) ) : ?><label>Release ID <input type="number" id="pod-release" min="1" placeholder="Optional album ID"></label><?php endif; ?> <button class="button" type="button" id="pod-find">Search</button></p>
        <p id="pod-search-status" role="status" aria-live="polite"></p><div id="pod-search-results"></div><button class="button" type="button" id="pod-more" hidden>Load more</button>
        <p><button class="button" type="button" id="pod-pick-all">Pick all loaded results</button> <button class="button" type="button" id="pod-exclude-all">Exclude all loaded results</button></p>
        <h2>Ordered picks</h2><p>Use Up and Down to set priority. Changes take effect when saved.</p><div id="pod-picks"></div>
        <h2>Excluded from this section</h2><div id="pod-exclusions"></div>
        <?php submit_button( 'Save programming' ); ?></form></div>
        <?php
    }
}
