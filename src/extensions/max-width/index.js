/**
 * Max Width Extension
 *
 * Adds a max-width control to all blocks (core and custom) allowing users to
 * constrain block width directly in the editor.
 * Editor controls are lazy-loaded to reduce initial bundle size.
 *
 * @since 1.0.0
 */

import './editor.scss';
import './style.scss';

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { lazy, Suspense } from '@wordpress/element';
import { shouldExtendBlock } from '../../utils/should-extend-block';

/**
 * List of blocks to exclude from max-width control
 */
const EXCLUDED_BLOCKS = [
	'core/spacer',
	'core/separator',
	'core/page-list',
	'core/navigation',
	'designsetgo/section',
	'designsetgo/row',
	'designsetgo/grid',
	// Blobs ships its own native, kit-controllable max-width control
	// (attribute `maxWidth` → `--dsgo-blob-max-width`), so it opts out of the
	// generic extension to avoid a duplicate control and conflicting markup.
	'designsetgo/blobs',
];

// Lazy-load editor components
const MaxWidthPanel = lazy(() =>
	import(/* webpackChunkName: "ext-max-width" */ './edit').then((m) => ({
		default: m.MaxWidthPanel,
	}))
);
const MaxWidthStyles = lazy(() =>
	import(/* webpackChunkName: "ext-max-width" */ './edit').then((m) => ({
		default: m.MaxWidthStyles,
	}))
);

/**
 * Add width attributes to blocks
 *
 * @param {Object} settings Block settings
 * @param {string} name     Block name
 */
function addMaxWidthAttribute(settings, name) {
	if (!shouldExtendBlock(name)) {
		return settings;
	}

	if (EXCLUDED_BLOCKS.includes(name)) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			dsgoMaxWidth: { type: 'string', default: '' },
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/add-max-width-attribute',
	addMaxWidthAttribute
);

/**
 * Add width controls to block inspector (lazy-loaded)
 */
const withMaxWidthControl = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { name } = props;

		if (!shouldExtendBlock(name) || EXCLUDED_BLOCKS.includes(name)) {
			return <BlockEdit {...props} />;
		}

		return (
			<>
				<BlockEdit {...props} />
				<Suspense fallback={null}>
					<MaxWidthPanel {...props} />
				</Suspense>
			</>
		);
	};
}, 'withMaxWidthControl');

addFilter(
	'editor.BlockEdit',
	'designsetgo/add-max-width-control',
	withMaxWidthControl,
	10
);

/**
 * Apply max-width styles in editor (lazy-loaded)
 */
const withMaxWidthStyles = createHigherOrderComponent((BlockListBlock) => {
	return (props) => {
		const { name } = props;

		if (!shouldExtendBlock(name) || EXCLUDED_BLOCKS.includes(name)) {
			return <BlockListBlock {...props} />;
		}

		return (
			<Suspense fallback={<BlockListBlock {...props} />}>
				<MaxWidthStyles BlockListBlock={BlockListBlock} {...props} />
			</Suspense>
		);
	};
}, 'withMaxWidthStyles');

addFilter(
	'editor.BlockListBlock',
	'designsetgo/add-max-width-styles-editor',
	withMaxWidthStyles,
	20
);

/**
 * Apply max-width styles to block wrapper on frontend
 *
 * @param {Object} props      Extra props
 * @param {Object} blockType  Block type
 * @param {Object} attributes Block attributes
 */
function applyMaxWidthStyles(props, blockType, attributes) {
	const { dsgoMaxWidth, align, textAlign } = attributes;

	if (!shouldExtendBlock(blockType.name)) {
		return props;
	}

	if (EXCLUDED_BLOCKS.includes(blockType.name)) {
		return props;
	}

	if (!dsgoMaxWidth) {
		return props;
	}

	let marginLeft = 'auto';
	let marginRight = 'auto';

	if (textAlign === 'left' || align === 'left') {
		marginLeft = '0';
		marginRight = 'auto';
	} else if (textAlign === 'right' || align === 'right') {
		marginLeft = 'auto';
		marginRight = '0';
	}

	return {
		...props,
		className: `${props.className || ''} dsgo-has-max-width`.trim(),
		style: {
			...props.style,
			maxWidth: dsgoMaxWidth,
			marginLeft,
			marginRight,
		},
	};
}

addFilter(
	'blocks.getSaveContent.extraProps',
	'designsetgo/apply-max-width-styles',
	applyMaxWidthStyles
);
