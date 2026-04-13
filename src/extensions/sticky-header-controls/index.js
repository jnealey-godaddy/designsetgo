/**
 * Sticky Header Controls Extension
 *
 * Adds sticky header configuration controls to template parts in the Site Editor.
 * Editor panel is lazy-loaded to reduce initial bundle size.
 *
 * @package
 * @since 1.0.0
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { lazy, Suspense } from '@wordpress/element';
import { shouldExtendBlock } from '../../utils/should-extend-block';

// Lazy-load editor panel
const StickyHeaderPanel = lazy(
	() => import(/* webpackChunkName: "ext-sticky-header" */ './edit')
);

/**
 * Add sticky header attributes to template parts
 *
 * @param {Object} settings Block settings
 * @param {string} name     Block name
 */
function addStickyHeaderAttributes(settings, name) {
	if (!shouldExtendBlock(name)) {
		return settings;
	}

	if (name !== 'core/template-part') {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			dsgoStickyEnabled: { type: 'boolean', default: false },
			dsgoStickyShadow: { type: 'string', default: 'medium' },
			dsgoStickyShrink: { type: 'boolean', default: true },
			dsgoStickyShrinkAmount: { type: 'number', default: 50 },
			dsgoStickyHideOnScroll: { type: 'boolean', default: false },
			dsgoStickyBackground: { type: 'boolean', default: false },
			dsgoStickySkipTopBar: { type: 'boolean', default: true },
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/sticky-header-attributes',
	addStickyHeaderAttributes
);

/**
 * Add sticky header controls to template parts (lazy-loaded)
 */
const withStickyHeaderControls = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { name, attributes } = props;

		if (name !== 'core/template-part') {
			return <BlockEdit {...props} />;
		}

		const isHeader =
			attributes.area === 'header' ||
			attributes.slug?.includes('header') ||
			attributes.theme?.includes('header');

		if (!isHeader) {
			return <BlockEdit {...props} />;
		}

		return (
			<>
				<BlockEdit {...props} />
				<Suspense fallback={null}>
					<StickyHeaderPanel {...props} />
				</Suspense>
			</>
		);
	};
}, 'withStickyHeaderControls');

addFilter(
	'editor.BlockEdit',
	'designsetgo/sticky-header-controls',
	withStickyHeaderControls
);

/**
 * Apply sticky header classes to template parts on save
 *
 * @param {Object} extraProps Extra props
 * @param {Object} blockType  Block type
 * @param {Object} attributes Block attributes
 */
function applyStickyHeaderClasses(extraProps, blockType, attributes) {
	if (blockType.name !== 'core/template-part') {
		return extraProps;
	}

	if (!attributes.dsgoStickyEnabled) {
		return extraProps;
	}

	const classes = ['dsgo-sticky-header-enabled'];

	if (attributes.dsgoStickyShadow && attributes.dsgoStickyShadow !== 'none') {
		classes.push(`dsgo-sticky-shadow-${attributes.dsgoStickyShadow}`);
	}

	if (attributes.dsgoStickyShrink) {
		classes.push('dsgo-sticky-shrink-logo');
	}

	if (attributes.dsgoStickyHideOnScroll) {
		classes.push('dsgo-sticky-hide-on-scroll-down');
	}

	if (attributes.dsgoStickyBackground) {
		classes.push('dsgo-sticky-bg-on-scroll');
	}

	if (attributes.dsgoStickySkipTopBar !== false) {
		classes.push('dsgo-sticky-skip-top-bar');
	}

	return {
		...extraProps,
		className: `${extraProps.className || ''} ${classes.join(' ')}`.trim(),
		'data-dsgo-shrink-amount': attributes.dsgoStickyShrinkAmount || 50,
	};
}

addFilter(
	'blocks.getSaveContent.extraProps',
	'designsetgo/sticky-header-classes',
	applyStickyHeaderClasses
);
