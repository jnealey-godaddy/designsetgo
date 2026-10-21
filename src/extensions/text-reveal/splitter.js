/**
 * Shared text-node splitter for effects that need individually addressable
 * words or characters. This module intentionally has no frontend lifecycle
 * side effects so blocks can reuse it without registering Text Reveal.
 */

/* global NodeFilter */

export const UNIT_CLASS = 'dsgo-text-reveal-unit';
export const SR_COPY_CLASS = 'dsgo-text-reveal-sr';

// Text inside these keeps its units exposed to assistive tech: aria-hiding
// them would strip the link or button of its accessible name.
const INTERACTIVE =
	'a, button, label, summary, [role="button"], [role="link"], [tabindex]';

// Visually hidden but still read, and unselectable so copying the text doesn't
// pick it up twice. Inline so it works wherever the splitter is reused,
// without depending on a particular stylesheet being loaded.
const SR_ONLY_STYLE =
	'position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0;user-select:none;';

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

	const originalText = element.textContent;

	if (!originalText || !originalText.trim()) {
		return;
	}

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
	 * @param {string}  text   Unit text.
	 * @param {boolean} hidden Whether to aria-hide the unit.
	 * @return {HTMLElement} The span.
	 */
	const makeUnit = (text, hidden) => {
		const span = document.createElement('span');
		span.className = UNIT_CLASS;
		if (hidden) {
			span.setAttribute('aria-hidden', 'true');
		}
		span.style.setProperty('--dsgo-unit-index', String(index));
		span.textContent = text;
		index++;
		return span;
	};

	// Process each text node.
	textNodes.forEach((textNode) => {
		const parent = textNode.parentNode;
		const fragment = document.createDocumentFragment();

		// Plain text: aria-hide the animated copy and keep the real text in a
		// visually hidden node (an aria-label on a <p> or <span> is not
		// honoured, and would hide the text of any link inside). Text inside a
		// link or button is left exposed instead, so the control keeps its name.
		const interactive = parent.closest?.(INTERACTIVE);
		const hidden = !(interactive && element.contains(interactive));

		if (hidden) {
			const copy = document.createElement('span');
			copy.className = SR_COPY_CLASS;
			copy.setAttribute('style', SR_ONLY_STYLE);
			copy.textContent = textNode.textContent;
			fragment.appendChild(copy);
		}

		if (splitMode === 'character') {
			textNode.textContent.split('').forEach((character) => {
				if (character === ' ') {
					fragment.appendChild(document.createTextNode(' '));
				} else {
					fragment.appendChild(makeUnit(character, hidden));
				}
			});
		} else {
			textNode.textContent.split(/(\s+)/).forEach((word) => {
				if (/^\s+$/.test(word)) {
					fragment.appendChild(document.createTextNode(word));
				} else if (word) {
					fragment.appendChild(makeUnit(word, hidden));
				}
			});
		}

		parent.replaceChild(fragment, textNode);
	});
}
