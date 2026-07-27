import { shouldExtendBlock } from '../../utils/should-extend-block';

// The block-animations extension's own exclude list, localized from the same
// config file Animation_Defaults_Injector reads via
// Extension_Attributes::get_extension_exclusions( 'block-animations' ) — so
// changing that config propagates here instead of needing a matching edit.
// Entries are exact names or `namespace/*` patterns, matching is_excluded().
const isStaticallyExcluded = (blockName, patterns) =>
	patterns.some((pattern) =>
		pattern.endsWith('/*')
			? blockName.startsWith(pattern.slice(0, -1))
			: blockName === pattern
	);

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

	// The render-time injector skips excluded blocks, so an indicator here
	// would promise an animation the frontend never applies. Exact-name map
	// keys are already dropped when the list is localized; this catches the
	// case that can only be settled against a concrete name — an excluded
	// block matching a `namespace/*` entry.
	const exclusions = Array.isArray(settings.blockAnimationExclusions)
		? settings.blockAnimationExclusions
		: [];
	if (
		!shouldExtendBlock(blockName) ||
		isStaticallyExcluded(blockName, exclusions)
	) {
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
