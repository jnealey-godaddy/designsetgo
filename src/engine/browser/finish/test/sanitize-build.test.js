/**
 * Markup sanitization for builds whose submitter lacked `unfiltered_html`:
 * the assembled markup goes through the server's KSES route before
 * anything is applied, and it is applied only when the filter changed no
 * block's structure, attributes, or validity against its current `save()`.
 */
import { finishBuild, FINISH_NOTICE_ID } from '../finish-build';
import { findSanitizedChanges } from '../sanitize';

const TREE = { version: 1, blocks: [{ name: 'core/paragraph' }] };
const BUILD_ID = 'build-1';
const MARKUP = '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->';
const SANITIZED_MARKUP =
	'<!-- wp:paragraph --><p>Hi (sanitized)</p><!-- /wp:paragraph -->';

/**
 * @param {string} name        Block name.
 * @param {Object} [overrides] Extra block fields.
 * @return {Object} A parsed-block-shaped fake.
 */
function block(name, overrides = {}) {
	return { name, isValid: true, innerBlocks: [], ...overrides };
}

// Same attributes, distinguishable objects: the sanitized parse is the one
// that must be applied.
const ASSEMBLED = [
	block('core/paragraph', {
		attributes: { content: 'Hi' },
		originalContent: 'assembled',
	}),
];
const SANITIZED = [
	block('core/paragraph', {
		attributes: { content: 'Hi' },
		originalContent: 'sanitized',
	}),
];

/** `wp.blocks.validateBlock`-shaped fake: every block valid. */
const validAll = () => [true, []];

/**
 * @param {Object} pendingOverrides Fields merged into the GET response.
 * @param {Object} overrides        Per-test dep overrides.
 * @return {Object} `finishBuild()` deps.
 */
function createDeps(pendingOverrides = {}, overrides = {}) {
	return {
		fetchPending: jest.fn().mockResolvedValue({
			pending: true,
			buildId: BUILD_ID,
			conflict: false,
			isSubmitter: false,
			tree: TREE,
			mode: 'replace',
			...pendingOverrides,
		}),
		postReport: jest.fn().mockResolvedValue(undefined),
		sanitizeMarkup: jest.fn().mockResolvedValue({
			markup: SANITIZED_MARKUP,
		}),
		engine: {
			assemble: jest.fn().mockReturnValue({
				status: 'valid',
				markup: MARKUP,
				invalid: [],
			}),
			lint: jest.fn().mockReturnValue([]),
		},
		parse: jest.fn((markup) =>
			markup === SANITIZED_MARKUP ? SANITIZED : ASSEMBLED
		),
		validateBlock: jest.fn(validAll),
		getEditorBlocks: jest.fn().mockReturnValue([block('core/heading')]),
		replaceBlocks: jest.fn(),
		lockAutosave: jest.fn(),
		unlockAutosave: jest.fn(),
		savePost: jest.fn().mockResolvedValue(true),
		isPublished: jest.fn().mockReturnValue(false),
		notify: jest.fn(),
		markDocument: jest.fn(),
		onNextSave: jest.fn(),
		...overrides,
	};
}

/**
 * @param {Object} deps Deps after `finishBuild()` ran.
 */
function expectNothingApplied(deps) {
	expect(deps.replaceBlocks).not.toHaveBeenCalled();
	expect(deps.savePost).not.toHaveBeenCalled();
	expect(deps.lockAutosave).not.toHaveBeenCalled();
	expect(deps.markDocument).toHaveBeenCalledWith('failed');
	expect(deps.notify).toHaveBeenCalledWith(
		'error',
		expect.any(String),
		expect.objectContaining({ id: FINISH_NOTICE_ID })
	);
}

