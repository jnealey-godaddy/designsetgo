/** Bounded CSS grammar; keep in sync with Layout_Expression in PHP. */
const units =
	'px|em|rem|ex|ch|vw|vh|vmin|vmax|svw|svh|lvw|lvh|dvw|dvh|cm|mm|in|pt|pc|%';
const scalar = new RegExp(`^[+-]?(?:[0-9]*\\.)?[0-9]+(?:${units})$`);

function split(value, delimiter) {
	const parts = [];
	let start = 0;
	let depth = 0;
	for (let i = 0; i < value.length; i++) {
		if (value[i] === '(') {
			depth++;
		} else if (value[i] === ')') {
			depth--;
		}
		if (
			depth === 0 &&
			(delimiter === ' ' ? /\s/.test(value[i]) : value[i] === delimiter)
		) {
			const part = value.slice(start, i).trim();
			if (part !== '' || delimiter === ',') {
				parts.push(part);
			}
			start = i + 1;
		}
	}
	parts.push(value.slice(start).trim());
	return parts;
}

function length(value) {
	value = value.trim();
	if (value === '0' || scalar.test(value)) {
		return true;
	}
	const match = value.match(/^(var|calc|min|max|clamp)\(([\s\S]*)\)$/);
	if (!match) {
		return false;
	}
	const args = split(match[2], ',');
	if (match[1] === 'var') {
		return (
			args.length <= 2 &&
			/^--[a-zA-Z_][a-zA-Z0-9_-]*$/.test(args[0]) &&
			(args.length === 1 || length(args[1]))
		);
	}
	if (match[1] === 'calc') {
		return args.length === 1 && math(args[0]) === 1;
	}
	if (match[1] === 'clamp' && args.length !== 3) {
		return false;
	}
	return args.every((arg) => math(arg) === 1);
}

function math(value) {
	value = value.trim();
	for (const operators of ['+-', '*/']) {
		let depth = 0;
		for (let i = value.length - 1; i >= 0; i--) {
			const character = value[i];
			if (character === ')') {
				depth++;
			} else if (character === '(') {
				depth--;
			}
			if (depth !== 0 || !operators.includes(character) || i === 0) {
				continue;
			}
			const left = value.slice(0, i).trimEnd();
			if (left === '' || /[+*/(-]$/.test(left)) {
				continue;
			}
			if (
				operators === '+-' &&
				(!/\s/.test(value[i - 1]) ||
					value[i + 1] === undefined ||
					!/\s/.test(value[i + 1]))
			) {
				continue;
			}
			const right = value.slice(i + 1).trim();
			const a = math(left);
			const b = math(right);
			if (a < 0 || b < 0) {
				return -1;
			}
			if (character === '+' || character === '-') {
				return a === b ? a : -1;
			}
			if (character === '/') {
				return b === 0 && !/^[+-]?0(?:\.0+)?$/.test(right) ? a : -1;
			}
			return a === 1 && b === 1 ? -1 : Math.max(a, b);
		}
	}
	if (/^[+-]?(?:[0-9]*\.)?[0-9]+$/.test(value)) {
		return 0;
	}
	if (value.startsWith('(') && value.endsWith(')')) {
		return math(value.slice(1, -1));
	}
	return length(value) ? 1 : -1;
}

function tracks(value) {
	for (const track of split(value, ' ')) {
		if (/^-(?:[0-9]|\.[0-9])/.test(track)) {
			return false;
		}
		const fit = track.match(/^fit-content\(([\s\S]*)\)$/);
		if (
			fit &&
			length(fit[1]) &&
			!/^-(?:[0-9]|\.[0-9])/.test(fit[1].trim())
		) {
			continue;
		}
		if (
			['auto', 'min-content', 'max-content'].includes(track) ||
			/^(?:[0-9]*\.)?[0-9]+fr$/.test(track) ||
			length(track)
		) {
			continue;
		}
		const match = track.match(/^(repeat|minmax)\(([\s\S]*)\)$/);
		if (!match) {
			return false;
		}
		const args = split(match[2], ',');
		if (args.length !== 2) {
			return false;
		}
		if (match[1] === 'repeat') {
			if (
				!/^[1-9][0-9]?$/.test(args[0]) ||
				/\brepeat\(/.test(args[1]) ||
				!tracks(args[1])
			) {
				return false;
			}
		} else if (
			!tracks(args[0]) ||
			!tracks(args[1]) ||
			split(args[0], ' ').length > 1 ||
			split(args[1], ' ').length > 1 ||
			/fr$|^(?:repeat|minmax|fit-content)\(/.test(args[0]) ||
			/^(?:repeat|minmax|fit-content)\(/.test(args[1])
		) {
			return false;
		}
	}
	return true;
}

export function validExpression(value, allowTracks = false) {
	if (/[^a-zA-Z0-9_\s.,%()+*/-]/.test(value)) {
		return false;
	}
	let depth = 0;
	for (const character of value) {
		if (character === '(') {
			depth++;
		} else if (character === ')') {
			depth--;
		}
		if (depth < 0 || depth > 8) {
			return false;
		}
	}
	return depth === 0 && (allowTracks ? tracks(value) : length(value));
}
