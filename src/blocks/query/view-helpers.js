/**
 * Dynamic Query — pure helpers for the Interactivity API view module.
 *
 * Everything here is framework-agnostic (no @wordpress/interactivity imports)
 * and mutates only DOM nodes passed in as arguments, so it can be exercised
 * from Jest with jsdom. The IAPI store in view.js uses these helpers for
 * URL/DOM plumbing that does NOT need to run inside an IAPI frame.
 *
 * @since 2.2.1
 */

/**
 * Selector for the element that actually holds a query's rendered items.
 *
 * Every item host tags its item container with this role/id pair —
 * designsetgo/query-results on its grid, designsetgo/slider on its track,
 * designsetgo/scroll-slides on its panels wrapper — so the shared plumbing
 * here works the same whichever presentation the author chose.
 *
 * @param {string} queryId The query ID.
 * @return {string} A CSS selector for that query's item container.
 */
export function itemContainerSelector(queryId) {
	return `[data-dsgo-query-results-role="container"][data-dsgo-query-id="${queryId}"]`;
}

/**
 * Pull the freshly rendered items out of a parsed REST region response.
 *
 * Reads the item container's element children rather than matching on
 * `.dsgo-query__item`: that class only exists on the grid host's <li>
 * wrappers, so a class-based match would find nothing for a carousel host
 * (whose items are bare `designsetgo/slide` renders) and would flatten
 * `<section class="dsgo-query-group">` wrappers when grouping is on.
 *
 * @param {Document} doc     Parsed response document.
 * @param {string}   queryId The query ID.
 * @return {Element[]} Items to append, in document order.
 */
export function extractRenderedItems(doc, queryId) {
	if (!doc) {
		return [];
	}
	const container = doc.querySelector(itemContainerSelector(queryId));
	if (container) {
		return Array.from(container.children);
	}
	// Legacy trees render the item template as a direct child of the query
	// with no host block at all, so there is no container to read.
	return Array.from(doc.querySelectorAll('.dsgo-query__item'));
}

/**
 * Tell the rest of the plugin that a chunk of DOM was replaced or extended.
 *
 * Blocks with a frontend runtime (slider, counters, flip cards, maps…) can
 * be inside a query region, and a filter refresh swaps that region's
 * innerHTML wholesale — leaving the replacement markup inert unless someone
 * says so. `dsgo-content-loaded` is the plugin-wide re-init signal already
 * used for bfcache restores, so reusing it here means every block that
 * already listens gets query refreshes for free.
 *
 * @param {Element|null} root   The element whose contents changed.
 * @param {string}       source Short label for the trigger, for listeners that care.
 */
export function notifyContentUpdated(root, source) {
	const doc =
		root?.ownerDocument ||
		(typeof document !== 'undefined' ? document : null);
	if (!doc) {
		return;
	}
	doc.dispatchEvent(
		new CustomEvent('dsgo-content-loaded', {
			detail: { source, container: root },
		})
	);
}

/**
 * Tell an item host that new items were appended to its container.
 *
 * Distinct from notifyContentUpdated(): nothing was replaced, so blocks that
 * key their init on the element identity (the slider caches its instance per
 * element) would treat the container as already initialised and never notice
 * the new children. The host listens for this on the container itself and
 * re-syncs whatever it derived from the old item count.
 *
 * @param {Element|null} container The item container that grew.
 * @param {string}       queryId   The query ID.
 * @param {number}       added     How many items were appended.
 */
export function notifyItemsAppended(container, queryId, added) {
	if (!container) {
		return;
	}
	container.dispatchEvent(
		new CustomEvent('dsgo-query-items-appended', {
			bubbles: true,
			detail: { queryId, added },
		})
	);
}

