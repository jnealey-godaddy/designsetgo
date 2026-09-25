/**
 * Soft-navigation asset loader: content swapped in without a page load must
 * still get the block and extension assets it needs.
 */

describe('soft-navigation asset loader', () => {
	let dispatched;
	let loadMissingAssets;
	const record = (event) => dispatched.push(event.detail?.source);

	const manifest = () => ({
		triggers: [
			{
				selector: '.wp-block-designsetgo-tabs',
				scripts: ['designsetgo-tabs-view-script'],
				styles: ['designsetgo-tabs-style'],
			},
			{
				selector: '.wp-block-designsetgo-form-builder',
				scripts: ['designsetgo-form-builder-view-script'],
			},
			{
				needles: ['has-dsgo-animation'],
				scripts: ['designsetgo-ext-block-animations'],
			},
			{
				selector: '.wp-block-designsetgo-map',
				scripts: ['designsetgo-map-view-script'],
			},
		],
		scripts: {
			'designsetgo-tabs-view-script': {
				deps: [],
				src: 'https://example.com/tabs.js',
			},
			'designsetgo-form-builder-view-script': {
				deps: ['wp-i18n'],
				src: 'https://example.com/form.js',
				before: 'window.designsetgoForm = { restUrl: "/x" };',
			},
			'wp-i18n': {
				deps: [],
				src: 'https://example.com/i18n.js',
				after: 'window.i18nAfterRan = true;',
			},
			'designsetgo-ext-block-animations': {
				deps: [],
				src: 'https://example.com/anim.js',
			},
			// Depends on something neither on the page nor loadable.
			'designsetgo-map-view-script': {
				deps: ['not-loadable'],
				src: 'https://example.com/map.js',
			},
		},
		styles: {
			'designsetgo-tabs-style': {
				href: 'https://example.com/tabs.css',
				after: '.x{color:red}',
			},
		},
	});

	/**
	 * Settle the loader's promise chain, firing each injected script's
	 * onload as it appears (jsdom doesn't fetch scripts).
	 */
	async function settle() {
		for (let i = 0; i < 10; i++) {
			document
				.querySelectorAll('script[src]:not([data-fired])')
				.forEach((script) => {
					script.dataset.fired = 'true';
					script.onload();
				});
			await Promise.resolve();
		}
	}

	beforeEach(() => {
		document.head.innerHTML = '';
		document.body.innerHTML = '';
		dispatched = [];
		window.dsgoAssets = manifest();
		jest.isolateModules(() => {
			({
				loadMissingAssets,
			} = require('../../src/utils/soft-nav-assets.js'));
		});
		document.addEventListener('dsgo-content-loaded', record);
	});

	afterEach(() => {
		document.removeEventListener('dsgo-content-loaded', record);
		delete window.dsgoAssets;
		delete window.designsetgoForm;
		delete window.i18nAfterRan;
	});

	test('loads a swapped-in block’s script and style, then re-dispatches', async () => {
		document.body.innerHTML =
			'<div class="wp-block-designsetgo-tabs"></div>';
		const done = loadMissingAssets();
		await settle();
		await done;

		expect(
			document.getElementById('designsetgo-tabs-view-script-js').src
		).toBe('https://example.com/tabs.js');
		expect(document.getElementById('designsetgo-tabs-style-css').href).toBe(
			'https://example.com/tabs.css'
		);
		expect(
			document.getElementById('designsetgo-tabs-style-inline-css')
				.textContent
		).toBe('.x{color:red}');
		expect(dispatched).toContain('dsgo-bundles');
		// Nothing else was needed.
		expect(document.querySelectorAll('script[src]')).toHaveLength(1);
	});

	test('loads dependencies first and runs inline data around each script', async () => {
		document.body.innerHTML =
			'<form class="wp-block-designsetgo-form-builder"></form>';
		const done = loadMissingAssets();
		await Promise.resolve();
		await Promise.resolve();

		// The dependency is injected before the script that needs it.
		expect(document.getElementById('wp-i18n-js')).not.toBeNull();
		expect(
			document.getElementById('designsetgo-form-builder-view-script-js')
		).toBeNull();

		await settle();
		await done;

		expect(window.i18nAfterRan).toBe(true);
		expect(
			document.getElementById(
				'designsetgo-form-builder-view-script-js-before'
			).textContent
		).toContain('designsetgoForm');
		expect(
			document.getElementById('designsetgo-form-builder-view-script-js')
		).not.toBeNull();
	});

	test('extensions match on needles, blocks on their exact class', async () => {
		document.body.innerHTML =
			'<p class="has-dsgo-animation"></p><div class="wp-block-designsetgo-tabs-extra"></div>';
		const done = loadMissingAssets();
		await settle();
		await done;

		expect(
			document.getElementById('designsetgo-ext-block-animations-js')
		).not.toBeNull();
		expect(
			document.getElementById('designsetgo-tabs-view-script-js')
		).toBeNull();
	});

	test('skips a script whose dependency cannot be satisfied', async () => {
		document.body.innerHTML =
			'<div class="wp-block-designsetgo-map"></div>';
		const done = loadMissingAssets();
		await settle();
		await done;

		expect(
			document.getElementById('designsetgo-map-view-script-js')
		).toBeNull();
		expect(dispatched).not.toContain('dsgo-bundles');
	});

	test('leaves styles WordPress or critical CSS already put on the page', () => {
		const critical = document.createElement('style');
		critical.dataset.dsgoHandles =
			'designsetgo-grid-style designsetgo-tabs-style';
		document.head.appendChild(critical);
		document.body.innerHTML =
			'<div class="wp-block-designsetgo-tabs"></div>';

		loadMissingAssets();

		expect(
			document.getElementById('designsetgo-tabs-style-css')
		).toBeNull();
	});

	test('runs on dsgo-content-loaded, except bfcache and its own re-dispatch', async () => {
		document.body.innerHTML =
			'<div class="wp-block-designsetgo-tabs"></div>';

		document.dispatchEvent(
			new CustomEvent('dsgo-content-loaded', {
				detail: { source: 'bfcache' },
			})
		);
		document.dispatchEvent(
			new CustomEvent('dsgo-content-loaded', {
				detail: { source: 'dsgo-bundles' },
			})
		);
		expect(
			document.getElementById('designsetgo-tabs-view-script-js')
		).toBeNull();

		await Promise.resolve();
		expect(
			document.getElementById('designsetgo-tabs-view-script-js')
		).toBeNull();

		document.dispatchEvent(new CustomEvent('dsgo-content-loaded'));
		await Promise.resolve();
		await Promise.resolve();
		expect(
			document.getElementById('designsetgo-tabs-view-script-js')
		).not.toBeNull();
	});
});
