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
