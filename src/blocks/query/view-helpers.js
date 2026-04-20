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
