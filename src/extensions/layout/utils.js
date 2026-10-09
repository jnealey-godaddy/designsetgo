import support from '../../../includes/data/layout-support.json';
import { validateValue } from './validate';
import { layoutHash } from './hash';

export { support };
export const isContainer = (name) =>
	['flex', 'grid'].includes(support.blocks[name]?.role);
export const appliesTo = (name, definition) => {
	const role = support.blocks[name]?.role;
	return (
		!!role &&
		(definition.scope === 'all' ||
			definition.scope === role ||
			(definition.scope === 'container' && isContainer(name)))
	);
};
const isObject = (value) =>
	value !== null && typeof value === 'object' && !Array.isArray(value);

/**
 * Returns a canonical layout, or null when any input is invalid.
 * @param {string} name   Block name.
 * @param {Object} layout Optional overrides.
 */
export function sanitizeLayout(name, layout) {
	if (!support.blocks[name]) {
		return null;
	}
	if (layout === undefined) {
		return {};
	}
	if (
		!isObject(layout) ||
		Object.keys(layout).some(
			(device) => !Object.hasOwn(support.devices, device)
		)
	) {
		return null;
	}
	const normalized = {};
	for (const device of Object.keys(support.devices)) {
		if (!Object.hasOwn(layout, device)) {
			continue;
		}
		const values = layout[device];
		if (
			!isObject(values) ||
			Object.keys(values).some(
				(key) =>
					!Object.hasOwn(support.properties, key) ||
					!appliesTo(name, support.properties[key])
			)
		) {
			return null;
		}
		const entry = {};
		for (const [key, definition] of Object.entries(support.properties)) {
			if (!Object.hasOwn(values, key)) {
				continue;
			}
			if (
				typeof values[key] !== 'string' &&
				typeof values[key] !== 'number'
			) {
				return null;
			}
			if (!validateValue(values[key], definition)) {
				return null;
			}
			entry[key] =
				typeof values[key] === 'string'
					? values[key].trim()
					: Math.round(values[key] * 10000) / 10000 || 0;
		}
		if (Object.keys(entry).length) {
			normalized[device] = entry;
		}
	}
	return normalized;
}

export function getLayoutClass(name, layout) {
	const normalized = sanitizeLayout(name, layout);
	if (!normalized || !Object.keys(normalized).length) {
		return '';
	}
	return `dsgo-layout-${layoutHash(name + JSON.stringify(normalized))}`;
}

/**
 * Compile without DOM style siblings; editor and frontend share routing.
 * @param {string} name     Block name.
 * @param {Object} layout   Optional overrides.
 * @param {string} selector Editor selector, when provided.
 */
export function compileLayoutCSS(name, layout, selector) {
	const normalized = sanitizeLayout(name, layout);
	const className = getLayoutClass(name, normalized || undefined);
	if (!className) {
		return '';
	}
	const root = selector || `.${className}.${className}`;
	const inner = `${root} > ${support.blocks[name].inner}`;
	const placement = `${root}:not(.dsgo-grid--match-rows > .dsgo-grid__inner > *)`;
	let css = '';
	for (const [device, values] of Object.entries(normalized)) {
		const rules = {};
		for (const [key, value] of Object.entries(values)) {
			const definition = support.properties[key];
			let target = definition.target === 'inner' ? inner : root;
			if (['gridColumn', 'gridRow', 'gridArea'].includes(key)) {
				target = placement;
			}
			rules[target] =
				(rules[target] || '') + `${definition.css}:${value}!important;`;
		}
		const deviceCSS = Object.entries(rules)
			.map(
				([target, declarations]) =>
					`${target}{${declarations.slice(0, -1)}}`
			)
			.join('');
		css += support.devices[device]
			? `@media (max-width:${support.devices[device]}px){${deviceCSS}}`
			: deviceCSS;
	}
	return css;
}

/**
 * A reset deletes the override so the preceding breakpoint inherits.
 * @param {string}        name   Block name.
 * @param {Object}        layout Optional overrides.
 * @param {string}        device Responsive viewport key.
 * @param {string}        key    Property key.
 * @param {string|number} value  New value, empty to reset.
 */
export function updateLayout(name, layout, device, key, value) {
	const next = { ...layout, [device]: { ...layout?.[device] } };
	if (value === '') {
		delete next[device][key];
	} else {
		next[device][key] = value;
	}
	return sanitizeLayout(name, next);
}
