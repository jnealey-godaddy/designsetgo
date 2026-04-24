/**
 * useDynamicTagFields — hook that lazily loads discovered fields for a source.
 *
 * Results are cached by `${source}|${postType}|${returns}`.
 */
import { useEffect, useState, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const cache = new Map();

export function useDynamicTagFields( { source, postType, returns, supportsFieldDiscovery } ) {
	const [ state, setState ] = useState( { status: 'idle', fields: [], error: null } );
	const keyRef = useRef( '' );

	useEffect( () => {
		if ( ! source || ! supportsFieldDiscovery ) {
			setState( { status: 'idle', fields: [], error: null } );
			return undefined;
		}

		const key = `${ source }|${ postType || '' }|${ returns || '' }`;
		keyRef.current = key;

		if ( cache.has( key ) ) {
			setState( { status: 'ready', fields: cache.get( key ), error: null } );
			return undefined;
		}

		setState( { status: 'loading', fields: [], error: null } );
		let cancelled = false;

		apiFetch( {
			path: addQueryArgs( '/designsetgo/v1/dynamic-tags/fields', {
				source,
				postType,
				returns,
			} ),
		} )
			.then( ( response ) => {
				if ( cancelled || keyRef.current !== key ) {
					return;
				}
				const fields = response.fields || [];
				cache.set( key, fields );
				setState( { status: 'ready', fields, error: null } );
			} )
			.catch( ( error ) => {
				if ( cancelled || keyRef.current !== key ) {
					return;
				}
				setState( { status: 'error', fields: [], error } );
			} );

		return () => {
			cancelled = true;
		};
	}, [ source, postType, returns, supportsFieldDiscovery ] );

	return state;
}
