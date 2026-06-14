<?php
/**
 * WP Multisite Internal SSO
 *
 * @package           WP_Multisite_Internal_SSO
 * @author            9ete
 * @copyright         9ete
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WP Multisite Internal SSO
 * Plugin URI:        https://github.com/9ete/wp-multisite-internal-sso
 * Description:       Enables automatic login (SSO) for users across sites in a WordPress Multisite network.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            9ete
 * Author URI:        https://petelower.com
 * Network:           true
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-multisite-internal-sso
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'WPMIS_SSO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPMIS_SSO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPMIS_SSO_PLUGIN_VERSION', get_file_data( __FILE__, array( 'Version' => 'Version' ) )['Version'] );

require_once WPMIS_SSO_PLUGIN_PATH . 'includes/class-wp-multisite-internal-sso-utils.php';
require_once WPMIS_SSO_PLUGIN_PATH . 'includes/class-wp-multisite-internal-sso.php';

/**
 * Bootstrap the plugin once all plugins are loaded.
 *
 * @return void
 */
function wpmis_sso_init() {
	$GLOBALS['wp_multisite_internal_sso'] = new WP_Multisite_Internal_SSO();
}

if ( is_multisite() && wpmisso_allow_request() && ! isset( $_GET['wpmisso_ignore'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	add_action( 'plugins_loaded', 'wpmis_sso_init' );
}

/**
 * Determine whether SSO logic should run for the current request.
 *
 * Skips static asset and system requests (favicons, sitemaps, cron, REST, etc.)
 * so the SSO redirect dance never triggers on non-page loads.
 *
 * @return bool True when SSO handling should run for this request.
 */
function wpmisso_allow_request() {

	$utils = new WP_Multisite_Internal_SSO_Utils();

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$utils->debug_message( 'REQUEST: ' . $request_uri . "\n", true );

	$file_requests_to_ignore = array(
		'*.ico',
		'robots.txt',
		'sitemap.xml',
		'wp-cron.php',
		'admin-ajax.php',
		'wp-json',
		'*.png',
		'*.jpg',
		'*.jpeg',
		'*.gif',
		'*.css',
		'*.js',
		'*.woff',
		'*.woff2',
		'*.ttf',
		'*.svg',
		'*.eot',
	);

	foreach ( $file_requests_to_ignore as $ignored_file ) {
		$pattern = '/' . str_replace( array( '*', '.' ), array( '.*', '\.' ), $ignored_file ) . '$/';
		if ( preg_match( $pattern, $request_uri ) ) {
			$utils->debug_message( 'Skipping SSO due to request of ' . $ignored_file . ' - URI: ' . $request_uri . "\n" );
			return false;
		}
	}

	return true;
}

add_action( 'init', 'wpmisso_redirect_cookie_count' );

/**
 * Track how many times the SSO redirect has been attempted for the request.
 *
 * @return void
 */
function wpmisso_redirect_cookie_count() {
	// Public counter on the SSO redirect endpoint; request authenticity is enforced
	// by the signed token, not a form nonce.
	if ( isset( $_GET['wpmisso_request'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cookie_name  = 'wpmisso_request';
		$cookie_value = isset( $_COOKIE[ $cookie_name ] ) ? absint( wp_unslash( $_COOKIE[ $cookie_name ] ) ) + 1 : 1;
		setcookie( $cookie_name, $cookie_value, time() + HOUR_IN_SECONDS, '/', '', is_ssl(), true );
	}
}
