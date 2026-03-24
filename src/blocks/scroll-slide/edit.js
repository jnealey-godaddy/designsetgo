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
import { useEffect } from '@wordpress/element';

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

	const { parentClientId, parentOverlayColor, overlayAutoApplied } =
		useSelect(
			(select) => {
				const { getBlockParentsByBlockName, getBlockAttributes } =
					select('core/block-editor');
				const parents = getBlockParentsByBlockName(
					clientId,
					'designsetgo/scroll-slides'
				);
				const parentId = parents[0] ?? null;
				const parentAttrs = parentId
					? getBlockAttributes(parentId)
					: null;
				return {
					parentClientId: parentId,
					parentOverlayColor: parentAttrs?.overlayColor ?? '',
					overlayAutoApplied:
						parentAttrs?.overlayAutoApplied ?? false,
				};
			},
			[clientId]
		);

	const { updateBlockAttributes } = useDispatch('core/block-editor');

	// Auto-apply overlay when a slide gets a background image.
	// The overlayAutoApplied attribute persists across undo/redo and remounts,
	// so the overlay won't re-apply after the user intentionally removes it.
	useEffect(() => {
		if (
			hasBackgroundImage &&
			parentClientId &&
			!parentOverlayColor &&
			!overlayAutoApplied
		) {
			updateBlockAttributes(parentClientId, {
				overlayColor: DEFAULT_OVERLAY_COLOR,
				overlayAutoApplied: true,
			});
		}
	}, [
		hasBackgroundImage,
		parentClientId,
		parentOverlayColor,
		overlayAutoApplied,
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
