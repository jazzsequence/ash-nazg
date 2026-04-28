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
    test.setTimeout(180_000); // Backup workflows take 60-120s on Pantheon
    // Select database-only backup to minimize time.
    await page.selectOption('#backup-element', 'database');
    await page.fill('#backup-keep-for', '7');

    // Click the create button.
    const createBtn = page.locator('#ash-nazg-create-backup-btn');
    await expect(createBtn).toBeEnabled();
    await createBtn.click();

    // AshNazgModal confirmation dialog appears first — click Confirm.
    const confirmModal = page.locator('.ash-nazg-modal-warning');
    await expect(confirmModal).toBeVisible({ timeout: 5_000 });
    await confirmModal.locator('button').filter({ hasText: 'Confirm' }).click();

    // Backup workflow starts asynchronously. Wait for the success/error modal
    // or for the button to re-enable, then reload and verify the backup exists.
    await page.waitForResponse(
      r => r.url().includes('admin-ajax.php') && r.request().postData()?.includes('ash_nazg_create_backup'),
      { timeout: 15_000 }
    );

    // Poll until a backup set appears in the active (visible) tab panel.
    await expect(async () => {
      await page.reload();
      await page.waitForLoadState('networkidle');
      // Backup sets inside hidden panels won't be visible; check the active panel.
      await expect(
        page.locator('.ash-nazg-backup-env-panel:not(.hidden) .ash-nazg-backup-set').first()
      ).toBeVisible();
    }).toPass({ timeout: 150_000, intervals: [15_000] });
  });

  test('download button generates a signed URL', async ({ page }) => {
    const activePanel = page.locator('.ash-nazg-backup-env-panel:not(.hidden)');
    await expect(activePanel).toBeVisible({ timeout: 10_000 });

    // Backup sets are collapsed by default — expand the first one.
    const firstToggle = activePanel.locator('.ash-nazg-backup-toggle').first();
    await expect(firstToggle).toBeVisible({ timeout: 10_000 });
    await firstToggle.click();

    const downloadBtn = activePanel.locator('.ash-nazg-download-backup').first();
    await expect(downloadBtn).toBeVisible({ timeout: 5_000 });

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
