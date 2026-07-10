/**
 * Inline editing user journey tests.
 *
 * Tests the site label and organization inline editing flows
 * end-to-end: open editor, change value, save, verify, restore.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Inline Site Label Editing', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg');
    await page.waitForLoadState('load');
  });

  test('can open the inline editor', async ({ page }) => {
    const editLink = page.locator('#ash-nazg-edit-site-label');
    if (await editLink.count() === 0) test.skip();

    await editLink.click();
    await expect(page.locator('#ash-nazg-site-label-form')).toBeVisible();
    await expect(page.locator('#ash-nazg-site-label-input')).toBeVisible();
  });

  test('cancel restores the original label', async ({ page }) => {
    const editLink = page.locator('#ash-nazg-edit-site-label');
    if (await editLink.count() === 0) test.skip();

    const originalLabel = await page.locator('#ash-nazg-site-label-display span').first().textContent();
    await editLink.click();
    await page.locator('#ash-nazg-site-label-input').fill('Test Label Change');
    await page.locator('#ash-nazg-cancel-site-label').click();

    await expect(page.locator('#ash-nazg-site-label-form')).toBeHidden();
    const restoredLabel = await page.locator('#ash-nazg-site-label-display span').first().textContent();
    expect(restoredLabel).toBe(originalLabel);
  });

  test('saves a new label and displays it', async ({ page }) => {
    const editLink = page.locator('#ash-nazg-edit-site-label');
    if (await editLink.count() === 0) test.skip();

    const originalLabel = await page.locator('#ash-nazg-site-label-display span').first().textContent();
    const testLabel = `E2E Test ${Date.now()}`;

    await editLink.click();
    await page.locator('#ash-nazg-site-label-input').fill(testLabel);
    await page.locator('#ash-nazg-save-site-label').click();

    // Success notice should appear.
    await expect(page.locator('.notice-success')).toBeVisible({ timeout: 10_000 });

    // Label display should show new value.
    await expect(page.locator('#ash-nazg-site-label-display')).toContainText(testLabel);

    // Restore original label.
    await page.locator('#ash-nazg-edit-site-label').click();
    await page.locator('#ash-nazg-site-label-input').fill(originalLabel?.trim() ?? '');
    await page.locator('#ash-nazg-save-site-label').click();
    await page.locator('.notice-success').waitFor({ timeout: 10_000 });
  });

  test('Enter key submits the inline label form', async ({ page }) => {
    const editLink = page.locator('#ash-nazg-edit-site-label');
    if (await editLink.count() === 0) test.skip();

    const originalLabel = await page.locator('#ash-nazg-site-label-display span').first().textContent();

    await editLink.click();
    const input = page.locator('#ash-nazg-site-label-input');
    await input.fill(originalLabel?.trim() ?? 'Test');
    await input.press('Enter');

    await expect(page.locator('.notice-success')).toBeVisible({ timeout: 10_000 });
  });
});

test.describe('Inline Organization Selector', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg');
    await page.waitForLoadState('load');
  });

  test('org edit button opens the selector', async ({ page }) => {
    const editBtn = page.locator('#ash-nazg-org-edit');
    if (await editBtn.count() === 0) test.skip();

    await editBtn.click();
    await expect(page.locator('#ash-nazg-org-select-wrap')).toBeVisible();
    await expect(page.locator('#ash-nazg-org-select')).toBeVisible();
  });

  test('org cancel hides the selector', async ({ page }) => {
    const editBtn = page.locator('#ash-nazg-org-edit');
    if (await editBtn.count() === 0) test.skip();

    await editBtn.click();
    await expect(page.locator('#ash-nazg-org-select-wrap')).toBeVisible();

    await page.locator('#ash-nazg-org-cancel').click();
    await expect(page.locator('#ash-nazg-org-select-wrap')).toBeHidden();
    await expect(page.locator('#ash-nazg-org-display')).toBeVisible();
  });
});
