/**
 * Dynamic Query — Interactivity API view script.
 *
 * Handles load-more pagination and filter/sort/reset actions.
 * All filter actions share a common dsgoQueryRefresh() helper that fetches
 * fresh HTML from the REST endpoint and swaps the list content in place,
 * then syncs the URL via history.replaceState.
 *
 * No-JS fallback: filter controls live inside <form method="get">, so
 * submitting without JS reloads the page with the new URL params.
 *
 * @since 2.1.0
 */
/* global HTMLElement, HTMLInputElement, HTMLSelectElement, DOMParser, IntersectionObserver */
import { store, getContext, getElement } from '@wordpress/interactivity';
import {
	sentinelObservers as dsgoSentinelObservers,
	disconnectSentinelObservers as dsgoDisconnectSentinelObservers,
	stampFeedPositions as dsgoStampFeedPositions,
	announceResultCount as dsgoAnnounceResultCount,
	collectParams as dsgoCollectParamsShared,
	itemContainerSelector as dsgoItemContainerSelector,
	extractRenderedItems as dsgoExtractRenderedItems,
	notifyContentUpdated as dsgoNotifyContentUpdated,
	notifyItemsAppended as dsgoNotifyItemsAppended,
	markHandledEvent as dsgoMarkHandledEvent,
	isHandledEvent as dsgoIsHandledEvent,
	readRefreshSource as dsgoReadRefreshSource,
	buildRefreshRequest as dsgoBuildRefreshRequest,
} from './view-helpers.js';
import {
	pageQueryIds as dsgoPageQueryIds,
	writeOwned as dsgoWriteOwned,
	removeOwnedValue as dsgoRemoveOwnedValue,
	resetOwned as dsgoResetOwned,
	resetOwnPage as dsgoResetOwnPage,
	normalizeListKeys as dsgoNormalizeListKeys,
} from './url-scope.js';

// Query IDs with an in-flight delegated refresh. The delegated handlers build
// a fresh ctx object from the DOM each call, so the ctx.busy guard inside
// dsgoQueryRefreshPlain is effectively a no-op for them — this module-level
// Set is what actually serialises rapid delegated interactions per queryId.
// IAPI-action callers pass a stable reactive ctx and do not go through here.
const dsgoDelegatedBusy = new Set();

store('designsetgo/query', {
	actions: {
		// ----------------------------------------------------------------
		// Pagination
		// ----------------------------------------------------------------
		*loadMore(event) {
			dsgoMarkHandledEvent(event);
			const ctx = getContext();
			const { ref } = getElement(); // the button itself
			yield dsgoLoadMorePlain(ctx, ref);
		},

		// ----------------------------------------------------------------
		// Filter actions (Task 14)
		// ----------------------------------------------------------------

		/**
		 * Handle change on a select, or submit of a search form.
		 *
		 * @param {Event} event
		 * @generator
		 */
		*setFilter(event) {
			event.preventDefault?.();
			dsgoMarkHandledEvent(event);
			const ctx = getContext();
			const url = dsgoFilterUrl(getElement().ref, ctx);
			if (url) {
				yield* dsgoQueryRefresh(ctx, url);
			}
		},

		/**
		 * Handle checkbox toggle for multi-value taxonomy filters.
		 *
		 * @param {Event} [event] Change event (IAPI may omit in some edge cases).
		 * @generator
		 */
		*toggleFilter(event) {
			if (event) {
				dsgoMarkHandledEvent(event);
			}
			const ctx = getContext();
			const url = dsgoFilterUrl(getElement().ref, ctx);
			if (url) {
				yield* dsgoQueryRefresh(ctx, url);
			}
		},

		/**
		 * Handle click on an active-filter chip.
		 *
		 * @param {Event} event
		 * @generator
		 */
		*removeActiveFilter(event) {
			event.preventDefault?.();
			dsgoMarkHandledEvent(event);
			const ctx = getContext();
			const url = dsgoFilterUrl(getElement().ref, ctx);
			if (url) {
				yield* dsgoQueryRefresh(ctx, url);
			}
		},

		/**
		 * Handle click on the reset-all-filters button.
		 *
		 * @param {Event} event
		 * @generator
		 */
		*resetAll(event) {
			event.preventDefault?.();
			dsgoMarkHandledEvent(event);
			const ctx = getContext();
			const url = dsgoFilterUrl(getElement().ref, ctx);
			if (url) {
				yield* dsgoQueryRefresh(ctx, url);
			}
		},
	},

	callbacks: {
		/**
		 * Initialise an IntersectionObserver on the infinite-scroll sentinel element.
		 *
		 * Called via data-wp-init="callbacks.initInfiniteObserver" on the sentinel div.
		 * Auto-advances by clicking the hidden fallback button (which carries the
		 * data-wp-on--click="actions.loadMore" wiring) so we reuse the existing
		 * generator action without duplicating its fetch logic.
		 *
		 * Respects prefers-reduced-motion: reveals the button immediately and
		 * skips auto-advance so keyboard/accessible users always have the button path.
		 *
		 * @since 2.2.0
		 */
		initInfiniteObserver() {
			const { ref } = getElement(); // sentinel div
			dsgoSetupInfiniteObserver(ref, getContext());
		},
	},
});

