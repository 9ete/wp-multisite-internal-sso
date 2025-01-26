<?php
/**
 * WP Multisite Internal SSO Admin Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WP_Multisite_Internal_SSO_Admin {

    /**
     * Settings Manager.
     *
     * @var WP_Multisite_Internal_SSO_Settings
     */
    private $settings;

    /**
     * SSO Handler.
     *
     * @var WP_Multisite_Internal_SSO_SSO
     */
    private $sso;

    /**
     * Utility Functions.
     *
     * @var WP_Multisite_Internal_SSO_Utils
     */
    private $utils;

    /**
     * Constructor.
     *
     * @param WP_Multisite_Internal_SSO_Settings $settings Settings manager instance.
     * @param WP_Multisite_Internal_SSO_SSO      $sso      SSO handler instance.
     * @param WP_Multisite_Internal_SSO_Utils    $utils    Utility functions instance.
     */
    public function __construct( $settings, $sso, $utils ) {
        $this->settings = $settings;
        $this->sso      = $sso;
        $this->utils    = $utils;
    }

    /**
     * Add admin menu for plugin settings.
     */
    public function add_admin_menu() {
        if (
            is_multisite() &&
            is_super_admin() &&
            $this->settings->get_primary_site_id() === get_current_blog_id()
        ) {
            add_submenu_page(
                'tools.php',
                __( 'WP Multisite Internal SSO Settings', 'wp-multisite-internal-sso' ),
                __( 'Multisite SSO', 'wp-multisite-internal-sso' ),
                'manage_network_options',
                'wp-multisite-internal-sso',
                array( $this->settings, 'settings_page' )
            );
        }
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'settings_page_wp-multisite-internal-sso' !== $hook ) {
            return;
        }
        wp_enqueue_script(
            'wpmis-sso-admin-js',
            WPMIS_SSO_PLUGIN_URL . 'assets/js/wpmis-sso-admin.js',
            array(),
            WPMIS_SSO_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Display user login status and action buttons in the admin bar or admin page.
     */
    public function display_user_status() {

        // Show how many times we've tried to redirect (if any).
        $cookie_name = $this->settings->get_redirect_cookie_name();
        $clear_cookies_button = '<button onclick="document.cookie = \'' . esc_js( $cookie_name ) . '=; expires=Thu, 01 Jan 1970 00:00:00 GMT;\';">' .
            esc_html__( 'Clear Cookies', 'wp-multisite-internal-sso' ) . '</button>';

        if ( isset( $_COOKIE[ $cookie_name ] ) ) {
            echo '<div class="wpmis-sso-status redirect-attempt">';
            echo esc_html__( 'Redirect Attempted - ', 'wp-multisite-internal-sso' ) .
                 esc_html( $_COOKIE[ $cookie_name ] );
            echo '</div>';
        }

        // Basic logged-in or not display
        if ( ! is_user_logged_in() ) {
            echo '<div class="wpmis-sso-status not-logged-in">' .
                esc_html__( 'Not logged in - ', 'wp-multisite-internal-sso' ) .
                esc_url( get_site_url() ) .
                '</div>';
        } else {
            echo '<div class="wpmis-sso-status logged-in">' .
                esc_html__( 'Logged in - ', 'wp-multisite-internal-sso' ) .
                esc_url( get_site_url() ) .
                '</div>';
        }

        // Display some helpful actions or notes
        echo '<div class="wpmis-sso-actions">';

        if ( is_user_logged_in() ) {
            // Instead of a param-based "Logout On All Sites," 
            // you can either show nothing or show a note linking 
            // to your new REST-based approach:
            ?>
            <p>
                <?php esc_html_e( 'Use the REST endpoint /wp-json/wpmis-sso/v1/logout to log out, or simply log out from your WordPress admin bar.', 'wp-multisite-internal-sso' ); ?>
            </p>
            <?php
        } else {
            // Not logged in
            echo '<a href="' . esc_url( wp_login_url() ) . '">' .
                 esc_html__( 'Login', 'wp-multisite-internal-sso' ) .
                 '</a>';
            echo '<span class="divider"> | </span>';
        }

        // If redirect cookie is set
        if ( isset( $_COOKIE[ $cookie_name ] ) ) {
            echo 'SSO Login Attempted - ' . $clear_cookies_button;
            echo '<span class="divider"> | </span>';
        }

        echo '</div>';
    }
}