/**
 * Star Rating — attribute defaults.
 *
 * Single source for the editor's reset-to-default behaviour. Mirrors
 * block.json; `tests/unit/blocks/star-rating.test.js` asserts they agree, so a
 * default changed in one place cannot quietly survive in the other.
 *
 * @since 2.8.0
 */

export const DEFAULTS = {
	rating: 4.5,
	maxRating: 5,
	precision: 'half',
	icon: 'star',
	iconStyle: 'filled',
	iconSize: 24,
	iconGap: 4,
	ratingColor: '',
	trackColor: '',
	showValue: false,
	showMax: false,
	ratingCount: 0,
	showCount: false,
	countTemplate: '(%s)',
	justification: 'left',
	schemaItemName: '',
	schemaAuthor: '',
};
