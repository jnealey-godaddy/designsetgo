/**
 * IntersectionObserver bookkeeping.
 *
 * In its own file because the runtime keeps the observer in module scope —
 * these tests need a fresh module registry and a mock installed before the
 * first initInteractions() call.
 */

const SPEC = JSON.stringify([
	{
		id: 'a',
		trigger: 'inView',
		targetMode: 'self',
		action: 'addClass',
		value: 'seen',
	},
]);

const ONCE_SPEC = JSON.stringify([
	{
		id: 'a',
		trigger: 'inView',
		targetMode: 'self',
		action: 'addClass',
		value: 'seen',
		once: true,
	},
]);

describe('inView observer lifecycle', () => {
	let observe;
	let unobserve;
	let callback;
	let initInteractions;

	beforeEach(() => {
		jest.resetModules();
		observe = jest.fn();
		unobserve = jest.fn();
		window.IntersectionObserver = jest.fn(function (cb) {
			callback = cb;
			return { observe, unobserve };
		});
		document.body.innerHTML = '';
		// Imported after the mock is installed, so the module-scoped observer
		// is built from it.
		initInteractions =
			require('../../../src/extensions/interactions/frontend').initInteractions;
	});

	afterEach(() => {
		delete window.IntersectionObserver;
	});

	it('observes an element with an inView interaction', () => {
		document.body.innerHTML = `<div id="a" data-dsgo-interactions='${SPEC}'></div>`;
		initInteractions();
		expect(observe).toHaveBeenCalledWith(document.getElementById('a'));
	});

	it('does not observe elements without an inView interaction', () => {
		const clickSpec = JSON.stringify([
			{ id: 'c', trigger: 'click', targetMode: 'self', action: 'show' },
		]);
		document.body.innerHTML = `<div data-dsgo-interactions='${clickSpec}'></div>`;
		initInteractions();
		expect(observe).not.toHaveBeenCalled();
	});

	it('stops observing elements a DOM swap detached', () => {
		// IntersectionObserver holds a strong reference to its targets, so a
		// soft-reload navigation would otherwise keep every replaced element
		// and its subtree alive for the life of the tab.
		document.body.innerHTML = `<div id="old" data-dsgo-interactions='${SPEC}'></div>`;
		const old = document.getElementById('old');
		initInteractions();
		expect(observe).toHaveBeenCalledWith(old);

		document.body.innerHTML = `<div id="fresh" data-dsgo-interactions='${SPEC}'></div>`;
		initInteractions();

		expect(old.isConnected).toBe(false);
		expect(unobserve).toHaveBeenCalledWith(old);
		expect(observe).toHaveBeenCalledWith(document.getElementById('fresh'));
	});

	it('keeps observing elements that survived the swap', () => {
		document.body.innerHTML = `<div id="keep" data-dsgo-interactions='${SPEC}'></div>`;
		const keep = document.getElementById('keep');
		initInteractions();
		initInteractions();

		expect(unobserve).not.toHaveBeenCalledWith(keep);
	});

	it('unobserves a one-shot interaction once it has fired', () => {
		document.body.innerHTML = `<div id="o" data-dsgo-interactions='${ONCE_SPEC}'></div>`;
		const el = document.getElementById('o');
		initInteractions();

		callback([{ isIntersecting: true, target: el }]);

		expect(el.classList.contains('seen')).toBe(true);
		expect(unobserve).toHaveBeenCalledWith(el);
	});

	it('keeps observing a repeatable interaction after it fires', () => {
		document.body.innerHTML = `<div id="r" data-dsgo-interactions='${SPEC}'></div>`;
		const el = document.getElementById('r');
		initInteractions();

		callback([{ isIntersecting: true, target: el }]);

		expect(unobserve).not.toHaveBeenCalled();
	});

	it('does nothing when the browser has no IntersectionObserver', () => {
		jest.resetModules();
		delete window.IntersectionObserver;
		const fresh =
			require('../../../src/extensions/interactions/frontend').initInteractions;

		document.body.innerHTML = `<div data-dsgo-interactions='${SPEC}'></div>`;
		expect(() => fresh()).not.toThrow();
	});
});
