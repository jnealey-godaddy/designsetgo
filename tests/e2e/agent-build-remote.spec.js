/**
 * Remote flow end to end.
 *
 * Proves the whole remote-agent build path against a real WordPress: a
 * `designsetgo/build-page` REST call parks a pending tree, the post is
 * opened at `finish_url`, the finishing plugin
 * (`src/engine/browser/finish/`) assembles it with the real `save()` and
 * reports back, and `designsetgo/get-build-status` reflects each outcome.
 *
 * Every ability call goes through the WP Abilities API run route
 * (`/wp-abilities/v1/abilities/{name}/run`, core since WP 6.9 — this repo's
 * wp-env pins 6.9). `build-page` is a non-readonly write, so it's POST with
 * `{ input }` as the JSON body; `get-build-status` is `readonly: true`, so
 * it's GET with `input[post_id]=...`. See `helpers/agent-build.js`'s module
 * doc for why `?rest_route=` is used instead of `/wp-json/...` (pretty
 * permalinks are off on this dev site).
 *
 * On the `failed` branch: the brief's own suggestion — a tree containing a
 * block registered only in PHP — doesn't exist as a real gap, because
 * `Tree_Validator::check_unknown_blocks()` checks the *same*
 * `WP_Block_Type_Registry` the browser's `wp.blocks.getBlockType()` reads,
 * populated by the same `register_block_type_from_metadata()` call
 * regardless of which side asks. But a genuinely PHP-valid,
 * browser-invalid tree does exist: `designsetgo/icon-button`'s `text`
 * attribute (`source: 'html'`, `type: 'string'`) has no format constraint
 * server-side, so PHP's `Tree_Attributes` schema check accepts unbalanced
 * HTML in it. The browser's `@wordpress/blocks` parser does not — it
 * re-extracts the attribute from the saved markup through an HTML parser
 * that auto-closes the dangling tag, so the re-derived attribute (and thus
 * the re-`save()`d markup) no longer matches what was written, and
 * `parse()` marks the block `isValid: false`. Confirmed with the built
 * Node CLI before writing this spec:
 * `node build/engine/node.cjs assemble tests/e2e/fixtures/agent-build-trees/failed-invalid-markup.json --json`
 * returns `status: "invalid"`, and the same tree passes
 * `designsetgo/build-page` (verified via curl against the live site) — so
 * the `failed` scenario below is real end-to-end coverage, not a stand-in.
 * `src/engine/browser/finish/test/finish-build.test.js` ("3. an invalid
 * assemble reports failed with invalid + findings, only allowed keys")
 * covers the same branch with fakes at the unit level.
 */

const { test, expect } = require('@playwright/test');
const { getEditorCanvas } = require('./helpers/wordpress');
const {
	buildPage,
	waitForBuildStatus,
	waitForFinishState,
	createPost,
	patchPost,
	getPostEditContext,
	deletePost,
	createUser,
	deleteUser,
	logIn,
} = require('./helpers/agent-build');

const validTree = require('./fixtures/agent-build-trees/valid.json');
const lintWarningTree = require('./fixtures/agent-build-trees/lint-warning.json');
const failedTree = require('./fixtures/agent-build-trees/failed-invalid-markup.json');
const escapedContentTree = require('./fixtures/agent-build-trees/escaped-content.json');
const coreListTree = require('./fixtures/agent-build-trees/core-list.json');
const javascriptButtonTree = require('./fixtures/agent-build-trees/javascript-url-button.json');
const javascriptIconButtonTree = require('./fixtures/agent-build-trees/javascript-url-icon-button.json');
const dataUrlImageTree = require('./fixtures/agent-build-trees/data-url-image.json');

const MARKER = 'Agent build remote e2e marker';
const CANVAS_TIMEOUT = 30000;

/**
 * @param {import('@playwright/test').Page} page Page navigated to a `finish_url`.
 */
async function waitForEditorCanvas(page) {
	await page.locator('iframe[name="editor-canvas"]').waitFor({
		timeout: CANVAS_TIMEOUT,
	});
	await getEditorCanvas(page).locator('body').waitFor({
		timeout: CANVAS_TIMEOUT,
	});
}

