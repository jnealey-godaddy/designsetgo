/**
 * Command logic for the Node engine CLI, factored out of `cli.js` so it can
 * be exercised by Jest with a fake `bootEngine` — spawning the real built
 * bundle (as `tests/engine/cli.test.mjs` does) can't cheaply force a boot
 * throw or a non-empty `failures` array without corrupting real plugin
 * source, and this module's whole job is to make those branches reachable
 * without that.
 *
 * Deliberately requires neither `./dom` nor `./boot`: every side-effecting
 * dependency (`bootEngine`, file I/O, stdout/stderr) is injected, so this
 * file has no opinion about jsdom globals or the real block registry and
 * can run under plain Jest.
 */
'use strict';

const { parseArgs } = require('./args');
const { buildFixtureCases } = require('./fixture-cases');

const COMMANDS = ['assemble', 'validate', 'lint', 'fixture-cases'];

/**
 * @param {(file: string) => string} readFile Reads `file` as JSON.
 * @param {string}                   file     Path to read.
 * @return {*} Parsed JSON value.
 * @throws {Error} When `readFile` throws or the contents aren't valid JSON.
 */
function readJsonFile(readFile, file) {
	let raw;
	try {
		raw = readFile(file);
	} catch (error) {
		throw new Error(`Cannot read file "${file}": ${error.message}`);
	}
	try {
		return JSON.parse(raw);
	} catch (error) {
		throw new Error(`Cannot parse JSON in "${file}": ${error.message}`);
	}
}

/**
 * @param {(file: string) => string} readFile Reads `file` as text.
 * @param {string}                   file     Path to read.
 * @return {string} File contents.
 * @throws {Error} When `readFile` throws.
 */
function readTextFile(readFile, file) {
	try {
		return readFile(file);
	} catch (error) {
		throw new Error(`Cannot read file "${file}": ${error.message}`);
	}
}

/**
 * @param {string} file   Source file path (for the text-mode header line).
 * @param {Object} result `{ status, invalid }` from `engine.validate()`.
 * @return {string} Multi-line text report for one file.
 */
function formatValidateFile(file, result) {
	const lines = [`${file}: ${result.status}`];
	result.invalid.forEach((entry) => {
		lines.push(`  ${entry.path} ${entry.block}: ${entry.reason}`);
	});
	return lines.join('\n');
}

/**
 * @param {(content: string) => void}               stdout    Stdout writer.
 * @param {(file: string, content: string) => void} writeFile File writer.
 * @param {string}                                  content   Text to write to stdout (and `outFile`, if given).
 * @param {string}                                  [outFile] Optional path to also write `content` to.
 */
function emit(stdout, writeFile, content, outFile) {
	stdout(`${content}\n`);
	if (outFile) {
		writeFile(outFile, content);
	}
}

/**
 * @param {Object}                                    engine Bound engine from `bootEngine()`.
 * @param {*}                                         tree   Parsed candidate block tree.
 * @param {Object}                                    flags  Parsed CLI flags.
 * @param {{ stdout: Function, writeFile: Function }} io     Injected sinks.
 * @return {number} Exit code: 0 valid, 1 invalid.
 */
function runAssemble(engine, tree, flags, { stdout, writeFile }) {
	const report = engine.assemble(tree);
	const output = flags.json ? JSON.stringify(report, null, 2) : report.markup;

	emit(stdout, writeFile, output, flags.out);
	return report.status === 'valid' ? 0 : 1;
}

/**
 * @param {Object}                                    engine Bound engine from `bootEngine()`.
 * @param {string[]}                                  files  File paths to validate.
 * @param {string[]}                                  markup Each file's already-read contents, same order.
 * @param {Object}                                    flags  Parsed CLI flags.
 * @param {{ stdout: Function, writeFile: Function }} io     Injected sinks.
 * @return {number} Exit code: 0 all valid, 1 any invalid.
 */
