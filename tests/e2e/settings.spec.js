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

  test('machine token field is present', async ({ page }) => {
    const tokenForm = page.locator('form').filter({ hasText: /machine token/i });
    await expect(tokenForm).toBeVisible();
  });

  test('session token section renders', async ({ page }) => {
    await expect(page.locator('text=session token')).toBeVisible({ timeout: 5000 });
  });

  test('user ID for Pantheon Secrets is displayed', async ({ page }) => {
    const userIdSection = page.locator('text=/user id|your user id/i');
    await expect(userIdSection).toBeVisible({ timeout: 5000 });
  });
});
