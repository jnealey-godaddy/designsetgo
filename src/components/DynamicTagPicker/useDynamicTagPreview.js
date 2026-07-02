/**
 * useDynamicTagPreview — debounced live-preview fetcher with editor fallback.
 *
 * Given a source + args + postId, returns the resolved preview value
 * (scalar string or image descriptor) so the picker can show users
 * exactly what will render on the frontend.
 *
 * For sources that read post-intrinsic fields, an in-editor value from
 * `core/editor`'s edited (unsaved) post attributes is preferred over the
 * REST round-trip — this keeps the preview live as authors type a title
 * or excerpt, and works even on auto-drafts whose persisted DB row is
 * still empty.
 */
import { useEffect, useMemo, useState, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';

const DEBOUNCE_MS = 250;

// Sources that map directly to an editor post attribute. Resolving these
// client-side from the editor store sidesteps two REST round-trip hazards:
//   1. auto-draft posts whose title hasn't been saved yet,
//   2. unauthenticated/CORS edge cases on REST.
function resolveFromEditor(source, editedPost) {
	if (!editedPost) {
		return undefined;
	}
	switch (source) {
		case 'designsetgo/post-title':
			return typeof editedPost.title === 'string' ? editedPost.title : '';
		case 'designsetgo/post-excerpt':
			return typeof editedPost.excerpt === 'string'
				? editedPost.excerpt
				: '';
		case 'designsetgo/post-id':
			return editedPost.id ? String(editedPost.id) : '';
		case 'designsetgo/post-type':
			return editedPost.type || '';
		case 'designsetgo/post-permalink':
			return editedPost.link || '';
		default:
			return undefined;
	}
}

export function useDynamicTagPreview({ source, args, postId, size }) {
	const [state, setState] = useState({
		status: 'idle',
		value: null,
		returns: null,
		error: null,
	});
	const timerRef = useRef(null);
	const requestRef = useRef(0);

	const editedPost = useSelect((select) => {
		const editor = select(editorStore);
		if (!editor || !editor.getCurrentPost) {
			return null;
		}
		const current = editor.getCurrentPost();
		if (!current) {
			return null;
		}
		return {
			id: editor.getCurrentPostId?.() || current.id || 0,
			type: editor.getCurrentPostType?.() || current.type || '',
			title:
				editor.getEditedPostAttribute?.('title') ?? current.title ?? '',
			excerpt:
				editor.getEditedPostAttribute?.('excerpt') ??
				current.excerpt ??
				'',
			link: current.link || '',
		};
	}, []);

	// Stabilise the args dependency — serialising inside the effect would
	// re-run JSON.stringify on every render, including renders where args
	// has not changed.
	const argsKey = useMemo(() => JSON.stringify(args || {}), [args]);

	useEffect(() => {
		if (!source) {
			setState({
				status: 'idle',
				value: null,
				returns: null,
				error: null,
			});
			return undefined;
		}

		// Editor-side shortcut: post-intrinsic scalar sources resolve from
		// the live editor state so the preview reflects unsaved edits.
		const editorValue = resolveFromEditor(source, editedPost);
		if (editorValue !== undefined) {
			setState({
				status: editorValue === '' ? 'empty' : 'resolved',
				value: editorValue === '' ? null : editorValue,
				returns: 'text',
				error: null,
			});
			return undefined;
		}

		clearTimeout(timerRef.current);
		const requestId = ++requestRef.current;
		setState((prev) => ({ ...prev, status: 'loading' }));

		timerRef.current = setTimeout(() => {
			apiFetch({
				path: addQueryArgs('/designsetgo/v1/dynamic-tags/preview', {
					source,
					args: args || {},
					postId: postId || undefined,
					size: size || undefined,
				}),
			})
				.then((response) => {
					if (requestRef.current !== requestId) {
						return;
					}
					setState({
						status: response.status || 'resolved',
						value: response.value ?? null,
						returns: response.returns || null,
						error: null,
					});
				})
				.catch((error) => {
					if (requestRef.current !== requestId) {
						return;
					}
					setState({
						status: 'error',
						value: null,
						returns: null,
						error,
					});
				});
		}, DEBOUNCE_MS);

		return () => {
			clearTimeout(timerRef.current);
		};
	}, [
		source,
		argsKey,
		postId,
		size,
		editedPost?.title,
		editedPost?.excerpt,
		editedPost?.id,
		editedPost?.type,
		editedPost?.link,
	]);

	return state;
}
