/**
 * Section Styles — Editor Preview: JS/PHP list parity
 *
 * `TARGET_SUFFIXES` / `SOURCE_BLOCKS` in generate-css.js must stay in sync with
 * `Section_Styles::$container_blocks` / `$source_blocks` in the PHP mirror. This
 * test parses the PHP source and fails if the two drift, so a container block
 * added/removed/renamed on either side surfaces immediately instead of silently
 * breaking the editor preview.
 *
 * @package
 */

import fs from 'fs';
import path from 'path';

import {
	TARGET_SUFFIXES,
	SOURCE_BLOCKS,
} from '../../../../src/extensions/section-styles-editor-preview/generate-css';

const PHP_FILE = path.join(
	__dirname,
	'../../../../includes/features/class-section-styles.php'
);

/**
 * Extract the quoted block names from a `private $name = array( ... );` PHP
 * property. Only matches quoted array entries, so commented-out mentions (e.g.
 * the `image-accordion-item` exclusion note) are ignored.
 *
 * @param {string} php      PHP source.
 * @param {string} property Property name without the `$`.
 * @param {RegExp} pattern  Per-entry matcher with one capture group.
 * @return {string[]} Captured values in source order.
 */
function extractList(php, property, pattern) {
	const start = php.indexOf(`private $${property}`);
	if (start === -1) {
		throw new Error(
			`Could not find $${property} in class-section-styles.php`
		);
	}
	const end = php.indexOf(');', start);
	const block = php.slice(start, end);

	const values = [];
	let match;
	const global = new RegExp(pattern.source, 'g');
	while ((match = global.exec(block)) !== null) {
		values.push(match[1]);
	}
	return values;
}

describe('Section Styles editor preview — JS/PHP list parity', () => {
	const php = fs.readFileSync(PHP_FILE, 'utf8');

	it('TARGET_SUFFIXES matches PHP $container_blocks', () => {
		const phpSuffixes = extractList(
			php,
			'container_blocks',
			/'designsetgo\/([a-z-]+)'/
		);
		expect(phpSuffixes.length).toBeGreaterThan(0);
		expect(TARGET_SUFFIXES).toEqual(phpSuffixes);
	});

	it('SOURCE_BLOCKS matches PHP $source_blocks', () => {
		const phpSources = extractList(
			php,
			'source_blocks',
			/'(core\/[a-z]+)'/
		);
		expect(phpSources.length).toBeGreaterThan(0);
		expect(SOURCE_BLOCKS).toEqual(phpSources);
	});
});
