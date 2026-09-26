import {
	applyOverlayMenuColors,
	resolveOverlayMenuColors,
} from '../../src/utils/overlay-menu-colors';

const theme = {
	'--dsgo-overlay-menu-surface': '#193341',
	'--wp--preset--color--base-2': '#193341',
	'--wp--preset--color--contrast-2': '#fff',
	'--wp--preset--color--contrast': '#000',
};
const tokenReader = (palette) => (name) => palette[name] || '';

describe('overlay menu palette', () => {
	it.each([
		['pairs base-2 with contrast-2', {}, 'rgb(25, 51, 65)', '#fff'],
		[
			'falls back to contrast when contrast-2 is missing',
			{ '--wp--preset--color--contrast-2': '' },
			'rgb(25, 51, 65)',
			'#000',
		],
		[
			'does not use contrast-2 for the legacy white surface',
			{ '--dsgo-overlay-menu-surface': '#fff' },
			'rgb(255, 255, 255)',
			'#000',
		],
		[
			'does not use contrast-2 without base-2',
			{ '--wp--preset--color--base-2': '' },
			'rgb(25, 51, 65)',
			'#000',
		],
		[
			'uses white on a dark surface without contrast tokens',
			{
				'--wp--preset--color--contrast-2': '',
				'--wp--preset--color--contrast': '',
			},
			'rgb(25, 51, 65)',
			'#fff',
		],
		[
			'uses black on a light surface without contrast tokens',
			{
				'--dsgo-overlay-menu-surface': '#eee6e1',
				'--wp--preset--color--contrast-2': '',
				'--wp--preset--color--contrast': '',
			},
			'rgb(238, 230, 225)',
			'#000',
		],
		[
			'trims token whitespace before pairing',
			{
				'--dsgo-overlay-menu-surface': ' #193341 ',
				'--wp--preset--color--base-2': ' #193341 ',
				'--wp--preset--color--contrast-2': ' #fff ',
			},
			'rgb(25, 51, 65)',
			'#fff',
		],
	])('%s', (name, overrides, background, foreground) => {
		const normalizeColor = jest.fn();
		expect(
			resolveOverlayMenuColors({
				token: tokenReader({ ...theme, ...overrides }),
				normalizeColor,
			})
		).toEqual({ background, foreground });
		expect(normalizeColor).not.toHaveBeenCalled();
	});

	it('defaults to opaque white and black when all tokens are missing', () => {
		expect(
			resolveOverlayMenuColors({
				token: tokenReader({}),
				normalizeColor: jest.fn(),
			})
		).toEqual({ background: 'rgb(255, 255, 255)', foreground: '#000' });
	});

	it.each(['#193341', 'rgb(25, 51, 65)', 'rgba(25, 51, 65, 0)'])(
		'makes %s opaque without browser normalization',
		(surface) => {
			const normalizeColor = jest.fn();
			expect(
				resolveOverlayMenuColors({
					token: tokenReader({
						'--dsgo-overlay-menu-surface': surface,
					}),
					normalizeColor,
				})
			).toEqual({ background: 'rgb(25, 51, 65)', foreground: '#fff' });
			expect(normalizeColor).not.toHaveBeenCalled();
		}
	);

	it.each([
		'hsl(202 44% 18% / .2)',
		'oklch(30% 0.04 240 / 0)',
		'#19334133',
		'var(--custom-surface)',
	])('uses supplied normalization for %s', (surface) => {
		const normalizeColor = jest.fn(() => ({ r: 25, g: 51, b: 65 }));
		expect(
			resolveOverlayMenuColors({
				token: tokenReader({ '--dsgo-overlay-menu-surface': surface }),
				normalizeColor,
			})
		).toEqual({ background: 'rgb(25, 51, 65)', foreground: '#fff' });
		expect(normalizeColor).toHaveBeenCalledTimes(1);
		expect(normalizeColor).toHaveBeenCalledWith(surface);
	});

	it('keeps the paired foreground for a browser-normalized surface', () => {
		const surface = 'oklch(30% 0.04 240)';
		expect(
			resolveOverlayMenuColors({
				token: tokenReader({
					...theme,
					'--dsgo-overlay-menu-surface': surface,
					'--wp--preset--color--base-2': surface,
					'--wp--preset--color--contrast-2': '#ffd700',
				}),
				normalizeColor: () => ({ r: 25, g: 51, b: 65 }),
			})
		).toEqual({ background: 'rgb(25, 51, 65)', foreground: '#ffd700' });
	});
});

