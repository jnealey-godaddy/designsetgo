#!/usr/bin/env node
/*
 * @wordpress/env 11.x uses the removed CommonJS default export of simple-git.
 * Keep its Git downloads working with the security-patched simple-git 4.x.
 * Remove this patch when wp-env adopts the named simpleGit export upstream.
 */
const fs = require('fs');
const path = require('path');

const originalImport = "const SimpleGit = require( 'simple-git' );";
const patchedImport =
	"const { simpleGit: SimpleGit } = require( 'simple-git' );";
const files = [
	'lib/download-sources.js',
	'lib/runtime/docker/download-wp-phpunit.js',
];

for (const relativePath of files) {
	const file = path.resolve(
		__dirname,
		'../node_modules/@wordpress/env',
		relativePath
	);
	if (!fs.existsSync(file)) {
		throw new Error(
			`[patch-wp-env] Missing expected module: ${relativePath}`
		);
	}
	const source = fs.readFileSync(file, 'utf8');
	if (source.includes(patchedImport)) {
		continue;
	}
	if (!source.includes(originalImport)) {
		throw new Error(
			`[patch-wp-env] Unexpected Git import: ${relativePath}`
		);
	}
	fs.writeFileSync(file, source.replace(originalImport, patchedImport));
	process.stdout.write(
		`[patch-wp-env] Updated Git import in ${relativePath}\n`
	);
}
