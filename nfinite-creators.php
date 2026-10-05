<?php
/**
 * Plugin Name:       Nfinite Creators
 * Plugin URI:        https://sitesbyyogi.com/
 * Description:       Creator profiles, frontend profile management, portfolios, audio playlists, and Creator Kits/EPKs for the WPNfinite ecosystem.
 * Version:           0.58.15
 * Author:            SitesByYogi
 * Author URI:        https://sitesbyyogi.com/
 * License:           GPL-2.0+
 * Text Domain:       nfinite-creators
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NFINITE_CREATORS_VERSION', '0.58.15' );
define( 'NFINITE_CREATORS_FILE', __FILE__ );
define( 'NFINITE_CREATORS_DIR', plugin_dir_path( __FILE__ ) );
define( 'NFINITE_CREATORS_URL', plugin_dir_url( __FILE__ ) );

require_once NFINITE_CREATORS_DIR . 'includes/class-nfinite-creators.php';

register_activation_hook( __FILE__, array( 'Nfinite_Creators', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Nfinite_Creators', 'deactivate' ) );

Nfinite_Creators::instance();
