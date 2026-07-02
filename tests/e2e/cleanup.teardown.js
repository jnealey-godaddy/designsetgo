/**
 * Cleanup Teardown for Playwright Tests
 *
 * This file handles cleanup after all tests complete.
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
