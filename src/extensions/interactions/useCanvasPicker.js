/**
 * Interaction Layers - Canvas target picker
 *
 * Lets the author click a block in the editor canvas instead of typing a CSS
 * selector. While picking, the modal hides itself so the canvas is reachable;
 * Escape or a click outside any block cancels.
 *
 * The canvas is an iframe in current WordPress, so every listener goes on the
 * iframe's own document, not the panel's.
 *
 * @package
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { hasBlockSupport } from '@wordpress/blocks';
import { deriveSelector } from './derive-selector';

const HIGHLIGHT_CLASS = 'dsgo-interaction-picking';

/**
 * Resolve the editor canvas document.
 *
 * @return {Document} The canvas document, or the current one as a fallback.
 */
function canvasDocument() {
	return (
		document.querySelector('iframe[name="editor-canvas"]')
			?.contentDocument || document
	);
}

/**
 * Canvas picker state machine.
 *
 * @param {Function} onPick Called with the derived selector string.
 * @return {{isPicking: boolean, startPicking: Function, cancelPicking: Function}} Picker controls.
 */
export function useCanvasPicker(onPick) {
	const [isPicking, setIsPicking] = useState(false);
	const hovered = useRef(null);

	// The block the picker was launched from. Restored after picking so the
	// inspector keeps showing this block's panel and the modal survives.
	const origin = useRef(null);

	const { getBlock, getSelectedBlockClientId } = useSelect(
		(select) => ({
			getBlock: select(blockEditorStore).getBlock,
			getSelectedBlockClientId:
				select(blockEditorStore).getSelectedBlockClientId,
		}),
		[]
	);
	const { updateBlockAttributes, selectBlock } =
		useDispatch(blockEditorStore);

	const clearHighlight = useCallback(() => {
		hovered.current?.classList.remove(HIGHLIGHT_CLASS);
		hovered.current = null;
	}, []);

	const restoreSelection = useCallback(() => {
		if (origin.current && getSelectedBlockClientId() !== origin.current) {
			selectBlock(origin.current);
		}
	}, [getSelectedBlockClientId, selectBlock]);

	const cancelPicking = useCallback(() => {
		clearHighlight();
		restoreSelection();
		setIsPicking(false);
	}, [clearHighlight, restoreSelection]);

	const startPicking = useCallback(() => {
		origin.current = getSelectedBlockClientId();
		setIsPicking(true);
	}, [getSelectedBlockClientId]);

	useEffect(() => {
		if (!isPicking) {
			return undefined;
		}

		const doc = canvasDocument();

		const blockAt = (target) =>
			target?.closest ? target.closest('[data-block]') : null;

		const onMove = (e) => {
			const el = blockAt(e.target);
			if (el === hovered.current) {
				return;
			}
			clearHighlight();
			if (el) {
				el.classList.add(HIGHLIGHT_CLASS);
				hovered.current = el;
			}
		};

		// The editor selects a block on mousedown, long before click fires.
		// A selection change re-renders the inspector for the newly selected
		// block, which unmounts the panel this picker was launched from and
		// destroys the modal mid-edit. Swallowing the whole pointer sequence
		// keeps the original block selected and the modal alive.
		const swallow = (e) => {
			if (!blockAt(e.target)) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			if (e.stopImmediatePropagation) {
				e.stopImmediatePropagation();
			}
		};

		const onClick = (e) => {
			const el = blockAt(e.target);
			if (!el) {
				return;
			}
			swallow(e);

			const block = getBlock(el.getAttribute('data-block'));
			const supportsClassName = block
				? hasBlockSupport(block.name, 'customClassName', true)
				: true;

			onPick(
				deriveSelector(block, updateBlockAttributes, supportsClassName),
				{ block, supportsClassName }
			);
			cancelPicking();
		};

		const onKey = (e) => {
			if ('Escape' === e.key) {
				e.preventDefault();
				cancelPicking();
			}
		};

		const SWALLOWED = [
			'pointerdown',
			'mousedown',
			'pointerup',
			'mouseup',
			'focusin',
			'dragstart',
		];

		doc.addEventListener('mousemove', onMove, true);
		doc.addEventListener('click', onClick, true);
		doc.addEventListener('keydown', onKey, true);
		SWALLOWED.forEach((type) => doc.addEventListener(type, swallow, true));
		// Escape must work while focus is still in the panel, not the canvas.
		document.addEventListener('keydown', onKey, true);

		return () => {
			doc.removeEventListener('mousemove', onMove, true);
			doc.removeEventListener('click', onClick, true);
			doc.removeEventListener('keydown', onKey, true);
			SWALLOWED.forEach((type) =>
				doc.removeEventListener(type, swallow, true)
			);
			document.removeEventListener('keydown', onKey, true);
			clearHighlight();
		};
	}, [
		isPicking,
		getBlock,
		updateBlockAttributes,
		onPick,
		cancelPicking,
		clearHighlight,
	]);

	return { isPicking, startPicking, cancelPicking };
}
