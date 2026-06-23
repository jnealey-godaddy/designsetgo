/**
 * Playwright Configuration for DesignSetGo WordPress Plugin
 *
 * This configuration is set up for end-to-end testing of WordPress Gutenberg blocks.
 * It integrates with wp-env for local WordPress testing.
 */

const { defineConfig, devices } = require('@playwright/test');
const path = require('path');

// Set up artifacts path
process.env.WP_ARTIFACTS_PATH =
	process.env.WP_ARTIFACTS_PATH || path.join(process.cwd(), 'artifacts');
process.env.STORAGE_STATE_PATH =
	process.env.STORAGE_STATE_PATH ||
	path.join(process.env.WP_ARTIFACTS_PATH, 'storage-states/admin.json');

// WordPress environment URL (matches port in .wp-env.json)
const WP_BASE_URL = process.env.WP_BASE_URL || 'http://localhost:9451';
const baseUrl = new URL(WP_BASE_URL);

module.exports = defineConfig({
	// Test directory
	testDir: './tests/e2e',

	// Artifacts directory
	outputDir: path.join(process.env.WP_ARTIFACTS_PATH, 'test-results'),

	// Timeout for each test
	timeout: 60000, // 60 seconds

	// Global timeout. The full chromium suite is ~60 tests (the per-block
	// happy-path sweep alone is ~34), run serially on 1 worker in CI with
	// retries, so the whole run needs comfortably more than 10 minutes.
	globalTimeout: 1800000, // 30 minutes

	// Expect timeout
	expect: {
		timeout: 10000, // 10 seconds
	},

	// Test execution settings
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: process.env.CI ? 1 : 2,

	// Reporter configuration
	reporter: process.env.CI ? [['github'], ['html']] : [['html'], ['list']],

	// Shared settings for all projects
	use: {
		// Base URL for navigation
		baseURL: baseUrl.href,

		// Browser context options
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',

		// Viewport size
		viewport: { width: 1280, height: 720 },

		// Ignore HTTPS errors (for local development)
		ignoreHTTPSErrors: true,

		// Locale
		locale: 'en-US',

		// Timezone
		timezoneId: 'America/New_York',

		// Action timeout
		actionTimeout: 10000, // 10 seconds

		// Context options
		contextOptions: {
			reducedMotion: 'reduce',
			strictSelectors: true,
		},
	},

	// Configure projects for different browsers
	projects: [
		// Setup project - runs first to create authentication state
		{
			name: 'setup',
			testMatch: /.*\.setup\.js/,
			teardown: 'cleanup',
		},

		// Cleanup project
		{
			name: 'cleanup',
			testMatch: /.*\.teardown\.js/,
		},

		// Desktop Chrome
		{
			name: 'chromium',
			use: {
				...devices['Desktop Chrome'],
				storageState: process.env.STORAGE_STATE_PATH,
			},
			dependencies: ['setup'],
		},

		// Desktop Firefox
		{
			name: 'firefox',
			use: {
				...devices['Desktop Firefox'],
				storageState: process.env.STORAGE_STATE_PATH,
			},
			dependencies: ['setup'],
		},

		// Desktop Safari
		{
			name: 'webkit',
			use: {
				...devices['Desktop Safari'],
				storageState: process.env.STORAGE_STATE_PATH,
			},
			dependencies: ['setup'],
		},

		// Mobile Chrome
		{
			name: 'mobile-chrome',
			use: {
				...devices['Pixel 5'],
				storageState: process.env.STORAGE_STATE_PATH,
			},
			dependencies: ['setup'],
		},

		// Mobile Safari
		{
			name: 'mobile-safari',
			use: {
				...devices['iPhone 12'],
				storageState: process.env.STORAGE_STATE_PATH,
			},
			dependencies: ['setup'],
		},

		// Tablet
		{
			name: 'tablet',
			use: {
				...devices['iPad Pro'],
				storageState: process.env.STORAGE_STATE_PATH,
			},
			dependencies: ['setup'],
		},
	],

	// Web server configuration (starts wp-env)
	// In CI, wp-env is started explicitly in the workflow before running tests.
	// reuseExistingServer must be true so Playwright skips starting wp-env when
	// it is already running. Locally this also allows reusing a manually started
	// environment; if nothing is running Playwright will start it automatically.
	webServer: {
		command: 'npm run wp-env start',
		url: baseUrl.href,
		timeout: 120000, // 2 minutes
		reuseExistingServer: true,
		stdout: 'ignore',
		stderr: 'pipe',
	},
});
