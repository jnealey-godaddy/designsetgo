/**
 * Disable the "Choose a pattern" start modal before the suite runs.
 *
 * When a new empty page is opened, WordPress pops the starter-pattern modal
 * (`StartPageOptions`) over the editor canvas. In the happy-path sweep this
 * covers the exact moment each block is inserted, so the recorded videos never
 * show the block landing in the editor.
 *
 * The modal is gated by the `core` → `enableChoosePatternModal` user
 * preference (default `true`). We seed it to `false` for the admin user
 * server-side — via WP-CLI through wp-env — so the editor reads it as disabled
 * from the very first render and the modal never opens. Merging (read → modify
 * → write) preserves the user's other persisted preferences.
 *
 * Runs in the `setup` project (matched by *.setup.js), which the browser
 * projects depend on, so it always completes before the actual tests start.
 */

const { test } = require('@playwright/test');
const { cli, shellArg } = require('./helpers/wp-cli');

test('disable choose-pattern start modal', async () => {
	try {
		const raw = cli(
			'wp user meta get admin wp_persisted_preferences --format=json'
		);
		const jsonLine = raw.split('\n').find((l) => l.trim().startsWith('{'));
		const prefs = jsonLine ? JSON.parse(jsonLine) : {};

		prefs.core = prefs.core || {};
		if (prefs.core.enableChoosePatternModal === false) {
			// eslint-disable-next-line no-console
			console.log('[disable-modal] Already disabled — nothing to do');
			return;
		}
		prefs.core.enableChoosePatternModal = false;

		// Shell-quote the JSON arg with shellArg() so a preference value
		// containing a single quote can't break out of the argument.
		const value = JSON.stringify(prefs);
		cli(
			`wp user meta update admin wp_persisted_preferences ${shellArg(
				value
			)} --format=json`
		);
		// eslint-disable-next-line no-console
		console.log('[disable-modal] Choose-pattern start modal disabled');
	} catch (e) {
		// Non-fatal: createNewPost still closes any modal that appears, so a
		// failed seed degrades the videos but won't break the tests. Surface
		// the cause so a misconfigured environment is visible.
		// eslint-disable-next-line no-console
		console.warn(
			'[disable-modal] Could not disable choose-pattern modal:',
			e.message.split('\n')[0]
		);
	}
});
