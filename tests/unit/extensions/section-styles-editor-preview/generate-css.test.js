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
});
