/**
 * Helpers for e2e coverage of the remote agent build flow: the
 * `designsetgo/build-page` and `designsetgo/get-build-status` abilities over
 * the WP Abilities API REST routes (`/wp-abilities/v1/abilities/{name}/run`,
 * WP 6.9+ core), plus thin wrappers around the core `wp/v2` REST endpoints
 * this flow needs (create/patch/delete a post, read `context=edit`).
 *
 * The dev site behind these tests runs with pretty permalinks off (`wp
 * option get permalink_structure` is empty), so `/wp-json/...` 404s —
 * every request here goes through `?rest_route=` instead, confirmed against
 * the live site before this file was written.
 *
 * Ability HTTP method is fixed by the ability's own `annotations`, not a
 * caller choice: `build-page` is a non-readonly write, so it is POST with
 * `{ input }` as the JSON body; `get-build-status` is `readonly: true`, so
 * it is GET with `input[post_id]=...` as a query param (WP's run controller
 * reads `input` from `get_query_params()` for GET/DELETE, from
 * `get_json_params()` for POST — see
 * `wp-includes/rest-api/endpoints/class-wp-rest-abilities-v1-run-controller.php`).
 */

const ABILITIES_BASE = '/?rest_route=/wp-abilities/v1/abilities';
const WP_V2_BASE = '/?rest_route=/wp/v2';

/**
 * Reads a REST nonce from the current page's `wpApiSettings`. Every request
 * below needs one: a cookie-authenticated request with no nonce is treated
 * as anonymous by core's REST cookie auth check, so an admin-only route
 * (build-page, get-build-status, and the `wp/v2` writes here) would 401
 * rather than run as the logged-in user.
 *
 * @param {import('@playwright/test').Page} page A page currently on a
 *                                               wp-admin screen (any screen — every one localizes `wpApiSettings`).
 * @return {Promise<string>} The nonce.
 */
async function getNonce(page) {
	let nonce = await page.evaluate(() => window.wpApiSettings?.nonce || '');
	if (!nonce) {
		// Not every screen localizes wpApiSettings for every role (a
		// contributor's dashboard doesn't); core's rest-nonce AJAX action
		// hands any logged-in user the same nonce.
		const response = await page.request.get(
			'/wp-admin/admin-ajax.php?action=rest-nonce',
			{ failOnStatusCode: false }
		);
		const text = response.ok() ? (await response.text()).trim() : '';
		nonce = /^[a-f0-9]+$/i.test(text) ? text : '';
	}
	if (!nonce) {
		throw new Error(
			'Could not read a REST nonce from wpApiSettings — is the page on a wp-admin screen?'
		);
	}
	return nonce;
}

/**
 * Calls `designsetgo/build-page/run` (POST — the ability is a non-readonly
 * write).
 *
 * @param {import('@playwright/test').Page} page  wp-admin page (for the nonce).
 * @param {Object}                          input Ability input: `{ post_id | new, tree, mode? }`.
 * @return {Promise<{status: number, body: Object}>} HTTP status and parsed JSON body.
 */
async function buildPage(page, input) {
	const nonce = await getNonce(page);
	const response = await page.request.post(
		`${ABILITIES_BASE}/designsetgo/build-page/run`,
		{
			headers: {
				'X-WP-Nonce': nonce,
				'Content-Type': 'application/json',
			},
			data: { input },
			failOnStatusCode: false,
		}
	);
	return { status: response.status(), body: await response.json() };
}

/**
 * Calls `designsetgo/get-build-status/run` (GET — the ability is `readonly`).
 *
 * @param {import('@playwright/test').Page} page   wp-admin page (for the nonce).
 * @param {number}                          postId Post id a `build-page` call targeted.
 * @return {Promise<{status: number, body: Object}>} HTTP status and parsed JSON body: `{ success, post_id, pending, report }`.
 */
async function getBuildStatus(page, postId) {
	const nonce = await getNonce(page);
	const response = await page.request.get(
		`${ABILITIES_BASE}/designsetgo/get-build-status/run`,
		{
			headers: { 'X-WP-Nonce': nonce },
			params: { 'input[post_id]': String(postId) },
			failOnStatusCode: false,
		}
	);
	return { status: response.status(), body: await response.json() };
}

