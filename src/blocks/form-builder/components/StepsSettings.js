/**
 * Form Builder — "Steps" item in the form's Settings panel.
 *
 * @since 2.10.0
 */

import { __ } from '@wordpress/i18n';
import { Button, SelectControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { DsgoInspectorPanel } from '../../../components/shared';
import { hasStepChildren, splitIntoSteps, mergeSteps } from '../utils/steps';

export default function StepsSettings({
	clientId,
	childBlocks,
	attributes,
	setAttributes,
}) {
	const { replaceInnerBlocks } = useDispatch('core/block-editor');
	const hasSteps = hasStepChildren(childBlocks);
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
				<>
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
				</>
			) : (
				<>
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
				</>
			)}
		</DsgoInspectorPanel.Item>
	);
}
