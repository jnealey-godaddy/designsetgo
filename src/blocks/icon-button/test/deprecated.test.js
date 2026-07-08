/**
 * Icon Button Block - Themeable-Gap Deprecation Migration Tests
 *
 * Verifies:
 *  - the current save() omits the icon↔text gap when there is no icon, marks
 *    icon buttons with `dsgo-icon-button--has-icon`, and writes an inline gap
 *    only for an explicit author override;
 *  - OLD icon buttons (inline `gap:0` / `gap:8px`, no marker class) still parse
 *    cleanly against the current save() + v8 deprecation instead of showing
 *    WordPress's "unexpected or invalid content / Attempt Recovery" warning.
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

// deprecated.js exports newest-first: [v8, v7, ...].
const [v8Deprecation] = deprecated;

describe('icon-button save() - themeable gap', () => {
	test('button with an icon carries --has-icon and no inline gap by default', () => {
		const markup = serialize(
			createBlock(metadata.name, {
				text: 'Go',
				url: '#',
				icon: 'star',
				iconPosition: 'start',
			})
		);
		expect(markup).toContain('dsgo-icon-button--has-icon');
		expect(markup).not.toMatch(/gap:/);
	});

	test('button without an icon omits the gap and the marker class', () => {
		const markup = serialize(
			createBlock(metadata.name, {
				text: 'Go',
				url: '#',
				iconPosition: 'none',
			})
		);
		expect(markup).not.toContain('dsgo-icon-button--has-icon');
		expect(markup).not.toMatch(/gap:/);
	});

	test('an explicit iconGap is written inline (author override wins)', () => {
		const markup = serialize(
			createBlock(metadata.name, {
				text: 'Go',
				url: '#',
				icon: 'star',
				iconPosition: 'start',
				iconGap: '12px',
			})
		);
		expect(markup).toContain('dsgo-icon-button--has-icon');
		expect(markup).toContain('gap:12px');
	});
});

describe('icon-button deprecations - v8 themeable-gap migration', () => {
	// Derive byte-exact OLD markup from the current canonical output: the
	// pre-refactor format dropped the marker class and always baked an inline
	// gap right after justify-content.
	const canonicalIcon = serialize(
		createBlock(metadata.name, {
			text: 'Go',
			url: '#',
			icon: 'star',
			iconPosition: 'start',
		})
	);
	const OLD_ICON_MARKUP = canonicalIcon
		.replace(' dsgo-icon-button--has-icon', '')
		.replace(
			'justify-content:center;width:auto',
			'justify-content:center;gap:8px;width:auto'
		);

	const canonicalNoIcon = serialize(
		createBlock(metadata.name, {
			text: 'Go',
			url: '#',
			iconPosition: 'none',
		})
	);
	const OLD_NO_ICON_MARKUP = canonicalNoIcon.replace(
		'justify-content:center;width:auto',
		'justify-content:center;gap:0;width:auto'
	);

	test('derived old markup differs from canonical as expected', () => {
		expect(OLD_ICON_MARKUP).not.toContain('dsgo-icon-button--has-icon');
		expect(OLD_ICON_MARKUP).toContain('gap:8px');
		expect(OLD_NO_ICON_MARKUP).toContain('gap:0');
	});

	test('old icon button (inline gap, no marker class) migrates silently', () => {
		const [block] = parse(OLD_ICON_MARKUP);

		expect(console).toHaveInformed();
		expect(block.name).toBe('designsetgo/icon-button');
		expect(block.isValid).toBe(true);
		// Re-serialized with the current save(): marker class present, no gap.
		expect(getBlockContent(block)).toContain('dsgo-icon-button--has-icon');
		expect(getBlockContent(block)).not.toMatch(/gap:/);
	});

	test('old icon-less button (gap:0) migrates silently', () => {
		const [block] = parse(OLD_NO_ICON_MARKUP);

		expect(console).toHaveInformed();
		expect(block.name).toBe('designsetgo/icon-button');
		expect(block.isValid).toBe(true);
		expect(getBlockContent(block)).not.toMatch(/gap:/);
	});

	test('old button with an explicit non-default gap keeps it after migration', () => {
		const canonicalExplicit = serialize(
			createBlock(metadata.name, {
				text: 'Go',
				url: '#',
				icon: 'star',
				iconPosition: 'start',
				iconGap: '12px',
			})
		);
		// Old explicit-gap markup: same inline gap:12px, only the marker class
		// is absent (that is the sole difference from the current save).
		const oldExplicit = canonicalExplicit.replace(
			' dsgo-icon-button--has-icon',
			''
		);
		const [block] = parse(oldExplicit);
		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.iconGap).toBe('12px');
		expect(getBlockContent(block)).toContain('gap:12px');
	});

	test('isEligible flags old markup (inline gap, no marker class)', () => {
		expect(
			v8Deprecation.isEligible({}, [], { innerHTML: OLD_ICON_MARKUP })
		).toBe(true);
		expect(
			v8Deprecation.isEligible({}, [], { innerHTML: OLD_NO_ICON_MARKUP })
		).toBe(true);
	});

	test('isEligible ignores current markup', () => {
		expect(
			v8Deprecation.isEligible({}, [], { innerHTML: canonicalIcon })
		).toBe(false);
		expect(
			v8Deprecation.isEligible({}, [], { innerHTML: canonicalNoIcon })
		).toBe(false);
	});

	test('migrate drops a default (8px) gap but preserves an explicit one', () => {
		expect(v8Deprecation.migrate({ iconGap: '8px', text: 'x' })).toEqual({
			text: 'x',
		});
		expect(v8Deprecation.migrate({ iconGap: '12px', text: 'x' })).toEqual({
			iconGap: '12px',
			text: 'x',
		});
	});
});
