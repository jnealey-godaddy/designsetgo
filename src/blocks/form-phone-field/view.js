/**
 * Form Phone Field Block - Frontend Script
 *
 * Populates country-code <select> options and handles auto-formatting.
 *
 * @since 1.0.0
 */

import COUNTRY_CODES from './country-codes';

/**
 * Format an international number as typed: a leading "+", the visitor's own
 * digit groups joined by dashes ("+44 20 7946 0958" → "+44-20-7946-0958").
 * Country codes and national groupings vary too much to impose one, so each
 * run of spaces, dots, dashes or parentheses becomes a single dash. Digits
 * are capped at 15 (E.164).
 *
 * @param {string}  value      Raw input.
 * @param {boolean} [complete] True once the visitor is done typing: drops a
 *                             trailing dash that mid-typing would keep.
 * @return {string} Formatted value.
 */
export function formatInternational(value, complete = false) {
	let digits = 0;
	let out = '';
	for (const ch of value) {
		if (ch >= '0' && ch <= '9') {
			if (digits < 15) {
				digits++;
				out += ch;
			}
		} else if (/[\s.\-()]/.test(ch) && out && !out.endsWith('-')) {
			out += '-';
		}
	}
	if (complete) {
		out = out.replace(/-$/, '');
	}
	return out ? `+${out}` : '';
}

/**
 * Format a phone number for the field's format.
 *
 * @param {string}  value      Raw input.
 * @param {string}  format     'us', 'international' or 'any'.
 * @param {boolean} [complete] True once the visitor is done typing.
 * @return {string} Formatted value.
 */
export function formatPhoneNumber(value, format, complete = false) {
	// Remove all non-numeric characters
	const cleaned = value.replace(/\D/g, '');

	if (format === 'us') {
		// US format: (555) 123-4567. A pasted "+1" / "1" country code
		// is dropped so it doesn't shift the area code.
		const national =
			cleaned.length > 10 && cleaned.startsWith('1')
				? cleaned.slice(1)
				: cleaned;
		if (national.length <= 3) {
			return national;
		} else if (national.length <= 6) {
			return `(${national.slice(0, 3)}) ${national.slice(3)}`;
		}
		return `(${national.slice(0, 3)}) ${national.slice(3, 6)}-${national.slice(6, 10)}`;
	} else if (format === 'international') {
		return formatInternational(value, complete);
	}
	// Any format: just add spaces for readability
	if (cleaned.length <= 3) {
		return cleaned;
	} else if (cleaned.length <= 6) {
		return `${cleaned.slice(0, 3)} ${cleaned.slice(3)}`;
	}
	return `${cleaned.slice(0, 3)} ${cleaned.slice(3, 6)} ${cleaned.slice(6)}`;
}

function initPhoneFields() {
	// Populate country-code selects rendered with data-dsgo-country-code.
	const codeSelects = document.querySelectorAll(
		'select.dsgo-form-field__country-code[data-dsgo-country-code]'
	);

	codeSelects.forEach((select) => {
		// Guard against duplicate initialization (e.g. bfcache restore)
		if (select.dataset.dsgoInitialized) {
			return;
		}
		select.dataset.dsgoInitialized = 'true';

		const defaultCode = select.dataset.dsgoCountryCode || '+1';

		COUNTRY_CODES.forEach(({ value }) => {
			const option = document.createElement('option');
			option.value = value;
			option.textContent = value;
			if (value === defaultCode) {
				option.selected = true;
			}
			select.appendChild(option);
		});
	});

	// Find all phone field wrappers with auto-format enabled
	const phoneWrappers = document.querySelectorAll(
		'.dsgo-form-field__phone-wrapper[data-auto-format="true"]'
	);

	phoneWrappers.forEach((wrapper) => {
		// Guard against duplicate initialization
		if (wrapper.dataset.dsgoInitialized) {
			return;
		}
		wrapper.dataset.dsgoInitialized = 'true';

		const input = wrapper.querySelector('input[type="tel"]');
		if (!input) {
			return;
		}

		const phoneFormat = input.dataset.phoneFormat || 'any';

		// Handle input event for auto-formatting
		input.addEventListener('input', function (e) {
			const cursorPosition = e.target.selectionStart;
			const oldValue = e.target.value;
			const oldLength = oldValue.length;

			// Format the value
			const formattedValue = formatPhoneNumber(oldValue, phoneFormat);
			const newLength = formattedValue.length;

			// Update the input value
			e.target.value = formattedValue;

			// Adjust cursor position
			const diff = newLength - oldLength;
			const newCursorPosition = cursorPosition + diff;
			e.target.setSelectionRange(newCursorPosition, newCursorPosition);
		});

		// Handle paste event
		input.addEventListener('paste', function (e) {
			const clipboard = e.clipboardData || window.clipboardData;
			if (!clipboard) {
				// Can't access clipboard data — allow default browser paste as fallback
				return;
			}
			e.preventDefault();
			const pastedText = clipboard.getData('text');
			const formattedValue = formatPhoneNumber(
				pastedText,
				phoneFormat,
				true
			);
			e.target.value = formattedValue;
		});

		// Typing keeps a trailing separator so the next group can follow it;
		// tidy it once the visitor moves on.
		input.addEventListener('blur', function (e) {
			e.target.value = formatPhoneNumber(
				e.target.value,
				phoneFormat,
				true
			);
		});
	});
}

document.addEventListener('DOMContentLoaded', initPhoneFields);
document.addEventListener('dsgo-content-loaded', initPhoneFields);
