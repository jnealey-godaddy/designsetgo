/**
 * Custom CSS Extension - breakpoint sections.
 *
 * `dsgoCustomCSS` stays one string. Tablet and mobile rules live in marked
 * sections at its end (`/* dsgo-css:tablet:start *\/` … `:end`), each one
 * wrapping its rules in a `max-width` media query. Everything outside the
 * markers is the desktop CSS, which applies at every width.
 *
 * This mirrors `DesignSetGo\Abilities\Responsive_CSS::merge()` exactly, so
 * the editor and the configure-custom-css ability read and write the same
 * markup. Change one, change the other.
 *
 * @since 2.10.0
 */

import support from '../../../includes/data/layout-support.json';

export const BREAKPOINT_DEVICES = ['tablet', 'mobile'];

const SECTION =
	/\/\* dsgo-css:(tablet|mobile):start \*\/[\s\S]*?\/\* dsgo-css:\1:end \*\//g;
const MARKER = /\/\* dsgo-css:(?:tablet|mobile):(?:start|end) \*\//g;
const WRAPPED =
	/^\/\* dsgo-css:\w+:start \*\/\n?@media[^{]*\{\n?([\s\S]*?)\n?\}\s*\/\* dsgo-css:\w+:end \*\/$/;

/**
 * Max width, in px, at which a device's rules apply.
 *
 * @param {string} device `tablet` or `mobile`.
 * @return {number} Breakpoint width.
 */
export function breakpointWidth(device) {
	return support.devices[device];
}

/**
 * Split stored CSS into per-device rules.
 *
 * CSS outside the markers is the desktop base, as the server treats it. A
 * section whose wrapper was hand-edited past recognition keeps its body with
 * the markers removed, so nothing is lost.
 *
 * @param {string} css Stored `dsgoCustomCSS`.
 * @return {{desktop: string, tablet: string, mobile: string}} Rules per device.
 */
export function splitResponsiveCSS(css) {
	const parts = { desktop: '', tablet: '', mobile: '' };
	let sections = 0;
	const base = String(css || '').replace(SECTION, (block, device) => {
		const match = block.match(WRAPPED);
		parts[device] = match ? match[1] : block.replace(MARKER, '').trim();
		sections++;
		return '';
	});
	// joinResponsiveCSS() puts one newline before each section; drop those so
	// the desktop rules round-trip exactly, trailing newline included.
	parts.desktop = sections
		? base.replace(new RegExp(`\\n{0,${sections}}$`), '')
		: base;
	return parts;
}

/**
 * Join per-device rules back into the stored string.
 *
 * With no tablet or mobile rules the result is exactly the desktop CSS, so
 * blocks that never use breakpoints keep their attribute and class hash.
 *
 * @param {{desktop?: string, tablet?: string, mobile?: string}} parts Rules per device.
 * @return {string} Value for `dsgoCustomCSS`.
 */
export function joinResponsiveCSS(parts) {
	const clean = (value) => String(value || '').replace(MARKER, '');
	const sections = BREAKPOINT_DEVICES.filter(
		(device) => clean(parts[device]).trim() !== ''
	).map(
		(device) =>
			`/* dsgo-css:${device}:start */\n@media (max-width: ${breakpointWidth(
				device
			)}px) {\n${clean(parts[device])}\n}\n/* dsgo-css:${device}:end */`
	);
	const desktop = clean(parts.desktop);
	return [desktop, ...sections]
		.filter((part, index) => index > 0 || part !== '')
		.join('\n');
}
