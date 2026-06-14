<?php
/**
 * PHPUnit bootstrap: defines WordPress stubs + plugin constants so the
 * WP Multisite Internal SSO classes can be loaded and unit-tested without
 * a full WordPress install.
 *
 * Tests control behaviour by priming the $GLOBALS['_test_*'] arrays before
 * exercising plugin code (see individual test files for examples).
 *
 * @package WP_Multisite_Internal_SSO
 */

// Guard constant required by every plugin file before its class definition.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/wpmssso-fake-wp/' );
}

// Constants referenced by plugin runtime code paths.
defined( 'WP_DEBUG' ) || define( 'WP_DEBUG', false );
defined( 'WP_DEBUG_LOG' ) || define( 'WP_DEBUG_LOG', false );
defined( 'WP_CONTENT_DIR' ) || define( 'WP_CONTENT_DIR', sys_get_temp_dir() );
defined( 'AUTH_SALT' ) || define( 'AUTH_SALT', 'unit-test-auth-salt-value' );
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );
defined( 'YEAR_IN_SECONDS' ) || define( 'YEAR_IN_SECONDS', 31536000 );
defined( 'COOKIEPATH' ) || define( 'COOKIEPATH', '/' );
defined( 'SITECOOKIEPATH' ) || define( 'SITECOOKIEPATH', '/' );
defined( 'COOKIE_DOMAIN' ) || define( 'COOKIE_DOMAIN', '' );
defined( 'COOKIEHASH' ) || define( 'COOKIEHASH', 'testcookiehash' );
defined( 'LOGGED_IN_COOKIE' ) || define( 'LOGGED_IN_COOKIE', 'wordpress_logged_in_' . COOKIEHASH );
defined( 'AUTH_COOKIE' ) || define( 'AUTH_COOKIE', 'wordpress_' . COOKIEHASH );
defined( 'SECURE_AUTH_COOKIE' ) || define( 'SECURE_AUTH_COOKIE', 'wordpress_sec_' . COOKIEHASH );

// ---------------------------------------------------------------------------
// Minimal WordPress function stubs (only what the plugin / tests exercise).
// ---------------------------------------------------------------------------

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $text ) {
		return addslashes( (string) $text );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url, $protocols = null, $_context = 'display' ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $str ) ) );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $n ) {
		return abs( (int) $n );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' ) . '/';
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' );
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) {
		// Only the array form is used by the plugin.
		$sep      = ( strpos( (string) $url, '?' ) !== false ) ? '&' : '?';
		$filtered = array();
		foreach ( (array) $args as $k => $v ) {
			if ( false === $v || null === $v ) {
				continue;
			}
			$filtered[ $k ] = $v;
		}
		if ( empty( $filtered ) ) {
			return (string) $url;
		}
		return (string) $url . $sep . http_build_query( $filtered );
	}
}
if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $keys, $url = '' ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'wp_hash' ) ) {
	function wp_hash( $data, $scheme = 'auth' ) {
		return hash_hmac( 'md5', (string) $data, 'test-key-' . $scheme );
	}
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$out   = '';
		for ( $i = 0; $i < (int) $length; $i++ ) {
			$out .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
		}
		return $out;
	}
}
if ( ! function_exists( 'is_ssl' ) ) {
	function is_ssl() {
		return $GLOBALS['_test_is_ssl'] ?? false;
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '', $scheme = null ) {
		return ( $GLOBALS['_test_home_url'] ?? 'https://example.com' ) . $path;
	}
}
if ( ! function_exists( 'get_site_url' ) ) {
	function get_site_url( $blog_id = null, $path = '', $scheme = null ) {
		if ( null !== $blog_id && isset( $GLOBALS['_test_site_urls'][ $blog_id ] ) ) {
			return $GLOBALS['_test_site_urls'][ $blog_id ] . $path;
		}
		return ( $GLOBALS['_test_home_url'] ?? 'https://example.com' ) . $path;
	}
}
if ( ! function_exists( 'get_main_site_id' ) ) {
	function get_main_site_id() {
		return $GLOBALS['_test_main_site_id'] ?? 1;
	}
}
if ( ! function_exists( 'get_current_blog_id' ) ) {
	function get_current_blog_id() {
		return $GLOBALS['_test_current_blog_id'] ?? 1;
	}
}
if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite() {
		return $GLOBALS['_test_is_multisite'] ?? true;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default_value = false ) {
		return $GLOBALS['_test_options'][ $option ] ?? $default_value;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['_test_options'][ $option ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_site_option' ) ) {
	function get_site_option( $option, $default_value = false ) {
		return $GLOBALS['_test_site_options'][ $option ] ?? $default_value;
	}
}
if ( ! function_exists( 'update_site_option' ) ) {
	function update_site_option( $option, $value ) {
		$GLOBALS['_test_site_options'][ $option ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_site_option' ) ) {
	function delete_site_option( $option ) {
		unset( $GLOBALS['_test_site_options'][ $option ] );
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		return $GLOBALS['_test_transients'][ $transient ] ?? false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['_test_transients'][ $transient ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		unset( $GLOBALS['_test_transients'][ $transient ] );
		return true;
	}
}
if ( ! function_exists( 'get_site_transient' ) ) {
	function get_site_transient( $transient ) {
		return $GLOBALS['_test_site_transients'][ $transient ] ?? false;
	}
}
if ( ! function_exists( 'set_site_transient' ) ) {
	function set_site_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['_test_site_transients'][ $transient ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_site_transient' ) ) {
	function delete_site_transient( $transient ) {
		unset( $GLOBALS['_test_site_transients'][ $transient ] );
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) {
		return true;
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( ...$args ) {}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'nonce_' . $action;
	}
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return ( $GLOBALS['_test_nonce_valid'] ?? true ) ? 1 : false;
	}
}
if ( ! function_exists( 'get_sites' ) ) {
	function get_sites( $args = array() ) {
		return $GLOBALS['_test_sites'] ?? array();
	}
}
if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		return $GLOBALS['_test_logged_in'] ?? false;
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap, ...$args ) {
		return $GLOBALS['_test_caps'][ $cap ] ?? false;
	}
}
if ( ! function_exists( 'is_super_admin' ) ) {
	function is_super_admin( $user_id = false ) {
		return $GLOBALS['_test_is_super_admin'] ?? false;
	}
}
if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $display = true ) {
		$result = ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) {
		return (string) $data;
	}
}
if ( ! function_exists( 'sanitize_url' ) ) {
	function sanitize_url( $url, $protocols = null ) {
		return (string) $url;
	}
}

// ---------------------------------------------------------------------------
// Composer autoloader (PHPUnit + any dev deps) when present.
// ---------------------------------------------------------------------------
$wpmssso_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( is_file( $wpmssso_autoload ) ) {
	require_once $wpmssso_autoload;
}

// ---------------------------------------------------------------------------
// Plugin class files (definitions only; no side effects until instantiated).
// ---------------------------------------------------------------------------
$wpmssso_inc = dirname( __DIR__ ) . '/includes/';
require_once $wpmssso_inc . 'class-wp-multisite-internal-sso-utils.php';
require_once $wpmssso_inc . 'class-wp-multisite-internal-sso-settings.php';
require_once $wpmssso_inc . 'class-wp-multisite-internal-sso-token.php';
require_once $wpmssso_inc . 'class-wp-multisite-internal-sso-sso.php';
require_once $wpmssso_inc . 'class-wp-multisite-internal-sso-auth.php';
require_once $wpmssso_inc . 'class-wp-multisite-internal-sso-admin.php';
require_once $wpmssso_inc . 'class-wp-multisite-internal-sso.php';
