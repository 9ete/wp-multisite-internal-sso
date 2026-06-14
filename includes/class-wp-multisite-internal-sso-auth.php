<?php
/**
 * WP Multisite Internal SSO Authentication Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Authenticates SSO users and handles logout / cookie-clearing actions.
 */
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
	 * @param WP_Multisite_Internal_SSO_Settings $settings Settings manager instance.
	 * @param WP_Multisite_Internal_SSO_Utils    $utils    Utility functions instance.
	 */
	public function __construct( $settings, $utils ) {
		$this->settings = $settings;
		$this->utils    = $utils;
	}

	/**
	 * Handle nonce verification and actions.
	 */
	public function handle_actions() {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( '' !== $nonce ) {
			if ( isset( $_GET['forcelogout'] ) && 'true' === $_GET['forcelogout'] ) {
				if ( ! wp_verify_nonce( $nonce, 'wpmis_sso_logout' ) ) {
					wp_die( esc_html__( 'Nonce verification failed.', 'wp-multisite-internal-sso' ) );
				}
				$this->logout_user();
			}

			if ( isset( $_GET['clear_cookies'] ) && 'true' === $_GET['clear_cookies'] ) {
				if ( ! wp_verify_nonce( $nonce, 'wpmis_sso_clear_cookies' ) ) {
					wp_die( esc_html__( 'Nonce verification failed.', 'wp-multisite-internal-sso' ) );
				}
				$this->clear_auth_cookies();
			}
		} elseif ( isset( $_GET['forcelogout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->utils->wpmis_wp_redirect(
				add_query_arg(
					array(
						'forcelogout' => 'true',
						'_wpnonce'    => wp_create_nonce( 'wpmis_sso_logout' ),
					)
				)
			);
		}
	}

	/**
	 * Log the user in.
	 *
	 * @param int|WP_User $user_identifier User ID (int) or WP_User object of the user to log in.
	 */
	public function log_user_in( $user_identifier ) {
		// Determine if $user_identifier is a WP_User object or a user ID.
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
			// Intentionally fire WordPress core's wp_login action on programmatic SSO login.
			do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$this->utils->debug_message( 'User ID ' . $user->ID . ' (' . $user->user_login . ') logged in successfully.' );
		} else {
			$this->utils->debug_message( 'User not found for ID: ' . $user_id );
		}
	}

	/**
	 * Logout user from all sites.
	 */
	private function logout_user() {
		$this->utils->debug_message( __( 'Logging out user from all sites.', 'wp-multisite-internal-sso' ) );

		if ( is_user_logged_in() ) {
			$blog_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( (int) $blog_id );
				$this->clear_auth_cookies();
				restore_current_blog();
			}

			wp_logout();
			$this->utils->wpmis_wp_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Clear authentication cookies.
	 */
	private function clear_auth_cookies() {
		$this->utils->debug_message( __( 'Clearing authentication cookies.', 'wp-multisite-internal-sso' ) );

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

		$this->utils->debug_message( __( 'Authentication cookies cleared.', 'wp-multisite-internal-sso' ) );

		$user_id = get_current_user_id();
		if ( $user_id ) {
			$this->utils->debug_message( __( 'Destroying session for user.', 'wp-multisite-internal-sso' ) . ' ' . $user_id );
			$session_manager = WP_Session_Tokens::get_instance( $user_id );
			$session_manager->destroy_all();
		}

		if ( function_exists( 'delete_user_meta' ) && $user_id ) {
			$this->utils->debug_message( __( 'Deleting user meta for user.', 'wp-multisite-internal-sso' ) . ' ' . $user_id );
			delete_user_meta( $user_id, 'session_tokens' );
		}
		// If coming from the Clear Cookies action, a source URL may be present; only
		// honour it when it is a configured secondary site (prevents open redirects).
		// Nonce is verified upstream in handle_actions() before this method runs.
		$source = isset( $_GET['source'] ) ? esc_url_raw( wp_unslash( $_GET['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $source && $this->utils->is_valid_site_url( $source, $this->settings->get_secondary_sites() ) ) {
			$this->utils->debug_message( 'Redirecting to source site.' );
			$this->utils->wpmis_wp_redirect( $source );
			exit;
		}
		// If not coming from the Debug button, the redirect is handled in the logout_user() method.
	}
}
