import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import {
	hasExplicitString,
	hasExplicitNumber,
} from '../../utils/has-explicit-value';

export default function ImageAccordionSave({ attributes }) {
	const {
		height,
		gap,
		expandedRatio,
		transitionDuration,
		enableOverlay,
		overlayColor,
		overlayOpacity,
		overlayOpacityExpanded,
		triggerType,
		defaultExpanded,
	} = attributes;

	// Same classes as edit.js - MUST MATCH EXACTLY
	const accordionClasses = classnames('dsgo-image-accordion', {
		'dsgo-image-accordion--hover': triggerType === 'hover',
		'dsgo-image-accordion--click': triggerType === 'click',
	});

	// Height and gap are written inline ONLY when the author sets an explicit
	// value. Left unset they are omitted so the stylesheet default owns them
	// (resolving through --dsgo-image-accordion-<prop> → the theme token → the
	// literal fallback) and Style Kits / patterns can retheme them without
	// fighting a baked-in magic number. MUST MATCH edit.js.
	const hasExplicitHeight = hasExplicitString(height);
	const hasExplicitGap = hasExplicitString(gap);

	// Overlay color/opacity/opacity-expanded follow the same explicit-or-omit
	// contract as height/gap: each is written inline ONLY when the author set it.
	// Left unset the property is omitted so the item's stylesheet default owns it,
	// resolving through the parent var → the theme token
	// (--wp--custom--designsetgo--image-accordion--overlay-*) → the literal
	// fallback. This lets Style Kits / patterns retheme the scrim without a
	// baked-in magic number and keeps the scrim ENABLED. MUST MATCH edit.js.
	const hasExplicitColor = hasExplicitString(overlayColor);
	const hasExplicitOpacity = hasExplicitNumber(overlayOpacity);
	const hasExplicitOpacityExpanded = hasExplicitNumber(
		overlayOpacityExpanded
	);

	// Apply settings as CSS custom properties - MUST MATCH edit.js
	// Note: Unitless values must be strings to prevent React from adding 'px'
	const customStyles = {
		...(hasExplicitHeight && {
			'--dsgo-image-accordion-height': height,
		}),
		...(hasExplicitGap && { '--dsgo-image-accordion-gap': gap }),
		'--dsgo-image-accordion-expanded-ratio': String(expandedRatio), // Unitless
		'--dsgo-image-accordion-transition': transitionDuration,
		...(hasExplicitColor && {
			'--dsgo-image-accordion-overlay-color':
				convertColorToCSSVar(overlayColor),
		}),
		...(hasExplicitOpacity && {
			'--dsgo-image-accordion-overlay-opacity': String(
				overlayOpacity / 100
			), // Unitless
		}),
		...(hasExplicitOpacityExpanded && {
			'--dsgo-image-accordion-overlay-opacity-expanded': String(
				overlayOpacityExpanded / 100
			), // Unitless
		}),
	};

	// Use .save() variant for save function
	const blockProps = useBlockProps.save({
		className: accordionClasses,
		style: customStyles,
		'data-trigger-type': triggerType,
		'data-default-expanded': defaultExpanded,
		'data-enable-overlay': enableOverlay,
	});

	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-image-accordion__items',
	});

	return (
		<div {...blockProps}>
			<div {...innerBlocksProps} />
		</div>
	);
}
