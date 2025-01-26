<?php
/**
 * Plugin Name: WP Multisite Internal SSO
 * Plugin URI:  https://github.com/9ete/wp-multisite-internal-sso
 * Description: Enables automatic login (SSO) for users from one multisite installation to another -- refactored for REST-based login/logout.
 * Version:     0.2.0
 * Author:      9ete
 * Author URI:  https://petelower.com
 * Network:     true
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WPMIS_SSO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPMIS_SSO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPMIS_SSO_PLUGIN_VERSION', get_file_data( __FILE__, array( 'Version' => 'Version' ) )['Version'] );

// Require needed classes (utils, main SSO, etc.)
require_once WPMIS_SSO_PLUGIN_PATH . 'includes/class-wp-multisite-internal-sso-utils.php';
require_once WPMIS_SSO_PLUGIN_PATH . 'includes/class-wp-multisite-internal-sso.php';  // loads admin, auth, sso sub-classes internally

/**
 * Initialize the plugin (the old plugin used param-based checks, we keep the structure but remove GET param checks).
 */
function wpmis_sso_init() {
    $GLOBALS['wp_multisite_internal_sso'] = new WP_Multisite_Internal_SSO();
}

/**
 * Decide whether to allow plugin code to run on the requested URI.
 */
function wpmisso_allow_request() {
    $utils = new WP_Multisite_Internal_SSO_Utils();
    $utils->debug_message( 'REQUEST: ' . $_SERVER['REQUEST_URI'] . "\n", true );

    $file_requests_to_ignore = [
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
    ];

    foreach ($file_requests_to_ignore as $ignored_file) {
        $pattern = '/' . str_replace(['*', '.'], ['.*', '\.'], $ignored_file) . '$/';
        if (preg_match($pattern, $_SERVER['REQUEST_URI'])) {
            $utils->debug_message( 'Skipping SSO due to request of ' . $ignored_file . ' - URI: ' . $_SERVER['REQUEST_URI'] . "\n" );
            return false;
        }
    }

    return true;
}

/**
 * (Optional) Count how many times we've redirected with "wpmisso_request".
 * You can remove if not needed.
 */
function wpmisso_redirect_cookie_count() {
    if ( isset($_GET['wpmisso_request']) ) {
        $cookie_name  = 'wpmisso_request';
        $cookie_value = isset($_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] + 1 : 1;
        setcookie($cookie_name, $cookie_value, time() + 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
}

// If multisite + allowed request + no ?wpmisso_ignore, then initialize.
if ( is_multisite() && wpmisso_allow_request() && ! isset( $_GET['wpmisso_ignore'] ) ) {
    add_action( 'plugins_loaded', 'wpmis_sso_init' );
}

// Hook into init to run the optional cookie count
add_action('init', 'wpmisso_redirect_cookie_count');

/* ------------------------------------------------------------------
 * NEW: REST ENDPOINTS for login & logout (replace old param-based)
 * ------------------------------------------------------------------ */
add_action('rest_api_init', function () {

    // POST /wp-json/wpmis-sso/v1/login
    register_rest_route(
        'wpmis-sso/v1',
        '/login',
        [
            'methods'  => 'POST',
            'callback' => 'wpmis_sso_rest_login',
        ]
    );

    // POST /wp-json/wpmis-sso/v1/logout
    register_rest_route(
        'wpmis-sso/v1',
        '/logout',
        [
            'methods'  => 'POST',
            'callback' => 'wpmis_sso_rest_logout',
        ]
    );
});

/**
 * Handle REST-based login
 *
 * Expects JSON body: { "username": "...", "password": "..." }
 */
function wpmis_sso_rest_login( WP_REST_Request $request ) {
    $username = $request->get_param('username');
    $password = $request->get_param('password');

    if ( empty($username) || empty($password) ) {
        return new WP_Error(
            'missing_credentials',
            __( 'Username and password required.', 'wp-multisite-internal-sso' ),
            [ 'status' => 400 ]
        );
    }

    $creds = [
        'user_login'    => $username,
        'user_password' => $password,
        'remember'      => true,
    ];
    $user = wp_signon( $creds, is_ssl() );

    if ( is_wp_error($user) ) {
        return new WP_Error(
            'invalid_credentials',
            __( 'Invalid username or password.', 'wp-multisite-internal-sso' ),
            [ 'status' => 401 ]
        );
    }

    // Success: WP will set auth cookies in the response.
    return new WP_REST_Response([
        'status'  => 'success',
        'user_id' => $user->ID,
        'message' => __( 'User logged in successfully.', 'wp-multisite-internal-sso' ),
    ], 200);
}

/**
 * Handle REST-based logout
 */
function wpmis_sso_rest_logout( WP_REST_Request $request ) {
    if ( is_user_logged_in() ) {
        wp_logout();
    }
    // Optionally clear additional cookies here if needed for cross-domain.

    return new WP_REST_Response([
        'status'  => 'success',
        'message' => __( 'User logged out successfully.', 'wp-multisite-internal-sso' ),
    ], 200);
}