/**
 * Validates already-serialized block markup with the real
 * `@wordpress/blocks` parser — the same check the block editor runs when it
 * decides whether a block needs "Attempt Recovery".
 */
import { walkTree } from './tree';
import { withQuietConsole } from './quiet';

/** Cap on a single invalid entry's `reason`, matching the brief exactly. */
const MAX_REASON_LENGTH = 500;

/**
 * Renders one arg the way a console would: strings as-is, everything else
 * as JSON (falling back to `String()` for values JSON can't encode).
 *
 * @param {*} value Arg value.
 * @return {string} Printable text.
 */
function printable(value) {
	if (typeof value === 'string') {
		return value;
	}
	try {
		const json = JSON.stringify(value);
		return json === undefined ? String(value) : json;
	} catch (error) {
		return String(value);
	}
}

/**
 * Formats a parser validation issue — `{ log, args }`, where `args[0]` is a
 * printf-style template (`'Expected %s but saw %o.'`) — into readable text,
 * filling each `%s`/`%o`/`%d` from the remaining args in order. Args left
 * over once every placeholder is filled are appended, space-separated.
 *
 * @param {{ args?: Array }} issue Validation issue from `block.validationIssues`.
 * @return {string} Readable reason.
 */
export function formatValidationIssue(issue) {
	const args = Array.isArray(issue?.args) ? [...issue.args] : [];
	if (!args.length) {
		return '';
	}

	const template = printable(args.shift());
	const text = template.replace(/%[sod]/g, (placeholder) =>
		args.length ? printable(args.shift()) : placeholder
	);

	return [text, ...args.map(printable)].join(' ');
}

/**
 * Collects invalid entries from an already-parsed block list.
 *
 * @param {Object[]} parsedBlocks Blocks returned by `parse()`.
 * @return {{ path: string, block: string, reason: string }[]} Invalid entries.
 */
export function findInvalidBlocks(parsedBlocks) {
	const invalid = [];

	walkTree(parsedBlocks, (block, path) => {
		if (block.name === 'core/missing') {
			invalid.push({
				path,
				block: block.name,
				reason: 'block type is not registered',
			});
			return;
		}

		if (block.isValid === false) {
			const reason = (block.validationIssues || [])
				.map(formatValidationIssue)
				.filter(Boolean)
				.join(' ')
				.slice(0, MAX_REASON_LENGTH);

			invalid.push({ path, block: block.name, reason });
		}
	});

	return invalid;
}

/**
 * @param {Object} blocksApi Object exposing `createBlock, serialize, parse, getBlockType`.
 * @param {string} markup    Serialized block markup to validate.
 * @return {{ status: 'valid'|'invalid', invalid: { path: string, block: string, reason: string }[] }}
 *         Validation result. `invalid` is empty when `status` is `'valid'`.
 */
export function validate(blocksApi, markup) {
	const parsedBlocks = withQuietConsole(() => blocksApi.parse(markup));
	const invalid = findInvalidBlocks(parsedBlocks);

	return {
		status: invalid.length ? 'invalid' : 'valid',
		invalid,
	};
}
