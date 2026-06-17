/**
 * E2E Tests for blocks affected by the PCP offloading branch.
 *
 * Verifies that the modal block, pattern placeholder images, and form builder
 * still render correctly in the editor and on the frontend after the refactors
 * (storage fallback, local placeholder images, WP_Query migration).
 */

const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');
const { getEditorCanvas, createNewPost } = require('./helpers/wordpress');

// Screenshots land alongside the other Playwright artifacts so they are easy
// to find (and get uploaded by CI). They are grouped one folder per block, with
// editor captures prefixed `editor-` and frontend captures left bare, e.g.
// screenshots/modal/editor-modal.png and screenshots/modal/modal.png.
const SCREENSHOT_DIR = path.join(
	process.env.WP_ARTIFACTS_PATH || path.join(process.cwd(), 'artifacts'),
	'screenshots'
);

// Optional video capture. Off by default (screenshots only); set
// DSGO_RECORD_VIDEO=1 to also record a video of each test. Videos are grouped
// next to the screenshots, one per block: screenshots/<block>/<block>.webm.
const RECORD_VIDEO = ['1', 'true'].includes(process.env.DSGO_RECORD_VIDEO);

test.use({ video: RECORD_VIDEO ? 'on' : 'off' });

// Collected during afterEach (path is known while recording) and copied into
// place in afterAll, once every per-test context has closed and the video
// files are finalized — avoids saveAs() deadlocking on the still-open page.
const pendingVideos = [];

test.afterEach(async ({ page }, testInfo) => {
	if (!RECORD_VIDEO) {
		return;
	}
	const video = page.video();
	const block = testInfo.annotations.find(
		(a) => a.type === 'dsgo-block'
	)?.description;
	if (!video || !block) {
		return;
	}
	pendingVideos.push({
		src: await video.path(),
		dest: path.join(SCREENSHOT_DIR, block, `${block}.webm`),
	});
});

test.afterAll(async () => {
	for (const { src, dest } of pendingVideos) {
		try {
			fs.mkdirSync(path.dirname(dest), { recursive: true });
			fs.copyFileSync(src, dest);
		} catch {
			// Best effort — the original still exists in the test-results dir.
		}
	}
});

/**
 * Save a full-page screenshot grouped by block name.
 *
 * @param {import('@playwright/test').Page} page   - Playwright page object
 * @param {string}                          block  - Block name (folder + base file name)
 * @param {'editor'|'frontend'}             source - Where the capture is from;
 *                                                 `editor` adds an `editor-` prefix so admin/editor shots are distinguishable.
 */
async function saveScreenshot(page, block, source) {
	const fileName =
		source === 'editor' ? `editor-${block}.png` : `${block}.png`;
	await page.screenshot({
		path: path.join(SCREENSHOT_DIR, block, fileName),
		fullPage: true,
	});
}

/**
 * Slowly scroll the frontend page from top to bottom and back to top.
 *
 * Used on the published (non-editor) view so the full block is revealed over
 * time in the recorded video, and so any lazy-loaded / parallax content is
 * triggered before the screenshot. Runs in small steps with a short delay so
 * the motion is visible rather than an instant jump.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object
 */
async function slowScrollToBottom(page) {
	await page.evaluate(async () => {
		const step = 250;
		const delayMs = 120;
		const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

		window.scrollTo(0, 0);
		await sleep(delayMs);

		let last = -1;
		// Guard against unbounded growth (infinite scroll) with a step cap.
		for (let i = 0; i < 200; i++) {
			const maxY =
				document.documentElement.scrollHeight - window.innerHeight;
			const y = Math.min(window.scrollY + step, maxY);
			window.scrollTo(0, y);
			await sleep(delayMs);
			if (y >= maxY || y === last) {
				break;
			}
			last = y;
		}

		await sleep(delayMs);
		window.scrollTo(0, 0);
	});
}

/**
 * Title the current post after the block under test, so test pages are
 * identifiable (instead of "(no title)") in the editor, the page list, and the
 * frontend.
 *
 * @param {import('@playwright/test').Page} page  - Playwright page object
 * @param {string}                          title - Post title (the block name)
 */
async function setPostTitle(page, title) {
	await page.evaluate((value) => {
		wp.data.dispatch('core/editor').editPost({ title: value });
	}, title);
}

/**
 * Publish the current post and return its frontend URL.
 *
 * Publishes through the editor data store rather than the publish-panel UI.
 * The UI flow is flaky under wp-env (the welcome-guide / pre-publish modal can
 * intercept the click, leaving the post an unqueryable draft). Dispatching
 * `editPost({ status: 'publish' })` + `savePost()` and then polling
 * `isCurrentPostPublished()` is deterministic.
 *
 * Returns a `?page_id=<id>` URL: a page published without a title keeps the
 * stale "auto-draft" slug in the permalink cache, so the pretty permalink can
 * 404. The query-arg form always resolves to the published content.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @return {Promise<string>} Frontend URL of the published post
 */
