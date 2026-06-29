/**
 * E2E Tests for blocks affected by the PrefixAllGlobals refactor (PR #420).
 *
 * Each dynamic block's render.php body was wrapped in a prefixed
 * designsetgo_render_*() function. These tests confirm the blocks still insert
 * in the editor without a validation warning and render on the frontend after
 * the refactor.
 *
 * Notes:
 *  - product-showcase-hero needs WooCommerce + a product to render its body,
 *    which is not installed in the test env, so it is editor-insertion only.
 */

const { test, expect } = require('@playwright/test');
const { getEditorCanvas, createNewPost } = require('./helpers/wordpress');
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

installVideoCapture(test);
// Delete each published page as the run proceeds so test pages don't pile up.
installPublishedPageCleanup(test);

/**
 * Insert a block in a fresh page, assert it appears in the editor canvas with
 * no validation warning, screenshot the editor, then publish and return the
 * frontend URL.
 *
 * @param {import('@playwright/test').Page} page
 * @param {object} testInfo
 * @param {object} options
 * @param {string} options.title     Post title.
 * @param {string} options.item      Artifact item path (e.g. 'blocks/slider').
 * @param {Function} options.build   () => block tree, evaluated in the page.
 * @param {string} options.blockName Block name for canvas selectors.
 * @returns {Promise<string>} Frontend URL.
 */
async function insertAndPublish(page, testInfo, { title, item, build, blockName }) {
	const artifact = defineArtifact(testInfo, 'blocks', item, 'insert-and-render');
	await createNewPost(page, 'page');
	await setPostTitle(page, title);

	await page.evaluate(build);
	await page.waitForTimeout(800);

	const canvas = getEditorCanvas(page);
	const block = canvas.locator(`[data-type="${blockName}"]`);
	// Attached (not visible): some dynamic blocks render a zero-size/empty
	// preview in the editor (e.g. dynamic-image with no resolvable image yet).
	await expect(block.first()).toBeAttached({ timeout: 10000 });

	const warnings = canvas.locator(
		`[data-type="${blockName}"] .block-editor-warning`
	);
	await expect(warnings).toHaveCount(0);

	await slowScrollEditor(page);
	await saveScreenshot(page, artifact, 'editor');

	const frontendUrl = await publishAndResolveUrl(page);
	await page.goto(frontendUrl);
	await page.waitForLoadState('domcontentloaded');
	return { artifact, frontendUrl };
}

// ---------------------------------------------------------------------------
test.describe('Slider block — editor and frontend', () => {
	test('slider inserts and renders its chrome on the frontend', async ({
		page,
	}, testInfo) => {
		const { artifact } = await insertAndPublish(page, testInfo, {
			title: 'Slider',
			item: 'slider',
			blockName: 'designsetgo/slider',
			build: () => {
				const { dispatch } = wp.data;
				const slide = wp.blocks.createBlock('designsetgo/slide', {}, [
					wp.blocks.createBlock('core/paragraph', {
						content: 'Slide one',
					}),
				]);
				const slider = wp.blocks.createBlock('designsetgo/slider', {}, [
					slide,
				]);
				dispatch('core/block-editor').insertBlocks([slider]);
			},
		});

		const slider = page.locator('.dsgo-slider');
		await expect(slider.first()).toBeAttached({ timeout: 10000 });

		await page.waitForLoadState('networkidle').catch(() => {});
		await slowScrollToBottom(page);
		await saveScreenshot(page, artifact, 'frontend');
	});
});

// ---------------------------------------------------------------------------
test.describe('Scroll Slides block — editor and frontend', () => {
	test('scroll-slides inserts and renders on the frontend', async ({
		page,
	}, testInfo) => {
		const { artifact } = await insertAndPublish(page, testInfo, {
			title: 'Scroll Slides',
			item: 'scroll-slides',
			blockName: 'designsetgo/scroll-slides',
			build: () => {
				const { dispatch } = wp.data;
				const slide = wp.blocks.createBlock(
					'designsetgo/scroll-slide',
					{},
					[
						wp.blocks.createBlock('core/paragraph', {
							content: 'Panel one',
						}),
					]
				);
				const slides = wp.blocks.createBlock(
					'designsetgo/scroll-slides',
					{},
					[slide]
				);
				dispatch('core/block-editor').insertBlocks([slides]);
			},
		});

		const wrapper = page.locator('.dsgo-scroll-slides');
		await expect(wrapper.first()).toBeAttached({ timeout: 10000 });

		await page.waitForLoadState('networkidle').catch(() => {});
		await slowScrollToBottom(page);
		await saveScreenshot(page, artifact, 'frontend');
	});
});

// ---------------------------------------------------------------------------
test.describe('Dynamic Image block — editor and frontend', () => {
	test('dynamic-image renders a figure from a fallback URL', async ({
		page,
	}, testInfo) => {
		const { artifact } = await insertAndPublish(page, testInfo, {
			title: 'Dynamic Image',
			item: 'dynamic-image',
			blockName: 'designsetgo/dynamic-image',
			build: () => {
				const { dispatch } = wp.data;
				// Same-origin core asset: avoids an external network request that
				// would keep the frontend page from reaching networkidle.
				const img = wp.blocks.createBlock('designsetgo/dynamic-image', {
					fallbackUrl: '/wp-includes/images/w-logo-blue.png',
					altText: 'Fallback image',
				});
				dispatch('core/block-editor').insertBlocks([img]);
			},
		});

		const figure = page.locator('figure.wp-block-designsetgo-dynamic-image');
		await expect(figure.first()).toBeAttached({ timeout: 10000 });
		const img = figure.locator('img[src*="w-logo-blue.png"]');
		expect(await img.count()).toBeGreaterThan(0);

		await page.waitForLoadState('networkidle').catch(() => {});
		await slowScrollToBottom(page);
		await saveScreenshot(page, artifact, 'frontend');
	});
});

// ---------------------------------------------------------------------------
test.describe('Breadcrumbs block — editor and frontend', () => {
	test('breadcrumbs inserts and renders nav on the frontend', async ({
		page,
	}, testInfo) => {
		const consoleErrors = [];
		page.on('pageerror', (err) => consoleErrors.push(err.message));

		const { artifact } = await insertAndPublish(page, testInfo, {
			title: 'Breadcrumbs',
			item: 'breadcrumbs',
			blockName: 'designsetgo/breadcrumbs',
			build: () => {
				const { dispatch } = wp.data;
				const crumbs = wp.blocks.createBlock('designsetgo/breadcrumbs', {
					showHome: true,
					showCurrent: true,
				});
				dispatch('core/block-editor').insertBlocks([crumbs]);
			},
		});

		// On a block theme the page content renders inside core/post-content,
		// which provides postId context, so the nav should render.
		const nav = page.locator('nav.dsgo-breadcrumbs');
		await expect(nav.first()).toBeAttached({ timeout: 10000 });
		expect(consoleErrors).toHaveLength(0);

		await page.waitForLoadState('networkidle').catch(() => {});
		await slowScrollToBottom(page);
		await saveScreenshot(page, artifact, 'frontend');
	});
});

// ---------------------------------------------------------------------------
// product-showcase-hero is intentionally NOT covered here: its editor preview
// and frontend render both require WooCommerce + a real product, which is not
// installed in the test env. The refactored guard/resolution logic
// (designsetgo_render_product_showcase_hero) is covered by its PHPUnit render
// test (tests/phpunit/blocks/product-showcase-hero/render-test.php). Full e2e
// coverage belongs on a WooCommerce-enabled environment.
