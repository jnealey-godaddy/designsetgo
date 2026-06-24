/**
 * Shared artifact + publish helpers for DesignSetGo e2e tests.
 *
 * Screenshots / videos land alongside the other Playwright artifacts so they
 * are easy to find (and get uploaded by CI). Every artifact is filed under a
 * uniform three-level path — a top-level group ("blocks" or "patterns"), the
 * item under test (a block name or pattern slug), then the scenario (the
 * specific test) — so one item can have many scenarios without collisions:
 *
 *   screenshots/<group>/<item>/<scenario>/editor.png
 *   screenshots/<group>/<item>/<scenario>/frontend.png
 *   screenshots/<group>/<item>/<scenario>/video.webm   (only when DSGO_RECORD_VIDEO=1)
 *
 * e.g. screenshots/blocks/modal/insert-and-render/editor.png
 *      screenshots/patterns/hero/hero-parallax/insert-publish-render/frontend.png
 */

const fs = require('fs');
const path = require('path');
const { deletePostIds } = require('./wp-cli');

const SCREENSHOT_DIR = path.join(
	process.env.WP_ARTIFACTS_PATH || path.join(process.cwd(), 'artifacts'),
	'screenshots'
);

// Optional video capture. Off by default (screenshots only); set
// DSGO_RECORD_VIDEO=1 to also record a video of each test.
const RECORD_VIDEO = ['1', 'true'].includes(process.env.DSGO_RECORD_VIDEO);

// IDs of pages published this run that haven't been deleted yet. Buffered so
// the per-test cleanup deletes in small batches instead of one CLI round-trip
// per test (see installPublishedPageCleanup). publishAndResolveUrl appends here.
const pendingPostIds = [];

/**
 * Tag a test with its group + item + scenario so screenshots and the recorded
 * video all land under screenshots/<group>/<item>/<scenario>/. Call once at the
 * top of a test; pass the returned descriptor to saveScreenshot().
 *
 * @param {import('@playwright/test').TestInfo} testInfo - Playwright test info
 * @param {string}                              group    - Top-level bucket ("blocks" | "patterns")
 * @param {string}                              item     - Item under test (block name / pattern slug)
 * @param {string}                              scenario - Scenario name (the specific test)
 * @return {{group: string, item: string, scenario: string}} Artifact descriptor
 */
function defineArtifact(testInfo, group, item, scenario) {
	testInfo.annotations.push({ type: 'dsgo-group', description: group });
	testInfo.annotations.push({ type: 'dsgo-item', description: item });
	testInfo.annotations.push({ type: 'dsgo-scenario', description: scenario });
	return { group, item, scenario };
}

/**
 * Save a full-page screenshot under screenshots/<group>/<item>/<scenario>/.
 *
 * @param {import('@playwright/test').Page}                 page     - Playwright page object
 * @param {{group: string, item: string, scenario: string}} artifact - Descriptor from defineArtifact()
 * @param {'editor'|'frontend'}                             source   - Which view; names the file
 *                                                                   editor.png or frontend.png.
 */
async function saveScreenshot(page, artifact, source) {
	const fileName = source === 'editor' ? 'editor.png' : 'frontend.png';
	await page.screenshot({
		path: path.join(
			SCREENSHOT_DIR,
			artifact.group,
			artifact.item,
			artifact.scenario,
			fileName
		),
		fullPage: true,
	});
}

/**
 * Register video-capture hooks on a test object. No-op unless
 * DSGO_RECORD_VIDEO=1. Each video lands at
 * screenshots/<group>/<item>/<scenario>/video.webm, copied in afterAll once
 * every per-test context has closed and the files are finalized (avoids
 * saveAs() deadlocking on the still-open page).
 *
 * Call once at module scope in a spec file:
 *   installVideoCapture(test);
 *
 * @param {import('@playwright/test').TestType} test - Playwright test object
 */
