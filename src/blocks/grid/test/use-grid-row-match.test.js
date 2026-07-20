/**
 * Grid Block - useGridRowMatch Tests
 *
 * Guards the editor/frontend parity seam: the hook filters cards by BLOCK NAME
 * while the frontend (view.js) and CSS match by class. This test locks the
 * supported-name list so a block rename (like stack→section / flex→row) can't
 * silently break Align Rows in the editor while it still works on the frontend.
 */

let mockBlocks = [];
let mockDeviceType = 'Desktop';

jest.mock('@wordpress/data', () => ({
	useSelect: (mapSelect) => {
		const select = (storeName) => {
			if (storeName === 'core/editor') {
				return { getDeviceType: () => mockDeviceType };
			}
			// block-editor store (passed by reference as blockEditorStore)
			return { getBlock: () => ({ innerBlocks: mockBlocks }) };
		};
		return mapSelect(select);
	},
}));

jest.mock('@wordpress/block-editor', () => ({ store: 'core/block-editor' }));

import { useGridRowMatch } from '../utils/use-grid-row-match';

const COLS = { desktopColumns: 3, tabletColumns: 2, mobileColumns: 1 };

/**
 * Build fake card blocks.
 *
 * @param {Array} spec Card specs as `[blockName, childCount]` pairs.
 * @return {Array} Block objects with `name` + `innerBlocks`.
 */
function cards(spec) {
	return spec.map(([name, count]) => ({
		name,
		innerBlocks: Array.from({ length: count }, () => ({})),
	}));
}

function run(blocks, { deviceType = 'Desktop', matchRowHeights = true } = {}) {
	mockBlocks = blocks;
	mockDeviceType = deviceType;
	// useSelect is mocked to a synchronous function, so the hook is safe to
	// call directly here (no real React runtime involved).
	// eslint-disable-next-line react-hooks/rules-of-hooks
	return useGridRowMatch('grid-1', { matchRowHeights, ...COLS });
}

describe('useGridRowMatch - supported card block names', () => {
	test.each([
		['designsetgo/section', 4],
		['designsetgo/stack', 4], // legacy alias
		['designsetgo/row', 4],
		['designsetgo/flex', 4], // legacy alias
		['core/group', 4],
	])('counts %s cards (parity with the frontend classes)', (name, count) => {
		const result = run(cards([[name, count]]));
		expect(result).toEqual({
			isRowMatchActive: true,
			matchRowCount: count,
		});
	});

	test('current Row (Flex) cards activate — regression guard for stack→section / flex→row rename', () => {
		expect(
			run(
				cards([
					['designsetgo/row', 3],
					['designsetgo/row', 3],
				])
			)
		).toEqual({ isRowMatchActive: true, matchRowCount: 3 });
	});

	test('unsupported cards (e.g. Card block) contribute 0 and do not inflate', () => {
		const result = run(
			cards([
				['designsetgo/section', 4],
				['designsetgo/card', 9], // must not inflate to 9
			])
		);
		expect(result).toEqual({ isRowMatchActive: true, matchRowCount: 4 });
	});

	test('a grid of only unsupported cards does not activate', () => {
		expect(run(cards([['designsetgo/card', 5]]))).toEqual({
			isRowMatchActive: false,
			matchRowCount: 0,
		});
	});
});

describe('useGridRowMatch - gating', () => {
	test('off when the toggle is disabled', () => {
		expect(
			run(cards([['designsetgo/section', 4]]), { matchRowHeights: false })
		).toEqual({ isRowMatchActive: false, matchRowCount: 0 });
	});

	test('clears when the previewed device collapses to one column', () => {
		expect(
			run(cards([['designsetgo/section', 4]]), { deviceType: 'Mobile' })
		).toEqual({ isRowMatchActive: false, matchRowCount: 4 });
	});
});
