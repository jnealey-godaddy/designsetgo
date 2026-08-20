import { __ } from '@wordpress/i18n';
import {
	BlockControls,
	InspectorControls,
	store as blockEditorStore,
	useBlockProps,
	useInnerBlocksProps,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
} from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import DsgoChildToolbar from '../../components/shared/DsgoChildToolbar';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { useBlockColors } from '../../hooks';
import { HOTSPOT_ITEM_DUPLICATE_OVERRIDES } from '../hotspot-item/constants';
import { getSafeHotspotColor } from '../hotspot-item/utils';
import HotspotCanvas from './components/HotspotCanvas';
import HotspotInspector from './components/HotspotInspector';

const ALLOWED_BLOCKS = ['designsetgo/hotspot-item'];
const TEMPLATE = [
	['designsetgo/hotspot-item', { label: '+', x: 32, y: 42 }],
	['designsetgo/hotspot-item', { label: '+', x: 68, y: 60 }],
];

export default function HotspotEdit({ attributes, setAttributes, clientId }) {
	const {
		imageUrl,
		imageAlt,
		tooltipWidth,
		sequenceDuration,
		markerColor,
		markerBackgroundColor,
		tooltipBackgroundColor,
		tooltipTextColor,
	} = attributes;
	const { innerBlocks, selectedBlockId } = useSelect(
		(select) => {
			const editor = select(blockEditorStore);
			return {
				innerBlocks: editor.getBlock(clientId)?.innerBlocks || [],
				selectedBlockId: editor.getSelectedBlockClientId(),
			};
		},
		[clientId]
	);
	const { updateBlockAttributes } = useDispatch(blockEditorStore);
	const { settings: colorSettings, colorGradientSettings } = useBlockColors({
		attributes,
		setAttributes,
		entries: [
			{
				label: __('Marker color', 'designsetgo'),
				attribute: 'markerColor',
			},
			{
				label: __('Marker background', 'designsetgo'),
				attribute: 'markerBackgroundColor',
			},
			{
				label: __('Tooltip background', 'designsetgo'),
				attribute: 'tooltipBackgroundColor',
			},
			{
				label: __('Tooltip text color', 'designsetgo'),
				attribute: 'tooltipTextColor',
			},
		],
	});
	const selectedIndex = innerBlocks.findIndex(
		(item) => item.clientId === selectedBlockId
	);
	const selectedItem = selectedIndex >= 0 ? innerBlocks[selectedIndex] : null;
	const blockProps = useBlockProps({
		className: `dsgo-hotspot dsgo-hotspot--position-${attributes.tooltipPosition} dsgo-hotspot--animation-${attributes.animation}`,
		style: {
			'--dsgo-hotspot-tooltip-width': `${tooltipWidth}px`,
			'--dsgo-hotspot-sequence-duration': `${sequenceDuration}ms`,
			...(getSafeHotspotColor(markerColor) && {
				'--dsgo-hotspot-marker-color': convertColorToCSSVar(
					getSafeHotspotColor(markerColor)
				),
			}),
			...(getSafeHotspotColor(markerBackgroundColor) && {
				'--dsgo-hotspot-marker-background': convertColorToCSSVar(
					getSafeHotspotColor(markerBackgroundColor)
				),
			}),
			...(getSafeHotspotColor(tooltipBackgroundColor) && {
				'--dsgo-hotspot-tooltip-background': convertColorToCSSVar(
					getSafeHotspotColor(tooltipBackgroundColor)
				),
			}),
			...(getSafeHotspotColor(tooltipTextColor) && {
				'--dsgo-hotspot-tooltip-color': convertColorToCSSVar(
					getSafeHotspotColor(tooltipTextColor)
				),
			}),
		},
	});
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'dsgo-hotspot__items' },
		{ allowedBlocks: ALLOWED_BLOCKS, template: TEMPLATE }
	);
	const updateCoordinates = (item, coordinates) =>
		updateBlockAttributes(item.clientId, coordinates);

	return (
		<>
			<BlockControls group="block">
				<DsgoChildToolbar
					parentClientId={clientId}
					childBlockName="designsetgo/hotspot-item"
					activeIndex={selectedIndex}
					cloneAttributeOverrides={HOTSPOT_ITEM_DUPLICATE_OVERRIDES}
					addLabel={__('Add hotspot', 'designsetgo')}
					duplicateLabel={__('Duplicate hotspot', 'designsetgo')}
					removeLabel={__('Remove hotspot', 'designsetgo')}
					orientation="vertical"
				/>
			</BlockControls>
			<InspectorControls>
				<HotspotInspector
					attributes={attributes}
					setAttributes={setAttributes}
					clientId={clientId}
				/>
			</InspectorControls>
			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Colors', 'designsetgo')}
					settings={colorSettings}
					{...colorGradientSettings}
				/>
			</InspectorControls>
			<div {...blockProps}>
				<HotspotCanvas
					imageUrl={imageUrl}
					imageAlt={imageAlt}
					innerBlocksProps={innerBlocksProps}
					selectedItem={selectedItem}
					onCoordinateChange={updateCoordinates}
				/>
			</div>
		</>
	);
}
