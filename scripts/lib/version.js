/*
 * Pure release-version logic shared by the release CLIs and the deploy
 * workflow's CI guard.
 *
 * Everything here is a pure string/object transform — no `fs`, no `process`,
 * no side effects — so it can be unit-tested with inline fixtures (see
 * tests/unit/release-version.test.js) and reused verbatim by
 * scripts/set-version.js and scripts/release-meta.js.
 *
 * Version grammar (WordPress-core style):
 *   X.Y.Z            → stable release
 *   X.Y.Z-beta<N>    → beta pre-release   (e.g. 2.5.0-beta1)
 *   X.Y.Z-rc<N>      → release candidate  (e.g. 2.5.0-rc1)
 *
 * A pre-release is any version containing a `-` suffix. The single most
 * important rule this module enforces: for a pre-release, the readme.txt
 * "Stable tag" is LEFT UNTOUCHED so WordPress.org keeps serving the last
 * stable version to the auto-update channel.
 */

'use strict';

const VERSION_RE = /^(\d+)\.(\d+)\.(\d+)(?:-(beta|rc)(\d+))?$/;

// Targeted rewrite regexes — each captures (prefix)(value)(suffix?) so we can
// swap only the value and leave surrounding formatting untouched.
const PKG_VERSION_RE = /("version"\s*:\s*")([^"]+)(")/;
const PHP_HEADER_RE = /^(\s*\*\s*Version:\s*)(.+)$/m;
const PHP_DEFINE_RE = /(define\(\s*'DESIGNSETGO_VERSION',\s*')([^']+)('\s*\))/;
const README_STABLE_RE = /^(Stable tag:\s*)(.+?)\s*$/im;

/**
 * Parse and validate a version or git-tag string.
 *
 * @param {string} input Version or tag (an optional leading `v` is stripped).
 * @return {{version: string, isPrerelease: boolean, channel: 'stable'|'beta'|'rc'}} Parsed version metadata.
 * @throws {Error} When the input is not a valid stable/beta/rc version.
 */
function parseVersion(input) {
	if (typeof input !== 'string') {
		throw new Error('Version must be a string.');
	}

	const version = input.trim().replace(/^v/, '');
	const match = VERSION_RE.exec(version);

	if (!match) {
		throw new Error(
			`Invalid version "${input}". Expected X.Y.Z, X.Y.Z-beta<N> or X.Y.Z-rc<N> (e.g. 2.5.0, 2.5.0-beta1, 2.5.0-rc1).`
		);
	}

	const channel = match[4] || 'stable';

	return {
		version,
		isPrerelease: channel !== 'stable',
		channel,
	};
}

/**
 * Rewrite the top-level "version" field in package.json text.
 *
 * @param {string} text    Raw package.json contents.
 * @param {string} version New version.
 * @return {string} Updated contents.
 */
function updatePackageJson(text, version) {
	return text.replace(PKG_VERSION_RE, `$1${version}$3`);
}

/**
 * Rewrite both the plugin-header "Version:" line and the
 * DESIGNSETGO_VERSION define in designsetgo.php text.
 *
 * @param {string} text    Raw designsetgo.php contents.
 * @param {string} version New version.
 * @return {string} Updated contents.
 */
function updatePluginPhp(text, version) {
	return text
		.replace(PHP_HEADER_RE, `$1${version}`)
		.replace(PHP_DEFINE_RE, `$1${version}$3`);
}

/**
 * Rewrite the "Stable tag:" line in readme.txt text.
 *
 * @param {string} text    Raw readme.txt contents.
 * @param {string} version New stable tag value.
 * @return {string} Updated contents.
 */
function updateReadmeStableTag(text, version) {
	return text.replace(README_STABLE_RE, `$1${version}`);
}

/**
 * Read the DESIGNSETGO_VERSION define (the canonical runtime version).
 *
 * @param {string} text Raw designsetgo.php contents.
 * @return {string|null} The version, or null when not found.
 */
function readPluginPhpVersion(text) {
	const match = PHP_DEFINE_RE.exec(text);
	return match ? match[2] : null;
}

/**
 * Read the top-level package.json "version".
 *
 * @param {string} text Raw package.json contents.
 * @return {string|null} The version, or null when not found.
 */
function readPackageVersion(text) {
	const match = PKG_VERSION_RE.exec(text);
	return match ? match[2] : null;
}

/**
 * Read the readme.txt "Stable tag" value.
 *
 * @param {string} text Raw readme.txt contents.
 * @return {string|null} The stable tag, or null when not found.
 */
function readReadmeStableTag(text) {
	const match = README_STABLE_RE.exec(text);
	return match ? match[2].trim() : null;
}

/**
 * Compute the new file contents for a version change.
 *
 * For a STABLE version all three files are updated. For a PRE-RELEASE the
 * readme.txt is deliberately left as-is (Stable tag stays at the last stable
 * release), so the beta/rc never reaches the auto-update channel.
 *
 * @param {{pkg: string, php: string, readme: string}} files   Raw file contents.
 * @param {string}                                     version New version.
 * @return {{pkg: string, php: string, readme: string, changed: string[]}} New file contents and the list of files that changed.
 */
function planFileUpdates(files, version) {
	const { isPrerelease } = parseVersion(version);

	const pkg = updatePackageJson(files.pkg, version);
	const php = updatePluginPhp(files.php, version);
	const readme = isPrerelease
		? files.readme
		: updateReadmeStableTag(files.readme, version);

	const changed = [];
	if (pkg !== files.pkg) {
		changed.push('package.json');
	}
	if (php !== files.php) {
		changed.push('designsetgo.php');
	}
	if (readme !== files.readme) {
		changed.push('readme.txt');
	}

	return { pkg, php, readme, changed };
}

module.exports = {
	VERSION_RE,
	parseVersion,
	updatePackageJson,
	updatePluginPhp,
	updateReadmeStableTag,
	readPluginPhpVersion,
	readPackageVersion,
	readReadmeStableTag,
	planFileUpdates,
};