describe('finishBuild() markup sanitization', () => {
	test('a submitter with unfiltered_html skips the sanitize route', async () => {
		const deps = createDeps({ submitterUnfiltered: true });

		await finishBuild(1, deps);

		expect(deps.sanitizeMarkup).not.toHaveBeenCalled();
		expect(deps.replaceBlocks).toHaveBeenCalledWith(ASSEMBLED);
	});

	test.each([
		['false', { submitterUnfiltered: false }],
		['missing', {}],
		['not strictly true', { submitterUnfiltered: 'true' }],
	])(
		'submitterUnfiltered %s sends the markup for sanitizing',
		async (label, pending) => {
			const deps = createDeps(pending);

			await finishBuild(1, deps);

			expect(deps.sanitizeMarkup).toHaveBeenCalledWith({
				buildId: BUILD_ID,
				markup: MARKUP,
			});
		}
	);

	test('the review branch applies the sanitized blocks', async () => {
		const deps = createDeps();

		await finishBuild(1, deps);

		expect(deps.replaceBlocks).toHaveBeenCalledTimes(1);
		expect(deps.replaceBlocks).toHaveBeenCalledWith(SANITIZED);
		expect(deps.postReport).toHaveBeenCalledWith(
			expect.objectContaining({ status: 'awaiting_review' })
		);
	});

	test('the submitter auto-save branch applies and saves the sanitized blocks', async () => {
		const deps = createDeps({ isSubmitter: true });

		await finishBuild(1, deps);

		expect(deps.sanitizeMarkup).toHaveBeenCalledTimes(1);
		expect(deps.replaceBlocks).toHaveBeenCalledWith(SANITIZED);
		expect(deps.savePost).toHaveBeenCalled();
		expect(deps.postReport).toHaveBeenCalledWith({
			buildId: BUILD_ID,
			status: 'finished',
			findings: [],
		});
	});

	test('append mode appends the sanitized blocks to the current ones', async () => {
		const deps = createDeps({ mode: 'append' });

		await finishBuild(1, deps);

		expect(deps.replaceBlocks).toHaveBeenCalledWith([
			block('core/heading'),
			...SANITIZED,
		]);
	});

	test('a block the filter invalidated fails the build and applies nothing', async () => {
		const deps = createDeps(
			{},
			{
				parse: jest.fn((markup) =>
					markup === SANITIZED_MARKUP
						? [block('core/paragraph', { isValid: false })]
						: ASSEMBLED
				),
			}
		);

		await finishBuild(1, deps);

		expect(deps.postReport).toHaveBeenCalledWith({
			buildId: BUILD_ID,
			status: 'failed',
			invalid: [
				{
					path: 'blocks[0]',
					block: 'core/paragraph',
					reason: expect.any(String),
					code: 'designsetgo_sanitized_content_changed',
				},
			],
			findings: [],
		});
		expectNothingApplied(deps);
	});

	test('an attribute the filter changed fails the build, naming the attribute, even though the block is valid', async () => {
		const deps = createDeps(
			{},
			{
				parse: jest.fn((markup) =>
					markup === SANITIZED_MARKUP
						? [
								block('core/button', {
									attributes: { text: 'Go', url: 'alert(1)' },
								}),
							]
						: [
								block('core/button', {
									attributes: {
										text: 'Go',
										url: 'javascript:alert(1)',
									},
								}),
							]
				),
			}
		);

		await finishBuild(1, deps);

		expect(deps.postReport).toHaveBeenCalledWith({
			buildId: BUILD_ID,
			status: 'failed',
			invalid: [
				{
					path: 'blocks[0]',
					block: 'core/button',
					reason: expect.stringContaining('url'),
					code: 'designsetgo_sanitized_content_changed',
				},
			],
			findings: [],
		});
		expect(
			deps.postReport.mock.calls[0][0].invalid[0].reason
		).not.toContain('text');
		expectNothingApplied(deps);
	});

	test('a block parse marked valid but that fails validateBlock() against the current save() fails the build', async () => {
		const deps = createDeps(
			{},
			{
				validateBlock: jest.fn((candidate) => [
					candidate.originalContent !== 'sanitized',
					[],
				]),
			}
		);

		await finishBuild(1, deps);

		expect(deps.validateBlock).toHaveBeenCalledWith(SANITIZED[0]);
		expect(deps.postReport).toHaveBeenCalledWith(
			expect.objectContaining({
				status: 'failed',
				invalid: [
					expect.objectContaining({
						path: 'blocks[0]',
						code: 'designsetgo_sanitized_content_changed',
					}),
				],
			})
		);
		expectNothingApplied(deps);
	});

	test('markup over 1 MB fails the build without calling the route', async () => {
		const deps = createDeps();
		deps.engine.assemble.mockReturnValue({
			status: 'valid',
			// Multi-byte characters: 350,000 × 3 bytes is over 1 MB in UTF-8.
			markup: `<p>${'€'.repeat(350000)}</p>`,
			invalid: [],
		});

		await finishBuild(1, deps);

		expect(deps.sanitizeMarkup).not.toHaveBeenCalled();
		expect(deps.postReport).toHaveBeenCalledWith({
			buildId: BUILD_ID,
			status: 'failed',
			invalid: [
				{ path: '', block: '', reason: 'sanitize markup too large' },
			],
			findings: [],
		});
		expectNothingApplied(deps);
	});

	test('a sanitize request failure fails the build, applies nothing, and never throws', async () => {
		const deps = createDeps(
			{},
			{ sanitizeMarkup: jest.fn().mockRejectedValue(new Error('down')) }
		);

		await expect(finishBuild(1, deps)).resolves.toBeUndefined();

		expect(deps.postReport).toHaveBeenCalledWith({
			buildId: BUILD_ID,
			status: 'failed',
			invalid: [
				{
					path: '',
					block: '',
					reason: expect.stringContaining('sanitize'),
				},
			],
			findings: [],
		});
		expectNothingApplied(deps);
	});

	test('a sanitize response without markup fails the build', async () => {
		const deps = createDeps(
			{},
			{ sanitizeMarkup: jest.fn().mockResolvedValue({}) }
		);

		await finishBuild(1, deps);

		expect(deps.postReport).toHaveBeenCalledWith(
			expect.objectContaining({ status: 'failed' })
		);
		expectNothingApplied(deps);
	});
});

