/**
 * Form Phone Field Block - Frontend Script
 *
 * Populates country-code <select> options and handles auto-formatting.
 *
 * @since 1.0.0
 */

import COUNTRY_CODES from './country-codes';

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

		// Format phone number based on format type
		function formatPhoneNumber(value, format) {
			// Remove all non-numeric characters
			const cleaned = value.replace(/\D/g, '');

			if (format === 'us') {
				// US format: (555) 123-4567
				if (cleaned.length <= 3) {
					return cleaned;
				} else if (cleaned.length <= 6) {
					return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3)}`;
				}
				return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(6, 10)}`;
			} else if (format === 'international') {
				// International format: +1 555 123 4567
				if (cleaned.length <= 3) {
					return cleaned;
				} else if (cleaned.length <= 6) {
					return `${cleaned.slice(0, 3)} ${cleaned.slice(3)}`;
				} else if (cleaned.length <= 9) {
					return `${cleaned.slice(0, 3)} ${cleaned.slice(3, 6)} ${cleaned.slice(6)}`;
				}
				return `${cleaned.slice(0, 3)} ${cleaned.slice(3, 6)} ${cleaned.slice(6, 10)}`;
			}
			// Any format: just add spaces for readability
			if (cleaned.length <= 3) {
				return cleaned;
			} else if (cleaned.length <= 6) {
				return `${cleaned.slice(0, 3)} ${cleaned.slice(3)}`;
			}
			return `${cleaned.slice(0, 3)} ${cleaned.slice(3, 6)} ${cleaned.slice(6)}`;
		}

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
			e.preventDefault();
			const pastedText = (
				e.clipboardData || window.clipboardData
			).getData('text');
			const formattedValue = formatPhoneNumber(pastedText, phoneFormat);
			e.target.value = formattedValue;
		});
	});
}

document.addEventListener('DOMContentLoaded', initPhoneFields);
document.addEventListener('dsgo-content-loaded', initPhoneFields);
