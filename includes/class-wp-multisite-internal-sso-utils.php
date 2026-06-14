<?php
/**
 * WP Multisite Internal SSO Utility Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Shared utility helpers: debug logging, URL allowlist validation, and redirects.
 */
class WP_Multisite_Internal_SSO_Utils {

	/**
	 * Log messages to the debug log if enabled.
	 *
	 * @param string $message     Message to log.
	 * @param bool   $space_above Whether to prepend blank lines before the entry.
	 */
	public function debug_message( $message, $space_above = false ) {
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			$log_file = WP_CONTENT_DIR . '/sso-debug.log';
			if ( is_writable( $log_file ) ) {
				if ( $space_above ) {
					error_log( "\n\n", 3, $log_file );
				}
				error_log( 'WPMIS SSO: ' . $message . "\n", 3, $log_file );
			} else {
				error_log( 'WPMIS SSO: Log file is not writable.' );
			}
		}
	}

	/**
	 * Validate if the given URL is a valid secondary site.
	 *
	 * @param string $url         URL to validate.
	 * @param array  $secondary_sites Array of secondary sites URLs.
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid_site_url( $url, $secondary_sites = array() ) {
		$url             = esc_url_raw( $url );
		$secondary_sites = empty( $secondary_sites ) ? array() : $secondary_sites;
		return in_array( trailingslashit( $url ), $secondary_sites, true );
	}

	/**
	 * Redirect to the given URL.
	 *
	 * @param string $redirect_to URL to redirect to.
	 * @param array  $params      Query parameters to add to the URL.
	 */
	public function wpmis_wp_redirect( $redirect_to, $params = array() ) {

		if ( ! empty( $params ) ) {
			$params['wpmisso_request'] = '';
		}

		$redirect_url = add_query_arg( $params, $redirect_to );
		$this->debug_message( 'Redirecting to ' . $redirect_url );
		wp_redirect( $redirect_url );
		exit;
	}
}
