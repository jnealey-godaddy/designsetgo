/** Dynamic icon REST requests must honor the server's 100-name limit. */
const SVG =
	'<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1v1Z"/></svg>';
const originalFetch = global.fetch;
const originalObserver = global.MutationObserver;

function addIcons(count, prefix = 'icon') {
	return Array.from({ length: count }, (_, index) => {
		const element = document.createElement('span');
		element.className = 'dsgo-lazy-icon';
		element.dataset.iconName = `${prefix}-${index}`;
		document.body.appendChild(element);
		return element;
	});
}

function namesFor(url) {
	return new URL(url).searchParams.get('names').split(',');
}

function response(icons) {
	return { ok: true, json: () => Promise.resolve(icons) };
}

async function settle() {
	for (let index = 0; index < 30; index++) {
		await Promise.resolve();
	}
}

function load() {
	jest.isolateModules(() => {
		require('../../src/frontend/lazy-icon-injector.js');
	});
}

beforeEach(() => {
	jest.useFakeTimers();
	global.MutationObserver = undefined;
	document.body.innerHTML = '';
	window.dsgoIcons = {};
	window.dsgoIconsRest = {
		url: 'https://example.com/wp-json/designsetgo/v1/icons',
		version: '2.8.2',
	};
	global.fetch = jest.fn((url) =>
		Promise.resolve(
			response(
				Object.fromEntries(
					namesFor(url)
						.slice(0, 100)
						.map((name) => [name, SVG])
				)
			)
		)
	);
});

afterEach(() => {
	jest.useRealTimers();
	global.fetch = originalFetch;
	global.MutationObserver = originalObserver;
	delete window.dsgoIcons;
	delete window.dsgoIconsRest;
});

test('injects all 101 dynamic icons through requests within the server limit', async () => {
	const icons = addIcons(101);
	load();
	await settle();

	expect(global.fetch).toHaveBeenCalledTimes(2);
	global.fetch.mock.calls.forEach(([url, options]) => {
		expect(namesFor(url).length).toBeLessThanOrEqual(100);
		expect(options.credentials).toBe('omit');
	});
	icons.forEach((icon) => expect(icon.querySelector('svg')).not.toBeNull());
});

test('queues large and overlapping scans without parallel or duplicate requests', async () => {
	const pending = [];
	global.fetch = jest.fn(
		(url) => new Promise((resolve) => pending.push({ url, resolve }))
	);
	const icons = addIcons(251);
	load();
	window.dsgoInjectIcons();
	icons.push(...addIcons(1, 'new'));
	window.dsgoInjectIcons();

	expect(global.fetch).toHaveBeenCalledTimes(1);
	for (let index = 0; index < 3; index++) {
		const request = pending[index];
		expect(namesFor(request.url).length).toBeLessThanOrEqual(100);
		request.resolve(
			response(
				Object.fromEntries(
					namesFor(request.url).map((name) => [name, SVG])
				)
			)
		);
		await settle();
		expect(global.fetch).toHaveBeenCalledTimes(Math.min(index + 2, 3));
	}
	const names = global.fetch.mock.calls.flatMap(([url]) => namesFor(url));
	expect(new Set(names).size).toBe(252);
	expect(names.length).toBe(252);
	icons.forEach((icon) => expect(icon.querySelector('svg')).not.toBeNull());
});

test('keeps successful batches when another batch fails, and retries only failed names after cooldown', async () => {
	const icons = addIcons(201);
	let requests = 0;
	global.fetch.mockImplementation((url) => {
		requests++;
		return Promise.resolve(
			requests === 2
				? { ok: false, status: 503 }
				: response(
						Object.fromEntries(
							namesFor(url).map((name) => [name, SVG])
						)
					)
		);
	});
	load();
	await settle();

	expect(global.fetch).toHaveBeenCalledTimes(3);
	expect(icons.filter((icon) => icon.querySelector('svg'))).toHaveLength(101);
	window.dsgoInjectIcons();
	expect(global.fetch).toHaveBeenCalledTimes(3);
	jest.advanceTimersByTime(29999);
	window.dsgoInjectIcons();
	expect(global.fetch).toHaveBeenCalledTimes(3);
	jest.advanceTimersByTime(1);
	window.dsgoInjectIcons();
	await settle();
	expect(global.fetch).toHaveBeenCalledTimes(4);
	expect(namesFor(global.fetch.mock.calls[3][0])).toHaveLength(100);
	icons.forEach((icon) => expect(icon.querySelector('svg')).not.toBeNull());
});

test('caches only explicit unknown answers, retrying omitted and invalid values after cooldown', async () => {
	addIcons(1, 'unknown');
	const omitted = addIcons(1, 'omitted')[0];
	const invalid = addIcons(1, 'invalid')[0];
	global.fetch.mockResolvedValueOnce(
		response({ 'unknown-0': '', 'invalid-0': null })
	);
	load();
	await settle();

	expect(window.dsgoIcons['unknown-0']).toBe('');
	expect(window.dsgoIcons).not.toHaveProperty('omitted-0');
	expect(window.dsgoIcons).not.toHaveProperty('invalid-0');
	window.dsgoInjectIcons();
	expect(global.fetch).toHaveBeenCalledTimes(1);
	jest.advanceTimersByTime(30000);
	window.dsgoInjectIcons();
	await settle();
	expect(global.fetch).toHaveBeenCalledTimes(2);
	expect(namesFor(global.fetch.mock.calls[1][0])).toEqual([
		'invalid-0',
		'omitted-0',
	]);
	expect(omitted.querySelector('svg')).not.toBeNull();
	expect(invalid.querySelector('svg')).not.toBeNull();
});

test.each([null, [], 'invalid'])(
	'retries malformed REST answer %p after cooldown without a request storm',
	async (answer) => {
		const icon = addIcons(1)[0];
		global.fetch.mockResolvedValueOnce(response(answer));
		load();
		await settle();

		expect(window.dsgoIcons).not.toHaveProperty('icon-0');
		for (let index = 0; index < 5; index++) {
			window.dsgoInjectIcons();
		}
		expect(global.fetch).toHaveBeenCalledTimes(1);
		jest.advanceTimersByTime(30000);
		window.dsgoInjectIcons();
		await settle();
		expect(global.fetch).toHaveBeenCalledTimes(2);
		expect(icon.querySelector('svg')).not.toBeNull();
	}
);
