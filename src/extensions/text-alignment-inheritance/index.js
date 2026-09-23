/**
 * Text Alignment Inheritance Extension
 *
 * Sets initial text alignment from parent container blocks on block insertion.
 * - New blocks inherit parent's textAlign setting once on insertion
 * - After insertion, blocks maintain their own alignment independently
 * - User can freely change alignment without parent interference
 *
 * @since 1.0.0
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useEffect, useRef } from '@wordpress/element';
import { shouldExtendBlock } from '../../utils/should-extend-block';
import { isExtensionEnabled } from '../../utils/is-extension-enabled';

/**
 * Track which blocks have already had alignment initialized
 * Using WeakMap so it doesn't persist to database or cause validation issues
 */
const initializedBlocks = new WeakMap();

/**
 * List of core blocks that support text alignment
 */
const TEXT_BLOCKS = [
	'core/paragraph',
	'core/heading',
	'core/list',
	'core/quote',
	'core/pullquote',
	'core/verse',
	'core/preformatted',
	'core/code',
];

/**
 * Add context support for parent text alignment
 */
addFilter(
	'blocks.registerBlockType',
	'designsetgo/add-alignment-inheritance-attributes',
	(settings, name) => {
		// Check user exclusion list first
		if (!shouldExtendBlock(name)) {
			return settings;
		}

		// Only apply to text blocks that support alignment
		if (!TEXT_BLOCKS.includes(name)) {
			return settings;
		}

		return {
			...settings,
			usesContext: [
				...(settings.usesContext || []),
				'designsetgo/textAlign',
			],
		};
	}
);

/**
 * Apply inherited alignment from parent container ONCE on insertion
 * Uses WeakMap to track initialization without affecting block validation
 */
const withAlignmentInheritance = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { name, attributes, setAttributes, context } = props;

		// Only apply to text blocks
		if (!TEXT_BLOCKS.includes(name)) {
			return <BlockEdit {...props} />;
		}

		const { textAlign } = attributes;
		const parentTextAlign = context['designsetgo/textAlign'];
		const isInitialized = useRef(initializedBlocks.get(props) || false);

		// One-time alignment inheritance on block insertion
		useEffect(() => {
			// Skip if already initialized
			if (isInitialized.current) {
				return;
			}

			// Mark as initialized immediately to prevent re-running
			isInitialized.current = true;
			initializedBlocks.set(props, true);

			// Only inherit if:
			// 1. Parent has alignment set
			// 2. This block has no alignment yet (newly inserted)
			if (parentTextAlign && !textAlign) {
				setAttributes({ textAlign: parentTextAlign });
			}
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, []); // Empty deps - only run once on mount

		return <BlockEdit {...props} />;
	};
}, 'withAlignmentInheritance');

// Switched off, new blocks simply don't inherit their parent's alignment;
// alignment already saved on blocks is untouched.
if (isExtensionEnabled('text-alignment-inheritance')) {
	addFilter(
		'editor.BlockEdit',
		'designsetgo/with-alignment-inheritance',
		withAlignmentInheritance
	);
}
