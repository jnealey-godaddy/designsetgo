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

const ATTR = 'data-dsgo-interactions';
const FIRED = new WeakMap(); // Element -> Set of interaction ids already fired.

let delegatesAttached = false;
let observer = null;

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
		runAction(
			interaction.action,
			resolveTarget(interaction, el),
			interaction,
			el
		);
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
			const el = sourceFor(e.target);
			if (el) {
				fire(el, 'hover', e);
			}
		},
		true // mouseenter does not bubble; capture reaches it.
	);

	document.addEventListener('keydown', (e) => {
		const el = sourceFor(e.target);
		if (!el) {
			return;
		}
		fire(el, 'keydown', e);
		// Space/Enter on a synthesised button must also fire the click layer.
		if (
			'button' === el.getAttribute('role') &&
			['Enter', ' '].includes(e.key)
		) {
			e.preventDefault();
			fire(el, 'click', e);
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
					if (entry.isIntersecting) {
						fire(entry.target, 'inView');
					}
				});
			},
			{ rootMargin: '0px 0px -10% 0px' }
		);
	}

	root.querySelectorAll(`[${ATTR}]`).forEach((el) => {
		const needsInView = readInteractions(el).some(
			(i) => 'inView' === i.trigger
		);
		if (needsInView) {
			observer.observe(el);
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
 * Initialise interactions. Idempotent — safe after a DOM swap.
 *
 * @param {Element|Document} root Subtree to scan. Defaults to the document.
 */
export function initInteractions(root = document) {
	attachDelegates();
	ensureKeyboardSemantics(root);
	observeInView(root);
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
