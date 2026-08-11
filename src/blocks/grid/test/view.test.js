/**
 * Grid Block - view.js Tests
 *
 * Covers the runtime "Align Rows" logic: reading the desktop column count,
 * counting a card's content rows, and activating/clearing subgrid matching
 * (publishing `--dsgo-row-count` + the `--rows-matched` class) per breakpoint.
 */

// Requiring view.js executes its IIFE, which registers window.DSGGrid.
require('../view.js');

const { DSGGrid } = window;

/**
 * Build a match-rows grid DOM: N Section cards, each with `rows` content
 * blocks nested under `.dsgo-stack__inner`.
 *
 * @param {Object}  opts        Options.
 * @param {number}  opts.cols   Desktop column count.
 * @param {number}  opts.tablet Tablet column count.
 * @param {number}  opts.mobile Mobile column count.
 * @param {boolean} opts.match  Whether to add the match-rows class.
 * @param {number}  opts.cards  Number of cards.
 * @param {number}  opts.rows   Content blocks per card.
 * @return {HTMLElement} The grid wrapper element (attached to the document).
 */
function buildGrid({
	cols = 3,
	tablet = 2,
	mobile = 1,
	match = true,
	cards = 3,
	rows = 4,
} = {}) {
	const el = document.createElement('div');
	el.className = [
		'dsgo-grid',
		`dsgo-grid-cols-${cols}`,
		`dsgo-grid-cols-tablet-${tablet}`,
		`dsgo-grid-cols-mobile-${mobile}`,
		match && 'dsgo-grid--match-rows',
	]
		.filter(Boolean)
		.join(' ');

	const inner = document.createElement('div');
	inner.className = 'dsgo-grid__inner';

	for (let c = 0; c < cards; c++) {
		const card = document.createElement('div');
		card.className = 'dsgo-stack';
		const wrap = document.createElement('div');
		wrap.className = 'dsgo-stack__inner';
		for (let r = 0; r < rows; r++) {
			wrap.appendChild(document.createElement('div'));
		}
		card.appendChild(wrap);
		inner.appendChild(card);
	}

	el.appendChild(inner);
	document.body.appendChild(el);
	return el;
}

describe('grid view.js - DSGGrid', () => {
	afterEach(() => {
		document.body.innerHTML = '';
	});

	test('exposes DSGGrid on window', () => {
		expect(typeof DSGGrid).toBe('function');
	});

	test('getDesktopColumns reads the desktop column class', () => {
		const grid = new DSGGrid(buildGrid({ cols: 4 }));
		expect(grid.getDesktopColumns()).toBe(4);
	});

	test("countCardRows counts a Section card's content blocks", () => {
		const el = buildGrid({ rows: 4 });
		const grid = new DSGGrid(el);
		const card = el.querySelector('.dsgo-grid__inner').children[0];
		expect(grid.countCardRows(card)).toBe(4);
	});

	test('countCardRows also counts a Flex card (.dsgo-flex__inner)', () => {
		const grid = new DSGGrid(buildGrid());
		const card = document.createElement('div');
		card.className = 'dsgo-flex';
		const wrap = document.createElement('div');
		wrap.className = 'dsgo-flex__inner';
		for (let i = 0; i < 3; i++) {
			wrap.appendChild(document.createElement('div'));
		}
		card.appendChild(wrap);
		expect(grid.countCardRows(card)).toBe(3);
	});

	test('countCardRows counts a Group card by its direct children', () => {
		const grid = new DSGGrid(buildGrid());
		const card = document.createElement('div');
		card.className = 'wp-block-group';
		for (let i = 0; i < 2; i++) {
			card.appendChild(document.createElement('div'));
		}
		expect(grid.countCardRows(card)).toBe(2);
	});

	test('countCardRows returns 0 for an unsupported card (e.g. Card block)', () => {
		const grid = new DSGGrid(buildGrid());
		const card = document.createElement('div');
		card.className = 'dsgo-card';
		const inner = document.createElement('div');
		inner.className = 'dsgo-card__inner';
		card.append(document.createElement('div'), inner); // background + inner
		expect(grid.countCardRows(card)).toBe(0);
	});

	test('applyRowMatching activates with 2+ columns and publishes the row count', () => {
		const grid = new DSGGrid(buildGrid({ cards: 3, rows: 4 }));
		grid.applyRowMatching(3);
		expect(
			grid.inner.classList.contains('dsgo-grid__inner--rows-matched')
		).toBe(true);
		expect(grid.inner.style.getPropertyValue('--dsgo-row-count')).toBe('4');
	});

	test('applyRowMatching clears at a single column', () => {
		const grid = new DSGGrid(buildGrid());
		grid.applyRowMatching(3);
		grid.applyRowMatching(1);
		expect(
			grid.inner.classList.contains('dsgo-grid__inner--rows-matched')
		).toBe(false);
		expect(grid.inner.style.getPropertyValue('--dsgo-row-count')).toBe('');
	});

	test('row count is the max across uneven cards', () => {
		const el = buildGrid({ cards: 2, rows: 4 });
		// Give the second card an extra content block.
		el.querySelectorAll('.dsgo-stack__inner')[1].appendChild(
			document.createElement('div')
		);
		const grid = new DSGGrid(el);
		grid.applyRowMatching(2);
		expect(grid.inner.style.getPropertyValue('--dsgo-row-count')).toBe('5');
	});

	test('does nothing when Align Rows is not enabled', () => {
		const grid = new DSGGrid(buildGrid({ match: false }));
		grid.applyRowMatching(3);
		expect(
			grid.inner.classList.contains('dsgo-grid__inner--rows-matched')
		).toBe(false);
	});
});

