/**
 * Exercises the built Node engine CLI end-to-end, spawning the real
 * `build/engine/node.cjs` bundle rather than importing any engine module
 * directly — this is the only place that would catch a `require()`-ordering
 * regression (a `@wordpress/*` module evaluating before `./dom` installs
 * jsdom globals) or a webpack externals/stub misconfiguration, neither of
 * which unit tests against engine source could ever see.
 *
 * `npm run test:engine` runs `npm run build:engine` first, so the bundle
 * this file spawns is always fresh.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import fs from 'node:fs';
import os from 'node:os';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = path.resolve(HERE, '../..');
const CLI = path.join(REPO_ROOT, 'build/engine/node.cjs');
const FIXTURES = path.join(HERE, 'fixtures');

const VALID_TREE = path.join(FIXTURES, 'valid-tree.json');
const UNKNOWN_BLOCK = path.join(FIXTURES, 'unknown-block.json');
const VALID_MARKUP = path.join(FIXTURES, 'valid-markup.html');
const INVALID_MARKUP = path.join(FIXTURES, 'invalid-markup.html');
const LINT_ERROR_TREE = path.join(FIXTURES, 'lint-error-tree.json');

/**
 * @param {string[]} args CLI arguments (command + file + flags).
 * @return {{ status: number, stdout: string, stderr: string }} Spawn result.
 */
function runCli(args) {
	const result = spawnSync(process.execPath, [CLI, ...args], {
		cwd: REPO_ROOT,
		encoding: 'utf8',
	});
	return {
		status: result.status,
		stdout: result.stdout,
		stderr: result.stderr,
	};
}

test('assemble: valid tree exits 0 and prints markup only, no stderr noise', () => {
	const { status, stdout, stderr } = runCli(['assemble', VALID_TREE]);

	assert.equal(status, 0);
	assert.equal(stderr, '');
	assert.match(stdout, /^<!-- wp:designsetgo\/section -->/);
	assert.match(stdout, /<!-- wp:designsetgo\/row -->/);
});

test('assemble --json: valid tree reports status valid with a 64-char treeHash', () => {
	const { status, stdout, stderr } = runCli([
		'assemble',
		VALID_TREE,
		'--json',
	]);

	assert.equal(status, 0);
	assert.equal(stderr, '');
	const report = JSON.parse(stdout);
	assert.equal(report.status, 'valid');
	assert.equal(report.invalid.length, 0);
	assert.match(report.markup, /wp:designsetgo\/section/);
	assert.match(report.treeHash, /^[0-9a-f]{64}$/);
});

test('assemble --json: unknown block exits 1 and reports the problem', () => {
	const { status, stdout } = runCli(['assemble', UNKNOWN_BLOCK, '--json']);

	assert.equal(status, 1);
	const report = JSON.parse(stdout);
	assert.equal(report.status, 'invalid');
	assert.equal(report.markup, '');
	assert.equal(report.invalid.length, 1);
	assert.equal(report.invalid[0].code, 'designsetgo_unknown_block');
	assert.equal(report.invalid[0].block, 'designsetgo/does-not-exist');
});

test('assemble: missing file argument exits 2 with a stderr message', () => {
	const { status, stdout, stderr } = runCli(['assemble']);

	assert.equal(status, 2);
	assert.equal(stdout, '');
	assert.match(stderr, /Missing file argument/);
});

test('assemble: nonexistent file exits 2 with a stderr message', () => {
	const { status, stdout, stderr } = runCli([
		'assemble',
		path.join(FIXTURES, 'does-not-exist.json'),
	]);

	assert.equal(status, 2);
	assert.equal(stdout, '');
	assert.match(stderr, /Cannot read file/);
});

test('assemble: unreadable/malformed JSON exits 2 with a stderr message', () => {
	const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'dsgo-engine-'));
	const badFile = path.join(dir, 'bad.json');
	fs.writeFileSync(badFile, '{not valid json');

	const { status, stdout, stderr } = runCli(['assemble', badFile]);

	assert.equal(status, 2);
	assert.equal(stdout, '');
	assert.match(stderr, /Cannot parse JSON/);
});

