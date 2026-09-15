/**
 * E2E Tests for Group Block Enhancements
 *
 * Tests the DesignSetGo enhancements to the core Group block:
 * - Responsive visibility
 * - Clickable groups with links
 */

const { test, expect } = require('@playwright/test');
const {
	getEditorCanvas,
	createNewPost,
	insertBlock,
	openBlockSettings,
	savePost,
	publishPost,
	getFrontendUrl,
	selectBlock,
	blockHasClass,
} = require('./helpers/wordpress');

test.describe('Group Block - Responsive Grid', () => {
	// Grid Columns (Desktop/Tablet/Mobile) is a feature of the designsetgo/grid
	// block, not an extension on core/group. Skip until a group grid extension
	// is implemented.
	test.skip('should allow setting responsive grid columns', async ({
		page,
	}) => {
		await createNewPost(page, 'post');
		await insertBlock(page, 'core/group');
		await selectBlock(page, 'core/group');
		await page.click('[aria-label="Grid"]');
		await openBlockSettings(page);
		const gridPanel = page.locator('text=Grid Columns');
		await expect(gridPanel).toBeVisible();
	});

	test.skip('should hide WordPress column controls when DSG grid is active', async ({
		page,
	}) => {
		await createNewPost(page, 'post');
		await insertBlock(page, 'core/group');
		await selectBlock(page, 'core/group');
		await page.click('[aria-label="Grid"]');
		await openBlockSettings(page);
	});
});

test.describe('Group Block - Responsive Visibility', () => {
	test('should allow hiding group on specific devices', async ({ page }) => {
		// Create a new post
		await createNewPost(page, 'post');

		// Insert a Group block
		await insertBlock(page, 'core/group');

		// Select the group block
		await selectBlock(page, 'core/group');

		// Open block settings
		await openBlockSettings(page);

		// Look for Responsive Visibility panel
		const visibilityPanel = page.locator('text=Responsive Visibility');
		await expect(visibilityPanel).toBeVisible();

		// Open the panel
		await visibilityPanel.click();

		// Hide on mobile — ToggleControl renders as a checkbox with a <label>
		await page.getByLabel(/Hide on Mobile/i).check();

		// Verify the editor indicator class is applied
		// (dsgo-hide-mobile is only added on save/frontend via extraProps;
		// the editor uses dsgo-has-responsive-visibility + data-hidden-devices)
		const hasVisibilityClass = await blockHasClass(
			page,
			'core/group',
			'dsgo-has-responsive-visibility'
		);
		expect(hasVisibilityClass).toBeTruthy();

		// Verify the hidden-devices data attribute includes Mobile
		const canvas = getEditorCanvas(page);
		const block = canvas.locator('[data-type="core/group"]').first();
		const hiddenDevices = await block.getAttribute('data-hidden-devices');
		expect(hiddenDevices).toContain('M');

		// Save the post
		await savePost(page);
	});

	test('should work with multiple visibility options', async ({ page }) => {
		// Create a new post
		await createNewPost(page, 'post');

		// Insert a Group block
		await insertBlock(page, 'core/group');

		// Select the group block
		await selectBlock(page, 'core/group');

		// Open block settings
		await openBlockSettings(page);

		// Open visibility panel
		await page.click('text=Responsive Visibility');

		// Hide on tablet and mobile — ToggleControl renders as a checkbox
		await page.getByLabel(/Hide on Tablet/i).check();
		await page.getByLabel(/Hide on Mobile/i).check();

		// Verify the editor indicator class is applied
		const hasVisibilityClass = await blockHasClass(
			page,
			'core/group',
			'dsgo-has-responsive-visibility'
		);
		expect(hasVisibilityClass).toBeTruthy();

		// Verify both devices appear in the data attribute
		const canvas = getEditorCanvas(page);
		const block = canvas.locator('[data-type="core/group"]').first();
		const hiddenDevices = await block.getAttribute('data-hidden-devices');
		expect(hiddenDevices).toContain('T');
		expect(hiddenDevices).toContain('M');
	});
});

