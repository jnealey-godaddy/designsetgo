/**
 * The interaction runtime ships as a standalone bundle registered with no
 * WordPress script dependencies. webpack externalises every `@wordpress/*`
 * import to a global (`wp.i18n`, `wp.element`, …), so a single such import
 * anywhere in this module graph throws
 * "Cannot read properties of undefined (reading 'i18n')" on page load and
 * kills every interaction on the site — silently, because nothing in the
 * editor exercises the frontend bundle.
 *
 * This happened once already: actions.js imported HIDDEN_CLASS from
 * constants.js, which imports @wordpress/i18n for its labels.
 */

const fs = require('fs');
const path = require('path');

const EXT_DIR = path.join(__dirname, '../../../src/extensions/interactions');

// Every module reachable from frontend.js.
const FRONTEND_GRAPH = [
	'frontend.js',
	'resolve-target.js',
	'actions.js',
	'hidden-class.js',
];

const read = (file) => fs.readFileSync(path.join(EXT_DIR, file), 'utf8');

const importsIn = (source) =>
	Array.from(source.matchAll(/from\s+['"]([^'"]+)['"]/g)).map((m) => m[1]);

describe('interaction frontend bundle dependencies', () => {
	it.each(FRONTEND_GRAPH)('%s imports no @wordpress package', (file) => {
		const wordpressImports = importsIn(read(file)).filter((spec) =>
			spec.startsWith('@wordpress/')
		);
		expect(wordpressImports).toEqual([]);
	});

	it.each(FRONTEND_GRAPH)(
		'%s does not reach constants.js, which imports i18n',
		(file) => {
			const local = importsIn(read(file)).filter((spec) =>
				spec.startsWith('.')
			);
			expect(local).not.toContain('./constants');
		}
	);

	it('covers the whole graph — every local import is listed here', () => {
		// If a new module is added to the runtime, it must be added to
		// FRONTEND_GRAPH so the checks above apply to it too.
		const reachable = new Set();
		FRONTEND_GRAPH.forEach((file) => {
			importsIn(read(file))
				.filter((spec) => spec.startsWith('.'))
				.forEach((spec) =>
					reachable.add(`${spec.replace(/^\.\//, '')}.js`)
				);
		});

		reachable.forEach((file) => {
			expect(FRONTEND_GRAPH).toContain(file);
		});
	});

	it('keeps constants.js free to use i18n for editor labels', () => {
		// The editor side is unaffected by the constraint above.
		expect(importsIn(read('constants.js'))).toContain('@wordpress/i18n');
	});
});
