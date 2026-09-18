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
	parse,
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

	test('default divider omits height/width custom props (theme tokens apply)', () => {
		// With no inline var the stylesheet cascade resolves the size from
		// `--wp--custom--designsetgo--shape-divider--{height,width}` and only
		// then from the 100px / 100% plugin defaults, so an untouched divider
		// must serialize with NO size custom property at all.
		const html = serialize(
			createBlock(metadata.name, { shapeDividerTop: 'wave' })
		);
		expect(html).toContain('is-shape-wave');
		expect(html).not.toContain('--dsgo-shape-height');
		expect(html).not.toContain('--dsgo-shape-width');
	});

	test('an explicit height is emitted', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopHeight: 80,
			})
		);
		expect(html).toContain('--dsgo-shape-height:80px');
	});

	test('an explicit height/width of the plugin default still serializes', () => {
		// Height and width are nullable, so 100 is an author choice rather than
		// "unset". Both must serialize — otherwise a theme.json
		// settings.custom.designsetgo.shapeDivider.{height,width} token would
		// silently override a divider the author deliberately pinned.
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopHeight: 100,
				shapeDividerTopWidth: 100,
			})
		);
		expect(html).toContain('--dsgo-shape-height:100px');
		expect(html).toContain('--dsgo-shape-width:100%');
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

	test('an explicit height with no explicit clearance exposes a height-matched wrapper var', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopHeight: 300,
			})
		);
		// The stylesheet fallback reads this so the reserved padding matches the
		// 300px divider instead of a flat 100px. No inline inner padding.
		expect(html).toContain('--dsgo-shape-clearance-top:300px');
		expect(innerStyle(html)).not.toContain('padding');
	});

	test('a divider with an unset height emits no clearance var (both sides inherit the theme token)', () => {
		// Unset height means the divider paints at the theme.json height token;
		// the clearance stylesheet falls back to that SAME token, so pinning a
		// px snapshot here would desync the padding from the shape.
		const html = serialize(
			createBlock(metadata.name, { shapeDividerTop: 'wave' })
		);
		expect(html).not.toContain('--dsgo-shape-clearance-top');
	});

	test('an explicit height of 100 still emits the clearance var', () => {
		// The divider pins itself to 100px against any theme token, so the
		// clearance has to pin to 100px too rather than inherit the token.
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopHeight: 100,
			})
		);
		expect(html).toContain('--dsgo-shape-clearance-top:100px');
	});

	test('the clearance var tracks the divider’s clamped render height, not a raw out-of-range value', () => {
		// ShapeDivider clamps height to 10–500; a stored 1000 (only reachable via
		// a direct REST/programmatic edit) renders at 500, so the reserved
		// clearance must be 500px, not 1000px.
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopHeight: 1000,
			})
		);
		expect(html).toContain('--dsgo-shape-clearance-top:500px');
		expect(html).not.toContain('--dsgo-shape-clearance-top:1000px');
	});

	test('a non-positive height (0) is treated as unset, so no size or clearance var is emitted', () => {
		// 0 is only reachable via the Abilities API, whose range check allows
		// it. It cannot mean "paint nothing", so it collapses to unset and the
		// divider inherits the theme token like any untouched divider.
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopHeight: 0,
			})
		);
		expect(html).not.toContain('--dsgo-shape-clearance-top');
		expect(html).not.toContain('--dsgo-shape-height');
	});

	test('an explicit clearance suppresses the wrapper var (inline inner padding wins)', () => {
		const html = serialize(
			createBlock(metadata.name, {
				shapeDividerTop: 'wave',
				shapeDividerTopHeight: 300,
				shapeDividerTopSpacing: 'var:preset|spacing|70',
			})
		);
		expect(html).not.toContain('--dsgo-shape-clearance-top');
		expect(innerStyle(html)).toContain(
			'padding-top:var(--wp--preset--spacing--70)'
		);
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

describe('section save - content column position', () => {
	const innerStyle = (html) => {
		const match = html.match(
			/class="dsgo-stack__inner"[^>]*style="([^"]*)"/
		);
		return match ? match[1] : '';
	};

	test('default centers the column with the historical markup, byte for byte', () => {
		const html = serialize(
			createBlock(metadata.name, { contentWidth: '500px' })
		);
		expect(innerStyle(html)).toBe(
			'max-width:500px;margin-left:auto;margin-right:auto'
		);
		expect(html).not.toContain('contentPosition');
	});

	test('left pins the column to the left edge', () => {
		const html = serialize(
			createBlock(metadata.name, {
				contentWidth: '500px',
				contentPosition: 'left',
			})
		);
		expect(innerStyle(html)).toBe(
			'max-width:500px;margin-left:0;margin-right:auto'
		);
	});

	test('right pins the column to the right edge', () => {
		const html = serialize(
			createBlock(metadata.name, {
				contentWidth: '500px',
				contentPosition: 'right',
			})
		);
		expect(innerStyle(html)).toBe(
			'max-width:500px;margin-left:auto;margin-right:0'
		);
	});

	test('an unconstrained section emits no margins whatever the position', () => {
		const html = serialize(
			createBlock(metadata.name, {
				constrainWidth: false,
				contentPosition: 'left',
			})
		);
		expect(innerStyle(html)).toBe('');
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

describe('section save - outer box width', () => {
	// `boxWidth` caps the OUTER wrapper; `contentWidth` caps
	// `.dsgo-stack__inner`. Assertions therefore have to be scoped per element
	// — a whole-HTML substring match cannot tell the two max-widths apart.
	const outerStyle = (html) => {
		const match = html.match(
			/<div class="[^"]*dsgo-stack[^"]*"[^>]*style="([^"]*)"/
		);
		return match ? match[1] : '';
	};
	const innerStyle = (html) => {
		const match = html.match(
			/class="dsgo-stack__inner"[^>]*style="([^"]*)"/
		);
		return match ? match[1] : '';
	};

	test('boxWidth defaults to an empty string', () => {
		expect(metadata.attributes.boxWidth).toEqual({
			type: 'string',
			default: '',
		});
	});

	test('an unset boxWidth changes NOTHING about the saved markup', () => {
		// The whole point of the default: existing content must keep parsing
		// against the current save() with no deprecation and no "Attempt
		// Recovery". Compare the serialized output of a block that has never
		// heard of boxWidth with one that explicitly carries the default.
		const bare = serialize(createBlock(metadata.name, {}));
		const explicitDefault = serialize(
			createBlock(metadata.name, { boxWidth: '' })
		);

		expect(explicitDefault).toBe(bare);
		expect(bare).not.toContain('dsgo-stack--has-box-width');
		expect(outerStyle(bare)).not.toContain('max-width');
		expect(outerStyle(bare)).not.toContain('width:100%');
	});

	test('the unset case is byte-identical with every other feature engaged', () => {
		// Guards the ordering of the spread in save(): a boxWidth-shaped hole in
		// the style object must not shift any neighbouring declaration.
		const attrs = {
			overlayColor: 'contrast',
			hoverBackgroundColor: 'base',
			hoverTextColor: 'contrast',
			shapeDividerTop: 'wave',
			shapeDividerTopHeight: 120,
			shapeDividerBottom: 'tilt',
			contentWidth: '720px',
			className: 'is-style-overlay-dark',
		};

		expect(serialize(createBlock(metadata.name, attrs))).toBe(
			serialize(createBlock(metadata.name, { ...attrs, boxWidth: '' }))
		);
	});

	test('a set boxWidth caps the OUTER element', () => {
		const html = serialize(
			createBlock(metadata.name, { boxWidth: '430px' })
		);

		expect(html).toContain('dsgo-stack--has-box-width');
		expect(outerStyle(html)).toContain('max-width:430px');
		// width:100% is what makes the box reach the cap inside a flex parent
		// instead of shrink-wrapping to its content.
		expect(outerStyle(html)).toContain('width:100%');
	});

	test('a capped box carries NO inline margin, so a flex parent can place it', () => {
		// Placement lives in styles/_box-width.scss, not here. Flex resolves
		// auto margins BEFORE align-items / justify-content, so an inline
		// `margin: auto` would silently override the alignment the author set
		// on the PARENT section — a parent justified left would still centre
		// its capped child. The marker class is what the stylesheet keys on.
		const style = outerStyle(
			serialize(createBlock(metadata.name, { boxWidth: '430px' }))
		);
		expect(style).not.toContain('margin-left');
		expect(style).not.toContain('margin-right');
	});

	test('boxWidth does NOT touch the content width', () => {
		const html = serialize(
			createBlock(metadata.name, { boxWidth: '430px' })
		);
		// constrainWidth defaults to true, so the inner measure is still the
		// theme content size — unchanged by the outer cap.
		expect(innerStyle(html)).toContain(
			'max-width:var(--wp--style--global--content-size, 1140px)'
		);
		expect(innerStyle(html)).not.toContain('430px');
	});

	test('boxWidth and contentWidth are independently settable', () => {
		const html = serialize(
			createBlock(metadata.name, {
				boxWidth: '430px',
				contentWidth: '320px',
			})
		);
		expect(outerStyle(html)).toContain('max-width:430px');
		expect(innerStyle(html)).toContain('max-width:320px');
	});

	test('boxWidth applies with constrainWidth off (inner stays unconstrained)', () => {
		const html = serialize(
			createBlock(metadata.name, {
				boxWidth: '430px',
				constrainWidth: false,
			})
		);
		expect(outerStyle(html)).toContain('max-width:430px');
		expect(html).toContain('dsgo-no-width-constraint');
		expect(innerStyle(html)).not.toContain('max-width');
	});

	test('boxWidth accepts any CSS length, not just px', () => {
		expect(
			outerStyle(
				serialize(createBlock(metadata.name, { boxWidth: '60%' }))
			)
		).toContain('max-width:60%');
		expect(
			outerStyle(
				serialize(createBlock(metadata.name, { boxWidth: '30rem' }))
			)
		).toContain('max-width:30rem');
	});

	test('an author-set margin survives, and nothing inline competes with it', () => {
		// WordPress's spacing support serializes style.spacing.margin into the
		// SAME inline style attribute, and save()'s own style prop is spread
		// LAST — so any margin save() emitted would clobber the author's.
		// Emitting none means the author's value is the only inline
		// declaration, and it beats the stylesheet default placement.
		const style = outerStyle(
			serialize(
				createBlock(metadata.name, {
					boxWidth: '430px',
					style: { spacing: { margin: { left: '40px' } } },
				})
			)
		);
		expect(style).toContain('margin-left:40px');
		expect(style).not.toContain('margin-left:auto');
		expect(style).not.toContain('margin-right');
	});

	test('boxWidth survives a serialize → parse round trip', () => {
		const html = serialize(
			createBlock(metadata.name, { boxWidth: '430px' })
		);
		const [parsed] = parse(html);
		expect(parsed.isValid).toBe(true);
		expect(parsed.attributes.boxWidth).toBe('430px');
	});

	test('a capped box still carries its shape dividers and overlay', () => {
		// Requirement: the cap moves the painted affordances in with it. The
		// dividers size off the wrapper (position: absolute, width in %), so
		// simply staying inside the capped wrapper is what makes that true.
		const html = serialize(
			createBlock(metadata.name, {
				boxWidth: '430px',
				overlayColor: 'contrast',
				shapeDividerTop: 'wave',
			})
		);
		expect(html).toContain('dsgo-stack--has-box-width');
		expect(html).toContain('dsgo-stack--has-overlay');
		expect(html).toContain('dsgo-shape-divider--top');
	});
});
