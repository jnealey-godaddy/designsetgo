/**
 * Slider — layout & responsive fields.
 *
 * Slides-per-view at each breakpoint, the pixel breakpoints themselves, and
 * the slider's accessible label. Composed inside the Settings
 * DsgoInspectorPanel in SettingsPanel.js.
 */

import { __ } from '@wordpress/i18n';
import { RangeControl, TextControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';

/**
 * @param {Object}   props                           Component props.
 * @param {Object}   props.attributes                Block attributes.
 * @param {Function} props.setAttributes             Attribute setter.
 * @param {boolean}  props.requiresSingleSlideEffect Whether the current
 *                                                   transition effect
 *                                                   forces one slide
 *                                                   per view.
 * @param {string}   props.singleSlideNotice         Help text shown
 *                                                   when locked to a
 *                                                   single slide.
 * @return {JSX.Element} Layout & responsive fields.
 */
export default function LayoutFields({
	attributes,
	setAttributes,
	requiresSingleSlideEffect,
	singleSlideNotice,
}) {
	const {
		slidesPerView,
		slidesPerViewTablet,
		slidesPerViewMobile,
		mobileBreakpoint,
		tabletBreakpoint,
		ariaLabel,
	} = attributes;

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Slides Per View (Desktop)', 'designsetgo')}
				hasValue={() => slidesPerView !== 1}
				onDeselect={() => setAttributes({ slidesPerView: 1 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Slides Per View (Desktop)', 'designsetgo')}
					value={slidesPerView}
					onChange={(value) =>
						setAttributes({ slidesPerView: value })
					}
					min={1}
					max={6}
					help={
						requiresSingleSlideEffect
							? singleSlideNotice
							: __(
									'Number of slides visible at once',
									'designsetgo'
								)
					}
					disabled={requiresSingleSlideEffect}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Slides Per View (Tablet)', 'designsetgo')}
				hasValue={() => slidesPerViewTablet !== 1}
				onDeselect={() => setAttributes({ slidesPerViewTablet: 1 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Slides Per View (Tablet)', 'designsetgo')}
					value={slidesPerViewTablet}
					onChange={(value) =>
						setAttributes({ slidesPerViewTablet: value })
					}
					min={1}
					max={4}
					help={requiresSingleSlideEffect ? singleSlideNotice : ''}
					disabled={requiresSingleSlideEffect}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Slides Per View (Mobile)', 'designsetgo')}
				hasValue={() => slidesPerViewMobile !== 1}
				onDeselect={() => setAttributes({ slidesPerViewMobile: 1 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Slides Per View (Mobile)', 'designsetgo')}
					value={slidesPerViewMobile}
					onChange={(value) =>
						setAttributes({ slidesPerViewMobile: value })
					}
					min={1}
					max={2}
					help={requiresSingleSlideEffect ? singleSlideNotice : ''}
					disabled={requiresSingleSlideEffect}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Mobile Breakpoint', 'designsetgo')}
				hasValue={() => mobileBreakpoint !== 768}
				onDeselect={() => setAttributes({ mobileBreakpoint: 768 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Mobile Breakpoint (px)', 'designsetgo')}
					value={mobileBreakpoint}
					onChange={(value) =>
						setAttributes({ mobileBreakpoint: value })
					}
					min={320}
					max={900}
					help={__(
						'Below this width, uses mobile slides per view',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('Tablet Breakpoint', 'designsetgo')}
				hasValue={() => tabletBreakpoint !== 1024}
				onDeselect={() => setAttributes({ tabletBreakpoint: 1024 })}
				isShownByDefault
			>
				<RangeControl
					label={__('Tablet Breakpoint (px)', 'designsetgo')}
					value={tabletBreakpoint}
					onChange={(value) =>
						setAttributes({ tabletBreakpoint: value })
					}
					min={768}
					max={1280}
					help={__(
						'Below this width, uses tablet slides per view',
						'designsetgo'
					)}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			<DsgoInspectorPanel.Item
				label={__('ARIA Label', 'designsetgo')}
				hasValue={() => ariaLabel !== ''}
				onDeselect={() => setAttributes({ ariaLabel: '' })}
				isShownByDefault
			>
				<TextControl
					label={__('ARIA Label', 'designsetgo')}
					value={ariaLabel}
					onChange={(value) => setAttributes({ ariaLabel: value })}
					help={__(
						'Accessible label for screen readers',
						'designsetgo'
					)}
					placeholder={__('Image slider', 'designsetgo')}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>
		</>
	);
}
