/**
 * Logs page user journey tests.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Logs', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-logs');
    await page.waitForLoadState('networkidle');
  });

  test('page renders without errors', async ({ page }) => {
    await expect(page.locator('.wrap')).toBeVisible();
    await expect(page.locator('.notice-error')).toHaveCount(0);
  });

  test('Fetch Logs button is present and clickable', async ({ page }) => {
    await expect(page.locator('#ash-nazg-fetch-logs')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#ash-nazg-fetch-logs')).toBeEnabled();
  });

  test('Clear Logs button is present', async ({ page }) => {
    await expect(page.locator('#ash-nazg-clear-logs')).toBeVisible({ timeout: 10_000 });
  });

  test('fetching logs loads content', async ({ page }) => {
    await page.locator('#ash-nazg-fetch-logs').click();
    // After fetching, the log container should have content.
    await expect(page.locator('#ash-nazg-logs-container')).toBeVisible({ timeout: 15_000 });
  });
});
