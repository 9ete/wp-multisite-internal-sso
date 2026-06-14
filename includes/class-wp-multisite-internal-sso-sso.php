<?php
/**
 * WP Multisite Internal SSO SSO Handling Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles the cross-site SSO redirect flow and token issuance/verification.
 */
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
	 * @param string  $redirect_to The redirect destination URL.
	 * @param string  $request     The requested redirect destination URL passed as a parameter.
	 * @param WP_User $user        WP_User object.
	 * @return string Redirect URL.
	 */
	public function wpmis_sso_login_redirect( $redirect_to, $request, $user ) {
		if ( isset( $user->roles ) && is_array( $user->roles ) ) {

			$this->utils->debug_message( 'wpmis_sso_login_redirect: ' );
			$this->utils->debug_message( ' - Redirect to: ' . $redirect_to );
			$this->utils->debug_message( ' - Request: ' . $request );
			$this->utils->debug_message( 'User ID: ' . $user->ID );

			// Prevent redirect if SSO parameters are present.
			if ( isset( $_GET['wpmssso_redirect'] ) || isset( $_GET['wpmssso_user'] ) ) {
				return $redirect_to; // Bypass SSO redirect.
			} else {
				$this->utils->debug_message( 'No SSO parameters found, proceeding with login redirect.' );
			}

			if ( $this->settings->get_primary_site_id() !== get_current_blog_id() ) {
				if ( ! is_user_member_of_blog( $user->ID, $this->settings->get_primary_site_id() ) ) {
					$this->utils->debug_message( 'User not a member of primary site.' );
				} else {
					// Redirect to primary site with auto-login payload.
					$this->clear_redirect_cookie();
					$this->redirect_user_with_auto_login_payload( $user->ID, $this->settings->get_primary_site(), $this->settings->get_secondary_sites()[0] );
				}
			} else {
				$this->utils->debug_message( 'Primary site login successful, redirecting to home page.' );
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
		$this->utils->debug_message( __( 'Running check_sso ' . $current_host, 'wp-multisite-internal-sso' ) );

		if ( $current_host === $this->settings->get_primary_site() ) {
			$this->handle_primary_site_logic();
		} elseif ( in_array( $current_host, $this->settings->get_secondary_sites(), true ) ) {
			$this->handle_secondary_site_logic();
		} else {
			$this->utils->debug_message( __( 'No SSO logic for current host.', 'wp-multisite-internal-sso' ) );
			$this->utils->debug_message( ' - Current Host: ' . $current_host );
			$this->utils->debug_message( ' - Primary Site: ' . $this->settings->get_primary_site() );
			$this->utils->debug_message( ' - Secondary Sites: ' . implode( ', ', $this->settings->get_secondary_sites() ) );
		}
	}

	/**
	 * Handle primary site SSO logic.
	 */
	private function handle_primary_site_logic() {
		$this->utils->debug_message( __( 'Running handle_primary_site_logic', 'wp-multisite-internal-sso' ) );
		if ( isset( $_GET['wpmssso_redirect'] ) && '1' === $_GET['wpmssso_redirect'] ) {
			if ( is_user_logged_in() ) {
				$this->utils->debug_message( __( 'User logged in on primary site.', 'wp-multisite-internal-sso' ) );
				$this->redirect_user_with_auto_login_payload( wp_get_current_user()->ID, $_GET['wpmssso_return'] );
			} else {
				$this->utils->debug_message( __( 'User not logged in on primary site. Redirecting to secondary site.', 'wp-multisite-internal-sso' ) );
				$this->utils->wpmis_wp_redirect( $this->settings->get_secondary_sites()[0] );
				exit;
			}
		}
		if ( isset( $_GET['wpmssso_user'], $_GET['wpmssso_token'], $_GET['wpmssso_time'] ) ) {

			// If the user is logged in, send to the home page.
			if ( is_user_logged_in() ) {
				$this->utils->debug_message( __( 'User already logged in on primary site.', 'wp-multisite-internal-sso' ) );
				$this->utils->wpmis_wp_redirect( home_url() );
				exit;
			}

			$auto_login_return = isset( $_GET['wpmssso_return'] ) ? $_GET['wpmssso_return'] : false;
			$this->utils->debug_message( __( 'Received SSO token on primary site.', 'wp-multisite-internal-sso' ) );
			$this->auto_login_user( $_GET['wpmssso_user'], $_GET['wpmssso_token'], $_GET['wpmssso_time'], $auto_login_return );
		}
	}

	/**
	 * Redirect the user to a destination site with an auto-login payload.
	 *
	 * @param int    $user_id    User ID to authenticate at the destination.
	 * @param string $dest_url   Destination site URL.
	 * @param string $return_url Optional URL to return to after auto-login.
	 */
	private function redirect_user_with_auto_login_payload( $user_id, $dest_url, $return_url = false ) {
		$this->utils->debug_message( 'Redirecting user with auto login payload.' );
		$this->utils->debug_message( ' - User ID: ' . $user_id );
		$this->utils->debug_message( ' - Destination: ' . $dest_url );
		$this->utils->debug_message( ' - Return URL: ' . $return_url );

		$this->utils->debug_message( 'Sending token to ' . $dest_url . ' site for user ID ' . $user_id . ' with return URL ' . $return_url );
		$redirect_url = $this->get_auto_login_url_with_payload( $user_id, time(), $dest_url, $return_url );

		$this->utils->debug_message( 'Redirecting to ' . $redirect_url );

		$this->utils->wpmis_wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Generate auto login URL with payload.
	 *
	 * @param int    $user_id    User ID.
	 * @param int    $time       Timestamp.
	 * @param string $dest_url   Destination URL.
	 * @param string $return_url Optional return URL.
	 * @return string Auto login URL.
	 */
	public function get_auto_login_url_with_payload( $user_id, $time, $dest_url, $return_url = false ) {

		if ( ! $user_id ) {
			$this->utils->debug_message( __( 'User ID not provided (get_auto_login_url_with_payload).', 'wp-multisite-internal-sso' ) );
			return;
		}

		$this->utils->debug_message( __( 'Generating auto login URL with payload.', 'wp-multisite-internal-sso' ) );
		$url_payload = add_query_arg(
			array(
				'wpmssso_user'   => rawurlencode( $user_id ),
				'wpmssso_token'  => rawurlencode( $this->generate_sso_token( $user_id, $time ) ),
				'wpmssso_time'   => absint( $time ),
				'wpmssso_return' => $return_url ? rawurlencode( $return_url ) : false,
			),
			esc_url_raw( wp_unslash( $dest_url ) )
		);
		$this->utils->debug_message( ' - URL Payload: ' . $url_payload );
		return $url_payload;
	}

	/**
	 * Handle secondary site SSO logic.
	 */
	private function handle_secondary_site_logic() {
		$this->utils->debug_message( __( 'Running SSO logic for secondary site.', 'wp-multisite-internal-sso' ) );
		if ( is_user_logged_in() ) {
			$this->utils->debug_message( __( 'User already logged in on ' . get_site_url() . ' ', 'wp-multisite-internal-sso' ) );
			return;
		}

		if ( isset( $_GET['wpmssso_user'], $_GET['wpmssso_token'], $_GET['wpmssso_time'] ) ) {
			$this->auto_login_user( $_GET['wpmssso_user'], $_GET['wpmssso_token'], $_GET['wpmssso_time'] );
		} elseif ( isset( $_COOKIE[ $this->settings->get_redirect_cookie_name() ] ) ) {
				$this->utils->debug_message( __( 'Redirect already attempted on ' . get_site_url() . ' No further action.', 'wp-multisite-internal-sso' ) );
		} else {
			$this->initiate_sso_auth_redirect();
		}
	}

	/**
	 * Initiate SSO authentication redirect.
	 */
	private function initiate_sso_auth_redirect() {
		$this->set_redirect_cookie();
		$this->utils->debug_message( __( 'Redirecting to primary site for SSO.', 'wp-multisite-internal-sso' ) );
		$redirect_args = array(
			'wpmssso_redirect' => '1',
			'wpmssso_return'   => urlencode( $this->get_current_site_url() ),
		);
		$this->utils->wpmis_wp_redirect( $this->settings->get_primary_site(), $redirect_args );
		exit;
	}

	/**
	 * Auto login user based on SSO token.
	 *
	 * @param int    $wpmssso_user  User ID.
	 * @param string $wpmssso_token Token to verify.
	 * @param int    $wpmssso_time  Timestamp.
	 * @param string $return_url     Optional return URL.
	 */
	private function auto_login_user( $wpmssso_user, $wpmssso_token, $wpmssso_time, $return_url = false ) {
		$user_id = intval( $wpmssso_user );
		$token   = sanitize_text_field( wp_unslash( $wpmssso_token ) );
		$time    = absint( $wpmssso_time );

		$this->utils->debug_message( __( 'Attempting to auto login user on ' . get_site_url() . ' ID ' . $user_id . ' ' . $time . ' ' . $token, 'wp-multisite-internal-sso' ) );

		if ( $this->verify_sso_token( $user_id, $token, $time ) ) {
			// Log the user in.
			$auth = new WP_Multisite_Internal_SSO_Auth( $this->settings, $this->utils );
			$auth->log_user_in( $user_id ); // Pass the user ID rather than a WP_User object.

			$this->clear_redirect_cookie();
			$this->utils->debug_message( __( 'Successfully logged in user on ' . get_site_url() . ' ', 'wp-multisite-internal-sso' ) . ' ' . $user_id );

			if ( $return_url ) {
				$this->utils->wpmis_wp_redirect( $return_url );
				exit;
			}
			wp_redirect( remove_query_arg( array( 'wpmssso_user', 'wpmssso_token', 'wpmssso_time' ) ) );
			exit;
		} else {
			$this->utils->debug_message( __( 'Invalid or expired token for user on ' . get_site_url() . ' ', 'wp-multisite-internal-sso' ) . ' ' . $user_id );
			return;
		}
	}

	/**
	 * Generate SSO token.
	 *
	 * @param int $user_id User ID.
	 * @param int $time    Timestamp.
	 * @return string Generated token.
	 */
	private function generate_sso_token( $user_id, $time ) {
		$data = $user_id . '|' . $time . '|' . AUTH_SALT;
		$hash = wp_hash( $data, 'auth' );
		$this->utils->debug_message( __( 'Generating SSO token.', 'wp-multisite-internal-sso' ) );
		return $hash;
	}

	/**
	 * Verify SSO token.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Token to verify.
	 * @param int    $time    Timestamp.
	 * @return bool True if valid, false otherwise.
	 */
	private function verify_sso_token( $user_id, $token, $time ) {
		if ( ( time() - $time ) > $this->settings->get_token_expiration() ) {
			return false;
		}
		$expected = $this->generate_sso_token( $user_id, $time );
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
