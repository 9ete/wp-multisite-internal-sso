/**
 * E2E screenshot capture for the WordPress.org plugin page.
 *
 * Captures the plugin's key admin screen so the `== Screenshots ==` set in
 * readme.txt stays reproducible. Output lands in
 * tests/e2e/cypress/screenshots/wp-repo-screenshots.cy.js/ and is promoted to
 * screenshot-{N}.png at plugin root by bin/sync-wp-repo-screenshots.sh.
 *
 * Invocation:
 *   CYPRESS_BASE_URL=https://wpmssso.lndo.site \
 *     npx cypress run --spec tests/e2e/cypress/e2e/wp-repo-screenshots.cy.js
 *   ./bin/sync-wp-repo-screenshots.sh
 */
describe( 'WP Multisite Internal SSO — WP.org screenshots', () => {
	beforeEach( () => {
		cy.viewport( 1544, 1000 );
		cy.wpLogin();
	} );

	it( 'captures the network settings screen (screenshot-1)', () => {
		cy.visitSsoSettings();
		cy.get( 'h1' ).should( 'contain.text', 'WP Multisite Internal SSO' );
		cy.get( 'select[name="wpmis_sso_settings[primary_site_id]"]' ).should( 'be.visible' );
		cy.screenshot( '01-network-settings', { capture: 'fullPage', overwrite: true } );
	} );
} );
