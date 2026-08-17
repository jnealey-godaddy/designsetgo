/**
 * Interaction Layers - Frontend runtime
 *
 * One delegated listener per event type on `document`. Never one listener
 * per element behind a shared flag — that starves every element after the
 * first and breaks silently after a DOM swap.
 *
 * @package
 */

/* global IntersectionObserver */

import { resolveTarget } from './resolve-target';
import { runAction } from './actions';
import { HIDDEN_CLASS, VISIBILITY_ACTIONS } from './visibility-contract';

const ATTR = 'data-dsgo-interactions';
const FIRED = new WeakMap(); // Element -> Set of interaction ids already fired.

let delegatesAttached = false;
let observer = null;
let keydownElements = [];
const observed = new Set(); // Elements handed to the IntersectionObserver.

/**
 * Read and parse an element's interaction list.
 *
 * @param {Element} el Element carrying the data attribute.
 * @return {Array} Interactions, empty on parse failure.
 */
function readInteractions(el) {
	const raw = el.getAttribute(ATTR);
	if (!raw) {
		return [];
	}
	try {
		const parsed = JSON.parse(raw);
		return Array.isArray(parsed) ? parsed : [];
	} catch (e) {
		return [];
	}
}

/**
 * Whether this interaction has already fired for this element.
 *
 * @param {Element} el          Source element.
 * @param {Object}  interaction Interaction config.
 * @return {boolean} True when it should be skipped.
 */
function alreadyFired(el, interaction) {
	if (!interaction.once) {
		return false;
	}
	const set = FIRED.get(el);
	return !!set && set.has(interaction.id);
}

/**
 * Record that a one-shot interaction has run.
 *
 * @param {Element} el          Source element.
 * @param {Object}  interaction Interaction config.
 */
function markFired(el, interaction) {
	if (!interaction.once) {
		return;
	}
	if (!FIRED.has(el)) {
		FIRED.set(el, new Set());
	}
	FIRED.get(el).add(interaction.id);
}

/**
 * Execute every interaction on an element matching a trigger.
 *
 * @param {Element} el      Source element.
 * @param {string}  trigger Trigger name.
 * @param {Event}   event   Originating event, may be undefined.
 */
function fire(el, trigger, event) {
	readInteractions(el).forEach((interaction) => {
		if (interaction.trigger !== trigger) {
			return;
		}
		if (alreadyFired(el, interaction)) {
			return;
		}
		if (
			'keydown' === trigger &&
			interaction.key &&
			event?.key !== interaction.key
		) {
			return;
		}
		// One bad interaction must not take the rest of the page with it.
		// These run inside a delegated document listener, so an uncaught
		// throw would abort every interaction queued behind it — on this
		// element and on every other element for the same event.
		try {
			runAction(
				interaction.action,
				resolveTarget(interaction, el),
				interaction,
				el
			);
		} catch (e) {
			return;
		}

		markFired(el, interaction);
	});
}

/**
 * Find the interaction-bearing ancestor of an event target.
 *
 * @param {EventTarget} target Event target.
 * @return {Element|null} The source element or null.
 */
function sourceFor(target) {
	if (!target || 'function' !== typeof target.closest) {
		return null;
	}
	return target.closest(`[${ATTR}]`);
}

/**
 * Rebuild the cache of elements carrying a keydown interaction.
 *
 * Key presses are page-scoped, so the alternative is querying the DOM and
 * re-parsing every interaction's JSON on every keystroke anywhere on the
 * page — including while the visitor is typing in a form field. The cache is
 * refreshed by initInteractions(), which already runs after a DOM swap.
 */
function refreshKeydownTargets() {
	keydownElements = Array.from(document.querySelectorAll(`[${ATTR}]`)).filter(
		(el) => readInteractions(el).some((i) => 'keydown' === i.trigger)
	);
}

/**
 * Attach the delegated document listeners. Runs at most once.
 */
function attachDelegates() {
	if (delegatesAttached) {
		return;
	}
	delegatesAttached = true;

	document.addEventListener('click', (e) => {
		const el = sourceFor(e.target);
		if (el) {
			fire(el, 'click', e);
		}
	});

	document.addEventListener(
		'mouseenter',
		(e) => {
			// Capture delivers mouseenter for descendants too, so `closest()`
			// would resolve back to the same source every time the pointer
			// crossed a child — making a hover + toggle flip-flop. The event
			// must have been targeted at the source element itself.
			const el = e.target;
			if (el?.nodeType === 1 && el.hasAttribute?.(ATTR)) {
				fire(el, 'hover', e);
			}
		},
		true // mouseenter does not bubble; capture reaches it.
	);

	document.addEventListener('keydown', (e) => {
		// Key presses are page-level: "press Escape to close" should work
		// wherever focus happens to be. Scoping this to the focused element
		// made keydown unreachable on any non-focusable block, since nothing
		// gives a plain div focus.
		keydownElements.forEach((el) => fire(el, 'keydown', e));

		// Space/Enter activating a synthesised button IS focus-scoped: it
		// stands in for a real click on the element the visitor is on.
		const focused = sourceFor(e.target);
		if (
			focused &&
			'button' === focused.getAttribute('role') &&
			['Enter', ' '].includes(e.key)
		) {
			e.preventDefault();
			fire(focused, 'click', e);
		}
	});

	document.addEventListener('mouseout', (e) => {
		if (e.relatedTarget || e.clientY > 0) {
			return;
		}
		document
			.querySelectorAll(`[${ATTR}]`)
			.forEach((el) => fire(el, 'exitIntent', e));
	});
}

