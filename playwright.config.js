// @ts-check
require('dotenv').config();
const { defineConfig, devices } = require('@playwright/test');

// Tests run against the Pantheon multidev environment directly.
// Required env vars (set in .env for local runs, or by CI via Terminus):
//   PLAYWRIGHT_BASE_URL  — e.g. https://e2e-3-cxr-ash-nazg.pantheonsite.io
//   E2E_WP_USERNAME      — WP admin username
//   E2E_WP_PASSWORD      — WP admin password
const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'https://dev-cxr-ash-nazg.pantheonsite.io';

module.exports = defineConfig({
  testDir: './tests/e2e',
  testMatch: '**/*.spec.js',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 4 : 1,
  reporter: [
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['junit', { outputFile: 'playwright-report/results.xml' }],
    ['list'],
  ],

  use: {
    baseURL: BASE_URL,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
    // Raise on a slow connection: E2E_ACTION_TIMEOUT=120000 npx playwright test ...
    actionTimeout: Number( process.env.E2E_ACTION_TIMEOUT ) || 30_000,
    navigationTimeout: Number( process.env.E2E_ACTION_TIMEOUT ) || 30_000,
  },

  projects: [
    // Global auth setup — runs once before all tests. No storageState here -
    // setting it to `undefined` to override the parent is unreliable (it can
    // be dropped across the config/worker process boundary), so storageState
    // is only ever set on the project that actually needs it (chromium).
    {
      name: 'setup',
      testMatch: '**/global.setup.js',
    },

    // Chromium — only browser for CI; add Firefox later if cross-browser gaps found
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], storageState: 'tests/e2e/.auth/admin.json' },
      dependencies: ['setup'],
    },
  ],
});
