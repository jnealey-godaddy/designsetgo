/**
 * Star Rating — attribute defaults.
 *
 * Single source for the editor's reset-to-default behaviour. Mirrors
 * block.json; `tests/unit/blocks/star-rating.test.js` asserts they agree, so a
 * default changed in one place cannot quietly survive in the other.
 *
 * `iconSize` and `iconStyle` are deliberately absent: they have no default so
 * an unset value inherits the theme tokens (settings.custom.designsetgo.
 * starRating.defaultSize and icon.defaultStyle). Resetting them means
 * `undefined`, not a number.
 *
 * @since 2.8.0
 */

export const DEFAULTS = {
	rating: 4.5,
	maxRating: 5,
	precision: 'half',
	icon: 'star',
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
