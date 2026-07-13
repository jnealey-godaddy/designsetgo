/**
 * Divider Block - Deprecated Versions
 *
 * Handles backward compatibility for blocks saved with previous versions.
 *
 * @since 1.2.0
 */

import { useBlockProps } from '@wordpress/block-editor';
import { getIcon } from '../shared/icon-utils';

/**
 * Shared supports for all deprecated versions.
 */
const sharedSupports = {
	anchor: true,
	align: ['left', 'center', 'right', 'wide', 'full'],
	html: false,
	inserter: true,
	spacing: {
		margin: true,
		padding: false,
		__experimentalDefaultControls: {
			margin: true,
		},
	},
	color: {
		text: true,
		gradient: false,
		__experimentalDefaultControls: {
			text: true,
		},
	},
	dimensions: {
		minHeight: true,
		__experimentalDefaultControls: {
			minHeight: false,
		},
	},
};

/**
 * Lazy-placeholder format — the last STATIC version, before the Divider became
 * server-rendered (dynamic).
 *
 * Static dividers using the "icon" style saved a `.dsgo-lazy-icon` placeholder
 * injected client-side; the dynamic block now renders the SVG in PHP
 * (render.php) and its save() returns null. This deprecation reproduces the
 * placeholder markup and migrates existing content untouched (passthrough),
 * avoiding the "Attempt Recovery" warning.
 */
const vLazy = {
	supports: sharedSupports,
	isEligible(attributes, innerBlocks, { blockNode, block } = {}) {
		const innerHTML = blockNode?.innerHTML ?? block?.originalContent ?? '';
		return Boolean(innerHTML) && innerHTML.includes('dsgo-lazy-icon');
	},
	attributes: {
		dividerStyle: { type: 'string', default: 'solid' },
		width: { type: 'number', default: 100 },
		thickness: { type: 'number', default: 2 },
		iconName: { type: 'string', default: 'star' },
		iconStyle: { type: 'string', enum: ['filled', 'outlined'] },
		strokeWidth: { type: 'number', default: 1.5 },
	},
	save({ attributes }) {
		const {
			dividerStyle,
			width,
			thickness,
			iconName,
			iconStyle,
			strokeWidth,
		} = attributes;

		const blockProps = useBlockProps.save({
			className: `dsgo-divider dsgo-divider--${dividerStyle}`,
		});

		const containerStyle = { width: `${width}%` };
		const lineStyle = { height: `${thickness}px` };

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
	},
	migrate(attributes) {
		return attributes;
	},
};

/**
 * Version 1: Before lazy loading icon library
 *
 * Changes in current version:
 * - Icons now use data attributes for frontend lazy loading
 * - Frontend icons injected via PHP to avoid bundling 51KB library
 */
const v1 = {
	supports: sharedSupports,
	attributes: {
		dividerStyle: {
			type: 'string',
			default: 'solid',
		},
		width: {
			type: 'number',
			default: 100,
		},
		thickness: {
			type: 'number',
			default: 2,
		},
		iconName: {
			type: 'string',
			default: 'star',
		},
	},
	isEligible(attributes, innerBlocks, { blockNode, block } = {}) {
		const innerHTML = blockNode?.innerHTML ?? block?.originalContent ?? '';
		// v1 blocks have inline SVG icons instead of dsgo-lazy-icon class
		return innerHTML && !innerHTML.includes('dsgo-lazy-icon');
	},
	save({ attributes }) {
		const { dividerStyle, width, thickness, iconName } = attributes;

		// Block wrapper props
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
							<span className="dsgo-divider__icon">
								{getIcon(iconName)}
							</span>
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
	},
	migrate(attributes) {
		// No attribute changes needed - only save function changed
		return attributes;
	},
};

export default [vLazy, v1];
