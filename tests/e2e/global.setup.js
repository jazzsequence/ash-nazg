/**
 * Global setup: capture authenticated WP admin session.
 *
 * WP Playground's blueprint `login` block auto-establishes the session
 * without a password — navigating to /wp-admin/ triggers the auto-login
 * and redirects to the dashboard without needing form credentials.
 */
const { test: setup, expect } = require('@playwright/test');
const path = require('path');

const AUTH_FILE = path.join(__dirname, '.auth/admin.json');

setup('authenticate as admin', async ({ page }) => {
  // WP Playground auto-login: the blueprint login block creates the session.
  // Navigating to /wp-admin/ triggers it and redirects to the dashboard.
  await page.goto('/wp-admin/');
  await expect(page).toHaveURL(/wp-admin/, { timeout: 15_000 });

  await page.context().storageState({ path: AUTH_FILE });
});
