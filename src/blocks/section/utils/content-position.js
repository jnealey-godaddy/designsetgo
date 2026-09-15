/**
 * Section content column position.
 *
 * Where a width-constrained `.dsgo-stack__inner` sits inside the section. This
 * is deliberately separate from the layout's `justifyContent`, which aligns the
 * blocks INSIDE the column: plenty of full-width sections use
 * `justifyContent: left` to left-align text within a centered column, and
 * reusing it to move the column would pin those columns to the viewport edge.
 *
 * `center` reproduces the markup every section stored before this attribute
 * existed, so existing content stays valid with no deprecation.
 *
 * Shared by edit.js and save.js so the two cannot drift. The PHP mirror is in
 * Block_Inserter::generate_designsetgo_wrapper_html().
 */

/**
 * Inline margins that place the constrained content column.
 *
 * @param {string} contentPosition 'left' | 'center' | 'right'.
 * @return {{marginLeft: string, marginRight: string}} Inline margin styles.
 */
export function getContentColumnMargins(contentPosition) {
	return {
		marginLeft: contentPosition === 'left' ? '0' : 'auto',
		marginRight: contentPosition === 'right' ? '0' : 'auto',
	};
}
