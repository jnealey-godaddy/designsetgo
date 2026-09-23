/**
 * Custom CSS Extension
 *
 * Adds a custom CSS control to all blocks (core and custom) allowing users to
 * add their own CSS styles directly in the editor.
 * Editor controls are lazy-loaded to reduce initial bundle size.
 *
 * @since 1.0.0
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { lazy, Suspense } from '@wordpress/element';
import { shouldExtendBlock } from '../../utils/should-extend-block';
import { isExtensionEnabled } from '../../utils/is-extension-enabled';

/**
 * List of blocks to exclude from custom CSS control
 */
const EXCLUDED_BLOCKS = ['core/html', 'core/code'];

// Lazy-load editor components
const CustomCSSPanel = lazy(() =>
	import(/* webpackChunkName: "ext-custom-css" */ './edit').then((m) => ({
		default: m.CustomCSSPanel,
	}))
);
const CustomCSSStyles = lazy(() =>
	import(/* webpackChunkName: "ext-custom-css" */ './edit').then((m) => ({
		default: m.CustomCSSStyles,
	}))
);

/**
 * Add custom CSS attribute to all blocks
 *
 * @param {Object} settings Block settings
 * @param {string} name     Block name
 */
function addCustomCSSAttribute(settings, name) {
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
			dsgoCustomCSS: { type: 'string', default: '' },
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/add-custom-css-attribute',
	addCustomCSSAttribute
);

/**
 * Add custom CSS control to block inspector (lazy-loaded)
 */
const withCustomCSSControl = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { name } = props;

		if (!shouldExtendBlock(name) || EXCLUDED_BLOCKS.includes(name)) {
			return <BlockEdit {...props} />;
		}

		return (
			<>
				<BlockEdit {...props} />
				<Suspense fallback={null}>
					<CustomCSSPanel {...props} />
				</Suspense>
			</>
		);
	};
}, 'withCustomCSSControl');

// Controls only: attributes and saved markup stay registered so existing
// content still validates when the extension is switched off.
if (isExtensionEnabled('custom-css')) {
	addFilter(
		'editor.BlockEdit',
		'designsetgo/add-custom-css-control',
		withCustomCSSControl,
		200
	);
}

/**
 * Add custom CSS class AND inject styles into editor (lazy-loaded)
 */
const withCustomCSSClassAndStyles = createHigherOrderComponent(
	(BlockListBlock) => {
		return (props) => {
			const { attributes, name } = props;

			if (!shouldExtendBlock(name)) {
				return <BlockListBlock {...props} />;
			}

			if (!attributes.dsgoCustomCSS || EXCLUDED_BLOCKS.includes(name)) {
				return <BlockListBlock {...props} />;
			}

			return (
				<Suspense fallback={<BlockListBlock {...props} />}>
					<CustomCSSStyles
						BlockListBlock={BlockListBlock}
						{...props}
					/>
				</Suspense>
			);
		};
	},
	'withCustomCSSClassAndStyles'
);

addFilter(
	'editor.BlockListBlock',
	'designsetgo/add-custom-css-class-and-styles',
	withCustomCSSClassAndStyles,
	20
);

/**
 * Simple hash function to generate consistent IDs
 *
 * @param {string} str - String to hash
 * @return {string} Hash string
 */
function hashCode(str) {
	let hash = 0;
	for (let i = 0; i < str.length; i++) {
		const char = str.charCodeAt(i);
		// eslint-disable-next-line no-bitwise
		hash = (hash << 5) - hash + char;
		// eslint-disable-next-line no-bitwise
		hash = hash & hash;
	}
	return Math.abs(hash).toString(36);
}

/**
 * Add custom CSS class to block wrapper on frontend
 *
 * @param {Object} props      Extra props
 * @param {Object} blockType  Block type
 * @param {Object} attributes Block attributes
 */
function applyCustomCSSClass(props, blockType, attributes) {
	const { dsgoCustomCSS } = attributes;

	if (!shouldExtendBlock(blockType.name)) {
		return props;
	}

	if (!dsgoCustomCSS || EXCLUDED_BLOCKS.includes(blockType.name)) {
		return props;
	}

	const hash = hashCode(dsgoCustomCSS + blockType.name);
	const customClassName = `dsgo-custom-css-${hash}`;

	return {
		...props,
		className: `${props.className || ''} ${customClassName}`.trim(),
	};
}

addFilter(
	'blocks.getSaveContent.extraProps',
	'designsetgo/apply-custom-css-class',
	applyCustomCSSClass
);
