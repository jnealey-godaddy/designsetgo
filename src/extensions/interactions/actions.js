/**
 * Interaction Layers - Action registry
 *
 * Each action receives the resolved target list and the interaction config.
 *
 * @package
 */

/* global navigator */

// Import from the leaf module, never from ./constants — that module pulls in
// @wordpress/i18n, which is not available in this bundle. See hidden-class.js.
import { HIDDEN_CLASS } from './hidden-class';

// Attributes an author may never set. Event handlers execute script; `style`
// is allowed because it cannot execute, but `on*` is a direct XSS vector.
const FORBIDDEN_ATTRIBUTE = /^on/i;

function eachTarget(targets, fn) {
	(targets || []).forEach((el) => el && fn(el));
}

/**
 * Mirror a target's shown/hidden state onto the control that toggled it.
 *
 * A visitor using a screen reader has no way to know a click revealed
 * something unless the trigger says so. Only elements that present as
 * controls get the attribute; putting aria-expanded on a plain div would
 * be noise.
 *
 * @param {Element}   sourceEl Element the interaction is declared on.
 * @param {Element[]} targets  Resolved targets.
 */
function reflectExpandedState(sourceEl, targets) {
	if (!sourceEl) {
		return;
	}

	const isControl =
		'BUTTON' === sourceEl.tagName ||
		'A' === sourceEl.tagName ||
		'button' === sourceEl.getAttribute('role');

	if (!isControl) {
		return;
	}

	const anyVisible = (targets || []).some(
		(el) => el && !el.classList.contains(HIDDEN_CLASS)
	);

	sourceEl.setAttribute('aria-expanded', anyVisible ? 'true' : 'false');
}

export const actionRegistry = {
	show(targets, config, sourceEl) {
		eachTarget(targets, (el) => el.classList.remove(HIDDEN_CLASS));
		reflectExpandedState(sourceEl, targets);
	},

	hide(targets, config, sourceEl) {
		eachTarget(targets, (el) => el.classList.add(HIDDEN_CLASS));
		reflectExpandedState(sourceEl, targets);
	},

	toggleVisibility(targets, config, sourceEl) {
		eachTarget(targets, (el) => el.classList.toggle(HIDDEN_CLASS));
		reflectExpandedState(sourceEl, targets);
	},

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

	removeAttribute(targets, { attributeName }) {
		if (!attributeName || FORBIDDEN_ATTRIBUTE.test(attributeName)) {
			return;
		}
		eachTarget(targets, (el) => el.removeAttribute(attributeName));
	},

	scrollToTop() {
		const reduced = window.matchMedia?.(
			'(prefers-reduced-motion: reduce)'
		)?.matches;
		window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
	},

	submitForm(targets) {
		const el = (targets || [])[0];
		// The target may be the form itself or anything inside it.
		const form =
			'FORM' === el?.tagName ? el : el?.closest?.('form') || null;
		if (!form) {
			return;
		}
		// requestSubmit fires validation and the submit event; form.submit()
		// skips both, which would bypass the form block's own handling.
		if (form.requestSubmit) {
			form.requestSubmit();
		}
	},

	playMedia(targets) {
		eachTarget(targets, (el) => el.play?.());
	},

	pauseMedia(targets) {
		eachTarget(targets, (el) => el.pause?.());
	},

	toggleMedia(targets) {
		eachTarget(targets, (el) => {
			if ('function' !== typeof el.play) {
				return;
			}
			if (el.paused) {
				el.play();
			} else {
				el.pause();
			}
		});
	},

	focusTarget(targets) {
		const el = (targets || [])[0];
		if (!el) {
			return;
		}
		// A non-interactive target cannot take focus without help.
		if (!el.hasAttribute('tabindex') && 'function' !== typeof el.focus) {
			return;
		}
		if (
			!el.hasAttribute('tabindex') &&
			!el.matches(
				'a, button, input, select, textarea, summary, [contenteditable]'
			)
		) {
			el.setAttribute('tabindex', '-1');
		}
		el.focus?.();
	},

	dispatchEvent(targets, { value }, sourceEl) {
		if (!value) {
			return;
		}
		const el = (targets || [])[0] || sourceEl;
		el?.dispatchEvent?.(
			new CustomEvent(value, { bubbles: true, detail: { sourceEl } })
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
