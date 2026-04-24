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
} from './view-helpers.js';

// Per-element debounce timer map (module-level, not reactive state).
const dsgoDebounceTimers = {};

// Events already processed by the IAPI store (first render). Used by the
// delegated fallback listener so we never double-fire. The WeakSet lets the
// browser GC events when they're done.
const dsgoHandledEvents = new WeakSet();

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
		 * Handle change on a select or single-value input.
		 * Sets (or clears) the param, resets paged, then refreshes.
		 *
		 * @param {Event} event
		 * @generator
		 */
		*setFilter(event) {
			event.preventDefault?.();
			dsgoHandledEvents.add(event);
			const { ref } = getElement();
			// ref may be the form (submit event) or the input/select (change event).
			const form =
				ref.closest('form') ?? (ref.tagName === 'FORM' ? ref : null);
			const input = form?.querySelector('[name]');
			const paramName = input?.getAttribute('name')?.replace(/\[\]$/, '');
			if (!paramName) {
				return;
			}

			// Read the value from the named input, not from ref — ref may be the
			// <form> element when the directive is bound to the form's submit event.
			const value = input?.value ?? ref.value ?? '';
			const ctx = getContext();
			const url = new URL(window.location.href);
			if (value) {
				url.searchParams.set(paramName, value);
			} else {
				url.searchParams.delete(paramName);
			}
			// Fix 3: strip both pagination params — `paged` for archives,
			// `page` for singular post paginators — so filtering from page 2+
			// always resets to page 1.
			url.searchParams.delete('paged');
			url.searchParams.delete('page');
			yield* dsgoQueryRefresh(ctx, url);
		},

		/**
		 * Debounced input handler for the search field.
		 * Fires 250 ms after typing stops. Declared as a regular function
		 * (not a generator) so IAPI treats it as synchronous — which is
		 * what we need for the setTimeout pattern.
		 *
		 * @param {Event} event
		 */
		setFilterDebounced(event) {
			dsgoHandledEvents.add(event);
			const el = event.target;
			const paramName = el.getAttribute('name')?.replace(/\[\]$/, '');
			if (!paramName) {
				return;
			}

			const ctx = getContext(); // capture while IAPI frame is live

			clearTimeout(dsgoDebounceTimers[paramName]);
			dsgoDebounceTimers[paramName] = setTimeout(() => {
				// Bail out if the input was removed mid-debounce by a concurrent
				// setFilter / toggleFilter refresh — the captured ctx is bound to
				// an orphaned state object in that case, and the fresh HTML has
				// its own data-wp-context to drive subsequent interactions.
				if (!el.isConnected) {
					return;
				}
				const url = new URL(window.location.href);
				if (el.value) {
					url.searchParams.set(paramName, el.value);
				} else {
					url.searchParams.delete(paramName);
				}
				// Fix 3: strip both pagination params.
				url.searchParams.delete('paged');
				url.searchParams.delete('page');
				// Use the Promise-based helper — no yield needed here.
				dsgoQueryRefreshPlain(ctx, url);
			}, 250);
		},

		/**
		 * Handle checkbox toggle for multi-value taxonomy filters.
		 * Appends or removes the checked value from the URL array param.
		 *
		 * @param {Event} [event] Change event (IAPI may omit in some edge cases).
		 * @generator
		 */
		*toggleFilter(event) {
			if (event) {
				dsgoHandledEvents.add(event);
			}
			const { ref } = getElement();
			const paramName = ref.getAttribute('name')?.replace(/\[\]$/, '');
			if (!paramName) {
				return;
			}

			const ctx = getContext();

			const url = new URL(window.location.href);
			const arrayKey = paramName + '[]';
			const current = url.searchParams.getAll(arrayKey);
			url.searchParams.delete(arrayKey);

			if (ref.checked) {
				if (!current.includes(ref.value)) {
					current.push(ref.value);
				}
			} else {
				const idx = current.indexOf(ref.value);
				if (idx > -1) {
					current.splice(idx, 1);
				}
			}

			current.forEach((v) => url.searchParams.append(arrayKey, v));
			// Fix 3: strip both pagination params.
			url.searchParams.delete('paged');
			url.searchParams.delete('page');
			yield* dsgoQueryRefresh(ctx, url);
		},

		/**
		 * Handle click on an active-filter chip.
		 * The chip <a> href already encodes the removal URL.
		 *
		 * @param {Event} event
		 * @generator
		 */
		*removeActiveFilter(event) {
			event.preventDefault?.();
			dsgoHandledEvents.add(event);
			const { ref } = getElement();
			const href = ref.getAttribute('href');
			if (!href) {
				return;
			}

			const ctx = getContext();
			const url = new URL(href, window.location.href);
			// Fix 3: strip both pagination params.
			url.searchParams.delete('paged');
			url.searchParams.delete('page');
			yield* dsgoQueryRefresh(ctx, url);
		},

		/**
		 * Handle click on the reset-all-filters button.
		 * The button <a> href already encodes the clean URL.
		 *
		 * @param {Event} event
		 * @generator
		 */
		*resetAll(event) {
			event.preventDefault?.();
			dsgoHandledEvents.add(event);
			const { ref } = getElement();
			const href = ref.getAttribute('href');
			const ctx = getContext();
			const url = href
				? new URL(href, window.location.href)
				: new URL(window.location.href);
			yield* dsgoQueryRefresh(ctx, url);
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
// IAPI is alive for an element, its store action fires first and marks the
// event via dsgoHandledEvents — the delegated handler bails. When IAPI is
// dead (post-swap DOM), only the delegated handler runs and drives the same
// URL-manipulation + dsgoQueryRefreshPlain() path.

/**
 * Mark a native event as already handled by the Interactivity API store.
 *
 * @param {Event} event Native event object.
 */
function dsgoMarkHandledEvent(event) {
	if (event && typeof event === 'object') {
		dsgoHandledEvents.add(event);
	}
}

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
	// The grid <ul> / <ol> / <div> is the actual item container — it carries
	// `data-dsgo-query-results-role="container"` + the matching query id.
	// The outer .dsgo-query-region wrapper ALSO carries data-dsgo-query-id
	// (so view.js can target the whole region for full swaps), so targeting
	// [data-dsgo-query-id] alone would match the region and cause load-more
	// to append new items after the pagination button instead of into the
	// grid. Scope by role to always land inside the grid.
	const doc = el?.ownerDocument || document;
	// Prefer scoping via the enclosing region so nested queries on the same
	// page can't collide even when another query shares the same queryId.
	const region = el?.closest(`[data-dsgo-query-region="${queryId}"]`);
	return (
		region?.querySelector(
			`[data-dsgo-query-results-role="container"][data-dsgo-query-id="${queryId}"]`
		) ||
		doc.querySelector(
			`[data-dsgo-query-results-role="container"][data-dsgo-query-id="${queryId}"]`
		)
	);
}

