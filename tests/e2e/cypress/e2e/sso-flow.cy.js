/**
 * E2E: the cross-site SSO redirect flow on a live multisite network.
 *
 * Assumes the plugin is network-activated and configured with primary = site 1
 * and secondary = site 2 (see bin/e2e-setup or configure-sso.php).
 */
describe( 'MSSO cross-site SSO flow', () => {
	it( 'primary front-end loads with no PHP errors', () => {
		cy.request( '/?wpmisso_ignore=1' ).then( ( res ) => {
			expect( res.status ).to.eq( 200 );
			expect( res.body ).to.not.match( /Fatal error|Parse error|Uncaught|Notice:|Warning:/i );
		} );
	} );

	it( 'a logged-out hit on the secondary initiates the SSO redirect to the primary', () => {
		cy.clearCookies();
		cy.request( { url: '/site2/', followRedirect: false } ).then( ( res ) => {
			expect( res.status ).to.eq( 302 );
			expect( res.redirectedToUrl ).to.contain( 'wpmssso_redirect=1' );
			expect( res.redirectedToUrl ).to.contain( 'wpmssso_return' );
		} );
	} );

	it( 'the secondary sets a redirect-attempt cookie to prevent loops', () => {
		cy.clearCookies();
		// First hop sets the redirect cookie; second hop must not loop forever.
		cy.request( { url: '/site2/', followRedirect: false } );
		cy.getCookie( 'wpmssso_redirect_attempt' ).should( 'not.be.null' );
	} );
} );