describe('findSanitizedChanges()', () => {
	test('identical valid structures report nothing', () => {
		const before = [block('a', { innerBlocks: [block('b')] })];
		const after = [block('a', { innerBlocks: [block('b')] })];

		expect(findSanitizedChanges(before, after, validAll)).toEqual([]);
	});

	test('rich-text values compare by their HTML string', () => {
		const richText = (html) => ({ toHTMLString: () => html });
		const before = [
			block('a', { attributes: { content: richText('x<br>y') } }),
		];
		const after = [
			block('a', { attributes: { content: richText('x<br>y') } }),
		];

		expect(findSanitizedChanges(before, after, validAll)).toEqual([]);
	});

	test('reports nested attribute changes by top-level attribute name', () => {
		const before = [
			block('a', {
				attributes: { style: { color: { text: 'red' } }, level: 2 },
			}),
		];
		const after = [
			block('a', {
				attributes: { style: { color: { text: 'blue' } }, level: 2 },
			}),
		];

		const [change] = findSanitizedChanges(before, after, validAll);
		expect(change.reason).toContain('style');
		expect(change.reason).not.toContain('level');
	});

	test('reports an attribute the filter removed', () => {
		const before = [block('a', { attributes: { url: 'x', alt: 'y' } })];
		const after = [block('a', { attributes: { alt: 'y' } })];

		expect(
			findSanitizedChanges(before, after, validAll)[0].reason
		).toContain('url');
	});

	test('reports an invalid nested block at its path', () => {
		const before = [block('a', { innerBlocks: [block('b'), block('c')] })];
		const after = [
			block('a', {
				innerBlocks: [block('b'), block('c', { isValid: false })],
			}),
		];

		expect(findSanitizedChanges(before, after, validAll)).toEqual([
			expect.objectContaining({
				path: 'blocks[0].innerBlocks[1]',
				block: 'c',
				code: 'designsetgo_sanitized_content_changed',
			}),
		]);
	});

	test('reports a renamed block and a changed child count', () => {
		const before = [block('a'), block('b', { innerBlocks: [block('c')] })];
		const after = [block('x'), block('b')];

		expect(
			findSanitizedChanges(before, after, validAll).map(
				({ path }) => path
			)
		).toEqual(['blocks[0]', 'blocks[1]']);
	});

	test('reports a changed top-level block count', () => {
		expect(findSanitizedChanges([block('a')], [], validAll)).toEqual([
			expect.objectContaining({
				path: 'blocks',
				code: 'designsetgo_sanitized_content_changed',
			}),
		]);
	});
});
