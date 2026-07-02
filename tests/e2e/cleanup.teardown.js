/**
 * Cleanup Teardown for Playwright Tests
 *
 * Runs after all tests complete. The full page/post wipe only happens in CI
 * (or when DSGO_E2E_RESET_CONTENT=1) — see deleteAllPagesAndPosts() in
 * helpers/wp-cli.js. Locally it is a no-op, so a pre-commit run never deletes a
 * developer's content; per-test cleanup still removes each test's own pages.
 */

const { test } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { deleteAllPagesAndPosts } = require('./helpers/wp-cli');

test('cleanup test data', async ({}) => {
	// Delete every page/post the suite published. Tests also clean up their own
	// pages per-test (see installPublishedPageCleanup), but this is the safety
	// net: if a test crashed before its afterEach ran, its page is removed here
	// so a run never leaves test pages cluttering the site (and the nav header).
	try {
		const didDelete = deleteAllPagesAndPosts();
		// eslint-disable-next-line no-console
		console.log(
			didDelete
				? '✓ Cleaned up test pages and posts'
				: '↷ Skipped full page/post cleanup outside CI — local content preserved'
		);
	} catch (e) {
		// eslint-disable-next-line no-console
		console.warn(
			'[cleanup] Could not delete test pages/posts:',
			e.message.split('\n')[0]
		);
	}

	// Clean up storage state
	const storageStatePath =
		process.env.STORAGE_STATE_PATH ||
		path.join(process.cwd(), 'artifacts/storage-states/admin.json');

	if (fs.existsSync(storageStatePath)) {
		fs.unlinkSync(storageStatePath);
		// eslint-disable-next-line no-console
		console.log('✓ Cleaned up authentication state');
	}
});
