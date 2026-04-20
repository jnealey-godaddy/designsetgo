/**
 * Query Pagination block variations.
 *
 * @since 2.2.0
 */
import { __ } from '@wordpress/i18n';

export default [
	{
		name: 'infinite-scroll',
		title: __('Infinite Scroll', 'designsetgo'),
		description: __(
			'Loads the next page automatically when the user scrolls to the end.',
			'designsetgo'
		),
		icon: 'scroll',
		attributes: { paginationKind: 'infinite' },
		isActive: ['paginationKind'],
		scope: ['inserter', 'transform'],
	},
];
