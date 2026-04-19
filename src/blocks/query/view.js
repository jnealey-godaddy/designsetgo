/**
 * Dynamic Query — Interactivity API view script.
 *
 * Handles load-more pagination: fetches the next page via
 * /wp-json/designsetgo/v1/query/render and appends returned items to the list,
 * updating aria-busy + focus per accessibility expectations.
 *
 * Task 14 will extend this store with filter + sort + reset actions.
 *
 * @since 2.1.0
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'designsetgo/query', {
	actions: {
		*loadMore() {
			const ctx = getContext();
			if ( ctx.busy ) return;
			ctx.busy = true;

			const { ref } = getElement();
			// The button is a sibling of the list; look upward for the nearest
			// wrapper that has data-dsgo-query-id on it (the <ul>/<ol>/<div>).
			const container =
				ref.closest( '[data-dsgo-query-id]' ) ||
				document.querySelector(
					`[data-dsgo-query-id="${ ctx.queryId }"]`
				);

			if ( ! container ) {
				ctx.busy = false;
				return;
			}

			container.setAttribute( 'aria-busy', 'true' );

			try {
				const attrsEl = container.querySelector(
					'script[data-dsgo-attrs]'
				);
				const innerEl = container.querySelector(
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

				const res = yield fetch(
					'/wp-json/designsetgo/v1/query/render',
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce':
								window.wpApiSettings?.nonce || '',
						},
						body: JSON.stringify( {
							queryId: ctx.queryId,
							attributes,
							page: nextPage,
							innerBlocks,
						} ),
					}
				);

				if ( ! res.ok ) {
					ctx.busy = false;
					container.setAttribute( 'aria-busy', 'false' );
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

					// Prefer a link or button inside the new item; fall back to the item.
					const focusable =
						firstNew.querySelector(
							'a, button, input, [tabindex]:not([tabindex="-1"])'
						) || firstNew;

					if ( focusable instanceof HTMLElement ) {
						if ( ! focusable.getAttribute( 'tabindex' ) ) {
							focusable.setAttribute( 'tabindex', '-1' );
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
			}
		},
	},
} );
