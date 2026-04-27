/**
 * Workflows page user journey tests.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Workflows', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-workflows');
    await page.waitForLoadState('networkidle');
  });

  test('page renders without errors', async ({ page }) => {
    await expect(page.locator('.wrap')).toBeVisible();
    await expect(page.locator('.notice-error')).toHaveCount(0);
  });

  test('available workflows section renders', async ({ page }) => {
    // Either shows workflows or a "no workflows" notice.
    const content = page.locator('.workflows-container, .notice-info');
    await expect(content.first()).toBeVisible({ timeout: 10_000 });
  });

  test('Object Cache Pro workflow card is present', async ({ page }) => {
    const ocpCard = page.locator('.workflow-card').filter({ hasText: /object cache pro/i });
    await expect(ocpCard).toBeVisible({ timeout: 10_000 });
  });

  test('trigger workflow button is present', async ({ page }) => {
    const triggerBtn = page.locator('input[type="submit"][value*="Trigger"]');
    await expect(triggerBtn.first()).toBeVisible({ timeout: 10_000 });
  });

  // Note: we deliberately do NOT trigger the workflow in automated tests —
  // installing OCP on the multidev would require license validation
  // and could leave the environment in an unexpected state.
});
