/**
 * Scroll Accordion Item Block - Deprecated versions
 *
 * @since 2.7.6
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import metadata from './block.json';
import { withLegacyOverlayOpacity } from '../../utils/overlay-opacity';

// Version 1: Fixed 0.8 overlay opacity. The current save() resolves
// `--dsgo-overlay-opacity` from the overlay colour (0.65 for opaque colours and
// presets, 1 for colours carrying their own alpha), so every item stored with
// an overlayColor and the old `--dsgo-overlay-opacity:0.8` mismatches it.
//
// save() is byte-for-byte the previous save(). Markup-change deprecation, so no
// isEligible; no attribute changed, so migrate() is a passthrough.
const v1 = {
	apiVersion: 3,
	supports: metadata.supports,
	attributes: { ...metadata.attributes },
	save({ attributes }) {
		const { overlayColor } = attributes;

		const overlayStyles = overlayColor
			? {
					'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
					'--dsgo-overlay-opacity': '0.8',
				}
			: {};

		const blockProps = useBlockProps.save({
			className: classnames('dsgo-scroll-accordion-item', {
				'dsgo-scroll-accordion-item--has-overlay': !!overlayColor,
			}),
			style: overlayColor ? overlayStyles : undefined,
		});

		const innerBlocksProps = useInnerBlocksProps.save(blockProps);

		return <div {...innerBlocksProps} />;
	},
	migrate(attributes) {
		return attributes;
	},
};

// Legacy inserter output: the Abilities inserter wrote `overlayColor` into
// `--dsgo-overlay-color` unconverted (a preset stayed `var:preset|color|x`,
// which never painted) with a fixed `--dsgo-overlay-opacity:0.8`. save() always
// converted the colour, so those items never validated. This save() reproduces
// the inserter's markup exactly; attributes pass through, and the current
// save() then writes the converted colour and the colour-aware opacity.
const legacyInserterRawColor = {
	apiVersion: 3,
	supports: metadata.supports,
	attributes: { ...metadata.attributes },
	save({ attributes }) {
		const { overlayColor } = attributes;

		const blockProps = useBlockProps.save({
			className: classnames('dsgo-scroll-accordion-item', {
				'dsgo-scroll-accordion-item--has-overlay': !!overlayColor,
			}),
			style: overlayColor
				? {
						'--dsgo-overlay-color': overlayColor,
						'--dsgo-overlay-opacity': '0.8',
					}
				: undefined,
		});

		const innerBlocksProps = useInnerBlocksProps.save(blockProps);

		return <div {...innerBlocksProps} />;
	},
	migrate(attributes) {
		return attributes;
	},
};

export default withLegacyOverlayOpacity([v1, legacyInserterRawColor]);