// ---------------------------------------------------------------------------
// Delegated fallback for filter interactions
// ---------------------------------------------------------------------------
//
// Problem: dsgoQueryRefresh() swaps region.innerHTML with freshly-rendered
// HTML from /query/render. @wordpress/interactivity binds directives like
// `data-wp-on--change` at hydration time; the replaced DOM carries the
// directive attribute but the handler is NOT re-attached. Result: the first
// filter interaction works (IAPI fires), but every subsequent click on the
// swapped DOM becomes a silent no-op.
//
// Fix: attach document-level delegated listeners once at module load. When
// IAPI is alive for an element, its store action fires first and claims the
// event via markHandledEvent() — the delegated handler bails. When IAPI is
// dead (post-swap DOM), only the delegated handler runs and drives the same
// URL-manipulation + dsgoQueryRefreshPlain() path. See view-helpers.js for
// why the claim cannot be a plain identity check on the event object.

/**
 * Serialise delegated-path network work per queryId.
 *
 * Two rapid interactions after the IAPI swap produce fresh ctx objects, so
 * ctx.busy cannot guard them. Track in-flight work in a module-level Set and
 * drop new requests while one is already running.
 *
 * @param {Object}   ctx      Parsed context ({ queryId, ... }).
 * @param {Function} callback Promise-returning runner.
 */
function dsgoRunDelegated(ctx, callback) {
	const queryId = ctx && ctx.queryId;
	if (!queryId || dsgoDelegatedBusy.has(queryId)) {
		return;
	}
	dsgoDelegatedBusy.add(queryId);
	Promise.resolve(callback()).finally(() => {
		dsgoDelegatedBusy.delete(queryId);
	});
}

function dsgoDelegatedRefresh(ctx, url) {
	dsgoRunDelegated(ctx, () => dsgoQueryRefreshPlain(ctx, url));
}

/**
 * Read the IAPI context encoded in a filter form's data-wp-context attribute.
 *
 * @param {HTMLElement} el Descendant of the interactive form.
 * @return {Object|null}   Parsed context object, or null if missing/malformed.
 */
function dsgoGetContextFromDom(el) {
	const form = el.closest('[data-wp-context]');
	if (!form) {
		return null;
	}
	try {
		return JSON.parse(form.getAttribute('data-wp-context') || '');
	} catch (err) {
		return null;
	}
}

function dsgoGetQueryContainer(queryId, el) {
	// The item container carries `data-dsgo-query-results-role="container"` +
	// the matching query id — the grid <ul>/<ol>/<div> for query-results, the
	// track for a slider host, the panels wrapper for scroll-slides.
	// The outer .dsgo-query-region wrapper ALSO carries data-dsgo-query-id
	// (so view.js can target the whole region for full swaps), so targeting
	// [data-dsgo-query-id] alone would match the region and cause load-more
	// to append new items after the pagination button instead of into the
	// item container. Scope by role to always land inside it.
	const doc = el?.ownerDocument || document;
	const selector = dsgoItemContainerSelector(queryId);
	// Prefer scoping via the enclosing region so nested queries on the same
	// page can't collide even when another query shares the same queryId.
	const region = el?.closest(`[data-dsgo-query-region="${queryId}"]`);
	return region?.querySelector(selector) || doc.querySelector(selector);
}

