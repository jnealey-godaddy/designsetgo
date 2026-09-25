/**
 * Slider — arrow appearance fields.
 *
 * Style, placement, size, and padding for the prev/next arrows. Only
 * rendered while arrows are shown — whether to show them lives in
 * NavigationFields in the Settings panel. Composed inside the Style
 * DsgoInspectorPanel in StylePanel.js.
 */

import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';

/**
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {JSX.Element|null} Arrow appearance fields, or null while arrows
 *                             are hidden.
 */
export default function ArrowAppearanceFields({ attributes, setAttributes }) {
	const {
		showArrows,
		arrowStyle,
		arrowPosition,
		arrowVerticalPosition,
		arrowSize,
		arrowPadding,
	} = attributes;

	if (!showArrows) {
		return null;
	}

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Arrow Style', 'designsetgo')}
				hasValue={() => arrowStyle !== 'default'}
				onDeselect={() => setAttributes({ arrowStyle: 'default' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Arrow Style', 'designsetgo')}
					value={arrowStyle}
					options={[
						{
							label: __('Default', 'designsetgo'),
							value: 'default',
						},
						{ label: __('Circle', 'designsetgo'), value: 'circle' },
						{ label: __('Square', 'designsetgo'), value: 'square' },
						{
							label: __('Minimal', 'designsetgo'),
							value: 'minimal',
						},
					]}
					onChange={(value) => setAttributes({ arrowStyle: value })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Arrow Position (Horizontal)', 'designsetgo')}
				hasValue={() => arrowPosition !== 'sides'}
				onDeselect={() => setAttributes({ arrowPosition: 'sides' })}
				isShownByDefault
			>
				<SelectControl
					label={__('Arrow Position (Horizontal)', 'designsetgo')}
					value={arrowPosition}
					options={[
						{ label: __('Sides', 'designsetgo'), value: 'sides' },
						{ label: __('Inside', 'designsetgo'), value: 'inside' },
						{
							label: __('Outside', 'designsetgo'),
							value: 'outside',
						},
					]}
					onChange={(value) =>
						setAttributes({ arrowPosition: value })
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Arrow Position (Vertical)', 'designsetgo')}
				hasValue={() => arrowVerticalPosition !== 'center'}
				onDeselect={() =>
					setAttributes({ arrowVerticalPosition: 'center' })
				}
				isShownByDefault
			>
				<SelectControl
					label={__('Arrow Position (Vertical)', 'designsetgo')}
					value={arrowVerticalPosition}
					options={[
						{ label: __('Top', 'designsetgo'), value: 'top' },
						{ label: __('Center', 'designsetgo'), value: 'center' },
						{ label: __('Bottom', 'designsetgo'), value: 'bottom' },
					]}
					onChange={(value) =>
						setAttributes({ arrowVerticalPosition: value })
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Arrow Size', 'designsetgo')}
				hasValue={() => arrowSize !== '24px'}
				onDeselect={() => setAttributes({ arrowSize: '24px' })}
				isShownByDefault
			>
				<UnitControl
					label={__('Arrow Size', 'designsetgo')}
					value={arrowSize}
					onChange={(value) => setAttributes({ arrowSize: value })}
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
				label={__('Arrow Padding', 'designsetgo')}
				hasValue={() => arrowPadding !== ''}
				onDeselect={() => setAttributes({ arrowPadding: '' })}
				isShownByDefault
			>
				<UnitControl
					label={__('Arrow Padding', 'designsetgo')}
					value={arrowPadding}
					onChange={(value) => setAttributes({ arrowPadding: value })}
					units={[
						{ value: 'px', label: 'px' },
						{ value: 'rem', label: 'rem' },
						{ value: 'em', label: 'em' },
					]}
					help={__(
						'Inner spacing of the arrow button',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
}