function dsgoGetRestConfig(ctx) {
	return {
		restUrl:
			ctx.restUrl ||
			(window.wpApiSettings?.root || '/wp-json/') +
				'designsetgo/v1/query/render',
		restNonce: ctx.nonce || window.wpApiSettings?.nonce || '',
	};
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
		const attrsEl = blobsHost?.querySelector('script[data-dsgo-attrs]');
		const innerEl = blobsHost?.querySelector('script[data-dsgo-inner]');

		if (!attrsEl || !innerEl) {
			return;
		}

		const attributes = JSON.parse(attrsEl.textContent);
		const innerBlocks = JSON.parse(innerEl.textContent);
		const nextPage = (ctx.page || 1) + 1;
		const { restUrl, restNonce } = dsgoGetRestConfig(ctx);
		const res = await fetch(restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': restNonce,
			},
			body: JSON.stringify({
				queryId: ctx.queryId,
				attributes,
				page: nextPage,
				innerBlocks,
				currentUrl: window.location.href,
			}),
		});

		if (!res.ok) {
			// eslint-disable-next-line no-console
			console.warn(
				`[designsetgo/query] load-more request failed (${res.status}). If 401, the nonce has likely expired — reload the page.`
			);
			return;
		}

		const data = await res.json();
		const doc = new DOMParser().parseFromString(
			data.html || '',
			'text/html'
		);
		const newItems = doc.querySelectorAll('.dsgo-query__item');

		if (newItems.length) {
			const firstNew = newItems[0];
			newItems.forEach((el) => container.appendChild(el));

			dsgoStampFeedPositions(container);
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
 * Delegated change handler for filter inputs and selects.
 *
 * Covers three filterKinds: checkbox (multi-value array param), select, sort,
 * and search-on-change. IAPI's actions.toggleFilter / actions.setFilter run
 * first when alive; this handler only takes over when they don't.
 *
 * @param {Event} event Native change event.
 */
function dsgoDelegatedChange(event) {
	if (dsgoHandledEvents.has(event)) {
		return;
	}
	const target = event.target;
	if (!(target instanceof HTMLElement)) {
		return;
	}
	const filterRoot = target.closest('.dsgo-query-filter');
	if (!filterRoot) {
		return;
	}
	const rawName =
		target instanceof HTMLInputElement ||
		target instanceof HTMLSelectElement
			? target.getAttribute('name') || ''
			: '';
	const paramName = rawName.replace(/\[\]$/, '');
	if (!paramName) {
		return;
	}
	const ctx = dsgoGetContextFromDom(target);
	if (!ctx) {
		return;
	}
	const kind = filterRoot.getAttribute('data-dsgo-filter-kind');
	const url = new URL(window.location.href);

	if (kind === 'checkbox') {
		const arrayKey = `${paramName}[]`;
		const current = url.searchParams.getAll(arrayKey);
		url.searchParams.delete(arrayKey);
		const value = target instanceof HTMLInputElement ? target.value : '';
		const checked =
			target instanceof HTMLInputElement ? target.checked : false;
		if (checked) {
			if (!current.includes(value)) {
				current.push(value);
			}
		} else {
			const idx = current.indexOf(value);
			if (idx > -1) {
				current.splice(idx, 1);
			}
		}
		current.forEach((v) => url.searchParams.append(arrayKey, v));
	} else {
		// select / sort / search-on-change: single value.
		const value =
			target instanceof HTMLInputElement ||
			target instanceof HTMLSelectElement
				? target.value
				: '';
		if (value) {
			url.searchParams.set(paramName, value);
		} else {
			url.searchParams.delete(paramName);
		}
	}

	url.searchParams.delete('paged');
	url.searchParams.delete('page');
	dsgoDelegatedRefresh(ctx, url);
}

/**
 * Delegated submit handler for search filter forms.
 *
 * Covers the post-swap DOM where IAPI directives are no longer live.
 * Mirrors the IAPI setFilter logic but reads the form's named input directly.
 *
 * @param {Event} event Native submit event.
 */
function dsgoDelegatedSubmit(event) {
	if (dsgoHandledEvents.has(event)) {
		return;
	}
	const target = event.target;
	if (!(target instanceof HTMLElement)) {
		return;
	}
	const filterRoot = target.closest('.dsgo-query-filter--search');
	if (!filterRoot) {
		return;
	}
	event.preventDefault();
	const input = target.querySelector('[name]');
	const paramName = (input?.getAttribute('name') || '').replace(/\[\]$/, '');
	if (!paramName) {
		return;
	}
	const ctx = dsgoGetContextFromDom(target);
	if (!ctx) {
		return;
	}
	const url = new URL(window.location.href);
	if (input?.value) {
		url.searchParams.set(paramName, input.value);
	} else {
		url.searchParams.delete(paramName);
	}
	url.searchParams.delete('paged');
	url.searchParams.delete('page');
	dsgoDelegatedRefresh(ctx, url);
}

/**
 * Delegated click handler for active-filter chips and reset-all buttons.
 *
 * Chips and reset buttons are <a href="…"> elements whose href encodes the
 * post-action URL; we just drive an AJAX refresh to that URL.
 *
 * @param {Event} event Native click event.
 */
function dsgoDelegatedClick(event) {
	if (dsgoHandledEvents.has(event)) {
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
	const href = chip.getAttribute('href');
	if (!href) {
		return;
	}
	const ctx = dsgoGetContextFromDom(chip);
	if (!ctx) {
		return;
	}

	event.preventDefault();
	const url = new URL(href, window.location.href);
	url.searchParams.delete('paged');
	url.searchParams.delete('page');
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
		const attrsEl = blobsHost.querySelector('script[data-dsgo-attrs]');
		const innerEl = blobsHost.querySelector('script[data-dsgo-inner]');
		if (!attrsEl || !innerEl) {
			return;
		}

		const attributes = JSON.parse(attrsEl.textContent);
		const innerBlocks = JSON.parse(innerEl.textContent);
		const params = dsgoCollectParams(url);

		const restUrl =
			ctx.restUrl ||
			(window.wpApiSettings?.root || '/wp-json/') +
				'designsetgo/v1/query/render';
		const restNonce = ctx.nonce || window.wpApiSettings?.nonce || '';
		const res = yield fetch(restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': restNonce,
			},
			body: JSON.stringify({
				queryId,
				attributes,
				page: 1,
				innerBlocks,
				params,
				currentUrl: url.toString(),
			}),
		});

		if (!res.ok) {
			// eslint-disable-next-line no-console
			console.warn(
				`[designsetgo/query] filter refresh failed (${res.status}). If 401, the nonce has likely expired — reload the page.`
			);
			return;
		}
		const data = yield res.json();

		// Parse the returned region HTML and swap the outer region's innerHTML.
		// This updates the list, pagination, no-results, and active-filter chips
		// in one operation. The outer region element (and its data attribute) stays
		// intact so the IAPI context survives the swap.
		const doc = new DOMParser().parseFromString(
			data.html || '',
			'text/html'
		);
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
		}

		// Sync the browser URL without a page reload.
		window.history.replaceState({}, '', url.toString());
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

	const listContainer = region.querySelector(
		`[data-dsgo-query-id="${queryId}"][data-dsgo-query-results-role="container"]`
	);
	if (listContainer) {
		listContainer.setAttribute('aria-busy', 'true');
	}

	try {
		const attrsEl = blobsHost.querySelector('script[data-dsgo-attrs]');
		const innerEl = blobsHost.querySelector('script[data-dsgo-inner]');
		if (!attrsEl || !innerEl) {
			return;
		}

		const attributes = JSON.parse(attrsEl.textContent);
		const innerBlocks = JSON.parse(innerEl.textContent);
		const params = dsgoCollectParams(url);

		const restUrl =
			ctx.restUrl ||
			(window.wpApiSettings?.root || '/wp-json/') +
				'designsetgo/v1/query/render';
		const restNonce = ctx.nonce || window.wpApiSettings?.nonce || '';
		const res = await fetch(restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': restNonce,
			},
			body: JSON.stringify({
				queryId,
				attributes,
				page: 1,
				innerBlocks,
				params,
				currentUrl: url.toString(),
			}),
		});

		if (!res.ok) {
			// eslint-disable-next-line no-console
			console.warn(
				`[designsetgo/query] debounced refresh failed (${res.status}). If 401, the nonce has likely expired — reload the page.`
			);
			return;
		}
		const data = await res.json();

		const doc = new DOMParser().parseFromString(
			data.html || '',
			'text/html'
		);
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
		}

		window.history.replaceState({}, '', url.toString());
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
