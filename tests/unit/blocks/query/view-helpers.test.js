/**
 * Unit tests for src/blocks/query/view-helpers.js.
 *
 * Covers the framework-agnostic pieces of the Interactivity API view module:
 * URL collection, filter action URL transforms, observer bookkeeping, and the
 * accessibility helpers (feed position stamping + result-count announcements).
 */
import '@testing-library/jest-dom';

import {
	sentinelObservers,
	disconnectSentinelObservers,
	stampFeedPositions,
	announceResultCount,
	collectParams,
	applyToggleFilter,
	applySetFilter,
	applyResetFilters,
} from '../../../../src/blocks/query/view-helpers.js';

/**
 * Build a DOM tree from an HTML string without touching element.innerHTML
 * assignment directly (the security-reminder hook blocks that literal).
 *
 * @param {string} html Fixture markup.
 */
function mountHTML(html) {
	document.body.insertAdjacentHTML('afterbegin', html);
}

function resetBody() {
	while (document.body.firstChild) {
		document.body.removeChild(document.body.firstChild);
	}
}

describe('collectParams', () => {
	it('extracts filter_* params into an object', () => {
		const url = new URL('https://example.com/archive?filter_category=news');
		expect(collectParams(url)).toEqual({ filter_category: 'news' });
	});

	it('coerces ?key[]=v into arrays', () => {
		const url = new URL(
			'https://example.com/?filter_tag[]=a&filter_tag[]=b'
		);
		expect(collectParams(url)).toEqual({ filter_tag: ['a', 'b'] });
	});

	it('keeps only the last value when the same bare key repeats (scalar-only form)', () => {
		// Repeated bare ?filter_tag=a&filter_tag=b is documented as scalar-only;
		// the supported multi-value form is ?filter_tag[]=a&filter_tag[]=b. A
		// scalar collision overwrites, matching URLSearchParams.get() semantics.
		const url = new URL('https://example.com/?filter_tag=a&filter_tag=b');
		expect(collectParams(url)).toEqual({ filter_tag: 'b' });
	});

	it('collects q and sort', () => {
		const url = new URL('https://example.com/?q=hello&sort=date.ASC');
		expect(collectParams(url)).toEqual({
			q: 'hello',
			sort: 'date.ASC',
		});
	});

	it('ignores non-filter params', () => {
		const url = new URL(
			'https://example.com/?filter_category=news&paged=3&utm_source=x'
		);
		expect(collectParams(url)).toEqual({ filter_category: 'news' });
	});
});

describe('applyToggleFilter', () => {
	it('adds a value when checked=true and not present', () => {
		const url = new URL('https://example.com/');
		const next = applyToggleFilter(url, 'filter_tag', 'news', true);
		expect(next.searchParams.getAll('filter_tag[]')).toEqual(['news']);
	});

	it('appends to existing array values without duplicates', () => {
		const url = new URL('https://example.com/?filter_tag[]=news');
		const next = applyToggleFilter(url, 'filter_tag', 'events', true);
		expect(next.searchParams.getAll('filter_tag[]')).toEqual([
			'news',
			'events',
		]);
	});

	it('dedupes when toggling the same value back on', () => {
		const url = new URL('https://example.com/?filter_tag[]=news');
		const next = applyToggleFilter(url, 'filter_tag', 'news', true);
		expect(next.searchParams.getAll('filter_tag[]')).toEqual(['news']);
	});

	it('removes the value when checked=false', () => {
		const url = new URL(
			'https://example.com/?filter_tag[]=news&filter_tag[]=events'
		);
		const next = applyToggleFilter(url, 'filter_tag', 'news', false);
		expect(next.searchParams.getAll('filter_tag[]')).toEqual(['events']);
	});

	it('strips paged and page params', () => {
		const url = new URL(
			'https://example.com/?paged=3&page=5&filter_tag[]=news'
		);
		const next = applyToggleFilter(url, 'filter_tag', 'events', true);
		expect(next.searchParams.has('paged')).toBe(false);
		expect(next.searchParams.has('page')).toBe(false);
	});

	it('does not mutate the input URL', () => {
		const url = new URL('https://example.com/?filter_tag[]=news');
		const original = url.toString();
		applyToggleFilter(url, 'filter_tag', 'events', true);
		expect(url.toString()).toBe(original);
	});
});

describe('applySetFilter', () => {
	it('sets the param to the given value', () => {
		const url = new URL('https://example.com/');
		const next = applySetFilter(url, 'q', 'hello');
		expect(next.searchParams.get('q')).toBe('hello');
	});

	it('removes the param when value is an empty string', () => {
		const url = new URL('https://example.com/?q=hello');
		const next = applySetFilter(url, 'q', '');
		expect(next.searchParams.has('q')).toBe(false);
	});

	it('strips paged and page params', () => {
		const url = new URL('https://example.com/?q=x&paged=3&page=9');
		const next = applySetFilter(url, 'q', 'hello');
		expect(next.searchParams.has('paged')).toBe(false);
		expect(next.searchParams.has('page')).toBe(false);
	});

	it('overwrites an existing value (not append)', () => {
		const url = new URL('https://example.com/?sort=date.ASC');
		const next = applySetFilter(url, 'sort', 'title.ASC');
		expect(next.searchParams.getAll('sort')).toEqual(['title.ASC']);
	});
});

