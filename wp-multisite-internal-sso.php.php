<?php
/**
 * Plugin Name: WP Multisite Internal SSO
 * Plugin URI:  https://github.com/9ete/wp-multisite-internal-sso
 * Description: Enables automatic login (SSO) for users from one multisite installation to another.
 * Version:     0.1.0
 * Author:      9ete
 * Author URI:  https://petelower.com
 * Network:     true
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'WPMIS_SSO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPMIS_SSO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPMIS_SSO_PLUGIN_DIR . 'inc/class-wp-multisite-internal-sso.php';

/**
 * Initialize the WP_Multisite_Internal_SSO plugin.
 */
function wpmis_sso_init() {
    $GLOBALS['wp_multisite_internal_sso'] = new WP_Multisite_Internal_SSO();
}
add_action( 'plugins_loaded', 'wpmis_sso_init' );