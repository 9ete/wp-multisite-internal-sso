<?php
/**
 * WP Multisite Internal SSO Settings Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Stores and retrieves SSO configuration as a network option, and renders the
 * network-admin settings screen (primary-site picker + per-site secondary toggles).
 */
class WP_Multisite_Internal_SSO_Settings {

	/**
	 * Utility Functions.
	 *
	 * @var WP_Multisite_Internal_SSO_Utils
	 */
	private $utils;

	/**
	 * Network option name.
	 */
	const OPTION_NAME = 'wpmis_sso_settings';

	/**
	 * Nonce action for the settings form.
	 */
	const NONCE_ACTION = 'wpmis_sso_settings_save';

	/**
	 * Slug for the network_admin_edit form handler action.
	 */
	const SAVE_ACTION = 'wpmis_sso_save';

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'wp-multisite-internal-sso';

	/**
	 * Default token lifetime, in seconds.
	 */
	const DEFAULT_TOKEN_EXPIRATION = 300;

	/**
	 * Minimum allowed token lifetime, in seconds.
	 */
	const MIN_TOKEN_EXPIRATION = 60;

	/**
	 * Default redirect cookie name.
	 */
	const DEFAULT_REDIRECT_COOKIE = 'wpmssso_redirect_attempt';

	/**
	 * Constructor.
	 *
	 * @param WP_Multisite_Internal_SSO_Utils $utils Utility functions instance.
	 */
	public function __construct( $utils ) {
		$this->utils = $utils;
	}

	/**
	 * Default configuration values.
	 *
	 * @return array
	 */
	private function defaults() {
		return array(
			'primary_site_id'      => (int) get_main_site_id(),
			'secondary_site_ids'   => array(),
			'token_expiration'     => self::DEFAULT_TOKEN_EXPIRATION,
			'redirect_cookie_name' => self::DEFAULT_REDIRECT_COOKIE,
			'secure_cookies'       => is_ssl(),
			'status_panel'         => false,
		);
	}

	/**
	 * Retrieve the stored settings merged with defaults.
	 *
	 * @return array
	 */
	public function get_settings() {
		$stored = get_site_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( $this->defaults(), $stored );
	}

	/**
	 * Sanitize a raw settings payload from the network settings form.
	 *
	 * @param array $input Raw form input.
	 * @return array Sanitized settings safe to persist.
	 */
	public function sanitize_settings( $input ) {
		$input     = is_array( $input ) ? $input : array();
		$valid_ids = $this->get_network_site_ids();

		// Primary site must be an existing site, else fall back to the main site.
		$primary = isset( $input['primary_site_id'] ) ? absint( $input['primary_site_id'] ) : 0;
		if ( ! in_array( $primary, $valid_ids, true ) ) {
			$primary = (int) get_main_site_id();
		}

		// Secondary sites: existing sites only, never the primary, de-duplicated.
		$secondary = array();
		if ( isset( $input['secondary_site_ids'] ) && is_array( $input['secondary_site_ids'] ) ) {
			foreach ( $input['secondary_site_ids'] as $id ) {
				$id = absint( $id );
				if ( $id && $id !== $primary && in_array( $id, $valid_ids, true ) ) {
					$secondary[ $id ] = $id;
				}
			}
		}

		$token_expiration = isset( $input['token_expiration'] ) ? absint( $input['token_expiration'] ) : self::DEFAULT_TOKEN_EXPIRATION;
		if ( $token_expiration < self::MIN_TOKEN_EXPIRATION ) {
			$token_expiration = self::MIN_TOKEN_EXPIRATION;
		}

		$cookie_name = isset( $input['redirect_cookie_name'] ) ? sanitize_key( $input['redirect_cookie_name'] ) : '';
		if ( '' === $cookie_name ) {
			$cookie_name = self::DEFAULT_REDIRECT_COOKIE;
		}

		return array(
			'primary_site_id'      => $primary,
			'secondary_site_ids'   => array_values( $secondary ),
			'token_expiration'     => $token_expiration,
			'redirect_cookie_name' => $cookie_name,
			'secure_cookies'       => ! empty( $input['secure_cookies'] ),
			'status_panel'         => ! empty( $input['status_panel'] ),
		);
	}

