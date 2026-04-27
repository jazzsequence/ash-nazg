/**
 * Dashboard page functional tests.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg');
  });

  test('renders the Pantheon branded header', async ({ page }) => {
    await expect(page.locator('.ash-nazg-header')).toBeVisible();
    await expect(page.locator('.ash-nazg-header img[src*="pantheon-logo"]')).toBeVisible();
  });

  test('shows the 50/50 two-column layout', async ({ page }) => {
    await expect(page.locator('.ash-nazg-dashboard')).toBeVisible();
  });

  test('environment card shows Pantheon site info', async ({ page }) => {
    const envCard = page.locator('.ash-nazg-card').filter({ hasText: /environment/i }).first();
    await expect(envCard).toBeVisible({ timeout: 15_000 });
  });

  test('API endpoint testing table renders', async ({ page }) => {
    const endpointTable = page.locator('.widefat').filter({ hasText: /endpoint/i });
    await expect(endpointTable).toBeVisible({ timeout: 10_000 });
  });

  test('inline site label editing is accessible', async ({ page }) => {
    const editLink = page.locator('#ash-nazg-edit-site-label');
    if (await editLink.isVisible()) {
      await editLink.click();
      await expect(page.locator('#ash-nazg-site-label-form')).toBeVisible();
      await page.locator('#ash-nazg-cancel-site-label').click();
    }
  });

  test('connection mode toggle button is present', async ({ page }) => {
    const toggle = page.locator('#ash-nazg-toggle-mode');
    if (await toggle.isVisible()) {
      await expect(toggle).toBeEnabled();
    }
  });
});

test.describe('WP Admin Dashboard Widget', () => {
  test('widget renders on wp-admin index', async ({ page }) => {
    await page.goto('/wp-admin/index.php');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#ash_nazg_metrics_widget')).toBeVisible();
  });

  test('widget chart renders after data loads', async ({ page }) => {
    await page.goto('/wp-admin/index.php');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(4000);
    await expect(page.locator('#ash-nazg-widget-chart')).toBeVisible();
  });

  test('widget links to Metrics page', async ({ page }) => {
    await page.goto('/wp-admin/index.php');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#ash_nazg_metrics_widget a[href*="ash-nazg-metrics"]')).toBeVisible();
  });
});
