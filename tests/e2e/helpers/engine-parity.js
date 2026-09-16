/**
 * Normalizes engine-assembled markup so Node (jsdom) output and browser
 * (`window.designsetgoEngine`) output can be compared for DesignSetGo-block
 * parity even though core block markup can differ across the WordPress
 * versions the two sides run against (Node pins 6.7; the browser runs the
 * site's own WordPress).
 *
 * `extractDesignSetGoRegions()` finds every OUTERMOST
 * `<!-- wp:designsetgo/… --> … <!-- /wp:designsetgo/… -->` (or self-closing
 * `<!-- wp:designsetgo/… /-->`) region in a markup string, and inside each
 * one replaces every non-DesignSetGo block's OWN markup — its comment pair
 * and any literal HTML directly inside it — with a single `<!--core-->`
 * placeholder. DesignSetGo blocks nested inside a kept region are preserved
 * and recursed into, so a DesignSetGo block inside a DesignSetGo block still
 * gets its own non-DesignSetGo children collapsed.
 *
 * Collapsing a non-DesignSetGo block does NOT stop the walk: a
 * `designsetgo/…` block nested inside a core block (e.g.
 * `designsetgo/section > core/group > designsetgo/icon-button`) is real
 * DesignSetGo structure and must still be compared, or a parity bug in that
 * position would go undetected. The deterministic representation is:
 * `CORE_PLACEHOLDER` for the collapsed block's own markup, immediately
 * followed by each child's normalized rendering in document order (a
 * DesignSetGo child renders in full; a non-DesignSetGo child recurses under
 * this same rule). A non-DesignSetGo subtree with no DesignSetGo descendant
 * anywhere inside it still collapses to a single bare `CORE_PLACEHOLDER`
 * token — no trailing children output — so this is backward-compatible with
 * the common case and never double-collapses nested core-in-core.
 *
 * This is a hand-rolled scanner rather than a reuse of
 * `@wordpress/block-serialization-default-parser` because that package only
 * hands back parsed attribute objects and joined innerHTML, not the raw
 * comment bytes — reproducing a kept block's original comment text from
 * `JSON.stringify(attrs)` risks key-order/spacing drift that would
 * masquerade as a real parity failure. Scanning for the raw
 * `<!-- wp:name {...} -->` / `/-->` / `<!-- /wp:name -->` boundaries keeps
 * every byte this module doesn't intentionally collapse untouched.
 */

'use strict';

const CORE_PLACEHOLDER = '<!--core-->';

const TAG_START_RE = /<!--\s*(\/)?wp:([a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)?)/g;

/**
 * @param {string} markup Markup to scan.
 * @param {number} pos    Index to start scanning from.
 * @return {number} Index of the first non-whitespace character at or after `pos`.
 */
function skipWhitespace(markup, pos) {
	let i = pos;
	while (i < markup.length && /\s/.test(markup[i])) {
		i++;
	}
	return i;
}

/**
 * Finds the index of the `}` that closes the JSON object starting at
 * `markup[start]` (`markup[start] === '{'`), balancing brace depth and
 * skipping over string literals (including escaped characters) so a brace
 * inside a quoted attribute value is never mistaken for structure.
 *
 * @param {string} markup Markup containing the JSON object.
 * @param {number} start  Index of the opening `{`.
 * @return {number} Index of the matching closing `}`.
 */
function findAttrsEnd(markup, start) {
	let depth = 0;
	let inString = false;

	for (let i = start; i < markup.length; i++) {
		const char = markup[i];
		if (inString) {
			if (char === '\\') {
				i++;
			} else if (char === '"') {
				inString = false;
			}
			continue;
		}
		if (char === '"') {
			inString = true;
		} else if (char === '{') {
			depth++;
		} else if (char === '}') {
			depth--;
			if (depth === 0) {
				return i;
			}
		}
	}

	throw new Error('engine-parity: unterminated block attrs JSON');
}

/**
 * Tokenizes every `<!-- wp:name -->`, `<!-- wp:name /-->`, and
 * `<!-- /wp:name -->` block comment in `markup`, in document order.
 *
 * @param {string} markup Markup to tokenize.
 * @return {Array<{type: 'open'|'void'|'close', name: string, start: number, end: number}>} Tokens.
 */
function tokenize(markup) {
	const tokens = [];
	TAG_START_RE.lastIndex = 0;
	let match;

	while ((match = TAG_START_RE.exec(markup))) {
		const isClosing = Boolean(match[1]);
		const name = match[2];
		let pos = skipWhitespace(markup, TAG_START_RE.lastIndex);

		if (!isClosing && markup[pos] === '{') {
			pos = skipWhitespace(markup, findAttrsEnd(markup, pos) + 1);
		}

		let isVoid = false;
		if (markup[pos] === '/') {
			isVoid = true;
			pos = skipWhitespace(markup, pos + 1);
		}

		if (markup.slice(pos, pos + 3) !== '-->') {
			throw new Error(
				`engine-parity: malformed block comment near index ${match.index}`
			);
		}
		pos += 3;

		let type = 'open';
		if (isClosing) {
			type = 'close';
		} else if (isVoid) {
			type = 'void';
		}

		tokens.push({
			type,
			name,
			start: match.index,
			end: pos,
		});
		TAG_START_RE.lastIndex = pos;
	}

	return tokens;
}

