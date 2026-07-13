/**
 * Tabs deep-linking must survive a hostile URL fragment.
 *
 * The fragment is whatever is in the address bar, and it was passed straight to
 * `querySelector('#' + hash)`. A fragment is NOT a valid CSS selector in the
 * general case: `#2024`, `#a b` and `#!` all make querySelector throw
 * SyntaxError. That throw happened inside init(), so a single malformed link
 * — or an ordinary year-numbered anchor from elsewhere on the page — took out
 * EVERY tabs block on the page, not just the deep link.
 */

const TABS_HTML = `
	<div class="wp-block-designsetgo-tabs" data-active-tab="0" data-deep-linking="true">
		<div class="dsgo-tabs__nav"></div>
		<div class="dsgo-tab" id="panel-one" aria-label="One"></div>
		<div class="dsgo-tab" id="panel-two" aria-label="Two"></div>
	</div>
`;

const mount = (hash) => {
	window.location.hash = hash;
	document.body.innerHTML = TABS_HTML;
	return document.querySelector('.wp-block-designsetgo-tabs');
};

describe('tabs deep linking', () => {
	beforeAll(() => {
		require('../view.js');
	});

	afterEach(() => {
		document.body.innerHTML = '';
		window.location.hash = '';
	});

	it.each([
		['a numeric fragment', '#2024'],
		['a fragment with a space', '#a b'],
		['a bare bang', '#!'],
		['a selector-ish fragment', '#a>b'],
		['a quote', '#a"b'],
	])('does not throw on %s', (_label, hash) => {
		const el = mount(hash);

		expect(() => new window.DSGTabs(el)).not.toThrow();

		// And the block is still alive: navigation got built.
		expect(el.querySelectorAll('.dsgo-tabs__tab')).toHaveLength(2);
	});

	it('still resolves a legitimate deep link to the right panel', () => {
		const el = mount('#panel-two');

		new window.DSGTabs(el);

		const second = el.querySelector('#panel-two');
		expect(second.classList.contains('is-active')).toBe(true);
	});

	it('falls back to the first tab when the fragment matches nothing', () => {
		const el = mount('#panel-nope');

		new window.DSGTabs(el);

		const first = el.querySelector('#panel-one');
		expect(first.classList.contains('is-active')).toBe(true);
	});
});
