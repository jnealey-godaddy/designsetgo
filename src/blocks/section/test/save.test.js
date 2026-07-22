/**
 * Section Block - save.js Tests
 *
 * Verifies shape dividers render as class-based markup (CSS mask-image
 * contract) with no inline SVG, that the theme-inherit option works, that the
 * shape region carries no fill (it is transparent / see-through to the
 * section background), and that the position-aware vertical-flip default is
 * emitted correctly.
 *
 * @since 2.6.0
 */

// `save.js` imports `@wordpress/block-editor`, which (in this repo's current
// dependency tree) bundles its OWN nested copy of `@wordpress/blocks`
// (block-editor requires ^14.15.0; the top-level package resolves to
// 13.10.0). `useBlockProps.save()` / `useInnerBlocksProps.save()` read block
// support metadata via `getBlockType()` from THAT nested registry, so the
// block must be registered on the same module instance block-editor uses —
// registering via the top-level `@wordpress/blocks` import leaves
// block-editor's internal registry empty, which throws when save() runs
// (`useBlockProps.save` needs `blockType.attributes.align`, etc.) and causes
// `serialize()` to silently no-op to the collapsed comment form. Importing
// from the nested path keeps the block-type registry and the save() call in
// sync so this test exercises the real save() output.
import {
	createBlock,
	serialize,
	registerBlockType,
	setCategories,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';

// The block's category ("designsetgo") isn't registered in the Jest
// environment (that happens in PHP via block-categories filters), which
// otherwise makes registerBlockType() reject the block and causes
// createBlock()/serialize() to silently no-op (self-closing comment, save()
// never called). Register it so save() actually runs.
setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save });

describe('section save - shape dividers', () => {
	test('save emits class-based divider, no inline SVG', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopHeight: 80,
			})
		);
		expect(html).toContain('dsgo-shape-divider--top');
		expect(html).toContain('is-shape-wave');
		expect(html).not.toContain('<svg');
	});

	test('save emits is-shape-inherit for inherit value', () => {
		expect(
			serialize(
				createBlock(metadata.name, { shapeDividerBottom: 'inherit' })
			)
		).toContain('is-shape-inherit');
	});

	test('default divider omits height/width custom props (CSS defaults apply)', () => {
		const html = serialize(
			createBlock(metadata.name, { shapeDividerTop: 'wave' })
		);
		expect(html).toContain('is-shape-wave');
		expect(html).not.toContain('--dsgo-shape-height');
		expect(html).not.toContain('--dsgo-shape-width');
	});

	test('non-default height is emitted', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopHeight: 80,
			})
		);
		expect(html).toContain('--dsgo-shape-height:80px');
	});

	test('shape region carries no fill var (transparent / see-through)', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				backgroundColor: 'contrast',
			})
		);
		expect(html).not.toContain('--dsgo-shape-fill');
	});

	test('bottom dividers flip vertically by default; top dividers do not', () => {
		const bottom = serialize(
			createBlock(metadata.name, { shapeDividerBottom: 'wave' })
		);
		expect(bottom).toContain('dsgo-shape-divider--bottom');
		expect(bottom).toContain('is-flip-y');

		const top = serialize(
			createBlock(metadata.name, { shapeDividerTop: 'wave' })
		);
		expect(top).toContain('dsgo-shape-divider--top');
		expect(top).not.toContain('is-flip-y');
	});

	test('flipY inverts the per-position default (bottom + flipY = not flipped)', () => {
		const bottomFlipped = serialize(
			createBlock(metadata.name, {
				shapeDividerBottom: 'wave',
				shapeDividerBottomFlipY: true,
			})
		);
		expect(bottomFlipped).not.toContain('is-flip-y');
	});
});

