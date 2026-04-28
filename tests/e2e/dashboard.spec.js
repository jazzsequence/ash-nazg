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
    await expect(page.locator('.widefat').filter({ hasText: /endpoint/i }).first()).toBeVisible({ timeout: 10_000 });
  });

  test('inline site label editing is accessible', async ({ page }) => {
    const editLink = page.locator('#ash-nazg-edit-site-label');
    if (await editLink.isVisible()) {
      await editLink.click();
      await expect(page.locator('#ash-nazg-site-label-form')).toBeVisible();
      await page.locator('#ash-nazg-cancel-site-label').click();
    }
  });

  test('connection mode toggle is present with a valid mode', async ({ page }) => {
    // Toggle only renders on Pantheon environments in SFTP or Git mode.
    const toggle = page.locator('#ash-nazg-toggle-mode');
    if (await toggle.count() === 0) {
      test.skip();
    }
    await expect(toggle).toBeVisible({ timeout: 10_000 });
    const mode = await toggle.getAttribute('data-mode');
    expect(['sftp', 'git']).toContain(mode);
  });
});

test.describe('WP Admin Dashboard Widget', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/index.php');
    await page.waitForLoadState('networkidle');
    // Widget only registers when the plugin has a valid Pantheon site ID.
    const present = await page.locator('#ash_nazg_metrics_widget').count() > 0;
    if (!present) test.skip();
  });

  test('widget renders on wp-admin index', async ({ page }) => {
    await expect(page.locator('#ash_nazg_metrics_widget')).toBeVisible();
  });

  test('widget chart renders after data loads', async ({ page }) => {
    // jQuery .show() overrides display:none with an inline style without removing
    // the ash-nazg-hidden class, so check computed visibility instead.
    await page.waitForFunction(
      () => {
        const el = document.getElementById('ash-nazg-widget-content');
        return el && window.getComputedStyle(el).display !== 'none';
      },
      { timeout: 20_000 }
    );
    await expect(page.locator('#ash-nazg-widget-chart')).toBeVisible();
  });

  test('widget links to Metrics page', async ({ page }) => {
    await expect(page.locator('#ash_nazg_metrics_widget a[href*="ash-nazg-metrics"]')).toBeVisible();
  });
});
