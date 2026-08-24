/**
 * Advanced Heading frontend animation.
 *
 * Saved markup deliberately contains the first word as readable content. This
 * controller only enhances a validated rotating headline, so an invalid or
 * unavailable runtime can never hide the author’s fallback copy.
 */

import { wrapTextNodes } from '../../extensions/text-reveal/splitter';

const HEADLINE_SELECTOR =
	'[data-dsgo-animated-headline="true"][data-dsgo-animated-headline-mode="rotating"]';
const WORD_SELECTOR = '.dsgo-heading-segment__animated';
const ROTATING_EFFECTS = new Set([
	'typing',
	'clip',
	'flip',
	'swirl',
	'blinds',
	'drop-in',
	'wave',
	'slide',
	'slide-down',
]);
const TEXT_REVEAL_EFFECTS = new Set(['typing']);
const MIN_DURATION = 250;
const MAX_DURATION = 10000;
const MAX_DELAY = 10000;
const MAX_WORDS = 50;
const MAX_WORD_LENGTH = 200;
const ROTATING_DIRECTIONS = new Set(['forward', 'reverse']);

// A heading has at most one interval regardless of re-initialisation.
const headlineStates = new WeakMap();
let visibilityListenerAttached = false;

/**
 * Return a saved integer only when it remains in its explicit bounds.
 *
 * @param {string|undefined} value Saved data value.
 * @param {number}           min   Inclusive lower bound.
 * @param {number}           max   Inclusive upper bound.
 * @return {number|null} Valid integer, or null.
 */
function parseBoundedInteger(value, min, max) {
	if (!/^\d+$/.test(value || '')) {
		return null;
	}

	const number = Number(value);

	return Number.isSafeInteger(number) && number >= min && number <= max
		? number
		: null;
}

/**
 * Parse the saved word payload without trusting it as executable data.
 *
 * @param {string|undefined} value Saved JSON word list.
 * @return {string[]} Valid trimmed words, or an empty list.
 */
function parseWords(value) {
	try {
		const candidate = JSON.parse(value || '[]');

		if (!Array.isArray(candidate) || candidate.length === 0) {
			return [];
		}

		const words = candidate.map((word) =>
			typeof word === 'string' ? word.trim() : ''
		);

		if (
			words.length > MAX_WORDS ||
			words.some((word) => !word || word.length > MAX_WORD_LENGTH)
		) {
			return [];
		}

		return words;
	} catch {
		return [];
	}
}

/**
 * Read and verify only the data this runtime owns.
 *
 * @param {HTMLElement} heading Saved heading element.
 * @return {Object|null} Safe runtime settings, or null.
 */
function getSettings(heading) {
	const effect = heading.dataset.dsgoAnimatedHeadlineEffect;
	const wordElement = heading.querySelector(WORD_SELECTOR);
	const words = parseWords(wordElement?.dataset.dsgoAnimatedWords);

	if (!ROTATING_EFFECTS.has(effect) || !wordElement || !words.length) {
		return null;
	}

	const duration = parseBoundedInteger(
		heading.dataset.dsgoAnimatedHeadlineDuration,
		MIN_DURATION,
		MAX_DURATION
	);
	const delay = parseBoundedInteger(
		heading.dataset.dsgoAnimatedHeadlineDelay,
		0,
		MAX_DELAY
	);
	const loop = heading.dataset.dsgoAnimatedHeadlineLoop;
	const direction =
		heading.dataset.dsgoAnimatedHeadlineDirection || 'forward';

	if (
		duration === null ||
		delay === null ||
		!['true', 'false'].includes(loop) ||
		!ROTATING_DIRECTIONS.has(direction)
	) {
		return null;
	}

	return {
		delay,
		duration,
		direction,
		effect,
		loop: loop === 'true',
		wordElement,
		words,
	};
}

/**
 * Announce the rotating words once, statically, instead of on every change.
 *
 * The visible word can change as often as every MIN_DURATION milliseconds and
 * a looping headline never stops, so a live region on the rotating element
 * would re-announce indefinitely. Hiding that element and reading the whole
 * list once conveys the same content without the churn. This runs only when
 * the animation actually starts, so a reduced-motion or scriptless visitor
 * keeps the saved first word as ordinary readable content.
 *
 * @param {Object} state Runtime heading state.
 */
