/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
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

export default function Edit({ attributes, setAttributes, clientId, context }) {
	const { navHeading } = attributes;
	// When bound to a dynamic query the heading is driven by the iterated
	// post's title (see src/blocks/scroll-slide/render.php), so showing an
	// editable control that has no effect would be misleading.
	const inQueryMode = !!context?.['designsetgo/queryId'];

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
			{!inQueryMode && (
				<InspectorControls>
					<DsgoInspectorPanel
						title={__('Settings', 'designsetgo')}
						panelName="settings"
						panelId={clientId}
						resetAll={() => setAttributes({ navHeading: '' })}
					>
						<DsgoInspectorPanel.Item
							label={__('Navigation Heading', 'designsetgo')}
							hasValue={() => navHeading !== ''}
							onDeselect={() => setAttributes({ navHeading: '' })}
							isShownByDefault
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
						</DsgoInspectorPanel.Item>
					</DsgoInspectorPanel>
				</InspectorControls>
			)}

			<div {...innerBlocksProps} />
		</>
	);
}
