<?php
/**
 * Unit tests for WP_Multisite_Internal_SSO_Utils.
 *
 * @package WP_Multisite_Internal_SSO
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers the public URL-allowlist helper used to gate SSO redirect targets.
 */
final class UtilsTest extends TestCase {

	/**
	 * A URL present in the allowlist is accepted (trailing slash normalised).
	 */
	public function test_is_valid_site_url_accepts_member(): void {
		$utils = new WP_Multisite_Internal_SSO_Utils();
		$this->assertTrue(
			$utils->is_valid_site_url( 'https://b.example.com', array( 'https://b.example.com/' ) )
		);
	}

	/**
	 * A URL not present in the allowlist is rejected (open-redirect guard).
	 */
	public function test_is_valid_site_url_rejects_non_member(): void {
		$utils = new WP_Multisite_Internal_SSO_Utils();
		$this->assertFalse(
			$utils->is_valid_site_url( 'https://evil.example.com', array( 'https://b.example.com/' ) )
		);
	}

	/**
	 * Already-trailing-slashed input still matches.
	 */
	public function test_is_valid_site_url_normalises_trailing_slash(): void {
		$utils = new WP_Multisite_Internal_SSO_Utils();
		$this->assertTrue(
			$utils->is_valid_site_url( 'https://b.example.com/', array( 'https://b.example.com/' ) )
		);
	}

	/**
	 * An empty allowlist rejects everything.
	 */
	public function test_is_valid_site_url_empty_allowlist_is_false(): void {
		$utils = new WP_Multisite_Internal_SSO_Utils();
		$this->assertFalse(
			$utils->is_valid_site_url( 'https://b.example.com', array() )
		);
	}
}
