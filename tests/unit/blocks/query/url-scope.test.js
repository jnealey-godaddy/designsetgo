/**
 * Unit tests for src/blocks/query/url-scope.js — per-Query URL params.
 *
 * Each case starts from a URL a visitor can really arrive at: a bookmark or
 * menu link with bare keys, a page where another Query already wrote its own
 * scoped keys, or both.
 */

import {
	pageQueryIds,
	splitKey,
	readOwned,
	writeOwned,
	resetOwnPage,
	removeOwnedValue,
	resetOwned,
	normalizeListKeys,
} from '../../../../src/blocks/query/url-scope.js';

const A = 'qa1a1a1a1';
const B = 'qb2b2b2b2';

const params = (qs) => new URLSearchParams(qs);
// Decoded, so expectations read like the address bar.
const str = (p) => decodeURIComponent(p.toString());

describe('pageQueryIds', () => {
	afterEach(() => {
		document.body.replaceChildren();
	});

	it('counts each Query region once', () => {
		document.body.insertAdjacentHTML(
			'afterbegin',
			`<div data-dsgo-query-region="${A}"></div><div data-dsgo-query-region="${B}"></div><div data-dsgo-query-region="${A}"></div>`
		);
		expect(pageQueryIds(document)).toEqual([A, B]);
	});
});

describe('splitKey', () => {
	it('splits a generated editor id', () => {
		expect(splitKey(`filter_category__${B}`, A)).toEqual({
			bare: 'filter_category',
			scope: B,
		});
	});

	it('splits a template-import id', () => {
		expect(splitKey('q__q-0123456789')).toEqual({
			bare: 'q',
			scope: 'q-0123456789',
		});
	});

	it('keeps a taxonomy with a double underscore bare', () => {
		expect(splitKey('filter_my__tax', A)).toEqual({
			bare: 'filter_my__tax',
			scope: '',
		});
	});

	it('recognises the caller’s own and known custom ids', () => {
		expect(splitKey('q__related', 'related').scope).toBe('related');
		expect(splitKey('q__featured', A, ['featured']).scope).toBe('featured');
		expect(splitKey('q__featured', A).scope).toBe('');
	});
});

describe('readOwned', () => {
	it('inherits a bare value', () => {
		expect(
			readOwned(params('filter_category[]=city'), 'filter_category', A)
		).toEqual({
			values: ['city'],
			isArray: true,
		});
	});

	it('prefers its own scoped value, and an empty one clears the bare value', () => {
		expect(readOwned(params(`q=bare&q__${A}=mine`), 'q', A).values).toEqual(
			['mine']
		);
		expect(readOwned(params(`q=bare&q__${A}=`), 'q', A).values).toEqual([]);
	});

	it('never reads another Query’s key', () => {
		expect(readOwned(params(`q__${B}=theirs`), 'q', A).values).toEqual([]);
	});
});

describe('writeOwned on a page with several Queries', () => {
	it('adds to a bookmarked bare value instead of replacing it', () => {
		const p = params('filter_category[]=city');
		writeOwned(p, {
			bare: 'filter_category',
			queryId: A,
			values: ['city', 'coast'],
			multi: true,
			isArray: true,
		});
		expect(str(p)).toBe(
			`filter_category[]=city&filter_category__${A}[]=city&filter_category__${A}[]=coast`
		);
	});

	it('clears an inherited bare value for this Query only', () => {
		const p = params('filter_category[]=city');
		writeOwned(p, {
			bare: 'filter_category',
			queryId: A,
			values: [],
			multi: true,
			isArray: true,
		});
		expect(readOwned(p, 'filter_category', A).values).toEqual([]);
		expect(readOwned(p, 'filter_category', B).values).toEqual(['city']);
	});

	it('removes its own key when there is nothing to shadow', () => {
		const p = params(`sort__${A}=title.ASC`);
		writeOwned(p, { bare: 'sort', queryId: A, values: [''], multi: true });
		expect(str(p)).toBe('');
	});

	it('leaves another Query’s keys alone', () => {
		const p = params(`filter_category__${B}=coast`);
		writeOwned(p, {
			bare: 'filter_category',
			queryId: A,
			values: ['city'],
			multi: true,
			isArray: true,
		});
		expect(p.get(`filter_category__${B}`)).toBe('coast');
	});
});

