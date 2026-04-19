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
import { store, getContext, getElement } from '@wordpress/interactivity';

// Per-element debounce timer map (module-level, not reactive state).
const dsgoDebounceTimers = {};

store( 'designsetgo/query', {
	actions: {
		// ----------------------------------------------------------------
		// Pagination
		// ----------------------------------------------------------------
		*loadMore() {
			const ctx = getContext();
			if ( ctx.busy ) return;
			ctx.busy = true;

			const { ref } = getElement(); // the button itself
			const idleLabel =
				ref instanceof HTMLElement
					? ref.getAttribute( 'data-dsgo-label-idle' ) ||
					  ref.textContent
					: '';
			const loadingLabel =
				ref instanceof HTMLElement
					? ref.getAttribute( 'data-dsgo-label-loading' ) || ''
					: '';

			if ( ref instanceof HTMLElement && loadingLabel ) {
				ref.textContent = loadingLabel;
				ref.setAttribute( 'aria-busy', 'true' );
				ref.disabled = true;
			}

			// Fix 1: exclude elements with [data-dsgo-pagination] so we find the
			// query list, not the pagination wrapper (both carry data-dsgo-query-id).
			const container =
				ref.closest(
					'[data-dsgo-query-id]:not([data-dsgo-pagination])'
				) ||
				document.querySelector(
					`[data-dsgo-query-id="${ ctx.queryId }"]:not([data-dsgo-pagination])`
				);

			if ( ! container ) {
				if ( ref instanceof HTMLElement ) {
					ref.textContent = idleLabel;
					ref.disabled = false;
					ref.removeAttribute( 'aria-busy' );
				}
				ctx.busy = false;
				return;
			}

			container.setAttribute( 'aria-busy', 'true' );

			try {
				// Fix 4: blobs now live in a preceding-sibling hidden div with
				// data-dsgo-blobs-for, not inside the list element.
				const blobsHost = document.querySelector(
					`[data-dsgo-blobs-for="${ ctx.queryId }"]`
				);
				const attrsEl = blobsHost?.querySelector(
					'script[data-dsgo-attrs]'
				);
				const innerEl = blobsHost?.querySelector(
					'script[data-dsgo-inner]'
				);

				if ( ! attrsEl || ! innerEl ) {
					ctx.busy = false;
					container.setAttribute( 'aria-busy', 'false' );
					return;
				}

				const attributes = JSON.parse( attrsEl.textContent );
				const innerBlocks = JSON.parse( innerEl.textContent );
				const nextPage = ( ctx.page || 1 ) + 1;

				// ctx.restUrl + ctx.nonce are seeded by render-helpers.php so we
				// don't rely on wpApiSettings (admin-only) or /wp-json/ rewrites
				// (not guaranteed on plain-permalink installs).
				const restUrl = ctx.restUrl
					|| ( window.wpApiSettings?.root || '/wp-json/' ) + 'designsetgo/v1/query/render';
				const restNonce = ctx.nonce || window.wpApiSettings?.nonce || '';
				const res = yield fetch( restUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': restNonce,
					},
					body: JSON.stringify( {
						queryId: ctx.queryId,
						attributes,
						page: nextPage,
						innerBlocks,
						currentUrl: window.location.href,
					} ),
				} );

				if ( ! res.ok ) {
					// 401 typically means the nonce expired (page open > 12h).
					// Surface this to devtools so "load more does nothing" is
					// debuggable without the user having to inspect network.
					// eslint-disable-next-line no-console
					console.warn(
						`[designsetgo/query] load-more request failed (${ res.status }). If 401, the nonce has likely expired — reload the page.`
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
				const newItems = doc.querySelectorAll( '.dsgo-query__item' );

				if ( newItems.length ) {
					// Focus management: move focus to the first newly-appended item.
					const firstNew = newItems[ 0 ];
					newItems.forEach( ( el ) => container.appendChild( el ) );

					// Fix 2: only stamp tabindex="-1" when falling back to the item
					// wrapper itself (no naturally focusable child found). This avoids
					// permanently evicting <a>/<button>/<input> from the tab order.
					const naturallyFocusable = firstNew.querySelector(
						'a, button, input, [tabindex]:not([tabindex="-1"])'
					);
					const focusable = naturallyFocusable || firstNew;

					if ( focusable instanceof HTMLElement ) {
						if ( ! naturallyFocusable ) {
							focusable.setAttribute( 'tabindex', '-1' );
							focusable.addEventListener(
								'blur',
								() => focusable.removeAttribute( 'tabindex' ),
								{ once: true }
							);
						}
						focusable.focus();
					}
				}

				ctx.page = nextPage;

				// Hide the load-more button once we've fetched the last page.
				if ( data.totalPages && nextPage >= data.totalPages ) {
					document
						.querySelectorAll(
							`[data-dsgo-query-id="${ ctx.queryId }"][data-dsgo-pagination="loadmore"] button`
						)
						.forEach( ( btn ) => btn.remove() );
				}
			} finally {
				ctx.busy = false;
				container.setAttribute( 'aria-busy', 'false' );
				// Restore button label and state (button may have been removed
				// when we reached the last page, so guard with isConnected).
				if ( ref instanceof HTMLElement && ref.isConnected ) {
					ref.textContent = idleLabel;
					ref.disabled = false;
					ref.removeAttribute( 'aria-busy' );
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
		 * @generator
		 */
		*setFilter( event ) {
			event.preventDefault?.();
			const { ref } = getElement();
			const ctx     = getContext();
			const form    = ref.closest( 'form' );
			const input   = form?.querySelector( '[name]' );
			const paramName = input
				?.getAttribute( 'name' )
				?.replace( /\[\]$/, '' );
			if ( ! paramName ) return;

			const url = new URL( window.location.href );
			if ( ref.value ) {
				url.searchParams.set( paramName, ref.value );
			} else {
				url.searchParams.delete( paramName );
			}
			// Fix 3: strip both pagination params — `paged` for archives,
			// `page` for singular post paginators — so filtering from page 2+
			// always resets to page 1.
			url.searchParams.delete( 'paged' );
			url.searchParams.delete( 'page' );
			yield* dsgoQueryRefresh( ctx, url );
		},

		/**
		 * Debounced input handler for the search field.
		 * Fires 250 ms after typing stops. Declared as a regular function
		 * (not a generator) so IAPI treats it as synchronous — which is
		 * what we need for the setTimeout pattern.
		 */
		setFilterDebounced( event ) {
			const ctx       = getContext(); // capture while IAPI frame is live
			const el        = event.target;
			const paramName = el.getAttribute( 'name' )?.replace( /\[\]$/, '' );
			if ( ! paramName ) return;

			clearTimeout( dsgoDebounceTimers[ paramName ] );
			dsgoDebounceTimers[ paramName ] = setTimeout( () => {
				const url = new URL( window.location.href );
				if ( el.value ) {
					url.searchParams.set( paramName, el.value );
				} else {
					url.searchParams.delete( paramName );
				}
				// Fix 3: strip both pagination params.
				url.searchParams.delete( 'paged' );
				url.searchParams.delete( 'page' );
				// Use the Promise-based helper — no yield needed here.
				dsgoQueryRefreshPlain( ctx, url );
			}, 250 );
		},

		/**
		 * Handle checkbox toggle for multi-value taxonomy filters.
		 * Appends or removes the checked value from the URL array param.
		 *
		 * @generator
		 */
		*toggleFilter() {
			const { ref } = getElement();
			const ctx     = getContext();
			const paramName = ref
				.getAttribute( 'name' )
				?.replace( /\[\]$/, '' );
			if ( ! paramName ) return;

			const url      = new URL( window.location.href );
			const arrayKey = paramName + '[]';
			const current  = url.searchParams.getAll( arrayKey );
			url.searchParams.delete( arrayKey );

			if ( ref.checked ) {
				if ( ! current.includes( ref.value ) ) {
					current.push( ref.value );
				}
			} else {
				const idx = current.indexOf( ref.value );
				if ( idx > -1 ) current.splice( idx, 1 );
			}

			current.forEach( ( v ) => url.searchParams.append( arrayKey, v ) );
			// Fix 3: strip both pagination params.
			url.searchParams.delete( 'paged' );
			url.searchParams.delete( 'page' );
			yield* dsgoQueryRefresh( ctx, url );
		},

		/**
		 * Handle click on an active-filter chip.
		 * The chip <a> href already encodes the removal URL.
		 *
		 * @generator
		 */
		*removeActiveFilter( event ) {
			event.preventDefault?.();
			const { ref } = getElement();
			const ctx     = getContext();
			const href    = ref.getAttribute( 'href' );
			if ( ! href ) return;
			const url = new URL( href, window.location.href );
			// Fix 3: strip both pagination params.
			url.searchParams.delete( 'paged' );
			url.searchParams.delete( 'page' );
			yield* dsgoQueryRefresh( ctx, url );
		},

		/**
		 * Handle click on the reset-all-filters button.
		 * The button <a> href already encodes the clean URL.
		 *
		 * @generator
		 */
		*resetAll( event ) {
			event.preventDefault?.();
			const { ref } = getElement();
			const ctx     = getContext();
			const href    = ref.getAttribute( 'href' );
			const url     = href
				? new URL( href, window.location.href )
				: new URL( window.location.href );
			yield* dsgoQueryRefresh( ctx, url );
		},
	},
} );

// ---------------------------------------------------------------------------
// Shared refresh helpers
// ---------------------------------------------------------------------------

/**
 * Build the query params object from a URL's search params.
 * Collects filter_*, q, sort — handles both ?key[]=v and ?key=v styles.
 *
 * @param {URL} url
 * @returns {Object}
 */
function dsgoCollectParams( url ) {
	const params = {};
	for ( const [ k, v ] of url.searchParams.entries() ) {
		const isArrayKey = k.endsWith( '[]' );
		const baseKey    = isArrayKey ? k.slice( 0, -2 ) : k;

		// Only collect filter_*, q, and sort keys.
		if (
			! baseKey.startsWith( 'filter_' ) &&
			baseKey !== 'q' &&
			baseKey !== 'sort'
		) {
			continue;
		}

		if ( isArrayKey || Array.isArray( params[ baseKey ] ) ) {
			if ( ! Array.isArray( params[ baseKey ] ) ) {
				params[ baseKey ] =
					params[ baseKey ] !== undefined
						? [ params[ baseKey ] ]
						: [];
			}
			params[ baseKey ].push( v );
		} else {
			params[ baseKey ] = v;
		}
	}
	return params;
}

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
 * @param {Object} ctx  IAPI context (must have .queryId).
 * @param {URL}    url  New URL to navigate to (search params = new filters).
 */
function* dsgoQueryRefresh( ctx, url ) {
	const queryId = ctx.queryId;
	if ( ! queryId || ctx.busy ) return;
	ctx.busy = true;

	// Find the outer region wrapper — its innerHTML is replaced after the fetch.
	const region = document.querySelector(
		`[data-dsgo-query-region="${ queryId }"]`
	);
	const blobsHost = document.querySelector(
		`[data-dsgo-blobs-for="${ queryId }"]`
	);

	if ( ! region || ! blobsHost ) {
		ctx.busy = false;
		return;
	}

	// Also find the list container (role="container") so we can aria-busy it
	// during the fetch. The region wrapper contains filter/pagination children
	// too; scoping with data-dsgo-query-role avoids aria-busy on those.
	const listContainer = region.querySelector(
		`[data-dsgo-query-id="${ queryId }"][data-dsgo-query-role="container"]`
	);
	if ( listContainer ) {
		listContainer.setAttribute( 'aria-busy', 'true' );
	}

	try {
		const attrsEl = blobsHost.querySelector( 'script[data-dsgo-attrs]' );
		const innerEl = blobsHost.querySelector( 'script[data-dsgo-inner]' );
		if ( ! attrsEl || ! innerEl ) return;

		const attributes  = JSON.parse( attrsEl.textContent );
		const innerBlocks = JSON.parse( innerEl.textContent );
		const params      = dsgoCollectParams( url );

		const restUrl = ctx.restUrl
			|| ( window.wpApiSettings?.root || '/wp-json/' ) + 'designsetgo/v1/query/render';
		const restNonce = ctx.nonce || window.wpApiSettings?.nonce || '';
		const res = yield fetch( restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': restNonce,
			},
			body: JSON.stringify( {
				queryId,
				attributes,
				page: 1,
				innerBlocks,
				params,
				currentUrl: url.toString(),
			} ),
		} );

		if ( ! res.ok ) {
			// eslint-disable-next-line no-console
			console.warn(
				`[designsetgo/query] filter refresh failed (${ res.status }). If 401, the nonce has likely expired — reload the page.`
			);
			return;
		}
		const data = yield res.json();

		// Parse the returned region HTML and swap the outer region's innerHTML.
		// This updates the list, pagination, no-results, and active-filter chips
		// in one operation. The outer region element (and its data attribute) stays
		// intact so the IAPI context survives the swap.
		const doc       = new DOMParser().parseFromString( data.html || '', 'text/html' );
		const newRegion = doc.querySelector( `[data-dsgo-query-region="${ queryId }"]` );
		if ( newRegion ) {
			// eslint-disable-next-line no-unsanitized/property -- server-rendered, WordPress-escaped content.
			region.innerHTML = newRegion.innerHTML;
		}

		// Sync the browser URL without a page reload.
		window.history.replaceState( {}, '', url.toString() );
		ctx.page = 1;
	} finally {
		ctx.busy = false;
		// listContainer may have been replaced by the innerHTML swap above;
		// re-query from the region to get the fresh element.
		const freshList = region.querySelector(
			`[data-dsgo-query-id="${ queryId }"][data-dsgo-query-role="container"]`
		);
		if ( freshList ) {
			freshList.setAttribute( 'aria-busy', 'false' );
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
 * @param {Object} ctx  IAPI context (must have .queryId).
 * @param {URL}    url  New URL to navigate to.
 */
async function dsgoQueryRefreshPlain( ctx, url ) {
	const queryId = ctx.queryId;
	if ( ! queryId || ctx.busy ) return;
	ctx.busy = true;

	const region = document.querySelector(
		`[data-dsgo-query-region="${ queryId }"]`
	);
	const blobsHost = document.querySelector(
		`[data-dsgo-blobs-for="${ queryId }"]`
	);

	if ( ! region || ! blobsHost ) {
		ctx.busy = false;
		return;
	}

	const listContainer = region.querySelector(
		`[data-dsgo-query-id="${ queryId }"][data-dsgo-query-role="container"]`
	);
	if ( listContainer ) {
		listContainer.setAttribute( 'aria-busy', 'true' );
	}

	try {
		const attrsEl = blobsHost.querySelector( 'script[data-dsgo-attrs]' );
		const innerEl = blobsHost.querySelector( 'script[data-dsgo-inner]' );
		if ( ! attrsEl || ! innerEl ) return;

		const attributes  = JSON.parse( attrsEl.textContent );
		const innerBlocks = JSON.parse( innerEl.textContent );
		const params      = dsgoCollectParams( url );

		const restUrl = ctx.restUrl
			|| ( window.wpApiSettings?.root || '/wp-json/' ) + 'designsetgo/v1/query/render';
		const restNonce = ctx.nonce || window.wpApiSettings?.nonce || '';
		const res = await fetch( restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': restNonce,
			},
			body: JSON.stringify( {
				queryId,
				attributes,
				page: 1,
				innerBlocks,
				params,
				currentUrl: url.toString(),
			} ),
		} );

		if ( ! res.ok ) {
			// eslint-disable-next-line no-console
			console.warn(
				`[designsetgo/query] debounced refresh failed (${ res.status }). If 401, the nonce has likely expired — reload the page.`
			);
			return;
		}
		const data = await res.json();

		const doc       = new DOMParser().parseFromString( data.html || '', 'text/html' );
		const newRegion = doc.querySelector( `[data-dsgo-query-region="${ queryId }"]` );
		if ( newRegion ) {
			// eslint-disable-next-line no-unsanitized/property -- server-rendered, WordPress-escaped content.
			region.innerHTML = newRegion.innerHTML;
		}

		window.history.replaceState( {}, '', url.toString() );
		ctx.page = 1;
	} finally {
		ctx.busy = false;
		const freshList = region.querySelector(
			`[data-dsgo-query-id="${ queryId }"][data-dsgo-query-role="container"]`
		);
		if ( freshList ) {
			freshList.setAttribute( 'aria-busy', 'false' );
		}
	}
}
