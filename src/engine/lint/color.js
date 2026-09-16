/**
 * Small, dependency-free color parsing and WCAG contrast math for lint
 * rules. Plain ES module — no Node-only or WordPress imports, so it runs
 * unmodified in the CLI and the browser editor.
 */

const HEX3_RE = /^#([0-9a-f]{3})$/i;
const HEX6_RE = /^#([0-9a-f]{6})$/i;
const HEX8_RE = /^#([0-9a-f]{8})$/i;
const RGB_RE =
	/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*[\d.]+\s*)?\)$/i;

/**
 * Parse a CSS color into an `{r,g,b}` triple.
 *
 * Accepts `#rgb`, `#rrggbb`, `#rrggbbaa` (alpha is ignored), and
 * `rgb()`/`rgba()` with integer channels. Anything else — including preset
 * references like `var:preset|color|primary` or a bare slug — is not a raw
 * color and returns `null`; resolving those is `design.js`'s job.
 *
 * @param {unknown} value Candidate color string.
 * @return {{r: number, g: number, b: number}|null} Parsed color, or `null`.
 */
export function parseColor(value) {
	if (typeof value !== 'string' || value === '') {
		return null;
	}

	const trimmed = value.trim();

	let match = HEX3_RE.exec(trimmed);
	if (match) {
		const [r, g, b] = match[1]
			.split('')
			.map((digit) => parseInt(digit + digit, 16));
		return { r, g, b };
	}

	match = HEX6_RE.exec(trimmed) || HEX8_RE.exec(trimmed);
	if (match) {
		const hex = match[1];
		return {
			r: parseInt(hex.slice(0, 2), 16),
			g: parseInt(hex.slice(2, 4), 16),
			b: parseInt(hex.slice(4, 6), 16),
		};
	}

	match = RGB_RE.exec(trimmed);
	if (match) {
		const [r, g, b] = [match[1], match[2], match[3]].map(Number);
		if (r > 255 || g > 255 || b > 255) {
			return null;
		}
		return { r, g, b };
	}

	return null;
}

/**
 * WCAG 2.x relative luminance of an `{r,g,b}` color (channels 0-255).
 *
 * @param {{r: number, g: number, b: number}} color Color to measure.
 * @return {number} Relative luminance, 0 (black) to 1 (white).
 */
function relativeLuminance({ r, g, b }) {
	const [rs, gs, bs] = [r, g, b].map((channel) => {
		const srgb = channel / 255;
		return srgb <= 0.03928
			? srgb / 12.92
			: Math.pow((srgb + 0.055) / 1.055, 2.4);
	});

	return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
}

/**
 * WCAG 2.x contrast ratio between two colors, 1 (no contrast) to 21 (max).
 *
 * Each argument may be a color string (parsed via `parseColor()`) or an
 * already-parsed `{r,g,b}` object. Returns `null` when either color fails
 * to parse.
 *
 * @param {unknown} colorA First color.
 * @param {unknown} colorB Second color.
 * @return {number|null} Contrast ratio, or `null` if either color is invalid.
 */
export function contrastRatio(colorA, colorB) {
	const a = typeof colorA === 'string' ? parseColor(colorA) : colorA;
	const b = typeof colorB === 'string' ? parseColor(colorB) : colorB;

	if (!a || !b) {
		return null;
	}

	const luminanceA = relativeLuminance(a);
	const luminanceB = relativeLuminance(b);
	const lighter = Math.max(luminanceA, luminanceB);
	const darker = Math.min(luminanceA, luminanceB);

	return (lighter + 0.05) / (darker + 0.05);
}
