# WP Multisite Internal SSO — Agent Instructions

WordPress **multisite-only** plugin (`Network: true`) that auto-logs a user in across
sites in one network: authenticate on the primary site, get auto-logged-in on the
secondary site(s) via a short-lived signed token passed on a single redirect hop.

## Quality gate (run before every commit)

```bash
composer lint        # phpcs (WPCS 3.x + PHPCompatibility-WP) — zero errors
composer test        # phpunit unit suite — zero failures
```

Full release gate (requires a running Lando multisite network):

```bash
composer check       # lint + phpunit + jest + cypress e2e + plugin-check
```

## Non-negotiables

- **PHPCS/WPCS clean** (zero errors) and **Plugin Check clean** on the **built release artifact**.
- Security (this plugin is an auth surface — treat it as hostile input everywhere):
  - Sanitize/validate **all input** (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER`) with `wp_unslash()` + the right `sanitize_*`/`absint`/`esc_url_raw`.
  - Escape **all output** (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
  - Nonces + capability checks on every privileged/state-changing action.
  - SSO tokens: HMAC-SHA256, single-use (replay-protected), short expiry, **constant-time** compare (`hash_equals`), **never logged**, never persisted in URLs beyond the one-time hop.
  - Redirects: `wp_safe_redirect()` and/or validate the destination against the network site allowlist (no open redirects).
  - Prepared SQL only; prefer WP APIs (`get_sites()`, network options) over raw `$wpdb`.
- Keep changes minimal, targeted, backwards compatible. Class-based, DRY, no dead code.

## Release artifact rules

Plugin Check must run on the **built/extracted artifact**, not the dev repo. The
`composer plugin-check` script builds `wp-multisite-internal-sso.zip` via `bin/build-zip.sh`,
extracts it, runs Plugin Check, then cleans up. Dev tooling is excluded from the zip via
`.distignore` — never delete tooling to satisfy Plugin Check; exclude it.

## Where things live

- Main bootstrap: `wp-multisite-internal-sso.php`
- PHP classes: `includes/class-wp-multisite-internal-sso-*.php`
- Runtime assets: `assets/css`, `assets/js`
- wp.org listing-only assets (banner/icon/screenshots): `assets/wporg/` (excluded from zip)
- Tests: `tests/Unit` (phpunit), `tests/e2e/cypress` (e2e)
- Translations: `languages/wp-multisite-internal-sso.pot`

## Conventions

- Text domain: `wp-multisite-internal-sso`. Function/global prefixes: `wpmis_sso` / `wpmisso`.
- Conventional commits, ticket-prefixed: `MSSO-NN: feat(scope): summary`. No co-author / AI attribution lines.
