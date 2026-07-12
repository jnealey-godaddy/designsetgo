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
 *
 * A second, narrower class of the same bug only shows up NESTED inside a
 * Section/Row/Grid: src/styles/_utilities.scss has its own, separate set of
 * "shrink these inline-ish blocks to fit-content" rules for children of
 * `.dsgo-stack__inner` / `.dsgo-flex__inner`, which — if it lists these four
 * blocks' root class — reapplies the exact `width: fit-content !important`
 * shrink-wrap `_justification.scss` forbids, independent of anything at the
 * top level. Every case below therefore runs three times: at the top level,
 * nested one level inside `designsetgo/section`, and nested one level inside
 * `designsetgo/row`.
 */

const { test, expect } = require('@playwright/test');
const {
	createNewPost,
	getEditorCanvas,
	getInvalidBlockNames,
} = require('./helpers/wordpress');
const { insertBlockByName, insertNestedBlockByName } = require('./helpers/blocks');
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

// Containers to nest the block under test inside, in addition to the top
// level. `null` means "top level, directly in post content" (the original
// coverage).
//
// `strictEdges` distinguishes two fundamentally different container models:
//
// - Top level and Section (`.dsgo-stack__inner`, a COLUMN-direction flex
//   layout) both give the block's `.dsgo-justify` wrapper the full available
//   cross-axis width, so `justification` positions the visible element at an
//   exact, predictable edge — same assertions as top level.
// - Row (`.dsgo-flex__inner`, a ROW-direction flex layout) deliberately does
//   NOT stretch a lone child on its main axis — that's what lets multiple
//   Icon Buttons/Pills sit side by side at their natural width (see
//   CHANGELOG: "use a Row block to place them side by side intentionally").
//   A single child's own `justification` therefore has no free space to
//   move within; positioning a group of Row children is Row's OWN
//   `layout.justifyContent`, not each child's `justification`. Row still
//   gets `constrainWidth: true` explicitly (its block.json default is
//   `false`, unlike Section) so its own bounds are well-defined, and the
//   meaningful invariant to assert there is CONTAINMENT — the block must
//   never escape past the row's own bounds into the full-width container,
//   which is the actual regression class this spec guards against.
const CONTAINERS = [
	{ label: 'top level', parentName: null, strictEdges: true },
	{
		label: 'nested in designsetgo/section',
		parentName: 'designsetgo/section',
		strictEdges: true,
	},
	{
		label: 'nested in designsetgo/row',
		parentName: 'designsetgo/row',
		parentAttributes: { constrainWidth: true },
		strictEdges: false,
	},
];

/**
 * Publish a new page containing a reference `core/paragraph` followed by the
 * block under test — either at the top level, or nested one level inside a
 * container block (Section/Row) — then measure the frontend bounding boxes
 * of the paragraph's content column and the block's visible element.
 *
 * @param {import('@playwright/test').Page} page               - Playwright page.
 * @param {Object}                          block              - Entry from BLOCKS.
 * @param {string}                          justification      - 'left' | 'center' | 'right'.
 * @param {string|null}                     [parentName]       - Optional container block name to nest inside.
 * @param {object}                          [parentAttributes] - Optional explicit attributes for the parent container.
 * @return {Promise<{columnRect: {left: number, right: number}, blockRect: {left: number, right: number}}>}
 *         Left/right edges of the content column and the block's visible element.
 */
async function measureJustifiedBlock(
	page,
	block,
	justification,
	parentName = null,
	parentAttributes = {}
) {
	await createNewPost(page, 'page');
	await setPostTitle(
		page,
		`Justification: ${block.name.split('/').pop()} (${justification}) ${parentName ? `in ${parentName}` : 'top level'}`
	);

	await insertBlockByName(page, 'core/paragraph', {
		attributes: { content: 'Reference paragraph' },
	});

	const attributes = { ...block.attributes, justification };
	const { clientId } = parentName
		? await insertNestedBlockByName(
				page,
				parentName,
				block.name,
				{ attributes },
				parentAttributes
			).then((r) => ({ clientId: r.childClientId }))
		: await insertBlockByName(page, block.name, { attributes });

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
		for (const container of CONTAINERS) {
			test(`${block.name} left-justified sits on the content column, not the container edge (${container.label})`, async ({
				page,
			}) => {
				const { columnRect, blockRect } = await measureJustifiedBlock(
					page,
					block,
					'left',
					container.parentName,
					container.parentAttributes
				);

				// "Left-justified" means the left edge of the content column — the
				// same x as a normal paragraph in the same post content — NOT the
				// left edge of the full-width container. Before the fix this was
				// 50 (container padding edge) vs 206 (content column). This holds
				// for every container: `justify-content: flex-start` is the
				// default even where a lone child can't be pushed elsewhere (Row).
				expect(
					Math.abs(blockRect.left - columnRect.left)
				).toBeLessThan(TOLERANCE);
			});

			test(`${block.name} right-justified sits on the content column's right edge (${container.label})`, async ({
				page,
			}) => {
				const { columnRect, blockRect } = await measureJustifiedBlock(
					page,
					block,
					'right',
					container.parentName,
					container.parentAttributes
				);

				if (container.strictEdges) {
					// Mirrors the left-justified assertion: `alignright` was
					// excluded from core's content-size cap exactly like
					// `alignleft`, so the right edge must land on the column's
					// right edge, not the container's.
					expect(
						Math.abs(blockRect.right - columnRect.right)
					).toBeLessThan(TOLERANCE);
					expect(blockRect.left).toBeGreaterThanOrEqual(
						columnRect.left - TOLERANCE
					);
				} else {
					// Row: a lone child has no free main-axis space to move
					// into (see CONTAINERS comment above), so `justification`
					// has no visible effect here — the meaningful invariant is
					// that it still stays inside the row's own bounds instead
					// of escaping past them.
					expect(blockRect.left).toBeGreaterThanOrEqual(
						columnRect.left - TOLERANCE
					);
					expect(blockRect.right).toBeLessThanOrEqual(
						columnRect.right + TOLERANCE
					);
				}
			});

			test(`${block.name} center-justified stays inside the content column, not merely centered on the full-width container (${container.label})`, async ({
				page,
			}) => {
				const { columnRect, blockRect } = await measureJustifiedBlock(
					page,
					block,
					'center',
					container.parentName,
					container.parentAttributes
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
	}
});
