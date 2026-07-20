/**
 * Grid Block - deriveRowMatch (editor-side) Tests
 *
 * Covers the editor's "Align Rows" derivation: publishing the per-card row
 * count and gating activation on the previewed device's effective columns.
 * Mirrors the frontend view.js contract exercised in view.test.js.
 */

import {
	deriveRowMatch,
	effectiveColumnsForDevice,
} from '../utils/derive-row-match';

const COLS = { desktopColumns: 3, tabletColumns: 2, mobileColumns: 1 };

describe('effectiveColumnsForDevice', () => {
	const cols = { desktop: 3, tablet: 2, mobile: 1 };

	test('maps each device to its column count', () => {
		expect(effectiveColumnsForDevice('Desktop', cols)).toBe(3);
		expect(effectiveColumnsForDevice('Tablet', cols)).toBe(2);
		expect(effectiveColumnsForDevice('Mobile', cols)).toBe(1);
	});

	test('falls back to desktop for unknown device types', () => {
		expect(effectiveColumnsForDevice(undefined, cols)).toBe(3);
		expect(effectiveColumnsForDevice('Watch', cols)).toBe(3);
	});
});

describe('deriveRowMatch', () => {
	test('inactive when the toggle is off', () => {
		expect(
			deriveRowMatch({
				matchRowHeights: false,
				cardChildCounts: [4, 4, 4],
				deviceType: 'Desktop',
				...COLS,
			})
		).toEqual({ isActive: false, rowCount: 0 });
	});

	test('active on Desktop, publishing the max card row count', () => {
		expect(
			deriveRowMatch({
				matchRowHeights: true,
				cardChildCounts: [4, 4, 4],
				deviceType: 'Desktop',
				...COLS,
			})
		).toEqual({ isActive: true, rowCount: 4 });
	});

	test('row count is the max across uneven cards', () => {
		expect(
			deriveRowMatch({
				matchRowHeights: true,
				cardChildCounts: [3, 5, 4],
				deviceType: 'Desktop',
				...COLS,
			}).rowCount
		).toBe(5);
	});

	test('clears when the previewed device collapses to one column', () => {
		const result = deriveRowMatch({
			matchRowHeights: true,
			cardChildCounts: [4, 4, 4],
			deviceType: 'Mobile', // mobileColumns = 1
			...COLS,
		});
		expect(result.isActive).toBe(false);
		// rowCount is still reported; only activation is gated.
		expect(result.rowCount).toBe(4);
	});

	test('stays active on Tablet when it keeps 2 columns', () => {
		expect(
			deriveRowMatch({
				matchRowHeights: true,
				cardChildCounts: [4, 4],
				deviceType: 'Tablet',
				...COLS,
			}).isActive
		).toBe(true);
	});

	test('inactive when a single-column desktop grid has no cross-column alignment to do', () => {
		expect(
			deriveRowMatch({
				matchRowHeights: true,
				cardChildCounts: [4],
				deviceType: 'Desktop',
				desktopColumns: 1,
				tabletColumns: 1,
				mobileColumns: 1,
			}).isActive
		).toBe(false);
	});

	test('inactive when there are no cards / zero rows', () => {
		expect(
			deriveRowMatch({
				matchRowHeights: true,
				cardChildCounts: [],
				deviceType: 'Desktop',
				...COLS,
			})
		).toEqual({ isActive: false, rowCount: 0 });
	});
});
