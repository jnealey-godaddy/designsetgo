/**
 * E2E Tests for blocks converted from static to dynamic (server-rendered).
 *
 * Each converted leaf block must satisfy two guarantees:
 *  1. A freshly inserted block is valid in the editor (no "Attempt Recovery"
 *     warning) and renders its real inline SVG on the frontend — NOT the old
 *     `.dsgo-lazy-icon` placeholder, since the SVG now ships from render.php.
 *  2. Legacy STATIC markup (the pre-conversion lazy-placeholder format) parses
 *     through the block's new deprecation and migrates silently — i.e. it never
 *     reports `isValid: false`, which is what would surface the recovery UI.
 *
 * The migration check runs the exact parse+deprecation pipeline the editor uses
 * on load via `wp.blocks.parse()`, then inspects the resulting block tree.
 */

const { test, expect } = require('@playwright/test');
const {
	getEditorCanvas,
	createNewPost,
	getInvalidBlockNames,
} = require('./helpers/wordpress');
const {
	defineArtifact,
	saveScreenshot,
	installVideoCapture,
	installPublishedPageCleanup,
	slowScrollToBottom,
	setPostTitle,
	publishAndResolveUrl,
} = require('./helpers/artifacts');

installVideoCapture(test);
installPublishedPageCleanup(test);

// Pre-conversion STATIC markup (lazy-icon placeholder). Feeding this through
// wp.blocks.parse() must migrate to the dynamic block without going invalid.
const LEGACY_ICON = `<!-- wp:designsetgo/icon {"icon":"heart","iconSize":40} -->
<div class="wp-block-designsetgo-icon dsgo-icon" style="display:flex;align-items:center;justify-content:center"><div class="dsgo-icon__wrapper dsgo-lazy-icon" style="width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;border-radius:inherit" data-icon-name="heart" data-icon-stroke-width="1.5" role="img" aria-label="Heart"></div></div>
<!-- /wp:designsetgo/icon -->`;

const LEGACY_DIVIDER = `<!-- wp:designsetgo/divider {"dividerStyle":"icon","iconName":"star"} -->
<div class="wp-block-designsetgo-divider dsgo-divider dsgo-divider--icon"><div class="dsgo-divider__container" style="width:100%"><div class="dsgo-divider__icon-wrapper"><span class="dsgo-divider__line dsgo-divider__line--left" style="height:2px"></span><span class="dsgo-divider__icon dsgo-lazy-icon" data-icon-name="star"></span><span class="dsgo-divider__line dsgo-divider__line--right" style="height:2px"></span></div></div></div>
<!-- /wp:designsetgo/divider -->`;

/**
 * Insert a block programmatically from name + attributes (registry-backed).
 *
 * @param {import('@playwright/test').Page} page       - Playwright page.
 * @param {string}                          name       - Block name.
 * @param {Object}                          attributes - Block attributes.
 */
async function insertBlock(page, name, attributes) {
	await page.evaluate(
		({ blockName, attrs }) => {
			const block = wp.blocks.createBlock(blockName, attrs);
			wp.data.dispatch('core/block-editor').insertBlocks([block]);
		},
		{ blockName: name, attrs: attributes }
	);
	await page.waitForTimeout(500);
}

/**
 * Parse raw serialized markup through the block pipeline and insert it. This is
 * the same path the editor uses on post load, so it exercises deprecations.
 *
 * @param {import('@playwright/test').Page} page   - Playwright page.
 * @param {string}                          markup - Serialized block markup.
 */
async function insertRawMarkup(page, markup) {
	await page.evaluate((html) => {
		const blocks = wp.blocks.parse(html);
		wp.data.dispatch('core/block-editor').insertBlocks(blocks);
	}, markup);
	await page.waitForTimeout(500);
}

