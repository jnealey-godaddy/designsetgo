/**
 * Interaction Layers - Target resolution
 *
 * Maps an interaction's target spec onto concrete elements.
 *
 * @package
 */

/**
 * Resolve an interaction's targets.
 *
 * @param {Object}       interaction Interaction config.
 * @param {Element|null} sourceEl    Element the interaction is declared on.
 * @return {Element[]} Matched elements, possibly empty.
 */
export function resolveTarget(interaction, sourceEl) {
	if (!sourceEl) {
		return [];
	}

	const { targetMode = 'self', targetSelector = '' } = interaction || {};

	if ('self' === targetMode) {
		return [sourceEl];
	}

	if (!targetSelector) {
		return [];
	}

	try {
		if ('parent' === targetMode) {
			const ancestor = sourceEl.closest(targetSelector);
			return ancestor ? [ancestor] : [];
		}

		return Array.from(document.querySelectorAll(targetSelector));
	} catch (e) {
		// Author-supplied selector; a syntax error must not break the page.
		return [];
	}
}
