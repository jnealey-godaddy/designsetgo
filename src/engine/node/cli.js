/**
 * Node CLI for the agent block engine.
 *
 * `npm run engine -- assemble tree.json` turns a JSON block tree into
 * editor-valid markup using the plugin's real block registration, outside
 * Jest. `npm run engine -- validate a.html b.html` re-runs the same
 * validity check the editor uses when it decides "Attempt Recovery".
 *
 * CommonJS, and `require('./dom')` is the very first statement — webpack
 * hoists ES `import`s to the top of a module, which would let a later
 * `@wordpress/*` import run before the DOM exists. `require()` has no such
 * hoisting, so the DOM install in `./dom` (pulled in again, idempotently, by
 * `./boot`) always runs before `./boot` requires `@wordpress/blocks`.
 */
'use strict';

require('./dom');

const fs = require('fs');
const { bootEngine } = require('./boot');
const { parseArgs } = require('./args');

const COMMANDS = ['assemble', 'validate', 'lint'];

/**
 * Writes `message` to stderr and sets the process exit code. Never calls
 * `process.exit()` directly, so buffered stdout writes flush first.
 *
 * @param {string} message Stderr message (no trailing newline).
 * @param {number} code    Exit code.
 */
function fail(message, code) {
	process.stderr.write(`${message}\n`);
	process.exitCode = code;
}

/**
 * @param {string} file Path to read as JSON.
 * @return {*} Parsed JSON value.
 * @throws {Error} When the file can't be read or isn't valid JSON.
 */
function readJsonFile(file) {
	let raw;
	try {
		raw = fs.readFileSync(file, 'utf8');
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
 * @param {string} file Path to read as text.
 * @return {string} File contents.
 * @throws {Error} When the file can't be read.
 */
function readTextFile(file) {
	try {
		return fs.readFileSync(file, 'utf8');
	} catch (error) {
		throw new Error(`Cannot read file "${file}": ${error.message}`);
	}
}

/**
 * @param {string} content   Text to write to stdout.
 * @param {string} [outFile] Optional path to also write `content` to.
 */
function emit(content, outFile) {
	process.stdout.write(`${content}\n`);
	if (outFile) {
		fs.writeFileSync(outFile, content);
	}
}

/**
 * @param {Object} engine Bound engine from `bootEngine()`.
 * @param {*}      tree   Parsed candidate block tree.
 * @param {Object} flags  Parsed CLI flags.
 */
function runAssemble(engine, tree, flags) {
	const report = engine.assemble(tree);
	const output = flags.json ? JSON.stringify(report, null, 2) : report.markup;

	emit(output, flags.out);
	process.exitCode = report.status === 'valid' ? 0 : 1;
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
 * @param {Object}   engine Bound engine from `bootEngine()`.
 * @param {string[]} files  File paths to validate.
 * @param {string[]} markup Each file's already-read contents, same order.
 * @param {Object}   flags  Parsed CLI flags.
 */
function runValidate(engine, files, markup, flags) {
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

	emit(output, flags.out);
	process.exitCode = anyInvalid ? 1 : 0;
}

function main() {
	const args = parseArgs(process.argv.slice(2));

	if (!COMMANDS.includes(args.command)) {
		fail(
			`Unknown command "${args.command || ''}". Usage: engine <assemble|validate|lint> <file> [options]`,
			2
		);
		return;
	}

	if (args.command === 'lint') {
		fail('lint is not available yet', 2);
		return;
	}

	if (!args.file.length) {
		const usage =
			args.command === 'validate'
				? 'engine validate <file...> [--json] [--out <file>]'
				: 'engine assemble <tree.json> [--json] [--out <file>]';
		fail(`Missing file argument. Usage: ${usage}`, 2);
		return;
	}

	let input;
	try {
		input =
			args.command === 'assemble'
				? readJsonFile(args.file[0])
				: args.file.map(readTextFile);
	} catch (error) {
		fail(error.message, 2);
		return;
	}

	let boot;
	try {
		boot = bootEngine();
	} catch (error) {
		fail(`Engine failed to boot: ${error.message}`, 2);
		return;
	}

	if (boot.failures.length) {
		const details = boot.failures
			.map((failure) => `  ${failure.file}: ${failure.message}`)
			.join('\n');
		fail(`Engine registration failed:\n${details}`, 2);
		return;
	}

	if (args.command === 'assemble') {
		runAssemble(boot.engine, input, args.flags);
	} else {
		runValidate(boot.engine, args.file, input, args.flags);
	}
}

main();
