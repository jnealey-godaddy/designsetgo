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
const { checkTreeShape } = require('../tree');
const { toInvalidEntry } = require('../assemble');

const COMMANDS = ['assemble', 'validate', 'lint', 'fixture-cases'];

/** Matches a non-negative integer `--max-warnings` value. */
const MAX_WARNINGS_RE = /^\d+$/;

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
 * @param {object[]} findings Lint findings, document order.
 * @return {string} One `severity path rule: message` line per finding,
 *   followed by an indented `  suggestion: ...` line for findings that have
 *   one.
 */
function formatFindingsText(findings) {
	return findings
		.map((finding) => {
			const line = `${finding.severity} ${finding.path} ${finding.rule}: ${finding.message}`;
			return finding.suggestion === undefined
				? line
				: `${line}\n  suggestion: ${finding.suggestion}`;
		})
		.join('\n');
}

/**
 * @param {object[]}         findings    Lint findings.
 * @param {number|undefined} maxWarnings Parsed `--max-warnings` value, or
 *                                       `undefined` when the flag wasn't given.
 * @return {number} 1 when any finding is `error`-severity, or the warning
 *   count exceeds `maxWarnings`; 0 otherwise.
 */
function findingsExitCode(findings, maxWarnings) {
	const hasError = findings.some((finding) => finding.severity === 'error');
	const warningCount = findings.filter(
		(finding) => finding.severity === 'warning'
	).length;
	const overMax = maxWarnings !== undefined && warningCount > maxWarnings;

	return hasError || overMax ? 1 : 0;
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
 * Runs `engine.lint()` on `tree` only when `--lint` was requested and the
 * tree passes `checkTreeShape()` — an `assemble()` report on a shape-invalid
 * tree already carries the shape problems in `invalid`, and lint rules
 * assume a shape-valid tree (see `src/engine/lint/index.js`).
 *
 * @param {Object} engine Bound engine from `bootEngine()`.
 * @param {*}      tree   Parsed candidate block tree.
 * @param {Object} design Parsed `--context` payload (`{}` when unset).
 * @param {Object} flags  Parsed CLI flags.
 * @return {object[]} Lint findings, or `[]` when `--lint` wasn't given or
 *   the tree is shape-invalid.
 */
function lintForAssemble(engine, tree, design, flags) {
	if (!flags.lint || checkTreeShape(tree).length) {
		return [];
	}
	return engine.lint(tree, design);
}

/**
 * @param {Object}                                                      engine      Bound engine from `bootEngine()`.
 * @param {*}                                                           tree        Parsed candidate block tree.
 * @param {Object}                                                      design      Parsed `--context` payload (`{}` when unset).
 * @param {Object}                                                      flags       Parsed CLI flags.
 * @param {number|undefined}                                            maxWarnings Parsed `--max-warnings` value.
 * @param {{ stdout: Function, writeFile: Function, stderr: Function }} io          Injected sinks.
 * @return {number} Exit code: 1 when the markup is invalid, `--lint` found
 *   an error, or `--lint` warnings exceed `maxWarnings`; 0 otherwise.
 */
function runAssemble(
	engine,
	tree,
	design,
	flags,
	maxWarnings,
	{ stdout, writeFile, stderr }
) {
	const report = engine.assemble(tree);
	const findings = lintForAssemble(engine, tree, design, flags);

	if (flags.json) {
		const jsonReport = flags.lint ? { ...report, findings } : report;
		emit(stdout, writeFile, JSON.stringify(jsonReport, null, 2), flags.out);
	} else {
		emit(stdout, writeFile, report.markup, flags.out);
		if (flags.lint && findings.length) {
			stderr(`${formatFindingsText(findings)}\n`);
		}
	}

	const lintFailed =
		flags.lint && findingsExitCode(findings, maxWarnings) === 1;
	return report.status === 'valid' && !lintFailed ? 0 : 1;
}

/**
 * Runs `lint` against a tree that has already failed `checkTreeShape()` —
 * reports the shape problems in the same `{ path, block, reason, code }`
 * entry shape (via `toInvalidEntry()`) and `{ status, invalid }` JSON/text
 * conventions `assemble()`/`validate()` use for invalid input, and never
 * runs lint rules on malformed input.
 *
 * @param {string}                                    file          Tree file path (text mode's header line).
 * @param {Object}                                    tree          The malformed tree.
 * @param {object[]}                                  shapeProblems Problems from `checkTreeShape(tree)`.
 * @param {Object}                                    flags         Parsed CLI flags.
 * @param {{ stdout: Function, writeFile: Function }} io            Injected sinks.
 * @return {number} Always 1.
 */
function runLintShapeInvalid(
	file,
	tree,
	shapeProblems,
	flags,
	{ stdout, writeFile }
) {
	const invalid = shapeProblems.map((problem) =>
		toInvalidEntry(problem, tree)
	);
	const result = { status: 'invalid', invalid };
	const output = flags.json
		? JSON.stringify(result, null, 2)
		: formatValidateFile(file, result);

	emit(stdout, writeFile, output, flags.out);
	return 1;
}

/**
 * @param {Object}                                    engine      Bound engine from `bootEngine()`.
 * @param {string}                                    file        Tree file path.
 * @param {*}                                         tree        Parsed candidate block tree.
 * @param {Object}                                    design      Parsed `--context` payload (`{}` when unset).
 * @param {Object}                                    flags       Parsed CLI flags.
 * @param {number|undefined}                          maxWarnings Parsed `--max-warnings` value.
 * @param {{ stdout: Function, writeFile: Function }} io          Injected sinks.
 * @return {number} Exit code: 1 when the tree is shape-invalid, has an
 *   error-severity finding, or exceeds `maxWarnings`; 0 otherwise.
 */
function runLint(
	engine,
	file,
	tree,
	design,
	flags,
	maxWarnings,
	{ stdout, writeFile }
) {
	const shapeProblems = checkTreeShape(tree);
	if (shapeProblems.length) {
		return runLintShapeInvalid(file, tree, shapeProblems, flags, {
			stdout,
			writeFile,
		});
	}

	const findings = engine.lint(tree, design);
	const output = flags.json
		? JSON.stringify({ findings }, null, 2)
		: formatFindingsText(findings);

	emit(stdout, writeFile, output, flags.out);
	return findingsExitCode(findings, maxWarnings);
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
			`Unknown command "${args.command || ''}". Usage: engine <assemble|validate|lint|fixture-cases> <file> [options]\n`
		);
		return 2;
	}

	let maxWarnings;
	if (args.flags.maxWarnings !== undefined) {
		if (!MAX_WARNINGS_RE.test(args.flags.maxWarnings)) {
			stderr(
				`Invalid --max-warnings "${args.flags.maxWarnings}": must be a non-negative integer.\n`
			);
			return 2;
		}
		maxWarnings = Number(args.flags.maxWarnings);
	}

	// fixture-cases takes no file argument — it generates from the registry,
	// not from anything on disk — so only assemble/validate/lint require one.
	if (args.command !== 'fixture-cases' && !args.file.length) {
		const usages = {
			validate: 'engine validate <file...> [--json] [--out <file>]',
			lint: 'engine lint <tree.json> [--context <file>] [--json] [--max-warnings <n>] [--out <file>]',
			assemble:
				'engine assemble <tree.json> [--json] [--lint] [--context <file>] [--out <file>]',
		};
		stderr(`Missing file argument. Usage: ${usages[args.command]}\n`);
		return 2;
	}

	let input;
	if (args.command !== 'fixture-cases') {
		try {
			input =
				args.command === 'assemble' || args.command === 'lint'
					? readJsonFile(readFile, args.file[0])
					: args.file.map((file) => readTextFile(readFile, file));
		} catch (error) {
			stderr(`${error.message}\n`);
			return 2;
		}
	}

	// Design context only matters to `lint` rules — read it for `lint`, and
	// for `assemble` when `--lint` will run them too.
	let design = {};
	const needsDesignContext =
		args.command === 'lint' ||
		(args.command === 'assemble' && args.flags.lint);
	if (needsDesignContext && args.flags.context) {
		try {
			design = readJsonFile(readFile, args.flags.context);
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
		return runAssemble(
			boot.engine,
			input,
			design,
			args.flags,
			maxWarnings,
			{
				stdout,
				writeFile,
				stderr,
			}
		);
	}
	if (args.command === 'lint') {
		return runLint(
			boot.engine,
			args.file[0],
			input,
			design,
			args.flags,
			maxWarnings,
			{ stdout, writeFile }
		);
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
