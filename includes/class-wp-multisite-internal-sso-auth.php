<?php
/**
 * WP Multisite Internal SSO Authentication Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WP_Multisite_Internal_SSO_Auth {

    /**
     * Settings Manager.
     *
     * @var WP_Multisite_Internal_SSO_Settings
     */
    private $settings;

    /**
     * Utility Functions.
     *
     * @var WP_Multisite_Internal_SSO_Utils
     */
    private $utils;

    /**
     * Constructor.
     *
     * @param WP_Multisite_Internal_SSO_Settings $settings
     * @param WP_Multisite_Internal_SSO_Utils    $utils
     */
    public function __construct( $settings, $utils ) {
        $this->settings = $settings;
        $this->utils    = $utils;
    }

    /**
     * Handle any leftover actions if needed. Previously handled '?forcelogout' or '?clear_cookies'.
     * Now we do nothing here, because logout is via REST.
     */
    public function handle_actions() {
        // Old param-based logic removed
    }

    /**
     * Log the user in from some user identifier. 
     * (Still used by other parts of the plugin if needed.)
     *
     * @param int|WP_User $user_identifier
     */
    public function log_user_in( $user_identifier ) {
        if ( is_object( $user_identifier ) && isset( $user_identifier->ID ) ) {
            $user_id = intval( $user_identifier->ID );
        } elseif ( is_numeric( $user_identifier ) ) {
            $user_id = intval( $user_identifier );
        } else {
            $this->utils->debug_message( 'Invalid user identifier provided to log_user_in.' );
            return;
        }

        $user = get_user_by( 'ID', $user_id );
        if ( $user ) {
            wp_set_current_user( $user->ID );
            wp_set_auth_cookie( $user->ID );
            do_action( 'wp_login', $user->user_login, $user );
            $this->utils->debug_message( 'User ID ' . $user->ID . ' logged in successfully.' );
        } else {
            $this->utils->debug_message( 'User not found for ID: ' . $user_id );
        }
    }

    /**
     * The old logout_user() and clear_auth_cookies() remain, but we no longer trigger them with GET params.
     * You can still call them internally if you want an "all-site logout" from your new REST function.
     */
    private function logout_user() {
        $this->utils->debug_message( 'Logging out user from all sites.' );
        if ( is_user_logged_in() ) {
            global $wpdb;
            $user_id = get_current_user_id();
            $blogs = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
            if ( $blogs ) {
                foreach ( $blogs as $blog_id ) {
                    switch_to_blog( $blog_id );
                    $this->clear_auth_cookies();
                    restore_current_blog();
                }
            }
            wp_logout();
            $this->utils->wpmis_wp_redirect( home_url() );
            exit;
        }
    }

    private function clear_auth_cookies() {
        $this->utils->debug_message( 'Clearing authentication cookies.' );
        wp_clear_auth_cookie();

        setcookie( LOGGED_IN_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( LOGGED_IN_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( SECURE_AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( SECURE_AUTH_COOKIE, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( 'wordpress_logged_in_' . COOKIEHASH, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( 'wordpress_logged_in_' . COOKIEHASH, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), true );
        setcookie( $this->settings->get_redirect_cookie_name(), '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), false );

        // Destroy session tokens if available
        if ( $user_id = get_current_user_id() ) {
            $session_manager = WP_Session_Tokens::get_instance( $user_id );
            $session_manager->destroy_all();
            delete_user_meta( $user_id, 'session_tokens' );
        }
    }
}