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
     * @param WP_Multisite_Internal_SSO_Settings $settings Settings manager instance.
     * @param WP_Multisite_Internal_SSO_Utils    $utils    Utility functions instance.
     */
    public function __construct( $settings, $utils ) {
        $this->settings = $settings;
        $this->utils    = $utils;
    }

    /**
     * Handle login redirection based on user roles.
     *
     * @param string $redirect_to The redirect destination URL.
     * @param string $request     The requested redirect destination URL passed as a parameter.
     * @param WP_User $user        WP_User object.
     * @return string Redirect URL.
     */
    public function wpmis_sso_login_redirect( $redirect_to, $request, $user ) {
        
        $this->utils->debug_message( 'Redirect to: ' . $redirect_to );
        $this->utils->debug_message( 'Request: ' . $request );
        $this->utils->debug_message( 'User: ' . print_r( $user, true ) );

        if ( isset( $user->roles ) && is_array( $user->roles ) ) {

            if ( ! is_user_member_of_blog( $user->ID, $this->settings->get_primary_site_id() ) ) {
                $this->utils->debug_message( 'User not a member of primary site.' );
            } else {
                $this->utils->debug_message( 'User is a member of primary site redirecting and logging in...' );
                $this->clear_redirect_cookie();
                // $redirect_url =  $this->get_auto_login_url_with_payload( $user->user_login, time(), $this->settings->get_primary_site() );
                
                // $this->utils->debug_message( 'Redirecting to: ' . $redirect_url );
                // wp_redirect( $redirect_url );

                $this->redirect_user_with_auto_login_payload($user, $this->settings->get_primary_site(), $this->settings->get_secondary_sites()[0]);
            }

            if ( in_array( 'administrator', $user->roles, true ) ) {
                return admin_url();
            } else {
                return home_url();
            }
        } else {
            return $redirect_to;
        }
    }

    /**
     * Check and handle SSO logic on 'template_redirect' hook.
     */
    public function check_sso() {
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            $this->utils->debug_message( __( 'Skipping SSO logic for admin and AJAX requests.', 'wp-multisite-internal-sso' ) );
            return;
        }

        $current_host = $this->get_current_site_url();

        if ( $current_host === $this->settings->get_primary_site() ) {
            $this->utils->debug_message( __( 'Running SSO logic for primary site.', 'wp-multisite-internal-sso' ) );
            $this->handle_primary_site_logic();
        } elseif ( in_array( $current_host, $this->settings->get_secondary_sites(), true ) ) {
            $this->utils->debug_message( __( 'Running SSO logic for secondary site.', 'wp-multisite-internal-sso' ) );
            $this->handle_secondary_site_logic();
        } else {
            $this->utils->debug_message( __( 'No SSO logic for current host.', 'wp-multisite-internal-sso' ) . ' ' . $current_host . ' ' . $this->settings->get_primary_site() );
        }
    }

    /**
     * Handle primary site SSO logic.
     */
    private function handle_primary_site_logic() {
        if ( isset( $_GET['wpmssso_redirect'] ) && '1' === $_GET['wpmssso_redirect'] ) {
            if ( is_user_logged_in() ) {
                $this->redirect_user_with_auto_login_payload(wp_get_current_user(), $_GET['wpmssso_return'] );
            } else {
                $this->utils->debug_message( __( 'User not logged in on primary site. Redirecting to secondary site.', 'wp-multisite-internal-sso' ) );
                wp_redirect( $this->settings->get_secondary_sites()[0] );
                exit;
            }
        }
        if ( isset( $_GET['wpmssso_user'], $_GET['wpmssso_token'], $_GET['wpmssso_time'] ) ) {
            $this->utils->debug_message( __( 'Received SSO token on primary site.', 'wp-multisite-internal-sso' ) );
            $this->auto_login_user($_GET['wpmssso_user'], $_GET['wpmssso_token'], $_GET['wpmssso_time'], $_GET['wpmssso_return'] );
        }
    }

    /**
     * Redirect user with auto login payload.
     */
    private function redirect_user_with_auto_login_payload($user, $dest_url, $return_url = false) {
        if ( ! empty( $return_url ) ) {
            $this->utils->debug_message( __( 'Sending token to secondary site for user', 'wp-multisite-internal-sso' ) . ' ' . $user->user_login . ' ' . $return_url );
            wp_redirect( $this->get_auto_login_url_with_payload($user->user_login, time(), $dest_url, $return_url) );
            exit;
        } else {
            $this->utils->debug_message( __( 'No return URL found for SSO.', 'wp-multisite-internal-sso' ) );
        }
    }

    /**
     * Generate auto login URL with payload.
     *
     * @param string $user_login User login name.
     * @param int    $time       Timestamp.
     * @return string Auto login URL.
     */
    public function get_auto_login_url_with_payload($user_login, $time, $dest_url, $return_url = false) {

        if ( ! $user_login ) {
            $this->utils->debug_message( __( 'User login name not provided (get_auto_login_url_with_payload) .', 'wp-multisite-internal-sso' ) );
            return;
        }

        $this->utils->debug_message( __( 'Generating auto login URL with payload.', 'wp-multisite-internal-sso' ) );
        return add_query_arg(
            array(
                'wpmssso_user'  => rawurlencode( $user_login ),
                'wpmssso_token' => rawurlencode( $this->generate_sso_token( $user_login, $time ) ),
                'wpmssso_time'  => absint( $time ),
                'wpmssso_return' => $return_url ? rawurlencode( $return_url ) : false
            ),
            esc_url_raw( wp_unslash( $dest_url ) )
        );
    }

    /**
     * Handle secondary site SSO logic.
     */
    private function handle_secondary_site_logic() {
        if ( is_user_logged_in() ) {
            $this->utils->debug_message( __( 'User already logged in on '  . get_site_url() . ' ', 'wp-multisite-internal-sso' ) );
            return;
        }

        if ( isset( $_GET['wpmssso_user'], $_GET['wpmssso_token'], $_GET['wpmssso_time'] ) ) {
            $this->auto_login_user($_GET['wpmssso_user'], $_GET['wpmssso_token'], $_GET['wpmssso_time'] );
        } else {
            if ( ! isset( $_COOKIE[ $this->settings->get_redirect_cookie_name() ] ) ) {
                $this->set_redirect_cookie();
                $this->utils->debug_message( __( 'Redirecting to primary site for SSO.', 'wp-multisite-internal-sso' ) );
                $redirect_url = add_query_arg( 'wpmssso_redirect', '1', $this->settings->get_primary_site() );
                $redirect_url = add_query_arg( 'wpmssso_return', urlencode( $this->get_current_site_url() ), $redirect_url );
                wp_redirect( $redirect_url );
                exit;
            } else {
                $this->utils->debug_message( __( 'Redirect already attempted on ' . get_site_url() . ' No further action.', 'wp-multisite-internal-sso' ) );
            }
        }
    }

    /**
     * Auto login user based on SSO token.
     *
     * @param string $wpmssso_user  User login name.
     * @param string $wpmssso_token Token to verify.
     * @param int    $wpmssso_time  Timestamp.
     */
    private function auto_login_user($wpmssso_user, $wpmssso_token, $wpmssso_time, $return_url = false) {
        $user_login = sanitize_user( wp_unslash( $wpmssso_user ), true );
        $token      = sanitize_text_field( wp_unslash( $wpmssso_token ) );
        $time       = absint( $wpmssso_time );

        $this->utils->debug_message( __( 'Attempting to auto login user on ' . get_site_url() . ' ' . $user_login . ' ' . $time . ' ' . $token, 'wp-multisite-internal-sso' ) . ' ' . $user_login );

        if ( $this->verify_sso_token( $user_login, $token, $time ) ) {
            // Log the user in
            $auth = new WP_Multisite_Internal_SSO_Auth( $this->settings, $this->utils );
            $auth->log_user_in( $user_login );

            $this->clear_redirect_cookie();
            $this->utils->debug_message( __( 'Successfully logged in user on' . get_site_url() . ' ', 'wp-multisite-internal-sso' ) . ' ' . $user_login );

            if ( $return_url ) {
                wp_redirect( $return_url );
                exit;
            }
            wp_redirect( remove_query_arg( array( 'wpmssso_user', 'wpmssso_token', 'wpmssso_time' ) ) );
            exit;
        } else {
            $this->utils->debug_message( __( 'Invalid or expired token for user on ' . get_site_url() . ' ', 'wp-multisite-internal-sso' ) . ' ' . $user_login );
            return;
        }
    }

    /**
     * Generate SSO token.
     *
     * @param string $user_login User login name.
     * @param int    $time       Timestamp.
     * @return string Generated token.
     */
    private function generate_sso_token( $user_login, $time ) {
        $data = $user_login . '|' . $time . '|' . AUTH_SALT;
        $hash = wp_hash( $data, 'auth' );
        $this->utils->debug_message( __( 'Generating SSO token.', 'wp-multisite-internal-sso' ) );
        return $hash;
    }

    /**
     * Verify SSO token.
     *
     * @param string $user_login User login name.
     * @param string $token      Token to verify.
     * @param int    $time       Timestamp.
     * @return bool True if valid, false otherwise.
     */
    private function verify_sso_token( $user_login, $token, $time ) {
        if ( ( time() - $time ) > $this->settings->get_token_expiration() ) {
            return false;
        }
        $expected = $this->generate_sso_token( $user_login, $time );
        $this->utils->debug_message( __( 'Verifying SSO token.', 'wp-multisite-internal-sso' ) );
        return hash_equals( $expected, $token );
    }

    /**
     * Set redirect cookie.
     */
    private function set_redirect_cookie() {
        setcookie( $this->settings->get_redirect_cookie_name(), '1', time() + 300, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), false );
        $this->utils->debug_message( __( 'Redirect cookie set.', 'wp-multisite-internal-sso' ) );
    }

    /**
     * Clear redirect cookie.
     */
    private function clear_redirect_cookie() {
        setcookie( $this->settings->get_redirect_cookie_name(), '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, $this->settings->are_secure_cookies_enabled(), false );
        $this->utils->debug_message( __( 'Redirect cookie cleared.', 'wp-multisite-internal-sso' ) );
    }

    /**
     * Get the current site URL.
     *
     * @return string Current site URL.
     */
    private function get_current_site_url() {
        return trailingslashit( home_url() );
    }
}