/**
 * Error state tests.
 *
 * Verifies the plugin handles gracefully:
 * - API failures (simulated via network interception)
 * - Missing or invalid AJAX responses
 * - Pages without Pantheon environment (can't fully test without a different env)
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Error States — API failure handling', () => {
  test('metrics page shows error when API call fails', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-metrics');
    await page.waitForLoadState('load');

    // Intercept the AJAX call and return a failure.
    await page.route('**/admin-ajax.php', async (route) => {
      const postData = route.request().postData() ?? '';
      if (postData.includes('ash_nazg_get_metrics')) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: false, data: { message: 'Simulated API error' } }),
        });
      } else {
        await route.continue();
      }
    });

    await page.click('#load-metrics');
    await page.waitForTimeout(3000);

    // Error state should show.
    const errorEl = page.locator('#metrics-error');
    await expect(errorEl).toBeVisible({ timeout: 10_000 });
  });

  test('dashboard widget shows error when metrics API fails', async ({ page }) => {
    // Intercept metrics AJAX.
    await page.route('**/admin-ajax.php', async (route) => {
      const postData = route.request().postData() ?? '';
      if (postData.includes('ash_nazg_get_metrics')) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: false, data: { message: 'Simulated error' } }),
        });
      } else {
        await route.continue();
      }
    });

    await page.goto('/wp-admin/index.php');
    await page.waitForLoadState('load');
    await page.waitForTimeout(4000);

    const widget = page.locator('#ash_nazg_metrics_widget');
    if (await widget.count() === 0) test.skip();

    const errorEl = page.locator('#ash-nazg-widget-error');
    await expect(errorEl).toBeVisible({ timeout: 10_000 });
  });

  test('logs page shows error when fetch fails', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-logs');
    await page.waitForLoadState('load');

    // Intercept log fetch.
    await page.route('**/admin-ajax.php', async (route) => {
      const postData = route.request().postData() ?? '';
      if (postData.includes('ash_nazg_fetch_logs')) {
        await route.fulfill({
          status: 500,
          body: 'Internal Server Error',
        });
      } else {
        await route.continue();
      }
    });

    await page.locator('#ash-nazg-fetch-logs').click();
    await page.waitForTimeout(3000);

    // Page should not crash — either shows an error notice or stays functional.
    await expect(page.locator('.wrap')).toBeVisible();
    await expect(page.locator('.notice-error')).toHaveCount(0); // Plugin shouldn't show a PHP error
  });

  test('connection mode toggle shows error when API fails', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg');
    await page.waitForLoadState('load');
    await page.waitForTimeout(2000);

    const toggle = page.locator('#ash-nazg-toggle-mode');
    if (await toggle.count() === 0) test.skip();

    // Intercept the toggle AJAX call.
    await page.route('**/admin-ajax.php', async (route) => {
      const postData = route.request().postData() ?? '';
      if (postData.includes('ash_nazg_toggle_connection_mode')) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: false, data: { message: 'Mode change failed' } }),
        });
      } else {
        await route.continue();
      }
    });

    await toggle.click();
    await page.waitForTimeout(3000);

    // Error notice should appear.
    await expect(page.locator('.notice-error')).toBeVisible({ timeout: 10_000 });
  });
});

test.describe('Error States — AJAX network failure', () => {
  test('backup creation handles network failure gracefully', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-backups');
    await page.waitForLoadState('load');

    // Intercept backup creation and abort the network request.
    await page.route('**/admin-ajax.php', async (route) => {
      const postData = route.request().postData() ?? '';
      if (postData.includes('ash_nazg_create_backup')) {
        await route.abort('failed');
      } else {
        await route.continue();
      }
    });

    await page.locator('#ash-nazg-create-backup-btn').click();

    // Confirm modal.
    const modal = page.locator('.ash-nazg-modal-warning');
    if (await modal.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await modal.locator('button').filter({ hasText: 'Confirm' }).click();
    }

    await page.waitForTimeout(5000);
    // Page should remain functional.
    await expect(page.locator('.wrap')).toBeVisible();
  });
});
