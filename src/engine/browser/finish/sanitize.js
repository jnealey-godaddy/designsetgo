/**
 * Server-side KSES for builds whose submitter lacked `unfiltered_html`.
 *
 * The server filters a submitter's attribute strings when the tree is
 * stored, but not the markup those attributes render into — a `javascript:`
 * URL in a URL attribute or an unsafe inline style only appears once
 * `save()` runs here. Before such a build is applied, its assembled markup
 * goes through `POST /designsetgo/v1/agent-build/{id}/sanitize`
 * (`wp_kses( $markup, 'post' )`, the filter core runs on that submitter's
 * own saves). The filtered markup is parsed again and applied only when
 * every block is still valid and the block structure is unchanged; a build
 * the filter changed fails instead of being applied half-stripped.
 *
 * Pure aside from the injected callbacks, like `./finish-build.js`.
 */
import { childPath } from '../../tree';

const CHANGED_CODE = 'designsetgo_sanitized_content_changed';

/**
 * @param {string} path  Block path.
 * @param {string} block Block name.
 * @return {{ path: string, block: string, reason: string, code: string }} Invalid entry.
 */
function changedEntry(path, block) {
	return {
		path,
		block,
		reason: "Filtering this block's markup for a submitter without unfiltered_html changed it.",
		code: CHANGED_CODE,
	};
}

/**
 * Compares blocks parsed from the assembled markup with blocks parsed from
 * its sanitized markup. Reports each block that is no longer valid, and
 * each block whose name or child count changed (its subtree is not
 * compared further).
 *
 * @param {Object[]} before       Blocks parsed from the assembled markup.
 * @param {Object[]} after        Blocks parsed from the sanitized markup.
 * @param {string}   [parentPath] Path of the parent, or '' for the root.
 * @return {{ path: string, block: string, reason: string, code: string }[]} Changed blocks.
 */
export function findSanitizedChanges(before, after, parentPath = '') {
	if (!parentPath && before.length !== after.length) {
		return [changedEntry('blocks', '')];
	}

	const changes = [];

	before.forEach((original, index) => {
		const filtered = after[index];
		const path = childPath(parentPath, index);
		const originalChildren = original.innerBlocks || [];
		const filteredChildren = filtered.innerBlocks || [];

		if (
			filtered.name !== original.name ||
			filteredChildren.length !== originalChildren.length
		) {
			changes.push(changedEntry(path, original.name));
			return;
		}

		if (filtered.isValid !== true) {
			changes.push(changedEntry(path, original.name));
		}

		changes.push(
			...findSanitizedChanges(originalChildren, filteredChildren, path)
		);
	});

	return changes;
}

/**
 * Sends assembled markup through the sanitize route and returns the blocks
 * to apply. Never throws.
 *
 * @param {Object}   options
 * @param {Function} options.sanitizeMarkup `({ buildId, markup }) => Promise<{ markup: string }>`.
 * @param {Function} options.parse          `wp.blocks.parse`.
 * @param {string}   options.buildId        The pending build's id.
 * @param {string}   options.markup         Assembled markup.
 * @param {Object[]} options.parsedBlocks   Blocks parsed from `markup`.
 * @return {Promise<{ok: true, blocks: Object[]}|{ok: false, invalid: Object[]}>} Sanitized blocks, or why they cannot be applied.
 */
export async function sanitizeAssembled({
	sanitizeMarkup,
	parse,
	buildId,
	markup,
	parsedBlocks,
}) {
	let sanitized;

	try {
		const response = await sanitizeMarkup({ buildId, markup });
		sanitized = response && response.markup;
	} catch (error) {
		sanitized = undefined;
	}

	if (typeof sanitized !== 'string') {
		return {
			ok: false,
			invalid: [
				{ path: '', block: '', reason: 'sanitize request failed' },
			],
		};
	}

	const blocks = parse(sanitized);
	const invalid = findSanitizedChanges(parsedBlocks, blocks);

	return invalid.length ? { ok: false, invalid } : { ok: true, blocks };
}
