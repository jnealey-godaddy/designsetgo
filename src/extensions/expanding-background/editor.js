/**
 * Expanding Background Extension - Editor Integration
 *
 * @package
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment } from '@wordpress/element';
import ExpandingBackgroundPanel from './components/ExpandingBackgroundPanel';
import { SUPPORTED_BLOCKS } from './constants';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import { isExtensionEnabled } from '../../utils/is-extension-enabled';

/**
 * Add expanding background controls to the block editor
 */
const withExpandingBackgroundControls = createHigherOrderComponent(
	(BlockEdit) => {
		return (props) => {
			const { name } = props;

			// Only add controls to supported blocks
			if (!SUPPORTED_BLOCKS.includes(name)) {
				return <BlockEdit {...props} />;
			}

			return (
				<Fragment>
					<BlockEdit {...props} />
					<ExpandingBackgroundPanel {...props} />
				</Fragment>
			);
		};
	},
	'withExpandingBackgroundControls'
);

// Controls only: attributes and saved markup stay registered so existing
// content still validates when the extension is switched off.
if (isExtensionEnabled('expanding-background')) {
	addFilter(
		'editor.BlockEdit',
		'designsetgo/expanding-background-controls',
		withExpandingBackgroundControls
	);
}

/**
 * Add expanding background classes and styles to block wrapper in editor
 */
const addExpandingBackgroundEditorClasses = createHigherOrderComponent(
	(BlockListBlock) => {
		return (props) => {
			const { attributes, name } = props;

			// Only add classes to supported blocks
			if (!SUPPORTED_BLOCKS.includes(name)) {
				return <BlockListBlock {...props} />;
			}

			const { dsgoExpandingBgEnabled, dsgoExpandingBgColor } = attributes;

			// Add class and inline styles if enabled
			if (dsgoExpandingBgEnabled) {
				const className = [
					props.className,
					'has-dsgo-expanding-background',
				]
					.filter(Boolean)
					.join(' ');

				// Add inline style with CSS variable for the color
				const style = {
					...props.style,
					'--dsgo-expanding-bg-color':
						convertColorToCSSVar(dsgoExpandingBgColor) || '#e8e8e8',
				};

				return (
					<BlockListBlock
						{...props}
						className={className}
						style={style}
					/>
				);
			}

			return <BlockListBlock {...props} />;
		};
	},
	'addExpandingBackgroundEditorClasses'
);

addFilter(
	'editor.BlockListBlock',
	'designsetgo/expanding-background-editor-classes',
	addExpandingBackgroundEditorClasses
);

/**
 * Add expanding background attributes to save props
 *
 * @param {Object} extraProps Block save props
 * @param {Object} blockType  Block type
 * @param {Object} attributes Block attributes
 * @return {Object} Modified props
 */
function addExpandingBackgroundSaveProps(extraProps, blockType, attributes) {
	const {
		dsgoExpandingBgEnabled,
		dsgoExpandingBgColor,
		dsgoExpandingBgInitialSize,
		dsgoExpandingBgBlur,
		dsgoExpandingBgSpeed,
		dsgoExpandingBgTriggerOffset,
		dsgoExpandingBgCompletionPoint,
	} = attributes;

	// Only add props to supported blocks with the effect enabled
	if (!SUPPORTED_BLOCKS.includes(blockType.name) || !dsgoExpandingBgEnabled) {
		return extraProps;
	}

	return {
		...extraProps,
		className: [extraProps.className, 'has-dsgo-expanding-background']
			.filter(Boolean)
			.join(' '),
		style: {
			...(extraProps.style || {}),
			'--dsgo-expanding-bg-color':
				convertColorToCSSVar(dsgoExpandingBgColor) || '#e8e8e8',
		},
		'data-dsgo-expanding-bg-enabled': 'true',
		'data-dsgo-expanding-bg-color':
			convertColorToCSSVar(dsgoExpandingBgColor) || '',
		'data-dsgo-expanding-bg-initial-size': dsgoExpandingBgInitialSize || '',
		'data-dsgo-expanding-bg-blur': dsgoExpandingBgBlur || '',
		'data-dsgo-expanding-bg-speed': dsgoExpandingBgSpeed || '',
		'data-dsgo-expanding-bg-trigger-offset':
			dsgoExpandingBgTriggerOffset || '',
		'data-dsgo-expanding-bg-completion-point':
			dsgoExpandingBgCompletionPoint || '',
	};
}

addFilter(
	'blocks.getSaveContent.extraProps',
	'designsetgo/expanding-background-save-props',
	addExpandingBackgroundSaveProps
);
