/**
 * useDynamicTagPreview — debounced live-preview fetcher.
 *
 * Given a source + args + postId, returns the resolved preview value
 * (scalar string or image descriptor) so the picker can show users
 * exactly what will render on the frontend.
 */
import { useEffect, useState, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const DEBOUNCE_MS = 250;

export function useDynamicTagPreview( { source, args, postId, size } ) {
	const [ state, setState ] = useState( { status: 'idle', value: null, returns: null, error: null } );
	const timerRef = useRef( null );
	const requestRef = useRef( 0 );

	useEffect( () => {
		if ( ! source ) {
			setState( { status: 'idle', value: null, returns: null, error: null } );
			return undefined;
		}

		clearTimeout( timerRef.current );
		const requestId = ++requestRef.current;
		setState( ( prev ) => ( { ...prev, status: 'loading' } ) );

		timerRef.current = setTimeout( () => {
			apiFetch( {
				path: addQueryArgs( '/designsetgo/v1/dynamic-tags/preview', {
					source,
					args: args || {},
					postId: postId || undefined,
					size: size || undefined,
				} ),
			} )
				.then( ( response ) => {
					if ( requestRef.current !== requestId ) {
						return;
					}
					setState( {
						status: response.status || 'resolved',
						value: response.value ?? null,
						returns: response.returns || null,
						error: null,
					} );
				} )
				.catch( ( error ) => {
					if ( requestRef.current !== requestId ) {
						return;
					}
					setState( { status: 'error', value: null, returns: null, error } );
				} );
		}, DEBOUNCE_MS );

		return () => {
			clearTimeout( timerRef.current );
		};
	}, [ source, JSON.stringify( args || {} ), postId, size ] );

	return state;
}
