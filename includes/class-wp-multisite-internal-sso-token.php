<?php
/**
 * WP Multisite Internal SSO Token Class
 *
 * @package WP_Multisite_Internal_SSO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Issues and verifies single-use, HMAC-SHA256 signed SSO tokens.
 *
 * A token authorises exactly one cross-site auto-login hop:
 *   - signed with a network-wide secret (HMAC-SHA256) so it cannot be forged;
 *   - bound to a server-stored single-use id (jti) consumed on first successful
 *     verification, so a captured token cannot be replayed;
 *   - short-lived (configurable expiry) with a small clock-skew allowance;
 *   - compared in constant time; never written to logs.
 */
class WP_Multisite_Internal_SSO_Token {

	/**
	 * Network option holding the HMAC signing secret.
	 */
	const KEY_OPTION = 'wpmis_sso_signing_key';

	/**
	 * Prefix for the per-token single-use markers (site transients).
	 */
	const JTI_PREFIX = 'wpmis_sso_jti_';

	/**
	 * Allowed clock skew, in seconds, for not-yet-valid timestamps.
	 */
	const CLOCK_SKEW = 60;

	/**
	 * Settings manager.
	 *
	 * @var WP_Multisite_Internal_SSO_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param WP_Multisite_Internal_SSO_Settings $settings Settings manager instance.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Issue a single-use token for a user.
	 *
	 * @param int $user_id User ID to authenticate on the destination site.
	 * @return array Query args to append to the destination URL.
	 */
	public function issue( $user_id ) {
		$user_id = absint( $user_id );
		$time    = $this->now();
		$jti     = $this->generate_jti();
		$token   = $this->sign( $user_id, $time, $jti );

		// Record the jti network-wide so any site can consume it exactly once.
		set_site_transient( self::JTI_PREFIX . $jti, $user_id, $this->ttl() );

		return array(
			'wpmssso_user'  => $user_id,
			'wpmssso_time'  => $time,
			'wpmssso_nonce' => $jti,
			'wpmssso_token' => $token,
		);
	}

	/**
	 * Verify a token and consume its single-use marker.
	 *
	 * @param int    $user_id Claimed user ID.
	 * @param int    $time    Issue timestamp.
	 * @param string $jti     Single-use id.
	 * @param string $token   HMAC signature to check.
	 * @return int The authenticated user ID on success, 0 on failure.
	 */
	public function verify_and_consume( $user_id, $time, $jti, $token ) {
		$user_id = absint( $user_id );
		$time    = absint( $time );
		$jti     = $this->sanitize_jti( $jti );
		$token   = is_string( $token ) ? $token : '';

		if ( ! $user_id || ! $time || '' === $jti || '' === $token ) {
			return 0;
		}

		// Reject expired or implausibly-future timestamps.
		$now = $this->now();
		if ( $time > ( $now + self::CLOCK_SKEW ) || ( $now - $time ) > $this->ttl() ) {
			return 0;
		}

		// Constant-time signature comparison.
		if ( ! hash_equals( $this->sign( $user_id, $time, $jti ), $token ) ) {
			return 0;
		}

		// Single-use: the jti must still exist and be bound to this user.
		$key    = self::JTI_PREFIX . $jti;
		$issued = get_site_transient( $key );
		if ( false === $issued || absint( $issued ) !== $user_id ) {
			return 0;
		}

		delete_site_transient( $key ); // Consume — any replay now fails.

		return $user_id;
	}

	/**
	 * Compute the HMAC-SHA256 signature for a token payload.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $time    Issue timestamp.
	 * @param string $jti     Single-use id.
	 * @return string Hex signature.
	 */
	private function sign( $user_id, $time, $jti ) {
		return hash_hmac( 'sha256', $user_id . '|' . $time . '|' . $jti, $this->secret() );
	}

	/**
	 * Token time-to-live, in seconds.
	 *
	 * @return int
	 */
	private function ttl() {
		return (int) $this->settings->get_token_expiration();
	}

	/**
	 * Current Unix time. Isolated as a seam so expiry can be unit-tested.
	 *
	 * @return int
	 */
	protected function now() {
		return time();
	}

	/**
	 * The network-wide HMAC secret, generated once and stored as a network option.
	 *
	 * @return string
	 */
	private function secret() {
		$secret = get_site_option( self::KEY_OPTION );
		if ( ! is_string( $secret ) || strlen( $secret ) < 32 ) {
			$secret = wp_generate_password( 64, true, true );
			update_site_option( self::KEY_OPTION, $secret );
		}
		return $secret;
	}

	/**
	 * Generate a random single-use id.
	 *
	 * @return string
	 */
	private function generate_jti() {
		return $this->sanitize_jti( wp_generate_password( 40, false, false ) );
	}

	/**
	 * Restrict a jti to URL/transient-safe alphanumerics.
	 *
	 * @param string $jti Raw id.
	 * @return string
	 */
	private function sanitize_jti( $jti ) {
		return preg_replace( '/[^a-zA-Z0-9]/', '', (string) $jti );
	}
}
