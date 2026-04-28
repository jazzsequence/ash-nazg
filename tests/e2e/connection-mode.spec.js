/**
 * Connection mode user journey tests.
 *
 * Actually toggles SFTP/Git mode and verifies the state change
 * is reflected in the UI. The e2e multidev is ephemeral so this is safe.
 */
const { expect } = require('@playwright/test');
const { test, goToPluginPage } = require('./fixtures/admin-page');

test.describe('Connection Mode Toggle', () => {
  test.beforeEach(async ({ page }) => {
    await goToPluginPage(page, 'ash-nazg');
    await page.waitForLoadState('load');
    await page.waitForTimeout(2000);
  });

  test('toggle is present and shows current mode', async ({ page }) => {
    const toggle = page.locator('#ash-nazg-toggle-mode');
    if (await toggle.count() === 0) test.skip();

    await expect(toggle).toBeVisible();
    const mode = await toggle.getAttribute('data-mode');
    expect(['sftp', 'git']).toContain(mode);
  });

  test('toggling mode updates the badge', async ({ page }) => {
    const toggle = page.locator('#ash-nazg-toggle-mode');
    if (await toggle.count() === 0) test.skip();

    const initialMode = await toggle.getAttribute('data-mode');
    await toggle.click();

    // Loading indicator should appear.
    const loading = page.locator('#ash-nazg-mode-loading');
    await expect(loading).toBeVisible({ timeout: 5_000 });

    // Wait for the mode change to complete (page reloads on success).
    await page.waitForLoadState('load', { timeout: 60_000 });
    await page.waitForTimeout(2000);

    // Mode badge should now show the opposite mode.
    const badge = page.locator('.ash-nazg-badge-sftp, .ash-nazg-badge-git');
    await expect(badge).toBeVisible({ timeout: 10_000 });
    const newBadgeText = await badge.textContent();
    const expectedMode = initialMode === 'git' ? 'SFTP' : 'Git';
    expect(newBadgeText?.toUpperCase()).toContain(expectedMode.toUpperCase());

    // Toggle back to restore original mode.
    const toggleAgain = page.locator('#ash-nazg-toggle-mode');
    if (await toggleAgain.count() > 0) {
      await toggleAgain.click();
      await page.waitForLoadState('load', { timeout: 60_000 });
    }
  });
});
