/**
 * Return a safe destination URL using the same allow-list approach as the
 * Timeline item block. Relative URLs and ordinary web/contact links remain
 * valid; executable schemes never reach saved markup.
 *
 * @param {string} url Candidate URL.
 * @return {string} Safe URL, or an empty string.
 */
export function getSafeHotspotUrl(url) {
	if (typeof url !== 'string' || !url.trim()) {
		return '';
	}
	const trimmed = url.trim();
	try {
		const parsed = new URL(trimmed, 'https://designsetgo.invalid');
		return ['https:', 'http:', 'mailto:', 'tel:'].includes(parsed.protocol)
			? trimmed
			: '';
	} catch {
		return '';
	}
}

/**
 * Restrict saved color values to ColorGradientSettingsDropdown output. This
 * keeps malformed block markup from injecting arbitrary CSS into custom vars.
 *
 * @param {string} color Candidate color value.
 * @return {string} Safe picker value, or an empty string.
 */
export function getSafeHotspotColor(color) {
	if (typeof color !== 'string') {
		return '';
	}
	const value = color.trim();
	const isPreset = /^var:preset\|color\|[a-z0-9-]+$/i.test(value);
	const isHex = /^#[0-9a-f]{3,8}$/i.test(value);
	const isFunctionalColor = /^(?:rgb|hsl)a?\([0-9.%\s,/+-]+\)$/i.test(value);
	return isPreset || isHex || isFunctionalColor ? value : '';
}
