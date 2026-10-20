/**
 * Phone field formatter tests.
 *
 * @package
 */

import {
	formatInternational,
	formatPhoneNumber,
} from '../../../src/blocks/form-phone-field/view';

jest.mock('../../../src/blocks/form-phone-field/country-codes', () => []);

describe('formatInternational', () => {
	test.each([
		['+44 20 7946 0958', '+44-20-7946-0958'],
		['+1.555.123.4567', '+1-555-123-4567'],
		['+1 (555) 123-4567', '+1-555-123-4567'],
		['44  20--7946', '+44-20-7946'],
		['+4915112345678', '+4915112345678'],
		['', ''],
		['+', ''],
	])('%s → %s', (typed, expected) => {
		expect(formatInternational(typed, true)).toBe(expected);
	});

	test('keeps a trailing dash while typing so the next group can follow', () => {
		expect(formatInternational('+44 ')).toBe('+44-');
		expect(formatInternational('+44 ', true)).toBe('+44');
	});

	test('caps at 15 digits', () => {
		expect(formatInternational('+1234567890123456789', true)).toBe(
			'+123456789012345'
		);
	});

	test('output matches the field pattern', () => {
		const pattern = new RegExp(
			'^(?:(?=(?:[^0-9]*[0-9]){7,15}[^0-9]*$)\\+[0-9\\s.\\-]+)$',
			'v'
		);
		expect(
			pattern.test(formatInternational('+44 20 7946 0958', true))
		).toBe(true);
	});
});

describe('formatPhoneNumber', () => {
	test('US keeps its (555) 123-4567 shape', () => {
		expect(formatPhoneNumber('5551234567', 'us')).toBe('(555) 123-4567');
	});

	test('international delegates to the dash formatter', () => {
		expect(
			formatPhoneNumber('+44 20 7946 0958', 'international', true)
		).toBe('+44-20-7946-0958');
	});
});

describe('national number (country-code select shown)', () => {
	test('does not force a leading "+"', () => {
		expect(formatInternational('20 7946 0958', true, false)).toBe(
			'20-7946-0958'
		);
		expect(
			formatPhoneNumber('20 7946 0958', 'international', true, false)
		).toBe('20-7946-0958');
	});

	test('still forces "+" by default', () => {
		expect(formatPhoneNumber('20 7946 0958', 'international', true)).toBe(
			'+20-7946-0958'
		);
	});
});
