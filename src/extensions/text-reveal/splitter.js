/**
 * Shared text-node splitter for effects that need individually addressable
 * words or characters. This module intentionally has no frontend lifecycle
 * side effects so blocks can reuse it without registering Text Reveal.
 */

/* global NodeFilter */

export const UNIT_CLASS = 'dsgo-text-reveal-unit';

/**
 * Wrap text nodes in spans for word or character split mode.
 *
 * Uses a TreeWalker so inline markup (links, emphasis) survives the split.
 * Each unit carries its running position as `--dsgo-unit-index`, indexed
 * continuously across text nodes so a heading broken up by a link still
 * counts in reading order. The shipped effects do not read it - reveal order
 * comes from the scroll-progress walk in `updateRevealProgress`, and a
 * per-unit transition delay on top of that would only lag it - but it is a
 * stable hook for author CSS.
 *
 * @param {HTMLElement} element   The element to process.
 * @param {string}      splitMode 'word' or 'character'.
 */
export function wrapTextNodes(element, splitMode) {
	// Already split - re-running would nest units inside units.
	if (element.querySelector(`.${UNIT_CLASS}`)) {
		return;
	}

	// Preserve original text content for screen readers.
	const originalText = element.textContent;

	if (!originalText || !originalText.trim()) {
		return;
	}

	element.setAttribute('aria-label', originalText);
	// Use TreeWalker to find all text nodes while preserving HTML structure.
	const walker = document.createTreeWalker(
		element,
		NodeFilter.SHOW_TEXT,
		null,
		false
	);

	const textNodes = [];
	let node;
	while ((node = walker.nextNode())) {
		// Skip empty or whitespace-only nodes.
		if (node.textContent.trim()) {
			textNodes.push(node);
		}
	}

	let index = 0;

	/**
	 * Build one unit span.
	 *
	 * @param {string} text Unit text.
	 * @return {HTMLElement} The span.
	 */
	const makeUnit = (text) => {
		const span = document.createElement('span');
		span.className = UNIT_CLASS;
		span.setAttribute('aria-hidden', 'true');
		span.style.setProperty('--dsgo-unit-index', String(index));
		span.textContent = text;
		index++;
		return span;
	};

	// Process each text node.
	textNodes.forEach((textNode) => {
		const parent = textNode.parentNode;
		const fragment = document.createDocumentFragment();

		if (splitMode === 'character') {
			textNode.textContent.split('').forEach((character) => {
				if (character === ' ') {
					fragment.appendChild(document.createTextNode(' '));
				} else {
					fragment.appendChild(makeUnit(character));
				}
			});
		} else {
			textNode.textContent.split(/(\s+)/).forEach((word) => {
				if (/^\s+$/.test(word)) {
					fragment.appendChild(document.createTextNode(word));
				} else if (word) {
					fragment.appendChild(makeUnit(word));
				}
			});
		}

		parent.replaceChild(fragment, textNode);
	});
}
