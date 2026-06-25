/**
 * WordPress Helper Functions for Playwright Tests
 *
 * Utilities for interacting with WordPress admin and block editor.
 * Compatible with WordPress 6.4+ which renders the editor inside an iframe.
 */

/**
 * Get the editor canvas frame locator.
 * WordPress 6.4+ renders block content inside an iframe named "editor-canvas".
 *
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @return {import('@playwright/test').FrameLocator} Frame locator for the editor canvas
 */
function getEditorCanvas(page) {
	return page.frameLocator('iframe[name="editor-canvas"]');
}

/**
 * Create a new post in the block editor
 *
 * @param {import('@playwright/test').Page} page     - Playwright page object
 * @param {string}                          postType - Post type (post, page, etc.)
 */
async function createNewPost(page, postType = 'post') {
	await page.goto(`/wp-admin/post-new.php?post_type=${postType}`);

	// WordPress 6.4+ renders the block editor inside an iframe.
	// Wait for the editor canvas iframe to load.
	await page.locator('iframe[name="editor-canvas"]').waitFor({
		timeout: 30000,
	});

	// Ensure the content inside the iframe is ready
	const canvas = getEditorCanvas(page);
	await canvas.locator('body').waitFor({ timeout: 15000 });

	// Close any modal dialogs (welcome guide, etc.)
	// These are rendered in the top-level page, not inside the iframe.
	try {
		const closeButton = page.locator(
			'.components-modal__header button[aria-label="Close"]'
		);
		await closeButton.first().click({ timeout: 2000 });
	} catch {
		// No dialog to close
	}
}

/**
 * Insert a block by name
 *
 * @param {import('@playwright/test').Page} page      - Playwright page object
 * @param {string}                          blockName - Block name (e.g., 'core/paragraph', 'core/group')
 */
async function insertBlock(page, blockName) {
	const blockSlug = blockName.split('/').pop();
	const blockLabel = blockSlug.charAt(0).toUpperCase() + blockSlug.slice(1);

	// Open the global block inserter via the toolbar toggle.
	// Use the CSS class to uniquely target the toolbar toggle button.
	// The aria-label varies across WP versions ("Toggle block inserter"
	// in WP ≤6.7, "Block Inserter" in WP 6.8+) and a regex match on
	// /block inserter/i also catches the "Close Block Inserter" sidebar
	// button, causing a strict-mode violation.
	const inserterToggle = page.locator(
		'button.editor-document-tools__inserter-toggle'
	);

	// Only open the inserter if it isn't already open
	const isAlreadyOpen =
		(await inserterToggle.getAttribute('aria-pressed')) === 'true';
	if (!isAlreadyOpen) {
		await inserterToggle.click();
	}

	// Wait for the inserter panel to appear and search for the block
	const searchInput = page
		.locator(
			'.block-editor-inserter__search input, .components-search-control__input'
		)
		.first();
	await searchInput.waitFor();
	await searchInput.fill(blockLabel);

	// Wait for search results to update
	await page.waitForTimeout(500);

	// Click the first matching block type item
	await page.locator('.block-editor-block-types-list__item').first().click();

	// Wait for the block to be inserted
	await page.waitForTimeout(500);

	// Close the inserter if still open
	const isPressed = await inserterToggle.getAttribute('aria-pressed');
	if (isPressed === 'true') {
		await inserterToggle.click();
	}
}

/**
 * Open block settings sidebar
 *
 * @param {import('@playwright/test').Page} page - Playwright page object
 */
async function openBlockSettings(page) {
	const settingsButton = page
		.locator('button[aria-label="Settings"]')
		.first();

	// Check if settings sidebar is already open
	const sidebar = page.locator('.editor-sidebar, .edit-post-sidebar');
	const isOpen = await sidebar
		.first()
		.isVisible()
		.catch(() => false);

	if (!isOpen) {
		await settingsButton.click();
		await sidebar.first().waitFor();
	}

	// Ensure the Block tab is active (not the Post/Page tab)
	const blockTab = page.getByRole('tab', { name: /block/i });
	if (await blockTab.isVisible().catch(() => false)) {
		const isSelected = await blockTab.getAttribute('aria-selected');
		if (isSelected !== 'true') {
			await blockTab.click();
		}
	}
}

