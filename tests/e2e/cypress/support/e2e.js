/**
 * Cypress support file — loaded before every e2e spec.
 *
 * Login is handled per-spec by cy.wpLogin() (see commands.js). The network
 * super-admin already exists from `wp core multisite-install`, so no user
 * bootstrapping task is required.
 */
import './commands';
