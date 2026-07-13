/**
 * Icon List Item Block - Kit-Controllable Gaps/Size Deprecation Tests
 *
 * Verifies:
 *  - the current save() no longer bakes the icon↔content gap on the item root,
 *    omits the content gap unless the author sets an explicit value, and does
 *    not set --dsgo-icon-list-size inline in the inherited-size case;
 *  - OLD icon-list-items (inline item gap `gap:16px`/`gap:12px`, inline content
 *    gap, inline --dsgo-icon-list-size) still parse cleanly against the current
 *    save() + v3 deprecation instead of showing WordPress's "unexpected or
 *    invalid content / Attempt Recovery" warning.
 *
 * The block reads its size/style/position from parent CONTEXT. In these tests
 * no parent is present, so context is empty and both the current save() and the
 * deprecated save() resolve to their inherited defaults (icon-left, size
 * inherited). We derive byte-exact OLD markup from the current canonical output
 * via string-replace, mirroring the icon-button deprecation tests.
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
import metadata from '../block.json';
import save from '../save';
import deprecated, { v3, v4 } from '../deprecated';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save, deprecated });

// Address versions by NAME, not position — deprecated.js exports newest-first,
// so positional destructuring silently re-points at the wrong entry as soon as
// a newer deprecation (v4) is prepended.
const v3Deprecation = v3;

// The v3 fixtures below are derived by string-patching the markup as it existed
// BEFORE the v4 (inline-icon-layout) change — i.e. v4.save(), not the current
// save(). Patching the current output would silently stop matching the moment
// save() drops one of the declarations being patched, which is exactly what
// happened when v4 removed `justify-content:center` from the icon box.
const v4BlockType = { name: metadata.name, ...v4 };
const v4Defaults = Object.fromEntries(
	Object.entries(v4.attributes)
		.filter(([, schema]) => 'default' in schema)
		.map(([key, schema]) => [key, schema.default])
);
const preV4Canonical = `<!-- wp:designsetgo/icon-list-item -->${getSaveContent(
	v4BlockType,
	v4Defaults
)}<!-- /wp:designsetgo/icon-list-item -->`;

const INHERITED_SIZE_VAR =
	'--dsgo-icon-list-size:calc(var(--wp--custom--designsetgo--icon-list--default-size, 32) * 1px)';

describe('icon-list-item save() - kit-controllable gaps/size', () => {
	test('default item omits the item gap and the inline inherited-size var', () => {
		const markup = serialize(createBlock(metadata.name, {}));
		// Item root no longer carries an inline gap.
		expect(markup).not.toMatch(/align-items:[^;"]+;gap:/);
		// Inherited size is owned by CSS, not baked inline.
		expect(markup).not.toContain('--dsgo-icon-list-size');
		// The inherit-size marker class is still emitted for CSS to key on.
		expect(markup).toContain('dsgo-icon-list-item__icon--inherit-size');
	});

	test('unset contentGap omits the inline content gap', () => {
		const markup = serialize(createBlock(metadata.name, {}));
		expect(markup).not.toMatch(/gap:/);
	});

	test('explicit contentGap is written inline (author override wins)', () => {
		const markup = serialize(
			createBlock(metadata.name, { contentGap: 12 })
		);
		expect(markup).toContain('gap:12px');
	});
});

describe('icon-list-item deprecations - v3 kit-controllable gaps/size', () => {
	// Canonical (current) output for a default block, empty context — used below
	// to confirm v3's isEligible() correctly ignores genuinely current markup.
	const canonical = serialize(createBlock(metadata.name, {}));

	// Derive byte-exact OLD markup from the PRE-v4 output (which still carried
	// the icon box's inline layout, including the `justify-content:center` this
	// patch anchors on). The v3-era format additionally baked the item gap right
	// after align-items, set --dsgo-icon-list-size inline on the icon box
	// (inherited-size case), and always wrote the content gap (default 8px).
	const OLD_MARKUP = preV4Canonical
		.replace('align-items:flex-start', 'align-items:flex-start;gap:16px')
		.replace(
			'justify-content:center',
			`justify-content:center;${INHERITED_SIZE_VAR}`
		)
		.replace('flex-direction:column', 'flex-direction:column;gap:8px');

	test('derived old markup differs from canonical as expected', () => {
		expect(OLD_MARKUP).toMatch(/align-items:flex-start;gap:16px/);
		expect(OLD_MARKUP).toContain(INHERITED_SIZE_VAR);
		expect(OLD_MARKUP).toContain('flex-direction:column;gap:8px');
	});

	test('old item (inline gaps + inline size var) migrates silently and pins the content gap', () => {
		const [block] = parse(OLD_MARKUP);

		expect(console).toHaveInformed();
		expect(block.name).toBe('designsetgo/icon-list-item');
		expect(block.isValid).toBe(true);
		// Passthrough migrate pins the content gap (8) the old markup carried.
		expect(block.attributes.contentGap).toBe(8);
		const content = getBlockContent(block);
		// The item gap and inherited size var move to CSS (no longer inline)...
		expect(content).not.toMatch(/align-items:[^;"]+;gap:/);
		expect(content).not.toContain('--dsgo-icon-list-size');
		// ...but the pinned content gap is re-emitted inline.
		expect(content).toContain('gap:8px');
	});

	test('old item with an explicit content gap keeps it after migration', () => {
		// Same derivation rule: patch the PRE-v4 output, not the current one.
		const preV4Explicit = `<!-- wp:designsetgo/icon-list-item {"contentGap":20} -->${getSaveContent(
			v4BlockType,
			{ ...v4Defaults, contentGap: 20 }
		)}<!-- /wp:designsetgo/icon-list-item -->`;
		// Old explicit-gap markup: same inline content gap:20px, plus the item
		// gap + inline size var that the current save() no longer emits.
		const oldExplicit = preV4Explicit
			.replace(
				'align-items:flex-start',
				'align-items:flex-start;gap:16px'
			)
			.replace(
				'justify-content:center',
				`justify-content:center;${INHERITED_SIZE_VAR}`
			);

		const [block] = parse(oldExplicit);
		expect(console).toHaveInformed();
		expect(block.isValid).toBe(true);
		expect(block.attributes.contentGap).toBe(20);
		expect(getBlockContent(block)).toContain('gap:20px');
	});

	test('isEligible flags old markup (inline item gap after align-items)', () => {
		expect(
			v3Deprecation.isEligible({}, [], {
				blockNode: { innerHTML: OLD_MARKUP },
			})
		).toBe(true);
	});

	test('isEligible ignores current markup', () => {
		expect(
			v3Deprecation.isEligible({}, [], {
				blockNode: { innerHTML: canonical },
			})
		).toBe(false);
	});

	test('isEligible ignores an item whose NESTED content emits align-items;gap', () => {
		// Regression: the item root opening tag has align-items but no adjacent
		// inline gap (current save()); a nested block inside the content area
		// happens to inline `align-items:center;gap:8px`. Scoping the check to
		// the item's own opening tag must keep this valid item from being
		// false-migrated.
		const nestedHTML =
			'<div class="dsgo-icon-list-item dsgo-icon-list-item--icon-left" style="display:flex;flex-direction:row;align-items:flex-start">' +
			'<span class="dsgo-icon-list-item__icon"></span>' +
			'<div class="dsgo-icon-list-item__content">' +
			'<div class="wp-block-columns" style="align-items:center;gap:8px">Nested</div>' +
			'</div></div>';
		expect(
			v3Deprecation.isEligible({}, [], {
				blockNode: { innerHTML: nestedHTML },
			})
		).toBe(false);
	});

	test('migrate is a passthrough that pins values (never strips defaults)', () => {
		expect(v3Deprecation.migrate({ contentGap: 8, icon: 'star' })).toEqual({
			contentGap: 8,
			icon: 'star',
		});
		expect(v3Deprecation.migrate({ contentGap: 20, icon: 'star' })).toEqual(
			{ contentGap: 20, icon: 'star' }
		);
		// An already-inherited (undefined) contentGap stays inherited.
		expect(v3Deprecation.migrate({ icon: 'star' })).toEqual({
			icon: 'star',
		});
	});
});