async function dsgoLoadMorePlain(ctx, button) {
	if (!ctx?.queryId || ctx.busy || !(button instanceof HTMLElement)) {
		return;
	}

	ctx.busy = true;

	const idleLabel =
		button.getAttribute('data-dsgo-label-idle') || button.textContent;
	const loadingLabel = button.getAttribute('data-dsgo-label-loading') || '';
	if (loadingLabel) {
		button.textContent = loadingLabel;
		button.setAttribute('aria-busy', 'true');
	}
	button.disabled = true;

	const container = dsgoGetQueryContainer(ctx.queryId, button);
	if (!container) {
		button.textContent = idleLabel;
		button.disabled = false;
		button.removeAttribute('aria-busy');
		ctx.busy = false;
		return;
	}

	container.setAttribute('aria-busy', 'true');

	try {
		const blobsHost = document.querySelector(
			`[data-dsgo-blobs-for="${ctx.queryId}"]`
		);
		const refreshSource = dsgoReadRefreshSource(blobsHost);
		if (!refreshSource) {
			return;
		}

		const nextPage = (ctx.page || 1) + 1;
		const request = dsgoBuildRefreshRequest(ctx, refreshSource, {
			page: nextPage,
			params: dsgoCollectParams(new URL(window.location.href)),
			currentUrl: window.location.href,
			multiQuery: dsgoPageQueryIds(document).length > 1,
		});
		const res = await fetch(request.url, request.init);

		if (!res.ok) {
			// eslint-disable-next-line no-console
			console.warn(
				`[designsetgo/query] load-more request failed (${res.status}). Reloading the page will refresh it.`
			);
			return;
		}

		const data = await res.json();
		const doc = new DOMParser().parseFromString(
			data.html || '',
			'text/html'
		);
		const newItems = dsgoExtractRenderedItems(doc, ctx.queryId);

		if (newItems.length) {
			const firstNew = newItems[0];
			newItems.forEach((el) => container.appendChild(el));

			dsgoStampFeedPositions(container);
			// Carousel hosts derive clone counts, dot counts and track
			// dimensions from the item count they saw at init, so appending
			// silently would leave the new slides unreachable.
			dsgoNotifyItemsAppended(container, ctx.queryId, newItems.length);
			if (Number.isFinite(data.totalItems)) {
				dsgoAnnounceResultCount(ctx.queryId, data.totalItems);
			}

			const naturallyFocusable = firstNew.querySelector(
				'a, button, input, [tabindex]:not([tabindex="-1"])'
			);
			const focusable = naturallyFocusable || firstNew;

			if (focusable instanceof HTMLElement) {
				if (!naturallyFocusable) {
					focusable.setAttribute('tabindex', '-1');
					focusable.addEventListener(
						'blur',
						() => focusable.removeAttribute('tabindex'),
						{ once: true }
					);
				}
				// preventScroll keeps the viewport anchored on the Load more
				// button the user just clicked. Without this the browser jumps
				// to wherever the first new item lands (often well below the
				// fold on long grids), which reads as a lost scroll position
				// even though focus is moving for a screen-reader handoff.
				focusable.focus({ preventScroll: true });
			}
		}

		ctx.page = nextPage;

		if (data.totalPages && nextPage >= data.totalPages) {
			document
				.querySelectorAll(
					`[data-dsgo-query-id="${ctx.queryId}"][data-dsgo-pagination="loadmore"] button, ` +
						`[data-dsgo-query-id="${ctx.queryId}"][data-dsgo-pagination="infinite"]`
				)
				.forEach((el) => el.remove());
		}
	} finally {
		ctx.busy = false;
		container.setAttribute('aria-busy', 'false');
		if (button.isConnected) {
			button.textContent = idleLabel;
			button.disabled = false;
			button.removeAttribute('aria-busy');
		}
	}
}