// ---------------------------------------------------------------------------
// Delegated-fallback de-duplication
// ---------------------------------------------------------------------------
//
// view.js runs every interaction twice over: once through the Interactivity
// API store action bound to the element, and once through a document-level
// delegated listener that exists to keep working after a filter refresh has
// replaced (and so de-hydrated) the region's markup. Only one of them may do
// the work.
//
// Identity alone cannot decide it. The IAPI hands a store action a *Proxy*
// around the native event (`wrapEventAsync()` in @wordpress/interactivity,
// skipped only for actions wrapped in `withSyncEvent()`), so the object the
// action marks is never the object the document listener later receives, and
// a WeakSet of marked events matches nothing. That is what made a single
// Load more click fire two REST requests and append the same page twice.
//
// What is reliable is the *ordering* and the *target*. `withScope()` runs a
// generator action's body synchronously up to its first `yield`, from inside
// the element's own handler — so the mark is always in place before the same
// event bubbles to document — and `event.target` read through the proxy is
// the identical native node. So we match on the target and drop the mark on
// the next task, once the dispatch that set it has finished.

/** Native events already claimed by a store action, when identity survives. */
const handledEvents = new WeakSet();

/** Target of the event a store action claimed during the current dispatch. */
let handledTarget = null;
let handledTargetTimer = null;

/**
 * Claim an event on behalf of the live Interactivity API store.
 *
 * @param {Event} event Event handed to the store action (possibly a Proxy).
 */
export function markHandledEvent(event) {
	if (!event || typeof event !== 'object') {
		return;
	}
	handledEvents.add(event);
	handledTarget = event.target || null;
	if (handledTargetTimer) {
		clearTimeout(handledTargetTimer);
	}
	// A macrotask, not a microtask: browsers take a microtask checkpoint
	// between listener callbacks, so a promise would clear the mark before
	// the event reached the document-level listener it exists to stop.
	handledTargetTimer = setTimeout(() => {
		handledTarget = null;
		handledTargetTimer = null;
	}, 0);
}

/**
 * Whether the live store already handled this event.
 *
 * @param {Event} event Native event seen by a delegated listener.
 * @return {boolean} True when the delegated handler must stand down.
 */
export function isHandledEvent(event) {
	if (!event || typeof event !== 'object') {
		return false;
	}
	if (handledEvents.has(event)) {
		return true;
	}
	return handledTarget !== null && event.target === handledTarget;
}

/**
 * Test seam — drop any standing claim.
 */
export function resetHandledEvents() {
	handledTarget = null;
	if (handledTargetTimer) {
		clearTimeout(handledTargetTimer);
		handledTargetTimer = null;
	}
}

/**
 * Track live IntersectionObservers by their sentinel element so filter-refresh
 * innerHTML swaps can disconnect them before the sentinel becomes detached.
 */
export const sentinelObservers = new WeakMap();

/**
 * Disconnect and drop any IntersectionObservers attached to infinite-scroll
 * sentinels inside the given root. Called before an innerHTML swap.
 *
 * @param {Element|null} root The region element about to be mutated.
 */
export function disconnectSentinelObservers(root) {
	if (!root) {
		return;
	}
	const sentinels = root.querySelectorAll(
		'[data-dsgo-pagination="infinite"] [data-wp-init*="initInfiniteObserver"]'
	);
	sentinels.forEach((sentinel) => {
		const obs = sentinelObservers.get(sentinel);
		if (obs) {
			obs.disconnect();
			sentinelObservers.delete(sentinel);
		}
	});
}

/**
 * Stamp aria-posinset / aria-setsize on every .dsgo-query__item inside the
 * container — no-op unless the container has role="feed".
 *
 * @param {Element|null} container The list element.
 */
export function stampFeedPositions(container) {
	if (!container || container.getAttribute('role') !== 'feed') {
		return;
	}
	const items = container.querySelectorAll('.dsgo-query__item');
	const size = items.length;
	items.forEach((item, i) => {
		item.setAttribute('aria-posinset', String(i + 1));
		item.setAttribute('aria-setsize', String(size));
	});
}

/**
 * Write a terse "N results found" message to the region's status element.
 *
 * @param {string}        queryId    The query ID.
 * @param {number}        totalItems The count reported by the server.
 * @param {Document|null} doc        Document to query (defaults to window.document).
 */
export function announceResultCount(
	queryId,
	totalItems,
	doc = typeof document !== 'undefined' ? document : null
) {
	if (!doc) {
		return;
	}
	const statusEl = doc.querySelector(`[data-dsgo-query-status="${queryId}"]`);
	if (!statusEl) {
		return;
	}
	const n = Number.isFinite(totalItems) ? totalItems : 0;
	let message;
	if (n === 0) {
		message = 'No results found';
	} else if (n === 1) {
		message = '1 result found';
	} else {
		message = `${n} results found`;
	}
	statusEl.textContent = message;
	statusEl.setAttribute('data-dsgo-total-items', String(n));
}

