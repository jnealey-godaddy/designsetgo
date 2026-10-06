/**
 * Form Builder — apply conditional field logic in the browser.
 *
 * Hidden fields get `hidden` and their controls `disabled`, so the browser's
 * own validation skips them and FormData / the no-JS POST never send them.
 * Values are left in place so a field shown again still holds them. The server
 * re-applies the same rules (Form_Conditions), so this is presentation only.
 *
 * @since 2.10.0
 */

import { __, sprintf } from '@wordpress/i18n';
import { visibleFields } from './conditions';

const CONTROLS = 'input, select, textarea';
const ANNOUNCE_DELAY = 300;

function readValue(wrapper) {
	const name = wrapper.dataset.dsgoField;
	const control = Array.from(wrapper.querySelectorAll(CONTROLS)).find(
		(el) => el.name === name
	);
	if (!control) {
		return '';
	}
	if (control.type === 'checkbox' || control.type === 'radio') {
		return control.checked ? control.value : '';
	}
	return control.value;
}

/**
 * The text a screen reader hears for a field: its label without the required
 * marker, else its field name. Hidden-type fields (only `<input type="hidden">`)
 * have nothing to perceive, so they are never announced.
 *
 * @param {HTMLElement} wrapper Field wrapper.
 * @return {string} Label text, or '' when the field isn't announced.
 */
function labelText(wrapper) {
	const label = wrapper.querySelector(
		'.dsgo-form-field__label, .dsgo-form-field__checkbox-label'
	);
	if (label) {
		const clone = label.cloneNode(true);
		clone
			.querySelectorAll('.dsgo-form-field__required')
			.forEach((el) => el.remove());
		const text = clone.textContent.trim();
		if (text) {
			return text;
		}
	}
	const controls = Array.from(wrapper.querySelectorAll(CONTROLS));
	if (controls.length && controls.every((el) => el.type === 'hidden')) {
		return '';
	}
	return wrapper.dataset.dsgoField || '';
}

function setVisible(wrapper, visible) {
	wrapper.hidden = !visible;
	wrapper.querySelectorAll(CONTROLS).forEach((control) => {
		if (!visible && !control.disabled) {
			control.disabled = true;
			control.dataset.dsgoCondDisabled = '1';
		} else if (visible && control.dataset.dsgoCondDisabled) {
			control.disabled = false;
			delete control.dataset.dsgoCondDisabled;
		}
	});
}

/**
 * Wire conditional logic for one form.
 *
 * @param {HTMLElement}      container .dsgo-form-builder wrapper.
 * @param {HTMLElement|null} form      The form element.
 */
export function initFormConditions(container, form) {
	let wrappers = [];
	try {
		if (!form) {
			return;
		}
		wrappers = Array.from(form.querySelectorAll('[data-dsgo-field]'));
		setUp(container, form, wrappers);
	} catch {
		// Fail open: never strand fields hidden, and never stop the rest of the
		// form (submit handling) from initialising. The server still enforces
		// the rules.
		wrappers.forEach((wrapper) => setVisible(wrapper, true));
	} finally {
		container.dataset.dsgoConditionsReady = '1';
	}
}

/**
 * Parse rules, apply initial visibility and listen for changes.
 *
 * @param {HTMLElement}   container .dsgo-form-builder wrapper.
 * @param {HTMLElement}   form      The form element.
 * @param {HTMLElement[]} wrappers  Field wrappers in document order.
 */
function setUp(container, form, wrappers) {
	const conditions = {};
	wrappers.forEach((wrapper) => {
		const raw = wrapper.dataset.dsgoConditions;
		if (!raw) {
			return;
		}
		try {
			conditions[wrapper.dataset.dsgoField] = JSON.parse(raw);
		} catch {
			// Malformed rules are ignored: the field stays visible.
		}
	});
	if (!Object.keys(conditions).length) {
		return;
	}

	const names = wrappers.map((wrapper) => wrapper.dataset.dsgoField);
	const region = document.createElement('div');
	region.className = 'screen-reader-text';
	region.setAttribute('aria-live', 'polite');
	container.appendChild(region);

	// Field name => { label, show }: a field that toggles within the debounce
	// window is announced once, in its final state.
	let pending = new Map();
	let timer = null;
	const announce = () => {
		const shown = [];
		const hidden = [];
		pending.forEach(({ label, show }) => {
			(show ? shown : hidden).push(label);
		});
		pending = new Map();
		const parts = [];
		if (shown.length) {
			/* translators: %s: comma-separated field labels */
			const shownText = __('Shown: %s.', 'designsetgo');
			parts.push(sprintf(shownText, shown.join(', ')));
		}
		if (hidden.length) {
			/* translators: %s: comma-separated field labels */
			const hiddenText = __('Hidden: %s.', 'designsetgo');
			parts.push(sprintf(hiddenText, hidden.join(', ')));
		}
		region.textContent = parts.join(' ');
	};

	const apply = (fromUser) => {
		const values = {};
		wrappers.forEach((wrapper) => {
			values[wrapper.dataset.dsgoField] = readValue(wrapper);
		});
		const visible = new Set(visibleFields(names, conditions, values));
		wrappers.forEach((wrapper) => {
			const show = visible.has(wrapper.dataset.dsgoField);
			if (wrapper.hidden === !show) {
				return;
			}
			setVisible(wrapper, show);
			const label = labelText(wrapper);
			if (fromUser && label) {
				pending.set(wrapper.dataset.dsgoField, { label, show });
			}
		});
		if (fromUser && pending.size) {
			clearTimeout(timer);
			timer = setTimeout(announce, ANNOUNCE_DELAY);
		}
	};

	// Initial pass: hidden wrappers start with hidden=false, so every
	// field that should hide is applied (not announced).
	wrappers.forEach((wrapper) => {
		wrapper.hidden = false;
	});
	apply(false);

	form.addEventListener('input', () => apply(true));
	form.addEventListener('change', () => apply(true));
	form.addEventListener('reset', () => setTimeout(() => apply(false), 0));
}
