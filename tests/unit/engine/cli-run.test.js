/**
 * Unit tests for `src/engine/node/run.js` against fake dependencies.
 *
 * `tests/engine/cli.test.mjs` spawns the real built bundle and proves the
 * real wiring (dom.js → boot.js → the real block registry), but it can't
 * cheaply force `bootEngine()` to throw or return registration failures
 * without corrupting real plugin source. `run()` takes every
 * side-effecting dependency as an argument specifically so those branches
 * — and the "a partially registered engine must not report valid"
 * invariant — are reachable here with plain fakes.
 */
import { run } from '../../../src/engine/node/run';

/**
 * @return {{ stdout: Function, stderr: Function, stdoutText: () => string, stderrText: () => string }}
 *   A pair of sink functions plus accessors for everything they collected.
 */
function makeSinks() {
	const stdoutChunks = [];
	const stderrChunks = [];
	return {
		stdout: (content) => stdoutChunks.push(content),
		stderr: (content) => stderrChunks.push(content),
		stdoutText: () => stdoutChunks.join(''),
		stderrText: () => stderrChunks.join(''),
	};
}

const VALID_TREE_JSON = JSON.stringify({ version: 1, blocks: [] });

describe('run() boot failures', () => {
	it('exits 2 and reports the message when bootEngine() throws', () => {
		const sinks = makeSinks();
		const bootEngine = jest.fn(() => {
			throw new Error('registerCoreBlocks exploded');
		});
		const readFile = jest.fn(() => VALID_TREE_JSON);
		const writeFile = jest.fn();

		const code = run(['assemble', 'tree.json'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(2);
		expect(sinks.stderrText()).toMatch(
			/Engine failed to boot: registerCoreBlocks exploded/
		);
		expect(sinks.stdoutText()).toBe('');
	});

	it('exits 2, lists failing files, and never calls assemble() when failures is non-empty', () => {
		const sinks = makeSinks();
		const assemble = jest.fn(() => ({
			status: 'valid',
			markup: '<!-- wp:should-not-run -->',
			invalid: [],
			treeHash: 'unused',
		}));
		const bootEngine = jest.fn(() => ({
			engine: { assemble, validate: jest.fn() },
			failures: [
				{ file: 'broken-block/index.js', message: 'Unexpected token' },
			],
		}));
		const readFile = jest.fn(() => VALID_TREE_JSON);
		const writeFile = jest.fn();

		const code = run(['assemble', 'tree.json'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(2);
		expect(sinks.stderrText()).toMatch(/Engine registration failed:/);
		expect(sinks.stderrText()).toMatch(
			/broken-block\/index\.js: Unexpected token/
		);
		expect(assemble).not.toHaveBeenCalled();
		expect(sinks.stdoutText()).toBe('');
	});
});

describe('run() happy path', () => {
	it('assemble: exits 0 and prints the markup a fake engine returns', () => {
		const sinks = makeSinks();
		const assemble = jest.fn(() => ({
			status: 'valid',
			markup: '<!-- wp:designsetgo/section --><!-- /wp:designsetgo/section -->',
			invalid: [],
			treeHash: 'deadbeef',
		}));
		const bootEngine = jest.fn(() => ({
			engine: { assemble, validate: jest.fn() },
			failures: [],
		}));
		const readFile = jest.fn(() => VALID_TREE_JSON);
		const writeFile = jest.fn();

		const code = run(['assemble', 'tree.json'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(0);
		expect(sinks.stdoutText()).toBe(
			'<!-- wp:designsetgo/section --><!-- /wp:designsetgo/section -->\n'
		);
		expect(sinks.stderrText()).toBe('');
		expect(assemble).toHaveBeenCalledWith({ version: 1, blocks: [] });
	});

	it('fixture-cases: exits 0 and prints buildFixtureCases() output, without a file argument', () => {
		const sinks = makeSinks();
		const blocksApi = { getBlockTypes: jest.fn(() => []) };
		const bootEngine = jest.fn(() => ({
			engine: { assemble: jest.fn(), validate: jest.fn() },
			failures: [],
			blocksApi,
		}));
		const readFile = jest.fn();
		const writeFile = jest.fn();

		const code = run(['fixture-cases'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(0);
		expect(readFile).not.toHaveBeenCalled();
		expect(sinks.stderrText()).toBe('');
		expect(sinks.stdoutText()).toBe('{}\n');
	});

	it('fixture-cases --out: writes the same content that went to stdout', () => {
		const sinks = makeSinks();
		const blocksApi = { getBlockTypes: jest.fn(() => []) };
		const bootEngine = jest.fn(() => ({
			engine: { assemble: jest.fn(), validate: jest.fn() },
			failures: [],
			blocksApi,
		}));
		const readFile = jest.fn();
		const writeFile = jest.fn();

		const code = run(['fixture-cases', '--out', 'cases.json'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(0);
		expect(writeFile).toHaveBeenCalledWith('cases.json', '{}');
	});
});

describe('run() lint', () => {
	/**
	 * @param {Function} lint Fake `engine.lint()`.
	 * @return {Function} A `bootEngine` fake wired to that `lint`.
	 */
	function bootEngineWithLint(lint) {
		return jest.fn(() => ({
			engine: { assemble: jest.fn(), validate: jest.fn(), lint },
			failures: [],
		}));
	}

	it('exits 1 and prints the finding when a rule reports an error', () => {
		const sinks = makeSinks();
		const lint = jest.fn(() => [
			{
				rule: 'no-custom-html',
				severity: 'error',
				path: 'blocks[0]',
				message: 'core/html is not allowed.',
				suggestion: 'Use a real block instead.',
			},
		]);
		const bootEngine = bootEngineWithLint(lint);
		const readFile = jest.fn(() => VALID_TREE_JSON);
		const writeFile = jest.fn();

		const code = run(['lint', 'tree.json'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(1);
		expect(sinks.stdoutText()).toBe(
			'error blocks[0] no-custom-html: core/html is not allowed.\n  suggestion: Use a real block instead.\n'
		);
		expect(sinks.stderrText()).toBe('');
	});

	it('exits 0 and prints "{ findings: [] }" when a rule reports nothing', () => {
		const sinks = makeSinks();
		const lint = jest.fn(() => []);
		const bootEngine = bootEngineWithLint(lint);
		const readFile = jest.fn(() => VALID_TREE_JSON);
		const writeFile = jest.fn();

		const code = run(['lint', 'tree.json', '--json'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(0);
		expect(JSON.parse(sinks.stdoutText())).toEqual({ findings: [] });
	});

	it('warnings exceeding --max-warnings exit 1', () => {
		const sinks = makeSinks();
		const lint = jest.fn(() => [
			{
				rule: 'a',
				severity: 'warning',
				path: 'blocks[0]',
				message: 'm1',
			},
			{
				rule: 'b',
				severity: 'warning',
				path: 'blocks[1]',
				message: 'm2',
			},
		]);
		const bootEngine = bootEngineWithLint(lint);
		const readFile = jest.fn(() => VALID_TREE_JSON);
		const writeFile = jest.fn();

		const code = run(['lint', 'tree.json', '--max-warnings', '1'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(1);
	});

	it('warnings at or under --max-warnings exit 0', () => {
		const sinks = makeSinks();
		const lint = jest.fn(() => [
			{
				rule: 'a',
				severity: 'warning',
				path: 'blocks[0]',
				message: 'm1',
			},
			{
				rule: 'b',
				severity: 'warning',
				path: 'blocks[1]',
				message: 'm2',
			},
		]);
		const bootEngine = bootEngineWithLint(lint);
		const readFile = jest.fn(() => VALID_TREE_JSON);
		const writeFile = jest.fn();

		const code = run(['lint', 'tree.json', '--max-warnings', '2'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(0);
	});

	it('warnings alone exit 0 when --max-warnings is not given', () => {
		const sinks = makeSinks();
		const lint = jest.fn(() => [
			{
				rule: 'a',
				severity: 'warning',
				path: 'blocks[0]',
				message: 'm1',
			},
		]);
		const bootEngine = bootEngineWithLint(lint);
		const readFile = jest.fn(() => VALID_TREE_JSON);
		const writeFile = jest.fn();

		const code = run(['lint', 'tree.json'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(0);
	});

	it('a non-integer --max-warnings exits 2 without booting or reading files', () => {
		const sinks = makeSinks();
		const bootEngine = jest.fn();
		const readFile = jest.fn();
		const writeFile = jest.fn();

		const code = run(['lint', 'tree.json', '--max-warnings', 'abc'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(2);
		expect(sinks.stderrText()).toMatch(/--max-warnings/);
		expect(bootEngine).not.toHaveBeenCalled();
		expect(readFile).not.toHaveBeenCalled();
	});

	it('a negative --max-warnings exits 2', () => {
		const sinks = makeSinks();
		const bootEngine = jest.fn();
		const readFile = jest.fn();
		const writeFile = jest.fn();

		const code = run(['lint', 'tree.json', '--max-warnings', '-1'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(2);
		expect(bootEngine).not.toHaveBeenCalled();
	});

	it('an unreadable --context file exits 2 without booting', () => {
		const sinks = makeSinks();
		const bootEngine = jest.fn();
		const readFile = jest.fn((file) => {
			if (file === 'tree.json') {
				return VALID_TREE_JSON;
			}
			throw new Error('ENOENT: no such file');
		});
		const writeFile = jest.fn();

		const code = run(['lint', 'tree.json', '--context', 'ctx.json'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(2);
		expect(sinks.stderrText()).toMatch(/Cannot read file "ctx\.json"/);
		expect(bootEngine).not.toHaveBeenCalled();
	});

	it('a malformed (non-JSON) --context file exits 2', () => {
		const sinks = makeSinks();
		const bootEngine = jest.fn();
		const readFile = jest.fn((file) =>
			file === 'tree.json' ? VALID_TREE_JSON : '{not valid json'
		);
		const writeFile = jest.fn();

		const code = run(['lint', 'tree.json', '--context', 'ctx.json'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(2);
		expect(sinks.stderrText()).toMatch(/Cannot parse JSON in "ctx\.json"/);
		expect(bootEngine).not.toHaveBeenCalled();
	});

	it('passes the parsed --context payload through to engine.lint()', () => {
		const sinks = makeSinks();
		const lint = jest.fn(() => []);
		const bootEngine = bootEngineWithLint(lint);
		const readFile = jest.fn((file) =>
			file === 'tree.json'
				? VALID_TREE_JSON
				: JSON.stringify({ palette: ['red'] })
		);
		const writeFile = jest.fn();

		run(['lint', 'tree.json', '--context', 'ctx.json'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(lint).toHaveBeenCalledWith(
			{ version: 1, blocks: [] },
			{ palette: ['red'] }
		);
	});

	it('a shape-invalid tree exits 1, reports the shape problem, and never calls engine.lint()', () => {
		const sinks = makeSinks();
		const lint = jest.fn();
		const bootEngine = bootEngineWithLint(lint);
		const readFile = jest.fn(() =>
			JSON.stringify({ version: 1, blocks: [{ name: 123 }] })
		);
		const writeFile = jest.fn();

		const code = run(['lint', 'tree.json', '--json'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(1);
		expect(lint).not.toHaveBeenCalled();
		const report = JSON.parse(sinks.stdoutText());
		expect(report.status).toBe('invalid');
		expect(report.invalid).toHaveLength(1);
		expect(report.invalid[0].path).toBe('blocks[0]');
		expect(report.invalid[0].code).toBe(
			'designsetgo_invalid_block_definition'
		);
	});
});

describe('run() assemble --lint', () => {
	it('exits 1 when lint reports an error even though the markup is valid', () => {
		const sinks = makeSinks();
		const assemble = jest.fn(() => ({
			status: 'valid',
			markup: '<!-- wp:designsetgo/section --><!-- /wp:designsetgo/section -->',
			invalid: [],
			treeHash: 'deadbeef',
		}));
		const lint = jest.fn(() => [
			{
				rule: 'no-custom-html',
				severity: 'error',
				path: 'blocks[0]',
				message: 'bad',
			},
		]);
		const bootEngine = jest.fn(() => ({
			engine: { assemble, validate: jest.fn(), lint },
			failures: [],
		}));
		const readFile = jest.fn(() => VALID_TREE_JSON);
		const writeFile = jest.fn();

		const code = run(['assemble', 'tree.json', '--lint', '--json'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(1);
		const report = JSON.parse(sinks.stdoutText());
		expect(report.status).toBe('valid');
		expect(report.findings).toHaveLength(1);
	});

	it('text mode keeps stdout markup-only and writes findings to stderr', () => {
		const sinks = makeSinks();
		const markup =
			'<!-- wp:designsetgo/section --><!-- /wp:designsetgo/section -->';
		const assemble = jest.fn(() => ({
			status: 'valid',
			markup,
			invalid: [],
			treeHash: 'deadbeef',
		}));
		const lint = jest.fn(() => [
			{
				rule: 'no-custom-html',
				severity: 'error',
				path: 'blocks[0]',
				message: 'bad',
			},
		]);
		const bootEngine = jest.fn(() => ({
			engine: { assemble, validate: jest.fn(), lint },
			failures: [],
		}));
		const readFile = jest.fn(() => VALID_TREE_JSON);
		const writeFile = jest.fn();

		const code = run(['assemble', 'tree.json', '--lint'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(1);
		expect(sinks.stdoutText()).toBe(`${markup}\n`);
		expect(sinks.stderrText()).toBe(
			'error blocks[0] no-custom-html: bad\n'
		);
	});

	it('without --lint, assemble never calls engine.lint()', () => {
		const sinks = makeSinks();
		const assemble = jest.fn(() => ({
			status: 'valid',
			markup: '<!-- wp:designsetgo/section --><!-- /wp:designsetgo/section -->',
			invalid: [],
			treeHash: 'deadbeef',
		}));
		const lint = jest.fn();
		const bootEngine = jest.fn(() => ({
			engine: { assemble, validate: jest.fn(), lint },
			failures: [],
		}));
		const readFile = jest.fn(() => VALID_TREE_JSON);
		const writeFile = jest.fn();

		const code = run(['assemble', 'tree.json'], {
			bootEngine,
			readFile,
			writeFile,
			stdout: sinks.stdout,
			stderr: sinks.stderr,
		});

		expect(code).toBe(0);
		expect(lint).not.toHaveBeenCalled();
	});
});
