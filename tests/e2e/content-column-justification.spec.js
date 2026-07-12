/**
 * E2E regression guard: content-column positioning for justified blocks.
 *
 * The bug: designsetgo/pill, designsetgo/icon, designsetgo/icon-button and
 * designsetgo/modal-trigger used WordPress's `align: left|center|right`
 * support for horizontal positioning. Core's constrained layout explicitly
 * EXCLUDES aligned blocks from the content-size cap
 * (`> :where(:not(.alignleft):not(.alignright):not(.alignfull))` in
 * wp-includes/block-supports/layout.php), so an aligned block gets
 * `max-width: none` and pins itself to the full-width container's padding
 * edge instead of the theme's content column. Measured before the fix: a
 * left-aligned block sat at x=50 while the content column started at x=206.
 *
 * The fix: each block's root is now a block-level "justification wrapper"
 * (`.dsgo-justify`, block.json-registered `justification` attribute) that
 * core's constrained layout caps at content width, exactly like a paragraph.
 * The visible element shrink-wraps inside that wrapper and is positioned with
 * `justify-content` (left | center | right).
 *
 * Unit and PHPUnit tests only prove the rendered markup; they cannot prove
 * where the browser actually paints it. This spec asserts real
 * getBoundingClientRect() positions against a `core/paragraph` published in
 * the same post content, so a regression that reintroduces a shrink-wrapped
 * or aligned wrapper is caught even though the markup-level tests would still
 * pass.
 */

const { test, expect } = require('@playwright/test');
const {
	createNewPost,
	getEditorCanvas,
	getInvalidBlockNames,
} = require('./helpers/wordpress');
const { insertBlockByName } = require('./helpers/blocks');
const {
	installVideoCapture,
	installPublishedPageCleanup,
	setPostTitle,
	publishAndResolveUrl,
} = require('./helpers/artifacts');

// Record a video per test when DSGO_RECORD_VIDEO=1 (screenshots only by default).
installVideoCapture(test);
// Delete each published page after the test so pages do not pile up.
installPublishedPageCleanup(test);

// Sub-pixel rounding tolerance for getBoundingClientRect() comparisons.
const TOLERANCE = 2;

// Blocks fixed by the justification-wrapper change, the attributes each needs
// to render real content, and the selector for the VISIBLE (shrink-wrapped)
// element — not the block-level positioning wrapper, which is what the
// original bug misplaced.
const BLOCKS = [
	{
		name: 'designsetgo/pill',
		attributes: { content: 'Hi' },
		selector: '.dsgo-pill__content',
	},
	{
		name: 'designsetgo/icon',
		attributes: { icon: 'star' },
		selector: '.dsgo-icon__wrapper',
	},
	{
		name: 'designsetgo/icon-button',
		attributes: { text: 'Go' },
		selector: '.dsgo-icon-button',
	},
	{
		name: 'designsetgo/modal-trigger',
		attributes: { text: 'Open', targetModalId: 'test-modal' },
		selector: '.dsgo-modal-trigger',
	},
];

/**
 * Publish a new page containing a reference `core/paragraph` followed by the
 * block under test (top level — NOT inside a Section, whose inner div is
 * already capped to content width and would mask the bug), then measure the
 * frontend bounding boxes of the paragraph's content column and the block's
 * visible element.
 *
 * @param {import('@playwright/test').Page} page          - Playwright page.
 * @param {Object}                          block         - Entry from BLOCKS.
 * @param {string}                          justification - 'left' | 'center' | 'right'.
 * @return {Promise<{columnRect: {left: number, right: number}, blockRect: {left: number, right: number}}>}
 *         Left/right edges of the content column and the block's visible element.
 */
async function measureJustifiedBlock(page, block, justification) {
	await createNewPost(page, 'page');
	await setPostTitle(
		page,
		`Justification: ${block.name.split('/').pop()} (${justification})`
	);

	await insertBlockByName(page, 'core/paragraph', {
		attributes: { content: 'Reference paragraph' },
	});
	const { clientId } = await insertBlockByName(page, block.name, {
		attributes: { ...block.attributes, justification },
	});

	// Wait for the inserted block to appear in the canvas — a real DOM signal
	// that React has finished rendering, rather than a fixed timeout.
	const canvas = getEditorCanvas(page);
	await canvas
		.locator(`[data-block="${clientId}"]`)
		.first()
		.waitFor({ state: 'attached', timeout: 10000 });

	expect(await getInvalidBlockNames(page)).toEqual([]);

	const frontendUrl = await publishAndResolveUrl(page);
	await page.goto(frontendUrl);
	await page.waitForLoadState('domcontentloaded');

	const columnRect = await page
		.locator('.wp-block-post-content > p')
		.first()
		.evaluate((el) => {
			const rect = el.getBoundingClientRect();
			return { left: rect.left, right: rect.right };
		});

	const blockRect = await page
		.locator(block.selector)
		.first()
		.evaluate((el) => {
			const rect = el.getBoundingClientRect();
			return { left: rect.left, right: rect.right };
		});

	return { columnRect, blockRect };
}

test.describe('Content-column justification — layout regression guard', () => {
	for (const block of BLOCKS) {
		test(`${block.name} left-justified sits on the content column, not the container edge`, async ({
			page,
		}) => {
			const { columnRect, blockRect } = await measureJustifiedBlock(
				page,
				block,
				'left'
			);

			// "Left-justified" means the left edge of the content column — the
			// same x as a normal paragraph in the same post content — NOT the
			// left edge of the full-width container. Before the fix this was
			// 50 (container padding edge) vs 206 (content column).
			expect(Math.abs(blockRect.left - columnRect.left)).toBeLessThan(
				TOLERANCE
			);
		});

		test(`${block.name} right-justified sits on the content column's right edge`, async ({
			page,
		}) => {
			const { columnRect, blockRect } = await measureJustifiedBlock(
				page,
				block,
				'right'
			);

			// Mirrors the left-justified assertion: `alignright` was excluded
			// from core's content-size cap exactly like `alignleft`, so the
			// right edge must land on the column's right edge, not the
			// container's.
			expect(Math.abs(blockRect.right - columnRect.right)).toBeLessThan(
				TOLERANCE
			);
			expect(blockRect.left).toBeGreaterThanOrEqual(
				columnRect.left - TOLERANCE
			);
		});

		test(`${block.name} center-justified stays inside the content column, not merely centered on the full-width container`, async ({
			page,
		}) => {
			const { columnRect, blockRect } = await measureJustifiedBlock(
				page,
				block,
				'center'
			);

			// A shrink-wrapped box centered on the full-width container can
			// still LOOK centered — the container and the content column
			// usually share the same center point — so centering alone can't
			// tell the two boxes apart. Containment can: if the wrapper lost
			// its content-size cap, a wide enough element would spill past
			// the column's edges even while still appearing centered.
			expect(blockRect.left).toBeGreaterThanOrEqual(
				columnRect.left - TOLERANCE
			);
			expect(blockRect.right).toBeLessThanOrEqual(
				columnRect.right + TOLERANCE
			);
		});
	}
});
