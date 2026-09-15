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
 *
 * `lint`-specific command logic lives in `./lint-command` and shared file
 * I/O / report-formatting helpers in `./io` — both split out purely to keep
 * every file in this directory under the project's 300-line cap.
 */
'use strict';

const { parseArgs } = require('./args');
const { buildFixtureCases } = require('./fixture-cases');
const {
	readJsonFile,
	readTextFile,
	formatValidateFile,
	emit,
} = require('./io');
const {
	formatFindingsText,
	findingsExitCode,
	lintForAssemble,
	runLint,
} = require('./lint-command');

const COMMANDS = ['assemble', 'validate', 'lint', 'fixture-cases'];

/** Matches a non-negative integer `--max-warnings` value. */
const MAX_WARNINGS_RE = /^\d+$/;

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
		// Markup alone says nothing about why it's empty; list the reasons.
		report.invalid.forEach((entry) => {
			stderr(`invalid ${entry.path} ${entry.block}: ${entry.reason}\n`);
		});
		if (flags.lint && findings.length) {
			stderr(`${formatFindingsText(findings)}\n`);
		}
	}

	const lintFailed =
		flags.lint && findingsExitCode(findings, maxWarnings) === 1;
	return report.status === 'valid' && !lintFailed ? 0 : 1;
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
				'engine assemble <tree.json> [--json] [--lint] [--context <file>] [--max-warnings <n>] [--out <file>]',
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
