/**
 * E2E happy-path sweep across EVERY registered DesignSetGo pattern.
 *
 * For each pattern: insert it into a fresh page, capture an editor screenshot,
 * confirm no block fell back to "Attempt Recovery" (invalid save() markup —
 * the primary signal that a pattern is visually broken in the editor), publish,
 * and confirm the frontend renders with no PHP critical error and no uncaught
 * JS errors. Captures an editor + frontend screenshot per pattern (and a video
 * when DSGO_RECORD_VIDEO=1) under screenshots/patterns/<category>/<name>/sweep/.
 *
 * Unlike patterns-happy-path.spec.js (a small, hand-picked set with
 * security-branch-specific assertions about placeholder tokens and parallax
 * markup), this sweep trades depth for breadth: it derives the full pattern
 * list from patterns/ at load time, so new patterns are covered automatically,
 * and its only hard assertion is editor validity + clean render.
 *
 * The editor screenshot is captured BEFORE the validity assertion, so a pattern
 * that fails still leaves a screenshot to eyeball.
 */

const { test, expect } = require('@playwright/test');
const {
	getEditorCanvas,
	createNewPost,
	insertPatternBySlug,
	getInvalidBlockNames,
} = require('./helpers/wordpress');
const { listDesignSetGoPatterns } = require('./helpers/patterns');
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
// Delete each pattern's published page as the run proceeds so they don't pile
// up on the site (and in the nav header).
installPublishedPageCleanup(test);

const PATTERNS = listDesignSetGoPatterns();

const TOKEN_PREFIX = '{{dsgo:placeholder-';

// Patterns whose inserted blocks are known to fail save() validation. Each
// entry is tracked debt — fix the pattern's saved markup (or the underlying
// block's save()) and remove it. Empty by default; fill from real run results,
// each with a TODO note.
const KNOWN_INVALID = new Set([
	// 'designsetgo/content/content-foo', // TODO(foo): stale save markup
]);

test.describe('Patterns — editor and frontend happy-path sweep', () => {
	for (const { slug, title } of PATTERNS) {
		test(`${slug} inserts, publishes, and renders cleanly`, async ({
			page,
		}, testInfo) => {
			const item = slug.replace('designsetgo/', '');
			const artifact = defineArtifact(
				testInfo,
				'patterns',
				item,
				'sweep'
			);

			// Surface any JS errors thrown while the pattern renders.
			const pageErrors = [];
			page.on('pageerror', (err) => pageErrors.push(err.message));

			// --- Insert in the editor ---------------------------------------
			await createNewPost(page, 'page');
			await setPostTitle(page, `Pattern: ${title}`);

			const insertion = await insertPatternBySlug(page, slug);
			expect(insertion.blockCount).toBeGreaterThan(0);
			// The loader resolves placeholder tokens in PHP, so the registered
			// content handed to the editor must already be token-free.
			expect(insertion.hadToken).toBe(false);

			await page.waitForTimeout(800);

			const canvas = getEditorCanvas(page);
			await expect(canvas.locator('body')).toBeVisible();

			// Capture the editor view first — even if validity fails below, the
			// screenshot is there to eyeball what broke.
			await slowScrollEditor(page);
			await saveScreenshot(page, artifact, 'editor');

			// Collect (don't assert yet) so the frontend screenshot is captured
			// for broken patterns too. Assertions run at the end.
			const invalid = await getInvalidBlockNames(page);
			const canvasHtml = await canvas.locator('body').innerHTML();

			// --- Publish and review the frontend ----------------------------
			const frontendUrl = await publishAndResolveUrl(page);
			const response = await page.goto(frontendUrl);
			expect(response?.ok()).toBeTruthy();
			await page.waitForLoadState('domcontentloaded');

			const pageContent = await page.content();

			await page.waitForLoadState('networkidle').catch(() => {});
			await slowScrollToBottom(page);
			await saveScreenshot(page, artifact, 'frontend');

			// --- Assertions (after all artifacts captured) ------------------
			// No raw placeholder token should leak into editor or frontend.
			expect(canvasHtml).not.toContain(TOKEN_PREFIX);
			expect(pageContent).not.toContain(TOKEN_PREFIX);

			// No PHP fatal on the rendered page.
			expect(pageContent).not.toContain('There has been a critical error');

			// Validity: every inserted block must match its save() output. A
			// stale block renders "Attempt Recovery" in the editor — the core
			// "visually broken" signal this sweep exists to catch.
			if (!KNOWN_INVALID.has(slug)) {
				expect(
					invalid,
					`Invalid (Attempt Recovery) blocks in ${slug}: ${invalid.join(
						', '
					)}`
				).toEqual([]);
			}

			// No uncaught JS errors should have fired during render.
			expect(pageErrors).toEqual([]);
		});
	}
});
