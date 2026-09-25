/**
 * Lazy icon injector: PHP now prints only the icons a page rendered, so an
 * icon that arrives later (Query "load more", soft navigation) must be
 * fetched from the icons REST route, once.
 */

const SVG =
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>';

function placeholder(name) {
	const el = document.createElement('span');
	el.className = 'dsgo-lazy-icon';
	el.dataset.iconName = name;
	document.body.appendChild(el);
	return el;
}

function flush() {
	return new Promise((resolve) => setTimeout(resolve, 0));
}

describe('lazy icon injector', () => {
	const NativeMutationObserver = global.MutationObserver;
	const setupFetch = global.fetch;

	beforeEach(() => {
		// Each test re-requires the module; without this, observers left by
		// earlier loads would run their own injection passes (and fetches).
		global.MutationObserver = undefined;
		document.body.innerHTML = '';
		window.dsgoIcons = { check: SVG, gone: '' };
		window.dsgoIconsRest = {
			url: 'https://example.com/?rest_route=/designsetgo/v1/icons',
			version: '2.8.3',
		};
		global.fetch = jest.fn(() =>
			Promise.resolve({
				ok: true,
				json: () => Promise.resolve({ star: SVG, nope: '' }),
			})
		);
	});

	afterEach(() => {
		global.MutationObserver = NativeMutationObserver;
		global.fetch = setupFetch;
		delete window.dsgoIcons;
		delete window.dsgoIconsRest;
	});

	function load() {
		jest.isolateModules(() => {
			require('../../src/frontend/lazy-icon-injector.js');
		});
	}

	test('injects printed icons without a request', () => {
		const el = placeholder('check');
		load();

		expect(el.querySelector('svg')).not.toBeNull();
		expect(global.fetch).not.toHaveBeenCalled();
	});

	test('does not fetch names the server already said are unknown', () => {
		placeholder('gone');
		load();

		expect(global.fetch).not.toHaveBeenCalled();
	});

	test('fetches missing icons once, then injects them', async () => {
		const star = placeholder('Star');
		placeholder('nope');
		load();

		expect(global.fetch).toHaveBeenCalledTimes(1);
		const url = new URL(global.fetch.mock.calls[0][0]);
		expect(url.searchParams.get('rest_route')).toBe(
			'/designsetgo/v1/icons'
		);
		expect(url.searchParams.get('names')).toBe('nope,star');
		expect(url.searchParams.get('ver')).toBe('2.8.3');

		await flush();
		expect(star.querySelector('svg')).not.toBeNull();

		// A later scan (e.g. MutationObserver) must not refetch either name.
		window.dsgoInjectIcons();
		expect(global.fetch).toHaveBeenCalledTimes(1);
	});
});
