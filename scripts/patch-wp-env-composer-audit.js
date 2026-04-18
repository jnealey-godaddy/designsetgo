#!/usr/bin/env node
/*
 * Patches @wordpress/env's generated Dockerfile so the in-container
 * `composer global require phpunit/phpunit` step is not blocked by
 * Composer's `audit.block-insecure` feature.
 *
 * Background: Composer 2.7+ refuses to install packages affected by
 * security advisories during dependency resolution (block-insecure).
 * wp-env's Dockerfile creates a brand-new ~/.composer/composer.json
 * at build time and runs `composer global require phpunit/phpunit`,
 * so the audit.ignore list in our project's composer.json cannot help.
 * Whenever packagist adds a new PHPUnit advisory (e.g.
 * PKSA-z3gr-8qht-p93v published 2026-04), CI and local `wp-env start`
 * fail at image-build time with:
 *   "…found phpunit/phpunit[...] but these were not loaded, because
 *    they are affected by security advisories …"
 *
 * Neither `COMPOSER_NO_AUDIT=1` nor `--no-audit` bypass block-insecure
 * (verified empirically). Setting `audit.block-insecure=false` in the
 * container's global composer config is the only reliable fix and is
 * safe for a dev/test environment — the plugin itself never runs
 * phpunit in production.
 *
 * This script is idempotent — safe to run multiple times. It exits
 * cleanly if @wordpress/env is not installed.
 */

const fs = require('fs');
const path = require('path');

const SENTINEL = 'audit.block-insecure false';
const RUN_NEEDLE = 'RUN composer global require --dev phpunit/phpunit';
const PATCH_LINES =
	'RUN composer global config --no-interaction audit.abandoned ignore\n' +
	'RUN composer global config --no-interaction audit.block-insecure false\n';

const candidates = [
	// wp-env <= 10.x
	path.resolve(
		__dirname,
		'..',
		'node_modules',
		'@wordpress',
		'env',
		'lib',
		'init-config.js'
	),
	// wp-env 11.x
	path.resolve(
		__dirname,
		'..',
		'node_modules',
		'@wordpress',
		'env',
		'lib',
		'runtime',
		'docker',
		'docker-config.js'
	),
];

let patched = 0;

for (const file of candidates) {
	if (!fs.existsSync(file)) {
		continue;
	}

	const original = fs.readFileSync(file, 'utf8');

	if (original.includes(SENTINEL)) {
		patched += 1;
		continue;
	}

	if (!original.includes(RUN_NEEDLE)) {
		process.stderr.write(
			`[patch-wp-env] Expected composer global require line not found in ${file}; leaving untouched.\n`
		);
		continue;
	}

	const updated = original.replace(RUN_NEEDLE, PATCH_LINES + RUN_NEEDLE);
	fs.writeFileSync(file, updated, 'utf8');
	patched += 1;
	process.stdout.write(
		`[patch-wp-env] Disabled audit.block-insecure in ${path.relative(process.cwd(), file)}\n`
	);
}

if (patched === 0) {
	process.stdout.write(
		'[patch-wp-env] @wordpress/env not installed; skipping patch.\n'
	);
}
