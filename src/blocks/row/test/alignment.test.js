import { getAlignItemsValue } from '../alignment';

describe('shared row alignment', () => {
	test.each([
		['top', 'flex-start'],
		['bottom', 'flex-end'],
		['center', 'center'],
		['stretch', 'stretch'],
		[undefined, undefined],
	])('%s resolves to %s in editor and save', (input, output) => {
		expect(getAlignItemsValue(input)).toBe(output);
	});
});
