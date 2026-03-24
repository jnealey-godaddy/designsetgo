/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './editor.scss';

/**
 * Default overlay color applied when a slide gets a background image.
 */
const DEFAULT_OVERLAY_COLOR = '#111111';

export default function Edit({ attributes, setAttributes, clientId }) {
	const { navHeading } = attributes;

	const hasBackgroundImage =
		!!attributes?.style?.background?.backgroundImage?.url;

	const { parentClientId, parentOverlayColor } = useSelect(
		(select) => {
			const { getBlockParentsByBlockName, getBlockAttributes } =
				select('core/block-editor');
			const parents = getBlockParentsByBlockName(
				clientId,
				'designsetgo/scroll-slides'
			);
			const parentId = parents[0] || null;
			const parentAttrs = parentId ? getBlockAttributes(parentId) : null;
			return {
				parentClientId: parentId,
				parentOverlayColor: parentAttrs?.overlayColor || '',
			};
		},
		[clientId]
	);

	const { updateBlockAttributes } = useDispatch('core/block-editor');

	// Track whether we've already auto-applied the overlay for this slide
	const hasAutoApplied = useRef(false);

	useEffect(() => {
		if (
			hasBackgroundImage &&
			parentClientId &&
			!parentOverlayColor &&
			!hasAutoApplied.current
		) {
			updateBlockAttributes(parentClientId, {
				overlayColor: DEFAULT_OVERLAY_COLOR,
			});
			hasAutoApplied.current = true;
		}
	}, [
		hasBackgroundImage,
		parentClientId,
		parentOverlayColor,
		updateBlockAttributes,
	]);

	const blockProps = useBlockProps({
		className: 'dsgo-scroll-slide',
	});

	const innerBlocksProps = useInnerBlocksProps(blockProps, {
		template: [
			[
				'designsetgo/section',
				{},
				[
					['core/image'],
					[
						'core/heading',
						{
							level: 3,
							placeholder: __('Slide title…', 'designsetgo'),
						},
					],
					[
						'core/paragraph',
						{
							placeholder: __(
								'Slide description…',
								'designsetgo'
							),
						},
					],
				],
			],
		],
		templateLock: false,
	});

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Slide Settings', 'designsetgo')}
					initialOpen={true}
				>
					<TextControl
						label={__('Navigation Heading', 'designsetgo')}
						value={navHeading}
						onChange={(value) =>
							setAttributes({ navHeading: value })
						}
						help={__(
							'Displayed in the slide navigation on the frontend',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<div {...innerBlocksProps} />
		</>
	);
}
