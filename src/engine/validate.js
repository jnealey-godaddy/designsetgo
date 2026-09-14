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
 * @param {Object} blocksApi Object exposing `createBlock, serialize, parse, getBlockType`.
 * @param {string} markup    Serialized block markup to validate.
 * @return {{ status: 'valid'|'invalid', invalid: { path: string, block: string, reason: string }[] }}
 *         Validation result. `invalid` is empty when `status` is `'valid'`.
 */
export function validate(blocksApi, markup) {
	const parsedBlocks = withQuietConsole(() => blocksApi.parse(markup));
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
				.flatMap((issue) => issue.args || [])
				.join(' ')
				.slice(0, MAX_REASON_LENGTH);

			invalid.push({ path, block: block.name, reason });
		}
	});

	return {
		status: invalid.length ? 'invalid' : 'valid',
		invalid,
	};
}
