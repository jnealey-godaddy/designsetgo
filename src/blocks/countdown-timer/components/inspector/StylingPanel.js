/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';

/**
 * Styling Panel component
 *
 * Renders DsgoInspectorPanel.Item entries for countdown unit spacing.
 * Meant to be composed inside the Settings DsgoInspectorPanel in
 * countdown-timer/edit.js.
 *
 * @param {Object}   props               - Component properties
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @return {JSX.Element} Item fragment
 */
export default function StylingPanel({ attributes, setAttributes }) {
	const { unitGap, unitPadding } = attributes;

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Gap Between Units', 'designsetgo')}
				hasValue={() => unitGap !== '1rem'}
				onDeselect={() => setAttributes({ unitGap: '1rem' })}
				isShownByDefault
			>
				<UnitControl
					label={__('Gap Between Units', 'designsetgo')}
					value={unitGap}
					onChange={(value) => setAttributes({ unitGap: value })}
					units={[
						{ value: 'px', label: 'px' },
						{ value: 'rem', label: 'rem' },
						{ value: 'em', label: 'em' },
					]}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Unit Padding', 'designsetgo')}
				hasValue={() => unitPadding !== '1.5rem'}
				onDeselect={() => setAttributes({ unitPadding: '1.5rem' })}
				isShownByDefault
			>
				<UnitControl
					label={__('Unit Padding', 'designsetgo')}
					value={unitPadding}
					onChange={(value) => setAttributes({ unitPadding: value })}
					units={[
						{ value: 'px', label: 'px' },
						{ value: 'rem', label: 'rem' },
						{ value: 'em', label: 'em' },
					]}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
}