function dsgoSetupInfiniteObserver(sentinel, ctx) {
	if (!(sentinel instanceof HTMLElement) || !ctx?.queryId) {
		return;
	}

	const wrapper = sentinel.closest('[data-dsgo-pagination="infinite"]');
	if (!wrapper) {
		return;
	}

	const prior = dsgoSentinelObservers.get(sentinel);
	if (prior) {
		prior.disconnect();
	}

	const feedContainer = dsgoGetQueryContainer(ctx.queryId, wrapper);
	if (feedContainer && feedContainer.getAttribute('role') !== 'feed') {
		feedContainer.setAttribute('role', 'feed');
		feedContainer.setAttribute('aria-busy', 'false');
		dsgoStampFeedPositions(feedContainer);
	}

	const button = wrapper.querySelector('.dsgo-query-pagination__loadmore');
	const prefersReduced = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;
	if (prefersReduced) {
		if (button) {
			button.hidden = false;
		}
		return;
	}

	const threshold = parseInt(wrapper.dataset.dsgoAutoPauseAfter || '3', 10);
	const offset = parseInt(wrapper.dataset.dsgoSentinelOffset || '200', 10);
	if (typeof ctx.autoLoadCount !== 'number') {
		ctx.autoLoadCount = 0;
	}

	const observer = new IntersectionObserver(
		(entries) => {
			entries.forEach((entry) => {
				if (!entry.isIntersecting || ctx.busy) {
					return;
				}

				if (ctx.autoLoadCount >= threshold) {
					if (button) {
						button.hidden = false;
					}
					observer.disconnect();
					dsgoSentinelObservers.delete(sentinel);
					return;
				}

				ctx.autoLoadCount++;

				if (button) {
					button.hidden = false;
					button.click();
					Promise.resolve().then(() => {
						if (button.isConnected) {
							button.hidden = true;
						}
					});
				}
			});
		},
		{ rootMargin: `${offset}px` }
	);

	observer.observe(sentinel);
	dsgoSentinelObservers.set(sentinel, observer);
}

function dsgoInitInfiniteObservers(root = document) {
	if (!root?.querySelectorAll) {
		return;
	}

	root.querySelectorAll(
		'[data-dsgo-pagination="infinite"] [data-wp-init*="initInfiniteObserver"]'
	).forEach((sentinel) => {
		const ctx = dsgoGetContextFromDom(sentinel);
		if (ctx) {
			dsgoSetupInfiniteObserver(sentinel, ctx);
		}
	});
}

/**
 * The URL a filter interaction leads to, built from the CURRENT URL.
 *
 * Every control, chip and Reset goes through here, so each one sees what the
 * others already changed (a chip's server-rendered href is only a no-JS
 * fallback and goes stale after the first in-place refresh). See
 * url-scope.js for the per-Query key rules.
 *
 * @param {HTMLElement} el  The control, chip or reset link.
 * @param {Object}      ctx Context with .queryId.
 * @return {URL|null} The new URL, or null when nothing applies.
 */
function dsgoFilterUrl(el, ctx) {
	const root = el?.closest?.('.dsgo-query-filter');
	const queryId = ctx?.queryId || root?.getAttribute('data-dsgo-query-id');
	if (!root || !queryId) {
		return null;
	}
	const knownIds = dsgoPageQueryIds(document);
	// Only write scoped keys when another Query could read ours; the server
	// omits data-dsgo-scoped when a site turns scoping off.
	const multi = root.hasAttribute('data-dsgo-scoped') && knownIds.length > 1;
	const kind = root.getAttribute('data-dsgo-filter-kind');
	const url = new URL(window.location.href);
	const params = url.searchParams;
	dsgoNormalizeListKeys(params);

	if (kind === 'reset') {
		dsgoResetOwned(params, queryId, multi, knownIds);
	} else if (kind === 'active') {
		const chip = el.closest('.dsgo-query-filter__chip');
		const bare = chip?.getAttribute('data-dsgo-filter-param');
		if (!bare) {
			return null;
		}
		dsgoRemoveOwnedValue(params, {
			bare,
			value: chip.getAttribute('data-dsgo-filter-value') || '',
			queryId,
			multi,
		});
	} else {
		const bare = root.getAttribute('data-dsgo-param');
		if (!bare) {
			return null;
		}
		if (kind === 'checkbox') {
			// The boxes are the source of truth: they were rendered from what
			// this Query reads, including a bare value it inherited.
			const values = Array.from(
				root.querySelectorAll('input[type="checkbox"]')
			)
				.filter((box) => box.checked)
				.map((box) => box.value);
			dsgoWriteOwned(params, {
				bare,
				queryId,
				values,
				multi,
				isArray: true,
			});
		} else {
			const input = root.querySelector(
				'select[name], input[name]:not([type="hidden"])'
			);
			dsgoWriteOwned(params, {
				bare,
				queryId,
				values: [input?.value ?? ''],
				multi,
				isArray: false,
			});
		}
	}

	dsgoResetOwnPage(params, queryId, multi, url.pathname);
	return url;
}

