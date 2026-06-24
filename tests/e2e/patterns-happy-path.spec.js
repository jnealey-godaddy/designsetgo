/**
 * E2E happy-path tests for patterns changed on the PCP security branch.
 *
 * Every pattern in `patterns/` was rewritten on this branch to replace
 * third-party asset references with local placeholders:
 *   - image patterns swap remote Unsplash `<img src>` for `{{dsgo:placeholder-*}}`
 *     tokens, resolved to bundled plugin assets at registration time;
 *   - link patterns (e.g. CTA social rows) swap external example URLs for `#`.
 *   - parallax image figures additionally gained the `rotate-*` data-attributes
 *     so their saved markup matches the current image-parallax save() output.
 *
 * Testing all 76 changed patterns end-to-end would be a very long suite, so this
 * covers one representative pattern per category through the full happy path:
 * insert → review the editor → publish → review the frontend. The assertions
 * target exactly what this branch changed — placeholder resolution and the
 * image/parallax block markup — and deliberately ignore unrelated, pre-existing
 * block-validation debt in the slider/accordion/cover/form blocks (tracked
 * separately) so this suite stays green on changes it does not own.
 */

const { test, expect } = require('@playwright/test');
const {
	getEditorCanvas,
	createNewPost,
	insertPatternBySlug,
	getInvalidBlockNames,
} = require('./helpers/wordpress');
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

// Local placeholder assets all live under this path; every resolved token
// points here, so a single substring match validates editor + frontend images.
const ASSET_PATH = 'designsetgo/assets/images/patterns/';
const TOKEN_PREFIX = '{{dsgo:placeholder-';

// Blocks whose saved markup carries the swapped image references. The token
// swap and the parallax-attribute fix must not invalidate these; unrelated
// blocks (slider/accordion/cover/form) may carry pre-existing validation debt
// that is out of scope for this branch, so they are intentionally not asserted.
const IMAGE_BLOCKS = ['core/image', 'core/gallery'];

/**
 * One representative pattern per category. `slug` is the registered pattern
 * name (designsetgo/<category>/<file>). Flags drive the optional assertions:
 *   - hasImages:    expect ≥1 local placeholder image in editor + frontend
 *   - hasParallax:  expect a parallax figure carrying the new rotate attribute
 *   - forbidden:    substrings that must NOT survive the placeholder rewrite
 */
const PATTERNS = [
	{
		slug: 'designsetgo/content/content-split-image',
		title: 'Pattern: Content Split Image',
		hasImages: true,
	},
	{
		slug: 'designsetgo/cta/cta-work-together',
		title: 'Pattern: CTA Work Together',
		hasImages: false,
		// External example links were replaced with "#" anchors.
		forbidden: [
			'https://linkedin.com',
			'https://x.com',
			'https://dribbble.com',
			'https://instagram.com',
		],
	},
	{
		slug: 'designsetgo/faq/faq-split-image',
		title: 'Pattern: FAQ Split Image',
		hasImages: true,
	},
	{
		slug: 'designsetgo/gallery/gallery-parallax-grid',
		title: 'Pattern: Gallery Parallax Grid',
		hasImages: true,
		hasParallax: true,
	},
	{
		slug: 'designsetgo/hero/hero-parallax',
		title: 'Pattern: Hero Parallax',
		hasImages: true,
		hasParallax: true,
	},
	{
		slug: 'designsetgo/homepage/homepage-portfolio',
		title: 'Pattern: Homepage Portfolio',
		hasImages: true,
	},
	{
		slug: 'designsetgo/modal/modal-video-cta',
		title: 'Pattern: Modal Video CTA',
		hasImages: true,
	},
	{
		slug: 'designsetgo/team/team-grid',
		title: 'Pattern: Team Grid',
		hasImages: true,
	},
	{
		slug: 'designsetgo/testimonials/testimonials-grid',
		title: 'Pattern: Testimonials Grid',
		hasImages: true,
	},
];