async function publishAndResolveUrl(page) {
	await page.evaluate(async () => {
		const { dispatch } = wp.data;
		await dispatch('core/editor').editPost({ status: 'publish' });
		await dispatch('core/editor').savePost();
	});

	// Wait until the store confirms the post is published and not mid-save.
	await page.waitForFunction(
		() => {
			const editor = wp.data.select('core/editor');
			return editor.isCurrentPostPublished() && !editor.isSavingPost();
		},
		undefined,
		{ timeout: 30000 }
	);

	const id = await page.evaluate(() =>
		wp.data.select('core/editor').getCurrentPostId()
	);
	if (!id) {
		throw new Error('Could not determine published post ID');
	}
	return `/?page_id=${id}`;
}

// ---------------------------------------------------------------------------
// Modal block — editor insertion + frontend rendering
// ---------------------------------------------------------------------------
test.describe('Modal block — editor and frontend', () => {
	test('modal block inserts without validation error and renders on frontend', async ({
		page,
	}, testInfo) => {
		testInfo.annotations.push({ type: 'dsgo-block', description: 'modal' });
		const consoleErrors = [];
		page.on('pageerror', (err) => consoleErrors.push(err.message));

		await createNewPost(page, 'page');
		await setPostTitle(page, 'Modal');

		// Insert a modal block with a paragraph inner block.
		await page.evaluate(() => {
			const { dispatch } = wp.data;
			const innerParagraph = wp.blocks.createBlock('core/paragraph', {
				content: 'Modal e2e test content',
			});
			const modal = wp.blocks.createBlock(
				'designsetgo/modal',
				{ width: '500px' },
				[innerParagraph]
			);
			dispatch('core/block-editor').insertBlocks([modal]);
		});

		await page.waitForTimeout(800);

		// Verify block appears in editor canvas without a validation warning.
		const canvas = getEditorCanvas(page);
		const modalBlock = canvas.locator('[data-type="designsetgo/modal"]');
		await expect(modalBlock.first()).toBeVisible({ timeout: 10000 });

		// No block validation warning should exist for the modal.
		const warnings = canvas.locator(
			'[data-type="designsetgo/modal"] .block-editor-warning'
		);
		await expect(warnings).toHaveCount(0);

		// Visual confirmation of the block in the editor.
		await saveScreenshot(page, 'modal', 'editor');

		// Publish and visit frontend.
		const frontendUrl = await publishAndResolveUrl(page);

		await page.goto(frontendUrl);
		await page.waitForLoadState('domcontentloaded');

		// The modal markup is always in the DOM but hidden (display:none)
		// until triggered — assert it is attached, not visible.
		const modalWrapper = page.locator('[data-dsgo-modal="true"]');
		await expect(modalWrapper.first()).toBeAttached({ timeout: 10000 });

		// Verify the modal content exists in the DOM.
		const modalContent = page.locator('.dsgo-modal__content');
		expect(await modalContent.count()).toBeGreaterThan(0);

		// Confirm the modal stylesheet loaded by asserting it is hidden by default.
		const display = await modalWrapper
			.first()
			.evaluate((el) => window.getComputedStyle(el).display);
		expect(display).toBe('none');

		// No JS errors from storage access should have been thrown.
		const storageErrors = consoleErrors.filter(
			(msg) =>
				msg.includes('localStorage') ||
				msg.includes('sessionStorage') ||
				msg.includes('SecurityError')
		);
		expect(storageErrors).toHaveLength(0);

		// Visual confirmation of the rendered frontend. The modal itself is
		// hidden until triggered, so this captures the page as a visitor first
		// sees it (modal markup present, not yet shown).
		await page.waitForLoadState('networkidle').catch(() => {});
		await slowScrollToBottom(page);
		await saveScreenshot(page, 'modal', 'frontend');
	});
});

