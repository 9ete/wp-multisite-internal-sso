<?php
/**
 * Unit tests for WP_Multisite_Internal_SSO_SSO (public surface).
 *
 * @package WP_Multisite_Internal_SSO
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers the auto-login URL builder, which integrates the token service.
 */
final class SsoTest extends TestCase {

	/**
	 * Reset the stubbed network state before each test.
	 */
	protected function setUp(): void {
		$GLOBALS['_test_site_options']    = array();
		$GLOBALS['_test_site_transients'] = array();
		$GLOBALS['_test_main_site_id']    = 1;
	}

	/**
	 * Build an SSO handler backed by default settings.
	 *
	 * @return WP_Multisite_Internal_SSO_SSO
	 */
	private function make_sso(): WP_Multisite_Internal_SSO_SSO {
		$utils    = new WP_Multisite_Internal_SSO_Utils();
		$settings = new WP_Multisite_Internal_SSO_Settings( $utils );
		return new WP_Multisite_Internal_SSO_SSO( $settings, $utils );
	}

	/**
	 * The auto-login URL carries the signed, single-use token args + destination.
	 */
	public function test_auto_login_url_contains_token_args(): void {
		$url = $this->make_sso()->get_auto_login_url_with_payload( 7, 'https://b.example.com/' );
		$this->assertIsString( $url );
		$this->assertStringContainsString( 'wpmssso_user=7', $url );
		$this->assertStringContainsString( 'wpmssso_time=', $url );
		$this->assertStringContainsString( 'wpmssso_nonce=', $url );
		$this->assertStringContainsString( 'wpmssso_token=', $url );
		$this->assertStringContainsString( 'https://b.example.com/', $url );
	}

	/**
	 * No user → null URL (guard against minting tokens for nobody).
	 */
	public function test_auto_login_url_null_without_user(): void {
		$this->assertNull( $this->make_sso()->get_auto_login_url_with_payload( 0, 'https://b.example.com/' ) );
	}

	/**
	 * A provided return URL is included in the payload.
	 */
	public function test_auto_login_url_includes_return(): void {
		$url = $this->make_sso()->get_auto_login_url_with_payload( 7, 'https://b.example.com/', 'https://b.example.com/welcome/' );
		$this->assertStringContainsString( 'wpmssso_return=', $url );
	}

	/**
	 * The issued token in the URL actually verifies against the token service
	 * (end-to-end at the SSO layer), proving issue↔verify wiring.
	 */
	public function test_issued_url_token_verifies(): void {
		$utils    = new WP_Multisite_Internal_SSO_Utils();
		$settings = new WP_Multisite_Internal_SSO_Settings( $utils );
		$sso      = new WP_Multisite_Internal_SSO_SSO( $settings, $utils );
		$token    = new WP_Multisite_Internal_SSO_Token( $settings );

		$url = $sso->get_auto_login_url_with_payload( 42, 'https://b.example.com/' );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $args );

		$this->assertSame(
			42,
			$token->verify_and_consume(
				$args['wpmssso_user'],
				$args['wpmssso_time'],
				$args['wpmssso_nonce'],
				$args['wpmssso_token']
			)
		);
	}
}
