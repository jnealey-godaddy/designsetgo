/**
 * useDynamicTagSources — hook that fetches the Dynamic Tag source catalog.
 *
 * Caches the response at module scope so opening the picker multiple
 * times doesn't hit REST repeatedly.
 */
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

let cachedPromise = null;

export function useDynamicTagSources( { returns } = {} ) {
	const [ state, setState ] = useState( () => ( {
		status: 'loading',
		groups: [],
		sources: [],
		error: null,
	} ) );

	useEffect( () => {
		let cancelled = false;

		if ( ! cachedPromise ) {
			cachedPromise = apiFetch( {
				path: '/designsetgo/v1/dynamic-tags/sources',
			} ).catch( ( error ) => {
				cachedPromise = null;
				throw error;
			} );
		}

		cachedPromise
			.then( ( response ) => {
				if ( cancelled ) {
					return;
				}
				setState( {
					status: 'ready',
					groups: response.groups || [],
					sources: response.sources || [],
					error: null,
				} );
			} )
			.catch( ( error ) => {
				if ( cancelled ) {
					return;
				}
				setState( {
					status: 'error',
					groups: [],
					sources: [],
					error,
				} );
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	if ( state.status !== 'ready' || ! returns ) {
		return state;
	}

	const wanted = Array.isArray( returns ) ? returns : [ returns ];
	const filtered = state.sources.filter( ( source ) => {
		const sourceReturns = Array.isArray( source.returns ) ? source.returns : [];
		return wanted.some( ( type ) => sourceReturns.includes( type ) );
	} );

	return { ...state, sources: filtered };
}
