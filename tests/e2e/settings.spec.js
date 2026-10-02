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

  test('token status or per-user setup documentation is visible', async ({ page }) => {
    await expect(page.locator('text=/per-user|user id|currently set/i').first()).toBeVisible({ timeout: 5000 });
  });
});
