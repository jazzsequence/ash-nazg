/**
 * Settings page functional tests.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Settings', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-settings');
  });

  test('settings page renders without errors', async ({ page }) => {
    await expect(page.locator('.wrap h1')).toContainText(/settings/i);
    await expect(page.locator('.notice-error')).toHaveCount(0);
  });

  test('machine token section is present', async ({ page }) => {
    // Token may be configured (showing status) or unconfigured (showing form).
    await expect(page.locator('text=/machine token/i').first()).toBeVisible({ timeout: 5000 });
  });

  test('session token section renders', async ({ page }) => {
    await expect(page.locator('text=/session token/i').first()).toBeVisible({ timeout: 5000 });
  });

  test('per-user token documentation is visible', async ({ page }) => {
    // When a global token is present the migration UI shows; when per-user
    // token is set it shows the user ID for Pantheon Secrets setup.
    // Either way, per-user token content appears on the page.
    await expect(page.locator('text=/per-user|user id/i').first()).toBeVisible({ timeout: 5000 });
  });
});
