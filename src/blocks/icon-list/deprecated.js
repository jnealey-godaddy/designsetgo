/**
 * Icon List Block - Deprecations
 *
 * @since 2.2.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import metadata from './block.json';

// Matches the responsive grid value `…minmax(min(100%, <width>), 1fr)` and
// captures the <width> so it can be recovered into the columnMinWidth attribute.
const MIN_WIDTH_RE = /minmax\(\s*min\(\s*100%\s*,\s*([0-9.]+[a-z%]+)/i;

/**
 * v1 — legacy gd-pattern-library markup.
 *
 * AI-generated patterns hard-coded a responsive grid on the items wrapper as
 * `grid-template-columns: repeat(auto-fit, minmax(min(100%, <width>), 1fr))`
 * in the INLINE style, without a `columnMinWidth` block attribute. The value
 * only existed in CSS, so the current save() (which reads `columnMinWidth`)
 * cannot reproduce it and the block fails validation ("Attempt Recovery").
 *
 * This deprecation captures the stored inner style via a sourced attribute,
 * reproduces it verbatim so validation passes, and migrate() recovers the
 * width into the new `columnMinWidth` attribute — after which the current
 * save() reproduces the markup from the attribute as normal.
 */
const v1 = {
	supports: metadata.supports,
	attributes: {
		...metadata.attributes,
		legacyItemsStyle: {
			type: 'string',
			source: 'attribute',
			selector: '.dsgo-icon-list__items',
			attribute: 'style',
		},
	},
	isEligible(attributes, innerBlocks, { blockNode, block } = {}) {
		const innerHTML = blockNode?.innerHTML ?? block?.originalContent ?? '';
		return !!innerHTML && innerHTML.includes('minmax(min(100%');
	},
	save({ attributes }) {
		const { layout, gap, columns, alignment, legacyItemsStyle } =
			attributes;

		// Mirror the current save()'s alignment logic exactly.
		let alignItemsValue;
		let justifyContentValue;
		if (layout === 'vertical') {
			if (alignment === 'center') {
				alignItemsValue = 'center';
			} else if (alignment === 'right') {
				alignItemsValue = 'flex-end';
			} else {
				alignItemsValue = 'flex-start';
			}
		} else if (layout === 'horizontal') {
			if (alignment === 'center') {
				justifyContentValue = 'center';
			} else if (alignment === 'right') {
				justifyContentValue = 'flex-end';
			} else {
				justifyContentValue = 'flex-start';
			}
		}

		let flexDirection;
		if (layout === 'vertical') {
			flexDirection = 'column';
		} else if (layout === 'horizontal') {
			flexDirection = 'row';
		}

		// Reproduce the stored grid-template-columns verbatim from the captured
		// inline style so this matches byte-for-byte; fall back to the fixed
		// column count when no legacy value is present.
		let gridTemplateColumns;
		if (layout === 'grid') {
			const gtc = (legacyItemsStyle || '').match(
				/grid-template-columns:\s*([^;]+)/i
			);
			gridTemplateColumns = gtc
				? gtc[1].trim()
				: `repeat(${columns}, 1fr)`;
		}

		const containerStyles = {
			display: layout === 'grid' ? 'grid' : 'flex',
			flexDirection,
			gridTemplateColumns,
			gap,
			alignItems: alignItemsValue,
			justifyContent: justifyContentValue,
			width: '100%',
		};

		const blockProps = useBlockProps.save({
			className: `dsgo-icon-list dsgo-icon-list--${layout}`,
			style: { width: '100%' },
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-icon-list__items',
			style: containerStyles,
		});

		return (
			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		);
	},
	migrate(attributes) {
		const { legacyItemsStyle, ...rest } = attributes;
		const match = (legacyItemsStyle || '').match(MIN_WIDTH_RE);
		return {
			...rest,
			columnMinWidth: match ? match[1] : '',
		};
	},
};

export default [v1];
