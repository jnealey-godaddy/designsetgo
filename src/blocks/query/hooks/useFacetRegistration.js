/**
 * useFacetRegistration — auto-register a query-filter facet with the PHP registry.
 *
 * Called from `designsetgo/query-filter` edit.js whenever the block's facet
 * configuration changes. POSTs to /designsetgo/v1/query/facet-register so the
 * server-side FacetRegistry stays in sync with editor state.
 *
 * Deduplicates via a fingerprint ref so we don't POST on every re-render —
 * only fires when facetKey, type, or source actually changes.
 *
 * @since 2.2.0
 */
import { useEffect, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * @param {Object} options
 * @param {string} options.facetKey The facet registry key (typically the taxonomy slug).
 * @param {Object} options.config   Facet config: { type, source }.
 */
export function useFacetRegistration({ facetKey, config }) {
	const lastSent = useRef(null);

	useEffect(() => {
		if (!facetKey || !config?.type || !config?.source) {
			return;
		}

		const fingerprint = `${facetKey}::${config.type}::${config.source}`;
		if (fingerprint === lastSent.current) {
			return;
		}
		lastSent.current = fingerprint;

		apiFetch({
			path: '/designsetgo/v1/query/facet-register',
			method: 'POST',
			data: {
				facet_key: facetKey,
				config,
			},
		} ).catch( ( err ) => {
			// Retry on network-level failures; keep dedup for persistent 4xx so we
			// don't storm the server with the same bad request.
			if ( err?.code === 'fetch_error' || err?.code === 'offline_error' ) {
				lastSent.current = null;
			}
		} );
	}, [facetKey, config?.type, config?.source]);
}
