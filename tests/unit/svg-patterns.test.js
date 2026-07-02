/**
 * SVG Patterns Extension Tests
 *
 * Tests for the SVG patterns extension that adds repeatable
 * SVG background patterns to container blocks.
 *
 * @package
 */

// Mock WordPress hooks before importing extensions
jest.mock('@wordpress/hooks', () => ({
	addFilter: jest.fn(),
}));

jest.mock('@wordpress/i18n', () => ({
	__: jest.fn((text) => text),
}));

jest.mock('@wordpress/compose', () => ({
	createHigherOrderComponent: jest.fn((fn) => fn),
}));

jest.mock('@wordpress/element', () => ({
	Fragment: ({ children }) => children,
	useMemo: jest.fn((fn) => fn()),
}));

jest.mock('@wordpress/block-editor', () => ({
	InspectorControls: ({ children }) => children,
	__experimentalColorGradientSettingsDropdown: () => null,
	__experimentalUseMultipleOriginColorsAndGradients: jest.fn(() => ({})),
	store: 'core/block-editor',
}));

jest.mock('@wordpress/data', () => ({
	useSelect: jest.fn((fn) =>
		fn(() => ({ getSettings: () => ({ colors: [] }) }))
	),
}));

jest.mock('@wordpress/components', () => ({
	PanelBody: ({ children }) => children,
	ToggleControl: () => null,
	RangeControl: () => null,
	Button: () => null,
	__experimentalHStack: ({ children }) => children,
}));

