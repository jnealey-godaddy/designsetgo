/**
 * Blobs Block - Native max-width control + migration tests
 *
 * Verifies:
 *  - a blob WITHOUT maxWidth serializes exactly as before (no
 *    `dsgo-has-max-width` class, no `--dsgo-blob-max-width` var) and re-parses
 *    valid against the current save() without any migration;
 *  - a blob WITH maxWidth emits the `dsgo-has-max-width` class and the
 *    kit-controllable `--dsgo-blob-max-width` custom property (not a raw inline
 *    max-width);
 *  - OLD content that got its max-width from the generic max-width extension
 *    (a `dsgoMaxWidth` attribute + `dsgo-has-max-width` class + raw inline
 *    `max-width;margin-left:auto;margin-right:auto`) migrates silently to the
 *    native `maxWidth` via the v3 deprecation instead of showing WordPress's
 *    "Attempt Recovery" warning.
 */

import {
	registerBlockType,
	setCategories,
	parse,
	createBlock,
	serialize,
	getBlockContent,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';
import deprecated from '../deprecated';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save, deprecated });

// deprecated.js exports newest-first: [v3, v2, v1].
const [v3Deprecation] = deprecated;

describe('blobs save() - native max-width', () => {
	test('a blob without maxWidth omits the class and the var', () => {
		const markup = serialize(
			createBlock(metadata.name, {
				size: '300px',
			})
		);
		expect(markup).toContain('dsgo-blobs-wrapper');
		expect(markup).not.toContain('dsgo-has-max-width');
		expect(markup).not.toContain('--dsgo-blob-max-width');
	});

	test('a blob with maxWidth adds the class and the kit-controllable var', () => {
		const markup = serialize(
			createBlock(metadata.name, {
				size: '80%',
				maxWidth: '800px',
			})
		);
		expect(markup).toContain('dsgo-has-max-width');
		expect(markup).toContain('--dsgo-blob-max-width:800px');
		// The raw max-width / centering margins live in the stylesheet, so they
		// must NOT be written inline on the wrapper.
		expect(markup).not.toMatch(/(?:^|[^-])max-width:/);
		expect(markup).not.toContain('margin-left:auto');
	});

	test('a maxWidth-unset blob re-parses valid with no migration', () => {
		const markup = serialize(
			createBlock(metadata.name, {
				size: '400px',
				blobShape: 'shape-2',
				blobAnimation: 'morph-1',
			})
		);
		const [block] = parse(markup);
		expect(block.name).toBe('designsetgo/blobs');
		expect(block.isValid).toBe(true);
		expect(block.attributes.maxWidth).toBeUndefined();
	});
});

describe('blobs deprecations - v3 native max-width migration', () => {
	// Derive byte-exact OLD (extension-era) markup from the current canonical
	// output: the extension stored `dsgoMaxWidth` (not `maxWidth`) and wrote a
	// raw inline max-width + centering margins instead of the var.
	const canonical = serialize(
		createBlock(metadata.name, {
			align: 'center',
			size: '80%',
			maxWidth: '800px',
		})
	);
	const OLD_MARKUP = canonical
		.replace('"maxWidth":"800px"', '"dsgoMaxWidth":"800px"')
		.replace(
			'--dsgo-blob-max-width:800px',
			'max-width:800px;margin-left:auto;margin-right:auto'
		);

	test('derived old markup differs from canonical as expected', () => {
		expect(OLD_MARKUP).toContain('dsgo-has-max-width');
		expect(OLD_MARKUP).toContain('"dsgoMaxWidth":"800px"');
		expect(OLD_MARKUP).toMatch(/(?:^|[^-])max-width:800px/);
		expect(OLD_MARKUP).not.toContain('--dsgo-blob-max-width');
	});

	test('old extension blob migrates silently to native maxWidth', () => {
		const [block] = parse(OLD_MARKUP);

		expect(console).toHaveInformed();
		expect(block.name).toBe('designsetgo/blobs');
		expect(block.isValid).toBe(true);
		expect(block.attributes.maxWidth).toBe('800px');
		expect(block.attributes.dsgoMaxWidth).toBeUndefined();
		// Re-serialized with the current save(): class + kit var, no raw inline.
		const content = getBlockContent(block);
		expect(content).toContain('dsgo-has-max-width');
		expect(content).toContain('--dsgo-blob-max-width:800px');
		expect(content).not.toMatch(/(?:^|[^-])max-width:/);
	});

	test('v3 isEligible flags old extension markup', () => {
		expect(
			v3Deprecation.isEligible({}, [], { innerHTML: OLD_MARKUP })
		).toBe(true);
	});

	test('v3 isEligible ignores current native markup', () => {
		expect(v3Deprecation.isEligible({}, [], { innerHTML: canonical })).toBe(
			false
		);
	});

	test('v3 isEligible ignores a plain (no max-width) blob', () => {
		const plain = serialize(createBlock(metadata.name, { size: '300px' }));
		expect(v3Deprecation.isEligible({}, [], { innerHTML: plain })).toBe(
			false
		);
	});

	test('v3 migrate maps dsgoMaxWidth onto maxWidth and drops the legacy key', () => {
		expect(
			v3Deprecation.migrate({ dsgoMaxWidth: '800px', size: '80%' })
		).toEqual({ maxWidth: '800px', size: '80%' });
		expect(
			v3Deprecation.migrate({ dsgoMaxWidth: '', size: '80%' })
		).toEqual({ size: '80%' });
	});
});
