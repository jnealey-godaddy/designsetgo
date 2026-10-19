/**
 * wasJustInserted Tests
 *
 * @package
 */

import { wasJustInserted } from '../../../src/hooks/useWasJustInserted';

/**
 * A `select` whose block-editor store recorded `inserted` (top-level client
 * ids) from an insertion with `source`, over a tree described by `parents`.
 *
 * @param {Object}           options
 * @param {string[]}         options.inserted Client ids core recorded.
 * @param {string|undefined} options.source   The insertion's meta source.
 * @param {Object}           options.parents  clientId → ancestor ids (root first).
 */
function selectWith({ inserted = [], source, parents = {} }) {
	const selectors = {
		wasBlockJustInserted: (id, s) => inserted.includes(id) && s === source,
		getBlockParents: (id) => parents[id] || [],
	};
	return () => selectors;
}

describe('wasJustInserted', () => {
	test('true for a block the web inserter just added (no source)', () => {
		expect(wasJustInserted(selectWith({ inserted: ['a'] }), 'a')).toBe(
			true
		);
	});

	test('true for a block inside a just-inserted tree', () => {
		const select = selectWith({
			inserted: ['root'],
			parents: { child: ['root', 'mid'] },
		});
		expect(wasJustInserted(select, 'child')).toBe(true);
	});

	test('true for the native inserter source', () => {
		const select = selectWith({ inserted: ['a'], source: 'inserter_menu' });
		expect(wasJustInserted(select, 'a')).toBe(true);
	});

	test('false for blocks loaded with the post (nothing recorded)', () => {
		const select = selectWith({ parents: { child: ['root'] } });
		expect(wasJustInserted(select, 'child')).toBe(false);
	});

	test('false for a block outside the last insertion', () => {
		const select = selectWith({
			inserted: ['other'],
			parents: { child: ['root'] },
		});
		expect(wasJustInserted(select, 'child')).toBe(false);
	});
});
