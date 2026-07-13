/**
 * Tab Block - Deprecated Versions
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const sharedSupports = {
	html: false,
	reusable: false,
	align: ['left', 'center', 'right', 'wide', 'full'],
	spacing: {
		margin: false,
		padding: true,
		blockGap: true,
	},
	color: {
		background: true,
		text: true,
		link: true,
	},
	typography: {
		fontSize: true,
		lineHeight: true,
		fontFamily: true,
		fontWeight: true,
	},
};

/**
 * Version 1: Before adding "none" icon position option
 * - iconPosition attribute didn't exist (was always "left" implicitly)
 * - Blocks with icons always output data-icon-position="left"
 */
const v1 = {
	supports: sharedSupports,

	// No isEligible: this is a MARKUP-change deprecation, and WordPress only
	// consults isEligible for a block that is still VALID
	// (`if (block.isValid && !isEligible(...)) continue;` — see
	// @wordpress/blocks → api/parser/apply-block-deprecated-versions.js). A v1
	// block's markup no longer matches the current save(), so it parses INVALID,
	// isEligible is skipped entirely, and WordPress picks this version because
	// its save() reproduces the stored HTML. There is nothing left for an
	// isEligible to do here.
	//
	// It used to test `typeof attributes.iconPosition === 'undefined'`, which is
	// unsound: `attributes` here is the RAW comment JSON, and WordPress never
	// serializes an attribute equal to its default. A current tab left at the
	// default iconPosition ('none') therefore also has no `iconPosition` key, so
	// the guard claimed current content — and because v1's schema predates
	// `strokeWidth`, migrate() silently dropped it.

	attributes: {
		uniqueId: {
			type: 'string',
			default: '',
		},
		title: {
			type: 'string',
			default: 'Tab',
		},
		icon: {
			type: 'string',
			default: '',
		},
		// iconPosition attribute didn't exist in v1
		anchor: {
			type: 'string',
			default: '',
		},
		style: {
			type: 'object',
			default: {
				spacing: {
					padding: {
						top: 'var:preset|spacing|40',
						right: 'var:preset|spacing|40',
						bottom: 'var:preset|spacing|40',
						left: 'var:preset|spacing|40',
					},
				},
			},
		},
	},

	save({ attributes }) {
		const { uniqueId, title, anchor } = attributes;

		const blockProps = useBlockProps.save({
			className: 'dsgo-tab',
			role: 'tabpanel',
			'aria-labelledby': `tab-${uniqueId}`,
			'aria-label': title || `Tab ${uniqueId}`,
			id: `panel-${anchor || uniqueId}`,
			hidden: true,
			// v1 had NO data-icon attributes - icons were rendered by frontend JS
			// using the icon stored in block attributes, not data attributes
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-tab__content',
		});

		return (
			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		);
	},

	migrate(attributes) {
		// Migrate to new structure with iconPosition attribute
		// Note: Existing blocks with icons default to 'left' position
		// to preserve their appearance, while new blocks default to 'none'
		// to provide a cleaner UX where users must explicitly choose to show icons
		return {
			...attributes,
			// If icon exists, set iconPosition to "left" (old default)
			// If no icon, set to "none" (new default)
			iconPosition: attributes.icon ? 'left' : 'none',
		};
	},
};

export default [v1];
