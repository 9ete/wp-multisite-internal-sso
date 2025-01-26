<?php
/**
 * WP Multisite Internal SSO SSO Handling Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WP_Multisite_Internal_SSO_SSO {

    /**
     * @var WP_Multisite_Internal_SSO_Settings
     */
    private $settings;

    /**
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
     * Called on template_redirect. Old code used ?wpmssso_* for auto login.
     * We remove that logic so SSO is now via REST.
     */
    public function check_sso() {
        // We skip everything if is_admin or DOING_AJAX
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            $this->utils->debug_message( 'Skipping SSO logic for admin or AJAX requests.' );
            return;
        }
        // *** All the old param-based checks are removed. ***
        $this->utils->debug_message( 'check_sso() called, but param-based SSO is disabled. Use REST endpoints instead.' );
    }

    /**
     * This logic is effectively disabled. No param-based approach.
     */
    private function handle_primary_site_logic() {
        // Disabled
    }

    /**
     * Also disabled. We won't redirect to primary site with ?wpmssso_redirect=1
     */
    private function handle_secondary_site_logic() {
        // Disabled
    }

    /**
     * The auto_login_user() methods are also not needed if we're strictly using REST.
     * We'll leave them but comment out the content for reference.
     */
    private function auto_login_user( $user_id, $token, $time, $return_url = false ) {
        // Disabled (no param-based SSO)
    }

    /**
     * Example function if you still need to generate or verify a token for some reason.
     */
    private function generate_sso_token( $user_id, $time ) {
        $data = $user_id . '|' . $time . '|' . AUTH_SALT;
        return wp_hash( $data, 'auth' );
    }

    private function verify_sso_token( $user_id, $token, $time ) {
        // Disabled in this context
        return false;
    }

    /**
     * (Optional) If you do want to keep the "redirect cookie" concept:
     */
    private function set_redirect_cookie() {
        // disabled
    }

    private function clear_redirect_cookie() {
        // disabled
    }
}