function installVideoCapture(test) {
	if (!RECORD_VIDEO) {
		test.use({ video: 'off' });
		return;
	}

	test.use({ video: 'on' });

	const pendingVideos = [];

	test.afterEach(async ({ page }, testInfo) => {
		const video = page.video();
		const find = (type) =>
			testInfo.annotations.find((a) => a.type === type)?.description;
		const group = find('dsgo-group');
		const item = find('dsgo-item');
		const scenario = find('dsgo-scenario');
		if (!video || !group || !item || !scenario) {
			return;
		}
		pendingVideos.push({
			src: await video.path(),
			dest: path.join(
				SCREENSHOT_DIR,
				group,
				item,
				scenario,
				'video.webm'
			),
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
}

/**
 * Slowly scroll a scope from top to bottom and back to top.
 *
 * Works on either a Page (frontend window) or a Frame (the editor canvas
 * iframe) — both expose `evaluate`, and the body scrolls whatever `window`
 * belongs to that scope. Used so the full block is revealed over time in the
 * recorded video and any lazy-loaded / parallax content is triggered before
 * the screenshot. Small steps with a delay keep the motion visible.
 *
 * @param {import('@playwright/test').Page | import('@playwright/test').Frame} scope
 *                                                                                   Page or Frame to scroll.
 */
async function slowScrollToBottom(scope) {
	await scope.evaluate(async () => {
		const step = 180;
		const delayMs = 220;
		const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

		window.scrollTo(0, 0);
		await sleep(delayMs);

		let last = -1;
		// Guard against unbounded growth (infinite scroll) with a step cap.
		for (let i = 0; i < 400; i++) {
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
 * Slowly scroll the editor canvas (which lives in the `editor-canvas` iframe)
 * so the whole block is revealed in the editor view of the recorded video.
 * Falls back to scrolling the page if the canvas iframe isn't present.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object
 */
async function slowScrollEditor(page) {
	const frame = page.frame({ name: 'editor-canvas' });
	await slowScrollToBottom(frame || page);
}

/**
 * Title the current post, so test pages are identifiable (instead of
 * "(no title)") in the editor, the page list, and the frontend.
 *
 * @param {import('@playwright/test').Page} page  - Playwright page object
 * @param {string}                          title - Post title
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
	// Track for cleanup so this published page doesn't linger on the site (and
	// in the nav header) once the test is done with it.
	pendingPostIds.push(id);
	return `/?page_id=${id}`;
}

/**
 * Register per-test cleanup of the pages published via publishAndResolveUrl.
 *
 * Without this, every test leaves its published page on the site; across a
 * large sweep they pile up in the database and in theme nav menus. This deletes
 * them as the run proceeds (in afterEach, which fires even when a test fails),
 * flushing in small batches so the site never holds more than `flushEvery`
 * stray test pages while keeping CLI round-trips bounded. A final flush in
 * afterAll clears the remainder. The cleanup teardown is the last-resort net for
 * anything a crashed worker skips.
 *
 * Call once at module scope in a spec file, alongside installVideoCapture:
 *   installPublishedPageCleanup(test);
 *
 * @param {import('@playwright/test').TestType} test                 - Playwright test object
 * @param {{flushEvery?: number}}               [options]            - Tuning
 * @param {number}                              [options.flushEvery] - Delete once this many pages are pending (default 5)
 */
function installPublishedPageCleanup(test, { flushEvery = 5 } = {}) {
	const flush = () => {
		if (!pendingPostIds.length) {
			return;
		}
		try {
			deletePostIds(pendingPostIds.splice(0));
		} catch (e) {
			// Non-fatal: the cleanup teardown wipes all pages/posts at the end,
			// so a transient CLI failure here only delays cleanup. Surface it.
			// eslint-disable-next-line no-console
			console.warn(
				'[cleanup] Could not delete published test pages:',
				e.message.split('\n')[0]
			);
		}
	};

	test.afterEach(async () => {
		if (pendingPostIds.length >= flushEvery) {
			flush();
		}
	});

	test.afterAll(async () => {
		flush();
	});
}

module.exports = {
	SCREENSHOT_DIR,
	RECORD_VIDEO,
	defineArtifact,
	saveScreenshot,
	installVideoCapture,
	installPublishedPageCleanup,
	slowScrollToBottom,
	slowScrollEditor,
	setPostTitle,
	publishAndResolveUrl,
};
