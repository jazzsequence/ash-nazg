/**
 * Shared fixtures for Ash-Nazg admin page tests.
 */
const { test: base } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

/**
 * Navigate to an Ash-Nazg plugin page and wait for it to settle.
 */
async function goToPluginPage(page, slug) {
  const url = slug
    ? `/wp-admin/admin.php?page=${slug}`
    : '/wp-admin/admin.php?page=ash-nazg';
  await page.goto(url);
  // 'load' waits for all scripts to execute (unlike domcontentloaded) but
  // doesn't require all network requests to finish (unlike networkidle).
  // networkidle can hang indefinitely on Pantheon when long-polling AJAX runs.
  await page.waitForLoadState('load');
}

/**
 * Run axe-core on the current page and return violations.
 * Excludes known WP core accessibility issues (not our responsibility).
 */
async function runAxe(page) {
  return new AxeBuilder({ page })
    .exclude('#wpadminbar')        // WP admin bar — not our code
    .exclude('#adminmenumain')     // WP sidebar menu — not our code
    .exclude('#wpfooter')          // WP admin footer — not our code
    .exclude('#community-events')           // WP core Events and News widget — not our code
    .exclude('.community-events-footer')    // WP core events widget footer — not our code
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'])
    .analyze();
}

/**
 * Assert no critical or serious axe violations in the plugin content area.
 */
async function assertNoA11yViolations(page, testInfo) {
  const results = await runAxe(page);

  const blocking = results.violations.filter(
    (v) => v.impact === 'critical' || v.impact === 'serious'
  );

  // Attach full axe report to test for review.
  await testInfo.attach('axe-results', {
    body: JSON.stringify(results, null, 2),
    contentType: 'application/json',
  });

  if (blocking.length > 0) {
    const summary = blocking.map((v) =>
      `[${v.impact}] ${v.id}: ${v.description} (${v.nodes.length} node(s))`
    ).join('\n');
    throw new Error(`Axe found ${blocking.length} critical/serious violation(s):\n${summary}`);
  }
}

const test = base.extend({
  goToPluginPage: async ({ page }, use) => {
    await use((slug) => goToPluginPage(page, slug));
  },
  assertNoA11yViolations: async ({ page }, use) => {
    await use((testInfo) => assertNoA11yViolations(page, testInfo));
  },
});

module.exports = { test, runAxe, assertNoA11yViolations, goToPluginPage };
