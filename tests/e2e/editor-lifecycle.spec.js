/** Frontend checks must preserve in-flight editor requests and surface errors. */
const { test, expect } = require('@playwright/test');
const { openFrontendPage } = require('./helpers/artifacts');

test('reviewing the frontend lets a pending editor request finish', async ({
	page,
	context,
}) => {
	let releaseRequest;
	const gate = new Promise((resolve) => {
		releaseRequest = resolve;
	});
	await context.route('**/__dsgo_lifecycle_*', async (route) => {
		const endpoint = new URL(route.request().url()).pathname;
		if (endpoint === '/__dsgo_lifecycle_data') {
			await gate;
			// A canceled route is the expected failure with same-tab navigation.
			await route
				.fulfill({ body: 'complete', contentType: 'text/plain' })
				.catch(() => {});
		} else if (endpoint === '/__dsgo_lifecycle_editor') {
			await route.fulfill({
				contentType: 'text/html',
				body: '<script>fetch("/__dsgo_lifecycle_data").then(r => r.text()).then(value => window.requestResult = value).catch(error => window.requestResult = error.message);</script>',
			});
		} else {
			await route.fulfill({
				contentType: 'text/html',
				body: '<h1>Published frontend</h1>',
			});
		}
	});
	try {
		const pendingRequest = page.waitForRequest('**/__dsgo_lifecycle_data');
		await page.goto('/__dsgo_lifecycle_editor');
		const request = await pendingRequest;
		const { page: frontend, response } = await openFrontendPage(
			page,
			'/__dsgo_lifecycle_frontend'
		);
		expect(response.ok()).toBe(true);
		await expect(
			frontend.getByRole('heading', { name: 'Published frontend' })
		).toBeVisible();
		releaseRequest();
		const completed = await request.response();
		expect(completed?.status()).toBe(200);
		await expect
			.poll(() => page.evaluate(() => window.requestResult))
			.toBe('complete');
	} finally {
		releaseRequest();
	}
});

test('frontend JavaScript errors remain observable', async ({
	page,
	context,
}) => {
	await context.route('**/__dsgo_frontend_error', (route) =>
		route.fulfill({
			contentType: 'text/html',
			body: '<script>throw new Error("Frontend regression sentinel")</script>',
		})
	);
	const errors = [];
	await openFrontendPage(page, '/__dsgo_frontend_error', (error) =>
		errors.push(error.message)
	);
	await expect.poll(() => errors).toContain('Frontend regression sentinel');
});