/**
 * Delegated change handler for filter inputs and selects.
 *
 * Covers checkbox, select and sort. IAPI's actions.toggleFilter /
 * actions.setFilter run first when alive; this handler only takes over when
 * they don't.
 *
 * @param {Event} event Native change event.
 */
function dsgoDelegatedChange(event) {
	if (dsgoIsHandledEvent(event)) {
		return;
	}
	const target = event.target;
	if (
		!(target instanceof HTMLInputElement) &&
		!(target instanceof HTMLSelectElement)
	) {
		return;
	}
	if (!target.closest('.dsgo-query-filter')) {
		return;
	}
	// A search filter submits, and blurring its input to click Submit fires
	// `change` first — so acting on both turns one interaction into two
	// identical REST round-trips. The submit path owns these controls in
	// either state: live, IAPI's setFilter runs it; de-hydrated,
	// dsgoDelegatedSubmit does.
	if (target.closest('form[data-wp-on--submit]')) {
		return;
	}
	const ctx = dsgoGetContextFromDom(target);
	const url = dsgoFilterUrl(target, ctx);
	if (url) {
		dsgoDelegatedRefresh(ctx, url);
	}
}

/**
 * Delegated submit handler for search filter forms.
 *
 * Covers the post-swap DOM where IAPI directives are no longer live.
 *
 * @param {Event} event Native submit event.
 */
function dsgoDelegatedSubmit(event) {
	if (dsgoIsHandledEvent(event)) {
		return;
	}
	const target = event.target;
	if (!(target instanceof HTMLElement)) {
		return;
	}
	if (!target.closest('.dsgo-query-filter--search')) {
		return;
	}
	event.preventDefault();
	const ctx = dsgoGetContextFromDom(target);
	const url = dsgoFilterUrl(target, ctx);
	if (url) {
		dsgoDelegatedRefresh(ctx, url);
	}
}

/**
 * Delegated click handler for load more, active-filter chips and Reset.
 *
 * @param {Event} event Native click event.
 */
function dsgoDelegatedClick(event) {
	if (dsgoIsHandledEvent(event)) {
		return;
	}
	const target = event.target;
	if (!(target instanceof HTMLElement)) {
		return;
	}
	const loadMoreButton = target.closest('.dsgo-query-pagination__loadmore');
	if (loadMoreButton) {
		const ctx = dsgoGetContextFromDom(loadMoreButton);
		if (!ctx) {
			return;
		}

		event.preventDefault();
		dsgoRunDelegated(ctx, () => dsgoLoadMorePlain(ctx, loadMoreButton));
		return;
	}
	const chip = target.closest(
		'.dsgo-query-filter--active .dsgo-query-filter__chip, .dsgo-query-filter--reset .dsgo-query-filter__reset, .dsgo-query-filter--reset a'
	);
	if (!chip) {
		return;
	}
	const ctx = dsgoGetContextFromDom(chip);
	const url = dsgoFilterUrl(chip, ctx);
	if (!url) {
		return;
	}
	event.preventDefault();
	dsgoDelegatedRefresh(ctx, url);
}

document.addEventListener('change', dsgoDelegatedChange);
// No delegated `input` listener: the search filter is submit-only to avoid
// yanking focus out of the input mid-typing when the debounced refresh swaps
// the form's DOM. Submit (Enter / button) is handled by dsgoDelegatedSubmit.
document.addEventListener('submit', dsgoDelegatedSubmit);
document.addEventListener('click', dsgoDelegatedClick);
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () =>
		dsgoInitInfiniteObservers(document)
	);
} else {
	dsgoInitInfiniteObservers(document);
}

// ---------------------------------------------------------------------------
// Shared refresh helpers
// ---------------------------------------------------------------------------

// collectParams lives in view-helpers.js so it can be unit-tested with jsdom;
// alias locally to preserve the existing call-site names below.
const dsgoCollectParams = dsgoCollectParamsShared;

