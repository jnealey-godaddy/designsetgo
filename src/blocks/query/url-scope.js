/**
 * Dynamic Query — per-Query URL params, browser side.
 *
 * Mirrors param-scoping.php. With two or more Queries on a page, a filter
 * control writes `{param}__{queryId}` so it drives only its own Query. The
 * rules every reader and writer follows:
 *
 * - A key scoped for this Query wins over the same bare key.
 * - A key scoped for another Query is never read or touched.
 * - A bare key (a menu link, a bookmark) applies to every Query. To clear it
 *   for one Query without changing the others, that Query writes an EMPTY
 *   scoped key, which shadows the bare one.
 *
 * With one Query on the page, controls write plain keys (`q`, `sort`,
 * `filter_category`), so URLs stay readable and site-search analytics that
 * look for `q` keep working.
 *
 * Pure functions over URLSearchParams, so they can be unit-tested.
 *
 * @since 2.8.3
 */

// The ids DSGo generates: `q` + 8 hex (editor), `q-` + 10 hex (template import).
const GENERATED_ID = /^q(?:[0-9a-f]{8}|-[0-9a-f]{10})$/;

const FILTER_PARAM = (bare) =>
	bare.startsWith('filter_') || bare === 'q' || bare === 'sort';

/**
 * The distinct Query ids on the page.
 *
 * @param {Document|Element} root Where to look.
 * @return {string[]} Query ids.
 */
export function pageQueryIds(root) {
	const ids = new Set();
	root.querySelectorAll('[data-dsgo-query-region]').forEach((el) => {
		const id = el.getAttribute('data-dsgo-query-region');
		if (id) {
			ids.add(id);
		}
	});
	return Array.from(ids);
}

/**
 * Rewrite indexed list keys (`filter_x[0]=a`, as WordPress's pagination
 * links write them) to `filter_x[]=a`, so every reader sees one spelling.
 *
 * @param {URLSearchParams} params URL params (mutated).
 */
export function normalizeListKeys(params) {
	const entries = Array.from(params.entries());
	if (!entries.some(([k]) => /\[\d+\]$/.test(k))) {
		return;
	}
	entries.forEach(([k]) => params.delete(k));
	entries.forEach(([k, v]) => params.append(k.replace(/\[\d+\]$/, '[]'), v));
}

/**
 * Split a URL key into its bare name and the Query it is scoped for.
 *
 * Only the text after the last `__` can be a Query id, and only when it is
 * one, so a taxonomy such as `filter_my__tax` stays a bare key.
 *
 * @param {string}   key      URL key, without any trailing `[]`.
 * @param {string}   ownId    The caller's Query id.
 * @param {string[]} knownIds Query ids on the page.
 * @return {{bare: string, scope: string}} Bare name and Query id ('' if bare).
 */
export function splitKey(key, ownId = '', knownIds = []) {
	const pos = key.lastIndexOf('__');
	if (pos <= 0) {
		return { bare: key, scope: '' };
	}
	const id = key.slice(pos + 2);
	if (
		id &&
		(id === ownId || GENERATED_ID.test(id) || knownIds.includes(id))
	) {
		return { bare: key.slice(0, pos), scope: id };
	}
	return { bare: key, scope: '' };
}

/**
 * The values one Query currently sees for a param.
 *
 * @param {URLSearchParams} params  URL params.
 * @param {string}          bare    Bare param name.
 * @param {string}          queryId The Query's id.
 * @return {{values: string[], isArray: boolean}} Non-empty values, and
 *                                                whether the key used `[]`.
 */
export function readOwned(params, bare, queryId) {
	const scoped = `${bare}__${queryId}`;
	const key =
		queryId && (params.has(scoped) || params.has(`${scoped}[]`))
			? scoped
			: bare;
	const isArray = params.has(`${key}[]`);
	const values = [...params.getAll(key), ...params.getAll(`${key}[]`)];
	return { values: values.filter((v) => v !== ''), isArray };
}

/**
 * Write one Query's values for a param.
 *
 * @param {URLSearchParams} params         URL params (mutated).
 * @param {Object}          opts
 * @param {string}          opts.bare      Bare param name.
 * @param {string}          opts.queryId   The Query's id.
 * @param {string[]}        opts.values    New values; empty clears.
 * @param {boolean}         opts.multi     Whether to write the scoped key.
 * @param {boolean}         [opts.isArray] Write `name[]` entries.
 */
export function writeOwned(params, { bare, queryId, values, multi, isArray }) {
	const scoped = `${bare}__${queryId}`;
	const clean = values.filter((v) => v !== '' && v !== undefined);
	const drop = (k) => {
		params.delete(k);
		params.delete(`${k}[]`);
	};
	const put = (k, list) => {
		const name = isArray ? `${k}[]` : k;
		(isArray ? list : list.slice(0, 1)).forEach((v) =>
			params.append(name, v)
		);
	};

	if (multi && queryId) {
		drop(scoped);
		if (clean.length) {
			put(scoped, clean);
		} else if (params.has(bare) || params.has(`${bare}[]`)) {
			// Shadow the bare value for this Query only.
			put(scoped, ['']);
		}
		return;
	}
	drop(bare);
	if (queryId) {
		drop(scoped);
	}
	put(bare, clean);
}

/**
 * Send one Query back to its first page.
 *
 * @param {URLSearchParams} params   URL params (mutated).
 * @param {string}          queryId  The Query's id.
 * @param {boolean}         multi    Whether the page has several Queries.
 * @param {string}          pathname Current path, for `/page/N/` links.
 */
export function resetOwnPage(params, queryId, multi, pathname = '') {
	const own = `qpage__${queryId}`;
	params.delete(own);
	if (!multi || !queryId) {
		params.delete('paged');
		params.delete('page');
		return;
	}
	// A shared `paged` pages every Query; leave it for the others and pin
	// this one to page 1.
	if (
		params.has('paged') ||
		params.has('page') ||
		/\/page\/\d+\/?$/.test(pathname)
	) {
		params.set(own, '1');
	}
}

/**
 * Remove one value from one Query's param (an active-filter chip).
 *
 * @param {URLSearchParams} params       URL params (mutated).
 * @param {Object}          opts
 * @param {string}          opts.bare    Bare param name.
 * @param {string}          opts.value   Value to remove.
 * @param {string}          opts.queryId The Query's id.
 * @param {boolean}         opts.multi   Whether to write the scoped key.
 */
export function removeOwnedValue(params, { bare, value, queryId, multi }) {
	const { values, isArray } = readOwned(params, bare, queryId);
	writeOwned(params, {
		bare,
		queryId,
		values: values.filter((v) => v !== value),
		multi,
		isArray,
	});
}

/**
 * Clear every filter / search / sort param one Query sees (Reset).
 *
 * @param {URLSearchParams} params   URL params (mutated).
 * @param {string}          queryId  The Query's id.
 * @param {boolean}         multi    Whether to write scoped keys.
 * @param {string[]}        knownIds Query ids on the page.
 */
export function resetOwned(params, queryId, multi, knownIds = []) {
	const bares = new Map();
	for (const rawKey of Array.from(params.keys())) {
		const key = rawKey.endsWith('[]') ? rawKey.slice(0, -2) : rawKey;
		const { bare, scope } = splitKey(key, queryId, knownIds);
		if ((scope === '' || scope === queryId) && FILTER_PARAM(bare)) {
			bares.set(bare, bares.get(bare) || rawKey.endsWith('[]'));
		}
	}
	bares.forEach((isArray, bare) =>
		writeOwned(params, { bare, queryId, values: [], multi, isArray })
	);
}
