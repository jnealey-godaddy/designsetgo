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
 * Run a WP-CLI command inside the wp-env `cli` container and return its stdout.
 *
 * @param {string} args - The `wp ...` command (without the leading `wp`).
 * @return {string} Raw stdout.
 */
function cli(args) {
	return execSync(`npx wp-env run cli -- ${args}`, {
		stdio: ['ignore', 'pipe', 'pipe'],
		cwd: process.cwd(),
	}).toString();
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
 */
function deleteAllPagesAndPosts() {
	cli(
		"sh -c 'ids=$(wp post list --post_type=page,post " +
			'--post_status=any --format=ids); ' +
			'if [ -n "$ids" ]; then wp post delete $ids --force; fi\''
	);
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
