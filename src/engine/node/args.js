/**
 * Parses the Node engine CLI's `argv` (already stripped of `node` and the
 * script path) into `{ command, file, flags }`.
 *
 * Plain positional/flag parsing — no dependency, since this bundle must stay
 * a single chunk (see `webpack.engine.config.js`). `file` is always an
 * array: `assemble`/`lint` use its first entry, `validate` uses all of them.
 */
'use strict';

/** Long flags that consume the following argv entry as their value. */
const VALUE_FLAGS = {
	'--context': 'context',
	'--max-warnings': 'maxWarnings',
	'--out': 'out',
};

/** Long flags that are booleans (presence alone sets them `true`). */
const BOOLEAN_FLAGS = {
	'--json': 'json',
	'--lint': 'lint',
};

/**
 * @param {string[]} argv `process.argv.slice(2)` — command name first, then
 *                        file paths and flags in any order.
 * @return {{ command: string|undefined, file: string[], flags: Object }}
 *   Parsed command, positional file arguments, and named flags.
 */
function parseArgs(argv) {
	const [command, ...rest] = argv;
	const file = [];
	const flags = {};

	for (let i = 0; i < rest.length; i++) {
		const arg = rest[i];

		if (BOOLEAN_FLAGS[arg]) {
			flags[BOOLEAN_FLAGS[arg]] = true;
			continue;
		}

		if (VALUE_FLAGS[arg]) {
			flags[VALUE_FLAGS[arg]] = rest[++i];
			continue;
		}

		file.push(arg);
	}

	return { command, file, flags };
}

module.exports = { parseArgs };
