/* global DOMParser */

/**
 * Safe SVG path data utilities for the Text Path block.
 *
 * These helpers deliberately accept only plain path commands and numeric
 * values. They return data rather than SVG markup so renderers remain in
 * control of escaping and DOM construction.
 */

const MAX_SVG_PATH_INPUT_LENGTH = 12 * 1024;
const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
const NUMBER_PATTERN =
	'[+-]?(?:(?:\\d+\\.\\d*|\\.\\d+|\\d+)(?:[eE][+-]?\\d+)?)';
const VIEW_BOX_PATTERN = new RegExp(
	`^\\s*${NUMBER_PATTERN}(?:[\\s,]+${NUMBER_PATTERN}){3}\\s*$`
);
const PATH_TOKEN_PATTERN = new RegExp(
	`[AaCcHhLlMmQqSsTtVvZz]|${NUMBER_PATTERN}`,
	'g'
);
const NUMBER_TOKEN_PATTERN = new RegExp(`^${NUMBER_PATTERN}$`);

const PATH_ARGUMENT_COUNTS = {
	A: 7,
	C: 6,
	H: 1,
	L: 2,
	M: 2,
	Q: 4,
	S: 4,
	T: 2,
	V: 1,
	Z: 0,
};

/**
 * Stable text-path presets. Keep these paths as data, not generated SVG, so
 * both editor and frontend renderers can use the same safe values.
 */
export const TEXT_PATH_PRESETS = Object.freeze({
	wave: Object.freeze({
		viewBox: '0 0 1000 200',
		d: 'M 0 100 C 250 0 750 200 1000 100',
	}),
	arc: Object.freeze({
		viewBox: '0 0 1000 200',
		d: 'M 0 200 Q 500 0 1000 200',
	}),
	circle: Object.freeze({
		viewBox: '0 0 1000 1000',
		d: 'M 500 0 A 500 500 0 1 1 499.9 0',
	}),
	line: Object.freeze({
		viewBox: '0 0 1000 200',
		d: 'M 0 100 L 1000 100',
	}),
	oval: Object.freeze({
		viewBox: '0 0 1000 500',
		d: 'M 500 0 A 500 250 0 1 1 499.9 0',
	}),
	spiral: Object.freeze({
		viewBox: '0 0 1000 1000',
		d: 'M 500 500 C 500 250 850 250 850 500 C 850 850 150 850 150 500 C 150 50 950 50 950 500',
	}),
});

function isSafeNumber(value) {
	return NUMBER_TOKEN_PATTERN.test(value) && Number.isFinite(Number(value));
}

function isPathCommand(token) {
	return /^[AaCcHhLlMmQqSsTtVvZz]$/.test(token);
}

function isLegalPathSeparator(separator, previous, next) {
	if (separator === '' || /^\s+$/.test(separator)) {
		return true;
	}

	return (
		/^\s*,\s*$/.test(separator) &&
		previous &&
		next &&
		!isPathCommand(previous) &&
		!isPathCommand(next)
	);
}

function normaliseViewBox(viewBox) {
	if (
		typeof viewBox !== 'string' ||
		viewBox.length === 0 ||
		viewBox.length > MAX_SVG_PATH_INPUT_LENGTH ||
		!VIEW_BOX_PATTERN.test(viewBox)
	) {
		return null;
	}

	const values = viewBox.trim().split(/[\s,]+/);
	if (
		values.length !== 4 ||
		!values.every(isSafeNumber) ||
		Number(values[2]) <= 0 ||
		Number(values[3]) <= 0
	) {
		return null;
	}

	return values.join(' ');
}

