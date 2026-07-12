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
	getSaveContent,
	getBlockContent,
	// eslint-disable-next-line import/no-unresolved
} from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import metadata from '../block.json';
import save from '../save';
import deprecated from '../deprecated';

setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }]);

registerBlockType(metadata.name, { ...metadata, save, deprecated });

// deprecated.js exports newest-first: [v9, v8, v7, ...]. v9 (the justification
// wrapper) was prepended ahead of v8 (the themeable-gap migration) — see
// src/blocks/icon-button/deprecated.js.
const [v9Deprecation, v8Deprecation] = deprecated;

// getSaveContent() renders EXACTLY the attributes object it is given — unlike
// createBlock()/parse(), it does not fill in a deprecation's own schema
// defaults for keys the caller omits. A real stored block always has those
// defaults already applied (e.g. `linkTarget: '_self'`, which save() renders
// as `target="_self"` whenever `url` is set), so fixtures built directly from
// getSaveContent() must fill them in too or they silently omit attributes
// (like `target`) that real content always carries.
function withDefaults(blockType, attrs) {
	const defaults = Object.fromEntries(
		Object.entries(blockType.attributes)
			.filter(([, schema]) => 'default' in schema)
			.map(([key, schema]) => [key, schema.default])
	);
	return { ...defaults, ...attrs };
}

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
	// v9 (deprecated.js) is a verbatim copy of the pre-wrapper save() — the
	// single-element `<a>`/`<button>` root the v8 gap fixtures below need.
	// getSaveContent() takes the blockTypeOrName argument directly (via a
	// module-level provider set right before save() runs), so passing the
	// deprecation object itself — not the currently-registered (wrapper-shaped)
	// block type — is what makes useBlockProps.save() inside v9.save() apply
	// v9's OWN supports/save, not the live block's.
	const v9BlockType = { name: metadata.name, ...v9Deprecation };

	// getSaveContent() returns bare HTML (no block-comment delimiters), but
	// parse() needs the full `<!-- wp:… {…} -->html<!-- /wp:… -->` syntax to
	// find the block at all. Wrap it exactly like a real stored post would.
	const wrapComment = (attrsJson, html) =>
		`<!-- wp:designsetgo/icon-button ${JSON.stringify(attrsJson)} -->${html}<!-- /wp:designsetgo/icon-button -->`;

	// Derive byte-exact OLD (v8) markup from v9's single-element output: the
	// pre-themeable-gap format additionally dropped the marker class and
	// always baked an inline gap right after justify-content.
	const v9Icon = getSaveContent(
		v9BlockType,
		withDefaults(v9BlockType, {
			text: 'Go',
			url: '#',
			icon: 'star',
			iconPosition: 'start',
		})
	);
	const OLD_ICON_MARKUP = wrapComment(
		{ text: 'Go', url: '#', icon: 'star', iconPosition: 'start' },
		v9Icon
			.replace(' dsgo-icon-button--has-icon', '')
			.replace(
				'justify-content:center;width:auto',
				'justify-content:center;gap:8px;width:auto'
			)
	);

	const v9NoIcon = getSaveContent(
		v9BlockType,
		withDefaults(v9BlockType, {
			text: 'Go',
			url: '#',
			iconPosition: 'none',
		})
	);
	const OLD_NO_ICON_MARKUP = wrapComment(
		{ text: 'Go', url: '#', iconPosition: 'none' },
		v9NoIcon.replace(
			'justify-content:center;width:auto',
			'justify-content:center;gap:0;width:auto'
		)
	);

	// The REAL current (wrapper-shaped) output — used below to confirm v8's
	// isEligible() correctly ignores genuinely current markup.
	const canonicalIcon = serialize(
		createBlock(metadata.name, {
			text: 'Go',
			url: '#',
			icon: 'star',
			iconPosition: 'start',
		})
	);
	const canonicalNoIcon = serialize(
		createBlock(metadata.name, {
			text: 'Go',
			url: '#',
			iconPosition: 'none',
		})
	);

	test('derived old markup differs from canonical as expected', () => {
		expect(OLD_ICON_MARKUP).not.toContain('dsgo-icon-button--has-icon');
		expect(OLD_ICON_MARKUP).toContain('gap:8px');
		expect(OLD_NO_ICON_MARKUP).toContain('gap:0');
	});

	test('old icon button (inline gap, no marker class) migrates silently and pins the gap', () => {
		const [block] = parse(OLD_ICON_MARKUP);

		expect(console).toHaveInformed();
		expect(block.name).toBe('designsetgo/icon-button');
		expect(block.isValid).toBe(true);
		// Passthrough migrate pins the gap the old markup carried; the current
		// save() re-emits it inline and adds the marker class.
		expect(block.attributes.iconGap).toBe('8px');
		expect(getBlockContent(block)).toContain('dsgo-icon-button--has-icon');
		expect(getBlockContent(block)).toContain('gap:8px');
	});

	test('old icon-less button (gap:0) migrates silently', () => {
		const [block] = parse(OLD_NO_ICON_MARKUP);

		expect(console).toHaveInformed();
		expect(block.name).toBe('designsetgo/icon-button');
		expect(block.isValid).toBe(true);
		expect(getBlockContent(block)).not.toMatch(/gap:/);
	});

	test('old button with an explicit non-default gap keeps it after migration', () => {
		const v9Explicit = getSaveContent(
			v9BlockType,
			withDefaults(v9BlockType, {
				text: 'Go',
				url: '#',
				icon: 'star',
				iconPosition: 'start',
				iconGap: '12px',
			})
		);
		// Old explicit-gap markup: same inline gap:12px, only the marker class
		// is absent (that is the sole difference from the v9/current save).
		const oldExplicit = wrapComment(
			{
				text: 'Go',
				url: '#',
				icon: 'star',
				iconPosition: 'start',
				iconGap: '12px',
			},
			v9Explicit.replace(' dsgo-icon-button--has-icon', '')
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

	test('isEligible ignores a valid icon-less button whose label contains "gap:"', () => {
		// `text` is free-form RichText serialized into innerHTML; scoping the
		// check to the button's own opening tag keeps a label like
		// "Mind the gap: ..." from false-triggering the deprecation.
		const html =
			'<a class="wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button" style="display:inline-flex;align-items:center;justify-content:center;width:auto;flex-direction:row" href="#"><span class="dsgo-icon-button__text">Mind the gap: watch your step</span></a>';
		expect(v8Deprecation.isEligible({}, [], { innerHTML: html })).toBe(
			false
		);
	});

	test('migrate is a passthrough that pins values (never strips defaults), plus the align→justification/fullWidth conversion', () => {
		expect(v8Deprecation.migrate({ iconGap: '8px', text: 'x' })).toEqual({
			iconGap: '8px',
			text: 'x',
			justification: 'left',
			fullWidth: false,
		});
		expect(v8Deprecation.migrate({ iconGap: '12px', text: 'x' })).toEqual({
			iconGap: '12px',
			text: 'x',
			justification: 'left',
			fullWidth: false,
		});
	});
});
