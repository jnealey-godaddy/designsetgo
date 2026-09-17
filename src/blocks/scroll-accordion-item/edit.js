/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import classnames from 'classnames';

/**
 * Internal dependencies
 */
import './editor.scss';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { getOverlayOpacity } from '../../utils/overlay-opacity';

/**
 * Edit component for the Scroll Accordion Item block.
 * Simple container for inner blocks with sticky stacking effect on frontend.
 *
 * @param {Object}   props               - Component props.
 * @param {Object}   props.attributes    - Block attributes.
 * @param {Function} props.setAttributes - Function to update attributes.
 * @param {string}   props.clientId      - Block client ID.
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes, clientId }) {
	const { overlayColor } = attributes;
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Apply overlay styles when color is set
	const overlayStyles = overlayColor
		? {
				'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
				'--dsgo-overlay-opacity': getOverlayOpacity(overlayColor),
			}
		: {};

	const blockProps = useBlockProps({
		className: classnames('dsgo-scroll-accordion-item', {
			'dsgo-scroll-accordion-item--has-overlay': !!overlayColor,
		}),
		style: {
			alignSelf: 'stretch',
			...overlayStyles,
		},
	});

	const innerBlocksProps = useInnerBlocksProps(blockProps, {
		template: [
			[
				'core/paragraph',
				{
					placeholder: __(
						'Add content for this scroll accordion item…',
						'designsetgo'
					),
				},
			],
		],
		templateLock: false,
	});

	return (
		<>
			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Overlay', 'designsetgo')}
					settings={[
						{
							label: __('Overlay Color', 'designsetgo'),
							colorValue: decodeColorValue(
								overlayColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									overlayColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
					]}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<div {...innerBlocksProps} />
		</>
	);
}
