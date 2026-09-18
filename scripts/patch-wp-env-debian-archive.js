#!/usr/bin/env node
/*
 * Patches @wordpress/env's generated Dockerfile so PHP 7.4 and 8.0 images
 * can still install packages now that Debian 11 (bullseye) is archived.
 *
 * Background: the official wordpress:php7.4 and wordpress:php8.0 images are
 * built on bullseye. Its LTS ended on 2026-08-31
 * (https://www.debian.org/News/2026/20260831), and the deb.debian.org
 * security pool now returns 404 for packages the images' indexes still
 * list, so wp-env's `apt-get install $PHPIZE_DEPS` step fails and every
 * PHP 7.4 / 8.0 CI job dies before a test runs.
 *
 * wp-env 11.x fixed this upstream by pointing bullseye at archive.debian.org
 * and dropping its security and updates suites (the security suite is not
 * on archive.debian.org yet). This backports the same three lines to the
 * wp-env 10.x generator this project pins, inserted after its buster block.
 * Remove this script once @wordpress/env is upgraded to 11.x.
 *
 * This script is idempotent — safe to run multiple times. It exits
 * cleanly if @wordpress/env is not installed.
 */

const fs = require('fs');
const path = require('path');

const SENTINEL = 'archive.debian.org/debian bullseye';
const AFTER_NEEDLE = "RUN sed -i '/buster-updates/d' /etc/apt/sources.list\n";
const PATCH_LINES =
	'\n# bullseye (https://www.debian.org/News/2026/20260831)\n' +
	'# The security suite is not on archive.debian.org yet, so it is dropped rather\n' +
	'# than rewritten.\n' +
	"RUN sed -i 's|deb.debian.org/debian bullseye|archive.debian.org/debian bullseye|g' /etc/apt/sources.list\n" +
	"RUN sed -i '/bullseye-security/d' /etc/apt/sources.list\n" +
	"RUN sed -i '/bullseye-updates/d' /etc/apt/sources.list\n";

const file = path.resolve(
	__dirname,
	'..',
	'node_modules',
	'@wordpress',
	'env',
	'lib',
	'init-config.js'
);

if (!fs.existsSync(file)) {
	process.stdout.write(
		'[patch-wp-env] @wordpress/env 10.x generator not found; skipping Debian archive patch.\n'
	);
	process.exit(0);
}

const original = fs.readFileSync(file, 'utf8');

if (original.includes(SENTINEL)) {
	process.exit(0);
}

if (!original.includes(AFTER_NEEDLE)) {
	process.stderr.write(
		`[patch-wp-env] Expected buster apt-sources line not found in ${file}; leaving untouched.\n`
	);
	process.exit(0);
}

fs.writeFileSync(
	file,
	original.replace(AFTER_NEEDLE, AFTER_NEEDLE + PATCH_LINES),
	'utf8'
);
process.stdout.write(
	`[patch-wp-env] Pointed bullseye apt sources at archive.debian.org in ${path.relative(process.cwd(), file)}\n`
);
