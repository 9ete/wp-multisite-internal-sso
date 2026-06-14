/**
 * Custom Cypress commands for WP Multisite Internal SSO e2e tests.
 */

/**
 * Log in to WordPress as the network super-admin.
 *
 * Uses programmatic login (cy.request) wrapped in cy.session so credentials are
 * established once and restored for every test.
 *
 * @example cy.wpLogin();
 */
Cypress.Commands.add( 'wpLogin', ( username, password ) => {
	const user = username || Cypress.env( 'test_user' );
	const pass = password || Cypress.env( 'test_pass' );

	cy.session(
		[ 'wp-admin', user ],
		() => {
			cy.request( '/wp-login.php' );
			cy.request( {
				method: 'POST',
				url: '/wp-login.php',
				form: true,
				body: {
					log: user,
					pwd: pass,
					'wp-submit': 'Log In',
					redirect_to: '/wp-admin/',
					testcookie: '1',
				},
				followRedirect: true,
			} ).then( ( response ) => {
				expect( response.status ).to.eq( 200 );
				expect( response.body ).to.not.include( 'id="loginform"' );
			} );
		},
		{
			cacheAcrossSpecs: true,
			validate() {
				cy.request( { url: '/wp-admin/', failOnStatusCode: false } ).then( ( response ) => {
					expect( response.status ).to.eq( 200 );
					expect( response.body ).to.not.include( 'id="loginform"' );
				} );
			},
		}
	);
} );

/**
 * Visit the network-admin settings page for the plugin.
 */
Cypress.Commands.add( 'visitSsoSettings', () => {
	cy.visit( '/wp-admin/network/settings.php?page=wp-multisite-internal-sso' );
} );
