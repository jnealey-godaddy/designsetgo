/**
 * Slider — transition fields.
 *
 * The animation effect between slides and its timing. Switching to a
 * single-slide effect (fade/zoom) also forces slides-per-view back to one —
 * `onEffectChange` (from edit.js) carries that side effect so this file
 * stays a pure fields component. Composed inside the Settings
 * DsgoInspectorPanel in SettingsPanel.js.
 */

import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	Notice,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';

/**
 * @param {Object}   props                           Component props.
 * @param {Object}   props.attributes                Block attributes.
 * @param {Function} props.setAttributes             Attribute setter.
 * @param {Function} props.onEffectChange            Handles the Transition
 *                                                   Effect select, also
 *                                                   clamping slides-per-view
 *                                                   when a single-slide
 *                                                   effect is chosen.
 * @param {boolean}  props.requiresSingleSlideEffect Whether the current
 *                                                   effect forces one
 *                                                   slide per view.
 * @param {string}   props.singleSlideNotice         Notice text shown when
 *                                                   locked to a single
 *                                                   slide.
 * @return {JSX.Element} Transition fields.
 */
export default function TransitionFields({
	attributes,
	setAttributes,
	onEffectChange,
	requiresSingleSlideEffect,
	singleSlideNotice,
}) {
	const { effect, transitionDuration, transitionEasing } = attributes;

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Transition Effect', 'designsetgo')}
				hasValue={() => effect !== 'slide'}
				onDeselect={() => onEffectChange('slide')}
				isShownByDefault
			>
				<SelectControl
					label={__('Transition Effect', 'designsetgo')}
					value={effect}
					options={[
						{ label: __('Slide', 'designsetgo'), value: 'slide' },
						{ label: __('Fade', 'designsetgo'), value: 'fade' },
						{ label: __('Zoom', 'designsetgo'), value: 'zoom' },
					]}
					onChange={onEffectChange}
					help={__('Animation style between slides', 'designsetgo')}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				{requiresSingleSlideEffect && (
					<Notice status="info" isDismissible={false}>
						{singleSlideNotice}
					</Notice>
				)}
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Transition Duration', 'designsetgo')}
				hasValue={() => transitionDuration !== '0.5s'}
				onDeselect={() => setAttributes({ transitionDuration: '0.5s' })}
				isShownByDefault
			>
				<UnitControl
					label={__('Transition Duration', 'designsetgo')}
					value={transitionDuration}
					onChange={(value) =>
						setAttributes({
							transitionDuration: value || '0.5s',
						})
					}
					units={[
						{ value: 's', label: 's', default: 0.5 },
						{ value: 'ms', label: 'ms', default: 500 },
					]}
					min={0.1}
					max={2}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Transition Easing', 'designsetgo')}
				hasValue={() => transitionEasing !== 'ease-in-out'}
				onDeselect={() =>
					setAttributes({ transitionEasing: 'ease-in-out' })
				}
				isShownByDefault
			>
				<SelectControl
					label={__('Transition Easing', 'designsetgo')}
					value={transitionEasing}
					options={[
						{ label: __('Ease', 'designsetgo'), value: 'ease' },
						{
							label: __('Ease In Out', 'designsetgo'),
							value: 'ease-in-out',
						},
						{
							label: __('Ease In', 'designsetgo'),
							value: 'ease-in',
						},
						{
							label: __('Ease Out', 'designsetgo'),
							value: 'ease-out',
						},
						{
							label: __('Linear', 'designsetgo'),
							value: 'linear',
						},
					]}
					onChange={(value) =>
						setAttributes({ transitionEasing: value })
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
}
