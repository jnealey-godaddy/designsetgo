/**
 * `heading-order` (error, page-level): collects `core/heading` and
 * `designsetgo/advanced-heading` levels in document order and flags two
 * accessibility mistakes:
 *
 *  - More than one level-1 heading on the page — flagged on the second
 *    (and any subsequent) level-1 heading.
 *  - A downward jump of more than one level between consecutive headings
 *    (e.g. H2 straight to H4, skipping H3) — flagged on the later heading.
 *    An *upward* jump (H4 back to H2) is normal document structure and is
 *    not flagged.
 *
 * Both blocks default `level` to 2 when the attribute is omitted (trees
 * omit default-valued attributes): WP core's `core/heading` default, and
 * `designsetgo/advanced-heading`'s own default per
 * `src/blocks/advanced-heading/block.json`.
 *
 * Page-level rule (`checkTree`), since heading order is a property of the
 * whole document, not any single node. Plain ES module — no Node-only or
 * WordPress imports.
 */
import { walkTree } from '../../tree';

const DEFAULT_HEADING_LEVEL = 2;

/**
 * A heading block's effective level, or `null` if `node` isn't a heading.
 *
 * @param {Object} node Block node.
 * @return {number|null} Effective heading level, or `null`.
 */
function headingLevel(node) {
	if (
		node.name !== 'core/heading' &&
		node.name !== 'designsetgo/advanced-heading'
	) {
		return null;
	}
	const level = node.attributes?.level;
	return typeof level === 'number' ? level : DEFAULT_HEADING_LEVEL;
}

const rule = {
	id: 'heading-order',
	severity: 'error',

	checkTree(tree, ctx) {
		const headings = [];
		walkTree(
			Array.isArray(tree?.blocks) ? tree.blocks : [],
			(node, path) => {
				const level = headingLevel(node);
				if (level !== null) {
					headings.push({ level, path });
				}
			}
		);

		let seenLevelOne = false;
		let previousLevel = null;

		for (const current of headings) {
			if (current.level === 1) {
				if (seenLevelOne) {
					ctx.report(
						current.path,
						'More than one level-1 heading on the page.',
						'Use a single H1 per page; demote additional top-level headings.'
					);
				}
				seenLevelOne = true;
			}

			if (previousLevel !== null && current.level - previousLevel > 1) {
				ctx.report(
					current.path,
					`Heading level jumps from H${previousLevel} to H${current.level}, skipping a level.`,
					'Use consecutive heading levels (e.g. H2 then H3) instead of skipping one.'
				);
			}

			previousLevel = current.level;
		}
	},
};

export default rule;