describe('writeOwned on a page with one Query', () => {
	it('writes the plain key so ?q= stays readable', () => {
		const p = params(`q__${A}=old`);
		writeOwned(p, {
			bare: 'q',
			queryId: A,
			values: ['shoes'],
			multi: false,
		});
		expect(str(p)).toBe('q=shoes');
	});

	it('replaces a comma-style bookmark with the list', () => {
		const p = params('filter_category=city,coast');
		writeOwned(p, {
			bare: 'filter_category',
			queryId: A,
			values: ['coast'],
			multi: false,
			isArray: true,
		});
		expect(str(p)).toBe('filter_category[]=coast');
	});
});

describe('resetOwnPage', () => {
	it('drops paged and page on a one-Query page', () => {
		const p = params('paged=3&page=2&x=1');
		resetOwnPage(p, A, false);
		expect(str(p)).toBe('x=1');
	});

	it('pins this Query to page 1 without paging the others', () => {
		const p = params(`paged=3&qpage__${A}=4&qpage__${B}=2`);
		resetOwnPage(p, A, true);
		expect(p.get('paged')).toBe('3');
		expect(p.get(`qpage__${A}`)).toBe('1');
		expect(p.get(`qpage__${B}`)).toBe('2');
	});

	it('handles /page/N/ in the path', () => {
		const p = params('');
		resetOwnPage(p, A, true, '/blog/page/2/');
		expect(p.get(`qpage__${A}`)).toBe('1');
	});

	it('just drops its own page when nothing is shared', () => {
		const p = params(`qpage__${A}=4`);
		resetOwnPage(p, A, true, '/blog/');
		expect(str(p)).toBe('');
	});
});

describe('removeOwnedValue (chip)', () => {
	it('removes a bookmarked value for this Query only', () => {
		const p = params('filter_category[]=city&filter_category[]=coast');
		removeOwnedValue(p, {
			bare: 'filter_category',
			value: 'city',
			queryId: A,
			multi: true,
		});
		expect(readOwned(p, 'filter_category', A).values).toEqual(['coast']);
		expect(readOwned(p, 'filter_category', B).values).toEqual([
			'city',
			'coast',
		]);
	});
});

describe('resetOwned (Reset)', () => {
	it('clears this Query and keeps the other Query’s filters', () => {
		const p = params(
			`filter_category=city&q__${A}=shoe&filter_category__${B}=coast&page_id=5`
		);
		resetOwned(p, A, true, [A, B]);
		expect(readOwned(p, 'filter_category', A).values).toEqual([]);
		expect(readOwned(p, 'q', A).values).toEqual([]);
		expect(p.get(`filter_category__${B}`)).toBe('coast');
		expect(readOwned(p, 'filter_category', B).values).toEqual(['coast']);
		expect(p.get('page_id')).toBe('5');
	});

	it('removes the plain keys on a one-Query page', () => {
		const p = params('filter_tag[]=a&q=x&sort=date&utm=keep');
		resetOwned(p, A, false, [A]);
		expect(str(p)).toBe('utm=keep');
	});
});

describe('normalizeListKeys', () => {
	it('rewrites the indexed keys WordPress pagination links write', () => {
		const p = params(
			'page_id=5&filter_category[0]=city&filter_category[1]=coast'
		);
		normalizeListKeys(p);
		expect(str(p)).toBe(
			'page_id=5&filter_category[]=city&filter_category[]=coast'
		);
		expect(readOwned(p, 'filter_category', A).values).toEqual([
			'city',
			'coast',
		]);
	});
});
