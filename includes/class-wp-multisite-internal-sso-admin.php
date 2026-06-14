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
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'settings_page_wp-multisite-internal-sso' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'wpmis-sso-admin-js', WPMIS_SSO_PLUGIN_URL . 'assets/js/wpmis-sso-admin.js', array(), WPMIS_SSO_PLUGIN_VERSION, true );
	}

	/**
	 * Display user login status and action buttons.
	 */
	public function display_user_status() {

		$clear_cookies_button = '<button onclick="document.cookie = \'' . esc_js( $this->settings->get_redirect_cookie_name() ) . '=;expires=Thu, 01 Jan 1970 00:00:00 GMT\';">' . esc_html__( 'Clear Cookies', 'wp-multisite-internal-sso' ) . '</button>';

		if ( isset( $_COOKIE[ $this->settings->get_redirect_cookie_name() ] ) ) {
			echo '<div class="wpmis-sso-status redirect-attempt">' . esc_html__( 'Redirect Attempted - ', 'wp-multisite-internal-sso' ) . esc_html( $_COOKIE[ $this->settings->get_redirect_cookie_name() ] ) . '</div>';
		}

		if ( ! is_user_logged_in() ) {
			echo '<div class="wpmis-sso-status not-logged-in">' . esc_html__( 'Not logged in - ', 'wp-multisite-internal-sso' ) . esc_url( get_site_url() ) . '</div>';
		} else {
			echo '<div class="wpmis-sso-status logged-in">' . esc_html__( 'Logged in - ', 'wp-multisite-internal-sso' ) . esc_url( get_site_url() ) . '</div>';
		}

		// Display logout button.
		echo '<div class="wpmis-sso-actions">';
		if ( is_user_logged_in() ) {
			echo '<a href="' . esc_url( $this->get_logout_url() ) . '">' . esc_html__( 'Logout On All Sites', 'wp-multisite-internal-sso' ) . '</a>';
			echo '<span class="divider"> | </span>';
		} else {
			echo '<a href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Login', 'wp-multisite-internal-sso' ) . '</a>';
			echo '<span class="divider"> | </span>';
		}

		if ( is_user_logged_in() && $this->settings->get_primary_site_id() !== get_current_blog_id() ) {
			echo '<a href="' . esc_url( (string) $this->sso->get_auto_login_url_with_payload( wp_get_current_user()->ID, $this->settings->get_primary_site() ) ) . '">' . esc_html__( 'Auto Log in to primary site', 'wp-multisite-internal-sso' ) . '</a>';
			echo '<span class="divider"> | </span>';
		}

		// If the redirect cookie is set.
		if ( isset( $_COOKIE[ $this->settings->get_redirect_cookie_name() ] ) ) {
			echo 'SSO Login Attempted - ';
			echo $clear_cookies_button;
			echo '<span class="divider"> | </span>';
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
