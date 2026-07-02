/**
 * Content Reset for Playwright Tests
 *
 * In CI (or when DSGO_E2E_RESET_CONTENT=1) this deletes all pages and posts
 * before the suite runs so each run starts from a clean slate — otherwise
 * every run's test pages would accumulate in the database. It is GUARDED to a
 * no-op locally (the pre-commit hook runs this suite) because the delete is
 * unconditional across all post statuses and would otherwise wipe a developer's
 * own content; see deleteAllPagesAndPosts() in helpers/wp-cli.js.
 *
 * Runs in the `setup` project (matched by *.setup.js), which the browser
 * projects depend on — so it always completes before the actual tests start.
 * Resets via WP-CLI through wp-env, matching how the project runs other CLI
 * tasks (see the `plugin-check` script).
 */

const { test } = require('@playwright/test');
const { deleteAllPagesAndPosts } = require('./helpers/wp-cli');

test('reset pages and posts', async () => {
	try {
		const didReset = deleteAllPagesAndPosts();
		// eslint-disable-next-line no-console
		console.log(
			didReset
				? '[reset-content] Pages and posts reset before test run'
				: '[reset-content] Skipped full reset outside CI — local content preserved (set DSGO_E2E_RESET_CONTENT=1 to force)'
		);
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
