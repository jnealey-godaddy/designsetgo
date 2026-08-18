/**
 * Text Reveal Extension - Editor
 *
 * Adds text reveal controls and classes to blocks in the editor
 *
 * @package
 * @since 1.0.0
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import TextRevealPanel from './components/TextRevealPanel';
import { SUPPORTED_BLOCKS } from './constants';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

/**
 * Add text reveal controls to block edit component
 */
const withTextRevealControls = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { attributes, setAttributes, name, clientId } = props;

		// Only add controls to supported blocks
		if (!SUPPORTED_BLOCKS.includes(name)) {
			return <BlockEdit {...props} />;
		}

		return (
			<>
				<BlockEdit {...props} />
				<TextRevealPanel
					attributes={attributes}
					setAttributes={setAttributes}
					clientId={clientId}
				/>
			</>
		);
	};
}, 'withTextRevealControls');

/**
 * Add text reveal classes to block wrapper in editor
 */
const withTextRevealClasses = createHigherOrderComponent((BlockListBlock) => {
	return (props) => {
		const { attributes, name } = props;
		const { dsgoTextRevealEnabled } = attributes;

		// Skip if not a supported block or text reveal not enabled
		if (!SUPPORTED_BLOCKS.includes(name) || !dsgoTextRevealEnabled) {
			return <BlockListBlock {...props} />;
		}

		// Build class name with text reveal indicator
		let className = props.className || '';
		className += ' has-dsgo-text-reveal';

		return <BlockListBlock {...props} className={className.trim()} />;
	};
}, 'withTextRevealClasses');

/**
 * Add text reveal data attributes to save props
 *
 * @param {Object} extraProps - Extra props to add to the block
 * @param {Object} blockType  - Block type object
 * @param {Object} attributes - Block attributes
 * @return {Object} Modified extra props with text reveal data attributes
 */
function addTextRevealSaveProps(extraProps, blockType, attributes) {
	// Skip if not a supported block
	if (!SUPPORTED_BLOCKS.includes(blockType.name)) {
		return extraProps;
	}

	const {
		dsgoTextRevealEnabled,
		dsgoTextRevealColor,
		dsgoTextRevealSplitMode,
		dsgoTextRevealTransition,
		dsgoTextRevealEffect,
	} = attributes;

	// Skip if text reveal not enabled
	if (!dsgoTextRevealEnabled) {
		return extraProps;
	}

	// Add data attributes for frontend JavaScript
	const dataAttributes = {
		'data-dsgo-text-reveal-enabled': 'true',
		'data-dsgo-text-reveal-color':
			convertColorToCSSVar(dsgoTextRevealColor) || '',
		'data-dsgo-text-reveal-split-mode': dsgoTextRevealSplitMode || 'word',
		'data-dsgo-text-reveal-transition': dsgoTextRevealTransition || 150,
		'data-dsgo-text-reveal-effect': dsgoTextRevealEffect || 'color',
	};

	// Add class
	let className = extraProps.className || '';
	className += ' has-dsgo-text-reveal';

	return {
		...extraProps,
		...dataAttributes,
		className: className.trim(),
	};
}

// Register filters
addFilter(
	'editor.BlockEdit',
	'designsetgo/text-reveal/with-controls',
	withTextRevealControls,
	100 // After core styling
);

addFilter(
	'editor.BlockListBlock',
	'designsetgo/text-reveal/with-classes',
	withTextRevealClasses
);

addFilter(
	'blocks.getSaveContent.extraProps',
	'designsetgo/text-reveal/save-props',
	addTextRevealSaveProps
);
