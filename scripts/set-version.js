#!/usr/bin/env node
/*
 * Sync the plugin version across every file that declares it, in one shot.
 *
 * Usage:
 *   node scripts/set-version.js <version>
 *   npm run version:set -- <version>
 *
 * Examples:
 *   node scripts/set-version.js 2.5.0          # stable release
 *   node scripts/set-version.js 2.5.0-beta1    # beta pre-release
 *   node scripts/set-version.js 2.5.0-rc1      # release candidate
 *
 * Files touched:
 *   - package.json              "version"
 *   - designsetgo.php           header "Version:" + DESIGNSETGO_VERSION define
 *   - readme.txt                "Stable tag:"  (STABLE releases only)
 *
 * For a pre-release the readme.txt "Stable tag" is intentionally left at the
 * last stable version so WordPress.org keeps serving stable to auto-update
 * users. See scripts/lib/version.js for the shared logic.
 */

'use strict';

const fs = require('fs');
const path = require('path');
const {
	parseVersion,
	planFileUpdates,
	readReadmeStableTag,
} = require('./lib/version');

const ROOT = path.resolve(__dirname, '..');
const FILES = {
	pkg: path.join(ROOT, 'package.json'),
	php: path.join(ROOT, 'designsetgo.php'),
	readme: path.join(ROOT, 'readme.txt'),
};

function fail(message) {
	process.stderr.write(`✖ ${message}\n`);
	process.exit(1);
}

function main() {
	const input = process.argv[2];

	if (!input || input === '--help' || input === '-h') {
		process.stdout.write(
			'Usage: node scripts/set-version.js <version>\n' +
				'  e.g. 2.5.0 | 2.5.0-beta1 | 2.5.0-rc1\n'
		);
		process.exit(input ? 0 : 1);
	}

	let meta;
	try {
		meta = parseVersion(input);
	} catch (error) {
		fail(error.message);
	}

	const raw = {
		pkg: fs.readFileSync(FILES.pkg, 'utf8'),
		php: fs.readFileSync(FILES.php, 'utf8'),
		readme: fs.readFileSync(FILES.readme, 'utf8'),
	};

	const result = planFileUpdates(raw, meta.version);

	fs.writeFileSync(FILES.pkg, result.pkg);
	fs.writeFileSync(FILES.php, result.php);
	fs.writeFileSync(FILES.readme, result.readme);

	process.stdout.write(
		`\n✔ Set version to ${meta.version} (${meta.channel})\n`
	);
	result.changed.forEach((file) => {
		process.stdout.write(`  • updated ${file}\n`);
	});

	if (meta.isPrerelease) {
		const stableTag = readReadmeStableTag(result.readme);
		process.stdout.write(
			`  • readme.txt "Stable tag" left at ${stableTag} — stable channel unaffected\n`
		);
	}

	process.stdout.write(
		`\nNext:\n` +
			`  git commit -am "chore: bump version to ${meta.version}"\n` +
			`  git tag ${meta.version} && git push origin ${meta.version}\n\n`
	);
}

main();
