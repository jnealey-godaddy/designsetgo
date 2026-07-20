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
