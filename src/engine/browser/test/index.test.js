/**
 * `src/engine/browser/index.js` exposes the agent block engine as
 * `window.designsetgoEngine` for an in-editor assistant to call. It is
 * built lazily — `createEngine(window.wp.blocks)` runs on first use, not
 * at import time — so blocks registered after this script loads are still
 * visible to it.
 */
// eslint-disable-next-line import/no-unresolved
import * as blocksApi from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { registerForJest } from '../../registry/sources-fs';
import { TREE_VERSION } from '../../tree';

// Registration is idempotent and must happen before any test builds a tree
// against real block types. Muted internally via `withQuietConsole`.
registerForJest();

describe('window.designsetgoEngine', () => {
	beforeEach(() => {
		jest.resetModules();
		delete window.designsetgoEngine;
		window.wp = { blocks: blocksApi };
	});

	afterEach(() => {
		delete window.wp;
		delete window.designsetgoEngine;
	});

	test('installs version 1 and the three engine methods', () => {
		require('../index');

		expect(window.designsetgoEngine.version).toBe(1);
		expect(typeof window.designsetgoEngine.assemble).toBe('function');
		expect(typeof window.designsetgoEngine.validate).toBe('function');
		expect(typeof window.designsetgoEngine.lint).toBe('function');
	});

	test('assemble() builds real markup against window.wp.blocks', () => {
		require('../index');
		const tree = {
			version: TREE_VERSION,
			blocks: [{ name: 'core/paragraph', attributes: { content: 'Hi' } }],
		};

		const result = window.designsetgoEngine.assemble(tree);

		expect(result.status).toBe('valid');
		expect(result.markup).toContain('wp:paragraph');
	});

	test('validate() checks already-serialized markup', () => {
		require('../index');
		const markup = blocksApi.serialize(
			blocksApi.createBlock('core/paragraph', { content: 'Hi' })
		);

		expect(window.designsetgoEngine.validate(markup)).toEqual({
			status: 'valid',
			invalid: [],
		});
	});

	test('lint() runs the real rule set, defaulting the design context', () => {
		require('../index');
		const tree = {
			version: TREE_VERSION,
			blocks: [
				{ name: 'core/html', attributes: { content: '<p>hi</p>' } },
			],
		};

		const findings = window.designsetgoEngine.lint(tree);

		expect(findings).toEqual([
			expect.objectContaining({
				rule: 'no-custom-html',
				severity: 'error',
			}),
		]);
	});

	test('never overwrites an existing window.designsetgoEngine', () => {
		const sentinel = { version: 999 };
		window.designsetgoEngine = sentinel;

		require('../index');

		expect(window.designsetgoEngine).toBe(sentinel);
	});

	test('does not read window.wp at import time, only on first call', () => {
		window.wp = undefined;

		expect(() => require('../index')).not.toThrow();

		window.wp = { blocks: blocksApi };
		const tree = {
			version: TREE_VERSION,
			blocks: [{ name: 'core/paragraph', attributes: { content: 'Hi' } }],
		};

		expect(window.designsetgoEngine.assemble(tree).status).toBe('valid');
	});
});
