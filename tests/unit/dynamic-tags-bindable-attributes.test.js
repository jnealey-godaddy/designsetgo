/**
 * The Dynamic Tags picker must offer exactly the DesignSetGo attributes the
 * server opts into Block Bindings. An attribute only in the JS list writes a
 * binding core ignores; one only in PHP is bindable with no way to bind it.
 *
 * @package
 */

const fs = require('fs');
const path = require('path');

import { BINDABLE_ATTRIBUTES } from '../../src/extensions/dynamic-tags/bindable-attributes';

function readPhpMap() {
	const source = fs.readFileSync(
		path.join(
			__dirname,
			'../../includes/bindings/class-block-bindings-support.php'
		),
		'utf8'
	);
	const body = source.match(
		/DEFAULT_SUPPORTED_ATTRIBUTES = array\(([\s\S]*?)\n\t\);/
	);
	expect(body).not.toBeNull();

	const map = {};
	for (const [, block, attrs] of body[1].matchAll(
		/'(designsetgo\/[a-z-]+)'\s*=>\s*array\(([^)]*)\)/g
	)) {
		map[block] = [...attrs.matchAll(/'([A-Za-z]+)'/g)]
			.map((m) => m[1])
			.sort();
	}
	return map;
}

describe('Dynamic Tags bindable attributes', () => {
	test('DesignSetGo entries mirror the server allowlist', () => {
		const php = readPhpMap();
		expect(Object.keys(php).length).toBeGreaterThan(0);

		const js = {};
		Object.entries(BINDABLE_ATTRIBUTES)
			.filter(([block]) => block.startsWith('designsetgo/'))
			.forEach(([block, entries]) => {
				js[block] = entries.map((entry) => entry.attribute).sort();
			});

		expect(js).toEqual(php);
	});
});
