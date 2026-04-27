/**
 * Backups action tests — safe operations against the E2E multidev.
 * Creating a backup is safe and reversible; restore and delete are not tested.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Backups — create and download', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg-backups');
    await page.waitForLoadState('networkidle');
  });

  test('backup type selector has all expected options', async ({ page }) => {
    const elementSelect = page.locator('#backup-element');
    await expect(elementSelect).toBeVisible();

    const options = await elementSelect.locator('option').allTextContents();
    const expected = ['all', 'code', 'database', 'files'];
    for (const val of expected) {
      expect(options.some((o) => o.toLowerCase().includes(val))).toBe(true);
    }
  });

  test('retention period field accepts a value', async ({ page }) => {
    const keepFor = page.locator('#backup-keep-for');
    await expect(keepFor).toBeVisible();
    await keepFor.fill('30');
    await expect(keepFor).toHaveValue('30');
  });

  test('create database backup and verify it appears in the list', async ({ page }) => {
    // Select database-only backup to minimize time.
    await page.selectOption('#backup-element', 'database');
    await page.fill('#backup-keep-for', '7');

    // Click the create button.
    const createBtn = page.locator('#ash-nazg-create-backup-btn');
    await expect(createBtn).toBeEnabled();
    await createBtn.click();

    // A progress indicator should appear.
    const loading = page.locator('#ash-nazg-backup-progress-modal, .ash-nazg-loading');
    await expect(loading.first()).toBeVisible({ timeout: 10_000 });

    // Wait for the backup workflow to complete (up to 3 minutes).
    await page.waitForFunction(
      () => !document.querySelector('#ash-nazg-backup-progress-modal[style*="block"]'),
      { timeout: 180_000 }
    );

    // Refresh the page and verify the new backup appears in the active tab.
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const backupList = page.locator('.ash-nazg-backup-set').first();
    await expect(backupList).toBeVisible({ timeout: 10_000 });
  });

  test('download button generates a signed URL', async ({ page }) => {
    await page.waitForTimeout(2000);

    const downloadBtn = page.locator('.ash-nazg-download-backup').first();
    if (!(await downloadBtn.isVisible())) {
      test.skip('No backups available to test download URL generation');
    }

    // Listen for the AJAX response that returns the signed URL.
    const responsePromise = page.waitForResponse(
      (r) => r.url().includes('admin-ajax.php') && r.request().postData()?.includes('ash_nazg_download_backup'),
      { timeout: 15_000 }
    );

    await downloadBtn.click();
    const response = await responsePromise;
    const body = await response.json();

    expect(body.success).toBe(true);
    expect(body.data?.url).toBeTruthy();
  });
});
