export const clamp = (value, minimum, maximum) =>
	Math.max(minimum, Math.min(maximum, Number(value) || 0));

export const getTextPathId = (uniqueId) =>
	`dsgo-text-path-${uniqueId || 'path'}`;

const TEXT_PATH_BLOCK_NAME = 'designsetgo/text-path';

/**
 * Find the Text Path block that owns a unique ID.
 *
 * Takes a flat client-ID list and the store's per-block selectors rather than a
 * block tree: `getBlocks()` materialises every block on every store change, so
 * scanning it re-walked the whole document on each keystroke. `clientIds` comes
 * from `getClientIdsWithDescendants()`, which only changes when block order
 * changes.
 *
 * @param {Array}    clientIds                    Flat list of client IDs to scan.
 * @param {string}   uniqueId                     Unique ID to find the owner of.
 * @param {Object}   selectors                    Block editor store selectors.
 * @param {Function} selectors.getBlockName       Resolves a client ID to a block name.
 * @param {Function} selectors.getBlockAttributes Resolves a client ID to its attributes.
 * @return {string|null} Client ID of the first owner, or null.
 */
export const findFirstTextPathClientId = (
	clientIds,
	uniqueId,
	{ getBlockName, getBlockAttributes } = {}
) => {
	if (!uniqueId || !Array.isArray(clientIds)) {
		return null;
	}

	for (const clientId of clientIds) {
		if (getBlockName?.(clientId) !== TEXT_PATH_BLOCK_NAME) {
			continue;
		}

		if (getBlockAttributes?.(clientId)?.uniqueId === uniqueId) {
			return clientId;
		}
	}

	return null;
};

export const getSafeTextPathUrl = (url) => {
	if (typeof url !== 'string') {
		return '';
	}

	const trimmedUrl = url.trim();
	return /^(?:https?:|mailto:|tel:|\/|#)/i.test(trimmedUrl) ? trimmedUrl : '';
};

export const getSafeTextPathColor = (color) => {
	if (typeof color !== 'string') {
		return '';
	}

	const value = color.trim();
	const isPreset = /^var:preset\|color\|[a-z0-9-]+$/i.test(value);
	const isHex = /^#[0-9a-f]{3,8}$/i.test(value);
	const isFunctionalColor = /^(?:rgb|hsl)a?\([0-9.%\s,/+-]+\)$/i.test(value);

	return isPreset || isHex || isFunctionalColor ? value : '';
};
