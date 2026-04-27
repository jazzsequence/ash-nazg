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
    const fetchBtn = page.locator('button, input[type="submit"]').filter({ hasText: /fetch logs/i });
    await expect(fetchBtn).toBeVisible({ timeout: 10_000 });
    await expect(fetchBtn).toBeEnabled();
  });

  test('Clear Logs button is present', async ({ page }) => {
    const clearBtn = page.locator('button, input[type="submit"]').filter({ hasText: /clear logs/i });
    await expect(clearBtn).toBeVisible({ timeout: 10_000 });
  });

  test('fetching logs loads content', async ({ page }) => {
    const fetchBtn = page.locator('button, input[type="submit"]').filter({ hasText: /fetch logs/i });
    await fetchBtn.click();

    // After fetching, either log content or a "no logs" message should appear.
    const logContent = page.locator('#ash-nazg-log-content, .ash-nazg-logs-content');
    await expect(logContent).toBeVisible({ timeout: 15_000 });
  });
});
