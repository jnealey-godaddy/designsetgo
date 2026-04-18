/**
 * cssVars Tests
 *
 * @package
 */

import { cssVars } from '../../../src/utils/css-vars';

describe('cssVars', () => {
	test('maps attributes through convertColorToCSSVar by default', () => {
		const result = cssVars(
			{ bg: 'var:preset|color|accent-3', text: '#fff' },
			{
				'--dsgo-bg': 'bg',
				'--dsgo-text': 'text',
			}
		);
		expect(result).toEqual({
			'--dsgo-bg': 'var(--wp--preset--color--accent-3)',
			'--dsgo-text': '#fff',
		});
	});

	test('omits keys whose attribute value is undefined or empty string', () => {
		const result = cssVars(
			{ bg: '', text: undefined, border: '#000' },
			{
				'--dsgo-bg': 'bg',
				'--dsgo-text': 'text',
				'--dsgo-border': 'border',
			}
		);
		expect(result).toEqual({ '--dsgo-border': '#000' });
	});

	test('omits keys whose attribute value is null', () => {
		const result = cssVars(
			{ bg: null, text: '#000' },
			{
				'--dsgo-bg': 'bg',
				'--dsgo-text': 'text',
			}
		);
		expect(result).toEqual({ '--dsgo-text': '#000' });
	});

	test('honors custom converter via { attribute, convert } shape', () => {
		const px = (v) => (typeof v === 'number' ? `${v}px` : v);
		const result = cssVars(
			{ pad: 12, gap: 'var:preset|spacing|md' },
			{
				'--dsgo-pad': { attribute: 'pad', convert: px },
				'--dsgo-gap': { attribute: 'gap', convert: (v) => v },
			}
		);
		expect(result).toEqual({
			'--dsgo-pad': '12px',
			'--dsgo-gap': 'var:preset|spacing|md',
		});
	});

	test('returns empty object when no entries map', () => {
		const result = cssVars({}, { '--dsgo-bg': 'bg' });
		expect(result).toEqual({});
	});

	test('passes through 0 (falsy but valid) when convert is identity', () => {
		const result = cssVars(
			{ z: 0 },
			{ '--dsgo-z': { attribute: 'z', convert: (v) => v } }
		);
		expect(result).toEqual({ '--dsgo-z': 0 });
	});
});