describe('SVG Patterns Extension', () => {
	describe('Attribute Registration', () => {
		let addSvgPatternAttributes;

		beforeEach(() => {
			jest.resetModules();
			jest.mock('@wordpress/hooks', () => ({
				addFilter: jest.fn(),
			}));
			jest.mock('@wordpress/i18n', () => ({
				__: jest.fn((text) => text),
			}));

			require('../../src/extensions/svg-patterns/attributes');

			const { addFilter: mockAddFilter } = require('@wordpress/hooks');
			const calls = mockAddFilter.mock.calls.filter(
				(call) =>
					call[0] === 'blocks.registerBlockType' &&
					call[1].includes('svg-pattern')
			);

			if (calls.length > 0) {
				addSvgPatternAttributes = calls[0][2];
			}
		});

		it('registers a filter for block registration', () => {
			const { addFilter: mockAddFilter } = require('@wordpress/hooks');
			expect(mockAddFilter).toHaveBeenCalledWith(
				'blocks.registerBlockType',
				'designsetgo/svg-pattern-attributes',
				expect.any(Function)
			);
		});

		it('adds SVG pattern attributes to core/group', () => {
			if (!addSvgPatternAttributes) {
				return;
			}

			const result = addSvgPatternAttributes(
				{ attributes: {} },
				'core/group'
			);

			expect(result.attributes).toHaveProperty('dsgoSvgPatternEnabled');
			expect(result.attributes).toHaveProperty('dsgoSvgPatternType');
			expect(result.attributes).toHaveProperty('dsgoSvgPatternColor');
			expect(result.attributes).toHaveProperty('dsgoSvgPatternOpacity');
			expect(result.attributes).toHaveProperty('dsgoSvgPatternScale');
			expect(result.attributes).toHaveProperty('dsgoSvgPatternFixed');
		});

		it('adds SVG pattern attributes to designsetgo/section', () => {
			if (!addSvgPatternAttributes) {
				return;
			}

			const result = addSvgPatternAttributes(
				{ attributes: {} },
				'designsetgo/section'
			);

			expect(result.attributes).toHaveProperty('dsgoSvgPatternEnabled');
		});

		it('does not add attributes to unsupported blocks', () => {
			if (!addSvgPatternAttributes) {
				return;
			}

			const result = addSvgPatternAttributes(
				{ attributes: {} },
				'core/paragraph'
			);

			expect(result.attributes).not.toHaveProperty(
				'dsgoSvgPatternEnabled'
			);
		});

		it('preserves original block attributes', () => {
			if (!addSvgPatternAttributes) {
				return;
			}

			const originalSettings = {
				attributes: {
					content: { type: 'string', default: 'Hello' },
				},
			};

			const result = addSvgPatternAttributes(
				originalSettings,
				'core/group'
			);

			expect(result.attributes.content).toEqual({
				type: 'string',
				default: 'Hello',
			});
		});

		it('attributes have correct types and defaults', () => {
			if (!addSvgPatternAttributes) {
				return;
			}

			const result = addSvgPatternAttributes(
				{ attributes: {} },
				'core/group'
			);

			expect(result.attributes.dsgoSvgPatternEnabled).toEqual({
				type: 'boolean',
				default: false,
			});
			expect(result.attributes.dsgoSvgPatternType).toEqual({
				type: 'string',
				default: '',
			});
			expect(result.attributes.dsgoSvgPatternColor).toEqual({
				type: 'string',
				default: '',
			});
			expect(result.attributes.dsgoSvgPatternOpacity).toEqual({
				type: 'number',
				default: 0.4,
			});
			expect(result.attributes.dsgoSvgPatternScale).toEqual({
				type: 'number',
				default: 1,
			});
			expect(result.attributes.dsgoSvgPatternFixed).toEqual({
				type: 'boolean',
				default: false,
			});
		});

		it('all attribute names follow dsgo prefix convention', () => {
			if (!addSvgPatternAttributes) {
				return;
			}

			const result = addSvgPatternAttributes(
				{ attributes: {} },
				'core/group'
			);

			const svgAttrs = Object.keys(result.attributes).filter((key) =>
				key.startsWith('dsgoSvgPattern')
			);

			expect(svgAttrs.length).toBe(6);
			svgAttrs.forEach((attr) => {
				expect(attr).toMatch(/^dsgo[A-Z]/);
			});
		});
	});

	describe('Pattern Utilities', () => {
		let isValidColor,
			encodeSvg,
			buildPatternSvg,
			getPatternBackground,
			PATTERNS,
			PATTERN_IDS,
			CATEGORIES;

		beforeEach(() => {
			jest.resetModules();
			jest.mock('@wordpress/i18n', () => ({
				__: jest.fn((text) => text),
			}));

			const patterns = require('../../src/extensions/svg-patterns/patterns');
			isValidColor = patterns.isValidColor;
			encodeSvg = patterns.encodeSvg;
			buildPatternSvg = patterns.buildPatternSvg;
			getPatternBackground = patterns.getPatternBackground;
			PATTERNS = patterns.PATTERNS;
			PATTERN_IDS = patterns.PATTERN_IDS;
			CATEGORIES = patterns.CATEGORIES;
		});

		describe('isValidColor', () => {
			it('accepts valid hex colors', () => {
				expect(isValidColor('#fff')).toBe(true);
				expect(isValidColor('#ffffff')).toBe(true);
				expect(isValidColor('#FF0000')).toBe(true);
				expect(isValidColor('#ff000080')).toBe(true);
			});

			it('accepts rgb/rgba colors', () => {
				expect(isValidColor('rgb(255, 0, 0)')).toBe(true);
				expect(isValidColor('rgba(255, 0, 0, 0.5)')).toBe(true);
			});

			it('accepts hsl/hsla colors', () => {
				expect(isValidColor('hsl(0, 100%, 50%)')).toBe(true);
				expect(isValidColor('hsla(0, 100%, 50%, 0.5)')).toBe(true);
			});

			it('accepts named colors', () => {
				expect(isValidColor('red')).toBe(true);
				expect(isValidColor('blue')).toBe(true);
				expect(isValidColor('transparent')).toBe(true);
			});

			it('rejects XSS injection attempts', () => {
				expect(isValidColor('#000" onload="alert(\'xss\')')).toBe(
					false
				);
				expect(isValidColor('javascript:alert(1)')).toBe(false);
				expect(isValidColor('<script>alert(1)</script>')).toBe(false);
			});

			it('rejects null/undefined/empty', () => {
				expect(isValidColor(null)).toBe(false);
				expect(isValidColor(undefined)).toBe(false);
				expect(isValidColor('')).toBe(false);
			});

			it('rejects non-string types', () => {
				expect(isValidColor(123)).toBe(false);
				expect(isValidColor({})).toBe(false);
			});

			it('rejects CSS variables (cannot be used in SVG data URIs)', () => {
				expect(isValidColor('var(--wp--preset--color--primary)')).toBe(
					false
				);
				expect(
					isValidColor('var(--wp--preset--color--vivid-red)')
				).toBe(false);
			});

			it('rejects WordPress preset format', () => {
				expect(isValidColor('var:preset|color|primary')).toBe(false);
			});
		});

		describe('encodeSvg', () => {
			it('returns a url() data URI', () => {
				const result = encodeSvg('<svg></svg>');
				expect(result).toMatch(/^url\("data:image\/svg\+xml,/);
			});

			it('encodes SVG content', () => {
				const result = encodeSvg('<svg width="10" height="10"></svg>');
				expect(result).toContain(encodeURIComponent('<svg'));
			});
		});

		describe('buildPatternSvg', () => {
			const testPattern = {
				width: 24,
				height: 24,
				paths: [
					{
						d: 'M10 12a2 2 0 1 0 4 0 2 2 0 0 0-4 0z',
					},
				],
			};

			it('generates valid SVG markup', () => {
				const svg = buildPatternSvg(testPattern, '#ff0000', 0.5);
				expect(svg).toContain('<svg');
				expect(svg).toContain('xmlns="http://www.w3.org/2000/svg"');
				expect(svg).toContain('width="24"');
				expect(svg).toContain('height="24"');
				expect(svg).toContain('<path');
				expect(svg).toContain('</svg>');
			});

			it('applies fill color', () => {
				const svg = buildPatternSvg(testPattern, '#ff0000', 0.5);
				expect(svg).toContain('fill="#ff0000"');
			});

			it('applies fill opacity', () => {
				const svg = buildPatternSvg(testPattern, '#000', 0.7);
				expect(svg).toContain('fill-opacity="0.7"');
			});

			it('sanitizes invalid color to default', () => {
				const svg = buildPatternSvg(
					testPattern,
					'<script>xss</script>',
					0.5
				);
				expect(svg).toContain('fill="#9c92ac"');
				expect(svg).not.toContain('<script>');
			});

			it('falls back to default for CSS variables (not valid in data URIs)', () => {
				const svg = buildPatternSvg(
					testPattern,
					'var(--wp--preset--color--primary)',
					0.5
				);
				expect(svg).toContain('fill="#9c92ac"');
				expect(svg).not.toContain('var(');
			});

			it('falls back to default for WordPress preset format', () => {
				const svg = buildPatternSvg(
					testPattern,
					'var:preset|color|primary',
					0.5
				);
				expect(svg).toContain('fill="#9c92ac"');
			});

			it('clamps opacity to 0-1 range', () => {
				const svgHigh = buildPatternSvg(testPattern, '#000', 5);
				expect(svgHigh).toContain('fill-opacity="1"');

				const svgLow = buildPatternSvg(testPattern, '#000', -1);
				expect(svgLow).toContain('fill-opacity="0"');
			});

			it('handles stroke-based patterns', () => {
				const strokePattern = {
					width: 120,
					height: 40,
					paths: [
						{
							d: 'M0 20Q30 5 60 20T120 20',
							stroke: true,
							strokeWidth: 2,
						},
					],
				};

				const svg = buildPatternSvg(strokePattern, '#333', 0.6);
				expect(svg).toContain('fill="none"');
				expect(svg).toContain('stroke="#333"');
				expect(svg).toContain('stroke-opacity="0.6"');
				expect(svg).toContain('stroke-width="2"');
			});
		});

		describe('getPatternBackground', () => {
			it('returns backgroundImage and backgroundSize for valid pattern', () => {
				const result = getPatternBackground('dot-grid', '#000', 0.4, 1);
				expect(result).toHaveProperty('backgroundImage');
				expect(result).toHaveProperty('backgroundSize');
				expect(result.backgroundImage).toMatch(/^url\(/);
				expect(result.backgroundSize).toMatch(/\d+px \d+px/);
			});

			it('returns null for invalid pattern ID', () => {
				expect(
					getPatternBackground('nonexistent', '#000', 0.4)
				).toBeNull();
			});

			it('returns null for empty pattern ID', () => {
				expect(getPatternBackground('', '#000', 0.4)).toBeNull();
			});

			it('returns null for non-string pattern ID', () => {
				expect(getPatternBackground(123, '#000', 0.4)).toBeNull();
				expect(getPatternBackground(null, '#000', 0.4)).toBeNull();
				expect(getPatternBackground(undefined, '#000', 0.4)).toBeNull();
			});

			it('applies scale to backgroundSize', () => {
				const result1x = getPatternBackground(
					'dot-grid',
					'#000',
					0.4,
					1
				);
				const result2x = getPatternBackground(
					'dot-grid',
					'#000',
					0.4,
					2
				);

				// 2x should have double the dimensions
				const [w1] = result1x.backgroundSize.split(' ').map(parseFloat);
				const [w2] = result2x.backgroundSize.split(' ').map(parseFloat);
				expect(w2).toBe(w1 * 2);
			});

			it('clamps scale to 0.25-4 range', () => {
				const resultLow = getPatternBackground(
					'dot-grid',
					'#000',
					0.4,
					0.01
				);
				const resultHigh = getPatternBackground(
					'dot-grid',
					'#000',
					0.4,
					100
				);
				const result025 = getPatternBackground(
					'dot-grid',
					'#000',
					0.4,
					0.25
				);
				const result4 = getPatternBackground(
					'dot-grid',
					'#000',
					0.4,
					4
				);

				expect(resultLow.backgroundSize).toBe(result025.backgroundSize);
				expect(resultHigh.backgroundSize).toBe(result4.backgroundSize);
			});
		});

		describe('Pattern Data', () => {
			it('PATTERNS object has entries', () => {
				expect(Object.keys(PATTERNS).length).toBeGreaterThan(0);
			});

			it('PATTERN_IDS is sorted alphabetically', () => {
				const sorted = [...PATTERN_IDS].sort();
				expect(PATTERN_IDS).toEqual(sorted);
			});

			it('PATTERN_IDS matches PATTERNS keys', () => {
				expect(PATTERN_IDS).toEqual(
					expect.arrayContaining(Object.keys(PATTERNS))
				);
				expect(PATTERN_IDS.length).toBe(Object.keys(PATTERNS).length);
			});

			it('every pattern has required properties', () => {
				PATTERN_IDS.forEach((id) => {
					const pattern = PATTERNS[id];
					expect(pattern).toHaveProperty('label');
					expect(pattern).toHaveProperty('category');
					expect(pattern).toHaveProperty('width');
					expect(pattern).toHaveProperty('height');
					expect(pattern).toHaveProperty('paths');
					expect(typeof pattern.label).toBe('string');
					expect(typeof pattern.category).toBe('string');
					expect(typeof pattern.width).toBe('number');
					expect(typeof pattern.height).toBe('number');
					expect(Array.isArray(pattern.paths)).toBe(true);
					expect(pattern.paths.length).toBeGreaterThan(0);
				});
			});

			it('every pattern path has a d attribute', () => {
				PATTERN_IDS.forEach((id) => {
					PATTERNS[id].paths.forEach((path) => {
						expect(path).toHaveProperty('d');
						expect(typeof path.d).toBe('string');
						expect(path.d.length).toBeGreaterThan(0);
					});
				});
			});

			it('every pattern category exists in CATEGORIES', () => {
				PATTERN_IDS.forEach((id) => {
					expect(CATEGORIES).toHaveProperty(PATTERNS[id].category);
				});
			});

			it('CATEGORIES has expected keys', () => {
				expect(CATEGORIES).toHaveProperty('minimal');
				expect(CATEGORIES).toHaveProperty('geometric');
				expect(CATEGORIES).toHaveProperty('organic');
				expect(CATEGORIES).toHaveProperty('texture');
				expect(CATEGORIES).toHaveProperty('depth');
				expect(CATEGORIES).toHaveProperty('technical');
			});
		});
	});

	describe('Filter Namespace', () => {
		it('all filters use designsetgo/ namespace', () => {
			jest.resetModules();
			jest.mock('@wordpress/hooks', () => ({
				addFilter: jest.fn(),
			}));
			jest.mock('@wordpress/i18n', () => ({
				__: jest.fn((text) => text),
			}));
			jest.mock('@wordpress/compose', () => ({
				createHigherOrderComponent: jest.fn((fn) => fn),
			}));
			jest.mock('@wordpress/element', () => ({
				Fragment: ({ children }) => children,
				useMemo: jest.fn((fn) => fn()),
			}));
			jest.mock('@wordpress/data', () => ({
				useSelect: jest.fn((fn) =>
					fn(() => ({ getSettings: () => ({ colors: [] }) }))
				),
			}));

			require('../../src/extensions/svg-patterns/attributes');
			require('../../src/extensions/svg-patterns/editor');

			const { addFilter: mockAddFilter } = require('@wordpress/hooks');

			mockAddFilter.mock.calls.forEach((call) => {
				expect(call[1]).toMatch(/^designsetgo\//);
			});
		});
	});
});
