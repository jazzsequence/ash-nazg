/**
 * Clone content page user journey tests.
 * We verify the UI renders correctly but do not trigger an actual clone
 * (which would overwrite the multidev's database).
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Clone', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-clone');
  });

  test('page renders without errors', async ({ page }) => {
    await expect(page.locator('.wrap')).toBeVisible();
    await expect(page.locator('.notice-error')).toHaveCount(0);
  });

  test('source environment selector is present', async ({ page }) => {
    const fromSelect = page.locator('select[name="from_env"]');
    await expect(fromSelect).toBeVisible({ timeout: 10_000 });

    const options = await fromSelect.locator('option').allTextContents();
    // Should include at least dev.
    expect(options.some((o) => o.toLowerCase().includes('dev'))).toBe(true);
  });

  test('target environment selector is present', async ({ page }) => {
    const toSelect = page.locator('select[name="to_env"]');
    await expect(toSelect).toBeVisible({ timeout: 10_000 });
  });

  test('database and files checkboxes are present', async ({ page }) => {
    await expect(page.locator('input[name="clone_database"]')).toBeVisible();
    await expect(page.locator('input[name="clone_files"]')).toBeVisible();
  });

  test('clone button is present', async ({ page }) => {
    const cloneBtn = page.locator('button, input[type="submit"]').filter({ hasText: /clone/i });
    await expect(cloneBtn.first()).toBeVisible({ timeout: 10_000 });
  });
});
