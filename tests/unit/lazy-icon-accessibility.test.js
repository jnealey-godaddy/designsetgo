/** Icon SVG accessibility ownership. */

const SVG =
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>';

function placeholder(name) {
	const el = document.createElement('span');
	el.className = 'dsgo-lazy-icon';
	el.dataset.iconName = name;
	document.body.appendChild(el);
	return el;
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

	test.each(['filled', 'outlined'])(
		'hides the nested %s SVG without losing a standalone link name',
		(style) => {
			document.body.innerHTML = `<a href="/contact"><span class="dsgo-icon__wrapper dsgo-lazy-icon" data-icon-name="check" data-icon-style="${style}" role="img" aria-label="Contact us"></span></a>`;
			load();
			const { computeAccessibleName } = require('dom-accessibility-api');
			const svg = document.querySelector('svg');
			expect(svg.getAttribute('aria-hidden')).toBe('true');
			expect(svg.getAttribute('focusable')).toBe('false');
			expect(computeAccessibleName(document.querySelector('a'))).toBe(
				'Contact us'
			);
		}
	);

	test.each(['div', 'a'])(
		'hides a list drawing while preserving %s item text',
		(tag) => {
			document.body.innerHTML = `<${tag} class="dsgo-icon-list-item" href="/delivery"><span class="dsgo-icon-list-item__icon dsgo-lazy-icon" data-icon-name="check"></span><div class="dsgo-icon-list-item__content"><p>Free delivery</p></div></${tag}>`;
			load();
			expect(
				document.querySelector('svg').getAttribute('aria-hidden')
			).toBe('true');
			if (tag === 'a') {
				const {
					computeAccessibleName,
				} = require('dom-accessibility-api');
				expect(computeAccessibleName(document.querySelector('a'))).toBe(
					'Free delivery'
				);
			}
		}
	);

	test('does not hide an unlabelled icon-only link or unrelated consumer', () => {
		document.body.innerHTML =
			'<a class="dsgo-icon-list-item" href="/contact"><span class="dsgo-icon-list-item__icon dsgo-lazy-icon" data-icon-name="check"></span><div class="dsgo-icon-list-item__content"></div></a>';
		const other = placeholder('check');
		other.setAttribute('role', 'img');
		other.setAttribute('aria-label', 'Available');
		load();
		expect(
			document.querySelector('a svg').getAttribute('aria-hidden')
		).toBeNull();
		expect(other.querySelector('svg').getAttribute('aria-label')).toBe(
			'Available'
		);
		expect(
			other.querySelector('svg').getAttribute('aria-hidden')
		).toBeNull();
	});

});