/**
 * Core refresh generator — used by all filter actions via yield*.
 *
 * Fetches fresh HTML from designsetgo/v1/query/render (which now returns
 * a full region — list + pagination + no-results + filter siblings), then
 * swaps the outer .dsgo-query-region wrapper's innerHTML so pagination,
 * no-results visibility, and active-filter chips all update together.
 *
 * Safety: the HTML returned by the REST endpoint is server-rendered by
 * WordPress (same pipeline as first-paint), so it is already escaped by
 * esc_html/esc_attr/get_block_wrapper_attributes. Assigning it to
 * innerHTML is equivalent to what wp_kses_post() would allow.
 *
 * @generator
 * @param {Object} ctx IAPI context (must have .queryId).
 * @param {URL}    url New URL to navigate to (search params = new filters).
 */
function* dsgoQueryRefresh(ctx, url) {
	const queryId = ctx.queryId;
	if (!queryId || ctx.busy) {
		return;
	}
	ctx.busy = true;

	// Find the outer region wrapper — its innerHTML is replaced after the fetch.
	const region = document.querySelector(
		`[data-dsgo-query-region="${queryId}"]`
	);
	const blobsHost = document.querySelector(
		`[data-dsgo-blobs-for="${queryId}"]`
	);

	if (!region || !blobsHost) {
		ctx.busy = false;
		return;
	}

	// Sync the URL now, not when the response lands: another Query's control
	// used meanwhile builds its URL from this one, and a late write would
	// drop whichever change came first.
	window.history.replaceState({}, '', url.toString());

	// Also find the list container (role="container") so we can aria-busy it
	// during the fetch. The region wrapper contains filter/pagination children
	// too; scoping with data-dsgo-query-results-role avoids aria-busy on those.
	const listContainer = region.querySelector(
		`[data-dsgo-query-id="${queryId}"][data-dsgo-query-results-role="container"]`
	);
	if (listContainer) {
		listContainer.setAttribute('aria-busy', 'true');
	}

	try {
		// Filters live in the URL, so when the in-place refresh can't run (no
		// signed source, or the server refused it — a cached page whose signature
		// predates a salt change, say) the server-rendered page is the fallback.
		const refreshSource = dsgoReadRefreshSource(blobsHost);
		if (!refreshSource) {
			window.location.assign(url.toString());
			return;
		}

		const request = dsgoBuildRefreshRequest(ctx, refreshSource, {
			page: 1,
			params: dsgoCollectParams(url),
			currentUrl: url.toString(),
			multiQuery: dsgoPageQueryIds(document).length > 1,
		});
		const res = yield fetch(request.url, request.init);

		if (!res.ok) {
			// eslint-disable-next-line no-console
			console.warn(
				`[designsetgo/query] filter refresh failed (${res.status}); loading the filtered page instead.`
			);
			window.location.assign(url.toString());
			return;
		}
		// Defence-in-depth: only parse responses we recognise. A misbehaving
		// proxy / WAF could replace the body with non-JSON; bail before injecting.
		const contentType = (
			res.headers.get('content-type') || ''
		).toLowerCase();
		if (!contentType.includes('application/json')) {
			return;
		}
		const data = yield res.json();
		if (!data || typeof data.html !== 'string') {
			return;
		}

		// Parse the returned region HTML and swap the outer region's innerHTML.
		// This updates the list, pagination, no-results, and active-filter chips
		// in one operation. The outer region element (and its data attribute) stays
		// intact so the IAPI context survives the swap.
		const doc = new DOMParser().parseFromString(data.html, 'text/html');
		const newRegion = doc.querySelector(
			`[data-dsgo-query-region="${queryId}"]`
		);
		if (newRegion) {
			// Disconnect observers inside the region before detaching their
			// sentinels — observers that fire post-swap close over stale ctx.
			dsgoDisconnectSentinelObservers(region);
			// Server-rendered HTML assembled from esc_attr / esc_html /
			// block-render output in designsetgo_query_render_region().
			region.innerHTML = newRegion.innerHTML;
			dsgoInitInfiniteObservers(region);
			// The swap replaced every element inside the region, including any
			// block with a frontend runtime (a slider host, counters, maps).
			// Without this they stay inert until the next full page load.
			dsgoNotifyContentUpdated(region, 'query-refresh');
		}

		ctx.page = 1;

		// Announce the new result count + re-stamp feed positions if the
		// list was in feed mode before the swap.
		if (Number.isFinite(data.totalItems)) {
			dsgoAnnounceResultCount(queryId, data.totalItems);
		}
	} finally {
		ctx.busy = false;
		// listContainer may have been replaced by the innerHTML swap above;
		// re-query from the region to get the fresh element.
		const freshList = region.querySelector(
			`[data-dsgo-query-id="${queryId}"][data-dsgo-query-results-role="container"]`
		);
		if (freshList) {
			freshList.setAttribute('aria-busy', 'false');
			dsgoStampFeedPositions(freshList);
		}
	}
}

