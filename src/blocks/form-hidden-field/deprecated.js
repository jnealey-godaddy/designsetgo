/**
 * Form Hidden Field Block - Deprecated Versions
 *
 * vStatic reproduces the last STATIC save (the block is now server-rendered via
 * render.php; save() returns null). isEligible matches any stored static hidden
 * field so existing content — and the current block-patterns HTML — migrates
 * silently (passthrough) with no "Attempt Recovery" warning.
 *
 * @since 2.5.0
 */

import { useBlockProps } from '@wordpress/block-editor';
import { getDeprecatedBlockHTML } from '../../utils/deprecated-block-html';

/**
 * Supports definition for deprecated versions.
 * Matches block.json supports.
 */
const sharedSupports = {
	html: false,
	anchor: false,
	customClassName: false,
	reusable: false,
};

/**
 * Shared attribute schema for the static deprecation (identical to block.json).
 */
const sharedAttributes = {
	fieldName: { type: 'string', default: '' },
	value: { type: 'string', default: '' },
};

/**
 * The last static markup, immediately before the block became server-rendered.
 */
const vStatic = {
	supports: sharedSupports,
	attributes: sharedAttributes,

	isEligible(attributes, innerBlocks, extra) {
		const innerHTML = getDeprecatedBlockHTML(extra);
		// Any stored static hidden field carries this wrapper class; the
		// dynamic block saves no inner HTML, so it never matches.
		return (
			Boolean(innerHTML) && innerHTML.includes('dsgo-form-field--hidden')
		);
	},

	save({ attributes }) {
		const { fieldName, value } = attributes;

		const blockProps = useBlockProps.save({
			className: 'dsgo-form-field dsgo-form-field--hidden',
		});

		return (
			<div {...blockProps}>
				<input
					type="hidden"
					name={fieldName}
					value={value}
					data-field-type="hidden"
				/>
			</div>
		);
	},

	migrate(attributes) {
		return attributes;
	},
};

export default [vStatic];