/**
 * Save post/page
 *
 * @param {import('@playwright/test').Page} page - Playwright page object
 */
async function savePost(page) {
	// Click save button
	await page
		.locator(
			'button.editor-post-save-draft, button.editor-post-publish-button__button'
		)
		.first()
		.click();

	// Wait for save to complete
	await page.locator('.editor-post-saved-state.is-saved').waitFor({
		timeout: 30000,
	});
}

/**
 * Publish post/page
 *
 * @param {import('@playwright/test').Page} page - Playwright page object
 */
async function publishPost(page) {
	// Click publish button
	await page.locator('.editor-post-publish-panel__toggle').first().click();

	// Wait for publish panel
	await page.locator('.editor-post-publish-panel').waitFor();

	// Click final publish button
	await page
		.locator('.editor-post-publish-panel .editor-post-publish-button')
		.first()
		.click();

	// Wait for the post-publish panel or snackbar confirmation
	await page
		.locator(
			'.editor-post-publish-panel__postpublish, .components-snackbar'
		)
		.first()
		.waitFor({ timeout: 30000 });
}

/**
 * Get the frontend URL of the current post
 *
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @return {Promise<string>} Frontend URL
 */
async function getFrontendUrl(page) {
	// After publishing, the post-publish panel contains a view link
	const postPublishPanel = page.locator(
		'.editor-post-publish-panel__postpublish'
	);

	if (await postPublishPanel.isVisible().catch(() => false)) {
		const viewLink = postPublishPanel
			.locator('a[href*="/?p="], a[href*="/?page_id="], a')
			.first();
		const href = await viewLink.getAttribute('href');
		if (href) {
			return href;
		}
	}

	// Fallback: construct URL from the current page's post ID
	const currentUrl = page.url();
	const match = currentUrl.match(/post=(\d+)/);
	if (match) {
		return `/?p=${match[1]}`;
	}

	throw new Error('Could not determine frontend URL');
}

/**
 * Select a block by type using the WordPress data store.
 *
 * Clicking on a container block's wrapper element in the editor iframe often
 * selects an inner block instead (the editor picks the innermost block at the
 * click position). Using `wp.data.dispatch` to select by clientId avoids this.
 *
 * @param {import('@playwright/test').Page} page      - Playwright page object
 * @param {string}                          blockType - Block type (e.g., 'core/group')
 * @param {number}                          index     - Index of the block (0-based)
 */
async function selectBlock(page, blockType, index = 0) {
	const selected = await page.evaluate(
		({ blockType: type, index: idx }) => {
			const { select, dispatch } = wp.data;

			function findBlocksByType(blocks, targetType) {
				const result = [];
				for (const block of blocks) {
					if (block.name === targetType) {
						result.push(block);
					}
					if (block.innerBlocks && block.innerBlocks.length > 0) {
						result.push(
							...findBlocksByType(block.innerBlocks, targetType)
						);
					}
				}
				return result;
			}

			const allBlocks = select('core/block-editor').getBlocks();
			const matching = findBlocksByType(allBlocks, type);

			if (matching[idx]) {
				dispatch('core/block-editor').selectBlock(
					matching[idx].clientId
				);
				return true;
			}
			return false;
		},
		{ blockType, index }
	);

	if (!selected) {
		throw new Error(`Block "${blockType}" at index ${index} not found`);
	}

	// Wait for the block to visually show as selected in the editor
	const canvas = getEditorCanvas(page);
	await canvas
		.locator(`[data-type="${blockType}"].is-selected`)
		.nth(index)
		.waitFor({ timeout: 5000 });
}

