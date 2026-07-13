/**
 * Scroll Accordion Block - Deprecated Versions
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { getDeprecatedBlockHTML } from '../../utils/deprecated-block-html';

/**
 * Inline layout constants — the version before the constant layout moved to
 * style.scss.
 *
 * The root serialized `width:100%; align-self:stretch` and the items wrapper
 * serialized `display:flex; flex-direction:column`. None of it varied by
 * attribute, and style.scss already declared all four verbatim, so the inline
 * copies were pure duplication that no Style Kit could override. Only the
 * author-controlled `alignItems` is still written inline.
 *
 * Markup-only change: same attributes, same classes — so migrate() is a
 * passthrough.
 *
 * NOTE: WordPress calls `isEligible(attributes, innerBlocks, { blockNode, block })`
 * — there is no `innerHTML` key on that third argument. The stored markup is
 * reached via `blockNode.innerHTML` / `block.originalContent`. It only matters
 * for a block that is otherwise VALID; for an invalid one WordPress skips
 * isEligible and picks the deprecation whose save() reproduces the stored HTML.
 */
const v1 = {
	// Must mirror block.json exactly. A deprecation's `supports` that omits a
	// group makes WordPress strip those attributes (backgroundColor, textColor,
	// gradient, fontSize, style, …) BEFORE migrate() runs — silently.
	attributes: {
		align: { type: 'string' },
		textAlign: { type: 'string' },
		alignItems: { type: 'string', default: 'flex-start' },
	},
	supports: {
		anchor: true,
		align: ['wide', 'full'],
		html: false,
		spacing: { margin: true, padding: true, blockGap: true },
		color: { background: true, text: true, gradients: true },
		typography: { fontSize: true, lineHeight: true },
		layout: { allowEditing: false },
	},
	isEligible(attributes, innerBlocks, extra) {
		const html = getDeprecatedBlockHTML(extra);
		return (
			html.includes('dsgo-scroll-accordion') &&
			html.includes('align-self:stretch')
		);
	},
	migrate(attributes) {
		// Markup-only change.
		return attributes;
	},
	save({ attributes }) {
		const { alignItems } = attributes;

		const innerStyles = {
			display: 'flex',
			flexDirection: 'column',
			alignItems: alignItems || 'flex-start',
		};

		const blockProps = useBlockProps.save({
			className: 'dsgo-scroll-accordion',
			style: {
				width: '100%',
				alignSelf: 'stretch',
			},
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-scroll-accordion__items',
			style: innerStyles,
		});

		return (
			<div {...blockProps}>
				<div {...innerBlocksProps} />
			</div>
		);
	},
};

export { v1 };

export default [v1];
