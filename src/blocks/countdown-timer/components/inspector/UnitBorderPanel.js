/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalBorderControl as BorderControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';

// Matches `attributes.unitBorder.default` in block.json. Exported so
// countdown-timer/edit.js can reference the same object in its
// panel-level resetAll — no drift between the per-item and panel-wide
// resets.
export const DEFAULT_UNIT_BORDER = {
	color: '',
	style: 'solid',
	width: '2px',
};

/**
 * Unit Border Panel component
 *
 * Renders DsgoInspectorPanel.Item entries for per-unit border + radius.
 * Originally lived in <InspectorControls group="border"> as a nested
 * ToolsPanel; now composed into the Settings panel in
 * countdown-timer/edit.js to match the rest of the plugin convention.
 *
 * @param {Object}   props               - Component properties
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @return {JSX.Element} Item fragment
 */
export default function UnitBorderPanel({ attributes, setAttributes }) {
	const { unitBorder, unitBorderRadius } = attributes;

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Unit Border', 'designsetgo')}
				hasValue={() =>
					!!unitBorder &&
					(unitBorder.color !== '' ||
						unitBorder.style !== 'solid' ||
						unitBorder.width !== '2px')
				}
				onDeselect={() =>
					setAttributes({ unitBorder: DEFAULT_UNIT_BORDER })
				}
				isShownByDefault
			>
				<BorderControl
					label={__('Unit Border', 'designsetgo')}
					value={unitBorder || DEFAULT_UNIT_BORDER}
					onChange={(value) => setAttributes({ unitBorder: value })}
					withSlider={true}
					__next40pxDefaultSize
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Unit Radius', 'designsetgo')}
				hasValue={() => unitBorderRadius !== 12}
				onDeselect={() => setAttributes({ unitBorderRadius: 12 })}
				isShownByDefault
			>
				<UnitControl
					label={__('Unit Radius', 'designsetgo')}
					value={`${unitBorderRadius}px`}
					onChange={(value) => {
						const numValue = parseInt(value);
						setAttributes({
							unitBorderRadius: isNaN(numValue) ? 12 : numValue,
						});
					}}
					units={[{ value: 'px', label: 'px' }]}
					min={0}
					max={50}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
}
