/**
 * Form Builder — "Add step" in the toolbar of a multi-step form.
 *
 * A form with steps holds only steps, so it has no canvas appender (that
 * "+" sat on the last step's own field "+" and offered field blocks). Steps
 * are added here, at the end, or from a step's toolbar, after that step.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import {
	BlockControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { createBlock } from '@wordpress/blocks';
import { useDispatch } from '@wordpress/data';
import { plus } from '@wordpress/icons';
import { STEP_BLOCK } from '../utils/steps';

/**
 * @param {Object} props            Component props.
 * @param {string} props.clientId   Form block client ID.
 * @param {number} props.childCount Number of blocks directly in the form.
 * @return {JSX.Element} Toolbar group.
 */
export default function AddStepToolbar({ clientId, childCount }) {
	const { insertBlock } = useDispatch(blockEditorStore);

	return (
		<BlockControls group="other">
			<ToolbarGroup>
				<ToolbarButton
					icon={plus}
					label={__('Add step', 'designsetgo')}
					onClick={() =>
						insertBlock(
							createBlock(STEP_BLOCK, { title: '' }),
							childCount,
							clientId,
							false
						)
					}
					showTooltip
				/>
			</ToolbarGroup>
		</BlockControls>
	);
}
