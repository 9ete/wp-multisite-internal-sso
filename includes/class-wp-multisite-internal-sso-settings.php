<?php
/**
 * WP Multisite Internal SSO Settings Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WP_Multisite_Internal_SSO_Settings {

    /**
     * Utility Functions.
     *
     * @var WP_Multisite_Internal_SSO_Utils
     */
    private $utils;

    /**
     * Option name.
     */
    const OPTION_NAME = 'wpmis_sso_settings';

    /**
     * Constructor.
     *
     * @param WP_Multisite_Internal_SSO_Utils $utils Utility functions instance.
     */
    public function __construct( $utils ) {
        $this->utils = $utils;
        add_action( 'admin_init', array( $this, 'load_textdomain' ) );
    }

    /**
     * Load plugin textdomain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'wp-multisite-internal-sso', false, dirname( plugin_basename( __FILE__ ) ) . '/../languages' );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting( 'wpmis_sso_settings_group', self::OPTION_NAME, array( $this, 'sanitize_settings' ) );

        add_settings_section(
            'wpmis_sso_main_section',
            __( 'Main Settings', 'wp-multisite-internal-sso' ),
            null,
            'wp-multisite-internal-sso'
        );

        add_settings_field(
            'primary_site',
            __( 'Primary Site URL', 'wp-multisite-internal-sso' ),
            array( $this, 'primary_site_callback' ),
            'wp-multisite-internal-sso',
            'wpmis_sso_main_section'
        );

        add_settings_field(
            'secondary_sites',
            __( 'Secondary Sites URLs', 'wp-multisite-internal-sso' ),
            array( $this, 'secondary_sites_callback' ),
            'wp-multisite-internal-sso',
            'wpmis_sso_main_section'
        );

        add_settings_field(
            'token_expiration',
            __( 'Token Expiration (seconds)', 'wp-multisite-internal-sso' ),
            array( $this, 'token_expiration_callback' ),
            'wp-multisite-internal-sso',
            'wpmis_sso_main_section'
        );

        add_settings_field(
            'redirect_cookie_name',
            __( 'Redirect Cookie Name', 'wp-multisite-internal-sso' ),
            array( $this, 'redirect_cookie_name_callback' ),
            'wp-multisite-internal-sso',
            'wpmis_sso_main_section'
        );

        add_settings_field(
            'secure_cookies',
            __( 'Secure Cookies', 'wp-multisite-internal-sso' ),
            array( $this, 'secure_cookies_callback' ),
            'wp-multisite-internal-sso',
            'wpmis_sso_main_section'
        );
    }

    /**
     * Sanitize plugin settings input.
     *
     * @param array $input Input settings.
     * @return array Sanitized settings.
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        if ( isset( $input['primary_site'] ) ) {
            $sanitized['primary_site'] = trailingslashit( esc_url_raw( $input['primary_site'] ) );
        }

        if ( isset( $input['secondary_sites'] ) && is_array( $input['secondary_sites'] ) ) {
            $sanitized['secondary_sites'] = array_map( 'trailingslashit', array_map( 'esc_url_raw', $input['secondary_sites'] ) );
        }

        if ( isset( $input['token_expiration'] ) ) {
            $sanitized['token_expiration'] = absint( $input['token_expiration'] );
        }

        if ( isset( $input['redirect_cookie_name'] ) ) {
            $sanitized['redirect_cookie_name'] = sanitize_text_field( $input['redirect_cookie_name'] );
        }

        if ( isset( $input['secure_cookies'] ) ) {
            $sanitized['secure_cookies'] = boolval( $input['secure_cookies'] );
        }

        return $sanitized;
    }

    /**
     * Callback for primary site URL field.
     */
    public function primary_site_callback() {
        $settings = get_option( self::OPTION_NAME, array() );
        ?>
        <input type="url" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[primary_site]" value="<?php echo isset( $settings['primary_site'] ) ? esc_attr( $settings['primary_site'] ) : esc_url( get_site_url( get_main_site_id() ) ); ?>" size="50" required />
        <p class="description"><?php esc_html_e( 'Enter the URL of the primary site where users will authenticate.', 'wp-multisite-internal-sso' ); ?></p>
        <?php
    }

    /**
     * Callback for secondary sites URLs field.
     */
    public function secondary_sites_callback() {
        $settings        = get_option( self::OPTION_NAME, array() );
        $secondary_sites = isset( $settings['secondary_sites'] ) ? $settings['secondary_sites'] : array( get_site_url( 2 ) );
        ?>
        <div id="secondary-sites-wrapper">
            <?php foreach ( $secondary_sites as $index => $site_url ) : ?>
                <div class="secondary-site-field">
                    <input type="url" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[secondary_sites][]" value="<?php echo esc_attr( $site_url ); ?>" size="50" required />
                    <?php if ( $index > 0 ) : ?>
                        <button type="button" class="button remove-secondary-site"><?php esc_html_e( 'Remove', 'wp-multisite-internal-sso' ); ?></button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button" id="add-secondary-site"><?php esc_html_e( 'Add Secondary Site', 'wp-multisite-internal-sso' ); ?></button>
        <p class="description"><?php esc_html_e( 'Enter the URLs of the secondary sites that should accept SSO from the primary site.', 'wp-multisite-internal-sso' ); ?></p>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('add-secondary-site').addEventListener('click', function(e) {
                    e.preventDefault();
                    var wrapper = document.getElementById('secondary-sites-wrapper');
                    var field = document.createElement('div');
                    field.className = 'secondary-site-field';
                    field.innerHTML = '<input type="url" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[secondary_sites][]" value="" size="50" required /> <button type="button" class="button remove-secondary-site"><?php esc_html_e( 'Remove', 'wp-multisite-internal-sso' ); ?></button>';
                    wrapper.appendChild(field);
                });

                document.getElementById('secondary-sites-wrapper').addEventListener('click', function(e) {
                    if ( e.target && e.target.classList.contains('remove-secondary-site') ) {
                        e.target.parentElement.remove();
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Callback for token expiration field.
     */
    public function token_expiration_callback() {
        $settings   = get_option( self::OPTION_NAME, array() );
        $expiration = isset( $settings['token_expiration'] ) ? absint( $settings['token_expiration'] ) : 300;
        ?>
        <input type="number" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[token_expiration]" value="<?php echo esc_attr( $expiration ); ?>" min="60" step="60" />
        <p class="description"><?php esc_html_e( 'Define how long SSO tokens remain valid (in seconds). Default is 300 seconds (5 minutes).', 'wp-multisite-internal-sso' ); ?></p>
        <?php
    }

    /**
     * Callback for redirect cookie name field.
     */
    public function redirect_cookie_name_callback() {
        $settings    = get_option( self::OPTION_NAME, array() );
        $cookie_name = isset( $settings['redirect_cookie_name'] ) ? sanitize_text_field( $settings['redirect_cookie_name'] ) : 'wpmssso_redirect_attempt';
        ?>
        <input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[redirect_cookie_name]" value="<?php echo esc_attr( $cookie_name ); ?>" size="30" />
        <p class="description"><?php esc_html_e( 'Specify a custom name for the redirect cookie used during the SSO process.', 'wp-multisite-internal-sso' ); ?></p>
        <?php
    }

    /**
     * Callback for secure cookies field.
     */
    public function secure_cookies_callback() {
        $settings = get_option( self::OPTION_NAME, array() );
        $secure   = isset( $settings['secure_cookies'] ) ? boolval( $settings['secure_cookies'] ) : is_ssl();
        ?>
        <input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[secure_cookies]" value="1" <?php checked( $secure, true ); ?> />
        <p class="description"><?php esc_html_e( 'Enable secure cookies to ensure cookies are transmitted only over HTTPS.', 'wp-multisite-internal-sso' ); ?></p>
        <?php
    }

    /**
     * Get primary site URL.
     *
     * @return string
     */
    public function get_primary_site() {
        $settings = get_option( self::OPTION_NAME, array() );
        return isset( $settings['primary_site'] ) ? trailingslashit( esc_url_raw( $settings['primary_site'] ) ) : trailingslashit( get_site_url( get_main_site_id() ) );
    }

    /**
     * Get primary site ID.
     *
     * @return int
     */
    public function get_primary_site_id() {
        $settings = get_option( self::OPTION_NAME, array() );
        return isset( $settings['primary_site_id'] ) ? absint( $settings['primary_site_id'] ) : get_main_site_id();
    }

    /**
     * Get secondary sites URLs.
     *
     * @return array
     */
    public function get_secondary_sites() {
        $settings        = get_option( self::OPTION_NAME, array() );
        $secondary_sites = isset( $settings['secondary_sites'] ) ? $settings['secondary_sites'] : array( trailingslashit( get_site_url( 2 ) ) );
        return array_map( 'trailingslashit', array_map( 'esc_url_raw', (array) $secondary_sites ) );
    }

    /**
     * Get secondary site IDs.
     *
     * @return array
     */
    public function get_secondary_sites_ids() {
        $sites = get_sites();
        $secondary_sites = $this->get_secondary_sites();
        $secondary_site_ids = array();
        foreach ( $sites as $site ) {
            if ( in_array( trailingslashit( $site->siteurl ), $secondary_sites, true ) ) {
                $secondary_site_ids[] = $site->blog_id;
            }
        }
        return $secondary_site_ids;
    }

    /**
     * Get redirect cookie name.
     *
     * @return string
     */
    public function get_redirect_cookie_name() {
        $settings = get_option( self::OPTION_NAME, array() );
        return isset( $settings['redirect_cookie_name'] ) ? sanitize_text_field( $settings['redirect_cookie_name'] ) : 'wpmssso_redirect_attempt';
    }

    /**
     * Get token expiration time.
     *
     * @return int
     */
    public function get_token_expiration() {
        $settings = get_option( self::OPTION_NAME, array() );
        return isset( $settings['token_expiration'] ) ? absint( $settings['token_expiration'] ) : 300;
    }

    /**
     * Determine if secure cookies are enabled.
     *
     * @return bool
     */
    public function are_secure_cookies_enabled() {
        $settings = get_option( self::OPTION_NAME, array() );
        return isset( $settings['secure_cookies'] ) ? boolval( $settings['secure_cookies'] ) : is_ssl();
    }

    /**
     * Render the settings page.
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'WP Multisite Internal SSO Settings', 'wp-multisite-internal-sso' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wpmis_sso_settings_group' );
                do_settings_sections( 'wp-multisite-internal-sso' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}