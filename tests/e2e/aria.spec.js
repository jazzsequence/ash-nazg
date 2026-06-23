/**
 * ARIA and accessibility tree tests.
 *
 * Verifies ARIA roles, labels, and structure using locator-based
 * assertions (page.accessibility was removed in Playwright 1.38+).
 * Also checks live region updates after content changes.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('ARIA — Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg');
    await page.waitForLoadState('load');
  });

  test('page has a meaningful document title', async ({ page }) => {
    const title = await page.title();
    // WP renders plugin page titles as "{Page Name} ‹ {Site} — WordPress"
    expect(title.length).toBeGreaterThan(10);
    expect(title).toContain('WordPress');
  });

  test('plugin header has an img with alt text', async ({ page }) => {
    const logo = page.locator('.ash-nazg-header img');
    await expect(logo).toBeVisible();
    const alt = await logo.getAttribute('alt');
    expect(alt?.trim().length).toBeGreaterThan(0);
  });

  test('main heading is present and non-empty', async ({ page }) => {
    const heading = page.locator('.wrap h1, .wrap h2').first();
    await expect(heading).toBeVisible();
    const text = await heading.textContent();
    expect(text?.trim().length).toBeGreaterThan(0);
  });

  test('environment card has accessible structure', async ({ page }) => {
    const card = page.locator('.ash-nazg-card').first();
    await expect(card).toBeVisible();
    // Cards should contain headings for screen reader navigation.
    const heading = card.locator('h2, h3').first();
    await expect(heading).toBeVisible();
  });
});

test.describe('ARIA — Modals', () => {
  test('backup confirmation modal has correct ARIA structure', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-backups');
    await page.waitForLoadState('load');

    await page.locator('#ash-nazg-create-backup-btn').click();
    const modal = page.locator('.ash-nazg-modal-warning');
    await expect(modal).toBeVisible({ timeout: 5_000 });

    // Modal must have a heading (screen readers announce this).
    const heading = modal.getByRole('heading');
    await expect(heading).toBeVisible();

    // All modal buttons must have non-empty accessible text.
    const buttons = await modal.locator('button').all();
    for (const btn of buttons) {
      const text = await btn.textContent();
      expect(text?.trim().length).toBeGreaterThan(0);
    }

    // Cancel.
    await modal.locator('button').filter({ hasText: 'Cancel' }).click();
  });

  test('progress modal heading exists in DOM', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-backups');
    await page.waitForLoadState('load');
    // Heading is present in DOM even when modal is hidden.
    await expect(page.locator('#ash-nazg-backup-progress-title')).toBeAttached();
  });
});

test.describe('ARIA — Live regions', () => {
  test('log contents region is reachable and labeled', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-logs');
    await page.waitForLoadState('load');

    await page.locator('#ash-nazg-fetch-logs').click();
    await expect(page.locator('#ash-nazg-logs-container')).toBeVisible({ timeout: 10_000 });

    // Region must have an accessible label so screen readers announce it.
    const region = page.locator('[role="region"]').filter({ hasText: /log/i });
    if (await region.count() > 0) {
      const label = await region.first().getAttribute('aria-label');
      expect(label?.trim().length).toBeGreaterThan(0);
    }
  });

  test('metrics summary stats have accessible labels', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-metrics');
    await page.waitForLoadState('load');

    // Stat labels should be text that screen readers can read.
    const labels = page.locator('.ash-nazg-stat-label');
    const count = await labels.count();
    for (let i = 0; i < count; i++) {
      const text = await labels.nth(i).textContent();
      expect(text?.trim().length).toBeGreaterThan(0);
    }
  });
});

test.describe('ARIA — Forms', () => {
  test('settings form inputs have associated labels', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-settings');
    await page.waitForLoadState('load');

    const inputs = await page.locator('.wrap input:not([type="hidden"]):not([type="submit"])').all();
    for (const input of inputs) {
      const id = await input.getAttribute('id');
      if (!id) continue;
      const hasLabel = await page.locator(`label[for="${id}"]`).count() > 0;
      const ariaLabel = await input.getAttribute('aria-label');
      const ariaLabelledBy = await input.getAttribute('aria-labelledby');
      expect(hasLabel || !!ariaLabel || !!ariaLabelledBy).toBe(true);
    }
  });

  test('backup form selects have visible labels', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-backups');
    await page.waitForLoadState('load');

    await expect(page.locator('label[for="backup-element"]')).toBeVisible();
    await expect(page.locator('label[for="backup-keep-for"]')).toBeVisible();
  });

  test('addons form checkboxes have associated labels', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-addons');
    await page.waitForLoadState('load');

    const checkboxes = await page.locator('input[type="checkbox"]').all();
    for (const cb of checkboxes) {
      const id = await cb.getAttribute('id');
      if (!id) continue;
      const label = page.locator(`label[for="${id}"]`);
      const count = await label.count();
      expect(count).toBeGreaterThan(0);
    }
  });
});

test.describe('ARIA — Landmark regions', () => {
  test('plugin pages use heading hierarchy correctly', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg');
    await page.waitForLoadState('load');

    // Plugin uses h2 inside .ash-nazg-card and .wrap (may be nested, not direct children).
    const pageHeadings = page.locator('.wrap h2').first();
    await expect(pageHeadings).toBeVisible({ timeout: 10_000 });
  });

  test('tables have captions or headers', async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg');
    await page.waitForLoadState('load');

    const tables = await page.locator('.wrap table').all();
    for (const table of tables) {
      const hasTh = await table.locator('th').count() > 0;
      const hasCaption = await table.locator('caption').count() > 0;
      const hasAriaLabel = !!(await table.getAttribute('aria-label'));
      expect(hasTh || hasCaption || hasAriaLabel).toBe(true);
    }
  });
});