describe('grid view.js - rendered column count gates row matching', () => {
	afterEach(() => {
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	});

	/**
	 * jsdom does no layout, so stub the resolved track list the way a browser
	 * would report it: space-separated used values, one per rendered column.
	 *
	 * @param {number} count Rendered column count to report.
	 */
	function stubTracks(count) {
		const real = window.getComputedStyle.bind(window);
		jest.spyOn(window, 'getComputedStyle').mockImplementation((el) => {
			if (el.classList.contains('dsgo-grid__inner')) {
				return { gridTemplateColumns: '364px '.repeat(count).trim() };
			}
			return real(el);
		});
	}

	test('reads the rendered count off the resolved track list', () => {
		stubTracks(2);
		const grid = new DSGGrid(buildGrid({ cols: 3 }));
		expect(grid.getRenderedColumns(3)).toBe(2);
	});

	test('falls back to the configured count when the track list is unreadable', () => {
		jest.spyOn(window, 'getComputedStyle').mockReturnValue({
			gridTemplateColumns: 'none',
		});
		const grid = new DSGGrid(buildGrid({ cols: 3 }));
		expect(grid.getRenderedColumns(3)).toBe(3);
	});

	test('a 3-column grid wrapped to one column does not activate row matching', () => {
		// The regression: cards spanning --dsgo-row-count row tracks in a
		// single-column grid absorb the row gaps and inflate for no benefit.
		stubTracks(1);
		const grid = new DSGGrid(buildGrid({ cols: 3, cards: 3, rows: 4 }));
		grid.handleResize();
		expect(
			grid.inner.classList.contains('dsgo-grid__inner--rows-matched')
		).toBe(false);
		expect(grid.inner.style.getPropertyValue('--dsgo-row-count')).toBe('');
	});

	test('a 3-column grid still rendering 3 columns activates as before', () => {
		stubTracks(3);
		const grid = new DSGGrid(buildGrid({ cols: 3, cards: 3, rows: 4 }));
		grid.handleResize();
		expect(
			grid.inner.classList.contains('dsgo-grid__inner--rows-matched')
		).toBe(true);
		expect(grid.inner.style.getPropertyValue('--dsgo-row-count')).toBe('4');
	});
});

describe('grid view.js - measurement is skipped without Align Rows', () => {
	afterEach(() => {
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	});

	test('does not measure when Align Rows is off', () => {
		// getComputedStyle forces a synchronous layout flush, and only Align
		// Rows consumes the result — a page full of plain grids must not pay
		// for a reflow each on every resize.
		const grid = new DSGGrid(buildGrid({ match: false }));
		const spy = jest.spyOn(grid, 'getRenderedColumns');
		grid.handleResize();
		expect(spy).not.toHaveBeenCalled();
	});

	test('still measures when Align Rows is on', () => {
		const grid = new DSGGrid(buildGrid({ match: true }));
		const spy = jest.spyOn(grid, 'getRenderedColumns');
		grid.handleResize();
		expect(spy).toHaveBeenCalled();
	});
});
