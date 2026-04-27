// @ts-check
const { defineConfig, devices } = require('@playwright/test');

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:9400';
const BLUEPRINT = process.env.CI
  ? 'tests/e2e/blueprint.generated.json'
  : 'tests/e2e/blueprint.json';

module.exports = defineConfig({
  testDir: './tests/e2e',
  testMatch: '**/*.spec.js',
  fullyParallel: false, // WP state is shared across tests
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1, // single WP Playground instance
  reporter: [
    ['html', { outputFolder: 'tests/e2e/report', open: 'never' }],
    ['junit', { outputFile: 'tests/e2e/results.xml' }],
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

  // Spin up WP Playground before tests; reuse an existing instance locally.
  webServer: {
    command: [
      'npx @wp-playground/cli@latest server',
      '--auto-mount',
      `--blueprint=${BLUEPRINT}`,
      '--port=9400',
      '--blueprint-may-read-adjacent-files',
    ].join(' '),
    url: `${BASE_URL}/wp-login.php`,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
});
