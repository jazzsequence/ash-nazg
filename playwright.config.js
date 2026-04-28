// @ts-check
const { defineConfig, devices } = require('@playwright/test');

// WP Playground must be running before tests execute.
// Locally:  npm run e2e:server   (starts WP Playground on port 9400)
//           npm run test:e2e     (in a second terminal)
// CI: the e2e.yml workflow starts WP Playground as a dedicated step
//     and passes PLAYWRIGHT_BASE_URL.
const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:9400';

module.exports = defineConfig({
  testDir: './tests/e2e',
  testMatch: '**/*.spec.js',
  fullyParallel: false, // WP state is shared across tests
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1, // single WP Playground instance
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

    // Chromium (primary)
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      dependencies: ['setup'],
    },

    // Firefox (cross-browser)
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
      dependencies: ['setup'],
    },
  ],
});
