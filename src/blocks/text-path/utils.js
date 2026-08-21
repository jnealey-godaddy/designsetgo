export const clamp = (value, minimum, maximum) =>
	Math.max(minimum, Math.min(maximum, Number(value) || 0));

export const getTextPathId = (uniqueId) =>
	`dsgo-text-path-${uniqueId || 'path'}`;

const textPathOwnersByBlockTree = new WeakMap();

const getTextPathOwners = (blocks) => {
	if (!Array.isArray(blocks)) {
		return new Map();
	}

	const cachedOwners = textPathOwnersByBlockTree.get(blocks);
	if (cachedOwners) {
		return cachedOwners;
	}

	const owners = new Map();
	const collectOwners = (candidates) => {
		for (const block of candidates || []) {
			if (
				block?.name === 'designsetgo/text-path' &&
				block?.attributes?.uniqueId &&
				!owners.has(block.attributes.uniqueId)
			) {
				owners.set(block.attributes.uniqueId, block.clientId);
			}

			collectOwners(block?.innerBlocks);
		}
	};

	collectOwners(blocks);
	textPathOwnersByBlockTree.set(blocks, owners);

	return owners;
};

/**
 * Finds the first Text Path block that owns a saved path ID, including blocks
 * nested inside layout containers.
 *
 * @param {Array}  blocks   Editor blocks to search.
 * @param {string} uniqueId Saved Text Path ID.
 * @return {string|null} First matching block client ID, if any.
 */
export const findFirstTextPathBlockClientId = (blocks, uniqueId) => {
	if (!uniqueId) {
		return null;
	}

	return getTextPathOwners(blocks).get(uniqueId) || null;
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
