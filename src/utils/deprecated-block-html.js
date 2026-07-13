/**
 * Stored markup for a deprecation's isEligible().
 *
 * WordPress calls isEligible as:
 *
 *     isEligible( attributes, innerBlocks, { blockNode, block } )
 *
 * (@wordpress/blocks → api/parser/apply-block-deprecated-versions.js). There is
 * NO `innerHTML` key on that third argument — destructuring one yields
 * `undefined`, and because every guard opens with a truthiness check, the guard
 * then silently returns false forever. This helper exists so that contract lives
 * in exactly one place instead of being re-derived at every call site.
 *
 * `blockNode.innerHTML` is the raw parsed block's own HTML (inner blocks
 * excluded); `block.originalContent` is the equivalent on the parsed block, and
 * is the fallback when only that is to hand. Returns `''` — never `undefined` —
 * so callers can use `.includes()` / regex tests without guarding first, and so
 * a missing third argument can never produce a false positive.
 *
 * @param {Object} [extra]           WordPress's third isEligible argument.
 * @param {Object} [extra.blockNode] Raw parsed block; carries `innerHTML`.
 * @param {Object} [extra.block]     Parsed block; carries `originalContent`.
 * @return {string} The block's stored markup, or `''` when unavailable.
 */
export function getDeprecatedBlockHTML(extra = {}) {
	return extra?.blockNode?.innerHTML ?? extra?.block?.originalContent ?? '';
}
