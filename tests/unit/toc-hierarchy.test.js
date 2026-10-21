import {
	buildTree,
	buildHierarchy,
} from '../../src/blocks/table-of-contents/utils/hierarchy';
import { generatePrettyId } from '../../src/blocks/table-of-contents/utils/id-generator';

const h = (level, id = `h${level}-${Math.random()}`) => ({
	level,
	id,
	text: id,
});

const render = (headings, tag = 'ul') => {
	const list = document.createElement(tag);
	const items = buildHierarchy(
		headings,
		0,
		(heading) => {
			const li = document.createElement('li');
			li.textContent = heading.id;
			li.dataset.id = heading.id;
			return li;
		},
		list
	);
	items.forEach((i) => list.appendChild(i));
	return list;
};

// Shape: ['a', ['b', 'c']] style string for easy assertions.
const shape = (nodes) =>
	nodes
		.map((n) =>
			n.children.length
				? `${n.heading.id}(${shape(n.children)})`
				: n.heading.id
		)
		.join(',');

describe('buildTree', () => {
	it('nests regular levels', () => {
		const t = buildTree([h(2, 'a'), h(3, 'b'), h(3, 'c'), h(2, 'd')]);
		expect(shape(t)).toBe('a(b,c),d');
	});

	it('keeps headings that skip a level (H2 -> H4)', () => {
		const t = buildTree([h(2, 'a'), h(4, 'b'), h(2, 'c')]);
		expect(shape(t)).toBe('a(b),c');
	});

	it('keeps a leading heading deeper than the shallowest', () => {
		const t = buildTree([h(3, 'a'), h(2, 'b'), h(3, 'c')]);
		expect(shape(t)).toBe('a,b(c)');
	});

	it('handles flat same-level lists', () => {
		expect(shape(buildTree([h(2, 'a'), h(2, 'b'), h(2, 'c')]))).toBe(
			'a,b,c'
		);
	});

	it('handles repeated and returning levels', () => {
		const t = buildTree([
			h(2, 'a'),
			h(3, 'b'),
			h(4, 'c'),
			h(3, 'd'),
			h(4, 'e'),
			h(2, 'f'),
		]);
		expect(shape(t)).toBe('a(b(c),d(e)),f');
	});

	it('handles levels toggled off (only H2 and H5 included)', () => {
		const t = buildTree([h(2, 'a'), h(5, 'b'), h(5, 'c'), h(2, 'd')]);
		expect(shape(t)).toBe('a(b,c),d');
	});

	it('handles empty input', () => {
		expect(buildTree([])).toEqual([]);
	});
});

describe('buildHierarchy', () => {
	it('renders every heading exactly once, with valid nesting', () => {
		const list = render([
			h(2, 'a'),
			h(4, 'b'),
			h(3, 'c'),
			h(1, 'd'),
			h(6, 'e'),
		]);
		const ids = [...list.querySelectorAll('li')].map((li) => li.dataset.id);
		expect(ids.sort()).toEqual(['a', 'b', 'c', 'd', 'e']);
		// Sublists live inside li elements only.
		list.querySelectorAll('ul').forEach((ul) =>
			expect(ul.parentElement.tagName).toBe('LI')
		);
	});

	it('uses ol sublists for an ordered list', () => {
		const list = render([h(2, 'a'), h(3, 'b')], 'ol');
		expect(
			list.querySelector('li > ol.dsgo-table-of-contents__sublist')
		).not.toBeNull();
	});
});

describe('generatePrettyId', () => {
	afterEach(() => {
		document.body.textContent = '';
	});

	it('slugifies Latin text and folds accents', () => {
		expect(generatePrettyId('Café Crème!', new Set())).toBe('cafe-creme');
	});

	it('keeps non-Latin letters', () => {
		expect(generatePrettyId('Введение в тему', new Set())).toBe(
			'введение-в-тему'
		);
		expect(generatePrettyId('日本語の見出し', new Set())).toBe(
			'日本語の見出し'
		);
	});

	it('falls back to "section" for symbol-only text', () => {
		expect(generatePrettyId('!!!', new Set())).toBe('section');
	});

	it('avoids ids already used in the set', () => {
		const used = new Set();
		expect(generatePrettyId('Intro', used)).toBe('intro');
		expect(generatePrettyId('Intro', used)).toBe('intro-1');
	});

	it('avoids ids that exist elsewhere on the page', () => {
		const el = document.createElement('div');
		el.id = 'intro';
		document.body.appendChild(el);
		expect(generatePrettyId('Intro', new Set())).toBe('intro-1');
	});
});
