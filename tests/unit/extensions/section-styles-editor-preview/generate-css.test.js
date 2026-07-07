/**
 * Section Styles — Editor Preview: CSS generation unit tests
 *
 * @package
 */

import {
	toCssValue,
	variationDeclarations,
	buildVariationCss,
	TARGET_SUFFIXES,
} from '../../../../src/extensions/section-styles-editor-preview/generate-css';

describe('toCssValue', () => {
	it('passes plain values through', () => {
		expect(toCssValue('#ff0000')).toBe('#ff0000');
		expect(toCssValue('3px')).toBe('3px');
	});

	it('expands preset references', () => {
		expect(toCssValue('var:preset|color|accent-2')).toBe(
			'var(--wp--preset--color--accent-2)'
		);
		expect(toCssValue('var:preset|shadow|natural')).toBe(
			'var(--wp--preset--shadow--natural)'
		);
	});

	it('kebab-cases multi-word preset types', () => {
		expect(toCssValue('var:preset|font-size|large')).toBe(
			'var(--wp--preset--font-size--large)'
		);
		expect(toCssValue('var:custom|myToken|innerValue')).toBe(
			'var(--wp--custom--my-token--inner-value)'
		);
	});

	it('ignores non-strings', () => {
		expect(toCssValue(40)).toBe(40);
		expect(toCssValue(undefined)).toBe(undefined);
	});

	it('strips rule-breaking characters to prevent CSS injection', () => {
		expect(toCssValue('red;}body{display:none')).toBe(
			'redbodydisplay:none'
		);
		expect(toCssValue('var:preset|color|a}b')).toBe(
			'var(--wp--preset--color--ab)'
		);
	});

	it('strips backslashes so CSS hex escapes cannot re-form rule-breaking characters', () => {
		// `\7b`/`\7d` would decode to `{`/`}` in the browser's CSS tokenizer,
		// slipping past a literal-character strip. Removing the backslash
		// neutralizes the escape.
		expect(toCssValue('0\\7b color:red\\7d')).toBe('07b color:red7d');
	});
});

describe('variationDeclarations', () => {
	it('returns empty for nothing set', () => {
		expect(variationDeclarations({})).toBe('');
		expect(variationDeclarations(null)).toBe('');
	});

	it('emits color + gradient + text', () => {
		expect(
			variationDeclarations({
				color: {
					background: 'var:preset|color|accent-2',
					text: '#fff',
				},
			})
		).toBe(
			'background-color:var(--wp--preset--color--accent-2);color:#fff'
		);
		expect(
			variationDeclarations({
				color: { gradient: 'linear-gradient(#000,#fff)' },
			})
		).toBe('background:linear-gradient(#000,#fff)');
	});

	it('resolves preset references on border width and radius', () => {
		expect(
			variationDeclarations({
				border: {
					width: 'var:preset|spacing|40',
					radius: 'var:custom|radius|lg',
				},
			})
		).toBe(
			'border-width:var(--wp--preset--spacing--40);' +
				'border-radius:var(--wp--custom--radius--lg)'
		);
	});

	it('preserves a zero border-width override (falsy-but-set)', () => {
		expect(
			variationDeclarations({ border: { width: '0', style: 'none' } })
		).toBe('border-width:0;border-style:none');
	});

	it('emits flat border with radius', () => {
		expect(
			variationDeclarations({
				border: {
					color: '#008000',
					width: '6px',
					style: 'solid',
					radius: '20px',
				},
			})
		).toBe(
			'border-color:#008000;border-width:6px;border-style:solid;border-radius:20px'
		);
	});

	it('emits split-side borders and per-corner radius', () => {
		expect(
			variationDeclarations({
				border: {
					top: { color: '#111', width: '2px', style: 'solid' },
					bottom: { width: '4px', style: 'dashed' },
					radius: {
						topLeft: '8px',
						bottomRight: '12px',
					},
				},
			})
		).toBe(
			'border-top-color:#111;border-top-width:2px;border-top-style:solid;' +
				'border-bottom-width:4px;border-bottom-style:dashed;' +
				'border-top-left-radius:8px;border-bottom-right-radius:12px'
		);
	});

	it('preserves a numeric 0 per-corner radius (falsy-but-set)', () => {
		expect(
			variationDeclarations({
				border: { radius: { topLeft: 0, bottomRight: '12px' } },
			})
		).toBe('border-top-left-radius:0;border-bottom-right-radius:12px');
	});

	it('emits spacing, shadow and typography', () => {
		expect(
			variationDeclarations({
				spacing: {
					padding: { top: '40px', bottom: 'var:preset|spacing|50' },
				},
				shadow: 'var:preset|shadow|natural',
				typography: { fontSize: 'var:preset|font-size|large' },
			})
		).toBe(
			'padding-top:40px;padding-bottom:var(--wp--preset--spacing--50);' +
				'box-shadow:var(--wp--preset--shadow--natural);' +
				'font-size:var(--wp--preset--font-size--large)'
		);
	});

	it('routes line-height through toCssValue (preset resolution + injection strip)', () => {
		expect(
			variationDeclarations({
				typography: { lineHeight: 'var:custom|lineHeight|tight' },
			})
		).toBe('line-height:var(--wp--custom--line-height--tight)');
		// Injection guard applies to line-height like every other property.
		expect(
			variationDeclarations({
				typography: { lineHeight: '1.5;}body{display:none' },
			})
		).toBe('line-height:1.5bodydisplay:none');
	});
});

