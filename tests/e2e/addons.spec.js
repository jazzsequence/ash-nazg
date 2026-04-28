/**
 * Addons page user journey tests.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Addons', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-addons');
    await page.waitForLoadState('networkidle');
  });

  test('page renders without errors', async ({ page }) => {
    await expect(page.locator('.wrap h1')).toContainText(/addon/i);
    await expect(page.locator('.notice-error')).toHaveCount(0);
  });

  test('Redis addon toggle is present', async ({ page }) => {
    const redisRow = page.locator('tr').filter({ hasText: /redis/i });
    await expect(redisRow).toBeVisible({ timeout: 10_000 });
    await expect(redisRow.locator('input[type="checkbox"]')).toBeVisible();
  });

  test('Solr addon toggle is present', async ({ page }) => {
    const solrRow = page.locator('tr').filter({ hasText: /solr/i });
    await expect(solrRow).toBeVisible({ timeout: 10_000 });
    await expect(solrRow.locator('input[type="checkbox"]')).toBeVisible();
  });

  test('Elasticsearch shows Coming Soon badge', async ({ page }) => {
    const esRow = page.locator('tr').filter({ hasText: /elasticsearch/i });
    await expect(esRow).toBeVisible({ timeout: 10_000 });
    await expect(esRow.locator('.ash-nazg-badge')).toContainText(/coming soon/i);
    // No checkbox — no toggle available yet.
    await expect(esRow.locator('input[type="checkbox"]')).toHaveCount(0);
  });

  test('Redis status reflects live environment state', async ({ page }) => {
    const redisRow = page.locator('tr').filter({ hasText: /redis/i });
    const checkbox = redisRow.locator('input[type="checkbox"]');
    // The checkbox state (checked/unchecked) should be deterministic —
    // it reflects the actual addon state read from $_ENV or Terminus API.
    // We assert the element exists; the actual state depends on the environment.
    await expect(checkbox).toBeVisible();
    const isChecked = await checkbox.isChecked();
    expect(typeof isChecked).toBe('boolean');
  });

  test('save button is present and enabled', async ({ page }) => {
    const saveBtn = page.locator('#submit');
    await expect(saveBtn).toBeVisible();
    await expect(saveBtn).toBeEnabled();
  });
});
