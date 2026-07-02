/**
 * Divider Block - Save Function
 *
 * WordPress Best Practice Approach:
 * - Declarative style application (matches edit.js exactly)
 * - Static output, no frontend JavaScript needed
 * - Block Supports automatically applies color styles
 *
 * @since 1.0.0
 */

import { useBlockProps } from '@wordpress/block-editor';

/**
 * Divider Save Function
 *
 * @param {Object} props            - Component props
 * @param {Object} props.attributes - Block attributes
 * @return {JSX.Element} Saved divider block markup
 */
export default function DividerSave({ attributes }) {
	const { dividerStyle, width, thickness, iconName, iconStyle, strokeWidth } =
		attributes;

	// Block wrapper props - Block Supports automatically applies color styles
	const blockProps = useBlockProps.save({
		className: `dsgo-divider dsgo-divider--${dividerStyle}`,
	});

	// Divider container styles
	const containerStyle = {
		width: `${width}%`,
	};

	// Divider line styles
	const lineStyle = {
		height: `${thickness}px`,
	};

	return (
		<div {...blockProps}>
			<div className="dsgo-divider__container" style={containerStyle}>
				{dividerStyle === 'icon' ? (
					<div className="dsgo-divider__icon-wrapper">
						<span
							className="dsgo-divider__line dsgo-divider__line--left"
							style={lineStyle}
						/>
						<span
							className="dsgo-divider__icon dsgo-lazy-icon"
							data-icon-name={iconName}
							// Omit when unset so the injector inherits the theme
							// default (settings.custom.designsetgo.icon.defaultStyle).
							data-icon-style={iconStyle || undefined}
							data-icon-stroke-width={
								iconStyle === 'outlined'
									? strokeWidth
									: undefined
							}
						/>
						<span
							className="dsgo-divider__line dsgo-divider__line--right"
							style={lineStyle}
						/>
					</div>
				) : (
					<div className="dsgo-divider__line" style={lineStyle} />
				)}
			</div>
		</div>
	);
}
