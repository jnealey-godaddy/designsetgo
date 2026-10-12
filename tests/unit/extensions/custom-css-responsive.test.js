/**
 * Custom CSS — per-breakpoint sections.
 */
import {
	splitResponsiveCSS,
	joinResponsiveCSS,
} from '../../../src/extensions/custom-css/responsive';

import fixture from '../../fixtures/custom-css-responsive.json';

// What Responsive_CSS::merge() writes for desktop + tablet + mobile input.
// PHPUnit asserts merge() still produces this exact string.
const SERVER_CSS = fixture.css;

describe('splitResponsiveCSS', () => {
	it('reads the sections the configure-custom-css ability writes', () => {
		expect(splitResponsiveCSS(SERVER_CSS)).toEqual(fixture.input);
	});

	it('treats CSS without markers as desktop, unchanged', () => {
		const css =
			'selector { gap: 1rem; }\n@media (max-width: 600px) { selector { gap: 0; } }\n';
		expect(splitResponsiveCSS(css)).toEqual({
			desktop: css,
			tablet: '',
			mobile: '',
		});
	});

	it('copes with empty and non-string values', () => {
		expect(splitResponsiveCSS(undefined)).toEqual({
			desktop: '',
			tablet: '',
			mobile: '',
		});
		expect(splitResponsiveCSS('')).toEqual({
			desktop: '',
			tablet: '',
			mobile: '',
		});
	});

	it('keeps the body of a section whose wrapper was hand-edited', () => {
		const css =
			'/* dsgo-css:mobile:start */\nselector { top: 0; }\n/* dsgo-css:mobile:end */';
		expect(splitResponsiveCSS(css).mobile).toBe('selector { top: 0; }');
	});
});

describe('joinResponsiveCSS', () => {
	it('writes exactly what the server writes', () => {
		expect(joinResponsiveCSS(fixture.input)).toBe(SERVER_CSS);
	});

	it('leaves desktop-only CSS byte-identical, so existing class hashes hold', () => {
		const css = 'selector { padding: 2rem; }\n';
		expect(
			joinResponsiveCSS({ desktop: css, tablet: '', mobile: '  ' })
		).toBe(css);
	});

	it('writes a mobile-only section with no desktop rules', () => {
		expect(
			joinResponsiveCSS({ desktop: '', mobile: 'selector { a: b; }' })
		).toBe(
			'/* dsgo-css:mobile:start */\n@media (max-width: 767px) {\nselector { a: b; }\n}\n/* dsgo-css:mobile:end */'
		);
	});

	it('strips markers typed into a field so sections cannot nest', () => {
		const joined = joinResponsiveCSS({
			desktop: '',
			tablet: '/* dsgo-css:tablet:end */ selector {}',
		});
		expect(joined.match(/dsgo-css:tablet:end/g)).toHaveLength(1);
	});
});

describe('round trip', () => {
	it.each([
		[{ desktop: 'a {}', tablet: '', mobile: '' }],
		[{ desktop: 'a {}\n', tablet: 'b {}', mobile: '' }],
		[{ desktop: '', tablet: 'b {}\n', mobile: 'c {}' }],
		[
			{
				desktop: 'a {}\n\n',
				tablet: 'selector { x: y; }\nselector h2 { z: 1; }',
				mobile: 'c {\n}',
			},
		],
	])(
		'keeps what the author typed, including trailing newlines (%#)',
		(parts) => {
			expect(splitResponsiveCSS(joinResponsiveCSS(parts))).toEqual(parts);
		}
	);
});
