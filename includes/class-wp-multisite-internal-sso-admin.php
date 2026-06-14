<?php
/**
 * WP Multisite Internal SSO Admin Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the settings entry point and the (debug-only) status output.
 */
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
	 * Register the network-admin settings page under the Settings menu.
	 *
	 * @return void
	 */
	public function add_network_admin_menu() {
		add_submenu_page(
			'settings.php',
			__( 'WP Multisite Internal SSO Settings', 'wp-multisite-internal-sso' ),
			__( 'Multisite SSO', 'wp-multisite-internal-sso' ),
			'manage_network_options',
			WP_Multisite_Internal_SSO_Settings::PAGE_SLUG,
			array( $this->settings, 'render_settings_page' )
		);
	}

	/**
	 * Render a small front-end status / actions panel.
	 *
	 * Only attached when WP_DEBUG is enabled and a network admin has opted in via
	 * the settings screen — never shown to ordinary visitors.
	 *
	 * @return void
	 */
	public function display_user_status() {
		$logged_in = is_user_logged_in();

		echo '<div class="wpmis-sso-status ' . ( $logged_in ? 'logged-in' : 'not-logged-in' ) . '">';
		echo esc_html( $logged_in ? __( 'Logged in', 'wp-multisite-internal-sso' ) : __( 'Not logged in', 'wp-multisite-internal-sso' ) );
		echo ' &mdash; ' . esc_html( get_site_url() );
		echo '</div>';

		echo '<div class="wpmis-sso-actions">';
		if ( $logged_in ) {
			echo '<a href="' . esc_url( $this->get_logout_url() ) . '">' . esc_html__( 'Log out on all sites', 'wp-multisite-internal-sso' ) . '</a>';

			if ( $this->settings->get_primary_site_id() !== get_current_blog_id() ) {
				$auto_login_url = $this->sso->get_auto_login_url_with_payload( wp_get_current_user()->ID, $this->settings->get_primary_site() );
				if ( $auto_login_url ) {
					echo ' | <a href="' . esc_url( $auto_login_url ) . '">' . esc_html__( 'Auto-login to primary site', 'wp-multisite-internal-sso' ) . '</a>';
				}
			}
		} else {
			echo '<a href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Log in', 'wp-multisite-internal-sso' ) . '</a>';
		}

		if ( isset( $_COOKIE[ $this->settings->get_redirect_cookie_name() ] ) ) {
			echo ' | <a href="' . esc_url( $this->get_clear_cookies_url() ) . '">' . esc_html__( 'Clear SSO cookies', 'wp-multisite-internal-sso' ) . '</a>';
		}
		echo '</div>';
	}

	/**
	 * Generate logout URL with nonce.
	 *
	 * @return string Logout URL.
	 */
	private function get_logout_url() {
		$args = array(
			'forcelogout' => 'true',
			'_wpnonce'    => wp_create_nonce( 'wpmis_sso_logout' ),
		);

		$current_host = $this->get_current_site_url();
		if ( in_array( $current_host, $this->settings->get_secondary_sites(), true ) ) {
			$args['source'] = $current_host;
		}

		return add_query_arg( $args, $this->get_current_site_url() );
	}

	/**
	 * Generate clear cookies URL with nonce.
	 *
	 * @return string Clear cookies URL.
	 */
	private function get_clear_cookies_url() {
		return add_query_arg(
			array(
				'clear_cookies' => 'true',
				'_wpnonce'      => wp_create_nonce( 'wpmis_sso_clear_cookies' ),
			),
			$this->get_current_site_url()
		);
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
