/**
 * Detects agent-submitted block attribute names a block type doesn't
 * register — a misspelled (`backgroundColour`) or invented name that
 * `createBlock()` would otherwise silently drop with no feedback. This is
 * the engine's authoritative check: PHP's `Tree_Attributes` deliberately
 * allows unknown names through (JS-only extensions add attributes PHP may
 * not know about), so this is the only place attribute names are validated
 * as a full set.
 *
 * "Known" is every key `getBlockType(name).attributes` has after full
 * registration. WordPress folds every block-support attribute (`style`,
 * `className`, `anchor`, `backgroundColor`, `lock`, `metadata`, ...) into
 * that object once block.json, extensions, and the block's own `index.js`
 * have all registered — verified empirically against `designsetgo/section`,
 * `core/paragraph`, `core/group`, and `core/image` under full registration
 * (`registerForJest()`/`registerForNode()`): every support attribute those
 * blocks carry showed up in `getBlockType().attributes`. No separate
 * allowlist is needed as a result.
 */
import { walkTree } from './tree';

/** Below this length, a suggestion needs distance <= 2; at or above, <= 3. */
const LONG_NAME_LENGTH = 10;

/**
 * Full Damerau-Levenshtein distance (arbitrary-position transpositions,
 * not just adjacent-character swaps restricted to one substitution) between
 * two strings, via the classic Damerau/Levenshtein 1965/1966 dynamic
 * program. No dependency — this is the only place the engine needs it.
 *
 * @param {string} a First string.
 * @param {string} b Second string.
 * @return {number} Edit distance.
 */
function damerauLevenshteinDistance(a, b) {
	const lenA = a.length;
	const lenB = b.length;

	if (a === b) {
		return 0;
	}
	if (!lenA) {
		return lenB;
	}
	if (!lenB) {
		return lenA;
	}

	const maxDist = lenA + lenB;
	const lastRow = new Map();
	// d is (lenA + 2) x (lenB + 2), offset by one extra row/column so the
	// algorithm's sentinel row/column (index 0) never collides with a real
	// character index.
	const d = [];
	for (let i = 0; i <= lenA + 1; i += 1) {
		d.push(new Array(lenB + 2).fill(0));
	}
	d[0][0] = maxDist;
	for (let i = 0; i <= lenA; i += 1) {
		d[i + 1][0] = maxDist;
		d[i + 1][1] = i;
	}
	for (let j = 0; j <= lenB; j += 1) {
		d[0][j + 1] = maxDist;
		d[1][j + 1] = j;
	}

	for (let i = 1; i <= lenA; i += 1) {
		let lastMatchCol = 0;
		for (let j = 1; j <= lenB; j += 1) {
			const i1 = lastRow.get(b[j - 1]) || 0;
			const j1 = lastMatchCol;
			let cost = 1;

			if (a[i - 1] === b[j - 1]) {
				cost = 0;
				lastMatchCol = j;
			}

			d[i + 1][j + 1] = Math.min(
				d[i][j] + cost, // substitution (or match)
				d[i + 1][j] + 1, // insertion
				d[i][j + 1] + 1, // deletion
				d[i1][j1] + (i - i1 - 1) + 1 + (j - j1 - 1) // transposition
			);
		}
		lastRow.set(a[i - 1], i);
	}

	return d[lenA + 1][lenB + 1];
}

/**
 * Edit distance between an attribute name and a candidate known name, with
 * a case-insensitive match always counting as distance 0 — a pure casing
 * mismatch (`backgroundcolor` for `backgroundColor`) should always surface
 * as "did you mean", regardless of how many characters differ in case.
 *
 * @param {string} name      Submitted (unknown) attribute name.
 * @param {string} candidate Known attribute name.
 * @return {number} Edit distance.
 */
export function attributeDistance(name, candidate) {
	if (name.toLowerCase() === candidate.toLowerCase()) {
		return 0;
	}
	return damerauLevenshteinDistance(name, candidate);
}

/**
 * Finds the closest known attribute name to `name`, when one is close
 * enough to plausibly be what the agent meant: distance <= 2, or <= 3 when
 * the longer of the two names is at least `LONG_NAME_LENGTH` characters (a
 * longer name has more room for an edit before it stops looking like a
 * typo). Ties break on `knownNames`' own order.
 *
 * @param {string}   name       Submitted (unknown) attribute name.
 * @param {string[]} knownNames The block type's registered attribute names.
 * @return {string|null} The closest name, or `null` when none is close enough.
 */
export function closestAttributeName(name, knownNames) {
	let best = null;
	let bestDistance = Infinity;

	knownNames.forEach((candidate) => {
		const distance = attributeDistance(name, candidate);
		const threshold =
			Math.max(name.length, candidate.length) >= LONG_NAME_LENGTH ? 3 : 2;

		if (distance <= threshold && distance < bestDistance) {
			best = candidate;
			bestDistance = distance;
		}
	});

	return best;
}

/**
 * Finds agent-submitted attribute names each block's own type doesn't
 * register, anywhere in `tree`. Skips nodes whose block name isn't
 * registered at all — `assemble()` already reports those as
 * `designsetgo_unknown_block`, and there is no schema to check an unknown
 * block's attributes against.
 *
 * @param {Object} blocksApi Object exposing `getBlockType`.
 * @param {Object} tree      A shape-valid agent block tree: `{ version, blocks }`.
 * @return {{ path: string, block: string, reason: string, code: string }[]}
 *   One entry per unknown attribute name, in document order.
 */
export function findUnknownAttributes(blocksApi, tree) {
	const problems = [];

	walkTree(tree.blocks, (node, path) => {
		const blockType = blocksApi.getBlockType(node.name);
		if (!blockType) {
			return;
		}

		const knownNames = Object.keys(blockType.attributes ?? {});
		const attributes =
			node.attributes && typeof node.attributes === 'object'
				? node.attributes
				: {};

		Object.keys(attributes).forEach((name) => {
			if (knownNames.includes(name)) {
				return;
			}

			const suggestion = closestAttributeName(name, knownNames);
			const reason = suggestion
				? `unknown attribute "${name}" for ${node.name} — did you mean "${suggestion}"?`
				: `unknown attribute "${name}" for ${node.name}`;

			problems.push({
				path,
				block: node.name,
				reason,
				code: 'designsetgo_unknown_attribute',
			});
		});
	});

	return problems;
}
