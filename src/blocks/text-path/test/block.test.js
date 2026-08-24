import { existsSync, readFileSync } from 'fs';
import { resolve } from 'path';
import metadata from '../block.json';
import { v1 as legacyTextPath } from '../deprecated';
import { findFirstTextPathClientId } from '../utils';

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
		expect(legacyTextPath.apiVersion).toBe(3);
		expect(
			legacyTextPath.isEligible({}, [], {
				blockNode: {
					innerHTML: '<textPath href="#legacy">Text</textPath>',
				},
			})
		).toBe(true);
		expect(
			legacyTextPath.isEligible({}, [], {
				blockNode: {
					innerHTML:
						'<textPath data-dsgo-text-path-offset="0">Text</textPath>',
				},
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

	test('opts the animated SVG out of browser scroll anchoring', () => {
		const styles = readFileSync(
			resolve(__dirname, '../style.scss'),
			'utf8'
		);

		expect(styles).toContain('overflow-anchor: none;');
	});

	test('keeps the first Text Path block as the owner of a duplicated path ID', () => {
		// getClientIdsWithDescendants() returns document order, so a nested
		// duplicate still resolves to the block that appears first.
		const blocks = {
			first: {
				name: 'designsetgo/text-path',
				attributes: { uniqueId: 'shared-path' },
			},
			group: { name: 'core/group', attributes: {} },
			duplicate: {
				name: 'designsetgo/text-path',
				attributes: { uniqueId: 'shared-path' },
			},
		};
		const selectors = {
			getBlockName: (clientId) => blocks[clientId]?.name,
			getBlockAttributes: (clientId) => blocks[clientId]?.attributes,
		};

		expect(
			findFirstTextPathClientId(
				['first', 'group', 'duplicate'],
				'shared-path',
				selectors
			)
		).toBe('first');
		expect(
			findFirstTextPathClientId(
				['group', 'duplicate', 'first'],
				'shared-path',
				selectors
			)
		).toBe('duplicate');
		expect(findFirstTextPathClientId(['first'], '', selectors)).toBeNull();
		expect(
			findFirstTextPathClientId(['group'], 'shared-path', selectors)
		).toBeNull();
	});

	test('scans a flat client-ID list rather than the whole block tree', () => {
		const editSource = readFileSync(
			resolve(__dirname, '../edit.js'),
			'utf8'
		);

		// getBlocks() rebuilds every block on every store change, so a scan
		// over it re-walks the document on each keystroke and hands back a new
		// reference that re-renders this block too.
		expect(editSource).not.toContain('getBlocks()');
		expect(editSource).toContain('getClientIdsWithDescendants()');
		expect(editSource).toContain('if (!attributes.uniqueId)');
		expect(editSource).toContain('return false;');
	});

	test('stops scanning at the first owner it finds', () => {
		const visited = [];
		const selectors = {
			getBlockName: (clientId) => {
				visited.push(clientId);
				return 'designsetgo/text-path';
			},
			getBlockAttributes: () => ({ uniqueId: 'shared-path' }),
		};

		findFirstTextPathClientId(['a', 'b', 'c'], 'shared-path', selectors);

		expect(visited).toEqual(['a']);
	});
});
