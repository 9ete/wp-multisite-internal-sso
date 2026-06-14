=== WP Multisite Internal SSO ===
Contributors: 9ete
Tags: multisite, sso, single sign-on, network, login
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
Network: true
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Seamless single sign-on across the sites in one WordPress Multisite network: log in once on the primary site and you are signed in everywhere.

== Description ==

WP Multisite Internal SSO links the sites in a single WordPress Multisite network so a user who authenticates on the primary site is automatically signed in on the secondary sites — with no second login prompt.

It is built for **internal** network SSO (one network, one shared user table), not for third-party identity providers.

= How it works =

* Choose a **primary site** (where users log in) and one or more **secondary sites** from a network-admin settings screen.
* When a logged-out visitor reaches a secondary site, the plugin bounces once to the primary site and — if a session exists there — issues a **single-use, HMAC-SHA256 signed token** that the secondary site verifies and consumes to establish the session.
* Tokens are short-lived, single-use (replay-protected), constant-time compared, and never written to logs.

= Features =

* Network-admin settings: primary-site picker plus a per-site enable toggle for any number of secondary sites.
* HMAC-SHA256 single-use tokens with a configurable expiry window.
* "Log out everywhere" across the whole network in one click.
* Optional, opt-in, `WP_DEBUG`-only front-end status panel for troubleshooting.
* Translation-ready (text domain: `wp-multisite-internal-sso`).

== Installation ==

1. This plugin requires a **WordPress Multisite** network.
2. Upload the `wp-multisite-internal-sso` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
3. **Network Activate** the plugin (Network Admin → Plugins).
4. Visit **Network Admin → Settings → Multisite SSO** and choose the primary site and the secondary site(s).
5. Ensure each user who should be signed in automatically exists on both the primary and the relevant secondary sites.

== Frequently Asked Questions ==

= Does this work outside of Multisite? =

No. It is designed for a single WordPress Multisite network and is network-activated.

= Does it integrate with Google, SAML, or OAuth? =

No. This is *internal* SSO between the sites of one network that already share a user table. It is not an external identity-provider integration.

= How secure are the tokens? =

Each token is HMAC-SHA256 signed with a network-wide secret, single-use (consumed on first verification, so it cannot be replayed), short-lived (configurable expiry, default five minutes), and compared in constant time. Tokens are never written to logs.

= A user is not being signed in on a secondary site. =

Confirm the user account exists on that secondary site, the secondary site is enabled in the settings, and the token has not expired.

== Screenshots ==

1. The network-admin settings screen: primary-site picker, per-site secondary toggles, and token/cookie options.

== Changelog ==

= 1.0.0 =
* Network-admin settings screen (primary-site picker + per-site secondary toggles) replacing the previous hardcoded site configuration.
* HMAC-SHA256, single-use, replay-protected tokens with configurable expiry and constant-time verification.
* Support for any number of secondary sites.
* Front-end status output gated behind a `WP_DEBUG`-only opt-in setting.
* Full security pass: input sanitization, output escaping, safe redirects, capability and nonce checks, and removal of direct database calls.
* Internationalization pass and `.pot` generation.

== Upgrade Notice ==

= 1.0.0 =
Hardened, single-use SSO tokens and a network-admin settings UI replacing hardcoded site configuration.
