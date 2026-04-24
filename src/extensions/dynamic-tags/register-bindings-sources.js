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
 * For post / site sources we resolve from the editor's live state
 * (unsaved title, etc.) so the preview tracks the author's edits in
 * real time. For sources that depend on data the editor doesn't have
 * locally (ACF, post-meta, archive) we omit the JS-side getValues so
 * core falls back to the placeholder until save → render pulls the
 * real value through PHP.
 *
 * Registers on `dom-ready` so the @wordpress/blocks store is fully
 * initialised before we register, regardless of script load order.
 */
import { registerBlockBindingsSource } from '@wordpress/blocks';
import { store as editorStore } from '@wordpress/editor';
import { store as coreStore } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';

// Each entry: PHP source slug → label + a getValue function that returns
// a single resolved scalar from the editor / core-data stores. The
// runner below maps the scalar across whichever block attributes the
// binding is wired to.
const SCALAR_RESOLVERS = {
	'designsetgo/post-title': {
		label: __('Post title', 'designsetgo'),
		getValue: (select) => {
			const editor = select(editorStore);
			const title = editor?.getEditedPostAttribute?.('title');
			if (typeof title === 'string') {
				return title;
			}
			return editor?.getCurrentPost?.()?.title ?? '';
		},
	},
	'designsetgo/post-excerpt': {
		label: __('Post excerpt', 'designsetgo'),
		getValue: (select) => {
			const editor = select(editorStore);
			const excerpt = editor?.getEditedPostAttribute?.('excerpt');
			if (typeof excerpt === 'string') {
				return excerpt;
			}
			return editor?.getCurrentPost?.()?.excerpt ?? '';
		},
	},
	'designsetgo/post-id': {
		label: __('Post ID', 'designsetgo'),
		getValue: (select) =>
			String(select(editorStore)?.getCurrentPostId?.() ?? ''),
	},
	'designsetgo/post-type': {
		label: __('Post type', 'designsetgo'),
		getValue: (select) => select(editorStore)?.getCurrentPostType?.() ?? '',
	},
	'designsetgo/post-permalink': {
		label: __('Post permalink', 'designsetgo'),
		getValue: (select) =>
			select(editorStore)?.getCurrentPost?.()?.link ?? '',
	},
	'designsetgo/post-date': {
		label: __('Post publish date', 'designsetgo'),
		getValue: (select) => {
			const editor = select(editorStore);
			const date = editor?.getEditedPostAttribute?.('date');
			if (typeof date === 'string' && date) {
				return date;
			}
			return editor?.getCurrentPost?.()?.date ?? '';
		},
	},
	'designsetgo/post-modified-date': {
		label: __('Post modified date', 'designsetgo'),
		getValue: (select) =>
			select(editorStore)?.getCurrentPost?.()?.modified ?? '',
	},
	'designsetgo/site-title': {
		label: __('Site title', 'designsetgo'),
		getValue: (select) =>
			select(coreStore)?.getEntityRecord?.('root', 'site')?.title ?? '',
	},
	'designsetgo/site-tagline': {
		label: __('Site tagline', 'designsetgo'),
		getValue: (select) =>
			select(coreStore)?.getEntityRecord?.('root', 'site')?.description ??
			'',
	},
	'designsetgo/site-url': {
		label: __('Site URL', 'designsetgo'),
		getValue: (select) =>
			select(coreStore)?.getEntityRecord?.('root', 'site')?.url ?? '',
	},
};

function buildGetValues(slug, getValue) {
	return ({ bindings, select }) => {
		const value = getValue(select) ?? '';
		// `bindings` is the per-block-attribute binding object. Project the
		// single scalar across every attribute the user wired up so the
		// editor canvas reflects the resolved value live, regardless of
		// which attribute (content / url / alt / …) the binding targets.
		const out = {};
		Object.keys(bindings || {}).forEach((attr) => {
			out[attr] = value;
		});
		return out;
	};
}

export function registerEditorBindings() {
	Object.entries(SCALAR_RESOLVERS).forEach(([slug, { label, getValue }]) => {
		try {
			registerBlockBindingsSource({
				name: slug,
				label,
				usesContext: ['postId', 'postType'],
				getValues: buildGetValues(slug, getValue),
			});
		} catch (e) {
			// Already registered (HMR / double-import) — ignore.
		}
	});
}

domReady(registerEditorBindings);