/**
 * @param {import('@playwright/test').Page} page Post editor page.
 * @return {Promise<boolean>} Whether `core/editor` autosaving is locked.
 */
function isAutosaveLocked(page) {
	return page.evaluate(() =>
		window.wp.data.select('core/editor').isPostAutosavingLocked()
	);
}

/**
 * Creates a contributor, submits `tree` as them into a new post, runs
 * `callback` with the build-page response (the admin `page` is still logged
 * in as an administrator), and cleans up the post and the user.
 *
 * @param {Object}                             fixtures
 * @param {import('@playwright/test').Page}    fixtures.page    Administrator page.
 * @param {import('@playwright/test').Browser} fixtures.browser Browser for the contributor context.
 * @param {Object}                             tree             Tree to submit.
 * @param {Function}                           callback         `(body) => Promise<void>`.
 */
async function asContributorBuild({ page, browser }, tree, callback) {
	await page.goto('/wp-admin/');
	const username = `dsgo-e2e-contributor-${Date.now()}`;
	const password = `Contributor-${Date.now()}-pass!`;
	const contributor = await createUser(page, {
		username,
		password,
		role: 'contributor',
	});
	const contributorContext = await browser.newContext({
		storageState: { cookies: [], origins: [] },
	});
	let postId;

	try {
		const contributorPage = await contributorContext.newPage();
		await logIn(contributorPage, username, password);

		const { body } = await buildPage(contributorPage, {
			new: { title: 'DSGo E2E contributor build', post_type: 'post' },
			tree,
			mode: 'replace',
		});
		expect(body.success).toBe(true);
		postId = body.post_id;

		await callback(body);
	} finally {
		if (postId) {
			await deletePost(page, 'post', postId);
		}
		await contributorContext.close();
		await deleteUser(page, contributor.id);
	}
}

