// @ts-check
const { defineConfig, devices } = require('@playwright/test');

// Tests run against the Pantheon multidev environment directly.
// Required env vars:
//   PLAYWRIGHT_BASE_URL  — e.g. https://e2e-3-cxr-ash-nazg.pantheonsite.io
//   E2E_WP_PASSWORD      — admin password set by CI via Terminus
//
// For local runs: set both vars and point at any Pantheon environment.
const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'https://dev-cxr-ash-nazg.pantheonsite.io';

module.exports = defineConfig({
  testDir: './tests/e2e',
  testMatch: '**/*.spec.js',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 4 : 2,
  reporter: [
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['junit', { outputFile: 'playwright-report/results.xml' }],
    ['list'],
  ],

  use: {
    baseURL: BASE_URL,
    storageState: 'tests/e2e/.auth/admin.json',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },

  projects: [
    // Global auth setup — runs once before all tests
    {
      name: 'setup',
      testMatch: '**/global.setup.js',
      use: { storageState: undefined },
    },

    // Chromium — only browser for CI; add Firefox later if cross-browser gaps found
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      dependencies: ['setup'],
    },
  ],
});
