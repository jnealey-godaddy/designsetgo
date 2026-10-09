/**
 * Form Builder — convert between single-page and multi-step forms.
 *
 * @since 2.10.0
 */

import { createBlock, cloneBlock } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

export const STEP_BLOCK = 'designsetgo/form-step';

export function hasStepChildren(blocks) {
	return (blocks || []).some((block) => block.name === STEP_BLOCK);
}

/**
 * Wrap a form's fields into a single first step.
 *
 * @param {Array} blocks Field blocks.
 * @return {Array} One step block containing copies of the fields.
 */
export function splitIntoSteps(blocks) {
	return [
		createBlock(
			STEP_BLOCK,
			{ title: __('Step 1', 'designsetgo') },
			(blocks || []).map((block) => cloneBlock(block))
		),
	];
}

/**
 * Unwrap every step back into one ordered list of fields.
 *
 * @param {Array} blocks Step blocks (any non-step block is kept as is).
 * @return {Array} Field blocks.
 */
export function mergeSteps(blocks) {
	return (blocks || []).flatMap((block) =>
		block.name === STEP_BLOCK
			? (block.innerBlocks || []).map((child) => cloneBlock(child))
			: [cloneBlock(block)]
	);
}