// ---------------------------------------------------------------------------
// Icon block
// ---------------------------------------------------------------------------
test.describe('Icon block — dynamic render', () => {
	test('fresh insert is valid and renders inline SVG on the frontend', async ({
		page,
	}, testInfo) => {
		const artifact = defineArtifact(
			testInfo,
			'blocks',
			'icon',
			'dynamic-render'
		);

		await createNewPost(page, 'page');
		await setPostTitle(page, 'Icon Dynamic Render');
		await insertBlock(page, 'designsetgo/icon', {
			icon: 'star',
			iconSize: 64,
		});

		const canvas = getEditorCanvas(page);
		await expect(
			canvas.locator('[data-type="designsetgo/icon"]').first()
		).toBeVisible({ timeout: 10000 });

		// No failed validation, no recovery warning.
		expect(await getInvalidBlockNames(page)).not.toContain(
			'designsetgo/icon'
		);
		await expect(
			canvas.locator('[data-type="designsetgo/icon"] .block-editor-warning')
		).toHaveCount(0);
		await saveScreenshot(page, artifact, 'editor');

		const frontendUrl = await publishAndResolveUrl(page);
		await page.goto(frontendUrl);
		await page.waitForLoadState('domcontentloaded');

		// Real inline SVG is present; the old lazy placeholder is gone.
		await expect(
			page.locator('.wp-block-designsetgo-icon svg').first()
		).toBeVisible({ timeout: 10000 });
		expect(
			await page.locator('.wp-block-designsetgo-icon .dsgo-lazy-icon').count()
		).toBe(0);

		await page.waitForLoadState('networkidle').catch(() => {});
		await slowScrollToBottom(page);
		await saveScreenshot(page, artifact, 'frontend');
	});

	test('legacy static markup migrates silently (no invalid content)', async ({
		page,
	}) => {
		await createNewPost(page, 'page');
		await setPostTitle(page, 'Icon Legacy Migration');
		await insertRawMarkup(page, LEGACY_ICON);

		expect(await getInvalidBlockNames(page)).not.toContain(
			'designsetgo/icon'
		);
	});
});

// ---------------------------------------------------------------------------
// Divider block
// ---------------------------------------------------------------------------
test.describe('Divider block — dynamic render', () => {
	test('fresh icon-style insert is valid and renders inline SVG on the frontend', async ({
		page,
	}, testInfo) => {
		const artifact = defineArtifact(
			testInfo,
			'blocks',
			'divider',
			'dynamic-render'
		);

		await createNewPost(page, 'page');
		await setPostTitle(page, 'Divider Dynamic Render');
		await insertBlock(page, 'designsetgo/divider', {
			dividerStyle: 'icon',
			iconName: 'heart',
		});

		const canvas = getEditorCanvas(page);
		await expect(
			canvas.locator('[data-type="designsetgo/divider"]').first()
		).toBeVisible({ timeout: 10000 });

		expect(await getInvalidBlockNames(page)).not.toContain(
			'designsetgo/divider'
		);
		await expect(
			canvas.locator(
				'[data-type="designsetgo/divider"] .block-editor-warning'
			)
		).toHaveCount(0);
		await saveScreenshot(page, artifact, 'editor');

		const frontendUrl = await publishAndResolveUrl(page);
		await page.goto(frontendUrl);
		await page.waitForLoadState('domcontentloaded');

		await expect(
			page.locator('.wp-block-designsetgo-divider svg').first()
		).toBeVisible({ timeout: 10000 });
		expect(
			await page
				.locator('.wp-block-designsetgo-divider .dsgo-lazy-icon')
				.count()
		).toBe(0);

		await page.waitForLoadState('networkidle').catch(() => {});
		await slowScrollToBottom(page);
		await saveScreenshot(page, artifact, 'frontend');
	});

	test('legacy static markup migrates silently (no invalid content)', async ({
		page,
	}) => {
		await createNewPost(page, 'page');
		await setPostTitle(page, 'Divider Legacy Migration');
		await insertRawMarkup(page, LEGACY_DIVIDER);

		expect(await getInvalidBlockNames(page)).not.toContain(
			'designsetgo/divider'
		);
	});
});
