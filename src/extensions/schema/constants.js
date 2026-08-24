/**
 * Schema Extension - Constants
 *
 * The allowlist is deliberately narrow. A block only appears here once a
 * server-side builder exists for every type it offers — a control that writes
 * an attribute nothing consumes is a no-op that looks like a feature.
 *
 * Star Rating's two types are not interchangeable, and choosing wrong is the
 * kind of mistake that gets structured data ignored or penalised:
 *
 * - `aggregate-rating` is *many people's* ratings of one thing. It needs a
 *   rating count, so the builder emits nothing without one.
 * - `review` is *one person's* rating of one thing. It needs a named author,
 *   so the builder emits nothing without one.
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
	'designsetgo/star-rating': [
		NONE,
		{
			value: 'aggregate-rating',
			label: __('Aggregate rating', 'designsetgo'),
		},
		{ value: 'review', label: __('Review', 'designsetgo') },
	],
};

export const SCHEMA_BLOCKS = Object.keys(SCHEMA_TYPES);
