/** Strict, shared-shape CSS value validation; never accepts arbitrary CSS. */
import { validExpression } from './expression';
const identifier = /^[a-zA-Z_][a-zA-Z0-9_-]*$/;
const reserved = /^(span|inherit|initial|unset|revert|revert-layer)$/i;

export function validIdentifier(value) {
	return identifier.test(value) && !reserved.test(value);
}

export function validAreas(value) {
	if (value === 'none') {
		return true;
	}
	if (!/^(?:"[a-zA-Z0-9_.\t -]+"\s*)+$/.test(value)) {
		return false;
	}
	const matches = [...value.matchAll(/"([^"]+)"/g)];
	if (!matches.length || value.replace(/"[^"]+"/g, '').trim()) {
		return false;
	}
	const rows = matches.map((match) => match[1].trim().split(/\s+/));
	if (
		rows.some(
			(row) =>
				row.length !== rows[0].length ||
				row.some(
					(cell) =>
						!/^\.+$/.test(cell) &&
						(!validIdentifier(cell) || /^(none|auto)$/i.test(cell))
				)
		)
	) {
		return false;
	}
	const names = new Set(rows.flat().filter((cell) => !/^\.+$/.test(cell)));
	for (const name of names) {
		const points = [];
		rows.forEach((row, y) =>
			row.forEach((cell, x) => cell === name && points.push([x, y]))
		);
		const xs = points.map(([x]) => x);
		const ys = points.map(([, y]) => y);
		if (
			(Math.max(...xs) - Math.min(...xs) + 1) *
				(Math.max(...ys) - Math.min(...ys) + 1) !==
			points.length
		) {
			return false;
		}
	}
	return true;
}

function validLine(value) {
	const parts = value.split('/').map((part) => part.trim());
	return (
		parts.length <= 2 &&
		parts.every((part) => {
			if (part === 'auto' || validIdentifier(part)) {
				return true;
			}
			if (/^-?[1-9]\d{0,2}$/.test(part)) {
				return true;
			}
			return /^span\s+[1-9]\d{0,2}$/.test(part);
		})
	);
}

export function validateValue(value, definition) {
	if (['integer', 'number'].includes(definition.type)) {
		return (
			typeof value === 'number' &&
			Number.isFinite(value) &&
			Math.abs(value) <= 9999 &&
			Math.abs(Math.round(value * 10000) - value * 10000) <= 0.0000001 &&
			(definition.type === 'integer'
				? Number.isInteger(value)
				: value >= 0)
		);
	}
	if (
		typeof value !== 'string' ||
		value.length > 512 ||
		/[^\x09\x0A\x0D\x20-\x7E]/.test(value) ||
		!value.trim()
	) {
		return false;
	}
	value = value.trim();
	if (definition.type === 'enum') {
		return definition.values.includes(value);
	}
	if (definition.type === 'ratio') {
		return (
			value === 'auto' ||
			(/^(?:\d*\.)?\d+(?:\s*\/\s*(?:\d*\.)?\d+)?$/.test(value) &&
				value.split('/').every((part) => Number(part) > 0))
		);
	}
	if (definition.type === 'identifier') {
		return validIdentifier(value);
	}
	if (definition.type === 'line') {
		return validLine(value);
	}
	if (definition.type === 'areas') {
		return validAreas(value);
	}
	const keywords = {
		size: ['auto', 'min-content', 'max-content', 'fit-content', 'stretch'],
		offset: ['auto'],
		gap: ['normal'],
		tracks: ['none', 'auto', 'min-content', 'max-content'],
	};
	if (
		definition.type === 'size' &&
		['max-width', 'max-height'].includes(definition.css)
	) {
		keywords.size = [
			'none',
			'min-content',
			'max-content',
			'fit-content',
			'stretch',
		];
	}
	if (definition.type !== 'offset' && /^-(?:[0-9]|\.[0-9])/.test(value)) {
		return false;
	}
	return (
		keywords[definition.type]?.includes(value) ||
		validExpression(value, definition.type === 'tracks')
	);
}
