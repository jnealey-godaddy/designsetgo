/**
 * Authentication Setup for Playwright Tests
 *
 * This file handles authentication before running tests.
 * It logs in as an admin user and saves the authentication state.
 */

const { test, expect } = require('@playwright/test');
const path = require('path');
const fs = require('fs');
const http = require('http');

// WordPress admin credentials (from wp-env defaults)
const ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const ADMIN_PASSWORD = process.env.WP_ADMIN_PASSWORD || 'password';

const WP_BASE_URL = process.env.WP_BASE_URL || 'http://localhost:9451';

/**
 * Wait for the WordPress server to be ready by polling the login page.
 * Returns when the server responds with HTTP 200, or throws after max attempts.
 */
async function waitForServer(url, { maxAttempts = 30, intervalMs = 2000 } = {}) {
	const target = new URL('/wp-login.php', url);
	for (let attempt = 1; attempt <= maxAttempts; attempt++) {
		try {
			await new Promise((resolve, reject) => {
				const req = http.get(target.href, (res) => {
					// Accept any HTTP response — the server is up
					res.resume();
					resolve(res.statusCode);
				});
				req.on('error', reject);
				req.setTimeout(5000, () => {
					req.destroy();
					reject(new Error('timeout'));
				});
			});
			console.log(`Server ready at ${target.href} (attempt ${attempt})`);
			return;
		} catch {
			console.log(
				`Waiting for server (attempt ${attempt}/${maxAttempts})...`
			);
			if (attempt < maxAttempts) {
				await new Promise((r) => setTimeout(r, intervalMs));
			}
		}
	}
	throw new Error(
		`Server at ${url} not ready after ${maxAttempts} attempts`
	);
}

test('authenticate as admin', async ({ page, context }) => {
	// Wait for WordPress to be fully ready before attempting login
	await waitForServer(WP_BASE_URL);

	// Navigate to WordPress login page
	await page.goto('/wp-login.php');

	// Fill in login form
	await page.fill('input[name="log"]', ADMIN_USER);
	await page.fill('input[name="pwd"]', ADMIN_PASSWORD);

	// Click login button
	await page.click('input[name="wp-submit"]');

	// Wait for navigation to complete
	await page.waitForURL('/wp-admin/');

	// Verify we're logged in by checking for admin bar
	await expect(page.locator('#wpadminbar')).toBeVisible();

	// Save authentication state
	const storageStatePath =
		process.env.STORAGE_STATE_PATH ||
		path.join(process.cwd(), 'artifacts/storage-states/admin.json');

	// Ensure directory exists
	const dir = path.dirname(storageStatePath);
	if (!fs.existsSync(dir)) {
		fs.mkdirSync(dir, { recursive: true });
	}

	// Save storage state
	await context.storageState({ path: storageStatePath });

	console.log('Authentication successful - state saved');
});
