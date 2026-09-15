/**
 * Server-side KSES for builds whose submitter lacked `unfiltered_html`.
 *
 * The server filters a submitter's attribute strings when the tree is
 * stored, but not the markup those attributes render into — a `javascript:`
 * URL in a URL attribute or an unsafe inline style only appears once
 * `save()` runs here. Before such a build is applied, its assembled markup
 * goes through `POST /designsetgo/v1/agent-build/{id}/sanitize`
 * (`wp_kses( $markup, 'post' )`, the filter core runs on that submitter's
 * own saves). The filtered markup is parsed again and applied only when the
 * filter changed nothing that matters: the same blocks in the same
 * structure, the same attribute values, and every block valid against its
 * current `save()`. A build the filter changed fails instead of being
 * applied half-stripped. Byte equality is deliberately not required — KSES
 * normalizes harmless things like `<br/>` to `<br />`.
 *
 * Pure aside from the injected callbacks, like `./finish-build.js`.
 */
import { childPath } from '../../tree';

const CHANGED_CODE = 'designsetgo_sanitized_content_changed';

/** Same cap the sanitize route enforces on `markup`. */
export const MAX_MARKUP_BYTES = 1048576;

/** Attribute names listed in a reason before it is truncated. */
const MAX_LISTED_ATTRIBUTES = 10;

/**
 * @param {string} text Any string.
 * @return {number} Its UTF-8 byte length.
 */
function utf8Bytes(text) {
	let bytes = 0;
	for (const character of text) {
		const code = character.codePointAt(0);
		if (code < 0x80) {
			bytes += 1;
		} else if (code < 0x800) {
			bytes += 2;
		} else if (code < 0x10000) {
			bytes += 3;
		} else {
			bytes += 4;
		}
	}
	return bytes;
}

/**
 * Normalizes an attribute value for comparison: rich-text values (e.g.
 * `RichTextData`) compare by their HTML string.
 *
 * @param {*} value Attribute value.
 * @return {*} Comparable plain value.
 */
function normalize(value) {
	if (value && typeof value.toHTMLString === 'function') {
		return value.toHTMLString();
	}
	if (Array.isArray(value)) {
		return value.map(normalize);
	}
	if (value && typeof value === 'object') {
		return Object.fromEntries(
			Object.keys(value)
				.sort()
				.map((key) => [key, normalize(value[key])])
		);
	}
	return value;
}

/**
 * @param {Object} before Attributes parsed from the assembled markup.
 * @param {Object} after  Attributes parsed from the sanitized markup.
 * @return {string[]} Names of top-level attributes whose values differ.
 */
function changedAttributes(before = {}, after = {}) {
	const names = new Set([...Object.keys(before), ...Object.keys(after)]);

	return [...names].filter(
		(name) =>
			JSON.stringify(normalize(before[name])) !==
			JSON.stringify(normalize(after[name]))
	);
}

/**
 * @param {string}   path       Block path.
 * @param {string}   block      Block name.
 * @param {string[]} attributes Changed attribute names.
 * @param {boolean}  invalid    Whether the block no longer validates.
 * @return {{ path: string, block: string, reason: string, code: string }} Invalid entry.
 */
function changedEntry(path, block, attributes = [], invalid = false) {
	const parts = [];
	if (attributes.length) {
		const listed = attributes.slice(0, MAX_LISTED_ATTRIBUTES).join(', ');
		const more = attributes.length > MAX_LISTED_ATTRIBUTES ? ', …' : '';
		parts.push(`changed attributes: ${listed}${more}`);
	}
	if (invalid) {
		parts.push('left the block invalid against its current save()');
	}
	const detail = parts.length
		? parts.join('; ')
		: 'changed the block structure';

	return {
		path,
		block,
		reason: `Filtering this block's markup for a submitter without unfiltered_html ${detail}.`,
		code: CHANGED_CODE,
	};
}

/**
 * Compares blocks parsed from the assembled markup with blocks parsed from
 * its sanitized markup. Reports, once per block, each block whose name or
 * child count changed (its subtree is not compared further), whose
 * attribute values changed, or that is not valid against its current
 * `save()` — `isValid` alone is not trusted, since the parser also marks a
 * block valid after migrating it through a deprecation.
 *
 * @param {Object[]} before        Blocks parsed from the assembled markup.
 * @param {Object[]} after         Blocks parsed from the sanitized markup.
 * @param {Function} validateBlock `wp.blocks.validateBlock` — `(block) => [isValid, log]`.
 * @param {string}   [parentPath]  Path of the parent, or '' for the root.
 * @return {{ path: string, block: string, reason: string, code: string }[]} Changed blocks.
 */
export function findSanitizedChanges(
	before,
	after,
	validateBlock,
	parentPath = ''
) {
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

		const attributes = changedAttributes(
			original.attributes,
			filtered.attributes
		);
		const invalid =
			filtered.isValid !== true || validateBlock(filtered)[0] !== true;

		if (attributes.length || invalid) {
			changes.push(
				changedEntry(path, original.name, attributes, invalid)
			);
		}

		changes.push(
			...findSanitizedChanges(
				originalChildren,
				filteredChildren,
				validateBlock,
				path
			)
		);
	});

	return changes;
}

/**
 * @param {string} reason Why the markup cannot be applied.
 * @return {{ok: false, invalid: Object[]}} Failure result.
 */
function failure(reason) {
	return { ok: false, invalid: [{ path: '', block: '', reason }] };
}

/**
 * Sends assembled markup through the sanitize route and returns the blocks
 * to apply. Never throws.
 *
 * @param {Object}   options
 * @param {Function} options.sanitizeMarkup `({ buildId, markup }) => Promise<{ markup: string }>`.
 * @param {Function} options.parse          `wp.blocks.parse`.
 * @param {Function} options.validateBlock  `wp.blocks.validateBlock`.
 * @param {string}   options.buildId        The pending build's id.
 * @param {string}   options.markup         Assembled markup.
 * @param {Object[]} options.parsedBlocks   Blocks parsed from `markup`.
 * @return {Promise<{ok: true, blocks: Object[]}|{ok: false, invalid: Object[]}>} Sanitized blocks, or why they cannot be applied.
 */
export async function sanitizeAssembled({
	sanitizeMarkup,
	parse,
	validateBlock,
	buildId,
	markup,
	parsedBlocks,
}) {
	if (utf8Bytes(markup) > MAX_MARKUP_BYTES) {
		return failure('sanitize markup too large');
	}

	let sanitized;
	try {
		const response = await sanitizeMarkup({ buildId, markup });
		sanitized = response && response.markup;
	} catch (error) {
		sanitized = undefined;
	}

	if (typeof sanitized !== 'string') {
		return failure('sanitize request failed');
	}

	try {
		const blocks = parse(sanitized);
		const invalid = findSanitizedChanges(
			parsedBlocks,
			blocks,
			validateBlock
		);

		return invalid.length ? { ok: false, invalid } : { ok: true, blocks };
	} catch (error) {
		return failure('sanitize validation failed');
	}
}
