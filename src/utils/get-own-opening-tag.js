/**
 * Isolate a block's own root opening tag from its serialized innerHTML.
 *
 * Deprecations detect pre-refactor markup by testing a signature (an inline
 * style, a marker class) against the block's OWN wrapper, not the whole subtree
 * — blocks with an inner-blocks area can nest arbitrary content whose markup
 * would otherwise false-match. The wrapper is the block's root element, so its
 * opening tag runs from the `<` before the first occurrence of its root class
 * to the following `>`.
 *
 * @since 2.4.0
 *
 * @param {string} innerHTML     The block's serialized content (as passed to a
 *                               deprecation's `isEligible`).
 * @param {string} rootClassName A class unique to the block's own root element
 *                               (its first occurrence in innerHTML). Children
 *                               must not carry it earlier in the string.
 * @return {string} The root element's opening tag (e.g. `<div class="…" …>`),
 *                   or an empty string when innerHTML or the class is absent.
 */
export function getOwnOpeningTag(innerHTML, rootClassName) {
	if (!innerHTML) {
		return '';
	}
	const idx = innerHTML.indexOf(rootClassName);
	if (idx === -1) {
		return '';
	}
	const tagStart = innerHTML.lastIndexOf('<', idx);
	const tagEnd = innerHTML.indexOf('>', idx);
	if (tagStart === -1 || tagEnd === -1) {
		return '';
	}
	return innerHTML.slice(tagStart, tagEnd + 1);
}
