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
const { cli, shellArg, deletePostIds } = require('./helpers/wp-cli');

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
 * of the relevant positioning context and the block's visible element.
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

	// A nested Section owns its own horizontal content edge (including its
	// responsive padding), and a Row owns its own bounds. Compare the visible
	// element with that actual positioning context rather than a paragraph
	// outside the container, which is a different layout context on mobile.
	const positioningContextSelector =
		parentName === 'designsetgo/section'
			? '.wp-block-designsetgo-section > .dsgo-stack__inner'
			: parentName === 'designsetgo/row'
				? '.wp-block-designsetgo-row > .dsgo-flex__inner'
				: '.wp-block-post-content > p';
	const columnRect = await page
		.locator(positioningContextSelector)
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

/**
 * Height (in px) of a `core/spacer` used to force a tall Grid row. Explicit
 * `height` on a grid item is a definite size, so CSS Grid's `align-self:
 * stretch` cannot override it and `grid-auto-rows: auto` sizes the row to
 * fit it — a stable, alignItems-independent "row height" reference. Every
 * justified block's own natural height (well under 100px) sits nowhere near
 * this, so a generous 60% threshold below comfortably separates "sized to
 * content" from "stretched to the row."
 */
const GRID_SPACER_HEIGHT = 320;

/**
 * Publish a page with a two-column `designsetgo/grid` (`alignItems` as
 * given) whose first cell is a `core/spacer` (forces the row to
 * `GRID_SPACER_HEIGHT`) and second cell is the block under test, then
 * measure both cells' rendered bounding boxes on the frontend.
 *
 * The spacer has an explicit height, so it has no free space of its own to
 * move within the row — its rect's top/bottom double as the row's own
 * vertical bounds, regardless of `alignItems`, making it a reliable
 * position anchor as well as a height anchor.
 *
 * Guards CRITICAL A: `.dsgo-grid__inner > .dsgo-justify` used to set
 * `align-self: stretch` — in a CSS GRID (unlike flexbox) `align-self` is the
 * BLOCK (vertical) axis, not the inline axis, so it stretched every
 * justified block's wrapper to the full row height AND silently overrode
 * the Grid's own author-set `alignItems` (an item's own `align-self` wins
 * over the container's `align-items`). Because the wrapper's shrink-wrapped
 * child is still centred inside it (`.dsgo-justify { align-items: center }`),
 * a height-only assertion at a single `alignItems` value can't tell "sized
 * to content and correctly positioned" apart from "stretched full-height,
 * but the visible child happens to still measure short" — only checking
 * where the child sits within the row, at `alignItems` values other than
 * `center`, can.
 *
 * @param {import('@playwright/test').Page} page        - Playwright page.
 * @param {Object}                          block       - Entry from BLOCKS.
 * @param {string}                          alignItems  - Grid `alignItems` value to test.
 * @return {Promise<{spacerRect: {top: number, bottom: number, height: number}, blockRect: {top: number, bottom: number, height: number}}>}
 *         Rendered bounding boxes of the spacer (row anchor) and the block's visible element.
 */
async function measureGridAlignItems(page, block, alignItems) {
	await createNewPost(page, 'page');
	await setPostTitle(
		page,
		`Grid alignItems=${alignItems}: ${block.name.split('/').pop()}`
	);

	const { clientId } = await page.evaluate(
		({ blockName, attrs, alignItemsValue, spacerHeight }) => {
			const { createBlock } = wp.blocks;
			const dispatch = wp.data.dispatch('core/block-editor');

			const spacerBlock = createBlock('core/spacer', {
				height: `${spacerHeight}px`,
			});
			const testBlock = createBlock(blockName, attrs);
			const gridBlock = createBlock(
				'designsetgo/grid',
				// This test needs both items in the same grid row at every viewport
				// to measure cross-axis alignment. The Grid's intentional mobile
				// default is one column, so pin the fixture to two columns here.
				{
					alignItems: alignItemsValue,
					desktopColumns: 2,
					tabletColumns: 2,
					mobileColumns: 2,
				},
				[spacerBlock, testBlock]
			);

			dispatch.insertBlocks(gridBlock);

			return { clientId: testBlock.clientId };
		},
		{
			blockName: block.name,
			attrs: { ...block.attributes, justification: 'center' },
			alignItemsValue: alignItems,
			spacerHeight: GRID_SPACER_HEIGHT,
		}
	);

	const canvas = getEditorCanvas(page);
	await canvas
		.locator(`[data-block="${clientId}"]`)
		.first()
		.waitFor({ state: 'attached', timeout: 10000 });

	expect(await getInvalidBlockNames(page)).toEqual([]);

	const frontendUrl = await publishAndResolveUrl(page);
	await page.goto(frontendUrl);
	await page.waitForLoadState('domcontentloaded');

	const spacerRect = await page
		.locator('.wp-block-spacer')
		.first()
		.evaluate((el) => {
			const rect = el.getBoundingClientRect();
			return { top: rect.top, bottom: rect.bottom, height: rect.height };
		});

	const blockRect = await page
		.locator(block.selector)
		.first()
		.evaluate((el) => {
			const rect = el.getBoundingClientRect();
			return { top: rect.top, bottom: rect.bottom, height: rect.height };
		});

	return { spacerRect, blockRect };
}