describe('section save - shape divider content clearance', () => {
	// The clearance is inner padding on `.dsgo-stack__inner`. The section's OWN
	// block padding lives on the OUTER wrapper (`.dsgo-stack`), so assertions
	// must be scoped to the inner element's style — a whole-HTML substring match
	// would collide with the wrapper's default `spacing|50`/`30` padding. The
	// clearance tests also use `spacing|70` (not the default `50`) so a match
	// can only come from the clearance, never the wrapper default.
	const innerStyle = (html) => {
		const match = html.match(
			/class="dsgo-stack__inner"[^>]*style="([^"]*)"/
		);
		return match ? match[1] : '';
	};

	test('a spacing preset token serializes to inner padding CSS var (top)', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopSpacing: 'var:preset|spacing|70',
			})
		);
		expect(innerStyle(html)).toContain(
			'padding-top:var(--wp--preset--spacing--70)'
		);
	});

	test('a spacing preset token serializes to inner padding CSS var (bottom)', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerBottom: 'wave',
				shapeDividerBottomSpacing: 'var:preset|spacing|70',
			})
		);
		expect(innerStyle(html)).toContain(
			'padding-bottom:var(--wp--preset--spacing--70)'
		);
	});

	test('a raw CSS length (e.g. a migrated legacy value) passes through unchanged', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopSpacing: '80px',
			})
		);
		expect(innerStyle(html)).toContain('padding-top:80px');
	});

	test('a divider with NO clearance set emits no inner padding (CSS fallback owns the default)', () => {
		const html = serialize(
			createBlock(metadata.name, { shapeDividerTop: 'wave' })
		);
		expect(innerStyle(html)).not.toContain('padding');
	});

	test('clearance is not emitted for a position that has no divider', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopSpacing: 'var:preset|spacing|70',
				// bottom spacing set but no bottom divider — must be ignored
				shapeDividerBottomSpacing: 'var:preset|spacing|70',
			})
		);
		expect(innerStyle(html)).toContain(
			'padding-top:var(--wp--preset--spacing--70)'
		);
		expect(innerStyle(html)).not.toContain('padding-bottom');
	});
});

describe('section save - overlay class', () => {
	test('no overlay by default', () => {
		const html = serialize(createBlock(metadata.name, {}));
		expect(html).not.toContain('dsgo-stack--has-overlay');
	});

	test('overlayColor emits overlay class + inline color var', () => {
		const html = serialize(
			createBlock(metadata.name, { overlayColor: 'contrast' })
		);
		expect(html).toContain('dsgo-stack--has-overlay');
		expect(html).toContain('--dsgo-overlay-color');
	});

	test('is-style-overlay-dark className emits overlay class without inline color var', () => {
		const html = serialize(
			createBlock(metadata.name, { className: 'is-style-overlay-dark' })
		);
		expect(html).toContain('dsgo-stack--has-overlay');
		// Color comes from the style variation's stylesheet, not inline.
		expect(html).not.toContain('--dsgo-overlay-color');
	});

	test('future is-style-overlay-* variations also enable the overlay', () => {
		const html = serialize(
			createBlock(metadata.name, { className: 'is-style-overlay-light' })
		);
		expect(html).toContain('dsgo-stack--has-overlay');
	});

	test('unrelated is-style-* variation does not enable the overlay', () => {
		const html = serialize(
			createBlock(metadata.name, { className: 'is-style-rounded' })
		);
		expect(html).not.toContain('dsgo-stack--has-overlay');
	});
});

describe('section save - hover variation activation classes', () => {
	test('no hover activation classes by default', () => {
		const html = serialize(createBlock(metadata.name, {}));
		expect(html).not.toContain('dsgo-stack--has-hover-text');
		expect(html).not.toContain('dsgo-stack--has-hover-icon');
		expect(html).not.toContain('dsgo-stack--has-hover-button');
	});

	test('is-style-hover-text-* emits only the hover-text activation class', () => {
		const html = serialize(
			createBlock(metadata.name, {
				className: 'is-style-hover-text-light',
			})
		);
		expect(html).toContain('dsgo-stack--has-hover-text');
		expect(html).not.toContain('dsgo-stack--has-hover-icon');
		expect(html).not.toContain('dsgo-stack--has-hover-button');
	});

	test('is-style-hover-icon-* emits only the hover-icon activation class', () => {
		const html = serialize(
			createBlock(metadata.name, {
				className: 'is-style-hover-icon-blue',
			})
		);
		expect(html).toContain('dsgo-stack--has-hover-icon');
		expect(html).not.toContain('dsgo-stack--has-hover-text');
	});

	test('is-style-hover-button-* emits only the hover-button activation class', () => {
		const html = serialize(
			createBlock(metadata.name, {
				className: 'is-style-hover-button-accent',
			})
		);
		expect(html).toContain('dsgo-stack--has-hover-button');
		expect(html).not.toContain('dsgo-stack--has-hover-icon');
	});

	test('setting a hover attribute alone does NOT add an activation class (inline gate handles it)', () => {
		const html = serialize(
			createBlock(metadata.name, { hoverTextColor: 'contrast' })
		);
		expect(html).not.toContain('dsgo-stack--has-hover-text');
	});
});
