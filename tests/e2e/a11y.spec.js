/**
 * Accessibility tests — axe-core sweeps across every Ash-Nazg admin page.
 *
 * Each test navigates to a page, waits for content to render (including
 * AJAX-loaded data), then runs axe-core checking WCAG 2.1 AA compliance.
 * Critical and serious violations fail the test; moderate/minor are reported
 * but do not block (review attached JSON report for full details).
 */
const { expect } = require('@playwright/test');
const { test, assertNoA11yViolations, goToPluginPage } = require('./fixtures/admin-page');

const PLUGIN_PAGES = [
  { slug: 'ash-nazg',             label: 'Dashboard' },
  { slug: 'ash-nazg-metrics',     label: 'Metrics' },
  { slug: 'ash-nazg-development', label: 'Development' },
  { slug: 'ash-nazg-addons',      label: 'Addons' },
  { slug: 'ash-nazg-workflows',   label: 'Workflows' },
  { slug: 'ash-nazg-backups',     label: 'Backups' },
  { slug: 'ash-nazg-clone',       label: 'Clone' },
  { slug: 'ash-nazg-logs',        label: 'Logs' },
  { slug: 'ash-nazg-settings',    label: 'Settings' },
];

for (const { slug, label } of PLUGIN_PAGES) {
  test(`${label} page has no critical/serious a11y violations`, async ({ page }, testInfo) => {
    test.setTimeout(60_000);
    await goToPluginPage(page, slug);
    await page.waitForTimeout(4000);
    await assertNoA11yViolations(page, testInfo);
  });
}

test('Dashboard widget on WP admin index has no a11y violations', async ({ page }, testInfo) => {
  await page.goto('/wp-admin/index.php');
  await page.waitForLoadState('load');
  await page.waitForTimeout(3000);
  await assertNoA11yViolations(page, testInfo);
});

test('Backups page tab switching maintains a11y', async ({ page }, testInfo) => {
  await goToPluginPage(page, 'ash-nazg-backups');
  await page.waitForTimeout(2000);

  const tabs = page.locator('.ash-nazg-backup-env-tab');
  const tabCount = await tabs.count();
  for (let i = 0; i < tabCount; i++) {
    await tabs.nth(i).click();
    await page.waitForTimeout(500);
  }

  await assertNoA11yViolations(page, testInfo);
});

test('Metrics page with data loaded has no a11y violations', async ({ page }, testInfo) => {
  await goToPluginPage(page, 'ash-nazg-metrics');

  const loadBtn = page.locator('#load-metrics');
  if (await loadBtn.isVisible()) {
    await loadBtn.click();
    await page.waitForTimeout(4000);
  }

  await assertNoA11yViolations(page, testInfo);
});

test.describe('Dashboard endpoint status filters', () => {
  test.beforeEach(async ({ page }) => {
    // The hook's wait counts toward the test timeout (30s by default), so the limit has to be raised here, not in
    // the test bodies, which only run after the hook: 180s for the rows to resolve plus headroom for the test.
    test.setTimeout(300_000);

    await goToPluginPage(page, 'ash-nazg');
    // Endpoint rows are probed one request at a time after the page loads; wait until
    // every row has resolved so the panel is scanned in its final state, not half-loaded.
    await expect(page.locator('.ash-nazg-endpoint-pending')).toHaveCount(0, { timeout: 180_000 });
  });

  test('filters are real buttons in a labelled group, defaulting to Needs attention with All last', async ({ page }) => {
    const group = page.getByRole('group', { name: 'Filter endpoints by status' });
    await expect(group).toBeVisible();

    const buttons = group.locator('button.nav-tab');
    await expect(buttons).toHaveCount(4);
    await expect(buttons.first()).toContainText('Needs attention');
    await expect(buttons.first()).toHaveAttribute('aria-pressed', 'true');
    await expect(buttons.last()).toContainText('All');
    await expect(buttons.last()).toHaveAttribute('aria-pressed', 'false');

    // The version filter has an associated visible label.
    await expect(page.getByLabel('API version:')).toBeVisible();
  });

  test('Space and Enter activate a filter, aria-pressed follows, and the change is announced', async ({ page }) => {
    const group = page.getByRole('group', { name: 'Filter endpoints by status' });
    const needsAttention = group.getByRole('button', { name: /^Needs attention/ });
    const working = group.getByRole('button', { name: /^Working/ });
    const all = group.getByRole('button', { name: /^All/ });
    const status = page.locator('#ash-nazg-endpoint-filter-status');

    await working.focus();
    await page.keyboard.press('Space');
    await expect(working).toHaveAttribute('aria-pressed', 'true');
    await expect(needsAttention).toHaveAttribute('aria-pressed', 'false');
    await expect(status).toHaveText(/\d+ endpoints shown/);

    await all.focus();
    await page.keyboard.press('Enter');
    await expect(all).toHaveAttribute('aria-pressed', 'true');
    await expect(working).toHaveAttribute('aria-pressed', 'false');
    await expect(status).toHaveText(/\d+ endpoints shown/);
  });

  test('every status filter and API version has no critical/serious a11y violations', async ({ page }, testInfo) => {
    const buttons = page.getByRole('group', { name: 'Filter endpoints by status' }).locator('button.nav-tab');
    const count = await buttons.count();
    for (let i = 0; i < count; i++) {
      await buttons.nth(i).click();
      await assertNoA11yViolations(page, testInfo);
    }

    for (const version of ['v1', 'v0', '']) {
      await page.locator('#ash-nazg-endpoint-version').selectOption(version);
      await assertNoA11yViolations(page, testInfo);
    }
  });
});
