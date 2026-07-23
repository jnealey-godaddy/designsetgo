/**
 * Resolve the effective theme animation default for a block name from the
 * localized settings, mirroring the PHP Animation_Defaults resolver
 * (exact name, then namespace wildcard). Returns null when none applies.
 *
 * @param {string} blockName Block name.
 * @return {Object|null} Config or null.
 */
export function resolveBlockAnimationDefault(blockName) {
	const settings =
		(typeof window !== 'undefined' && window.dsgoSettings) || {};

	if (!settings.blockAnimationsEnabled) {
		return null;
	}

	const list = Array.isArray(settings.blockAnimations)
		? settings.blockAnimations
		: [];

	const exact = list.find((entry) => entry && entry.block === blockName);
	let match = exact;

	if (!match) {
		const slash = blockName.indexOf('/');
		if (slash !== -1) {
			const wildcard = `${blockName.slice(0, slash + 1)}*`;
			match = list.find((entry) => entry && entry.block === wildcard);
		}
	}

	if (!match) {
		return null;
	}

	return {
		entrance: match.entrance || '',
		exit: match.exit || '',
		trigger: match.trigger || 'scroll',
		duration: typeof match.duration === 'number' ? match.duration : 600,
		delay: typeof match.delay === 'number' ? match.delay : 0,
		easing: match.easing || 'ease-out',
		offset: typeof match.offset === 'number' ? match.offset : 100,
		once: typeof match.once === 'boolean' ? match.once : true,
	};
}