test('assemble --out: writes the same content that went to stdout', () => {
	const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'dsgo-engine-'));
	const outFile = path.join(dir, 'out.html');

	const { status, stdout } = runCli([
		'assemble',
		VALID_TREE,
		'--out',
		outFile,
	]);

	assert.equal(status, 0);
	const written = fs.readFileSync(outFile, 'utf8');
	assert.equal(stdout, `${written}\n`);
});

test('validate: valid markup exits 0 with a text report', () => {
	const { status, stdout, stderr } = runCli(['validate', VALID_MARKUP]);

	assert.equal(status, 0);
	assert.equal(stderr, '');
	assert.equal(stdout.trim(), `${VALID_MARKUP}: valid`);
});

test('validate: invalid markup exits 1 and lists the offending block', () => {
	const { status, stdout } = runCli(['validate', INVALID_MARKUP]);

	assert.equal(status, 1);
	const lines = stdout.trim().split('\n');
	assert.equal(lines[0], `${INVALID_MARKUP}: invalid`);
	assert.match(
		lines[1],
		/^\s+blocks\[1]\.innerBlocks\[0] designsetgo\/icon-button:/
	);
});

test('validate: mixed valid + invalid files exits 1 and reports both', () => {
	const { status, stdout } = runCli([
		'validate',
		VALID_MARKUP,
		INVALID_MARKUP,
	]);

	assert.equal(status, 1);
	assert.match(stdout, new RegExp(`${escapeRegExp(VALID_MARKUP)}: valid`));
	assert.match(
		stdout,
		new RegExp(`${escapeRegExp(INVALID_MARKUP)}: invalid`)
	);
});

test('validate --json: reports one entry per file with status and invalid', () => {
	const { status, stdout } = runCli([
		'validate',
		VALID_MARKUP,
		INVALID_MARKUP,
		'--json',
	]);

	assert.equal(status, 1);
	const report = JSON.parse(stdout);
	assert.equal(report.files.length, 2);
	assert.equal(report.files[0].file, VALID_MARKUP);
	assert.equal(report.files[0].status, 'valid');
	assert.equal(report.files[1].status, 'invalid');
	assert.equal(report.files[1].invalid.length, 1);
});

test('validate: missing file arguments exits 2', () => {
	const { status, stdout, stderr } = runCli(['validate']);

	assert.equal(status, 2);
	assert.equal(stdout, '');
	assert.match(stderr, /Missing file argument/);
});

test('validate: a nonexistent file exits 2 with a stderr message', () => {
	const { status, stdout, stderr } = runCli([
		'validate',
		path.join(FIXTURES, 'does-not-exist.html'),
	]);

	assert.equal(status, 2);
	assert.equal(stdout, '');
	assert.match(stderr, /Cannot read file/);
});

test('validate --out: writes the same content that went to stdout', () => {
	const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'dsgo-engine-'));
	const outFile = path.join(dir, 'out.txt');

	const { status, stdout } = runCli([
		'validate',
		VALID_MARKUP,
		'--out',
		outFile,
	]);

	assert.equal(status, 0);
	const written = fs.readFileSync(outFile, 'utf8');
	assert.equal(stdout, `${written}\n`);
});

test('fixture-cases: exits 0, needs no file argument, and prints designsetgo/* cases nested by block then attribute', () => {
	const { status, stdout, stderr } = runCli(['fixture-cases']);

	assert.equal(status, 0);
	assert.equal(stderr, '');
	const cases = JSON.parse(stdout);
	const blockNames = Object.keys(cases);
	assert.ok(blockNames.length > 20);
	blockNames.forEach((name) => assert.match(name, /^designsetgo\//));
	// parent-restricted children never get a bare top-level case.
	assert.equal(cases['designsetgo/tab'], undefined);
});

test('fixture-cases --out: writes the same content that went to stdout', () => {
	const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'dsgo-engine-'));
	const outFile = path.join(dir, 'cases.json');

	const { status, stdout } = runCli(['fixture-cases', '--out', outFile]);

	assert.equal(status, 0);
	const written = fs.readFileSync(outFile, 'utf8');
	assert.equal(stdout, `${written}\n`);
});

