/**
 * Reads the payload of the `designsetgo/get-design-context` ability —
 * `{ theme, settings, styles, blockStyles }` where `settings` is
 * `wp_get_global_settings()` — and exposes the theme.json preset
 * collections lint rules need, with WordPress's own origin-merge order
 * applied (`default` < `theme` < `custom`, later origin wins on a slug
 * clash). Plain ES module — no Node-only or WordPress imports.
 *
 * Every export tolerates a missing/empty/undefined design context: absent
 * settings simply yield an empty Map/Set, never a throw.
 */
import { parseColor } from './color';

const VAR_PRESET_COLOR_RE = /^var:preset\|color\|(.+)$/;
const VAR_FUNCTION_COLOR_RE = /^var\(\s*--wp--preset--color--([^,)\s]+)\s*\)$/;

/**
 * Merge a theme.json preset collection (`{ default?, theme?, custom? }`,
 * each an array of `{ slug, ... }`) into a single slug → entry Map, in
 * origin precedence order.
 *
 * @param {unknown} collection Candidate `{ default?, theme?, custom? }` shape.
 * @return {Map<string, object>} Merged entries, keyed by slug.
 */
function mergeOrigins(collection) {
	const merged = new Map();

	if (typeof collection !== 'object' || collection === null) {
		return merged;
	}

	for (const origin of ['default', 'theme', 'custom']) {
		const entries = collection[origin];
		if (!Array.isArray(entries)) {
			continue;
		}
		for (const entry of entries) {
			if (entry && typeof entry.slug === 'string') {
				merged.set(entry.slug, entry);
			}
		}
	}

	return merged;
}

/**
 * `settings.color.palette` merged across origins, as slug → hex.
 *
 * @param {Object} [design] A `get-design-context` payload.
 * @return {Map<string, string>} Palette colors, keyed by slug.
 */
export function paletteColors(design) {
	const merged = mergeOrigins(design?.settings?.color?.palette);
	const palette = new Map();

	for (const [slug, entry] of merged) {
		if (typeof entry.color === 'string') {
			palette.set(slug, entry.color);
		}
	}

	return palette;
}

/**
 * `settings.spacing.spacingSizes` merged across origins, as a Set of slugs.
 *
 * @param {Object} [design] A `get-design-context` payload.
 * @return {Set<string>} Registered spacing slugs.
 */
export function spacingSlugs(design) {
	return new Set(
		mergeOrigins(design?.settings?.spacing?.spacingSizes).keys()
	);
}

/**
 * `settings.typography.fontSizes` merged across origins, as a Set of slugs.
 *
 * @param {Object} [design] A `get-design-context` payload.
 * @return {Set<string>} Registered font size slugs.
 */
export function fontSizeSlugs(design) {
	return new Set(
		mergeOrigins(design?.settings?.typography?.fontSizes).keys()
	);
}

/**
 * Resolve an agent-authored color reference to its palette hex.
 *
 * Handles the three forms agents use: a bare slug (as seen in
 * `textColor`/`backgroundColor` attributes), `var:preset|color|<slug>`
 * (as seen in `style.color.*`), and `var(--wp--preset--color--<slug>)`.
 * A raw color (anything `color.js`'s `parseColor()` recognizes) is not a
 * preset reference and resolves to `null`, as does an unknown slug.
 *
 * @param {Object}  [design] A `get-design-context` payload.
 * @param {unknown} value    Candidate color reference.
 * @return {string|null} The palette hex, or `null`.
 */
export function presetColor(design, value) {
	if (typeof value !== 'string' || value === '') {
		return null;
	}

	if (parseColor(value)) {
		return null;
	}

	let slug = value;

	const presetMatch = VAR_PRESET_COLOR_RE.exec(value);
	const functionMatch = VAR_FUNCTION_COLOR_RE.exec(value);
	if (presetMatch) {
		slug = presetMatch[1];
	} else if (functionMatch) {
		slug = functionMatch[1];
	}

	const palette = paletteColors(design);
	return palette.has(slug) ? palette.get(slug) : null;
}
