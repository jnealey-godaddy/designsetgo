import { existsSync, readFileSync } from 'fs';
import { resolve } from 'path';
import metadata from '../block.json';
import { v1 as legacyTextPath } from '../deprecated';
import { findFirstTextPathBlockClientId } from '../utils';

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

	test('keeps link target spacing outside the canvas-only editor selector', () => {
		const editorStyles = readFileSync(
			resolve(__dirname, '../editor.scss'),
			'utf8'
		);

		expect(editorStyles).toContain('\n.dsgo-text-path__link-target {');
		expect(editorStyles).not.toContain(
			'\n\t.dsgo-text-path__link-target {'
		);
	});

	test('keeps the first Text Path block as the owner of a duplicated path ID', () => {
		const firstClientId = findFirstTextPathBlockClientId(
			[
				{
					clientId: 'first',
					name: 'designsetgo/text-path',
					attributes: { uniqueId: 'shared-path' },
				},
				{
					clientId: 'group',
					name: 'core/group',
					innerBlocks: [
						{
							clientId: 'duplicate',
							name: 'designsetgo/text-path',
							attributes: { uniqueId: 'shared-path' },
						},
					],
				},
			],
			'shared-path'
		);

		expect(firstClientId).toBe('first');
	});
});
