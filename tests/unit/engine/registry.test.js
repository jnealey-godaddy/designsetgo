/**
 * `registerAll()` (via `registerForJest()`) must load every block and
 * extension from its REAL registration file — the same files a real editor
 * page load requires — so the engine's `save()` output is trustworthy for
 * the Node CLI (Task 5) and downstream Jest tests (Tasks 6, 7, 13, 14).
 *
 * Registers once for the whole file (`beforeAll`): WordPress's block
 * registry is a module-scoped data store, and `registerAll()`'s own guard
 * (an already-registered `designsetgo/section` short-circuits) makes a
 * second call here a no-op rather than a double-registration error.
 */
import fs from 'fs';
import path from 'path';
// eslint-disable-next-line import/no-unresolved
import * as blocksApi from '@wordpress/block-editor/node_modules/@wordpress/blocks';
import { registerForJest } from '../../../src/engine/registry/sources-fs';
import { createEngine } from '../../../src/engine';
import { TREE_VERSION } from '../../../src/engine/tree';

const BLOCKS_DIR = path.resolve(__dirname, '../../../src/blocks');

/**
 * Every `name` declared in `src/blocks/*\/block.json` — never the directory
 * name, which doesn't always match the registered block name (e.g.
 * `src/blocks/form-textarea-field/` registers `designsetgo/form-textarea`).
 *
 * @return {string[]} Registered block names.
 */
function designSetGoBlockNames() {
	return fs
		.readdirSync(BLOCKS_DIR, { withFileTypes: true })
		.filter((entry) => entry.isDirectory())
		.map((entry) => path.join(BLOCKS_DIR, entry.name, 'block.json'))
		.filter((file) => fs.existsSync(file))
		.map((file) => JSON.parse(fs.readFileSync(file, 'utf8')).name);
}

describe('registry', () => {
	let failures;

	beforeAll(() => {
		failures = registerForJest();
	});

	it('registers every block and extension with no failures', () => {
		expect(failures).toEqual([]);
	});

	it('registers every DesignSetGo block declared in its block.json', () => {
		const names = designSetGoBlockNames();

		// Sanity check on the fixture itself: this must find real blocks, or
		// the assertion below would trivially pass on an empty list.
		expect(names.length).toBeGreaterThan(50);

		names.forEach((name) => {
			expect(blocksApi.getBlockType(name)).toBeDefined();
		});
	});

	it('registers at least 90 core blocks', () => {
		const coreBlockCount = blocksApi
			.getBlockTypes()
			.filter((blockType) => blockType.name.startsWith('core/')).length;

		expect(coreBlockCount).toBeGreaterThanOrEqual(90);
	});

	describe('every extension is loaded from its real registration file', () => {
		// One known attribute per extension, read from the extension's own
		// source (never guessed) — confirms the extension's real
		// `blocks.registerBlockType` filter actually ran, not just that some
		// file of that name exists.
		it.each([
			// src/extensions/block-animations/attributes.js — applies to all blocks.
			['block-animations', 'dsgoAnimationEnabled'],
			// src/extensions/custom-css/index.js — EXCLUDED_BLOCKS is core/html, core/code only.
			['custom-css', 'dsgoCustomCSS'],
			// src/extensions/responsive/index.js — applies to all blocks.
			['responsive', 'dsgoHideOnDesktop'],
			// src/extensions/visibility/filters.js — BLOCKED excludes only core/freeform, core/missing, core/template-part.
			['visibility', 'dsgoVisibility'],
			// src/extensions/style-binding/filters.js — same BLOCKED set as visibility.
			['style-binding', 'dsgoStyleBinding'],
			// src/extensions/svg-patterns/constants.js — SUPPORTED_BLOCKS is exactly core/group, designsetgo/section.
			['svg-patterns', 'dsgoSvgPatternEnabled'],
			// src/extensions/background-video/index.js — ALLOWED_BLOCKS includes designsetgo/section.
			['background-video', 'dsgoVideoUrl'],
		])(
			'%s (designsetgo/section carries %s)',
			(extensionName, attribute) => {
				const section = blocksApi.getBlockType('designsetgo/section');
				expect(section.attributes).toHaveProperty(attribute);
			}
		);

		// src/extensions/max-width/index.js's own EXCLUDED_BLOCKS rules out
		// designsetgo/section/row/grid/blobs, but not core/group — and
		// extensions register (step 3) before core blocks do (step 4, see
		// register-all.js), mirroring the real editor's enqueue order
		// (plugin editor scripts run before edit-post's initializeEditor()
		// calls registerCoreBlocks()), so this filter DOES reach core/group.
		it('max-width (core/group carries dsgoMaxWidth)', () => {
			const group = blocksApi.getBlockType('core/group');
			expect(group.attributes).toHaveProperty('dsgoMaxWidth');
		});

		// src/extensions/svg-patterns/constants.js's SUPPORTED_BLOCKS names
		// core/group explicitly (not just "all blocks"), so this is a direct
		// confirmation that a CORE block picks up an extension attribute —
		// the exact behavior the real editor's script load order relies on.
		it('svg-patterns (core/group carries dsgoSvgPatternEnabled)', () => {
			const group = blocksApi.getBlockType('core/group');
			expect(group.attributes).toHaveProperty('dsgoSvgPatternEnabled');
		});
	});

	it('assembles a mixed core + DesignSetGo tree to valid markup', () => {
		const engine = createEngine(blocksApi);
		const tree = {
			version: TREE_VERSION,
			blocks: [
				{
					name: 'designsetgo/section',
					innerBlocks: [
						{ name: 'core/heading' },
						{ name: 'core/paragraph' },
						{
							name: 'core/image',
							attributes: {
								url: 'https://example.com/image.jpg',
								alt: 'Example image',
							},
						},
						{
							name: 'core/buttons',
							innerBlocks: [
								{
									name: 'core/button',
									attributes: {
										text: 'Learn more',
										url: 'https://example.com',
									},
								},
							],
						},
						{
							name: 'designsetgo/row',
							innerBlocks: [
								{
									name: 'designsetgo/icon-button',
									attributes: { text: 'Click me' },
								},
							],
						},
					],
				},
			],
		};

		const result = engine.assemble(tree);

		expect(result.status).toBe('valid');
		expect(result.invalid).toEqual([]);
		expect(result.markup).toContain('<!-- wp:designsetgo/section');
	});
});
