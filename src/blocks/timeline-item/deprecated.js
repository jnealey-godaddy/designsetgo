/**
 * Timeline Item Block - Deprecations
 *
 * v1: save() before the marker size moved out of the markup. The marker SVG
 * carried width/height attributes and a marker image an inline width/height,
 * both taken from the parent timeline's markerSize context. save() never
 * receives block context, so every stored item was written at the 16px
 * fallback whatever the parent's Marker Size said — the frontend marker never
 * followed the setting. The current save() writes no size; style.scss sizes
 * the marker from --dsgo-timeline-marker-size, which the parent sets.
 *
 * Markup-only change with an unchanged attribute schema, so no isEligible:
 * stored HTML no longer matches the current save(), and this frozen copy
 * reproduces it.
 *
 * @package
 */

import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import metadata from './block.json';

/**
 * Validates URL protocol to prevent javascript: and other dangerous protocols.
 * @param {string} url - The URL to validate.
 * @return {string|null} - The safe URL or null if invalid.
 */
const getSafeUrlV1 = (url) => {
	if (!url) {
		return null;
	}
	try {
		const parsed = new URL(url, window.location.origin);
		const allowedProtocols = ['https:', 'http:', 'mailto:', 'tel:'];
		if (allowedProtocols.includes(parsed.protocol)) {
			return url;
		}
		return null;
	} catch {
		// Relative URLs are okay
		if (url.startsWith('/') || url.startsWith('#')) {
			return url;
		}
		return null;
	}
};

// Marker shape SVGs - same as edit.js
const MarkerShapesV1 = {
	circle: ({ size, fillColor, borderColor }) => (
		<svg
			width={size}
			height={size}
			viewBox="0 0 24 24"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
		>
			<circle
				cx="12"
				cy="12"
				r="10"
				fill={fillColor}
				stroke={borderColor}
				strokeWidth="2"
			/>
		</svg>
	),
	square: ({ size, fillColor, borderColor }) => (
		<svg
			width={size}
			height={size}
			viewBox="0 0 24 24"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
		>
			<rect
				x="2"
				y="2"
				width="20"
				height="20"
				rx="2"
				fill={fillColor}
				stroke={borderColor}
				strokeWidth="2"
			/>
		</svg>
	),
	diamond: ({ size, fillColor, borderColor }) => (
		<svg
			width={size}
			height={size}
			viewBox="0 0 24 24"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
		>
			<rect
				x="12"
				y="2"
				width="14"
				height="14"
				rx="2"
				transform="rotate(45 12 2)"
				fill={fillColor}
				stroke={borderColor}
				strokeWidth="2"
			/>
		</svg>
	),
};

function saveV1({ attributes, context }) {
	const {
		date,
		title,
		imageUrl,
		isActive,
		linkUrl,
		linkTarget,
		customMarkerColor,
	} = attributes;

	// Get context from parent timeline
	const markerStyle =
		context?.['designsetgo/timeline/markerStyle'] || 'circle';
	const markerSize = context?.['designsetgo/timeline/markerSize'] || 16;
	const markerColor = context?.['designsetgo/timeline/markerColor'] || '';
	const markerBorderColor =
		context?.['designsetgo/timeline/markerBorderColor'] || '';

	// Determine marker colors (custom overrides parent)
	const effectiveMarkerColor =
		customMarkerColor ||
		markerColor ||
		'var(--wp--preset--color--primary, #2563eb)';
	const effectiveBorderColor = markerBorderColor || effectiveMarkerColor;

	// Get the marker shape component
	const MarkerShape = MarkerShapesV1[markerStyle] || MarkerShapesV1.circle;

	// Validate URL protocol for security
	const safeUrl = getSafeUrlV1(linkUrl);

	// Build class names - must match edit.js
	const itemClasses = classnames('dsgo-timeline-item', {
		'dsgo-timeline-item--active': isActive,
		'dsgo-timeline-item--has-image': imageUrl,
		'dsgo-timeline-item--has-link': safeUrl,
	});

	// Custom styles for marker
	const customStyles = customMarkerColor
		? {
				'--dsgo-timeline-item-marker-color':
					convertColorToCSSVar(customMarkerColor),
			}
		: {};

	const blockProps = useBlockProps.save({
		className: itemClasses,
		style: customStyles,
	});

	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-timeline-item__content',
	});

	// Render the marker
	const renderMarker = () => (
		<div className="dsgo-timeline-item__marker" aria-hidden="true">
			{imageUrl ? (
				<img
					src={imageUrl}
					alt=""
					className="dsgo-timeline-item__marker-image"
					style={{
						width: markerSize,
						height: markerSize,
						borderRadius: markerStyle === 'circle' ? '50%' : '4px',
						objectFit: 'cover',
					}}
				/>
			) : (
				<MarkerShape
					size={markerSize}
					fillColor={effectiveMarkerColor}
					borderColor={effectiveBorderColor}
				/>
			)}
		</div>
	);

	// Render the content
	const renderContent = () => (
		<div className="dsgo-timeline-item__wrapper">
			{date && (
				<RichText.Content
					tagName="span"
					className="dsgo-timeline-item__date"
					value={date}
				/>
			)}

			{title && (
				<RichText.Content
					tagName="h3"
					className="dsgo-timeline-item__title"
					value={title}
				/>
			)}

			<div {...innerBlocksProps} />
		</div>
	);

	// If there's a safe link, wrap the entire item
	if (safeUrl) {
		const linkRel =
			linkTarget === '_blank' ? 'noopener noreferrer' : undefined;

		return (
			<div {...blockProps}>
				{renderMarker()}
				<a
					href={safeUrl}
					target={linkTarget}
					rel={linkRel}
					className="dsgo-timeline-item__link"
				>
					{renderContent()}
				</a>
			</div>
		);
	}

	return (
		<div {...blockProps}>
			{renderMarker()}
			{renderContent()}
		</div>
	);
}

const v1 = {
	apiVersion: 3,
	attributes: metadata.attributes,
	supports: metadata.supports,
	save: saveV1,
};

export default [v1];
