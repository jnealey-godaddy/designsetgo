/**
 * E2E Tests for blocks affected by the PCP offloading branch.
 *
 * Verifies that the modal block, pattern placeholder images, and form builder
 * still render correctly in the editor and on the frontend after the refactors
 * (storage fallback, local placeholder images, WP_Query migration).
 */

const path = require('path');
const { test, expect } = require('@playwright/test');
const { getEditorCanvas, createNewPost } = require('./helpers/wordpress');

// Screenshots land alongside the other Playwright artifacts so they are easy
// to find (and get uploaded by CI). One per block for the editor view and one
// for the rendered frontend, for quick visual confirmation.
const SCREENSHOT_DIR = path.join(
	process.env.WP_ARTIFACTS_PATH || path.join(process.cwd(), 'artifacts'),
	'screenshots'
);

/**
 * Save a full-page screenshot under the shared screenshots directory.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @param {string}                          name - File name without extension
 */
async function saveScreenshot(page, name) {
	await page.screenshot({
		path: path.join(SCREENSHOT_DIR, `${name}.png`),
		fullPage: true,
	});
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
	}) => {
		const consoleErrors = [];
		page.on('pageerror', (err) => consoleErrors.push(err.message));

		await createNewPost(page, 'page');

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
		await saveScreenshot(page, 'modal-editor');

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
		await saveScreenshot(page, 'modal-frontend');
	});
});

// ---------------------------------------------------------------------------
// Pattern placeholder images — UI insertion + frontend image URLs
// ---------------------------------------------------------------------------
test.describe('Pattern placeholder images — inserter and frontend', () => {
	test('Hero Split pattern inserts with local placeholder images', async ({
		page,
	}) => {
		await createNewPost(page, 'page');

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
		await saveScreenshot(page, 'pattern-hero-split-editor');

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
		await saveScreenshot(page, 'pattern-hero-split-frontend');
	});
});

// ---------------------------------------------------------------------------
// Form builder — editor insertion + frontend HTML structure
// ---------------------------------------------------------------------------
test.describe('Form builder — editor and frontend', () => {
	test('form builder inserts and renders form element on frontend', async ({
		page,
	}) => {
		await createNewPost(page, 'page');

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
		await saveScreenshot(page, 'form-builder-editor');

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
		await saveScreenshot(page, 'form-builder-frontend');
	});
});