test.describe('Agent build — remote flow end to end', () => {
	test('draft: a valid tree assembles, saves, and reloads without Attempt Recovery', async ({
		page,
	}) => {
		await page.goto('/wp-admin/');
		const { status, body } = await buildPage(page, {
			new: { title: 'DSGo E2E draft build', post_type: 'page' },
			tree: validTree,
			mode: 'replace',
		});
		expect(status).toBe(200);
		expect(body.success).toBe(true);
		const postId = body.post_id;

		try {
			await page.goto(body.finish_url);
			await waitForEditorCanvas(page);

			const finishState = await waitForFinishState(page);
			expect(finishState).toBe('done');

			const report = await waitForBuildStatus(page, postId, ['finished']);
			expect(report.status).toBe('finished');

			// Reload fresh (no ?dsgo-finish=1) and confirm the saved markup
			// round-trips cleanly — no block shows "Attempt Recovery".
			await page.goto(`/wp-admin/post.php?post=${postId}&action=edit`);
			await waitForEditorCanvas(page);
			await expect(
				getEditorCanvas(page).locator('.block-editor-warning')
			).toHaveCount(0);

			const saved = await getPostEditContext(page, 'page', postId);
			expect(saved.content.raw).toContain('<!-- wp:designsetgo/section');
			expect(saved.content.raw).toContain(MARKER);
		} finally {
			await deletePost(page, 'page', postId);
		}
	});

	// See the module doc comment above for why this is real coverage, not
	// a stand-in for a case that doesn't exist.
	test('failed: browser-only-invalid markup reports failed and saves nothing', async ({
		page,
	}) => {
		await page.goto('/wp-admin/');
		const { body } = await buildPage(page, {
			new: { title: 'DSGo E2E failed build', post_type: 'page' },
			tree: failedTree,
			mode: 'replace',
		});
		expect(body.success).toBe(true);
		const postId = body.post_id;

		try {
			await page.goto(body.finish_url);
			await waitForEditorCanvas(page);

			const finishState = await waitForFinishState(page);
			expect(finishState).toBe('failed');

			const report = await waitForBuildStatus(page, postId, ['failed']);
			expect(report.status).toBe('failed');
			expect(report.invalid?.length).toBeGreaterThan(0);
			expect(report.invalid[0].block).toBe('designsetgo/icon-button');

			const saved = await getPostEditContext(page, 'page', postId);
			expect(saved.content.raw).toBe('');
		} finally {
			await deletePost(page, 'page', postId);
		}
	});

	test('published: awaiting_review holds for human review, the frontend stays untouched, and Discard reverts', async ({
		page,
	}) => {
		await page.goto('/wp-admin/');
		const original = await createPost(page, {
			postType: 'page',
			status: 'publish',
			title: 'DSGo E2E published build',
			content:
				'<!-- wp:paragraph --><p>Original published content marker</p><!-- /wp:paragraph -->',
		});
		const postId = original.id;

		try {
			const { body } = await buildPage(page, {
				post_id: postId,
				tree: validTree,
				mode: 'replace',
			});
			expect(body.success).toBe(true);

			await page.goto(body.finish_url);
			await waitForEditorCanvas(page);

			// The published branch still marks the document "done" once the
			// build has been applied to the canvas for review — "done" here
			// means "finishBuild() reached a terminal outcome", not "saved".
			const finishState = await waitForFinishState(page);
			expect(finishState).toBe('done');

			const report = await waitForBuildStatus(page, postId, [
				'awaiting_review',
			]);
			expect(report.status).toBe('awaiting_review');

			const reviewNotice = page.locator('.components-notice').filter({
				hasText: 'Review agent changes before updating.',
			});
			await expect(reviewNotice).toBeVisible();

			// Frontend (logged out — no cookies, no nonce) must still show the
			// original content: applying to the canvas for review must never
			// touch what a visitor sees.
			const origin = new URL(page.url()).origin;
			const frontendResponse = await fetch(`${origin}/?page_id=${postId}`);
			const frontendHtml = await frontendResponse.text();
			expect(frontendHtml).toContain('Original published content marker');
			expect(frontendHtml).not.toContain(MARKER);

			// Not saved server-side either.
			const beforeDiscard = await getPostEditContext(page, 'page', postId);
			expect(beforeDiscard.content.raw).toContain(
				'Original published content marker'
			);
			expect(beforeDiscard.content.raw).not.toContain(
				'designsetgo/section'
			);
			expect(beforeDiscard.modified_gmt).toBe(original.modified_gmt);

			await reviewNotice.getByRole('button', { name: 'Discard' }).click();

			const discardedReport = await waitForBuildStatus(page, postId, [
				'discarded',
			]);
			expect(discardedReport.status).toBe('discarded');

			const canvas = getEditorCanvas(page);
			await expect(canvas.getByText(MARKER)).toHaveCount(0);
			await expect(
				canvas.getByText('Original published content marker')
			).toBeVisible();

			const afterDiscard = await getPostEditContext(page, 'page', postId);
			expect(afterDiscard.content.raw).toBe(beforeDiscard.content.raw);
			expect(afterDiscard.modified_gmt).toBe(original.modified_gmt);
		} finally {
			await deletePost(page, 'page', postId);
		}
	});

	test('conflict: a post edited after build-page is queued reports conflict and applies nothing', async ({
		page,
	}) => {
		await page.goto('/wp-admin/');
		const original = await createPost(page, {
			postType: 'page',
			status: 'draft',
			title: 'DSGo E2E conflict build',
		});
		const postId = original.id;

		try {
			const { body } = await buildPage(page, {
				post_id: postId,
				tree: validTree,
				mode: 'replace',
			});
			expect(body.success).toBe(true);

			// Modify the post through REST *after* queuing but *before* ever
			// opening finish_url — Build_Store::is_conflict() compares the
			// stored base against the post's current post_modified_gmt.
			await patchPost(page, 'page', postId, {
				title: 'DSGo E2E conflict build (edited)',
			});

			await page.goto(body.finish_url);
			await waitForEditorCanvas(page);

			// Conflict marks the document "failed" — see finish-build.js.
			const finishState = await waitForFinishState(page);
			expect(finishState).toBe('failed');

			const report = await waitForBuildStatus(page, postId, ['conflict']);
			expect(report.status).toBe('conflict');

			const conflictNotice = page.locator('.components-notice').filter({
				hasText: 'This page changed since the agent build was queued.',
			});
			await expect(conflictNotice).toBeVisible();

			const saved = await getPostEditContext(page, 'page', postId);
			expect(saved.content.raw).toBe('');
		} finally {
			await deletePost(page, 'page', postId);
		}
	});

	test('lint warning: a warning-only tree reports finished_with_findings', async ({
		page,
	}) => {
		await page.goto('/wp-admin/');
		const { body } = await buildPage(page, {
			new: { title: 'DSGo E2E lint warning build', post_type: 'page' },
			tree: lintWarningTree,
			mode: 'replace',
		});
		expect(body.success).toBe(true);
		const postId = body.post_id;

		try {
			await page.goto(body.finish_url);
			await waitForEditorCanvas(page);

			const finishState = await waitForFinishState(page);
			expect(finishState).toBe('done');

			const report = await waitForBuildStatus(page, postId, [
				'finished_with_findings',
			]);
			expect(report.status).toBe('finished_with_findings');
			expect(
				report.findings.some((finding) => finding.rule === 'mobile-layout')
			).toBe(true);

			const saved = await getPostEditContext(page, 'page', postId);
			expect(saved.content.raw).toContain('<!-- wp:designsetgo/grid');
		} finally {
			await deletePost(page, 'page', postId);
		}
	});
	// Quotes, a real link, and non-ASCII are all escaped by wp_json_encode();
	// post meta used to unslash those escapes away, so this build vanished.
	// The section's explicit `style: {}` used to assemble invalid, too.
	test('escaped content: a link, non-ASCII text, and style: {} finish intact', async ({
		page,
	}) => {
		await page.goto('/wp-admin/');
		const { body } = await buildPage(page, {
			new: { title: 'DSGo E2E escaped content build', post_type: 'page' },
			tree: escapedContentTree,
			mode: 'replace',
		});
		expect(body.success).toBe(true);
		const postId = body.post_id;

		try {
			await page.goto(body.finish_url);
			await waitForEditorCanvas(page);

			expect(await waitForFinishState(page)).toBe('done');
			const report = await waitForBuildStatus(page, postId, ['finished']);
			expect(report.status).toBe('finished');

			const saved = await getPostEditContext(page, 'page', postId);
			expect(saved.content.raw).toContain(
				'<a href="https://example.com">our café</a>'
			);
			expect(saved.content.raw).toContain('naïve “quotes”');
		} finally {
			await deletePost(page, 'page', postId);
		}
	});

	test('core containers: a core/list > core/list-item tree is accepted and finished', async ({
		page,
	}) => {
		await page.goto('/wp-admin/');
		const { body } = await buildPage(page, {
			new: { title: 'DSGo E2E core list build', post_type: 'page' },
			tree: coreListTree,
			mode: 'replace',
		});
		expect(body.success).toBe(true);
		const postId = body.post_id;

		try {
			await page.goto(body.finish_url);
			await waitForEditorCanvas(page);

			expect(await waitForFinishState(page)).toBe('done');
			const report = await waitForBuildStatus(page, postId, ['finished']);
			expect(report.status).toBe('finished');

			const saved = await getPostEditContext(page, 'page', postId);
			expect(saved.content.raw).toContain('<!-- wp:list-item -->');
			expect(saved.content.raw).toContain('Core list marker two');
		} finally {
			await deletePost(page, 'page', postId);
		}
	});

	// A build is only saved automatically for the person who submitted it:
	// an administrator opening a contributor's build must review it, never
	// save it under their own unfiltered_html.
	test('another user: a contributor build opened by an administrator waits for review, unsaved', async ({
		page,
		browser,
	}) => {
		await asContributorBuild({ page, browser }, validTree, async (body) => {
			const postId = body.post_id;
			const sanitizeRequests = [];
			page.on('request', (request) => {
				const url = decodeURIComponent(request.url());
				if (/\/agent-build\/\d+\/sanitize/.test(url)) {
					sanitizeRequests.push(url);
				}
			});

			await page.goto(body.finish_url);
			await waitForEditorCanvas(page);

			expect(await waitForFinishState(page)).toBe('done');
			const report = await waitForBuildStatus(page, postId, [
				'awaiting_review',
			]);
			expect(report.status).toBe('awaiting_review');
			// Safe content from a contributor passes the markup filter.
			expect(sanitizeRequests).toEqual([
				expect.stringContaining(`/agent-build/${postId}/sanitize`),
			]);

			const reviewNotice = page.locator('.components-notice').filter({
				hasText: 'on behalf of another user',
			});
			await expect(reviewNotice).toBeVisible();

			// Core autosave on this draft would write the build into the
			// post under the administrator's capabilities.
			expect(await isAutosaveLocked(page)).toBe(true);

			const saved = await getPostEditContext(page, 'post', postId);
			expect(saved.content.raw).toBe('');
			expect(saved.status).toBe('draft');

			await reviewNotice.getByRole('button', { name: 'Discard' }).click();
			await waitForBuildStatus(page, postId, ['discarded']);
			expect(await isAutosaveLocked(page)).toBe(false);
		});
	});

	// KSES rewrites `href="javascript:alert(1)"` to `href="alert(1)"` and
	// `src="data:…"` to `src="image/png;base64,…"`. Each block still
	// validates, but the url attribute re-parses to a different value, and
	// any attribute the filter changes fails the build rather than applying
	// it half-stripped.
	for (const { label, tree } of [
		{ label: 'javascript: url in a core/button', tree: javascriptButtonTree },
		{ label: 'data: src in a core/image', tree: dataUrlImageTree },
	]) {
		test(`${label} from a contributor fails as sanitized content changed`, async ({
			page,
			browser,
		}) => {
			await asContributorBuild({ page, browser }, tree, async (body) => {
				const postId = body.post_id;
				await page.goto(body.finish_url);
				await waitForEditorCanvas(page);

				expect(await waitForFinishState(page)).toBe('failed');
				const report = await waitForBuildStatus(page, postId, [
					'failed',
					'awaiting_review',
				]);
				expect(report.status).toBe('failed');
				expect(report.invalid).toEqual([
					expect.objectContaining({
						code: 'designsetgo_sanitized_content_changed',
						reason: expect.stringContaining('url'),
					}),
				]);

				const canvasMarkup = await page.evaluate(() =>
					window.wp.data.select('core/editor').getEditedPostContent()
				);
				expect(canvasMarkup).not.toContain('javascript:');
				expect(canvasMarkup).not.toContain('data:image');

				const saved = await getPostEditContext(page, 'post', postId);
				expect(saved.content.raw).toBe('');
			});
		});
	}

	// designsetgo/icon-button keeps url in the block comment, which KSES
	// leaves as plain text, while the rendered href loses its protocol. The
	// re-parsed block no longer matches its markup, so the build fails.
	test('javascript: url in a contributor icon-button fails as sanitized content changed', async ({
		page,
		browser,
	}) => {
		await asContributorBuild(
			{ page, browser },
			javascriptIconButtonTree,
			async (body) => {
				const postId = body.post_id;
				await page.goto(body.finish_url);
				await waitForEditorCanvas(page);

				expect(await waitForFinishState(page)).toBe('failed');
				const report = await waitForBuildStatus(page, postId, [
					'failed',
					'awaiting_review',
				]);
				expect(report.status).toBe('failed');
				expect(report.invalid).toEqual([
					expect.objectContaining({
						path: 'blocks[0]',
						block: 'designsetgo/icon-button',
						code: 'designsetgo_sanitized_content_changed',
					}),
				]);

				const canvasMarkup = await page.evaluate(() =>
					window.wp.data.select('core/editor').getEditedPostContent()
				);
				expect(canvasMarkup).not.toContain('javascript:');

				const saved = await getPostEditContext(page, 'post', postId);
				expect(saved.content.raw).toBe('');
			}
		);
	});
});
