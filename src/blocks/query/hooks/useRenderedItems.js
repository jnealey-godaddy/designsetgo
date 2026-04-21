import apiFetch from '@wordpress/api-fetch';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { serialize } from '@wordpress/blocks';

/**
 * Fetches server-rendered HTML for each item of a Dynamic Query block and
 * returns it as an array of strings. The editor uses this to show items 2..N
 * as true-to-frontend previews (read-only) while item 0 stays editable via
 * InnerBlocks. Solves the BlockPreview visual parity issue where fluid
 * spacing presets (e.g. `var:preset|spacing|50` → `clamp(…)`) resolve
 * differently against the iframe's narrow viewport.
 *
 * @param {Object}  root0
 * @param {string}  root0.queryId     The block's queryId attribute.
 * @param {Object}  root0.attributes  Full block attributes.
 * @param {Array}   root0.innerBlocks Current editor innerBlocks (template).
 * @param {boolean} [root0.enabled]   False skips all fetching (e.g. when source is
 *                                    manual/current, or the block has no inner
 *                                    blocks yet).
 * @return {{ items: string[]|null, loading: boolean, error: Error|null }} Hook state.
 */
export default function useRenderedItems({
	queryId,
	attributes,
	innerBlocks,
	enabled = true,
}) {
	const [state, setState] = useState({
		items: null,
		loading: false,
		error: null,
	});

	// Keep the most recent fetch's abort token so superseded requests can't
	// overwrite the newer render result on resolution.
	const reqIdRef = useRef(0);

	// Serialize innerBlocks once per render — cheap enough (~10s of blocks)
	// and avoids passing a freshly-allocated string through the dep array.
	const innerHtml = useMemo(
		() => (Array.isArray(innerBlocks) ? serialize(innerBlocks) : ''),
		[innerBlocks]
	);

	// Combine attributes + innerHtml into one stable key; JSON.stringify is
	// plenty fast for a single block's attribute object and avoids deep-equal
	// bookkeeping.
	const payloadKey = useMemo(
		() => JSON.stringify({ a: attributes, h: innerHtml }),
		[attributes, innerHtml]
	);

	useEffect(() => {
		if (!enabled || !queryId || innerHtml === '') {
			setState({ items: null, loading: false, error: null });
			return undefined;
		}
		const reqId = ++reqIdRef.current;
		setState((s) => ({ ...s, loading: true, error: null }));

		const timer = setTimeout(() => {
			apiFetch({
				path: '/designsetgo/v1/query/render',
				method: 'POST',
				data: {
					queryId,
					attributes,
					page: 1,
					innerBlocks: innerHtml,
				},
			})
				.then((res) => {
					if (reqId !== reqIdRef.current) {
						return;
					}
					const items = extractItems(res?.html || '');
					setState({ items, loading: false, error: null });
				})
				.catch((err) => {
					if (reqId !== reqIdRef.current) {
						return;
					}
					setState({ items: null, loading: false, error: err });
				});
		}, 300);

		return () => {
			clearTimeout(timer);
		};
		// payloadKey subsumes attributes + innerHtml so they don't need to be deps.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [payloadKey, queryId, enabled]);

	return state;
}

/**
 * Extract per-item HTML strings from the server-rendered region markup.
 * Each item is wrapped with `class="dsgo-query__item"` (see
 * designsetgo_query_render_item() in render-helpers.php), so we use that
 * selector rather than a tag-name match that would misfire on ul/ol/div
 * item-tag variants.
 *
 * @param {string} html Full region HTML from /query/render.
 * @return {string[]} Inner HTML of each `.dsgo-query__item` element.
 */
function extractItems(html) {
	if (typeof html !== 'string' || html === '') {
		return [];
	}
	// DOMParser is a browser global; the editor always runs in the browser.
	// eslint-disable-next-line no-undef
	const doc = new DOMParser().parseFromString(html, 'text/html');
	const nodes = doc.querySelectorAll('.dsgo-query__item');
	return Array.from(nodes).map((n) => n.innerHTML);
}
