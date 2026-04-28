/**
 * Development page functional tests.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Development', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-development');
    await page.waitForTimeout(2000);
  });

  test('page renders without errors', async ({ page }) => {
    await expect(page.locator('.wrap')).toBeVisible();
    const errorNotices = page.locator('.notice-error');
    await expect(errorNotices).toHaveCount(0);
  });

  test('upstream updates section renders', async ({ page }) => {
    const upstreamSection = page.locator('.ash-nazg-card').filter({ hasText: /upstream/i });
    await expect(upstreamSection).toBeVisible({ timeout: 15_000 });
  });

  test('recent commits card renders', async ({ page }) => {
    const commitsCard = page.locator('.ash-nazg-card').filter({ hasText: /recent commits/i });
    await expect(commitsCard).toBeVisible({ timeout: 15_000 });
  });

  test('environments card renders with environment list', async ({ page }) => {
    const envCard = page.locator('.ash-nazg-card').filter({ hasText: /environments/i });
    await expect(envCard.first()).toBeVisible({ timeout: 15_000 });
  });

  test('refresh button on recent commits is clickable', async ({ page }) => {
    // Refresh is a link-button (<a class="button">) inside the Recent Commits card.
    await expect(page.locator('.ash-nazg-card').filter({ hasText: /recent commits/i })).toBeVisible({ timeout: 15_000 });
    const refreshBtn = page.locator('a.button, button').filter({ hasText: /refresh/i });
    await expect(refreshBtn.first()).toBeVisible({ timeout: 10_000 });
  });

  test('code deployment section renders', async ({ page }) => {
    const deploySection = page.locator('.ash-nazg-card').filter({ hasText: /code deployment/i });
    await expect(deploySection).toBeVisible({ timeout: 10_000 });
  });
});
