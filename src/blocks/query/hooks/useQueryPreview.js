import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

/**
 * Runs the same render helper server-side via REST so the editor can show
 * a live "N matches" badge without reimplementing WP_Query in the browser.
 *
 * Debounced by React re-render cadence + JSON.stringify key so rapid attribute
 * changes coalesce into a single request.
 * @param {Object} root0            The hook options object.
 * @param {Object} root0.attributes The query block attributes to send to the server.
 * @param {string} root0.queryId    The unique query ID used to scope the REST request.
 */
export default function useQueryPreview({ attributes, queryId }) {
	const [state, setState] = useState({
		loading: false,
		totalItems: null,
		error: null,
	});

	const payloadKey = JSON.stringify(attributes);

	useEffect(() => {
		if (!queryId) {
			return undefined;
		}
		let cancelled = false;
		setState((s) => ({ ...s, loading: true }));

		apiFetch({
			path: '/designsetgo/v1/query/render',
			method: 'POST',
			data: {
				queryId,
				attributes,
				page: 1,
				innerBlocks: '',
			},
		})
			.then((res) => {
				if (cancelled) {
					return;
				}
				setState({
					loading: false,
					totalItems:
						typeof res?.totalItems === 'number'
							? res.totalItems
							: null,
					error: null,
				});
			})
			.catch((err) => {
				if (cancelled) {
					return;
				}
				setState({ loading: false, totalItems: null, error: err });
			});

		return () => {
			cancelled = true;
		};
		// payloadKey makes the effect rerun on attribute changes (cheaper than deep compare).
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [payloadKey, queryId]);

	return state;
}
