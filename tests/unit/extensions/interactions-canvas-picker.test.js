/* global MouseEvent, KeyboardEvent */

import { render, screen, fireEvent, act } from '@testing-library/react';
import { useCanvasPicker } from '../../../src/extensions/interactions/useCanvasPicker';

const mockGetBlock = jest.fn();
const mockGetSelectedBlockClientId = jest.fn(() => 'origin-block');
const mockUpdateBlockAttributes = jest.fn();
const mockSelectBlock = jest.fn();

jest.mock('@wordpress/data', () => ({
	useSelect: (mapper) =>
		mapper(() => ({
			getBlock: (...a) => mockGetBlock(...a),
			getSelectedBlockClientId: (...a) =>
				mockGetSelectedBlockClientId(...a),
		})),
	useDispatch: () => ({
		updateBlockAttributes: (...a) => mockUpdateBlockAttributes(...a),
		selectBlock: (...a) => mockSelectBlock(...a),
	}),
}));

jest.mock('@wordpress/block-editor', () => ({ store: 'core/block-editor' }));

function Harness({ onPick }) {
	const { isPicking, startPicking } = useCanvasPicker(onPick);
	return (
		<button onClick={startPicking}>{isPicking ? 'picking' : 'idle'}</button>
	);
}

/**
 * Dispatch the pointer sequence a real browser produces, in order.
 *
 * @param {Element} el Element to click.
 * @return {Object} Each event, so callers can assert defaultPrevented.
 */
function realClick(el) {
	const make = (type) =>
		new MouseEvent(type, { bubbles: true, cancelable: true });
	const events = {
		mousedown: make('mousedown'),
		mouseup: make('mouseup'),
		click: make('click'),
	};
	el.dispatchEvent(events.mousedown);
	el.dispatchEvent(events.mouseup);
	el.dispatchEvent(events.click);
	return events;
}

describe('useCanvasPicker', () => {
	beforeEach(() => {
		jest.clearAllMocks();
		mockGetSelectedBlockClientId.mockReturnValue('origin-block');
		mockGetBlock.mockReturnValue({
			clientId: 'target-block',
			attributes: { anchor: 'hero' },
		});
		document.body.innerHTML = `
			<div data-block="target-block" class="wp-block-group">
				<p>text</p>
			</div>
		`;
	});

	const start = () => {
		render(<Harness onPick={jest.fn()} />);
		fireEvent.click(screen.getByRole('button'));
		return screen.getByRole('button');
	};

	it('enters picking mode', () => {
		const btn = start();
		expect(btn).toHaveTextContent('picking');
	});

	it('swallows mousedown so the editor cannot change the selection', () => {
		start();
		const block = document.querySelector('[data-block]');
		let events;
		act(() => {
			events = realClick(block);
		});

		// This is the regression: the editor selects a block on mousedown,
		// which unmounts the inspector panel the picker was launched from and
		// destroys the modal mid-edit. mousedown must never reach the editor.
		expect(events.mousedown.defaultPrevented).toBe(true);
	});

	it('picks the block and reports its selector', () => {
		const onPick = jest.fn();
		render(<Harness onPick={onPick} />);
		fireEvent.click(screen.getByRole('button'));

		act(() => {
			realClick(document.querySelector('[data-block]'));
		});

		expect(onPick).toHaveBeenCalledWith('#hero');
	});

	it('leaves picking mode after a pick so the editor can reopen', () => {
		const btn = start();
		act(() => {
			realClick(document.querySelector('[data-block]'));
		});
		expect(btn).toHaveTextContent('idle');
	});

	it('restores the originating selection if it drifted', () => {
		start();
		// Something else selected a different block mid-pick.
		mockGetSelectedBlockClientId.mockReturnValue('some-other-block');

		act(() => {
			realClick(document.querySelector('[data-block]'));
		});

		expect(mockSelectBlock).toHaveBeenCalledWith('origin-block');
	});

	it('does not re-select when the selection never moved', () => {
		start();
		act(() => {
			realClick(document.querySelector('[data-block]'));
		});
		expect(mockSelectBlock).not.toHaveBeenCalled();
	});

	it('ignores pointer events outside any block', () => {
		const onPick = jest.fn();
		render(<Harness onPick={onPick} />);
		fireEvent.click(screen.getByRole('button'));

		const outside = document.createElement('div');
		document.body.appendChild(outside);
		const events = realClick(outside);

		expect(onPick).not.toHaveBeenCalled();
		// Clicks off-block stay live so the author can reach the Cancel button.
		expect(events.mousedown.defaultPrevented).toBe(false);
	});

	it('cancels on Escape', () => {
		const btn = start();
		act(() => {
			document.dispatchEvent(
				new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })
			);
		});
		expect(btn).toHaveTextContent('idle');
	});

	it('highlights the hovered block and clears it on exit', () => {
		start();
		const block = document.querySelector('[data-block]');

		act(() => {
			block.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));
		});
		expect(block.classList.contains('dsgo-interaction-picking')).toBe(true);

		act(() => {
			document.dispatchEvent(
				new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })
			);
		});
		expect(block.classList.contains('dsgo-interaction-picking')).toBe(
			false
		);
	});
});
