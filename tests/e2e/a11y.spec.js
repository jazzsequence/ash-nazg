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
    await goToPluginPage(page, slug);
    await page.waitForTimeout(2000);
    await assertNoA11yViolations(page, testInfo);
  });
}

test('Dashboard widget on WP admin index has no a11y violations', async ({ page }, testInfo) => {
  await page.goto('/wp-admin/index.php');
  await page.waitForLoadState('networkidle');
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