/**
 * Attach the in-view observer to elements that need it.
 *
 * @param {Element|Document} root Subtree to scan.
 */
function observeInView(root) {
	if (!('IntersectionObserver' in window)) {
		return;
	}

	if (!observer) {
		observer = new IntersectionObserver(
			(entries) => {
				entries.forEach((entry) => {
					if (!entry.isIntersecting) {
						return;
					}
					fire(entry.target, 'inView');

					// A one-shot interaction has nothing left to do; keeping
					// the element observed costs a callback on every scroll
					// past it for the life of the page.
					const allOnce = readInteractions(entry.target)
						.filter((i) => 'inView' === i.trigger)
						.every((i) => i.once);
					if (allOnce) {
						observer.unobserve(entry.target);
						observed.delete(entry.target);
					}
				});
			},
			{ rootMargin: '0px 0px -10% 0px' }
		);
	}

	// A soft-reload navigation replaces the content region, detaching every
	// element observed for the old page. IntersectionObserver holds a strong
	// reference to its targets, so without this they — and their subtrees —
	// stay alive for the life of the tab.
	observed.forEach((el) => {
		if (!el.isConnected) {
			observer.unobserve(el);
			observed.delete(el);
		}
	});

	root.querySelectorAll(`[${ATTR}]`).forEach((el) => {
		const needsInView = readInteractions(el).some(
			(i) => 'inView' === i.trigger
		);
		if (needsInView) {
			observer.observe(el);
			observed.add(el);
		}
	});
}

/**
 * Give click-triggered non-interactive elements keyboard semantics.
 *
 * @param {Element|Document} root Subtree to scan.
 */
function ensureKeyboardSemantics(root) {
	const NATIVE = ['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'SUMMARY'];

	root.querySelectorAll(`[${ATTR}]`).forEach((el) => {
		const hasClick = readInteractions(el).some(
			(i) => 'click' === i.trigger
		);
		if (!hasClick || NATIVE.includes(el.tagName)) {
			return;
		}
		if (!el.hasAttribute('tabindex')) {
			el.setAttribute('tabindex', '0');
		}
		if (!el.hasAttribute('role')) {
			el.setAttribute('role', 'button');
		}
	});
}

/**
 * Publish the starting state of any control that toggles visibility.
 *
 * Without this the trigger has no aria-expanded until the first click, so a
 * screen-reader user is told nothing about a collapsed region until after
 * they have already operated it.
 *
 * @param {Element|Document} root Subtree to scan.
 */
function seedExpandedState(root) {
	root.querySelectorAll(`[${ATTR}]`).forEach((el) => {
		const visibility = readInteractions(el).filter((i) =>
			VISIBILITY_ACTIONS.includes(i.action)
		);

		if (!visibility.length || el.hasAttribute('aria-expanded')) {
			return;
		}

		const isControl =
			'BUTTON' === el.tagName ||
			'A' === el.tagName ||
			'button' === el.getAttribute('role');

		if (!isControl) {
			return;
		}

		const targets = resolveTarget(visibility[0], el);
		if (!targets.length) {
			return;
		}

		const anyVisible = targets.some(
			(t) => !t.classList.contains(HIDDEN_CLASS)
		);
		el.setAttribute('aria-expanded', anyVisible ? 'true' : 'false');
	});
}

/**
 * Initialise interactions. Idempotent — safe after a DOM swap.
 *
 * @param {Element|Document} root Subtree to scan. Defaults to the document.
 */
export function initInteractions(root = document) {
	attachDelegates();
	ensureKeyboardSemantics(root);
	seedExpandedState(root);
	observeInView(root);
	refreshKeydownTargets();
}

if ('undefined' !== typeof document) {
	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', () => initInteractions());
	} else {
		initInteractions();
	}
	// Soft-reload navigations replace the content region wholesale.
	document.addEventListener('dsgo-content-loaded', () => initInteractions());
}
