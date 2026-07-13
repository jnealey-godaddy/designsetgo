import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

// v1: save() emitted empty --dsgo-accordion-* custom properties even when the
// colors were unset; current save() omits them. Markup-only change → passthrough
// migrate. isEligible matches the legacy empty-declaration signature.
const v1 = {
	attributes: {
		allowMultipleOpen: {
			type: 'boolean',
			default: false,
		},
		iconStyle: {
			type: 'string',
			default: 'chevron',
			enum: ['chevron', 'plus-minus', 'caret', 'none'],
		},
		iconPosition: {
			type: 'string',
			default: 'right',
			enum: ['left', 'right'],
		},
		borderBetween: {
			type: 'boolean',
			default: true,
		},
		borderBetweenColor: {
			type: 'string',
			default: '',
		},
		itemGap: {
			type: 'string',
			default: '0.5rem',
		},
		openBackgroundColor: {
			type: 'string',
			default: '',
		},
		openTextColor: {
			type: 'string',
			default: '',
		},
		hoverBackgroundColor: {
			type: 'string',
			default: '',
		},
		hoverTextColor: {
			type: 'string',
			default: '',
		},
	},
	supports: {
		anchor: true,
		align: ['wide', 'full'],
		html: false,
		inserter: true,
		spacing: {
			margin: true,
			padding: true,
			blockGap: true,
			__experimentalDefaultControls: {
				padding: true,
				blockGap: true,
			},
		},
		color: {
			background: true,
			text: true,
			link: true,
			__experimentalDefaultControls: {
				background: true,
				text: true,
			},
		},
		typography: {
			fontSize: true,
			lineHeight: true,
			__experimentalDefaultControls: {
				fontSize: true,
				lineHeight: true,
				fontWeight: true,
			},
			__experimentalFontFamily: true,
			__experimentalFontWeight: true,
		},
		__experimentalBorder: {
			color: true,
			radius: true,
			style: true,
			width: true,
			__experimentalDefaultControls: {
				color: true,
				radius: true,
				style: true,
				width: true,
			},
		},
	},
	isEligible(attributes, innerBlocks, { blockNode, block } = {}) {
		const innerHTML = blockNode?.innerHTML ?? block?.originalContent ?? '';
		return !!innerHTML && innerHTML.includes('--dsgo-accordion-open-bg:;');
	},
	save({ attributes }) {
		const {
			allowMultipleOpen,
			iconStyle,
			iconPosition,
			borderBetween,
			borderBetweenColor,
			itemGap,
			openBackgroundColor,
			openTextColor,
			hoverBackgroundColor,
			hoverTextColor,
		} = attributes;

		const effectiveHoverBg = hoverBackgroundColor || openBackgroundColor;
		const effectiveHoverText = hoverTextColor || openTextColor;

		const accordionClasses = classnames('dsgo-accordion', {
			'dsgo-accordion--multiple': allowMultipleOpen,
			'dsgo-accordion--icon-left': iconPosition === 'left',
			'dsgo-accordion--icon-right': iconPosition === 'right',
			'dsgo-accordion--no-icon': iconStyle === 'none',
			'dsgo-accordion--border-between': borderBetween,
		});

		// OLD behavior: empty string fallback so the declaration is always emitted.
		const customStyles = {
			'--dsgo-accordion-open-bg':
				convertColorToCSSVar(openBackgroundColor) || '',
			'--dsgo-accordion-open-text':
				convertColorToCSSVar(openTextColor) || '',
			'--dsgo-accordion-hover-bg':
				convertColorToCSSVar(effectiveHoverBg) || '',
			'--dsgo-accordion-hover-text':
				convertColorToCSSVar(effectiveHoverText) || '',
			'--dsgo-accordion-gap': itemGap,
			...(borderBetweenColor && {
				'--dsgo-accordion-border-color':
					convertColorToCSSVar(borderBetweenColor),
			}),
		};

		const blockProps = useBlockProps.save({
			className: accordionClasses,
			style: customStyles,
			'data-allow-multiple': allowMultipleOpen,
			'data-icon-style': iconStyle,
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-accordion__items',
		});

		return (
			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		);
	},
	migrate(attributes) {
		return attributes;
	},
};

export default [v1];
