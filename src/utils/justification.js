/**
 * Justification helpers.
 *
 * These blocks position their visible element inside a block-level wrapper that
 * core's constrained layout caps at the theme's content width. `align: left` /
 * `align: right` cannot be used for this: core deliberately excludes aligned
 * blocks from that cap (see wp-includes/block-supports/layout.php), so an
 * aligned block escapes the content column entirely.
 */

import { __ } from '@wordpress/i18n';
import { justifyLeft, justifyCenter, justifyRight } from '@wordpress/icons';

export const JUSTIFICATION_OPTIONS = [
	{
		value: 'left',
		label: __('Justify left', 'designsetgo'),
		icon: justifyLeft,
	},
	{
		value: 'center',
		label: __('Justify center', 'designsetgo'),
		icon: justifyCenter,
	},
	{
		value: 'right',
		label: __('Justify right', 'designsetgo'),
		icon: justifyRight,
	},
];

const CLASS_BY_VALUE = {
	left: 'dsgo-justify--left',
	center: 'dsgo-justify--center',
	right: 'dsgo-justify--right',
};

/**
 * Map a justification attribute value to its CSS class.
 *
 * @param {string} [value] Justification value.
 * @return {string} Class name, or '' when the value is not supported.
 */
export function getJustificationClass(value) {
	return CLASS_BY_VALUE[value] || '';
}
