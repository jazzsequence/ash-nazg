/**
 * Global setup: log in to WP Admin and save auth state.
 * Runs once before all test projects so every test starts authenticated.
 */
const { test: setup, expect } = require('@playwright/test');
const path = require('path');

const AUTH_FILE = path.join(__dirname, '.auth/admin.json');

setup('authenticate as admin', async ({ page }) => {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'password');
  await page.click('#wp-submit');

  // Wait for the dashboard to confirm login succeeded.
  await expect(page).toHaveURL(/wp-admin/);

  await page.context().storageState({ path: AUTH_FILE });
});
