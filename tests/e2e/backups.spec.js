/**
 * Backups page functional tests.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Backups', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-backups');
    await page.waitForTimeout(2000);
  });

  test('page renders with tabbed environment UI', async ({ page }) => {
    await expect(page.locator('.ash-nazg-backup-tabs')).toBeVisible();
    const tabs = page.locator('.ash-nazg-backup-env-tab');
    expect(await tabs.count()).toBeGreaterThanOrEqual(1);
  });

  test('default tab is visible and active', async ({ page }) => {
    const activeTab = page.locator('.ash-nazg-backup-env-tab.nav-tab-active');
    await expect(activeTab).toBeVisible();
  });

  test('tab switching shows the correct panel', async ({ page }) => {
    const tabs = page.locator('.ash-nazg-backup-env-tab');
    const count = await tabs.count();

    for (let i = 0; i < count; i++) {
      const tab = tabs.nth(i);
      const env = await tab.getAttribute('data-env');
      await tab.click();

      await expect(page.locator(`#ash-nazg-backup-env-${env}`)).toBeVisible();
    }
  });

  test('backup creation form renders', async ({ page }) => {
    const form = page.locator('#ash-nazg-create-backup-form');
    await expect(form).toBeVisible();
    await expect(page.locator('#backup-environment')).toBeVisible();
    await expect(page.locator('#backup-element')).toBeVisible();
    await expect(page.locator('#ash-nazg-create-backup-btn')).toBeVisible();
  });

  test('existing backups section loads', async ({ page }) => {
    // Check the active (visible) tab panel for backup sets or an empty message.
    const content = page.locator('.ash-nazg-backup-env-panel:not(.hidden)');
    await expect(content.first()).toBeVisible({ timeout: 15_000 });
  });
});