/**
 * Check if a block has a specific CSS class (single class name only).
 * The block content is inside the editor canvas iframe.
 *
 * First locates the Nth block of the given type, then polls for the class
 * to appear (up to 5 s), which handles the React re-render delay after
 * attribute changes trigger the editor.BlockListBlock filter.
 *
 * @param {import('@playwright/test').Page} page      - Playwright page object
 * @param {string}                          blockType - Block type (e.g., 'core/group')
 * @param {string}                          className - Single CSS class name to check
 * @param {number}                          index     - Index of the block (0-based)
 * @return {Promise<boolean>} True if the block has the class, false otherwise
 */
async function blockHasClass(page, blockType, className, index = 0) {
	const canvas = getEditorCanvas(page);
	const block = canvas.locator(`[data-type="${blockType}"]`).nth(index);

	const deadline = Date.now() + 5000;
	while (Date.now() < deadline) {
		const classes = await block.getAttribute('class');
		if (classes && classes.split(/\s+/).includes(className)) {
			return true;
		}
		await page.waitForTimeout(250);
	}
	return false;
}

/**
 * Insert a registered block pattern by its slug, programmatically.
 *
 * Reads the pattern's resolved `content` straight from the data store, parses
 * it into blocks, and inserts them. This avoids the inserter-UI search (which
 * is flaky: the Patterns tab label and the results list vary across WP
 * versions) and guarantees we exercise the exact content the loader
 * registered — including the placeholder-token replacement done in PHP.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @param {string}                          slug - Pattern slug, e.g. 'designsetgo/hero/hero-split'
 * @return {Promise<{found: boolean, hadToken: boolean, blockCount: number, clientId: ?string}>}
 *         Insertion result: whether the pattern was registered, whether its
 *         registered content still contained a raw placeholder token, how many
 *         top-level blocks were inserted, and the clientId of the first one
 *         (so callers can wait for it to render in the canvas).
 */
async function insertPatternBySlug(page, slug) {
	const result = await page.evaluate(async (patternSlug) => {
		// `core`.getBlockPatterns is resolver-backed (it fetches from the REST
		// block-patterns endpoint), so the first synchronous call returns [].
		// Await the resolver so the full registry is present before we look up
		// the slug; fall back to the editor settings list for older WP.
		let patterns = [];
		if (wp.data.resolveSelect('core').getBlockPatterns) {
			patterns = await wp.data.resolveSelect('core').getBlockPatterns();
		}
		if (!patterns || !patterns.length) {
			patterns =
				wp.data.select('core/block-editor').getSettings()
					.__experimentalBlockPatterns || [];
		}
		const pattern = patterns.find((p) => p.name === patternSlug);
		if (!pattern) {
			return { found: false, hadToken: false, blockCount: 0 };
		}

		const hadToken = pattern.content.includes('{{dsgo:placeholder-');
		const blocks = wp.blocks.parse(pattern.content);
		wp.data.dispatch('core/block-editor').insertBlocks(blocks);

		return {
			found: true,
			hadToken,
			blockCount: blocks.length,
			clientId: blocks[0] ? blocks[0].clientId : null,
		};
	}, slug);

	if (!result.found) {
		throw new Error(`Pattern "${slug}" is not registered`);
	}

	return result;
}

/**
 * Walk the editor's block tree and return the names of every block whose
 * `isValid` is false (i.e. its markup no longer matches its save() output, so
 * the editor would show an "Attempt Recovery" warning).
 *
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @return {Promise<string[]>} Unique block names that failed validation.
 */
async function getInvalidBlockNames(page) {
	return page.evaluate(() => {
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
		return Array.from(new Set(bad));
	});
}

module.exports = {
	getEditorCanvas,
	createNewPost,
	insertBlock,
	insertPatternBySlug,
	getInvalidBlockNames,
	openBlockSettings,
	savePost,
	publishPost,
	getFrontendUrl,
	selectBlock,
	blockHasClass,
};