/**
 * Recursive-descent pass over `tokenize()`'s output: turns the flat token
 * list into a tree of block spans, honoring nesting via open/close pairs.
 *
 * @param {Array<Object>} tokens Tokens from `tokenize()`.
 * @param {number}        index  Index into `tokens` to start at.
 * @return {{nodes: Array<Object>, next: number}} Sibling nodes found before a
 *   `close` token (or the end of the list), and the index to resume at.
 */
function parseLevel(tokens, index) {
	const nodes = [];
	let i = index;

	while (i < tokens.length && tokens[i].type !== 'close') {
		const token = tokens[i];

		if (token.type === 'void') {
			nodes.push({
				name: token.name,
				start: token.start,
				end: token.end,
				isVoid: true,
				children: [],
				innerStart: token.end,
				innerEnd: token.end,
			});
			i++;
			continue;
		}

		const { nodes: children, next } = parseLevel(tokens, i + 1);
		const closeToken = tokens[next];
		if (!closeToken || closeToken.name !== token.name) {
			throw new Error(
				`engine-parity: "${token.name}" is missing its closing comment`
			);
		}
		nodes.push({
			name: token.name,
			start: token.start,
			end: closeToken.end,
			isVoid: false,
			children,
			innerStart: token.end,
			innerEnd: closeToken.start,
		});
		i = next + 1;
	}

	return { nodes, next: i };
}

/**
 * @param {string} name Block name.
 * @return {boolean} True for a `designsetgo/…` block.
 */
function isDesignSetGo(name) {
	return name.startsWith('designsetgo/');
}

/**
 * @param {Object} node Parsed node from `parseLevel()`.
 * @return {boolean} True when `node` is a `designsetgo/…` block, or has one
 *   anywhere among its descendants.
 */
function containsDesignSetGo(node) {
	if (isDesignSetGo(node.name)) {
		return true;
	}
	return node.children.some((child) => containsDesignSetGo(child));
}

/**
 * Renders one parsed node back to a string. A `designsetgo/…` node
 * reproduces its own comment bytes untouched and recurses into its
 * children. A non-DesignSetGo node collapses its own markup to
 * `CORE_PLACEHOLDER`; if it has no DesignSetGo descendant anywhere inside,
 * that placeholder is the whole result, but if it does, each child's
 * normalized rendering is appended after the placeholder in document order
 * — see the module doc comment for why this doesn't stop the walk.
 *
 * @param {Object} node   Parsed node from `parseLevel()`.
 * @param {string} markup Original markup the node's offsets index into.
 * @return {string} Normalized rendering of `node`.
 */
function renderNode(node, markup) {
	if (!isDesignSetGo(node.name)) {
		if (!containsDesignSetGo(node)) {
			return CORE_PLACEHOLDER;
		}
		return (
			CORE_PLACEHOLDER +
			node.children.map((child) => renderNode(child, markup)).join('')
		);
	}
	if (node.isVoid) {
		return markup.slice(node.start, node.end);
	}

	const openTag = markup.slice(node.start, node.innerStart);
	const closeTag = markup.slice(node.innerEnd, node.end);

	let body = '';
	let cursor = node.innerStart;
	for (const child of node.children) {
		body += markup.slice(cursor, child.start);
		body += renderNode(child, markup);
		cursor = child.end;
	}
	body += markup.slice(cursor, node.innerEnd);

	return openTag + body + closeTag;
}

/**
 * Extracts every outermost `designsetgo/…` block region from `markup`, each
 * normalized so nested non-DesignSetGo blocks (any depth) collapse to
 * `CORE_PLACEHOLDER` while DesignSetGo structure — including further nested
 * DesignSetGo blocks — is preserved byte-for-byte. Non-DesignSetGo content
 * at the top level (and anything between top-level regions) is dropped.
 *
 * @param {string} markup Full assembled markup (e.g. an engine `assemble()` result).
 * @return {string[]} One normalized region string per top-level `designsetgo/…` block, in document order.
 */
function extractDesignSetGoRegions(markup) {
	const tokens = tokenize(markup);
	const { nodes } = parseLevel(tokens, 0);
	return nodes
		.filter((node) => isDesignSetGo(node.name))
		.map((node) => renderNode(node, markup));
}

module.exports = {
	CORE_PLACEHOLDER,
	extractDesignSetGoRegions,
};
