/**
 * Icon Button - v10 deprecation (inline icon layout → style.scss)
 *
 * The icon <span> used to serialize
 * `display:flex;align-items:center;justify-content:center;flex-shrink:0` into
 * every saved button. None of it varied by attribute, so it moved to
 * `.dsgo-icon-button__icon` in style.scss and only an explicit iconSize is
 * still written inline.
 *
 * Guards:
 *  - stored buttons carrying the old inline layout migrate SILENTLY (no
 *    "Attempt Recovery"), keeping every attribute;
 *  - the re-serialized markup no longer carries the layout declarations;
 *  - an explicit iconSize is still emitted inline;
 *  - an ICON-LESS button's markup is unchanged by this version, so it must stay
 *    valid without being claimed by the deprecation.
 */

import {
	registerBlockType,
	setCategories,
	parse,
	createBlock,
	serialize,
	getSaveContent,
	getBlockContent,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../../../../src/blocks/icon-button/block.json';
import save from '../../../../src/blocks/icon-button/save';
import deprecated, { v10 } from '../../../../src/blocks/icon-button/deprecated';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);
registerBlockType(metadata.name, { ...metadata, save, deprecated });

const LAYOUT = 'display:flex;align-items:center;justify-content:center';

// v10.save() reproduces the pre-change markup byte-for-byte, so it is the
// fixture source — no hand-written HTML to drift out of date.
const v10BlockType = { name: metadata.name, ...v10 };

function withDefaults(blockType, attrs) {
	const defaults = Object.fromEntries(
		Object.entries(blockType.attributes)
			.filter(([, schema]) => 'default' in schema)
			.map(([key, schema]) => [key, schema.default])
	);
	return { ...defaults, ...attrs };
}

const wrapComment = (attrs, html) =>
	`<!-- wp:designsetgo/icon-button ${JSON.stringify(
		attrs
	)} -->${html}<!-- /wp:designsetgo/icon-button -->`;

describe('icon-button v10 - inline icon layout removed from save()', () => {
	test('current save() emits no layout declarations on the icon span', () => {
		const markup = serialize(
			createBlock(metadata.name, {
				text: 'Go',
				icon: 'star',
				iconPosition: 'start',
			})
		);
		expect(markup).toContain('dsgo-icon-button__icon');
		expect(markup).not.toContain(LAYOUT);
		expect(markup).not.toContain('flex-shrink:0');
	});

	test('an explicit iconSize is still written inline', () => {
		const markup = serialize(
			createBlock(metadata.name, {
				text: 'Go',
				icon: 'star',
				iconPosition: 'start',
				iconSize: 32,
			})
		);
		expect(markup).toContain('width:32px;height:32px');
		expect(markup).not.toContain(LAYOUT);
	});

	test('old markup with the inline layout migrates silently and keeps attributes', () => {
		const attrs = {
			text: 'Go',
			url: '#',
			icon: 'star',
			iconPosition: 'start',
			iconGap: '12px',
		};
		const oldHtml = getSaveContent(
			v10BlockType,
			withDefaults(v10BlockType, attrs)
		);
		expect(oldHtml).toContain(LAYOUT); // fixture really is the old shape

		const [block] = parse(wrapComment(attrs, oldHtml));

		expect(console).toHaveInformed(); // "Block successfully updated" — silent migration
		expect(block.name).toBe('designsetgo/icon-button');
		expect(block.isValid).toBe(true);

		// Attributes survive the passthrough migrate.
		expect(block.attributes.text).toBe('Go');
		expect(block.attributes.icon).toBe('star');
		expect(block.attributes.iconGap).toBe('12px');

		// And the re-serialized markup has shed the layout declarations.
		expect(getBlockContent(block)).not.toContain(LAYOUT);
		expect(getBlockContent(block)).not.toContain('flex-shrink:0');
	});

	test('old markup with an explicit iconSize keeps the size after migration', () => {
		const attrs = {
			text: 'Go',
			icon: 'star',
			iconPosition: 'start',
			iconSize: 32,
		};
		const oldHtml = getSaveContent(
			v10BlockType,
			withDefaults(v10BlockType, attrs)
		);
		const [block] = parse(wrapComment(attrs, oldHtml));

		expect(console).toHaveInformed(); // silent migration
		expect(block.isValid).toBe(true);
		expect(block.attributes.iconSize).toBe(32);
		expect(getBlockContent(block)).toContain('width:32px;height:32px');
		expect(getBlockContent(block)).not.toContain(LAYOUT);
	});

	test('an icon-less button is untouched by this version and stays valid', () => {
		// No icon span at all, so this version changed nothing about its markup.
		const canonical = serialize(
			createBlock(metadata.name, {
				text: 'Go',
				url: '#',
				iconPosition: 'none',
			})
		);
		const [block] = parse(canonical);

		expect(block.isValid).toBe(true);
		expect(console).not.toHaveInformed(); // no migration should have run
	});

	test('isEligible only claims markup carrying the icon-span layout', () => {
		const oldHtml = getSaveContent(
			v10BlockType,
			withDefaults(v10BlockType, {
				text: 'Go',
				icon: 'star',
				iconPosition: 'start',
			})
		);
		const currentHtml = serialize(
			createBlock(metadata.name, {
				text: 'Go',
				icon: 'star',
				iconPosition: 'start',
			})
		);

		// WordPress passes { blockNode, block } — NOT { innerHTML }.
		const eligible = (html) =>
			v10.isEligible({}, [], { blockNode: { innerHTML: html } });

		expect(eligible(oldHtml)).toBe(true);
		expect(eligible(currentHtml)).toBe(false);
	});
});
