<?php
/**
 * WP Multisite Internal SSO Main Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WP_Multisite_Internal_SSO {

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
     * Authentication Manager.
     *
     * @var WP_Multisite_Internal_SSO_Auth
     */
    private $auth;

    /**
     * Admin Interface.
     *
     * @var WP_Multisite_Internal_SSO_Admin
     */
    private $admin;

    /**
     * Utility Functions.
     *
     * @var WP_Multisite_Internal_SSO_Utils
     */
    private $utils;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required dependencies.
     */
    private function load_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'class-wp-multisite-internal-sso-settings.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-wp-multisite-internal-sso-sso.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-wp-multisite-internal-sso-auth.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-wp-multisite-internal-sso-admin.php';
        require_once plugin_dir_path( __FILE__ ) . 'class-wp-multisite-internal-sso-utils.php';

        $this->utils    = new WP_Multisite_Internal_SSO_Utils();
        $this->settings = new WP_Multisite_Internal_SSO_Settings( $this->utils );
        $this->sso      = new WP_Multisite_Internal_SSO_SSO( $this->settings, $this->utils );
        $this->auth     = new WP_Multisite_Internal_SSO_Auth( $this->settings, $this->utils );
        $this->admin    = new WP_Multisite_Internal_SSO_Admin( $this->settings, $this->utils );
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'init_action' ), 1 );
        add_action( 'template_redirect', array( $this->sso, 'check_sso' ) );
        add_action( 'admin_menu', array( $this->admin, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this->settings, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_admin_scripts' ) );
        add_filter( 'login_redirect', array( $this->sso, 'wpmis_sso_login_redirect' ), 10, 3 );

        if ( WP_DEBUG ) {
            add_action( 'wp_body_open', array( $this->admin, 'display_user_status' ) );
        }

        add_action( 'init', array( $this->auth, 'handle_actions' ) );
    }

    /**
     * Initialize actions on 'init' hook.
     */
    public function init_action() {
        $this->utils->debug_message( __( 'Init action triggered.', 'wp-multisite-internal-sso' ) );
        $this->utils->debug_message( __( 'Primary site:', 'wp-multisite-internal-sso' ) . ' ' . $this->settings->get_primary_site() );
        $this->utils->debug_message( __( 'Secondary sites:', 'wp-multisite-internal-sso' ) . ' ' . implode( ', ', $this->settings->get_secondary_sites() ) );

        // Additional initialization if needed
    }
}