/**
 * Form Builder — "Steps" item in the form's Settings panel.
 *
 * @since 2.10.0
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Notice,
	SelectControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { DsgoInspectorPanel } from '../../../components/shared';
import {
	hasStepChildren,
	splitIntoSteps,
	mergeSteps,
	STEP_BLOCK,
} from '../utils/steps';

export default function StepsSettings({
	clientId,
	childBlocks,
	attributes,
	setAttributes,
}) {
	const { replaceInnerBlocks, moveBlocksToPosition } =
		useDispatch('core/block-editor');
	const hasSteps = hasStepChildren(childBlocks);
	// Fields left directly in a form that has steps show on every step.
	// Only a form edited before steps owned their fields can have them.
	const strays = hasSteps
		? childBlocks.filter((block) => block.name !== STEP_BLOCK)
		: [];
	const lastStep = [...childBlocks]
		.reverse()
		.find((block) => block.name === STEP_BLOCK);
	const moveStrays = () =>
		moveBlocksToPosition(
			strays.map((block) => block.clientId),
			clientId,
			lastStep.clientId,
			lastStep.innerBlocks.length
		);
	const { stepProgress } = attributes;

	const split = () => {
		replaceInnerBlocks(clientId, splitIntoSteps(childBlocks), false);
		if (attributes.submitButtonPosition === 'inline') {
			setAttributes({ submitButtonPosition: 'below' });
		}
	};
	const merge = () =>
		replaceInnerBlocks(clientId, mergeSteps(childBlocks), false);

	return (
		<DsgoInspectorPanel.Item
			label={__('Steps', 'designsetgo')}
			hasValue={() => hasSteps && stepProgress !== 'steps'}
			onDeselect={() => setAttributes({ stepProgress: 'steps' })}
			isShownByDefault
		>
			{hasSteps ? (
				// Spacing between the select and the button; the select drops
				// its own bottom margin (__nextHasNoMarginBottom).
				<VStack spacing={3}>
					{strays.length > 0 && (
						<Notice status="warning" isDismissible={false}>
							<p>
								{sprintf(
									/* translators: %d: number of fields outside the form's steps. */
									_n(
										'%d field sits outside the steps, so visitors see it on every step.',
										'%d fields sit outside the steps, so visitors see them on every step.',
										strays.length,
										'designsetgo'
									),
									strays.length
								)}
							</p>
							<Button
								variant="secondary"
								onClick={moveStrays}
								__next40pxDefaultSize
							>
								{__('Move into the last step', 'designsetgo')}
							</Button>
						</Notice>
					)}
					<SelectControl
						label={__('Progress indicator', 'designsetgo')}
						value={stepProgress}
						options={[
							{
								value: 'steps',
								label: __('Steps', 'designsetgo'),
							},
							{
								value: 'bar',
								label: __('Progress bar', 'designsetgo'),
							},
							{ value: 'none', label: __('None', 'designsetgo') },
						]}
						onChange={(value) =>
							setAttributes({ stepProgress: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						onClick={merge}
						__next40pxDefaultSize
					>
						{__('Merge into one page', 'designsetgo')}
					</Button>
				</VStack>
			) : (
				<VStack spacing={3}>
					<p>
						{__(
							'Split a long form into steps that visitors fill in one at a time.',
							'designsetgo'
						)}
					</p>
					<Button
						variant="secondary"
						onClick={split}
						__next40pxDefaultSize
					>
						{__('Split into steps', 'designsetgo')}
					</Button>
				</VStack>
			)}
		</DsgoInspectorPanel.Item>
	);
}
