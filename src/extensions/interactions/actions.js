/**
 * Interaction Layers - Action registry
 *
 * Each action receives the resolved target list and the interaction config.
 *
 * @package
 */

/* global navigator */

// Import from the leaf module, never from ./constants — that module pulls in
// @wordpress/i18n, which is not available in this bundle.
// See visibility-contract.js.
import { HIDDEN_CLASS } from './visibility-contract';

// Attributes an author may never set. Event handlers execute script; `style`
// is allowed because it cannot execute, but `on*` is a direct XSS vector.
const FORBIDDEN_ATTRIBUTE = /^on/i;

// Attributes whose value is fetched or navigated to. A `javascript:` or
// `data:` value on any of these executes script just as surely as an
// `on*` handler would.
const URL_ATTRIBUTE =
	/^(href|src|srcset|action|formaction|data|poster|background|ping|xlink:href)$/i;

const DANGEROUS_URL = /^\s*(javascript|data|vbscript):/i;

/**
 * Split an author-supplied class string into individual, usable class names.
 *
 * `classList.add()` throws InvalidCharacterError on an empty string or one
 * containing whitespace — and a throw here escapes the delegated document
 * listener, killing every interaction that would have run after it. Authors
 * reasonably type `.is-open` (copying a selector) or `a b` (two classes), so
 * both are normalised rather than rejected.
 *
 * @param {string} value Raw class value from the interaction config.
 * @return {string[]} Zero or more valid class names.
 */
export function parseClassNames(value) {
	if ('string' !== typeof value) {
		return [];
	}

	return value
		.split(/[\s,]+/)
		.map((name) => name.trim().replace(/^\./, ''))
		.filter(Boolean);
}

/**
 * Whether an attribute may be written by the setAttribute action.
 *
 * @param {string} name  Attribute name.
 * @param {string} value Attribute value.
 * @return {boolean} True when writing it is safe.
 */
export function isAttributeAllowed(name, value) {
	if (!name || 'string' !== typeof name) {
		return false;
	}

	// setAttribute throws on a name that is not a valid XML name.
	if (!/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/.test(name)) {
		return false;
	}

	if (FORBIDDEN_ATTRIBUTE.test(name)) {
		return false;
	}

	if (URL_ATTRIBUTE.test(name) && DANGEROUS_URL.test(String(value ?? ''))) {
		return false;
	}

	return true;
}

/**
 * Find the media element for a target.
 *
 * Media blocks wrap their element: core/video renders a <figure> around the
 * <video>, so the block the author targets is rarely the media itself.
 *
 * @param {Element} el Target element.
 * @return {Element|null}|null} The media element, or null.
 */
export function mediaIn(el) {
	if (!el) {
		return null;
	}
	if ('function' === typeof el.play && 'function' === typeof el.pause) {
		return el;
	}
	return el.querySelector?.('video, audio') || null;
}

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
		const names = parseClassNames(value);
		if (!names.length) {
			return;
		}
		eachTarget(targets, (el) =>
			names.forEach((name) => el.classList.toggle(name))
		);
	},

	addClass(targets, { value }) {
		const names = parseClassNames(value);
		if (!names.length) {
			return;
		}
		eachTarget(targets, (el) => el.classList.add(...names));
	},

	removeClass(targets, { value }) {
		const names = parseClassNames(value);
		if (!names.length) {
			return;
		}
		eachTarget(targets, (el) => el.classList.remove(...names));
	},

	setAttribute(targets, { attributeName, value }) {
		if (!isAttributeAllowed(attributeName, value)) {
			return;
		}
		eachTarget(targets, (el) =>
			el.setAttribute(attributeName, value ?? '')
		);
	},

	removeAttribute(targets, { attributeName }) {
		if (!isAttributeAllowed(attributeName, '')) {
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
		if (!el) {
			return;
		}
		// The natural target is the form-builder block, whose wrapper is a
		// <div> containing the <form> — so look down as well as up.
		const form =
			'FORM' === el.tagName
				? el
				: el.closest?.('form') || el.querySelector?.('form') || null;
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
		eachTarget(targets, (el) => mediaIn(el)?.play?.());
	},

	pauseMedia(targets) {
		eachTarget(targets, (el) => mediaIn(el)?.pause?.());
	},

	toggleMedia(targets) {
		eachTarget(targets, (el) => {
			const media = mediaIn(el);
			if (!media || 'function' !== typeof media.play) {
				return;
			}
			if (media.paused) {
				media.play();
			} else {
				media.pause();
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