/**
 * Polls `getBuildStatus()` until `report.status` matches one of `statuses`,
 * or times out. The finishing plugin's REST report POST and this spec's
 * subsequent poll are two separate requests, so a fixed wait would be
 * flaky — this closes that gap deterministically.
 *
 * @param {import('@playwright/test').Page} page                 wp-admin page (for the nonce).
 * @param {number}                          postId               Post id to poll.
 * @param {string[]}                        statuses             Acceptable terminal `report.status` values.
 * @param {Object}                          [options]
 * @param {number}                          [options.timeoutMs]  Overall cap. Default 20000.
 * @param {number}                          [options.intervalMs] Gap between polls. Default 500.
 * @return {Promise<Object>} The final report body's `report` object.
 */
async function waitForBuildStatus(
	page,
	postId,
	statuses,
	{ timeoutMs = 20000, intervalMs = 500 } = {}
) {
	const deadline = Date.now() + timeoutMs;
	let last;

	while (Date.now() < deadline) {
		const { body } = await getBuildStatus(page, postId);
		last = body.report;
		if (last && statuses.includes(last.status)) {
			return last;
		}
		// eslint-disable-next-line no-await-in-loop
		await new Promise((resolve) => setTimeout(resolve, intervalMs));
	}

	throw new Error(
		`Timed out waiting for post ${postId}'s build status to reach one of [${statuses.join(', ')}]; last seen: ${JSON.stringify(last)}`
	);
}

/**
 * Waits for the finishing plugin to mark the top-level document with a
 * terminal `data-dsgo-finish` state (`done` or `failed`) — see
 * `src/engine/browser/finish/index.js`'s `markDocument`.
 *
 * @param {import('@playwright/test').Page} page      Editor page, already navigated to `finish_url`.
 * @param {number}                          [timeout] Max wait, ms. Default 20000.
 * @return {Promise<string>} The final `data-dsgo-finish` value.
 */
async function waitForFinishState(page, timeout = 20000) {
	await page.waitForFunction(
		() => Boolean(document.documentElement.dataset.dsgoFinish),
		undefined,
		{ timeout }
	);
	return page.evaluate(() => document.documentElement.dataset.dsgoFinish);
}

/**
 * Creates a post via `wp/v2/{postType}s` (POST).
 *
 * @param {import('@playwright/test').Page} page              wp-admin page (for the nonce).
 * @param {Object}                          options
 * @param {string}                          options.postType  Post type slug, e.g. `'page'`.
 * @param {string}                          options.status    `'draft'` or `'publish'`.
 * @param {string}                          options.title     Post title.
 * @param {string}                          [options.content] Raw block content.
 * @return {Promise<Object>} The created post's REST representation.
 */
async function createPost(page, { postType, status, title, content = '' }) {
	const nonce = await getNonce(page);
	const response = await page.request.post(`${WP_V2_BASE}/${postType}s`, {
		headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
		data: { status, title, content },
		failOnStatusCode: false,
	});
	if (!response.ok()) {
		throw new Error(
			`createPost(${postType}) failed: ${response.status()} ${await response.text()}`
		);
	}
	return response.json();
}

/**
 * Patches a post via `wp/v2/{postType}s/{id}` (POST) — e.g. changing the
 * title to bump `post_modified_gmt` for the conflict scenario.
 *
 * @param {import('@playwright/test').Page} page     wp-admin page (for the nonce).
 * @param {string}                          postType Post type slug.
 * @param {number}                          id       Post id.
 * @param {Object}                          data     Fields to update.
 * @return {Promise<Object>} The updated post's REST representation.
 */
async function patchPost(page, postType, id, data) {
	const nonce = await getNonce(page);
	const response = await page.request.post(
		`${WP_V2_BASE}/${postType}s/${id}`,
		{
			headers: {
				'X-WP-Nonce': nonce,
				'Content-Type': 'application/json',
			},
			data,
			failOnStatusCode: false,
		}
	);
	if (!response.ok()) {
		throw new Error(
			`patchPost(${postType}, ${id}) failed: ${response.status()} ${await response.text()}`
		);
	}
	return response.json();
}

