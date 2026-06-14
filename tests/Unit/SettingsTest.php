<?php
/**
 * Unit tests for WP_Multisite_Internal_SSO_Settings (network-option storage).
 *
 * @package WP_Multisite_Internal_SSO
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers settings sanitization and the URL/ID derivation getters.
 */
final class SettingsTest extends TestCase {

	/**
	 * Build a Settings instance backed by the stubbed WP environment.
	 *
	 * @return WP_Multisite_Internal_SSO_Settings
	 */
	private function make_settings(): WP_Multisite_Internal_SSO_Settings {
		return new WP_Multisite_Internal_SSO_Settings( new WP_Multisite_Internal_SSO_Utils() );
	}

	/**
	 * Reset the simulated network: main site 1, plus sites 2 and 3.
	 */
	protected function setUp(): void {
		$GLOBALS['_test_site_options'] = array();
		$GLOBALS['_test_main_site_id'] = 1;
		$GLOBALS['_test_sites']        = array(
			(object) array( 'blog_id' => 1 ),
			(object) array( 'blog_id' => 2 ),
			(object) array( 'blog_id' => 3 ),
		);
		$GLOBALS['_test_site_urls'] = array(
			1 => 'https://primary.example.com',
			2 => 'https://b.example.com',
			3 => 'https://c.example.com',
		);
	}

	/**
	 * Valid primary + secondaries survive sanitization with correct types.
	 */
	public function test_sanitize_keeps_valid_values(): void {
		$out = $this->make_settings()->sanitize_settings(
			array(
				'primary_site_id'      => '1',
				'secondary_site_ids'   => array( '2', '3' ),
				'token_expiration'     => '600',
				'redirect_cookie_name' => 'My_Cookie',
				'secure_cookies'       => '1',
			)
		);
		$this->assertSame( 1, $out['primary_site_id'] );
		$this->assertSame( array( 2, 3 ), $out['secondary_site_ids'] );
		$this->assertSame( 600, $out['token_expiration'] );
		$this->assertSame( 'my_cookie', $out['redirect_cookie_name'] );
		$this->assertTrue( $out['secure_cookies'] );
	}

	/**
	 * Unknown site IDs and the primary itself are dropped from secondaries.
	 */
	public function test_sanitize_drops_invalid_and_primary_from_secondaries(): void {
		$out = $this->make_settings()->sanitize_settings(
			array(
				'primary_site_id'    => '2',
				'secondary_site_ids' => array( '2', '3', '99' ),
			)
		);
		$this->assertSame( 2, $out['primary_site_id'] );
		$this->assertSame( array( 3 ), $out['secondary_site_ids'] );
	}

	/**
	 * An invalid primary falls back to the network main site.
	 */
	public function test_sanitize_invalid_primary_falls_back_to_main(): void {
		$out = $this->make_settings()->sanitize_settings( array( 'primary_site_id' => '99' ) );
		$this->assertSame( 1, $out['primary_site_id'] );
	}

	/**
	 * Token expiration below the floor is clamped up.
	 */
	public function test_sanitize_clamps_token_expiration(): void {
		$out = $this->make_settings()->sanitize_settings( array( 'token_expiration' => '5' ) );
		$this->assertSame( 60, $out['token_expiration'] );
	}

	/**
	 * Secondary IDs resolve to trailing-slashed URLs.
	 */
	public function test_get_secondary_sites_maps_ids_to_urls(): void {
		$GLOBALS['_test_site_options']['wpmis_sso_settings'] = array(
			'primary_site_id'    => 1,
			'secondary_site_ids' => array( 2, 3 ),
		);
		$this->assertSame(
			array( 'https://b.example.com/', 'https://c.example.com/' ),
			$this->make_settings()->get_secondary_sites()
		);
	}

	/**
	 * With nothing configured, get_secondary_sites() returns [] (never throws).
	 */
	public function test_get_secondary_sites_empty_when_unconfigured(): void {
		$this->assertSame( array(), $this->make_settings()->get_secondary_sites() );
	}

	/**
	 * Primary URL is derived from the stored primary ID.
	 */
	public function test_get_primary_site_derives_url_from_id(): void {
		$GLOBALS['_test_site_options']['wpmis_sso_settings'] = array( 'primary_site_id' => 2 );
		$this->assertSame( 'https://b.example.com/', $this->make_settings()->get_primary_site() );
	}

	/**
	 * Sensible defaults apply when the network option is absent.
	 */
	public function test_defaults_when_unconfigured(): void {
		$settings = $this->make_settings();
		$this->assertSame( 1, $settings->get_primary_site_id() );
		$this->assertSame( 300, $settings->get_token_expiration() );
		$this->assertSame( 'wpmssso_redirect_attempt', $settings->get_redirect_cookie_name() );
	}

	/**
	 * is_secondary_site() matches any enabled secondary and rejects others.
	 */
	public function test_is_secondary_site_membership(): void {
		$GLOBALS['_test_site_options']['wpmis_sso_settings'] = array(
			'primary_site_id'    => 1,
			'secondary_site_ids' => array( 2, 3 ),
		);
		$settings = $this->make_settings();
		$this->assertTrue( $settings->is_secondary_site( 'https://b.example.com' ) );
		$this->assertTrue( $settings->is_secondary_site( 'https://c.example.com/' ) );
		$this->assertFalse( $settings->is_secondary_site( 'https://primary.example.com' ) );
		$this->assertFalse( $settings->is_secondary_site( 'https://evil.example.com' ) );
	}

	/**
	 * secure_cookies getter reflects the stored value.
	 */
	public function test_secure_cookies_getter(): void {
		$GLOBALS['_test_site_options']['wpmis_sso_settings'] = array( 'secure_cookies' => true );
		$this->assertTrue( $this->make_settings()->are_secure_cookies_enabled() );
	}

	/**
	 * A sanitize → persist → get round-trip yields consistent derived values.
	 */
	public function test_sanitize_persist_get_roundtrip(): void {
		$settings = $this->make_settings();
		$GLOBALS['_test_site_options']['wpmis_sso_settings'] = $settings->sanitize_settings(
			array(
				'primary_site_id'      => '1',
				'secondary_site_ids'   => array( '2', '3' ),
				'token_expiration'     => '120',
				'redirect_cookie_name' => 'roundtrip',
				'secure_cookies'       => '1',
				'status_panel'         => '1',
			)
		);
		$this->assertSame( 'https://primary.example.com/', $settings->get_primary_site() );
		$this->assertSame( array( 'https://b.example.com/', 'https://c.example.com/' ), $settings->get_secondary_sites() );
		$this->assertSame( 120, $settings->get_token_expiration() );
		$this->assertSame( 'roundtrip', $settings->get_redirect_cookie_name() );
		$this->assertTrue( $settings->are_secure_cookies_enabled() );
		$this->assertTrue( $settings->is_status_panel_enabled() );
	}

	/**
	 * The debug status panel is off by default and can be opted into.
	 */
	public function test_status_panel_defaults_off_and_opts_in(): void {
		$this->assertFalse( $this->make_settings()->is_status_panel_enabled() );

		$out = $this->make_settings()->sanitize_settings( array( 'status_panel' => '1' ) );
		$this->assertTrue( $out['status_panel'] );

		$GLOBALS['_test_site_options']['wpmis_sso_settings'] = array( 'status_panel' => true );
		$this->assertTrue( $this->make_settings()->is_status_panel_enabled() );
	}
}
