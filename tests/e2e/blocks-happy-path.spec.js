/**
 * E2E happy-path tests for every top-level DesignSetGo block.
 *
 * For each block: insert it in the editor, confirm it's valid (no "Attempt
 * Recovery"), publish, and confirm the frontend renders with no PHP critical
 * error and no uncaught JS errors. Captures an editor + frontend screenshot per
 * block (and a video when DSGO_RECORD_VIDEO=1), under
 * screenshots/blocks/<block>/insert-publish-render/.
 *
 * The block list is derived from src/blocks/block.json files at load time
 * (top-level only — children ride in via their parent's template; Woo product
 * blocks are skipped), so new blocks are covered automatically.
 */

const { test, expect } = require('@playwright/test');
const {
	getEditorCanvas,
	createNewPost,
	getInvalidBlockNames,
} = require('./helpers/wordpress');
const {
	listTopLevelDesignSetGoBlocks,
	insertBlockByName,
} = require('./helpers/blocks');
const {
	defineArtifact,
	saveScreenshot,
	installVideoCapture,
	installPublishedPageCleanup,
	slowScrollToBottom,
	slowScrollEditor,
	setPostTitle,
	publishAndResolveUrl,
} = require('./helpers/artifacts');

// Record a video per test when DSGO_RECORD_VIDEO=1 (screenshots only by default).
installVideoCapture(test);
// Delete each published page as the run proceeds so test pages don't pile up.
installPublishedPageCleanup(test);

const BLOCKS = listTopLevelDesignSetGoBlocks();

// Per-block insertion overrides: { 'designsetgo/x': { attributes, innerBlocks } }.
// Populate ONLY where a block's defaults + block.json `example` don't render
// meaningfully. Empty by default; fill from real run results.
const OVERRIDES = {};

// Blocks whose green-field insert is known to fail save() validation. Each
// entry is tracked debt — fix the block's save() and remove it. Empty by
// default; fill from real run results, each with a TODO note.
const KNOWN_INVALID = new Set([
	// 'designsetgo/foo', // TODO(foo): fix save() — stale markup
]);

test.describe('Blocks — editor and frontend happy path', () => {
	for (const name of BLOCKS) {
		test(`${name} inserts, publishes, and renders cleanly`, async ({
			page,
		}, testInfo) => {
			const item = name.replace('designsetgo/', '');
			const artifact = defineArtifact(
				testInfo,
				'blocks',
				item,
				'insert-publish-render'
			);

			// Surface any JS errors thrown while the block renders.
			const pageErrors = [];
			page.on('pageerror', (err) => pageErrors.push(err.message));

			// --- Insert in the editor ---------------------------------------
			await createNewPost(page, 'page');
			await setPostTitle(page, `Block: ${item}`);

			const { clientId, blockCount } = await insertBlockByName(
				page,
				name,
				OVERRIDES[name]
			);
			expect(blockCount).toBeGreaterThan(0);

			// Wait for the editor to actually render the inserted block in the
			// canvas (the node's presence is the real signal, not a fixed sleep).
			const canvas = getEditorCanvas(page);
			await canvas
				.locator(`[data-block="${clientId}"]`)
				.first()
				.waitFor({ state: 'attached', timeout: 10000 });

			// Validity: the fresh insert must match save() output (a stale
			// figure would render "Attempt Recovery" instead).
			if (!KNOWN_INVALID.has(name)) {
				const invalid = await getInvalidBlockNames(page);
				expect(invalid).toEqual([]);
			}

			await slowScrollEditor(page);
			await saveScreenshot(page, artifact, 'editor');

			// --- Publish and review the frontend ----------------------------
			const frontendUrl = await publishAndResolveUrl(page);
			const response = await page.goto(frontendUrl);
			expect(response?.ok()).toBeTruthy();
			await page.waitForLoadState('domcontentloaded');

			const pageContent = await page.content();
			expect(pageContent).not.toContain(
				'There has been a critical error'
			);

			await page.waitForLoadState('networkidle').catch(() => {});
			await slowScrollToBottom(page);
			await saveScreenshot(page, artifact, 'frontend');

			// No uncaught JS errors should have fired during render.
			expect(pageErrors).toEqual([]);
		});
	}
});
