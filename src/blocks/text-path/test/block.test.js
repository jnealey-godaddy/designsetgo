import { existsSync } from 'fs';
import { resolve } from 'path';
import metadata from '../block.json';
import { v1 as legacyTextPath } from '../deprecated';

describe('text path block', () => {
	test('ships a block manifest', () => {
		expect(existsSync(resolve(__dirname, '../block.json'))).toBe(true);
	});

	test('ships a static save implementation', () => {
		expect(existsSync(resolve(__dirname, '../save.js'))).toBe(true);
	});

	test('persists custom path and link configuration', () => {
		expect(metadata.attributes).toEqual(
			expect.objectContaining({
				customPath: expect.any(Object),
				direction: expect.any(Object),
				url: expect.any(Object),
			})
		);
	});

	test('keeps the first saved markup version as a silent migration', () => {
		expect(existsSync(resolve(__dirname, '../deprecated.js'))).toBe(true);
		expect(
			legacyTextPath.isEligible({}, [], {
				innerHTML: '<textPath href="#legacy">Text</textPath>',
			})
		).toBe(true);
		expect(
			legacyTextPath.isEligible({}, [], {
				innerHTML:
					'<textPath data-dsgo-text-path-offset="0">Text</textPath>',
			})
		).toBe(false);
	});
});