function runValidate(engine, files, markup, flags, { stdout, writeFile }) {
	let anyInvalid = false;

	const results = files.map((file, index) => {
		const result = engine.validate(markup[index]);
		if (result.status !== 'valid') {
			anyInvalid = true;
		}
		return { file, status: result.status, invalid: result.invalid };
	});

	const output = flags.json
		? JSON.stringify({ files: results }, null, 2)
		: results
				.map((result) => formatValidateFile(result.file, result))
				.join('\n');

	emit(stdout, writeFile, output, flags.out);
	return anyInvalid ? 1 : 0;
}

/**
 * @param {Object}                                    blocksApi The `@wordpress/blocks` module `bootEngine()` registered into.
 * @param {Object}                                    flags     Parsed CLI flags.
 * @param {{ stdout: Function, writeFile: Function }} io        Injected sinks.
 * @return {number} Exit code: always 0 — generation cannot itself be "invalid".
 */
function runFixtureCases(blocksApi, flags, { stdout, writeFile }) {
	const cases = buildFixtureCases(blocksApi);
	const output = JSON.stringify(cases, null, 2);

	emit(stdout, writeFile, output, flags.out);
	return 0;
}

/**
 * Runs one CLI invocation against injected dependencies.
 *
 * @param {string[]}                                                                argv            `process.argv.slice(2)`.
 * @param {Object}                                                                  deps            Injected dependencies:
 * @param {() => { engine: Object, failures: { file: string, message: string }[] }} deps.bootEngine
 *                                                                                                  Boots the block registry and returns the bound engine. May throw.
 * @param {(file: string) => string}                                                deps.readFile   Reads a file as text. May throw.
 * @param {(file: string, content: string) => void}                                 deps.writeFile  Writes a file.
 * @param {(content: string) => void}                                               deps.stdout     Stdout sink.
 * @param {(content: string) => void}                                               deps.stderr     Stderr sink.
 * @return {number} Process exit code.
 */
function run(argv, { bootEngine, readFile, writeFile, stdout, stderr }) {
	const args = parseArgs(argv);

	if (!COMMANDS.includes(args.command)) {
		stderr(
			`Unknown command "${args.command || ''}". Usage: engine <assemble|validate|lint> <file> [options]\n`
		);
		return 2;
	}

	if (args.command === 'lint') {
		stderr('lint is not available yet\n');
		return 2;
	}

	// fixture-cases takes no file argument — it generates from the registry,
	// not from anything on disk — so only assemble/validate require one.
	if (args.command !== 'fixture-cases' && !args.file.length) {
		const usage =
			args.command === 'validate'
				? 'engine validate <file...> [--json] [--out <file>]'
				: 'engine assemble <tree.json> [--json] [--out <file>]';
		stderr(`Missing file argument. Usage: ${usage}\n`);
		return 2;
	}

	let input;
	if (args.command !== 'fixture-cases') {
		try {
			input =
				args.command === 'assemble'
					? readJsonFile(readFile, args.file[0])
					: args.file.map((file) => readTextFile(readFile, file));
		} catch (error) {
			stderr(`${error.message}\n`);
			return 2;
		}
	}

	let boot;
	try {
		boot = bootEngine();
	} catch (error) {
		stderr(`Engine failed to boot: ${error.message}\n`);
		return 2;
	}

	if (boot.failures.length) {
		const details = boot.failures
			.map((failure) => `  ${failure.file}: ${failure.message}`)
			.join('\n');
		stderr(`Engine registration failed:\n${details}\n`);
		return 2;
	}

	if (args.command === 'assemble') {
		return runAssemble(boot.engine, input, args.flags, {
			stdout,
			writeFile,
		});
	}
	if (args.command === 'fixture-cases') {
		return runFixtureCases(boot.blocksApi, args.flags, {
			stdout,
			writeFile,
		});
	}
	return runValidate(boot.engine, args.file, input, args.flags, {
		stdout,
		writeFile,
	});
}

module.exports = { run };
