# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: a11y.spec.js >> Dashboard page has no critical/serious a11y violations
- Location: tests/e2e/a11y.spec.js:25:3

# Error details

```
Test timeout of 30000ms exceeded.
```

```
TimeoutError: page.goto: Timeout 30000ms exceeded.
Call log:
  - navigating to "https://e2e-3-cxr-ash-nazg.pantheonsite.io/wp-admin/admin.php?page=ash-nazg", waiting until "load"

```

# Test source

```ts
  1  | /**
  2  |  * Shared fixtures for Ash-Nazg admin page tests.
  3  |  */
  4  | const { test: base } = require('@playwright/test');
  5  | const AxeBuilder = require('@axe-core/playwright').default;
  6  | 
  7  | /**
  8  |  * Navigate to an Ash-Nazg plugin page and wait for it to settle.
  9  |  */
  10 | async function goToPluginPage(page, slug) {
  11 |   const url = slug
  12 |     ? `/wp-admin/admin.php?page=${slug}`
  13 |     : '/wp-admin/admin.php?page=ash-nazg';
> 14 |   await page.goto(url);
     |              ^ TimeoutError: page.goto: Timeout 30000ms exceeded.
  15 |   await page.waitForLoadState('networkidle');
  16 | }
  17 | 
  18 | /**
  19 |  * Run axe-core on the current page and return violations.
  20 |  * Excludes known WP core accessibility issues (not our responsibility).
  21 |  */
  22 | async function runAxe(page) {
  23 |   return new AxeBuilder({ page })
  24 |     .exclude('#wpadminbar')       // WP admin bar — not our code
  25 |     .exclude('#adminmenumain')    // WP sidebar menu — not our code
  26 |     .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'])
  27 |     .analyze();
  28 | }
  29 | 
  30 | /**
  31 |  * Assert no critical or serious axe violations in the plugin content area.
  32 |  */
  33 | async function assertNoA11yViolations(page, testInfo) {
  34 |   const results = await runAxe(page);
  35 | 
  36 |   const blocking = results.violations.filter(
  37 |     (v) => v.impact === 'critical' || v.impact === 'serious'
  38 |   );
  39 | 
  40 |   // Attach full axe report to test for review.
  41 |   await testInfo.attach('axe-results', {
  42 |     body: JSON.stringify(results, null, 2),
  43 |     contentType: 'application/json',
  44 |   });
  45 | 
  46 |   if (blocking.length > 0) {
  47 |     const summary = blocking.map((v) =>
  48 |       `[${v.impact}] ${v.id}: ${v.description} (${v.nodes.length} node(s))`
  49 |     ).join('\n');
  50 |     throw new Error(`Axe found ${blocking.length} critical/serious violation(s):\n${summary}`);
  51 |   }
  52 | }
  53 | 
  54 | const test = base.extend({
  55 |   goToPluginPage: async ({ page }, use) => {
  56 |     await use((slug) => goToPluginPage(page, slug));
  57 |   },
  58 |   assertNoA11yViolations: async ({ page }, use) => {
  59 |     await use((testInfo) => assertNoA11yViolations(page, testInfo));
  60 |   },
  61 | });
  62 | 
  63 | module.exports = { test, runAxe, assertNoA11yViolations, goToPluginPage };
  64 | 
```