	/**
	 * IDs of every site in the network.
	 *
	 * @return int[]
	 */
	private function get_network_site_ids() {
		$ids = array();
		foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
			$ids[] = (int) ( is_object( $site ) ? $site->blog_id : $site );
		}
		return $ids;
	}

	/**
	 * Get the primary site ID.
	 *
	 * @return int
	 */
	public function get_primary_site_id() {
		$settings = $this->get_settings();
		$id       = absint( $settings['primary_site_id'] );
		return $id ? $id : (int) get_main_site_id();
	}

	/**
	 * Get the primary site URL (trailing-slashed).
	 *
	 * @return string
	 */
	public function get_primary_site() {
		return trailingslashit( get_site_url( $this->get_primary_site_id() ) );
	}

	/**
	 * Get the enabled secondary site IDs.
	 *
	 * @return int[]
	 */
	public function get_secondary_site_ids() {
		$settings = $this->get_settings();
		$ids      = isset( $settings['secondary_site_ids'] ) ? (array) $settings['secondary_site_ids'] : array();
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Get the enabled secondary site URLs (trailing-slashed).
	 *
	 * Returns an empty array when none are configured (never throws), so the SSO
	 * flow degrades gracefully on a freshly-activated network.
	 *
	 * @return string[]
	 */
	public function get_secondary_sites() {
		$urls = array();
		foreach ( $this->get_secondary_site_ids() as $id ) {
			$url = get_site_url( $id );
			if ( $url ) {
				$urls[] = trailingslashit( $url );
			}
		}
		return $urls;
	}

	/**
	 * Whether a URL matches one of the enabled secondary sites.
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	public function is_secondary_site( $url ) {
		$url = trailingslashit( esc_url_raw( (string) $url ) );
		return in_array( $url, $this->get_secondary_sites(), true );
	}

	/**
	 * Get the redirect cookie name.
	 *
	 * @return string
	 */
	public function get_redirect_cookie_name() {
		$settings = $this->get_settings();
		$name     = sanitize_key( (string) $settings['redirect_cookie_name'] );
		return $name ? $name : self::DEFAULT_REDIRECT_COOKIE;
	}

	/**
	 * Get the token expiration time, in seconds.
	 *
	 * @return int
	 */
	public function get_token_expiration() {
		$settings = $this->get_settings();
		return max( self::MIN_TOKEN_EXPIRATION, absint( $settings['token_expiration'] ) );
	}

	/**
	 * Determine whether secure cookies are enabled.
	 *
	 * @return bool
	 */
	public function are_secure_cookies_enabled() {
		$settings = $this->get_settings();
		return ! empty( $settings['secure_cookies'] );
	}

	/**
	 * Whether the opt-in front-end debug status panel is enabled.
	 *
	 * @return bool
	 */
	public function is_status_panel_enabled() {
		$settings = $this->get_settings();
		return ! empty( $settings['status_panel'] );
	}

	/**
	 * Handle the network settings form submission.
	 *
	 * Hooked to network_admin_edit_{SAVE_ACTION}; validates the nonce and the
	 * manage_network_options capability, persists the sanitized payload as a
	 * network option, then redirects back to the settings screen.
	 *
	 * @return void
	 */
	public function handle_network_save() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'wp-multisite-internal-sso' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		// Sanitized field-by-field in sanitize_settings().
		$raw = isset( $_POST[ self::OPTION_NAME ] ) ? wp_unslash( $_POST[ self::OPTION_NAME ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_site_option( self::OPTION_NAME, $this->sanitize_settings( $raw ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'updated' => 'true',
				),
				network_admin_url( 'settings.php' )
			)
		);
		exit;
	}

	/**
	 * Render the network settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		$settings   = $this->get_settings();
		$sites      = get_sites( array( 'number' => 0 ) );
		$primary_id = (int) $settings['primary_site_id'];
		$secondary  = array_map( 'absint', (array) $settings['secondary_site_ids'] );
		$form_url   = network_admin_url( 'edit.php?action=' . self::SAVE_ACTION );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Multisite Internal SSO Settings', 'wp-multisite-internal-sso' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div id="message" class="updated notice is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'wp-multisite-internal-sso' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $form_url ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpmis_sso_primary_site"><?php esc_html_e( 'Primary Site', 'wp-multisite-internal-sso' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[primary_site_id]" id="wpmis_sso_primary_site">
								<?php foreach ( $sites as $site ) : ?>
									<?php $sid = (int) $site->blog_id; ?>
									<option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $sid, $primary_id ); ?>>
										<?php echo esc_html( get_site_url( $sid ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'The site that holds the canonical login. Users authenticate here.', 'wp-multisite-internal-sso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Secondary Sites', 'wp-multisite-internal-sso' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Secondary sites', 'wp-multisite-internal-sso' ); ?></legend>
								<?php foreach ( $sites as $site ) : ?>
									<?php
									$sid = (int) $site->blog_id;
									if ( $sid === $primary_id ) {
										continue;
									}
									?>
									<label style="display:block;margin-bottom:4px;">
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[secondary_site_ids][]" value="<?php echo esc_attr( $sid ); ?>" <?php checked( in_array( $sid, $secondary, true ) ); ?> />
										<?php echo esc_html( get_site_url( $sid ) ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Sites where users are auto-logged-in after authenticating on the primary site.', 'wp-multisite-internal-sso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpmis_sso_token_expiration"><?php esc_html_e( 'Token Expiration (seconds)', 'wp-multisite-internal-sso' ); ?></label></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[token_expiration]" id="wpmis_sso_token_expiration" value="<?php echo esc_attr( $settings['token_expiration'] ); ?>" min="<?php echo esc_attr( self::MIN_TOKEN_EXPIRATION ); ?>" step="30" />
							<p class="description"><?php esc_html_e( 'How long an SSO token stays valid. Default 300 seconds (5 minutes).', 'wp-multisite-internal-sso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpmis_sso_cookie_name"><?php esc_html_e( 'Redirect Cookie Name', 'wp-multisite-internal-sso' ); ?></label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[redirect_cookie_name]" id="wpmis_sso_cookie_name" value="<?php echo esc_attr( $settings['redirect_cookie_name'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Name of the short-lived cookie used to prevent redirect loops.', 'wp-multisite-internal-sso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Secure Cookies', 'wp-multisite-internal-sso' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[secure_cookies]" value="1" <?php checked( ! empty( $settings['secure_cookies'] ) ); ?> />
								<?php esc_html_e( 'Only transmit SSO cookies over HTTPS.', 'wp-multisite-internal-sso' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Debug Status Panel', 'wp-multisite-internal-sso' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[status_panel]" value="1" <?php checked( ! empty( $settings['status_panel'] ) ); ?> />
								<?php esc_html_e( 'Show a front-end login-status panel (only renders when WP_DEBUG is enabled).', 'wp-multisite-internal-sso' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
