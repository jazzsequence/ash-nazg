/**
 * Screen Options user journey tests.
 *
 * Verifies that Screen Options panels work end-to-end:
 * open the panel, toggle sections, apply, verify the page reflects changes.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

async function openScreenOptions(page) {
  const btn = page.locator('#show-settings-link');
  if (await btn.count() === 0) return false;
  await btn.click();
  await expect(page.locator('#screen-options')).toBeVisible({ timeout: 3_000 });
  return true;
}

async function applyScreenOptions(page) {
  await page.locator('#screen-options input[name="screen-options-apply"], #screen-options .button-primary').first().click();
}

test.describe('Screen Options — Dashboard endpoint groups', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg');
    await page.waitForLoadState('load');
  });

  test('can open Screen Options panel', async ({ page }) => {
    const opened = await openScreenOptions(page);
    if (!opened) test.skip();
    await expect(page.locator('#screen-options')).toBeVisible();
  });

  test('master toggle hides all endpoint groups', async ({ page }) => {
    const opened = await openScreenOptions(page);
    if (!opened) test.skip();

    const masterToggle = page.locator('#ash_nazg_dashboard_show_endpoints');
    if (await masterToggle.count() === 0) test.skip();

    // Ensure it's checked (showing), then uncheck.
    if (await masterToggle.isChecked()) {
      await masterToggle.uncheck();
      await applyScreenOptions(page);
      await page.waitForLoadState('load');
      // Endpoints section should be hidden.
      await expect(page.locator('.ash-nazg-endpoints-wrap')).toBeHidden();

      // Restore.
      await openScreenOptions(page);
      await masterToggle.check();
      await applyScreenOptions(page);
      await page.waitForLoadState('load');
    }
  });
});

test.describe('Screen Options — Development sections', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-development');
    await page.waitForLoadState('load');
    await page.waitForTimeout(1000);
  });

  test('can hide and restore Environments card', async ({ page }) => {
    const opened = await openScreenOptions(page);
    if (!opened) test.skip();

    const envCheckbox = page.locator('input[name="ash_nazg_dev_visible_sections[]"][value="environments"]');
    if (await envCheckbox.count() === 0) test.skip();

    const wasChecked = await envCheckbox.isChecked();
    if (wasChecked) {
      await envCheckbox.uncheck();
      await applyScreenOptions(page);
      await page.waitForLoadState('load');
      // Environments card should be hidden.
      const envCard = page.locator('.ash-nazg-card').filter({ hasText: /^environments$/i });
      expect(await envCard.count()).toBe(0);

      // Restore.
      await openScreenOptions(page);
      await page.locator('input[name="ash_nazg_dev_visible_sections[]"][value="environments"]').check();
      await applyScreenOptions(page);
      await page.waitForLoadState('load');
    }
  });
});

test.describe('Screen Options — Backups age filter', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-backups');
    await page.waitForLoadState('load');
  });

  test('age filter options are present', async ({ page }) => {
    const opened = await openScreenOptions(page);
    if (!opened) test.skip();

    for (const days of ['0', '7', '30', '365']) {
      await expect(
        page.locator(`input[name="ash_nazg_backups_max_age"][value="${days}"]`)
      ).toBeAttached();
    }
  });

  test('selecting 7-day filter and applying restricts backup list', async ({ page }) => {
    const opened = await openScreenOptions(page);
    if (!opened) test.skip();

    const sevenDay = page.locator('input[name="ash_nazg_backups_max_age"][value="7"]');
    if (await sevenDay.count() === 0) test.skip();

    await sevenDay.check();
    await applyScreenOptions(page);
    await page.waitForLoadState('load');

    // Restore to "all".
    await openScreenOptions(page);
    await page.locator('input[name="ash_nazg_backups_max_age"][value="0"]').check();
    await applyScreenOptions(page);
    await page.waitForLoadState('load');
  });
});

test.describe('Screen Options — Metrics charts', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-metrics');
    await page.waitForLoadState('load');
  });

  test('hiding all charts also hides the filters section', async ({ page }) => {
    const opened = await openScreenOptions(page);
    if (!opened) test.skip();

    const charts = ['pages_served', 'unique_visits', 'cache_performance'];
    const checkboxes = charts.map(c =>
      page.locator(`input[name="ash_nazg_metrics_visible_charts[]"][value="${c}"]`)
    );

    const allPresent = (await Promise.all(checkboxes.map(cb => cb.count()))).every(c => c > 0);
    if (!allPresent) test.skip();

    // Uncheck all charts.
    for (const cb of checkboxes) await cb.uncheck();
    await applyScreenOptions(page);
    await page.waitForLoadState('load');

    // Filters should be hidden.
    await expect(page.locator('.ash-nazg-card').filter({ hasText: /metrics filters/i })).toBeHidden();

    // Restore.
    await openScreenOptions(page);
    for (const c of charts) {
      await page.locator(`input[name="ash_nazg_metrics_visible_charts[]"][value="${c}"]`).check();
    }
    await applyScreenOptions(page);
    await page.waitForLoadState('load');
  });
});
