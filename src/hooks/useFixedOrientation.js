/**
 * useFixedOrientation
 *
 * Keeps a flex container on the one orientation its block stands for:
 * Section is vertical, Row is horizontal. Both hide WordPress's orientation
 * toggle (`allowOrientation: false`), so a layout stored with the other
 * orientation (content from before the transforms set it, or markup written
 * outside the editor) would otherwise be stuck. This replaces the old
 * behaviour of swapping the whole block, which dropped most of its settings.
 *
 * The fix is not added to the undo stack: it corrects stored data rather
 * than recording something the author did.
 */

import { useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

/**
 * @param {Object|undefined} layout        The block's layout attribute.
 * @param {string}           orientation   'horizontal' or 'vertical'.
 * @param {Function}         setAttributes Block setAttributes.
 */
export function useFixedOrientation(layout, orientation, setAttributes) {
	const { __unstableMarkNextChangeAsNotPersistent: markNotPersistent } =
		useDispatch(blockEditorStore);
	const stored = layout?.orientation;

	useEffect(() => {
		if (stored && stored !== orientation) {
			markNotPersistent();
			setAttributes({ layout: { ...layout, orientation } });
		}
		// Only the stored orientation decides whether there's anything to fix.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [stored, orientation]);
}
