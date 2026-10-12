/**
 * Form Builder — revealing a step below a sticky header.
 */
import {
	topObstruction,
	revealBelowHeader,
} from '../../../src/blocks/form-builder/utils/reveal-below-header';

function box(top, bottom) {
	return {
		getBoundingClientRect: () => ({ top, bottom, height: bottom - top }),
	};
}

/**
 * A fake window: `bars` are the elements found at the top edge, each with a
 * position and a rect.
 * @param {Object[]} bars
 */
function fakeWindow(bars) {
	const body = { parentElement: null };
	const doc = {
		body,
		documentElement: { clientWidth: 1200 },
		elementsFromPoint: (x, y) =>
			bars
				.filter((bar) => bar.top <= y && y < bar.bottom)
				.map((bar) => ({
					...box(bar.top, bar.bottom),
					parentElement: body,
					position: bar.position,
				})),
	};
	return {
		document: doc,
		innerWidth: 1200,
		innerHeight: 800,
		getComputedStyle: (node) => ({ position: node.position || 'static' }),
		scrollBy: jest.fn(),
	};
}

describe('topObstruction', () => {
	it('measures a sticky header covering the top edge', () => {
		expect(
			topObstruction(
				fakeWindow([{ position: 'sticky', top: 0, bottom: 103 }])
			)
		).toBe(103);
	});

	it('adds up stacked bars, like the admin bar above a sticky header', () => {
		expect(
			topObstruction(
				fakeWindow([
					{ position: 'fixed', top: 0, bottom: 32 },
					{ position: 'sticky', top: 32, bottom: 102 },
				])
			)
		).toBe(102);
	});

	it('ignores static content and tall overlays', () => {
		expect(
			topObstruction(
				fakeWindow([
					{ position: 'static', top: 0, bottom: 300 },
					{ position: 'fixed', top: 0, bottom: 700 },
				])
			)
		).toBe(0);
	});

	it('returns 0 where elementsFromPoint is unavailable', () => {
		const win = fakeWindow([]);
		delete win.document.elementsFromPoint;
		expect(topObstruction(win)).toBe(0);
	});
});

describe('revealBelowHeader', () => {
	it('scrolls the progress row out from under a sticky header', () => {
		const win = fakeWindow([{ position: 'sticky', top: 0, bottom: 103 }]);
		revealBelowHeader(box(30, 60), box(110, 140), win);
		// 30 → 103 + 16 gap.
		expect(win.scrollBy).toHaveBeenCalledWith({
			top: 30 - 119,
			left: 0,
			behavior: 'auto',
		});
	});

	it('scrolls up to the progress row when the heading is below the fold', () => {
		const win = fakeWindow([]);
		revealBelowHeader(box(-400, -370), box(-320, -290), win);
		expect(win.scrollBy).toHaveBeenCalledWith({
			top: -416,
			left: 0,
			behavior: 'auto',
		});
	});

	it('leaves the page alone when both are already visible', () => {
		const win = fakeWindow([{ position: 'sticky', top: 0, bottom: 103 }]);
		revealBelowHeader(box(200, 230), box(260, 290), win);
		expect(win.scrollBy).not.toHaveBeenCalled();
	});

	it('scrolls when the heading has dropped below the viewport', () => {
		const win = fakeWindow([]);
		revealBelowHeader(box(500, 530), box(820, 850), win);
		expect(win.scrollBy).toHaveBeenCalledWith({
			top: 484,
			left: 0,
			behavior: 'auto',
		});
	});
});
