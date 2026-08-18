/**
 * Schema Extension - Constants
 *
 * The allowlist is deliberately narrow. A block only appears here once a
 * server-side builder exists for every type it offers — a control that writes
 * an attribute nothing consumes is a no-op that looks like a feature.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';

const NONE = { value: 'none', label: __('None', 'designsetgo') };

export const SCHEMA_TYPES = {
	'designsetgo/accordion': [
		NONE,
		{ value: 'faq', label: __('FAQ', 'designsetgo') },
		{ value: 'howto', label: __('How-to', 'designsetgo') },
	],
};

export const SCHEMA_BLOCKS = Object.keys(SCHEMA_TYPES);