function isSafePathData(pathData) {
	if (
		typeof pathData !== 'string' ||
		pathData.length === 0 ||
		pathData.length > MAX_SVG_PATH_INPUT_LENGTH
	) {
		return false;
	}

	const tokens = [];
	let cursor = 0;
	let match;
	PATH_TOKEN_PATTERN.lastIndex = 0;

	while ((match = PATH_TOKEN_PATTERN.exec(pathData))) {
		if (
			!isLegalPathSeparator(
				pathData.slice(cursor, match.index),
				tokens[tokens.length - 1],
				match[0]
			)
		) {
			return false;
		}
		tokens.push(match[0]);
		cursor = PATH_TOKEN_PATTERN.lastIndex;
	}

	if (tokens.length === 0 || !/^\s*$/.test(pathData.slice(cursor))) {
		return false;
	}

	let command = null;
	let argumentIndex = 0;
	let hasMove = false;
	let hasDrawableSegment = false;
	let endsWithClose = false;

	for (const token of tokens) {
		if (isPathCommand(token)) {
			if (
				command &&
				(argumentIndex === 0 ||
					argumentIndex % PATH_ARGUMENT_COUNTS[command] !== 0)
			) {
				return false;
			}

			command = token.toUpperCase();
			argumentIndex = 0;
			if (command === 'Z') {
				command = null;
				endsWithClose = true;
				continue;
			}
			endsWithClose = false;
			if (!hasMove && command !== 'M') {
				return false;
			}
			if (command === 'M') {
				hasMove = true;
			}
			continue;
		}

		if (!command || !isSafeNumber(token)) {
			return false;
		}

		if (
			command === 'A' &&
			(argumentIndex % PATH_ARGUMENT_COUNTS.A === 3 ||
				argumentIndex % PATH_ARGUMENT_COUNTS.A === 4) &&
			token !== '0' &&
			token !== '1'
		) {
			return false;
		}

		if (command !== 'M' || argumentIndex >= PATH_ARGUMENT_COUNTS.M) {
			hasDrawableSegment = true;
		}
		argumentIndex += 1;
		endsWithClose = false;
	}

	return (
		hasMove &&
		hasDrawableSegment &&
		(endsWithClose ||
			(Boolean(command) &&
				argumentIndex > 0 &&
				argumentIndex % PATH_ARGUMENT_COUNTS[command] === 0))
	);
}

/**
 * Return normalized data for a safe SVG text path.
 *
 * @param {Object} candidate Candidate path data.
 * @return {{viewBox: string, d: string}|null} Safe path data or null.
 */
export function normaliseTextPathData(candidate) {
	if (!candidate || typeof candidate !== 'object') {
		return null;
	}

	const viewBox = normaliseViewBox(candidate.viewBox);
	const pathData = candidate.d;
	if (!viewBox || !isSafePathData(pathData)) {
		return null;
	}

	return {
		viewBox,
		d: pathData.trim(),
	};
}

/**
 * Extract the first usable text path from untrusted SVG markup.
 *
 * @param {string} svg Raw SVG markup.
 * @return {{viewBox: string, d: string}|null} Safe path data or null.
 */
export function extractTextPathFromSvg(svg) {
	if (
		typeof svg !== 'string' ||
		svg.length === 0 ||
		svg.length > MAX_SVG_PATH_INPUT_LENGTH ||
		/<!\s*(?:doctype|entity)\b/i.test(svg) ||
		typeof DOMParser === 'undefined'
	) {
		return null;
	}

	const document = new DOMParser().parseFromString(svg, 'image/svg+xml');
	const root = document.documentElement;
	if (
		!root ||
		root.localName !== 'svg' ||
		root.namespaceURI !== SVG_NAMESPACE ||
		document.doctype ||
		document.getElementsByTagName('parsererror').length > 0
	) {
		return null;
	}

	const elements = root.getElementsByTagName('*');
	for (const element of elements) {
		const name = element.localName.toLowerCase();
		if (name === 'script' || name === 'foreignobject') {
			return null;
		}
	}

	const viewBox = root.getAttribute('viewBox');
	const paths = root.getElementsByTagNameNS(SVG_NAMESPACE, 'path');
	for (const path of paths) {
		const pathData = normaliseTextPathData({
			viewBox,
			d: path.getAttribute('d'),
		});
		if (pathData) {
			return pathData;
		}
	}

	return null;
}
