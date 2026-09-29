import { getLuminance, parseColor } from './contrast-checker';

/**
 * Minimum contrast for menu text (WCAG AA, normal text).
 *
 * @type {number}
 */
const MIN_CONTRAST = 4.5;

/**
 * WCAG contrast ratio between two RGB colors.
 *
 * @param {Object} a RGB channels.
 * @param {Object} b RGB channels.
 * @return {number} Ratio, 1–21.
 */
function contrastRatio(a, b) {
	const [lighter, darker] = [getLuminance(a), getLuminance(b)].sort(
		(x, y) => y - x
	);
	return (lighter + 0.05) / (darker + 0.05);
}

/**
 * Resolve the normal menu palette independently of header scroll colors.
 *
 * The surface is the theme's base-2, else base, else white (see the
 * stylesheet). The text is the first theme color that is readable on it:
 * contrast-2 (only when the surface is base-2, the pair it was designed for),
 * then contrast, then black or white. A theme color that isn't readable on
 * the surface is skipped. On a dark palette without base-2 (Twenty
 * Twenty-Five's Evening), contrast is light, and pairing it with a white
 * surface left the menu unreadable.
 *
 * @param {Object}   args                Color resolution inputs.
 * @param {Function} args.token          Read a theme custom property.
 * @param {Function} args.normalizeColor Normalize unsupported colors to RGB.
 * @return {{background: string, foreground: string}} Opaque menu colors.
 */
export function resolveOverlayMenuColors({ token, normalizeColor }) {
	const toRgb = (color) => parseColor(color) || normalizeColor(color);
	const surface = token('--dsgo-overlay-menu-surface').trim() || '#fff';
	const rgb = toRgb(surface) || { r: 255, g: 255, b: 255 };
	const candidates = [
		surface === token('--wp--preset--color--base-2').trim() &&
			token('--wp--preset--color--contrast-2').trim(),
		token('--wp--preset--color--contrast').trim(),
	].filter(Boolean);
	const foreground =
		candidates.find((color) => {
			const candidate = toRgb(color);
			return candidate && contrastRatio(candidate, rgb) >= MIN_CONTRAST;
		}) || (getLuminance(rgb) > 0.179 ? '#000' : '#fff');

	return {
		background: `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`,
		foreground,
	};
}

/**
 * Let the browser normalize hsl(), modern colors and nested variables.
 *
 * @param {HTMLElement} header  Header providing the theme variable context.
 * @param {string}      surface Menu surface color.
 * @return {Object|null} RGB channels, without the surface's alpha.
 */
function normalizeMenuColor(header, surface) {
	const probe = document.createElement('span');
	probe.style.cssText = `position:absolute;visibility:hidden;color:${surface}`;
	header.appendChild(probe);
	try {
		const color = window.getComputedStyle(probe).color;
		const rgb = parseColor(color);
		if (rgb) {
			return rgb;
		}
		const context = document.createElement('canvas').getContext('2d');
		if (!context) {
			return null;
		}
		// Keep even zero-alpha theme surfaces opaque, retaining their RGB channels.
		context.fillStyle = color.replace(/\s*\/\s*[\d.]+\s*\)$/, ' / 1)');
		context.fillRect(0, 0, 1, 1);
		const [r, g, b] = context.getImageData(0, 0, 1, 1).data;
		return { r, g, b };
	} finally {
		probe.remove();
	}
}

/**
 * Apply menu colors without changing the header's scroll state or styles.
 *
 * @param {HTMLElement} header Header element.
 */
export function applyOverlayMenuColors(header) {
	const styles = window.getComputedStyle(header);
	const { background, foreground } = resolveOverlayMenuColors({
		token: (name) => styles.getPropertyValue(name),
		normalizeColor: (surface) => normalizeMenuColor(header, surface),
	});
	header.style.setProperty('--dsgo-overlay-menu-bg', background);
	header.style.setProperty('--dsgo-overlay-menu-fg', foreground);
}