test('lint: a tree tripping a real rule (core/html) exits 1 and reports the finding', () => {
	const { status, stdout, stderr } = runCli(['lint', LINT_ERROR_TREE]);

	assert.equal(status, 1);
	assert.equal(stderr, '');
	assert.match(
		stdout,
		/^error blocks\[0\]\.innerBlocks\[0\] no-custom-html: core\/html/
	);
	assert.match(stdout, /\n {2}suggestion: /);
});

test('lint --json: a tree tripping a real rule reports one error finding', () => {
	const { status, stdout } = runCli(['lint', LINT_ERROR_TREE, '--json']);

	assert.equal(status, 1);
	const report = JSON.parse(stdout);
	assert.equal(report.findings.length, 1);
	assert.equal(report.findings[0].rule, 'no-custom-html');
	assert.equal(report.findings[0].severity, 'error');
	assert.equal(report.findings[0].path, 'blocks[0].innerBlocks[0]');
});

test('lint --json: a clean tree exits 0 with no error-severity findings', () => {
	const { status, stdout, stderr } = runCli(['lint', VALID_TREE, '--json']);

	assert.equal(status, 0);
	assert.equal(stderr, '');
	const report = JSON.parse(stdout);
	assert.ok(Array.isArray(report.findings));
	assert.ok(!report.findings.some((finding) => finding.severity === 'error'));
});

test('lint: a shape-invalid tree exits 1 and never runs rules', () => {
	const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'dsgo-engine-'));
	const badTree = path.join(dir, 'bad-tree.json');
	fs.writeFileSync(
		badTree,
		JSON.stringify({ version: 1, blocks: [{ name: 'not-a-valid-name' }] })
	);

	const { status, stdout } = runCli(['lint', badTree, '--json']);

	assert.equal(status, 1);
	const report = JSON.parse(stdout);
	assert.equal(report.status, 'invalid');
	assert.equal(report.invalid.length, 1);
	assert.equal(
		report.invalid[0].code,
		'designsetgo_invalid_block_definition'
	);
});

test('lint: an unknown command-line flag for --max-warnings exits 2', () => {
	const { status, stdout, stderr } = runCli([
		'lint',
		VALID_TREE,
		'--max-warnings',
		'abc',
	]);

	assert.equal(status, 2);
	assert.equal(stdout, '');
	assert.match(stderr, /--max-warnings/);
});

test('lint: an unreadable --context file exits 2', () => {
	const { status, stdout, stderr } = runCli([
		'lint',
		VALID_TREE,
		'--context',
		path.join(FIXTURES, 'does-not-exist.json'),
	]);

	assert.equal(status, 2);
	assert.equal(stdout, '');
	assert.match(stderr, /Cannot read file/);
});

test('assemble --lint --json: report gains a findings array alongside status/markup', () => {
	const { status, stdout, stderr } = runCli([
		'assemble',
		VALID_TREE,
		'--lint',
		'--json',
	]);

	assert.equal(stderr, '');
	const report = JSON.parse(stdout);
	assert.equal(report.status, 'valid');
	assert.ok(Array.isArray(report.findings));
	// Nothing in valid-tree.json should trip an error-severity rule.
	assert.ok(!report.findings.some((finding) => finding.severity === 'error'));
	assert.equal(status, 0);
});

test('assemble --lint: a tree with a real lint error exits 1 even though markup is valid, findings on stderr', () => {
	const { status, stdout, stderr } = runCli([
		'assemble',
		LINT_ERROR_TREE,
		'--lint',
	]);

	assert.equal(status, 1);
	assert.match(stdout, /^<!-- wp:designsetgo\/section -->/);
	assert.match(stderr, /error blocks\[0\]\.innerBlocks\[0\] no-custom-html:/);
});

test('assemble (no --lint): report has no findings key', () => {
	const { status, stdout } = runCli(['assemble', VALID_TREE, '--json']);

	assert.equal(status, 0);
	const report = JSON.parse(stdout);
	assert.equal(report.findings, undefined);
});

test('unknown command: exits 2 with a stderr message', () => {
	const { status, stdout, stderr } = runCli(['frobnicate', VALID_TREE]);

	assert.equal(status, 2);
	assert.equal(stdout, '');
	assert.match(stderr, /Unknown command/);
});

/**
 * @param {string} value String to escape for use inside a `RegExp`.
 * @return {string} Escaped string.
 */
function escapeRegExp(value) {
	return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
