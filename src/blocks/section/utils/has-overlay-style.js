/**
 * Section overlay/hover style-variation detection.
 *
 * Thin Section-specific wrapper around the shared
 * `src/utils/style-variation-classes.js` detection, pinned to Section's own
 * `dsgo-stack` class prefix. See that module for the full behavior
 * description; kept here so Section's existing imports
 * (`./utils/has-overlay-style`) don't need to change.
 *
 * Used by both edit.js and save.js so the editor preview and saved markup stay
 * byte-identical.
 */

import {
	hasOverlayStyleClass as sharedHasOverlayStyleClass,
	hoverVariationClasses as sharedHoverVariationClasses,
} from '../../../utils/style-variation-classes';

export const hasOverlayStyleClass = sharedHasOverlayStyleClass;

/**
 * @param {string} [className] The block's `className` attribute value.
 * @return {string[]} Activation classes to add (possibly empty).
 */
export function hoverVariationClasses(className) {
	return sharedHoverVariationClasses(className, 'dsgo-stack');
}