/**
 * Reads a post with `context=edit` (so `content.raw` and `modified_gmt` are
 * present, matching what `Build_Store` compares against for conflicts).
 *
 * @param {import('@playwright/test').Page} page     wp-admin page (for the nonce).
 * @param {string}                          postType Post type slug.
 * @param {number}                          id       Post id.
 * @return {Promise<Object>} The post's REST representation, edit context.
 */
async function getPostEditContext(page, postType, id) {
	const nonce = await getNonce(page);
	const response = await page.request.get(
		`${WP_V2_BASE}/${postType}s/${id}`,
		{
			headers: { 'X-WP-Nonce': nonce },
			params: { context: 'edit' },
			failOnStatusCode: false,
		}
	);
	if (!response.ok()) {
		throw new Error(
			`getPostEditContext(${postType}, ${id}) failed: ${response.status()} ${await response.text()}`
		);
	}
	return response.json();
}

/**
 * Permanently deletes a post via `wp/v2/{postType}s/{id}?force=true` — used
 * in test teardown so the spec is re-runnable.
 *
 * @param {import('@playwright/test').Page} page     wp-admin page (for the nonce).
 * @param {string}                          postType Post type slug.
 * @param {number}                          id       Post id.
 * @return {Promise<void>}
 */
async function deletePost(page, postType, id) {
	const nonce = await getNonce(page);
	await page.request.delete(`${WP_V2_BASE}/${postType}s/${id}`, {
		headers: { 'X-WP-Nonce': nonce },
		params: { force: 'true' },
		failOnStatusCode: false,
	});
}

/**
 * Creates a user via `wp/v2/users` (POST) as the current admin.
 *
 * @param {import('@playwright/test').Page} page             wp-admin page (for the nonce).
 * @param {Object}                          options
 * @param {string}                          options.username Login name.
 * @param {string}                          options.password Password.
 * @param {string}                          options.role     Role slug, e.g. `'contributor'`.
 * @return {Promise<Object>} The created user's REST representation.
 */
async function createUser(page, { username, password, role }) {
	const nonce = await getNonce(page);
	const response = await page.request.post(`${WP_V2_BASE}/users`, {
		headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
		data: {
			username,
			password,
			email: `${username}@example.com`,
			roles: [role],
		},
		failOnStatusCode: false,
	});
	if (!response.ok()) {
		throw new Error(
			`createUser(${username}) failed: ${response.status()} ${await response.text()}`
		);
	}
	return response.json();
}

/**
 * Permanently deletes a user via `wp/v2/users/{id}`, reassigning their
 * content to the current admin.
 *
 * @param {import('@playwright/test').Page} page wp-admin page (for the nonce).
 * @param {number}                          id   User id.
 * @return {Promise<void>}
 */
async function deleteUser(page, id) {
	const nonce = await getNonce(page);
	const me = await page.request.get(`${WP_V2_BASE}/users/me`, {
		headers: { 'X-WP-Nonce': nonce },
		failOnStatusCode: false,
	});
	const { id: adminId } = await me.json();
	await page.request.delete(`${WP_V2_BASE}/users/${id}`, {
		headers: { 'X-WP-Nonce': nonce },
		params: { force: 'true', reassign: String(adminId) },
		failOnStatusCode: false,
	});
}

/**
 * Logs a fresh browser context in through `wp-login.php` and leaves its page
 * on a wp-admin screen, ready for `getNonce()`.
 *
 * @param {import('@playwright/test').Page} page     Page in a context with no stored auth.
 * @param {string}                          username Login name.
 * @param {string}                          password Password.
 * @return {Promise<void>}
 */
async function logIn(page, username, password) {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', username);
	await page.fill('#user_pass', password);
	await Promise.all([
		page.waitForURL(/\/wp-admin\//),
		page.click('#wp-submit'),
	]);
}

module.exports = {
	getNonce,
	createUser,
	deleteUser,
	logIn,
	buildPage,
	getBuildStatus,
	waitForBuildStatus,
	waitForFinishState,
	createPost,
	patchPost,
	getPostEditContext,
	deletePost,
};
