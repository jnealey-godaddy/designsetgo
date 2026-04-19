/**
 * Dynamic Query — Interactivity API view script.
 *
 * Handles load-more pagination: fetches the next page via the REST API and
 * appends returned items to the list, updating aria-busy + focus per
 * accessibility expectations.
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

				// Fix 3: use wpApiSettings.root so subdirectory WP installs work.
				const restRoot = window.wpApiSettings?.root || '/wp-json/';
				const res = yield fetch(
					`${ restRoot }designsetgo/v1/query/render`,
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
	},
} );
