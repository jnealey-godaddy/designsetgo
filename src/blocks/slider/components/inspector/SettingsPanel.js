/**
 * Slider — Settings panel.
 *
 * Behaviour and content: responsive slides-per-view, navigation on/off,
 * transitions, autoplay, and interaction behaviour. How things look lives in
 * StylePanel.
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { DsgoInspectorPanel } from '../../../../components/shared';
import LayoutFields from './LayoutFields';
import NavigationFields from './NavigationFields';
import TransitionFields from './TransitionFields';
import AutoplayFields from './AutoplayFields';
import BehaviorFields from './BehaviorFields';

const NOTICES_STYLE = {
	gridColumn: '1 / -1',
	display: 'flex',
	flexDirection: 'column',
	gap: '8px',
};

/**
 * @param {Object}   props                           Component props.
 * @param {Object}   props.attributes                Block attributes.
 * @param {Function} props.setAttributes             Attribute setter.
 * @param {string}   props.clientId                  Block client id.
 * @param {Function} props.onEffectChange            Transition Effect
 *                                                   change handler
 *                                                   (also clamps
 *                                                   slides-per-view).
 * @param {boolean}  props.requiresSingleSlideEffect Whether the current
 *                                                   effect forces one
 *                                                   slide per view.
 * @param {string}   props.singleSlideNotice         Notice text shown
 *                                                   when locked to a
 *                                                   single slide.
 * @param {Element}  [props.notices]                 Notices shown at the
 *                                                   top of Settings
 *                                                   (Dynamic Query mode).
 * @return {JSX.Element} Settings panel.
 */
export default function SettingsPanel({
	attributes,
	setAttributes,
	clientId,
	onEffectChange,
	requiresSingleSlideEffect,
	singleSlideNotice,
	notices,
}) {
	return (
		<InspectorControls>
			<DsgoInspectorPanel
				title={__('Settings', 'designsetgo')}
				panelName="settings"
				panelId={clientId}
				resetAll={() =>
					setAttributes({
						slidesPerView: 1,
						slidesPerViewTablet: 1,
						slidesPerViewMobile: 1,
						mobileBreakpoint: 768,
						tabletBreakpoint: 1024,
						ariaLabel: '',
						showArrows: true,
						showDots: true,
						effect: 'slide',
						transitionDuration: '0.5s',
						transitionEasing: 'ease-in-out',
						autoplay: false,
						autoplayInterval: 3000,
						pauseOnHover: true,
						pauseOnInteraction: true,
						loop: true,
						swipeable: true,
						draggable: true,
						freeMode: false,
						centeredSlides: false,
						scrollDriven: false,
						scrollDrivenSpeed: 1,
					})
				}
			>
				{notices && (
					// A plain child of the ToolsPanel grid, spanning both
					// columns so the notices get the panel's padding and
					// always sit above the first control.
					<div
						className="dsgo-slider-inspector__notices"
						style={NOTICES_STYLE}
					>
						{notices}
					</div>
				)}

				<LayoutFields
					attributes={attributes}
					setAttributes={setAttributes}
					requiresSingleSlideEffect={requiresSingleSlideEffect}
					singleSlideNotice={singleSlideNotice}
				/>

				<NavigationFields
					attributes={attributes}
					setAttributes={setAttributes}
				/>

				<TransitionFields
					attributes={attributes}
					setAttributes={setAttributes}
					onEffectChange={onEffectChange}
					requiresSingleSlideEffect={requiresSingleSlideEffect}
					singleSlideNotice={singleSlideNotice}
				/>

				<AutoplayFields
					attributes={attributes}
					setAttributes={setAttributes}
				/>

				<BehaviorFields
					attributes={attributes}
					setAttributes={setAttributes}
				/>
			</DsgoInspectorPanel>
		</InspectorControls>
	);
}
