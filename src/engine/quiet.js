/**
 * Console muting for the agent block engine.
 *
 * WordPress's block registration, parsing, and validation machinery logs
 * liberally through `console.{log,info,warn,error,groupCollapsed,groupEnd}` —
 * "already registered" notices, block-validation diffs, deprecation
 * warnings. None of that is actionable for an agent driving the engine, and
 * in Jest it trips `@wordpress/jest-console`'s "console.error/warn should
 * not be used unless explicitly expected" failures. The engine must mute
 * this noise itself rather than push the problem onto Jest config or onto
 * every caller.
 */

const MUTED_METHODS = [
	'log',
	'info',
	'warn',
	'error',
	'groupCollapsed',
	'groupEnd',
];

/**
 * Runs `fn` with the console methods above replaced by no-ops, restoring the
 * originals in a `finally` block so a throw from `fn` still un-mutes.
 *
 * Synchronous only: `fn` must not return a Promise. Muting a Promise's
 * eventual `.then()` continuation would leave the console silenced past the
 * point this function returns.
 *
 * @param {() => any} fn Synchronous function to run with a quiet console.
 * @return {any} Whatever `fn` returns.
 */
export function withQuietConsole(fn) {
	const originals = {};

	MUTED_METHODS.forEach((method) => {
		originals[method] = console[method]; // eslint-disable-line no-console
		console[method] = () => {}; // eslint-disable-line no-console
	});

	try {
		return fn();
	} finally {
		MUTED_METHODS.forEach((method) => {
			console[method] = originals[method]; // eslint-disable-line no-console
		});
	}
}
