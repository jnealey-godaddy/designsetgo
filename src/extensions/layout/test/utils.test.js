import { sanitizeLayout, getLayoutClass, compileLayoutCSS } from '../utils';

const grid = 'designsetgo/grid';
describe('responsive composition contract', () => {
	it('preserves empty settings without a class or stylesheet', () => {
		expect(sanitizeLayout(grid, { desktop: {}, mobile: {} })).toEqual({});
		expect(getLayoutClass(grid, {})).toBe('');
		expect(compileLayoutCSS(grid, undefined)).toBe('');
	});
	it('normalizes in device/property order and makes equivalent settings stable', () => {
		const a = {
			mobile: { gap: '  1rem  ' },
			desktop: { gap: '2rem', width: '90%' },
		};
		const b = {
			desktop: { width: '90%', gap: '2rem' },
			mobile: { gap: '1rem' },
		};
		expect(sanitizeLayout(grid, a)).toEqual(b);
		expect(getLayoutClass(grid, a)).toBe(getLayoutClass(grid, b));
	});
	it('keeps distinct CSS variables independent despite legacy 32-bit collisions', () => {
		const a = { desktop: { width: 'var(--Aa)' } };
		const b = { desktop: { width: 'var(--BB)' } };
		expect(getLayoutClass('core/group', a)).not.toBe(
			getLayoutClass('core/group', b)
		);
	});
	it('routes responsive spacing and grid tracks to the inner wrapper', () => {
		const css = compileLayoutCSS(grid, {
			desktop: {
				width: 'clamp(20rem, 60vw, 70rem)',
				gridTemplateRows: 'repeat(2, minmax(5rem, 1fr))',
			},
			tablet: {
				gap: 'var(--wp--preset--spacing--40)',
				paddingTop: '2rem',
			},
			mobile: { gap: '12px' },
		});
		expect(css).toContain('width:clamp(20rem, 60vw, 70rem)!important');
		expect(css).toContain(
			'> .dsgo-grid__inner{grid-template-rows:repeat(2, minmax(5rem, 1fr))!important}'
		);
		expect(css).toContain('@media (max-width:1024px)');
		expect(css).toContain('@media (max-width:767px)');
		expect(css).toContain('padding-top:2rem!important');
	});
	it('keeps Align Rows placement precedence including named areas', () => {
		const css = compileLayoutCSS('core/group', {
			desktop: { gridArea: 'media', gridColumn: '2 / span 2' },
		});
		expect(css).toContain(
			':not(.dsgo-grid--match-rows > .dsgo-grid__inner > *)'
		);
		expect(css).toContain('grid-area:media!important');
	});
	it('accepts rectangular named areas and rejects disconnected areas', () => {
		expect(
			sanitizeLayout(grid, {
				desktop: { gridTemplateAreas: '"a a b" "c c b"' },
			})
		).not.toBeNull();
		for (const areas of ['"a b" "b a"', '"a b" "c"', '"a a" "a b"']) {
			expect(
				sanitizeLayout(grid, { desktop: { gridTemplateAreas: areas } })
			).toBeNull();
		}
	});
	it.each([
		{ watch: { width: '10px' } },
		{ desktop: { unknown: '10px' } },
		{ desktop: { width: '1px; color:red' } },
		{ desktop: { width: 'url(https://example.com)' } },
		{ desktop: { width: 'var(--x, url(x))' } },
		{ desktop: { direction: 'row' } },
		{ desktop: { gridArea: 'inherit' } },
		{ desktop: { gridColumn: 'span 0' } },
		{ desktop: { zIndex: '2.5' } },
		{ desktop: { flexGrow: -1 } },
		{ desktop: { width: 'var(1px)' } },
		{ desktop: { width: 'calc(1px +)' } },
		{ desktop: { width: 'none' } },
		{ desktop: { paddingTop: '-4px' } },
		{ desktop: { gap: '-2px' } },
		{ desktop: { gridTemplateRows: 'repeat(0,1fr)' } },
		{ desktop: { gridTemplateRows: '1fr + 2fr' } },
		{ desktop: { flexGrow: 0.00001 } },
	])('refuses invalid values atomically: %j', (layout) => {
		expect(sanitizeLayout(grid, layout)).toBeNull();
		expect(compileLayoutCSS(grid, layout)).toBe('');
	});
});
