/**
 * justification Tests
 *
 * @package
 */

import {
	getJustificationClass,
	JUSTIFICATION_OPTIONS,
} from '../../../src/utils/justification';

describe('getJustificationClass', () => {
	test('maps each supported value to a dsgo-prefixed class', () => {
		expect(getJustificationClass('left')).toBe('dsgo-justify--left');
		expect(getJustificationClass('center')).toBe('dsgo-justify--center');
		expect(getJustificationClass('right')).toBe('dsgo-justify--right');
	});

	test('returns an empty string for unknown or missing values', () => {
		expect(getJustificationClass(undefined)).toBe('');
		expect(getJustificationClass('full')).toBe('');
		expect(getJustificationClass('')).toBe('');
	});

	test('exposes exactly the three supported options', () => {
		expect(JUSTIFICATION_OPTIONS.map((o) => o.value)).toEqual([
			'left',
			'center',
			'right',
		]);
	});
});
