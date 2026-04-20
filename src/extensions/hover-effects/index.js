/**
 * Hover Effects Extension
 *
 * Adds subtle CSS hover micro-interactions (lift, grow, tilt, etc.)
 * to common container and interactive core blocks.
 *
 * @package
 * @since 1.0.0
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { lazy, Suspense } from '@wordpress/element';
import classnames from 'classnames';
import { shouldExtendBlock } from '../../utils/should-extend-block';
import { HOVER_EFFECTS, SUPPORTED_BLOCKS } from './constants';

// Editor-only styles (frontend styles live in src/styles/style.scss)
import './editor.scss';

const HoverEffectsPanel = lazy(
	() => import(/* webpackChunkName: "ext-hover-effects" */ './edit')
);

const VALID_EFFECTS = HOVER_EFFECTS.map((option) => option.value).filter(
	Boolean
);

/**
 * Build the class list for a given effect value.
 *
 * @param {string} effect Effect preset value.
 * @return {string} Space-separated class list, or empty string.
 */
function getHoverEffectClasses(effect) {
	if (!effect || !VALID_EFFECTS.includes(effect)) {
		return '';
	}
	return `dsgo-hover-effect dsgo-hover-effect--${effect}`;
}

/**
 * Register the dsgoHoverEffect attribute on supported blocks.
 *
 * @param {Object} settings Block settings.
 * @param {string} name     Block name.
 * @return {Object} Filtered block settings.
 */
function addHoverEffectAttribute(settings, name) {
	if (!shouldExtendBlock(name)) {
		return settings;
	}

	if (!SUPPORTED_BLOCKS.includes(name)) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			dsgoHoverEffect: { type: 'string', default: '' },
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/hover-effects-attributes',
	addHoverEffectAttribute
);

/**
 * Append the Inspector panel to supported blocks (lazy-loaded).
 */
const withHoverEffectControls = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		if (!SUPPORTED_BLOCKS.includes(props.name)) {
			return <BlockEdit {...props} />;
		}

		return (
			<>
				<BlockEdit {...props} />
				<Suspense fallback={null}>
					<HoverEffectsPanel {...props} />
				</Suspense>
			</>
		);
	};
}, 'withHoverEffectControls');

addFilter(
	'editor.BlockEdit',
	'designsetgo/hover-effects-controls',
	withHoverEffectControls
);

/**
 * Add hover effect classes to the editor block wrapper for live preview.
 */
const withHoverEffectClass = createHigherOrderComponent((BlockListBlock) => {
	return (props) => {
		const { name, attributes, className, wrapperProps = {} } = props;

		if (!SUPPORTED_BLOCKS.includes(name)) {
			return <BlockListBlock {...props} />;
		}

		const effectClasses = getHoverEffectClasses(
			attributes?.dsgoHoverEffect
		);
		if (!effectClasses) {
			return <BlockListBlock {...props} />;
		}

		const mergedClassName = classnames(
			className,
			wrapperProps.className,
			effectClasses
		);
		const updatedWrapperProps = {
			...wrapperProps,
			className: mergedClassName || undefined,
		};

		return <BlockListBlock {...props} wrapperProps={updatedWrapperProps} />;
	};
}, 'withHoverEffectClass');

addFilter(
	'editor.BlockListBlock',
	'designsetgo/hover-effects-class',
	withHoverEffectClass
);

/**
 * Write hover effect classes into the saved markup so the effect
 * applies on the frontend.
 *
 * @param {Object} extraProps Save props.
 * @param {Object} blockType  Block type.
 * @param {Object} attributes Block attributes.
 * @return {Object} Filtered save props.
 */
function addHoverEffectSaveProps(extraProps, blockType, attributes) {
	if (!SUPPORTED_BLOCKS.includes(blockType.name)) {
		return extraProps;
	}

	const effectClasses = getHoverEffectClasses(attributes?.dsgoHoverEffect);
	if (!effectClasses) {
		return extraProps;
	}

	return {
		...extraProps,
		className: classnames(extraProps.className, effectClasses),
	};
}

addFilter(
	'blocks.getSaveContent.extraProps',
	'designsetgo/hover-effects-save-props',
	addHoverEffectSaveProps
);