test.describe('Group Block - Clickable with Link', () => {
	test('should allow making group clickable', async ({ page }) => {
		// Create a new post
		await createNewPost(page, 'post');

		// Insert a Group block
		await insertBlock(page, 'core/group');

		// Add content so the group has visible dimensions on the frontend
		await page.evaluate(() => {
			const { dispatch, select } = wp.data;
			const blocks = select('core/block-editor').getBlocks();
			const groupBlock = blocks.find((b) => b.name === 'core/group');
			if (groupBlock) {
				const p = wp.blocks.createBlock('core/paragraph', {
					content: 'Clickable group content',
				});
				dispatch('core/block-editor').insertBlocks(
					[p],
					0,
					groupBlock.clientId
				);
			}
		});
		await page.waitForTimeout(500);

		// Select the group block (not the inner paragraph)
		await selectBlock(page, 'core/group');

		// Open block settings
		await openBlockSettings(page);

		// Look for Link Settings panel
		const linkPanel = page.locator('text=Link Settings');
		await expect(linkPanel).toBeVisible();

		// Open the panel
		await linkPanel.click();

		// Enter a URL — no separate "enable" toggle; URL field is always visible
		const urlInput = page.locator('input[placeholder*="https://"]');
		await urlInput.fill('https://example.com');

		// Wait for "Open in new tab" toggle (conditionally rendered when URL is set)
		const newTabToggle = page.getByLabel(/Open in new tab/i);
		await expect(newTabToggle).toBeVisible();
		await newTabToggle.check();

		// Save the post
		await savePost(page);

		// Publish the post
		await publishPost(page);

		// Get frontend URL
		const previewUrl = await getFrontendUrl(page);

		// Visit the frontend
		await page.goto(previewUrl);

		// Verify the group has the link attributes (class is dsgo-clickable)
		const clickableGroup = page.locator('.wp-block-group.dsgo-clickable');
		await expect(clickableGroup).toBeVisible();

		const linkUrl = await clickableGroup.getAttribute('data-link-url');
		expect(linkUrl).toBe('https://example.com');

		const linkTarget =
			await clickableGroup.getAttribute('data-link-target');
		expect(linkTarget).toBe('_blank');
	});
});

test.describe('Group Block - Overlay', () => {
	// Overlay is not implemented as an extension on core/group.
	// Individual blocks (section, card, slide) have their own overlay support.
	test.skip('should allow adding overlay to group', async ({ page }) => {
		await createNewPost(page, 'post');
		await insertBlock(page, 'core/group');
		await selectBlock(page, 'core/group');
		await openBlockSettings(page);
	});

	test.skip('should apply white text color with overlay', async ({
		page,
	}) => {
		await createNewPost(page, 'post');
	});
});

test.describe('Group Block - Frontend Behavior', () => {
	test('should apply responsive visibility classes on frontend', async ({
		page,
		context,
	}) => {
		// Create a new post
		await createNewPost(page, 'post');

		// Insert a Group block
		await insertBlock(page, 'core/group');

		// Add content so the group has visible dimensions on the frontend
		await page.evaluate(() => {
			const { dispatch, select } = wp.data;
			const blocks = select('core/block-editor').getBlocks();
			const groupBlock = blocks.find((b) => b.name === 'core/group');
			if (groupBlock) {
				const p = wp.blocks.createBlock('core/paragraph', {
					content: 'Visibility test content',
				});
				dispatch('core/block-editor').insertBlocks(
					[p],
					0,
					groupBlock.clientId
				);
			}
		});
		await page.waitForTimeout(500);

		// Select the group block (not the inner paragraph)
		await selectBlock(page, 'core/group');

		// Set to hide on mobile
		await openBlockSettings(page);
		await page.click('text=Responsive Visibility');
		await page.getByLabel(/Hide on Mobile/i).check();

		// Save and publish
		await savePost(page);
		await publishPost(page);

		// Get frontend URL
		const previewUrl = await getFrontendUrl(page);

		// Set desktop dimensions explicitly, including in mobile projects.
		await page.setViewportSize({ width: 1280, height: 720 });
		await page.goto(previewUrl);
		const desktopGroup = page.locator('.wp-block-group.dsgo-hide-mobile');
		await expect(desktopGroup).toHaveCount(1);
		// Verify CSS does NOT hide the element on desktop
		const desktopDisplay = await desktopGroup.evaluate(
			(el) => getComputedStyle(el).display
		);
		expect(desktopDisplay).not.toBe('none');

		// Create a new page with mobile viewport
		const mobilePage = await context.newPage();
		await mobilePage.setViewportSize({ width: 375, height: 667 });
		await mobilePage.goto(previewUrl);

		// Group should be hidden on mobile via CSS media query
		const mobileGroup = mobilePage.locator(
			'.wp-block-group.dsgo-hide-mobile'
		);
		await expect(mobileGroup).toHaveCount(1);
		const mobileDisplay = await mobileGroup.evaluate(
			(el) => getComputedStyle(el).display
		);
		expect(mobileDisplay).toBe('none');

		await mobilePage.close();
	});
});