describe('buildVariationCss', () => {
	it('returns empty when no source variations exist', () => {
		expect(buildVariationCss({})).toBe('');
		expect(buildVariationCss({ 'core/group': {} })).toBe('');
		expect(buildVariationCss(null)).toBe('');
	});

	it('emits one rule per target for each variation, stable is-style class', () => {
		const css = buildVariationCss({
			'core/group': {
				variations: {
					'section-2': { border: { width: '5px', style: 'solid' } },
				},
			},
		});
		// One rule per DSGo target suffix.
		const ruleCount = (css.match(/is-style-section-2\{/g) || []).length;
		expect(ruleCount).toBe(TARGET_SUFFIXES.length);
		expect(css).toContain(
			'.wp-block-designsetgo-section.is-style-section-2{border-width:5px;border-style:solid}'
		);
		expect(css).toContain(
			'.wp-block-designsetgo-counter.is-style-section-2{border-width:5px;border-style:solid}'
		);
	});

	it('excludes image-accordion-item (background opt-out)', () => {
		const css = buildVariationCss({
			'core/group': {
				variations: { 'section-2': { color: { background: '#f00' } } },
			},
		});
		expect(css).not.toContain('image-accordion-item');
	});

	it('skips variations with no renderable declarations', () => {
		expect(
			buildVariationCss({
				'core/group': { variations: { empty: {} } },
			})
		).toBe('');
	});

	it('skips a target that defines its own explicit variation for the slug', () => {
		const css = buildVariationCss({
			'core/group': {
				variations: {
					'section-2': { border: { width: '5px', style: 'solid' } },
				},
			},
			// The section block has its own explicit section-2 — the editor
			// previews that natively and the server mirror protects it, so the
			// overlay must not emit the core-container version onto it.
			'designsetgo/section': {
				variations: { 'section-2': { color: { text: '#000' } } },
			},
		});
		expect(css).not.toContain(
			'.wp-block-designsetgo-section.is-style-section-2{'
		);
		// Other targets without their own explicit variation still get it.
		expect(css).toContain(
			'.wp-block-designsetgo-counter.is-style-section-2{'
		);
	});

	it('first source block wins on slug collision', () => {
		const css = buildVariationCss({
			'core/group': {
				variations: { dup: { color: { text: '#111' } } },
			},
			'core/columns': {
				variations: { dup: { color: { text: '#999' } } },
			},
		});
		expect(css).toContain(
			'.wp-block-designsetgo-section.is-style-dup{color:#111}'
		);
		expect(css).not.toContain('#999');
	});

	it('drops a variation slug that is not a safe CSS-class token', () => {
		const css = buildVariationCss({
			'core/group': {
				variations: {
					'evil}body{display:none': {
						color: { background: '#f00' },
					},
					safe: { color: { text: '#111' } },
				},
			},
		});
		// The crafted slug can't reach the selector at all.
		expect(css).not.toContain('display:none');
		expect(css).not.toContain('evil');
		// Legitimate sibling variation still emits.
		expect(css).toContain(
			'.wp-block-designsetgo-section.is-style-safe{color:#111}'
		);
	});
});
