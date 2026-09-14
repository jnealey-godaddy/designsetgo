/**
 * `lint` command logic for the Node engine CLI, split out of `run.js` purely
 * to keep every file in `src/engine/node/` under the project's 300-line cap
 * — no behavior changed in this split, see `run.js`'s header comment for why
 * this whole layer takes its dependencies injected rather than reading
 * `process.*`/`fs` directly.
 */
'use strict';

const { checkTreeShape } = require('../tree');
const { toInvalidEntry } = require('../assemble');
const { formatValidateFile, emit } = require('./io');

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

module.exports = {
	formatFindingsText,
	findingsExitCode,
	lintForAssemble,
	runLintShapeInvalid,
	runLint,
};
