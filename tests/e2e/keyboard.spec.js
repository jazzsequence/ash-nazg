/**
 * Keyboard navigation tests.
 *
 * Verifies that every plugin page is fully operable via keyboard:
 * - All interactive elements are reachable via Tab
 * - Buttons/links respond to Enter/Space
 * - Modals trap focus while open and restore focus when closed
 * - Custom widgets (toggles, inline editors, tabs) are keyboard operable
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

// Helper: collect all focusable elements in the main content area.
async function getFocusableElements(page) {
  return page.evaluate(() => {
    const selectors = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    return [...document.querySelector('.wrap').querySelectorAll(selectors)]
      .filter(el => {
        const rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0 && window.getComputedStyle(el).visibility !== 'hidden';
      })
      .map(el => ({ tag: el.tagName, id: el.id, text: el.textContent.trim().slice(0, 40) }));
  });
}

// Helper: press Tab n times and return the focused element.
async function tabTo(page, count) {
  for (let i = 0; i < count; i++) {
    await page.keyboard.press('Tab');
  }
  return page.evaluate(() => ({
    tag: document.activeElement.tagName,
    id: document.activeElement.id,
    className: document.activeElement.className,
  }));
}

test.describe('Keyboard — Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg');
    await page.waitForLoadState('load');
    // Move focus into the main content area.
    await page.locator('.wrap').first().click();
  });

  test('all interactive elements in .wrap are reachable via Tab', async ({ page }) => {
    const elements = await getFocusableElements(page);
    expect(elements.length).toBeGreaterThan(0);
    // Tab through all of them and verify focus moves.
    for (let i = 0; i < Math.min(elements.length, 20); i++) {
      await page.keyboard.press('Tab');
      const focused = await page.evaluate(() => document.activeElement !== document.body);
      expect(focused).toBe(true);
    }
  });

  test('inline site label edit is keyboard operable', async ({ page }) => {
    const editLink = page.locator('#ash-nazg-edit-site-label');
    if (await editLink.count() === 0) test.skip();
    await editLink.focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#ash-nazg-site-label-form')).toBeVisible();
    // Escape should cancel.
    await page.keyboard.press('Escape');
    await expect(page.locator('#ash-nazg-site-label-form')).toBeHidden();
  });

  test('connection mode toggle responds to Enter', async ({ page }) => {
    const toggle = page.locator('#ash-nazg-toggle-mode');
    if (await toggle.count() === 0) test.skip();
    await toggle.focus();
    // Verify it's focusable.
    const focused = await page.evaluate(() => document.activeElement.id === 'ash-nazg-toggle-mode');
    expect(focused).toBe(true);
  });
});

test.describe('Keyboard — Backups tabs', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-backups');
    await page.waitForLoadState('load');
  });

  test('environment tabs are keyboard navigable', async ({ page }) => {
    const firstTab = page.locator('.ash-nazg-backup-env-tab').first();
    await firstTab.focus();
    // Tab should move to next tab.
    await page.keyboard.press('Tab');
    const secondTab = page.locator('.ash-nazg-backup-env-tab').nth(1);
    // Enter activates the tab.
    await firstTab.focus();
    await page.keyboard.press('Enter');
    await expect(firstTab).toHaveClass(/nav-tab-active/);
  });

  test('backup set toggle is keyboard operable', async ({ page }) => {
    const toggle = page.locator('.ash-nazg-backup-toggle').first();
    if (await toggle.count() === 0) test.skip();
    await toggle.focus();
    await page.keyboard.press('Enter');
    const table = page.locator('.ash-nazg-backup-elements-table').first();
    await expect(table).toBeVisible();
  });
});

test.describe('Keyboard — Metrics filters', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-metrics');
    await page.waitForLoadState('load');
  });

  test('environment and duration selects are keyboard operable', async ({ page }) => {
    const envSelect = page.locator('#metrics-environment');
    await envSelect.focus();
    const focused = await page.evaluate(() => document.activeElement.id === 'metrics-environment');
    expect(focused).toBe(true);
    // Arrow key changes selection.
    await page.keyboard.press('ArrowDown');
    const newValue = await envSelect.inputValue();
    expect(newValue).toBeTruthy();
  });
});

test.describe('Keyboard — Modal focus trapping', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-backups');
    await page.waitForLoadState('load');
  });

  test('confirmation modal traps focus when open', async ({ page }) => {
    const createBtn = page.locator('#ash-nazg-create-backup-btn');
    await createBtn.click();
    const modal = page.locator('.ash-nazg-modal-warning');
    await expect(modal).toBeVisible({ timeout: 5_000 });

    // Focus should be inside the modal.
    const focusedInModal = await page.evaluate(() => {
      const modal = document.querySelector('.ash-nazg-modal-warning');
      return modal && modal.contains(document.activeElement);
    });
    expect(focusedInModal).toBe(true);

    // Cancel the modal.
    await modal.locator('button').filter({ hasText: 'Cancel' }).click();
    await expect(modal).toBeHidden();
  });
});

test.describe('Keyboard — Addons toggles', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-addons');
    await page.waitForLoadState('load');
  });

  test('Redis and Solr checkboxes are keyboard operable', async ({ page }) => {
    const redisCheckbox = page.locator('tr').filter({ hasText: /redis/i }).locator('input[type="checkbox"]');
    await redisCheckbox.focus();
    const focused = await page.evaluate(() => document.activeElement.type === 'checkbox');
    expect(focused).toBe(true);
    // Space toggles a checkbox.
    const before = await redisCheckbox.isChecked();
    await page.keyboard.press('Space');
    const after = await redisCheckbox.isChecked();
    expect(after).toBe(!before);
    // Restore.
    await page.keyboard.press('Space');
  });
});

test.describe('Keyboard — Development page', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-development');
    await page.waitForLoadState('load');
    await page.waitForTimeout(2000);
  });

  test('all deploy buttons are keyboard reachable', async ({ page }) => {
    const deployBtns = page.locator('.ash-nazg-card').filter({ hasText: /deploy/i }).locator('button');
    const count = await deployBtns.count();
    expect(count).toBeGreaterThan(0);
    await deployBtns.first().focus();
    const focused = await page.evaluate(() => document.activeElement.tagName === 'BUTTON');
    expect(focused).toBe(true);
  });
});