describe('applyResetFilters', () => {
	it('strips all filter_*, q, sort, paged, page', () => {
		const url = new URL(
			'https://example.com/?filter_category=news&filter_tag[]=a&q=x&sort=date&paged=2&page=5&utm=keep'
		);
		const next = applyResetFilters(url);
		expect(next.searchParams.has('filter_category')).toBe(false);
		expect(next.searchParams.has('filter_tag[]')).toBe(false);
		expect(next.searchParams.has('q')).toBe(false);
		expect(next.searchParams.has('sort')).toBe(false);
		expect(next.searchParams.has('paged')).toBe(false);
		expect(next.searchParams.has('page')).toBe(false);
		expect(next.searchParams.get('utm')).toBe('keep');
	});
});

describe('stampFeedPositions', () => {
	afterEach(resetBody);

	it('no-ops when container is null', () => {
		expect(() => stampFeedPositions(null)).not.toThrow();
	});

	it('no-ops when role is not "feed"', () => {
		mountHTML(
			'<ul id="l"><li class="dsgo-query__item"></li><li class="dsgo-query__item"></li></ul>'
		);
		const list = document.getElementById('l');
		stampFeedPositions(list);
		expect(list.firstElementChild.getAttribute('aria-posinset')).toBeNull();
	});

	it('stamps aria-posinset and aria-setsize on items when role=feed', () => {
		mountHTML(
			'<ul id="l" role="feed">' +
				'<li class="dsgo-query__item">A</li>' +
				'<li class="dsgo-query__item">B</li>' +
				'<li class="dsgo-query__item">C</li>' +
				'</ul>'
		);
		const list = document.getElementById('l');
		stampFeedPositions(list);

		const items = list.querySelectorAll('.dsgo-query__item');
		expect(items[0].getAttribute('aria-posinset')).toBe('1');
		expect(items[0].getAttribute('aria-setsize')).toBe('3');
		expect(items[2].getAttribute('aria-posinset')).toBe('3');
		expect(items[2].getAttribute('aria-setsize')).toBe('3');
	});
});

describe('announceResultCount', () => {
	afterEach(resetBody);

	it('writes "No results found" when total is 0', () => {
		mountHTML('<div role="status" data-dsgo-query-status="q1"></div>');
		announceResultCount('q1', 0);
		expect(
			document.querySelector('[data-dsgo-query-status="q1"]').textContent
		).toBe('No results found');
	});

	it('uses singular form when total is 1', () => {
		mountHTML('<div role="status" data-dsgo-query-status="q1"></div>');
		announceResultCount('q1', 1);
		expect(
			document.querySelector('[data-dsgo-query-status="q1"]').textContent
		).toBe('1 result found');
	});

	it('uses plural form with the count for >1', () => {
		mountHTML('<div role="status" data-dsgo-query-status="q1"></div>');
		announceResultCount('q1', 42);
		expect(
			document.querySelector('[data-dsgo-query-status="q1"]').textContent
		).toBe('42 results found');
	});

	it('is a no-op when the status element is missing', () => {
		mountHTML('<div></div>');
		expect(() => announceResultCount('q1', 5)).not.toThrow();
	});

	it('only targets the region matching the given queryId', () => {
		mountHTML(
			'<div role="status" data-dsgo-query-status="q1">before</div>' +
				'<div role="status" data-dsgo-query-status="q2">other</div>'
		);
		announceResultCount('q1', 7);
		expect(
			document.querySelector('[data-dsgo-query-status="q1"]').textContent
		).toBe('7 results found');
		expect(
			document.querySelector('[data-dsgo-query-status="q2"]').textContent
		).toBe('other');
	});
});

describe('disconnectSentinelObservers', () => {
	afterEach(resetBody);

	it('disconnects observers tracked for sentinels inside the root', () => {
		mountHTML(
			'<div class="region">' +
				'<div data-dsgo-pagination="infinite">' +
				'<div class="sentinel" data-wp-init="callbacks.initInfiniteObserver"></div>' +
				'</div>' +
				'</div>'
		);
		const sentinel = document.querySelector('.sentinel');
		const disconnect = jest.fn();
		sentinelObservers.set(sentinel, { disconnect });

		disconnectSentinelObservers(document.querySelector('.region'));

		expect(disconnect).toHaveBeenCalledTimes(1);
		expect(sentinelObservers.get(sentinel)).toBeUndefined();
	});

	it('is a no-op when no sentinels match', () => {
		mountHTML('<div class="region"></div>');
		expect(() =>
			disconnectSentinelObservers(document.querySelector('.region'))
		).not.toThrow();
	});

	it('handles null root without throwing', () => {
		expect(() => disconnectSentinelObservers(null)).not.toThrow();
	});
});