/**
 * Promise-based (non-generator) variant for the debounced search action.
 * Mirrors dsgoQueryRefresh but uses async/await so it can be called from
 * a regular (non-generator) setTimeout callback.
 *
 * Like dsgoQueryRefresh, swaps the outer .dsgo-query-region innerHTML so
 * pagination, no-results, and filter chips update along with the list.
 *
 * @param {Object} ctx IAPI context (must have .queryId).
 * @param {URL}    url New URL to navigate to.
 */
async function dsgoQueryRefreshPlain(ctx, url) {
	const queryId = ctx.queryId;
	if (!queryId || ctx.busy) {
		return;
	}
	ctx.busy = true;

	const region = document.querySelector(
		`[data-dsgo-query-region="${queryId}"]`
	);
	const blobsHost = document.querySelector(
		`[data-dsgo-blobs-for="${queryId}"]`
	);

	if (!region || !blobsHost) {
		ctx.busy = false;
		return;
	}

	// Sync the URL now, not when the response lands: another Query's control
	// used meanwhile builds its URL from this one, and a late write would
	// drop whichever change came first.
	window.history.replaceState({}, '', url.toString());

	const listContainer = region.querySelector(
		`[data-dsgo-query-id="${queryId}"][data-dsgo-query-results-role="container"]`
	);
	if (listContainer) {
		listContainer.setAttribute('aria-busy', 'true');
	}

	try {
		// Same fallback as dsgoQueryRefresh: the server renders the filtered page.
		const refreshSource = dsgoReadRefreshSource(blobsHost);
		if (!refreshSource) {
			window.location.assign(url.toString());
			return;
		}

		const request = dsgoBuildRefreshRequest(ctx, refreshSource, {
			page: 1,
			params: dsgoCollectParams(url),
			currentUrl: url.toString(),
			multiQuery: dsgoPageQueryIds(document).length > 1,
		});
		const res = await fetch(request.url, request.init);

		if (!res.ok) {
			// eslint-disable-next-line no-console
			console.warn(
				`[designsetgo/query] debounced refresh failed (${res.status}); loading the filtered page instead.`
			);
			window.location.assign(url.toString());
			return;
		}
		// Defence-in-depth: see dsgoQueryRefresh — only inject from JSON envelopes.
		const contentType = (
			res.headers.get('content-type') || ''
		).toLowerCase();
		if (!contentType.includes('application/json')) {
			return;
		}
		const data = await res.json();
		if (!data || typeof data.html !== 'string') {
			return;
		}

		const doc = new DOMParser().parseFromString(data.html, 'text/html');
		const newRegion = doc.querySelector(
			`[data-dsgo-query-region="${queryId}"]`
		);
		if (newRegion) {
			// Disconnect observers inside the region before detaching their
			// sentinels — observers that fire post-swap close over stale ctx.
			dsgoDisconnectSentinelObservers(region);
			// Server-rendered HTML assembled from esc_attr / esc_html /
			// block-render output in designsetgo_query_render_region().
			region.innerHTML = newRegion.innerHTML;
			dsgoInitInfiniteObservers(region);
			// The swap replaced every element inside the region, including any
			// block with a frontend runtime (a slider host, counters, maps).
			// Without this they stay inert until the next full page load.
			dsgoNotifyContentUpdated(region, 'query-refresh');
		}

		ctx.page = 1;

		if (Number.isFinite(data.totalItems)) {
			dsgoAnnounceResultCount(queryId, data.totalItems);
		}
	} finally {
		ctx.busy = false;
		const freshList = region.querySelector(
			`[data-dsgo-query-id="${queryId}"][data-dsgo-query-results-role="container"]`
		);
		if (freshList) {
			freshList.setAttribute('aria-busy', 'false');
			dsgoStampFeedPositions(freshList);
		}
	}
}
