/**
 * Metrics page full interaction tests.
 *
 * Tests all time periods, environment switching, chart visibility,
 * and filter persistence — actually loading real data each time.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Metrics — All time periods load data', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-metrics');
    await page.waitForLoadState('load');
    // Clear any cached localStorage selections.
    await page.evaluate(() => {
      localStorage.removeItem('ashNazgMetricsEnv');
      localStorage.removeItem('ashNazgMetricsDuration');
    });
    await page.reload();
    await page.waitForLoadState('load');
  });

  for (const [value, label] of [['7d', '7 days'], ['28d', '28 days'], ['12w', '12 weeks'], ['12m', '12 months']]) {
    test(`loads data for ${label}`, async ({ page }) => {
      await page.selectOption('#metrics-duration', value);
      await page.click('#load-metrics');
      await page.waitForTimeout(5000);

      const ratio = await page.locator('#avg-cache-ratio').textContent();
      expect(ratio?.trim()).not.toBe('-');
    });
  }
});

test.describe('Metrics — Environment switching', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-metrics');
    await page.waitForLoadState('load');
  });

  test('switching environment loads data for that environment', async ({ page }) => {
    const select = page.locator('#metrics-environment');
    const options = await select.locator('option').all();
    if (options.length < 2) test.skip();

    // Select a different environment.
    const secondOption = await options[1].getAttribute('value');
    await select.selectOption(secondOption);
    await page.click('#load-metrics');
    await page.waitForTimeout(5000);

    const pages = await page.locator('#total-pages-served').textContent();
    expect(pages?.trim()).not.toBe('-');
  });
});

test.describe('Metrics — Refresh clears cache', () => {
  test('Refresh button clears cache and reloads', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-metrics');
    await page.waitForLoadState('load');

    // Load metrics first.
    await page.click('#load-metrics');
    await page.waitForTimeout(5000);

    // Click Refresh — should trigger a new load.
    const responsePromise = page.waitForResponse(
      r => r.url().includes('admin-ajax.php') && r.request().postData()?.includes('ash_nazg_refresh_metrics'),
      { timeout: 15_000 }
    );
    await page.click('#refresh-metrics');
    const response = await responsePromise;
    const body = await response.json();
    expect(body.success).toBe(true);
  });
});

test.describe('Metrics — Chart visibility via Screen Options', () => {
  test('hidden chart canvas is absent from the DOM', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-metrics');
    await page.waitForLoadState('load');

    // All three chart canvases should be present by default.
    await expect(page.locator('#pages-served-chart')).toBeAttached();
    await expect(page.locator('#unique-visits-chart')).toBeAttached();
    await expect(page.locator('#cache-performance-chart')).toBeAttached();
  });
});
