import { getLuminance, parseColor } from './contrast-checker';

/**
 * Resolve the normal menu palette independently of header scroll colors.
 *
 * @param {Object}   args                Color resolution inputs.
 * @param {Function} args.token          Read a theme custom property.
 * @param {Function} args.normalizeColor Normalize unsupported colors to RGB.
 * @return {{background: string, foreground: string}} Opaque menu colors.
 */
export function resolveOverlayMenuColors({ token, normalizeColor }) {
	const surface = token('--dsgo-overlay-menu-surface').trim() || '#fff';
	const rgb = parseColor(surface) ||
		normalizeColor(surface) || { r: 255, g: 255, b: 255 };
	const foreground =
		// Only pair contrast-2 with the theme's base-2 surface.
		(surface === token('--wp--preset--color--base-2').trim() &&
			token('--wp--preset--color--contrast-2').trim()) ||
		token('--wp--preset--color--contrast').trim() ||
		(getLuminance(rgb) > 0.179 ? '#000' : '#fff');

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
