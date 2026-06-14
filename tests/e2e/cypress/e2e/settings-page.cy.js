/**
 * E2E: the network-admin settings screen renders and saves.
 */
describe( 'MSSO network settings page', () => {
	beforeEach( () => {
		cy.wpLogin();
	} );

	it( 'renders the SSO settings form in network admin', () => {
		cy.visitSsoSettings();
		cy.get( 'h1' ).should( 'contain.text', 'WP Multisite Internal SSO' );
		cy.get( 'select[name="wpmis_sso_settings[primary_site_id]"]' ).should( 'exist' );
		cy.get( 'input[name="wpmis_sso_settings[secondary_site_ids][]"]' ).should( 'exist' );
		cy.get( 'input[name="wpmis_sso_settings[token_expiration]"]' ).should( 'exist' );
		cy.get( 'input[name="wpmis_sso_settings[secure_cookies]"]' ).should( 'exist' );
	} );

	it( 'persists settings through the network save handler (nonce + cap + redirect)', () => {
		// Drive the real save endpoint: read the page for a fresh nonce, then POST
		// to the network_admin_edit handler and assert the success redirect.
		cy.request( '/wp-admin/network/settings.php?page=wp-multisite-internal-sso' ).then( ( page ) => {
			const nonce = page.body.match( /name="_wpnonce" value="([^"]+)"/ )[ 1 ];
			cy.request( {
				method: 'POST',
				url: '/wp-admin/network/edit.php?action=wpmis_sso_save',
				form: true,
				followRedirect: false,
				body: {
					_wpnonce: nonce,
					'wpmis_sso_settings[primary_site_id]': '1',
					'wpmis_sso_settings[secondary_site_ids][]': '2',
					'wpmis_sso_settings[token_expiration]': '300',
					'wpmis_sso_settings[redirect_cookie_name]': 'wpmssso_redirect_attempt',
				},
			} ).then( ( res ) => {
				expect( res.status ).to.eq( 302 );
				expect( res.redirectedToUrl ).to.contain( 'updated=true' );
			} );
		} );
	} );

	it( 'rejects a save with a bad nonce', () => {
		cy.request( {
			method: 'POST',
			url: '/wp-admin/network/edit.php?action=wpmis_sso_save',
			form: true,
			failOnStatusCode: false,
			body: { _wpnonce: 'bogus', 'wpmis_sso_settings[primary_site_id]': '2' },
		} ).then( ( res ) => {
			expect( res.status ).to.not.eq( 302 );
		} );
	} );
} );
