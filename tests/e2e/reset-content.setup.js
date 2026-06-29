/**
 * Content Reset for Playwright Tests
 *
 * Deletes all pages and posts before the suite runs so each run starts from a
 * clean slate. Without this, every run publishes new test pages that accumulate
 * in the database, cluttering the site (and the frontend screenshots' nav/lists).
 *
 * Runs in the `setup` project (matched by *.setup.js), which the browser
 * projects depend on — so it always completes before the actual tests start.
 * Resets via WP-CLI through wp-env, which is thorough (all post statuses) and
 * matches how the project runs other CLI tasks (see the `plugin-check` script).
 */

const { test } = require('@playwright/test');
const { deleteAllPagesAndPosts } = require('./helpers/wp-cli');

test('reset pages and posts', async () => {
	try {
		deleteAllPagesAndPosts();
		// eslint-disable-next-line no-console
		console.log('[reset-content] Pages and posts reset before test run');
	} catch (e) {
		// Non-fatal: each test creates and queries its own page by ID, so a
		// failed reset won't break the tests — but surface the cause so a
		// misconfigured environment is visible rather than silently skipped.
		// eslint-disable-next-line no-console
		console.warn(
			'[reset-content] Could not reset pages/posts:',
			e.message.split('\n')[0]
		);
	}
});
