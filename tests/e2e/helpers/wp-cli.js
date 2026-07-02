/**
 * WP-CLI helpers for DesignSetGo e2e tests (host side).
 *
 * Thin wrappers around `wp-env run cli` so the setup/teardown projects and the
 * per-test page cleanup all delete content through one code path instead of
 * copy-pasting the same `execSync` incantation. Host-only — these shell out to
 * wp-env and must NOT be imported into browser-context code.
 */

const { execSync } = require('child_process');

/**
 * Distil wp-env's stderr down to the substantive failure text.
 *
 * wp-env wraps the inner command's output with its own decoration: a leading
 * `ℹ Starting '…'` info line and a trailing `✖ Command failed…` line that it
 * also echoes once more un-glyphed. Drop the info/blank lines and de-duplicate
 * so the real WP-CLI error (e.g. `Error: …`) leads the message.
 *
 * @param {string} stderr - Raw captured stderr.
 * @return {string} Meaningful failure lines joined with "; " (may be empty).
 */
function meaningfulStderr(stderr) {
	const lines = stderr
		.split('\n')
		.map((l) => l.trim())
		.filter((l) => l && !l.startsWith('ℹ'));
	return [...new Set(lines.map((l) => l.replace(/^✖\s*/, '')))].join('; ');
}

/**
 * Run a WP-CLI command inside the wp-env `cli` container and return its stdout.
 *
 * @param {string} args - The `wp ...` command (without the leading `wp`).
 * @return {string} Raw stdout.
 */
function cli(args) {
	try {
		return execSync(`npx wp-env run cli -- ${args}`, {
			stdio: ['ignore', 'pipe', 'pipe'],
			cwd: process.cwd(),
		}).toString();
	} catch (e) {
		// execSync's default message is the command invocation, not the WP-CLI
		// failure reason — which lands on the captured stderr. Promote the
		// distilled stderr onto .message so callers logging
		// e.message.split('\n')[0] report the actual cause. The message is
		// newline-free (lines joined with "; "), so that split keeps it intact.
		// The original .stderr / .status are preserved.
		const stderr = meaningfulStderr(e.stderr ? e.stderr.toString() : '');
		if (stderr) {
			e.message = `${stderr} (${args})`;
		}
		throw e;
	}
}

/**
 * Quote an arbitrary string as a single argument for the host shell.
 *
 * Wraps the value in single quotes and rewrites every embedded single quote as
 * the canonical `'\''` sequence, so a value containing quotes (or any other
 * shell metacharacter) survives verbatim. `wp-env run cli -- …` forwards the
 * post-`--` tokens straight to the container as argv (no second shell parse —
 * that's why deleteAllPagesAndPosts needs an explicit `sh -c`), so quoting for
 * the host shell alone is sufficient.
 *
 * @param {string} value - Raw value to pass as one argument.
 * @return {string} Shell-quoted token, including the surrounding quotes.
 */
function shellArg(value) {
	return `'${String(value).replace(/'/g, `'\\''`)}'`;
}

/**
 * Force-delete every page and post (any status) in one CLI round-trip.
 *
 * The inner `sh -c` runs inside the cli container so the $(...) subshell and
 * $ids expansion happen there, not on the host. Used by the content reset
 * (before the suite) and the cleanup teardown (after the suite) so a run both
 * starts and ends from a clean slate.
 *
 * SAFETY GUARD: this wipes ALL content, including a developer's real pages on
 * their local wp-env DB. The pre-commit hook runs the e2e suite locally, so
 * without a guard a routine `git commit` would destroy the working site. The
 * destructive delete therefore only runs against a disposable CI database
 * (GitHub Actions sets `CI=true`) or when a developer explicitly opts in via
 * `DSGO_E2E_RESET_CONTENT=1`. Otherwise it is a no-op, and the suite still
 * works: each test creates and queries its own page by ID, and per-test
 * cleanup removes those pages regardless.
 *
 * @return {boolean} True if content was actually deleted, false if skipped.
 */
function deleteAllPagesAndPosts() {
	const inCI = !!process.env.CI;
	const optedIn = process.env.DSGO_E2E_RESET_CONTENT === '1';
	if (!inCI && !optedIn) {
		return false;
	}
	cli(
		"sh -c 'ids=$(wp post list --post_type=page,post " +
			'--post_status=any --format=ids); ' +
			'if [ -n "$ids" ]; then wp post delete $ids --force; fi\''
	);
	return true;
}

/**
 * Force-delete a specific set of posts by ID. No-op for an empty list.
 *
 * @param {Array<number|string>} ids - Post IDs to delete.
 */
function deletePostIds(ids) {
	const list = (ids || []).filter(Boolean);
	if (!list.length) {
		return;
	}
	cli(`wp post delete ${list.join(' ')} --force`);
}

module.exports = {
	cli,
	shellArg,
	deleteAllPagesAndPosts,
	deletePostIds,
};
