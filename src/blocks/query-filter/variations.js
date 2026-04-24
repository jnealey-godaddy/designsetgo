import { __ } from '@wordpress/i18n';

export default [
	{
		name: 'checkbox',
		title: __('Taxonomy (multi-select)', 'designsetgo'),
		icon: 'list-view',
		description: __(
			'Multi-select taxonomy filter — render as checkboxes, pills, or underlined tabs.',
			'designsetgo'
		),
		// New inserts opt into the modern underlined-tabs look. The block
		// default stays `default` (classic checkboxes) so legacy saved blocks
		// don't silently change appearance on upgrade.
		attributes: {
			filterKind: 'checkbox',
			paramName: 'filter_category',
			filterStyle: 'underline',
		},
		isDefault: true,
		scope: ['inserter', 'transform'],
	},
	{
		name: 'select',
		title: __('Taxonomy (dropdown)', 'designsetgo'),
		icon: 'menu',
		description: __('Single-select taxonomy dropdown.', 'designsetgo'),
		attributes: { filterKind: 'select', paramName: 'filter_category' },
		scope: ['inserter', 'transform'],
	},
	{
		name: 'search',
		title: __('Search input', 'designsetgo'),
		icon: 'search',
		description: __('Free-text search bound to ?q=.', 'designsetgo'),
		attributes: { filterKind: 'search', paramName: 'q' },
		scope: ['inserter', 'transform'],
	},
	{
		name: 'sort',
		title: __('Sort dropdown', 'designsetgo'),
		icon: 'sort',
		description: __('Sort bound to ?sort=.', 'designsetgo'),
		attributes: { filterKind: 'sort', paramName: 'sort' },
		scope: ['inserter', 'transform'],
	},
	{
		name: 'active',
		title: __('Active filters', 'designsetgo'),
		icon: 'tag',
		description: __(
			'Show removable chips for each active filter.',
			'designsetgo'
		),
		attributes: { filterKind: 'active', paramName: '' },
		scope: ['inserter', 'transform'],
	},
	{
		name: 'reset',
		title: __('Reset button', 'designsetgo'),
		icon: 'undo',
		description: __('Clear all filter params.', 'designsetgo'),
		attributes: {
			filterKind: 'reset',
			paramName: '',
			label: __('Reset', 'designsetgo'),
		},
		scope: ['inserter', 'transform'],
	},
];
