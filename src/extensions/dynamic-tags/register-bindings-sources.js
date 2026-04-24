/**
 * JS-side Block Bindings registration.
 *
 * Each `register_block_bindings_source()` call on the PHP side handles
 * the FRONTEND render. To get a live, in-canvas preview of the resolved
 * value while authors edit a post, WordPress also needs a JS-side
 * `registerBlockBindingsSource` with a `getValues` callback — without
 * it the editor falls back to the source's label (e.g. "Post title")
 * as a placeholder.
 *
 * Resolution rules:
 *  - When a block declares `context.postId` (Query Loop inner blocks,
 *    template parts, etc.) AND that ID differs from the main editor
 *    post, resolve via `core-data` `getEntityRecord` so each loop item
 *    renders its own data instead of all showing the editor post.
 *  - When the context post matches the main editor post (the default
 *    case for a regular post being edited), prefer the editor store's
 *    edited attributes so the preview tracks unsaved title/excerpt
 *    edits in real time.
 *  - Sources that depend on data the editor doesn't have locally (ACF,
 *    post-meta, archive, user) have no JS resolver — core falls back
 *    to the source label until save → render pulls the real value
 *    through PHP.
 *
 * Registers on `dom-ready` so the @wordpress/blocks store is fully
 * initialised before we register, regardless of script load order.
 */
import {
	registerBlockBindingsSource,
	getBlockBindingsSource,
} from '@wordpress/blocks';
import { store as editorStore } from '@wordpress/editor';
import { store as coreStore } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';

// Each entry: PHP source slug → label + a resolver that returns a
// scalar from the right source. The runner below maps the scalar
// across whichever block attributes the binding is wired to.
const SCALAR_RESOLVERS = {
	'designsetgo/post-title': {
		label: __('Post title', 'designsetgo'),
		entityField: (record) =>
			record?.title?.rendered ?? record?.title ?? '',
		editorField: (editor) => {
			const live = editor?.getEditedPostAttribute?.('title');
			if (typeof live === 'string') {
				return live;
			}
			return editor?.getCurrentPost?.()?.title ?? '';
		},
	},
	'designsetgo/post-excerpt': {
		label: __('Post excerpt', 'designsetgo'),
		entityField: (record) =>
			record?.excerpt?.rendered ?? record?.excerpt ?? '',
		editorField: (editor) => {
			const live = editor?.getEditedPostAttribute?.('excerpt');
			if (typeof live === 'string') {
				return live;
			}
			return editor?.getCurrentPost?.()?.excerpt ?? '';
		},
	},
	'designsetgo/post-id': {
		label: __('Post ID', 'designsetgo'),
		// Return the per-block context post ID directly when present —
		// no entity-record fetch needed.
		fromContext: (context) =>
			context?.postId ? String(context.postId) : '',
		editorField: (editor) => {
			const id = editor?.getCurrentPostId?.();
			return id ? String(id) : '';
		},
	},
	'designsetgo/post-type': {
		label: __('Post type', 'designsetgo'),
		fromContext: (context) => context?.postType ?? '',
		editorField: (editor) => editor?.getCurrentPostType?.() ?? '',
	},
	'designsetgo/post-permalink': {
		label: __('Post permalink', 'designsetgo'),
		entityField: (record) => record?.link ?? '',
		editorField: (editor) => editor?.getCurrentPost?.()?.link ?? '',
	},
	'designsetgo/post-date': {
		label: __('Post publish date', 'designsetgo'),
		entityField: (record) => record?.date ?? '',
		editorField: (editor) => {
			const live = editor?.getEditedPostAttribute?.('date');
			if (typeof live === 'string' && live) {
				return live;
			}
			return editor?.getCurrentPost?.()?.date ?? '';
		},
	},
	'designsetgo/post-modified-date': {
		label: __('Post modified date', 'designsetgo'),
		entityField: (record) => record?.modified ?? '',
		editorField: (editor) => editor?.getCurrentPost?.()?.modified ?? '',
	},
	'designsetgo/site-title': {
		label: __('Site title', 'designsetgo'),
		fromSite: (site) => site?.title ?? '',
	},
	'designsetgo/site-tagline': {
		label: __('Site tagline', 'designsetgo'),
		fromSite: (site) => site?.description ?? '',
	},
	'designsetgo/site-url': {
		label: __('Site URL', 'designsetgo'),
		fromSite: (site) => site?.url ?? '',
	},
};

function resolveValue(resolver, { context, select }) {
	// Site-level sources don't depend on a post.
	if (typeof resolver.fromSite === 'function') {
		const site = select(coreStore)?.getEntityRecord?.('root', 'site');
		return resolver.fromSite(site) ?? '';
	}

	const editor = select(editorStore);
	const editorPostId = editor?.getCurrentPostId?.() || 0;
	const ctxPostId = context?.postId || 0;
	const ctxPostType = context?.postType || '';

	const isLoopItem = ctxPostId && ctxPostId !== editorPostId;

	// Pure-context sources (post-id, post-type) read the per-block context
	// directly without touching either store.
	if (typeof resolver.fromContext === 'function') {
		if (isLoopItem) {
			return resolver.fromContext(context) ?? '';
		}
		return resolver.editorField(editor) ?? '';
	}

	// Loop items: pull the queried post's record so every item shows its
	// own data instead of the editor post's.
	if (isLoopItem && typeof resolver.entityField === 'function') {
		const record = ctxPostType
			? select(coreStore)?.getEntityRecord?.(
					'postType',
					ctxPostType,
					ctxPostId
				)
			: null;
		// Returning empty string lets WP fall back to the source label
		// placeholder while core-data fetches the record (entity records
		// load lazily); once loaded, getValues re-runs.
		return record ? (resolver.entityField(record) ?? '') : '';
	}

	// Default: live editor state (tracks unsaved edits in real time).
	if (typeof resolver.editorField === 'function') {
		return resolver.editorField(editor) ?? '';
	}

	return '';
}

function buildGetValues(resolver) {
	return ({ bindings, context, select }) => {
		const value = resolveValue(resolver, { context, select });
		const out = {};
		Object.keys(bindings || {}).forEach((attr) => {
			out[attr] = value;
		});
		return out;
	};
}

export function registerEditorBindings() {
	Object.entries(SCALAR_RESOLVERS).forEach(([slug, resolver]) => {
		// Avoid double-registration (HMR / double-import) without
		// swallowing any other error registerBlockBindingsSource may
		// throw — narrow catch only after a positive existence check.
		if (getBlockBindingsSource?.(slug)) {
			return;
		}
		registerBlockBindingsSource({
			name: slug,
			label: resolver.label,
			usesContext: ['postId', 'postType'],
			getValues: buildGetValues(resolver),
		});
	});
}

domReady(registerEditorBindings);