// Mock conversion results here to test adapter wiring; Chromium checks verify
// actual browser conversion, which jsdom cannot provide for modern colors.
describe('overlay menu browser adapter', () => {
	let header;
	let probe;
	let canvas;

	beforeEach(() => {
		header = document.createElement('header');
		document.body.appendChild(header);
		canvas = jest.spyOn(window.HTMLCanvasElement.prototype, 'getContext');
	});

	afterEach(() => {
		header.remove();
		jest.restoreAllMocks();
	});

	function mockComputedColor(surface, color) {
		jest.spyOn(window, 'getComputedStyle').mockImplementation((element) => {
			if (element === header) {
				return {
					getPropertyValue: tokenReader({
						'--dsgo-overlay-menu-surface': surface,
					}),
				};
			}
			probe = element;
			expect(element.parentNode).toBe(header);
			return { color };
		});
	}

	function expectColors(background, foreground) {
		expect(header.style.getPropertyValue('--dsgo-overlay-menu-bg')).toBe(
			background
		);
		expect(header.style.getPropertyValue('--dsgo-overlay-menu-fg')).toBe(
			foreground
		);
		expect(probe.isConnected).toBe(false);
		expect(header.children).toHaveLength(0);
	}

	it('uses the probe result without canvas when the browser returns rgba', () => {
		mockComputedColor('hsl(210 50% 20% / .4)', 'rgba(26, 51, 77, 0.4)');
		applyOverlayMenuColors(header);
		expectColors('rgb(26, 51, 77)', '#fff');
		expect(canvas).not.toHaveBeenCalled();
	});

	it.each(['0', '0.2'])(
		'forces modern alpha %s to one before sampling',
		(alpha) => {
			const color = `color(srgb 0.1 0.2 0.3 / ${alpha})`;
			mockComputedColor(color, color);
			const context = {
				fillStyle: '',
				fillRect: jest.fn(),
				getImageData: jest.fn(() => ({ data: [26, 51, 77, 255] })),
			};
			canvas.mockReturnValue(context);
			applyOverlayMenuColors(header);
			expect(context.fillStyle).toBe('color(srgb 0.1 0.2 0.3 / 1)');
			expect(context.fillRect).toHaveBeenCalledWith(0, 0, 1, 1);
			expect(context.getImageData).toHaveBeenCalledWith(0, 0, 1, 1);
			expectColors('rgb(26, 51, 77)', '#fff');
		}
	);

	it('replaces stale colors with the white fallback when canvas is unavailable', () => {
		mockComputedColor('oklch(30% 0.04 240)', 'oklch(0.3 0.04 240)');
		header.style.setProperty('--dsgo-overlay-menu-bg', '#123456');
		header.style.setProperty('--dsgo-overlay-menu-fg', '#abcdef');
		canvas.mockReturnValue(null);
		applyOverlayMenuColors(header);
		expectColors('rgb(255, 255, 255)', '#000');
	});

	it('removes the probe even if canvas sampling throws', () => {
		mockComputedColor('oklch(30% 0.04 240)', 'oklch(0.3 0.04 240)');
		canvas.mockImplementation(() => {
			throw new Error('Canvas unavailable');
		});
		expect(() => applyOverlayMenuColors(header)).toThrow(
			'Canvas unavailable'
		);
		expect(probe.isConnected).toBe(false);
	});
});
