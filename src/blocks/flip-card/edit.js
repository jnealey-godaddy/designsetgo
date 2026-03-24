/**
 * Flip Card Block - Edit Component
 *
 * Interactive card that flips to reveal content on the back.
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Flip Card Edit Component
 *
 * Uses a template with two child blocks (flip-card-front and flip-card-back).
 * Each child block can contain any blocks the user wants to add.
 *
 * @param {Object}   props               Component props
 * @param {Object}   props.attributes    Block attributes
 * @param {Function} props.setAttributes Function to update attributes
 * @param {string}   props.clientId      Block client ID
 * @return {JSX.Element} Edit component
 */
export default function FlipCardEdit({ attributes, setAttributes, clientId }) {
	const { flipTrigger, flipEffect, flipDirection, flipDuration } = attributes;

	// Determine which face blocks already exist to prevent duplicates
	const allowedBlocks = useSelect(
		(select) => {
			const { getBlock } = select('core/block-editor');
			const block = getBlock(clientId);
			const childNames = (block?.innerBlocks || []).map(
				(child) => child.name
			);
			const missing = [];
			if (!childNames.includes('designsetgo/flip-card-front')) {
				missing.push('designsetgo/flip-card-front');
			}
			if (!childNames.includes('designsetgo/flip-card-back')) {
				missing.push('designsetgo/flip-card-back');
			}
			return missing;
		},
		[clientId]
	);

	// Block wrapper props
	const blockProps = useBlockProps({
		className: 'dsgo-flip-card',
		style: {
			'--dsgo-flip-duration': flipDuration,
			width: '100%',
		},
	});

	// Inner blocks configuration
	// Template seeds front and back faces on first insert.
	// templateLock is false so users can delete a face and re-add it,
	// which also allows "Attempt Recovery" to work on validation errors.
	// allowedBlocks is computed dynamically to only permit missing faces,
	// preventing duplicate front or back blocks.
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-flip-card__container',
		},
		{
			allowedBlocks,
			template: [
				['designsetgo/flip-card-front', {}],
				['designsetgo/flip-card-back', {}],
			],
			templateLock: false,
			orientation: 'vertical',
		}
	);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Flip Card Settings', 'designsetgo')}
					initialOpen={true}
				>
					<SelectControl
						label={__('Flip Trigger', 'designsetgo')}
						value={flipTrigger}
						options={[
							{
								label: __('Hover', 'designsetgo'),
								value: 'hover',
							},
							{
								label: __('Click', 'designsetgo'),
								value: 'click',
							},
						]}
						onChange={(value) =>
							setAttributes({ flipTrigger: value })
						}
						help={
							flipTrigger === 'hover'
								? __(
										'Card flips when hovering over it',
										'designsetgo'
									)
								: __(
										'Card flips when clicking on it',
										'designsetgo'
									)
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Flip Effect', 'designsetgo')}
						value={flipEffect}
						options={[
							{
								label: __('Flip (3D Rotation)', 'designsetgo'),
								value: 'flip',
							},
							{
								label: __('Fade', 'designsetgo'),
								value: 'fade',
							},
							{
								label: __('Slide', 'designsetgo'),
								value: 'slide',
							},
							{
								label: __('Zoom', 'designsetgo'),
								value: 'zoom',
							},
						]}
						onChange={(value) =>
							setAttributes({ flipEffect: value })
						}
						help={__(
							'Choose the transition animation style',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					{flipEffect === 'flip' && (
						<SelectControl
							label={__('Flip Direction', 'designsetgo')}
							value={flipDirection}
							options={[
								{
									label: __('Horizontal', 'designsetgo'),
									value: 'horizontal',
								},
								{
									label: __('Vertical', 'designsetgo'),
									value: 'vertical',
								},
							]}
							onChange={(value) =>
								setAttributes({ flipDirection: value })
							}
							help={
								flipDirection === 'horizontal'
									? __(
											'Card flips left to right',
											'designsetgo'
										)
									: __(
											'Card flips top to bottom',
											'designsetgo'
										)
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					)}

					<UnitControl
						label={__('Flip Duration', 'designsetgo')}
						value={flipDuration}
						onChange={(value) =>
							setAttributes({ flipDuration: value || '0.6s' })
						}
						units={[
							{ value: 's', label: 's', default: 0.6 },
							{ value: 'ms', label: 'ms', default: 600 },
						]}
						min={0.1}
						max={3}
						step={0.1}
						help={__('Speed of the flip animation', 'designsetgo')}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={__('How to Use', 'designsetgo')}
					initialOpen={false}
				>
					<p>
						{__(
							'This flip card has two sections: Front and Back. Click on each section to edit its content.',
							'designsetgo'
						)}
					</p>
					<p>
						{__(
							'You can add any blocks you want to the front and back, including images, headings, buttons, and more.',
							'designsetgo'
						)}
					</p>
					<p>
						{__(
							'Style each section using the block toolbar or the Styles panel.',
							'designsetgo'
						)}
					</p>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		</>
	);
}
