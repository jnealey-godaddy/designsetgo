#!/usr/bin/env node
/*
 * Release metadata + CI guard for the deploy workflow.
 *
 * Two modes, both driven by scripts/lib/version.js so CI trusts exactly the
 * same logic the unit tests cover:
 *
 *   node scripts/release-meta.js <tag>
 *     Prints GitHub-Actions outputs for a pushed tag:
 *       version=2.5.0-beta1
 *       prerelease=true
 *       channel=beta
 *     The workflow appends these to $GITHUB_OUTPUT.
 *
 *   node scripts/release-meta.js --validate <tag>
 *     Fails (non-zero + ::error::) unless the working tree matches the tag:
 *       - always: DESIGNSETGO_VERSION and package.json "version" == tag version
 *       - stable tag:      readme.txt "Stable tag" == tag version
 *       - pre-release tag: readme.txt "Stable tag" != tag version
 *                          (must still point at the last stable release)
 */

'use strict';

const fs = require('fs');
const path = require('path');
const {
	parseVersion,
	readPluginPhpVersion,
	readPackageVersion,
	readReadmeStableTag,
} = require('./lib/version');

const ROOT = path.resolve(__dirname, '..');

function ghError(message) {
	// Emits a GitHub Actions error annotation; harmless as plain text locally.
	process.stdout.write(`::error::${message}\n`);
}

function readFile(relative) {
	return fs.readFileSync(path.join(ROOT, relative), 'utf8');
}

function printOutputs(meta) {
	process.stdout.write(`version=${meta.version}\n`);
	process.stdout.write(
		`prerelease=${meta.isPrerelease ? 'true' : 'false'}\n`
	);
	process.stdout.write(`channel=${meta.channel}\n`);
}

function validate(meta) {
	const { version, isPrerelease } = meta;
	const errors = [];

	const phpVersion = readPluginPhpVersion(readFile('designsetgo.php'));
	const pkgVersion = readPackageVersion(readFile('package.json'));
	const stableTag = readReadmeStableTag(readFile('readme.txt'));

	if (phpVersion !== version) {
		errors.push(
			`designsetgo.php DESIGNSETGO_VERSION is "${phpVersion}" but the tag is "${version}". Run: npm run version:set -- ${version}`
		);
	}

	if (pkgVersion !== version) {
		errors.push(
			`package.json version is "${pkgVersion}" but the tag is "${version}". Run: npm run version:set -- ${version}`
		);
	}

	if (isPrerelease) {
		if (stableTag === version) {
			errors.push(
				`readme.txt "Stable tag" is "${stableTag}", which equals this pre-release. It must stay at the last STABLE version so pre-releases never reach the auto-update channel.`
			);
		}
	} else if (stableTag !== version) {
		errors.push(
			`readme.txt "Stable tag" is "${stableTag}" but this stable release is "${version}". Run: npm run version:set -- ${version}`
		);
	}

	if (errors.length) {
		errors.forEach(ghError);
		process.exit(1);
	}

	process.stdout.write(
		`✔ Version metadata is consistent for ${version} (${meta.channel}).\n`
	);
}

function main() {
	const args = process.argv.slice(2);
	const isValidate = args[0] === '--validate';
	const tag = isValidate ? args[1] : args[0];

	if (!tag) {
		ghError(
			'Missing tag argument. Usage: release-meta.js [--validate] <tag>'
		);
		process.exit(1);
	}

	let meta;
	try {
		meta = parseVersion(tag);
	} catch (error) {
		ghError(error.message);
		process.exit(1);
	}

	if (isValidate) {
		validate(meta);
	} else {
		printOutputs(meta);
	}
}

main();
