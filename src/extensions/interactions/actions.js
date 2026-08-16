/**
 * Interaction Layers - Action registry
 *
 * Each action receives the resolved target list and the interaction config.
 *
 * @package
 */

/* global navigator */

// Attributes an author may never set. Event handlers execute script; `style`
// is allowed because it cannot execute, but `on*` is a direct XSS vector.
const FORBIDDEN_ATTRIBUTE = /^on/i;

function eachTarget(targets, fn) {
	(targets || []).forEach((el) => el && fn(el));
}

export const actionRegistry = {
	toggleClass(targets, { value }) {
		if (!value) {
			return;
		}
		eachTarget(targets, (el) => el.classList.toggle(value));
	},

	addClass(targets, { value }) {
		if (!value) {
			return;
		}
		eachTarget(targets, (el) => el.classList.add(value));
	},

	removeClass(targets, { value }) {
		if (!value) {
			return;
		}
		eachTarget(targets, (el) => el.classList.remove(value));
	},

	setAttribute(targets, { attributeName, value }) {
		if (!attributeName || FORBIDDEN_ATTRIBUTE.test(attributeName)) {
			return;
		}
		eachTarget(targets, (el) =>
			el.setAttribute(attributeName, value ?? '')
		);
	},

	scrollTo(targets, { offset = 0 }) {
		const el = (targets || [])[0];
		if (!el) {
			return;
		}
		const reduced = window.matchMedia?.(
			'(prefers-reduced-motion: reduce)'
		)?.matches;
		const top =
			el.getBoundingClientRect().top +
			window.scrollY -
			Number(offset || 0);
		window.scrollTo({ top, behavior: reduced ? 'auto' : 'smooth' });
	},

	openModal(targets, { value }) {
		document.dispatchEvent(
			new CustomEvent('dsgo-modal-open', { detail: { modalId: value } })
		);
	},

	closeModal(targets, { value }) {
		document.dispatchEvent(
			new CustomEvent('dsgo-modal-close', { detail: { modalId: value } })
		);
	},

	copyToClipboard(targets, { value }) {
		const text = value || (targets || [])[0]?.textContent || '';
		navigator.clipboard?.writeText(text);
	},
};

/**
 * Register an additional action.
 *
 * @param {string}   name Action name.
 * @param {Function} fn   ( targets, interaction, sourceEl ) => void
 */
export function registerAction(name, fn) {
	actionRegistry[name] = fn;
}

/**
 * Run an action by name.
 *
 * @param {string}    name        Action name.
 * @param {Element[]} targets     Resolved targets.
 * @param {Object}    interaction Interaction config.
 * @param {Element}   sourceEl    Element the interaction is declared on.
 */
export function runAction(name, targets, interaction, sourceEl) {
	const fn = actionRegistry[name];
	if ('function' !== typeof fn) {
		return;
	}
	fn(targets, interaction || {}, sourceEl);
}
