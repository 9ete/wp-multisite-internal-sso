<?php
/**
 * Unit tests for WP_Multisite_Internal_SSO_Token.
 *
 * @package WP_Multisite_Internal_SSO
 */

use PHPUnit\Framework\TestCase;

/**
 * Token subclass with a controllable clock so expiry/skew paths are testable.
 */
final class WPMISSO_Clock_Token extends WP_Multisite_Internal_SSO_Token {

	/**
	 * Overridden "current time".
	 *
	 * @var int
	 */
	public $fake_now = 1000;

	/**
	 * Return the fake clock value.
	 *
	 * @return int
	 */
	protected function now() {
		return $this->fake_now;
	}
}

/**
 * Covers token issuance, verification, single-use, expiry, and tampering.
 */
final class TokenTest extends TestCase {

	/**
	 * Reset the stubbed network state before each test.
	 */
	protected function setUp(): void {
		$GLOBALS['_test_site_options']    = array();
		$GLOBALS['_test_site_transients'] = array();
		$GLOBALS['_test_main_site_id']    = 1;
	}

	/**
	 * Build a token service backed by default settings.
	 *
	 * @return WP_Multisite_Internal_SSO_Token
	 */
	private function make_token(): WP_Multisite_Internal_SSO_Token {
		return new WP_Multisite_Internal_SSO_Token( new WP_Multisite_Internal_SSO_Settings( new WP_Multisite_Internal_SSO_Utils() ) );
	}

	/**
	 * issue() returns the expected payload shape with a 64-char SHA-256 signature.
	 */
	public function test_issue_returns_payload(): void {
		$payload = $this->make_token()->issue( 7 );
		$this->assertSame( 7, $payload['wpmssso_user'] );
		$this->assertIsInt( $payload['wpmssso_time'] );
		$this->assertNotEmpty( $payload['wpmssso_nonce'] );
		$this->assertSame( 64, strlen( $payload['wpmssso_token'] ) );
	}

	/**
	 * A freshly-issued token verifies and returns the user ID.
	 */
	public function test_valid_token_verifies(): void {
		$t = $this->make_token();
		$p = $t->issue( 7 );
		$this->assertSame( 7, $t->verify_and_consume( $p['wpmssso_user'], $p['wpmssso_time'], $p['wpmssso_nonce'], $p['wpmssso_token'] ) );
	}

	/**
	 * A token is single-use: the second verification fails (replay protection).
	 */
	public function test_token_is_single_use(): void {
		$t = $this->make_token();
		$p = $t->issue( 7 );
		$this->assertSame( 7, $t->verify_and_consume( $p['wpmssso_user'], $p['wpmssso_time'], $p['wpmssso_nonce'], $p['wpmssso_token'] ) );
		$this->assertSame( 0, $t->verify_and_consume( $p['wpmssso_user'], $p['wpmssso_time'], $p['wpmssso_nonce'], $p['wpmssso_token'] ) );
	}

	/**
	 * A tampered signature is rejected.
	 */
	public function test_tampered_token_rejected(): void {
		$t   = $this->make_token();
		$p   = $t->issue( 7 );
		$bad = $p['wpmssso_token'];
		$bad[0] = ( 'a' === $bad[0] ) ? 'b' : 'a';
		$this->assertSame( 0, $t->verify_and_consume( 7, $p['wpmssso_time'], $p['wpmssso_nonce'], $bad ) );
	}

	/**
	 * A token cannot be used to authenticate a different user.
	 */
	public function test_wrong_user_rejected(): void {
		$t = $this->make_token();
		$p = $t->issue( 7 );
		$this->assertSame( 0, $t->verify_and_consume( 8, $p['wpmssso_time'], $p['wpmssso_nonce'], $p['wpmssso_token'] ) );
	}

	/**
	 * An unknown jti (never issued) is rejected even with a valid-looking shape.
	 */
	public function test_unknown_jti_rejected(): void {
		$t = $this->make_token();
		$p = $t->issue( 7 );
		// Same signature inputs but the jti was never stored as issued.
		$this->assertSame( 0, $t->verify_and_consume( 7, $p['wpmssso_time'], 'neverissuedjti', $p['wpmssso_token'] ) );
	}

	/**
	 * A token past its TTL is rejected (expiry path).
	 */
	public function test_expired_token_rejected(): void {
		$t           = new WPMISSO_Clock_Token( new WP_Multisite_Internal_SSO_Settings( new WP_Multisite_Internal_SSO_Utils() ) );
		$t->fake_now = 1000;
		$p           = $t->issue( 7 );          // default TTL = 300s.
		$t->fake_now = 1000 + 301;              // 301s later → expired.
		$this->assertSame( 0, $t->verify_and_consume( $p['wpmssso_user'], $p['wpmssso_time'], $p['wpmssso_nonce'], $p['wpmssso_token'] ) );
	}

	/**
	 * A token within its TTL still verifies.
	 */
	public function test_within_ttl_verifies(): void {
		$t           = new WPMISSO_Clock_Token( new WP_Multisite_Internal_SSO_Settings( new WP_Multisite_Internal_SSO_Utils() ) );
		$t->fake_now = 1000;
		$p           = $t->issue( 7 );
		$t->fake_now = 1000 + 100;              // within 300s.
		$this->assertSame( 7, $t->verify_and_consume( $p['wpmssso_user'], $p['wpmssso_time'], $p['wpmssso_nonce'], $p['wpmssso_token'] ) );
	}

	/**
	 * A timestamp implausibly far in the future is rejected (clock-skew guard).
	 */
	public function test_future_timestamp_rejected(): void {
		$t           = new WPMISSO_Clock_Token( new WP_Multisite_Internal_SSO_Settings( new WP_Multisite_Internal_SSO_Utils() ) );
		$t->fake_now = 5000;
		$p           = $t->issue( 7 );          // issued at t=5000.
		$t->fake_now = 5000 - 200;              // verifier clock is 200s behind → beyond skew.
		$this->assertSame( 0, $t->verify_and_consume( $p['wpmssso_user'], $p['wpmssso_time'], $p['wpmssso_nonce'], $p['wpmssso_token'] ) );
	}
}