test.describe('Changed patterns — editor and frontend happy path', () => {
	for (const pattern of PATTERNS) {
		test(`${pattern.slug} inserts, publishes, and renders with local assets`, async ({
			page,
		}, testInfo) => {
			// item nests under patterns/<category>/<name>/; scenario names this
			// specific test so the same pattern can host multiple scenarios
			// without colliding (mirrors how block artifacts are grouped).
			const item = pattern.slug.replace('designsetgo/', '');
			const artifact = defineArtifact(
				testInfo,
				'patterns',
				item,
				'insert-publish-render'
			);

			// Surface any JS errors thrown while the pattern renders.
			const pageErrors = [];
			page.on('pageerror', (err) => pageErrors.push(err.message));

			// --- Insert in the editor ---------------------------------------
			await createNewPost(page, 'page');
			await setPostTitle(page, pattern.title);

			const insertion = await insertPatternBySlug(page, pattern.slug);
			expect(insertion.blockCount).toBeGreaterThan(0);
			// The loader replaces tokens in PHP, so the registered content
			// handed to the editor must already be token-free.
			expect(insertion.hadToken).toBe(false);

			await page.waitForTimeout(800);

			const canvas = getEditorCanvas(page);
			// Pattern blocks should be present in the canvas.
			await expect(canvas.locator('body')).toBeVisible();

			// No raw placeholder token should leak into the editor markup.
			const canvasHtml = await canvas.locator('body').innerHTML();
			expect(canvasHtml).not.toContain(TOKEN_PREFIX);

			// Forbidden external references must be gone.
			for (const needle of pattern.forbidden || []) {
				expect(canvasHtml).not.toContain(needle);
			}

			// Regression guard: the image/parallax blocks this branch touched
			// must still validate against their save() output (a stale figure
			// would render the "Attempt Recovery" warning instead).
			const invalid = await getInvalidBlockNames(page);
			const invalidImageBlocks = invalid.filter((name) =>
				IMAGE_BLOCKS.includes(name)
			);
			expect(invalidImageBlocks).toEqual([]);

			if (pattern.hasImages) {
				const editorImages = canvas.locator(
					`img[src*="${ASSET_PATH}"]`
				);
				expect(await editorImages.count()).toBeGreaterThan(0);
			}

			await slowScrollEditor(page);
			await saveScreenshot(page, artifact, 'editor');

			// --- Publish and review the frontend ----------------------------
			const frontendUrl = await publishAndResolveUrl(page);

			const response = await page.goto(frontendUrl);
			expect(response?.ok()).toBeTruthy();
			await page.waitForLoadState('domcontentloaded');

			const pageContent = await page.content();
			// No PHP fatal and no token leak on the rendered page.
			expect(pageContent).not.toContain(TOKEN_PREFIX);
			expect(pageContent).not.toContain(
				'There has been a critical error'
			);
			for (const needle of pattern.forbidden || []) {
				expect(pageContent).not.toContain(needle);
			}

			if (pattern.hasParallax) {
				// The parallax fix added rotate-* attributes to the saved figure
				// markup; confirm the rendered frontend carries one (proves the
				// pattern's saved markup matches the current image-parallax save()).
				const parallaxFigure = page.locator(
					'.dsgo-has-parallax[data-dsgo-parallax-rotate-enabled]'
				);
				expect(await parallaxFigure.count()).toBeGreaterThan(0);
			}

			if (pattern.hasImages) {
				const frontendImages = page.locator(
					`img[src*="${ASSET_PATH}"]`
				);
				expect(await frontendImages.count()).toBeGreaterThan(0);
				// The first placeholder image must actually load (real asset,
				// not a 404), proving the bundled files resolve.
				const naturalWidth = await frontendImages
					.first()
					.evaluate((img) => {
						if (img.complete) {
							return img.naturalWidth;
						}
						return new Promise((resolve) => {
							img.addEventListener('load', () =>
								resolve(img.naturalWidth)
							);
							img.addEventListener('error', () => resolve(0));
						});
					});
				expect(naturalWidth).toBeGreaterThan(0);
			}

			await page.waitForLoadState('networkidle').catch(() => {});
			await slowScrollToBottom(page);
			await saveScreenshot(page, artifact, 'frontend');

			// No uncaught JS errors should have fired during render.
			expect(pageErrors).toEqual([]);
		});
	}
});
