/**
 * Convert WordPress vertical alignment value to CSS align-items value
 * WordPress stores: stretch, center, top, bottom, space-between
 * CSS align-items needs: stretch, center, flex-start, flex-end, space-between
 *
 * @param {string} value The WordPress vertical alignment value
 * @return {string} CSS align-items value
 */
export function getAlignItemsValue(value) {
	if (!value) {
		return undefined;
	}

	const alignMap = {
		stretch: 'stretch',
		center: 'center',
		top: 'flex-start',
		bottom: 'flex-end',
		'space-between': 'space-between',
	};

	return alignMap[value];
}
