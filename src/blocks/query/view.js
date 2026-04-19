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
		*loadMore() {
			const ctx = getContext();
			if (ctx.busy) {
				return;
			}
			ctx.busy = true;

			const { ref } = getElement(); // the button itself
			const idleLabel =
				ref instanceof HTMLElement
					? ref.getAttribute('data-dsgo-label-idle') ||
						ref.textContent
					: '';
			const loadingLabel =
				ref instanceof HTMLElement
					? ref.getAttribute('data-dsgo-label-loading') || ''
					: '';

			if (ref instanceof HTMLElement && loadingLabel) {
				ref.textContent = loadingLabel;
				ref.setAttribute('aria-busy', 'true');
				ref.disabled = true;
			}

			// Fix 1: exclude elements with [data-dsgo-pagination] so we find the
			// query list, not the pagination wrapper (both carry data-dsgo-query-id).
			const container =
				ref.closest(
					'[data-dsgo-query-id]:not([data-dsgo-pagination])'
				) ||
				document.querySelector(
					`[data-dsgo-query-id="${ctx.queryId}"]:not([data-dsgo-pagination])`
				);

			if (!container) {
				if (ref instanceof HTMLElement) {
					ref.textContent = idleLabel;
					ref.disabled = false;
					ref.removeAttribute('aria-busy');
				}
				ctx.busy = false;
				return;
			}

			container.setAttribute('aria-busy', 'true');

			try {
				// Fix 4: blobs now live in a preceding-sibling hidden div with
				// data-dsgo-blobs-for, not inside the list element.
				const blobsHost = document.querySelector(
					`[data-dsgo-blobs-for="${ctx.queryId}"]`
				);
				const attrsEl = blobsHost?.querySelector(
					'script[data-dsgo-attrs]'
				);
				const innerEl = blobsHost?.querySelector(
					'script[data-dsgo-inner]'
				);

				if (!attrsEl || !innerEl) {
					ctx.busy = false;
					container.setAttribute('aria-busy', 'false');
					return;
				}

				const attributes = JSON.parse(attrsEl.textContent);
				const innerBlocks = JSON.parse(innerEl.textContent);
				const nextPage = (ctx.page || 1) + 1;

				// ctx.restUrl + ctx.nonce are seeded by render-helpers.php so we
				// don't rely on wpApiSettings (admin-only) or /wp-json/ rewrites
				// (not guaranteed on plain-permalink installs).
				const restUrl =
					ctx.restUrl ||
					(window.wpApiSettings?.root || '/wp-json/') +
						'designsetgo/v1/query/render';
				const restNonce =
					ctx.nonce || window.wpApiSettings?.nonce || '';
				const res = yield fetch(restUrl, {
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
					// 401 typically means the nonce expired (page open > 12h).
					// Surface this to devtools so "load more does nothing" is
					// debuggable without the user having to inspect network.
					// eslint-disable-next-line no-console
					console.warn(
						`[designsetgo/query] load-more request failed (${res.status}). If 401, the nonce has likely expired — reload the page.`
					);
					return;
				}

				const data = yield res.json();

				// Parse the returned HTML and append .dsgo-query__item nodes.
				const parser = new DOMParser();
				const doc = parser.parseFromString(
					data.html || '',
					'text/html'
				);
				const newItems = doc.querySelectorAll('.dsgo-query__item');

				if (newItems.length) {
					// Focus management: move focus to the first newly-appended item.
					const firstNew = newItems[0];
					newItems.forEach((el) => container.appendChild(el));

					// Refresh feed positions for the now-longer set and announce
					// the running count if the server reported a total.
					dsgoStampFeedPositions(container);
					if (Number.isFinite(data.totalItems)) {
						dsgoAnnounceResultCount(ctx.queryId, data.totalItems);
					}

					// Fix 2: only stamp tabindex="-1" when falling back to the item
					// wrapper itself (no naturally focusable child found). This avoids
					// permanently evicting <a>/<button>/<input> from the tab order.
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
						focusable.focus();
					}
				}

				ctx.page = nextPage;

				// Remove pagination controls once we've fetched the last page.
				// Covers both load-more buttons and the infinite-scroll sentinel
				// wrapper (removing the wrapper also garbage-collects any
				// IntersectionObserver attached to the sentinel inside it).
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
				// Restore button label and state (button may have been removed
				// when we reached the last page, so guard with isConnected).
				if (ref instanceof HTMLElement && ref.isConnected) {
					ref.textContent = idleLabel;
					ref.disabled = false;
					ref.removeAttribute('aria-busy');
				}
			}
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
			const form = ref.closest('form');
			const input = form?.querySelector('[name]');
			const paramName = input?.getAttribute('name')?.replace(/\[\]$/, '');
			if (!paramName) {
				return;
			}

			const ctx = getContext();
			const url = new URL(window.location.href);
			if (ref.value) {
				url.searchParams.set(paramName, ref.value);
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

			const wrapper = ref.closest('[data-dsgo-pagination="infinite"]');
			if (!wrapper) {
				return;
			}

			// Disconnect any prior observer for this sentinel. IAPI may
			// re-run callbacks.initInfiniteObserver on the same element if
			// a refresh swaps innerHTML and the sentinel is rebuilt, and
			// leftover observers from previous mounts would keep firing.
			const prior = dsgoSentinelObservers.get(ref);
			if (prior) {
				prior.disconnect();
			}

			// Promote the list to role="feed" + stamp positions so AT users
			// can navigate the incrementally-loaded items structurally. Done
			// here (not in render.php) so markup stays correct under plain
			// (non-infinite) pagination modes.
			const queryId = wrapper.getAttribute('data-dsgo-query-id') || '';
			const feedContainer = queryId
				? document.querySelector(
						`[data-dsgo-query-id="${queryId}"][data-dsgo-query-role="container"]`
					)
				: null;
			if (
				feedContainer &&
				feedContainer.getAttribute('role') !== 'feed'
			) {
				feedContainer.setAttribute('role', 'feed');
				feedContainer.setAttribute('aria-busy', 'false');
				dsgoStampFeedPositions(feedContainer);
			}

			const button = wrapper.querySelector(
				'.dsgo-query-pagination__loadmore'
			);

			// Reduced-motion: reveal the button and skip auto-advance entirely.
			const prefersReduced = window.matchMedia(
				'(prefers-reduced-motion: reduce)'
			).matches;
			if (prefersReduced) {
				if (button) {
					button.hidden = false;
				}
				return;
			}

			const ctx = getContext();

			const threshold = parseInt(
				wrapper.dataset.dsgoAutoPauseAfter || '3',
				10
			);
			const offset = parseInt(
				wrapper.dataset.dsgoSentinelOffset || '200',
				10
			);

			// Initialise the auto-load counter on the context if not already set.
			if (typeof ctx.autoLoadCount !== 'number') {
				ctx.autoLoadCount = 0;
			}

			const observer = new IntersectionObserver(
				(entries) => {
					entries.forEach((entry) => {
						if (!entry.isIntersecting) {
							return;
						}

						// Don't count or fire if a load is already in-flight.
						if (ctx.busy) {
							return;
						}

						if (ctx.autoLoadCount >= threshold) {
							// Auto-pause threshold reached — reveal button,
							// disconnect observer, let the user opt in to more.
							if (button) {
								button.hidden = false;
							}
							observer.disconnect();
							dsgoSentinelObservers.delete(ref);
							return;
						}

						ctx.autoLoadCount++;

						// Fire a synthetic click on the button so the existing
						// loadMore generator action handles the full fetch/append
						// cycle (including busy-guard, aria-busy, focus management).
						// The button stays hidden visually during auto-loads;
						// we briefly un-hide it so the IAPI click event resolves
						// the correct element reference, then re-hide immediately.
						if (button) {
							button.hidden = false;
							button.click();
							// Re-hide after the microtask tick so the click event
							// is dispatched with the button visible, then restore.
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

			observer.observe(ref);
			dsgoSentinelObservers.set(ref, observer);
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
 * Serialise delegated-path refreshes per queryId.
 *
 * Two rapid filter changes after the IAPI swap produce two calls to
 * dsgoQueryRefreshPlain, each with a freshly-parsed ctx — so ctx.busy cannot
 * guard them. Track in-flight refreshes in the module-level Set and drop new
 * requests while one is already running, mirroring the IAPI reactive guard.
 *
 * @param {Object} ctx Parsed context ({ queryId, ...}).
 * @param {URL}    url Target URL.
 */
function dsgoDelegatedRefresh(ctx, url) {
	const queryId = ctx && ctx.queryId;
	if (!queryId || dsgoDelegatedBusy.has(queryId)) {
		return;
	}
	dsgoDelegatedBusy.add(queryId);
	Promise.resolve(dsgoQueryRefreshPlain(ctx, url)).finally(() => {
		dsgoDelegatedBusy.delete(queryId);
	});
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
 * Delegated input handler for the debounced search field.
 *
 * @param {Event} event Native input event.
 */
function dsgoDelegatedInput(event) {
	if (dsgoHandledEvents.has(event)) {
		return;
	}
	const target = event.target;
	if (!(target instanceof HTMLInputElement)) {
		return;
	}
	const filterRoot = target.closest('.dsgo-query-filter--search');
	if (!filterRoot) {
		return;
	}
	const paramName = (target.getAttribute('name') || '').replace(/\[\]$/, '');
	if (!paramName) {
		return;
	}
	const ctx = dsgoGetContextFromDom(target);
	if (!ctx) {
		return;
	}

	clearTimeout(dsgoDebounceTimers[paramName]);
	dsgoDebounceTimers[paramName] = setTimeout(() => {
		if (!target.isConnected) {
			return;
		}
		const url = new URL(window.location.href);
		if (target.value) {
			url.searchParams.set(paramName, target.value);
		} else {
			url.searchParams.delete(paramName);
		}
		url.searchParams.delete('paged');
		url.searchParams.delete('page');
		dsgoDelegatedRefresh(ctx, url);
	}, 250);
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
document.addEventListener('input', dsgoDelegatedInput);
document.addEventListener('click', dsgoDelegatedClick);

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
	// too; scoping with data-dsgo-query-role avoids aria-busy on those.
	const listContainer = region.querySelector(
		`[data-dsgo-query-id="${queryId}"][data-dsgo-query-role="container"]`
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
			`[data-dsgo-query-id="${queryId}"][data-dsgo-query-role="container"]`
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
		`[data-dsgo-query-id="${queryId}"][data-dsgo-query-role="container"]`
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
		}

		window.history.replaceState({}, '', url.toString());
		ctx.page = 1;

		if (Number.isFinite(data.totalItems)) {
			dsgoAnnounceResultCount(queryId, data.totalItems);
		}
	} finally {
		ctx.busy = false;
		const freshList = region.querySelector(
			`[data-dsgo-query-id="${queryId}"][data-dsgo-query-role="container"]`
		);
		if (freshList) {
			freshList.setAttribute('aria-busy', 'false');
			dsgoStampFeedPositions(freshList);
		}
	}
}
