/**
 * Global setup: log in to the Pantheon multidev WP admin and save auth state.
 *
 * The admin password is set by the CI workflow via Terminus before tests run
 * and passed in via the E2E_WP_PASSWORD environment variable.
 *
 * For local runs against a real Pantheon environment, set:
 *   PLAYWRIGHT_BASE_URL=https://dev-cxr-ash-nazg.pantheonsite.io
 *   E2E_WP_PASSWORD=<your-dev-admin-password>
 */
const { test: setup, expect } = require('@playwright/test');
const path = require('path');

const AUTH_FILE = path.join(__dirname, '.auth/admin.json');

setup('authenticate as admin', async ({ page }) => {
  const password = process.env.E2E_WP_PASSWORD;
  if (!password) {
    throw new Error('E2E_WP_PASSWORD environment variable is not set.');
  }

  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', password);
  await page.click('#wp-submit');

  await expect(page).toHaveURL(/wp-admin/, { timeout: 15_000 });

  await page.context().storageState({ path: AUTH_FILE });
});
