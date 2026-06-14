const { defineConfig } = require( 'cypress' );

/**
 * Cypress config for WP Multisite Internal SSO e2e.
 *
 * Targets a Lando WordPress **multisite** network (subdirectory) with at least
 * two sites and this plugin network-activated. Override the base URL via
 * CYPRESS_BASE_URL for a different environment.
 *
 * Local env used to author these specs:
 *   ~/Sites/wpmssso  (lando app, name "wpmssso")
 *   site 1: https://wpmssso.lndo.site/   (primary)
 *   site 2: https://wpmssso.lndo.site/site2/  (secondary)
 */
module.exports = defineConfig( {
	e2e: {
		baseUrl: process.env.CYPRESS_BASE_URL || 'https://wpmssso.lndo.site',
		specPattern: 'tests/e2e/cypress/e2e/**/*.cy.js',
		supportFile: 'tests/e2e/cypress/support/e2e.js',
		screenshotsFolder: 'tests/e2e/cypress/screenshots',
		videosFolder: 'tests/e2e/cypress/videos',
		video: false,
		// Lando serves a self-signed cert and the SSO flow bounces between sites.
		chromeWebSecurity: false,
	},
	env: {
		// The network super-admin created by `wp core multisite-install`.
		test_user: process.env.CYPRESS_TEST_USER || 'admin',
		test_pass: process.env.CYPRESS_TEST_PASS || 'password',
	},
} );
