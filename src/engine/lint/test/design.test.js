/**
 * `design.js` reads the `designsetgo/get-design-context` ability payload
 * (theme.json-resolved `settings`) and exposes it as lookups lint rules can
 * use without re-implementing WordPress's origin-merge rules.
 */
import {
	paletteColors,
	spacingSlugs,
	fontSizeSlugs,
	presetColor,
} from '../design';

describe('paletteColors', () => {
	test('merges default < theme < custom, later origin wins on slug clash', () => {
		const design = {
			settings: {
				color: {
					palette: {
						default: [
							{
								slug: 'primary',
								color: '#111111',
								name: 'Default Primary',
							},
							{ slug: 'base', color: '#ffffff', name: 'Base' },
						],
						theme: [
							{
								slug: 'primary',
								color: '#222222',
								name: 'Theme Primary',
							},
						],
						custom: [
							{
								slug: 'primary',
								color: '#333333',
								name: 'Custom Primary',
							},
							{
								slug: 'accent',
								color: '#444444',
								name: 'Accent',
							},
						],
					},
				},
			},
		};

		const palette = paletteColors(design);
		expect(palette).toBeInstanceOf(Map);
		expect(palette.get('primary')).toBe('#333333');
		expect(palette.get('base')).toBe('#ffffff');
		expect(palette.get('accent')).toBe('#444444');
		expect(palette.size).toBe(3);
	});

	test('returns an empty Map for missing/empty/undefined design context', () => {
		expect(paletteColors(undefined).size).toBe(0);
		expect(paletteColors({}).size).toBe(0);
		expect(paletteColors({ settings: {} }).size).toBe(0);
		expect(paletteColors({ settings: { color: {} } }).size).toBe(0);
	});
});

describe('spacingSlugs', () => {
	test('merges origins into a Set of slugs', () => {
		const design = {
			settings: {
				spacing: {
					spacingSizes: {
						default: [{ slug: '20', size: '0.25rem' }],
						theme: [{ slug: '50', size: '1rem' }],
						custom: [{ slug: '50', size: '1.5rem' }],
					},
				},
			},
		};

		const slugs = spacingSlugs(design);
		expect(slugs).toBeInstanceOf(Set);
		expect([...slugs].sort()).toEqual(['20', '50']);
	});

	test('returns an empty Set for missing design context', () => {
		expect(spacingSlugs(undefined).size).toBe(0);
		expect(spacingSlugs({}).size).toBe(0);
	});
});

describe('fontSizeSlugs', () => {
	test('merges origins into a Set of slugs', () => {
		const design = {
			settings: {
				typography: {
					fontSizes: {
						theme: [{ slug: 'small', size: '0.8rem' }],
						custom: [{ slug: 'large', size: '2rem' }],
					},
				},
			},
		};

		const slugs = fontSizeSlugs(design);
		expect([...slugs].sort()).toEqual(['large', 'small']);
	});

	test('returns an empty Set for missing design context', () => {
		expect(fontSizeSlugs(undefined).size).toBe(0);
	});
});

describe('presetColor', () => {
	const design = {
		settings: {
			color: {
				palette: {
					theme: [{ slug: 'primary', color: '#123456' }],
				},
			},
		},
	};

	test('resolves a bare slug', () => {
		expect(presetColor(design, 'primary')).toBe('#123456');
	});

	test('resolves var:preset|color|slug', () => {
		expect(presetColor(design, 'var:preset|color|primary')).toBe('#123456');
	});

	test('resolves var(--wp--preset--color--slug)', () => {
		expect(presetColor(design, 'var(--wp--preset--color--primary)')).toBe(
			'#123456'
		);
	});

	test('returns null for a raw hex color', () => {
		expect(presetColor(design, '#123456')).toBeNull();
	});

	test('returns null for a raw rgb() color', () => {
		expect(presetColor(design, 'rgb(1, 2, 3)')).toBeNull();
	});

	test('returns null for an unknown slug', () => {
		expect(presetColor(design, 'not-registered')).toBeNull();
	});

	test('does not crash on an empty/undefined design context', () => {
		expect(presetColor(undefined, 'primary')).toBeNull();
		expect(presetColor({}, 'var:preset|color|primary')).toBeNull();
	});

	test('returns null for non-string values', () => {
		expect(presetColor(design, undefined)).toBeNull();
		expect(presetColor(design, null)).toBeNull();
		expect(presetColor(design, 42)).toBeNull();
	});
});
