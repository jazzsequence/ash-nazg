/**
 * Metrics page functional tests.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Metrics', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-metrics');
  });

  test('page renders summary statistics above filters', async ({ page }) => {
    const summary = page.locator('.ash-nazg-metrics-summary');
    const filters = page.locator('.ash-nazg-metrics-filters');

    await expect(summary).toBeVisible();
    await expect(filters).toBeVisible();

    // Summary must appear before filters in the DOM.
    const summaryBox = await summary.boundingBox();
    const filtersBox = await filters.boundingBox();
    expect(summaryBox.y).toBeLessThan(filtersBox.y);
  });

  test('Load Metrics button fetches real API data', async ({ page }) => {
    await page.click('#load-metrics');

    // Wait for charts to render after API call.
    await page.waitForTimeout(5000);

    // At least one chart canvas should be visible.
    const charts = page.locator('#pages-served-chart, #unique-visits-chart, #cache-performance-chart');
    const visibleCount = await charts.filter({ visible: true }).count();
    expect(visibleCount).toBeGreaterThan(0);
  });

  test('environment selector contains expected options', async ({ page }) => {
    const select = page.locator('#metrics-environment');
    await expect(select).toBeVisible();

    const options = await select.locator('option').allTextContents();
    // Should have at least dev.
    expect(options.some((o) => o.toLowerCase().includes('dev'))).toBe(true);
  });

  test('duration selector has all time period options', async ({ page }) => {
    const durations = ['7d', '28d', '12w', '12m'];
    for (const dur of durations) {
      await expect(page.locator(`#metrics-duration option[value="${dur}"]`)).toBeAttached();
    }
  });

  test('summary stats update after data loads', async ({ page }) => {
    await page.click('#load-metrics');
    await page.waitForTimeout(5000);

    const ratio = page.locator('#avg-cache-ratio');
    const text = await ratio.textContent();
    // Should no longer be the placeholder dash.
    expect(text.trim()).not.toBe('-');
  });

  test('filter persistence: selections survive page reload', async ({ page }) => {
    // Change duration to 12 weeks.
    await page.selectOption('#metrics-duration', '12w');
    await page.click('#load-metrics');
    await page.waitForTimeout(2000);

    // Reload.
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Duration should be restored from localStorage.
    const selected = await page.locator('#metrics-duration').inputValue();
    expect(selected).toBe('12w');
  });
});
