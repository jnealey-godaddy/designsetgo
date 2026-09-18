/**
 * Form field width helper (editor preview).
 *
 * The fields container is a wrapping flex row with
 * `gap: var(--dsgo-form-field-spacing)`. For N equal columns of P% each the
 * row holds N-1 gaps, so each field gives up gap * (100 - P) / 100. 33 and 66
 * map to exact thirds so three (or 66 + 33) fields fit on one row.
 *
 * Must stay byte-identical to designsetgo_form_field_width_style() in
 * includes/blocks/forms/field-render-helpers.php, which renders the frontend.
 * Both are tested against tests/fixtures/form-field-width-cases.json.
 */

const PERCENT_MAP = {
	33: '33.3333',
	66: '66.6667',
};

/**
 * Get the flex-basis / max-width value for a form field width.
 *
 * @param {string|number} fieldWidth Width percentage attribute (e.g. '50').
 * @return {string} CSS length: '100%' or a calc() expression.
 */
export function getFormFieldWidth(fieldWidth) {
	const digits = String(fieldWidth ?? '').replace(/[^0-9]/g, '');
	const width = digits === '' ? '100' : digits;

	if (width === '100') {
		return '100%';
	}

	const percent = PERCENT_MAP[width] ?? width;
	// Rounded to 4 decimals, trailing zeros trimmed (50 -> 0.5).
	const gapFactor = String(
		Number(((100 - parseFloat(percent)) / 100).toFixed(4))
	);

	return `calc(${percent}% - var(--dsgo-form-field-spacing, 1.5rem) * ${gapFactor})`;
}

/**
 * Get the inline style object for a form field wrapper.
 *
 * @param {string|number} fieldWidth Width percentage attribute.
 * @return {{flexBasis: string, maxWidth: string}} Style properties.
 */
export function getFormFieldWidthStyle(fieldWidth) {
	const value = getFormFieldWidth(fieldWidth);
	return { flexBasis: value, maxWidth: value };
}