/**
 * Build the filter params object from a URL's search params. Collects only
 * filter_*, q, and sort keys — extensions can add more via the server-side
 * `designsetgo_query_url_params` filter (the REST endpoint is the source of
 * truth for the allowed list).
 *
 * Handles both ?key[]=v and ?key=v styles: multi-value keys (either expressed
 * with trailing brackets or repeated bare keys) are coerced to arrays.
 *
 * @param {URL} url Source URL.
 * @return {Object<string, string|string[]>} Params keyed by name; multi-value keys surface as arrays.
 */
export function collectParams(url) {
	const params = {};
	for (const [k, v] of url.searchParams.entries()) {
		const isArrayKey = k.endsWith('[]');
		const baseKey = isArrayKey ? k.slice(0, -2) : k;

		if (
			!baseKey.startsWith('filter_') &&
			baseKey !== 'q' &&
			baseKey !== 'sort'
		) {
			continue;
		}

		if (isArrayKey || Array.isArray(params[baseKey])) {
			if (!Array.isArray(params[baseKey])) {
				params[baseKey] =
					params[baseKey] !== undefined ? [params[baseKey]] : [];
			}
			params[baseKey].push(v);
		} else {
			params[baseKey] = v;
		}
	}
	return params;
}

/**
 * Apply the toggle-filter semantics to a URL, given a checkbox-style input.
 *
 * - Adds the value to `name[]` if checked; removes it otherwise.
 * - Deduplicates values.
 * - Strips both pagination params (`paged` and `page`) so toggling always
 *   returns to page 1.
 *
 * Pure function — does not touch the DOM or IAPI state. The caller is
 * responsible for dispatching the resulting URL to the refresh helper.
 *
 * @param {URL}     url     Source URL (is NOT mutated).
 * @param {string}  name    Param name WITHOUT the trailing `[]` (e.g. "filter_category").
 * @param {string}  value   The checkbox value.
 * @param {boolean} checked Whether the checkbox is now checked.
 * @return {URL} A new URL with the toggle applied.
 */
export function applyToggleFilter(url, name, value, checked) {
	const next = new URL(url.toString());
	const arrayKey = `${name}[]`;
	const current = next.searchParams.getAll(arrayKey);
	next.searchParams.delete(arrayKey);

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

	current.forEach((v) => next.searchParams.append(arrayKey, v));
	next.searchParams.delete('paged');
	next.searchParams.delete('page');
	return next;
}

/**
 * Apply the set-filter semantics to a URL: set or remove the given param,
 * always strip pagination. Pure function.
 *
 * @param {URL}    url   Source URL (not mutated).
 * @param {string} name  Param name (no `[]`).
 * @param {string} value New value; empty string removes the param entirely.
 * @return {URL} New URL with the param set or removed and pagination stripped.
 */
export function applySetFilter(url, name, value) {
	const next = new URL(url.toString());
	if (value) {
		next.searchParams.set(name, value);
	} else {
		next.searchParams.delete(name);
	}
	next.searchParams.delete('paged');
	next.searchParams.delete('page');
	return next;
}

/**
 * Strip all filter params (filter_*, q, sort) and pagination from the URL.
 * Pure function — used by the "Reset filters" action.
 *
 * @param {URL} url Source URL (not mutated).
 * @return {URL} New URL with all filter + pagination params removed.
 */
export function applyResetFilters(url) {
	const next = new URL(url.toString());
	const toDelete = [];
	for (const k of next.searchParams.keys()) {
		const base = k.endsWith('[]') ? k.slice(0, -2) : k;
		if (
			base.startsWith('filter_') ||
			base === 'q' ||
			base === 'sort' ||
			base === 'paged' ||
			base === 'page'
		) {
			toDelete.push(k);
		}
	}
	// Dedup — a key may appear multiple times in the iterator view; set below
	// is tolerant but we avoid redundant delete() calls.
	Array.from(new Set(toDelete)).forEach((k) => next.searchParams.delete(k));
	return next;
}
