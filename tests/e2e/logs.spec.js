/**
 * Logs page user journey tests.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Logs', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-logs');
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
    // Wait for the loading indicator to hide — reliable signal AJAX completed.
    // Connection-mode switching can take a while, hence the longer timeout.
    await expect(page.locator('#ash-nazg-logs-loading')).toBeHidden({ timeout: 30_000 });
    // Container should now have text content (log entries or empty-log notice from JS).
    await expect(page.locator('#ash-nazg-logs-container')).not.toBeEmpty();
  });
});