/**
 * Grid `alignItems` values under test, each paired with where the visible
 * block element is expected to sit relative to the spacer-anchored row.
 *
 * `center` alone cannot distinguish "correctly centred" from "stretched to
 * the full row, then centred inside itself" — both look identical measured
 * from the middle. `start`/`end` are what actually falsifies the stretch
 * bug: a wrapper pinned to `align-self: stretch` has no free space left to
 * honour a per-item edge alignment, so it collapses every `alignItems`
 * value to the same "centred on the row" result instead of sitting flush
 * with the top or bottom edge.
 */
const GRID_ALIGN_ITEMS_CASES = [
	{ alignItems: 'start', edge: 'top' },
	{ alignItems: 'center', edge: 'center' },
	{ alignItems: 'end', edge: 'bottom' },
];

test.describe('Content-column justification — Grid alignItems regression guard (Critical A)', () => {
	for (const block of BLOCKS) {
		for (const { alignItems, edge } of GRID_ALIGN_ITEMS_CASES) {
			test(`${block.name} respects Grid alignItems="${alignItems}" (vertical position within the row, not just height)`, async ({
				page,
			}) => {
				const { spacerRect, blockRect } = await measureGridAlignItems(
					page,
					block,
					alignItems
				);

				// Sanity check the reference itself actually forced a tall row —
				// otherwise a false pass could hide a broken fixture rather than
				// a fixed bug.
				expect(spacerRect.height).toBeGreaterThanOrEqual(
					GRID_SPACER_HEIGHT - TOLERANCE
				);

				// Before the fix this was ~spacerRect.height (e.g. 339px for a
				// 339px row) — the visible element stretched to fill the row
				// instead of sizing to its own content and being positioned by
				// the Grid's alignItems.
				expect(blockRect.height).toBeLessThan(spacerRect.height * 0.6);

				// Before the fix, `align-self: stretch` on the wrapper claimed
				// the row's full block-axis space regardless of `alignItems`,
				// so the shrink-wrapped visible child — centred inside the now
				// full-height wrapper by `.dsgo-justify`'s own
				// `align-items: center` — landed on the row's vertical centre
				// for every `alignItems` value instead of the edge the author
				// asked for.
				if (edge === 'top') {
					expect(
						Math.abs(blockRect.top - spacerRect.top)
					).toBeLessThan(TOLERANCE);
				} else if (edge === 'bottom') {
					expect(
						Math.abs(blockRect.bottom - spacerRect.bottom)
					).toBeLessThan(TOLERANCE);
				} else {
					const blockCenter = (blockRect.top + blockRect.bottom) / 2;
					const spacerCenter =
						(spacerRect.top + spacerRect.bottom) / 2;
					expect(
						Math.abs(blockCenter - spacerCenter)
					).toBeLessThan(TOLERANCE);
				}
			});
		}
	}
});

test.describe('Content-column justification — legacy align regression guard (Critical B)', () => {
	// Un-migrated stored markup is created directly via WP-CLI (bypassing the
	// block editor entirely) so the raw `align` attribute survives exactly as
	// legacy content would have it — the editor's live block type would never
	// let us author this combination today. Tracked and cleaned up manually
	// since these pages never pass through publishAndResolveUrl().
	const pageIds = [];

	test.afterEach(() => {
		if (pageIds.length) {
			deletePostIds(pageIds.splice(0, pageIds.length));
		}
	});

	for (const align of ['left', 'right']) {
		test(`un-migrated Icon with stored align:"${align}" inside a Section sits on the content column's ${align} edge`, async ({
			page,
		}) => {
			// Icon is a dynamic block (save() returns null), so a stored
			// instance serializes as a single self-closing comment — exactly
			// what pre-2.4.0 content still has: `align` present, no
			// `justification` key.
			const markup = [
				'<!-- wp:paragraph -->',
				'<p>Reference paragraph</p>',
				'<!-- /wp:paragraph -->',
				'',
				'<!-- wp:designsetgo/section -->',
				'<div class="wp-block-designsetgo-section dsgo-stack"><div class="dsgo-stack__inner">',
				`<!-- wp:designsetgo/icon {"icon":"star","align":"${align}"} /-->`,
				'</div></div>',
				'<!-- /wp:designsetgo/section -->',
			].join('\n');

			const out = cli(
				'wp post create --post_type=page --post_status=publish --porcelain ' +
					`--post_title=${shellArg(`Legacy Icon align=${align} in Section E2E`)} ` +
					`--post_content=${shellArg(markup)}`
			);
			const pageId = (out.match(/\d+/) || [])[0];
			expect(pageId, 'created page id').toBeTruthy();
			pageIds.push(pageId);

			await page.goto(`/?page_id=${pageId}`);
			await page.waitForLoadState('domcontentloaded');

			const columnRect = await page
				.locator('.wp-block-post-content > p')
				.first()
				.evaluate((el) => {
					const rect = el.getBoundingClientRect();
					return { left: rect.left, right: rect.right };
				});

			const iconRect = await page
				.locator('.dsgo-icon__wrapper')
				.first()
				.evaluate((el) => {
					const rect = el.getBoundingClientRect();
					return { left: rect.left, right: rect.right };
				});

			// Before the fix, core's own `margin-left/right: auto` (from the
			// stale alignleft/alignright class render.php unavoidably emitted
			// alongside .dsgo-justify) overrode the wrapper's flexbox
			// `justify-content`, centring BOTH left- and right-aligned icons
			// at the same x position regardless of `align`.
			if (align === 'left') {
				expect(Math.abs(iconRect.left - columnRect.left)).toBeLessThan(
					TOLERANCE
				);
			} else {
				expect(
					Math.abs(iconRect.right - columnRect.right)
				).toBeLessThan(TOLERANCE);
			}
		});
	}
});