function addAccessibleWordSummary(state) {
	const { wordElement, words } = state;
	const summary = document.createElement('span');

	summary.className = 'screen-reader-text';
	summary.textContent = words.join(', ');

	wordElement.setAttribute('aria-hidden', 'true');
	wordElement.after(summary);
}

/**
 * Keep one actual word readable, using Text Reveal’s established splitter
 * only for the typing effect. Other effects animate the word as one unit.
 *
 * @param {Object} state Runtime heading state.
 */
function renderCurrentWord(state) {
	const word = state.words[state.index];
	const { wordElement } = state;

	wordElement.classList.remove('is-active');
	wordElement.replaceChildren(document.createTextNode(word));
	// Re-adding the class after a reflow restarts the selected CSS effect for
	// every new word without adding a second animation implementation.
	void wordElement.offsetWidth;
	wordElement.classList.add('is-active');

	if (TEXT_REVEAL_EFFECTS.has(state.effect)) {
		wrapTextNodes(wordElement, 'character');
	}
}

/**
 * Stop a state’s outstanding interval.
 *
 * @param {Object} state Runtime heading state.
 */
function pause(state) {
	if (state.timer !== null) {
		window.clearInterval(state.timer);
		state.timer = null;
	}
}

/**
 * Advance a single headline and retain the final word for non-looping modes.
 *
 * @param {Object} state Runtime heading state.
 */
function advance(state) {
	if (!state.wordElement.isConnected) {
		pause(state);
		headlineStates.delete(state.heading);
		return;
	}

	const nextIndex = state.index + (state.direction === 'reverse' ? -1 : 1);

	if (nextIndex < 0 || nextIndex >= state.words.length) {
		if (!state.loop) {
			state.completed = true;
			pause(state);
			return;
		}

		state.index =
			state.direction === 'reverse' ? state.words.length - 1 : 0;
	} else {
		state.index = nextIndex;
	}

	renderCurrentWord(state);

	if (
		!state.loop &&
		state.index ===
			(state.direction === 'reverse' ? 0 : state.words.length - 1)
	) {
		state.completed = true;
		pause(state);
	}
}

/**
 * Start a state only when motion is allowed and there are words to rotate.
 *
 * @param {Object} state Runtime heading state.
 */
function resume(state) {
	if (
		state.timer !== null ||
		state.completed ||
		document.hidden ||
		window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
		state.words.length < 2
	) {
		return;
	}

	const interval = state.duration + state.delay;
	state.timer = window.setInterval(() => advance(state), interval);
}

/**
 * Attach a one-time visibility listener. Querying current markup avoids
 * retaining detached headings while preserving the WeakMap ownership model.
 */
function attachVisibilityListener() {
	if (visibilityListenerAttached) {
		return;
	}

	visibilityListenerAttached = true;
	document.addEventListener('visibilitychange', () => {
		document.querySelectorAll(HEADLINE_SELECTOR).forEach((heading) => {
			const state = headlineStates.get(heading);

			if (!state) {
				return;
			}

			if (document.hidden) {
				pause(state);
			} else {
				resume(state);
			}
		});
	});
}

/**
 * Initialise all saved Advanced Headings within a root.
 *
 * @param {Element|Document} root DOM subtree to initialise.
 */
export function initAnimatedHeadlines(root = document) {
	attachVisibilityListener();

	const headings = [];
	if (root.matches?.(HEADLINE_SELECTOR)) {
		headings.push(root);
	}
	root.querySelectorAll?.(HEADLINE_SELECTOR).forEach((heading) =>
		headings.push(heading)
	);

	headings.forEach((heading) => {
		if (headlineStates.has(heading)) {
			return;
		}

		const settings = getSettings(heading);
		if (!settings) {
			return;
		}

		// Leave the server-saved first word untouched when motion is disabled.
		// In particular, reverse rotation otherwise swaps in the last word before
		// `resume()` has a chance to decline the animation.
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return;
		}

		const state = {
			...settings,
			heading,
			index:
				settings.direction === 'reverse'
					? settings.words.length - 1
					: 0,
			timer: null,
			completed: false,
		};
		headlineStates.set(heading, state);
		addAccessibleWordSummary(state);
		renderCurrentWord(state);
		resume(state);
	});
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () =>
		initAnimatedHeadlines()
	);
} else {
	initAnimatedHeadlines();
}

document.addEventListener('dsgo-content-loaded', () => initAnimatedHeadlines());
