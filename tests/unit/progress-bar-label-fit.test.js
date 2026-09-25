/**
 * Progress Bar — insideLabelFits().
 *
 * jsdom has no layout, so the fill's width, the computed paddings and the
 * label text's Range rect are stubbed. Those are exactly the values the
 * helper reads.
 */

import { insideLabelFits } from '../../src/blocks/progress-bar/utils/label-fit';

function stub({ fillWidth, textWidth, fillPad = 8, labelPad = 8 }) {
	const fill = document.createElement('div');
	const label = document.createElement('div');
	label.textContent = 'Skill 12 - 12%';
	fill.appendChild(label);
	document.body.appendChild(fill);

	Object.defineProperty(fill, 'clientWidth', { value: fillWidth });
	jest.spyOn(window, 'getComputedStyle').mockImplementation((el) =>
		el === fill
			? { paddingLeft: `${fillPad}px`, paddingRight: `${fillPad}px` }
			: { paddingLeft: '0px', paddingRight: `${labelPad}px` }
	);
	jest.spyOn(document, 'createRange').mockReturnValue({
		selectNodeContents: () => {},
		getBoundingClientRect: () => ({ width: textWidth }),
	});

	return { fill, label };
}

describe('progress-bar insideLabelFits', () => {
	afterEach(() => {
		jest.restoreAllMocks();
		document.body.innerHTML = '';
	});

	it('fits when the text and its padding fit the fill content box', () => {
		// 16px fill padding + 8px label padding + 80px text = 104px.
		const { fill, label } = stub({ fillWidth: 104, textWidth: 80 });
		expect(insideLabelFits(fill, label)).toBe(true);
	});

	it('does not fit when the fill is narrower than the text needs', () => {
		const { fill, label } = stub({ fillWidth: 100, textWidth: 80 });
		expect(insideLabelFits(fill, label)).toBe(false);
	});

	it('allows half a pixel of rounding at the boundary', () => {
		const { fill, label } = stub({ fillWidth: 104, textWidth: 80.4 });
		expect(insideLabelFits(fill, label)).toBe(true);
	});
});