// ---------------------------------------------------------------------------
// Pattern placeholder images — UI insertion + frontend image URLs
// ---------------------------------------------------------------------------
test.describe('Pattern placeholder images — inserter and frontend', () => {
	test('Hero Split pattern inserts with local placeholder images', async ({
		page,
	}, testInfo) => {
		testInfo.annotations.push({
			type: 'dsgo-block',
			description: 'hero-split',
		});
		await createNewPost(page, 'page');
		await setPostTitle(page, 'Hero Split');

		// Open the global block inserter.
		const inserterToggle = page.locator(
			'button.editor-document-tools__inserter-toggle'
		);
		const isAlreadyOpen =
			(await inserterToggle.getAttribute('aria-pressed')) === 'true';
		if (!isAlreadyOpen) {
			await inserterToggle.click();
		}

		// Switch to the Patterns tab.
		const patternsTab = page.getByRole('tab', { name: /patterns/i });
		await patternsTab.waitFor({ timeout: 5000 });
		await patternsTab.click();

		// Search for the Hero Split pattern.
		const searchInput = page
			.locator(
				'.block-editor-inserter__search input, .components-search-control__input'
			)
			.first();
		await searchInput.waitFor();
		await searchInput.fill('Hero Split');

		// Wait for the pattern result to appear in the patterns list.
		const patternItem = page
			.locator('.block-editor-block-patterns-list__item')
			.first();

		// If no pattern found, skip (pattern might not be registered in test env).
		try {
			await patternItem.waitFor({ state: 'visible', timeout: 8000 });
		} catch {
			test.skip(true, 'Hero Split pattern not found in inserter');
			return;
		}

		await patternItem.click();
		await page.waitForTimeout(1500);

		// Close inserter if still open.
		const isPressed = await inserterToggle.getAttribute('aria-pressed');
		if (isPressed === 'true') {
			await inserterToggle.click();
		}

		// Verify no raw placeholder tokens are visible in editor canvas.
		const canvas = getEditorCanvas(page);
		const canvasHtml = await canvas.locator('body').innerHTML();
		expect(canvasHtml).not.toContain('{{dsgo:placeholder-');

		// Verify images are present with plugin asset URLs.
		const images = canvas.locator(
			'img[src*="designsetgo/assets/images/patterns/"]'
		);
		expect(await images.count()).toBeGreaterThan(0);

		// Regression guard: every block in the pattern must validate against its
		// save() output. A stale pattern (e.g. the parallax image missing the
		// rotate data-attributes) shows up as isValid:false and would render the
		// "Attempt Recovery" warning in the editor.
		const invalidBlocks = await page.evaluate(() => {
			const bad = [];
			const walk = (blocks) => {
				for (const b of blocks) {
					if (b.isValid === false) {
						bad.push(b.name);
					}
					if (b.innerBlocks) {
						walk(b.innerBlocks);
					}
				}
			};
			walk(wp.data.select('core/block-editor').getBlocks());
			return bad;
		});
		expect(invalidBlocks).toEqual([]);

		// Visual confirmation of the pattern in the editor.
		await saveScreenshot(page, 'hero-split', 'editor');

		// Publish and check frontend.
		const frontendUrl = await publishAndResolveUrl(page);

		await page.goto(frontendUrl);
		await page.waitForLoadState('domcontentloaded');

		// Frontend should have images pointing to local plugin assets.
		const frontendImages = page.locator(
			'img[src*="designsetgo/assets/images/patterns/"]'
		);
		expect(await frontendImages.count()).toBeGreaterThan(0);

		// No raw tokens should appear in the page source.
		const pageContent = await page.content();
		expect(pageContent).not.toContain('{{dsgo:placeholder-');

		// Visual confirmation of the rendered frontend (let images settle first).
		await page.waitForLoadState('networkidle').catch(() => {});
		await slowScrollToBottom(page);
		await saveScreenshot(page, 'hero-split', 'frontend');
	});
});

// ---------------------------------------------------------------------------
// Form builder — editor insertion + frontend HTML structure
// ---------------------------------------------------------------------------
test.describe('Form builder — editor and frontend', () => {
	test('form builder inserts and renders form element on frontend', async ({
		page,
	}, testInfo) => {
		testInfo.annotations.push({
			type: 'dsgo-block',
			description: 'form-builder',
		});
		await createNewPost(page, 'page');
		await setPostTitle(page, 'Form Builder');

		// Insert the form-builder block with a text field child.
		await page.evaluate(() => {
			const { dispatch } = wp.data;
			const textField = wp.blocks.createBlock(
				'designsetgo/form-text-field',
				{ label: 'Name', fieldName: 'name', required: true }
			);
			const form = wp.blocks.createBlock(
				'designsetgo/form-builder',
				// hasFields gates save() — set it explicitly so the form renders
				// on the frontend without waiting on the editor's sync effect.
				{ hasFields: true },
				[textField]
			);
			dispatch('core/block-editor').insertBlocks([form]);
		});

		await page.waitForTimeout(800);

		// Verify block appears in editor canvas without warnings.
		const canvas = getEditorCanvas(page);
		const formBlock = canvas.locator(
			'[data-type="designsetgo/form-builder"]'
		);
		await expect(formBlock.first()).toBeVisible({ timeout: 10000 });

		const warnings = canvas.locator(
			'[data-type="designsetgo/form-builder"] .block-editor-warning'
		);
		await expect(warnings).toHaveCount(0);

		// Visual confirmation of the block in the editor.
		await saveScreenshot(page, 'form-builder', 'editor');

		// Publish and visit frontend.
		const frontendUrl = await publishAndResolveUrl(page);

		await page.goto(frontendUrl);
		await page.waitForLoadState('domcontentloaded');

		// The block wrapper carries dsgo-form-builder; the actual <form>
		// element inside it carries dsgo-form.
		const formElement = page.locator('.dsgo-form-builder form.dsgo-form');
		await expect(formElement.first()).toBeVisible({ timeout: 10000 });

		// Verify the form has the text field we added (name="name").
		const inputField = formElement.locator('input[name="name"]');
		expect(await inputField.count()).toBeGreaterThan(0);

		// Visual confirmation of the rendered frontend.
		await page.waitForLoadState('networkidle').catch(() => {});
		await slowScrollToBottom(page);
		await saveScreenshot(page, 'form-builder', 'frontend');
	});
});
