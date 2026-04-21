/**
 * useQueryHostPreview — editor preview data for layout blocks bound to a
 * Dynamic Query (slider, scroll-slides, future hosts).
 *
 * Combines three paths into one hook:
 *  - posts: useEntityRecords('postType', postType, args)
 *  - users/terms: /designsetgo/v1/query/preview REST route via apiFetch
 *  - relationship: useQueryPreview (reads parent post's field, resolves IDs)
 *
 * Also fetches server-rendered HTML for each item via useRenderedItems so
 * items 1..N can be shown as true-to-frontend markup while item 0 stays
 * editable via InnerBlocks.
 *
 * The goal is a single call site for layout blocks:
 *
 *     const { records, hasResolved, serverHtml, loading } =
 *         useQueryHostPreview({ attributes, queryId, innerBlocks });
 *
 * where `innerBlocks` is the host's template block tree (e.g. the one
 * designsetgo/slide child of designsetgo/slider) and `serverHtml[idx]` is the
 * rendered inner HTML of item idx (see useRenderedItems for extraction
 * details).
 *
 * NOTE: the data-fetching helpers here mirror usePosts/useRemotePreview in
 * src/blocks/query/components/EditorPreviewList.js. If those get fixed, mirror
 * the fix here too — or consolidate into a single module in a follow-up.
 *
 * @since 2.6.0
 */
import { useMemo, useState, useEffect } from '@wordpress/element';
import { useEntityRecords } from '@wordpress/core-data';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { __, sprintf } from '@wordpress/i18n';
import useQueryPreview from './useQueryPreview';
import useRenderedItems from './useRenderedItems';

/**
 * @param {Object} root0
 * @param {Object} root0.attributes  Parent query block attributes (source,
 *                                   postType, perPage, filters, etc.).
 * @param {string} root0.queryId     Parent query's queryId attribute.
 * @param {Array}  root0.innerBlocks Template block tree (rendered once per item).
 * @param {boolean} [root0.enabled]  Skip all fetching when false.
 * @return {{
 *   records: Array|null,
 *   hasResolved: boolean,
 *   serverHtml: Array<string>|null,
 *   loading: boolean,
 * }}
 */
export default function useQueryHostPreview({
	attributes,
	queryId,
	innerBlocks,
	enabled = true,
}) {
	const source = attributes?.source || 'posts';
	const isPostsLike =
		source === 'posts' || source === 'manual' || source === 'current';
	const isRelationship = source === 'relationship';

	const postsData = usePosts(attributes, enabled && isPostsLike);
	const remoteData = useRemotePreview(
		attributes,
		enabled && !isPostsLike && !isRelationship
	);
	const relationshipData = useQueryPreview({
		source,
		relationshipField: attributes?.relationshipField || '',
		perPage: attributes?.perPage || 6,
	});

	const rendered = useRenderedItems({
		queryId,
		attributes,
		innerBlocks,
		enabled:
			enabled &&
			Array.isArray(innerBlocks) &&
			innerBlocks.length > 0 &&
			!!queryId,
	});

	const { records, hasResolved } = isPostsLike
		? postsData
		: isRelationship
		? relationshipData
		: remoteData;

	return {
		records,
		hasResolved,
		serverHtml: rendered.items,
		loading: rendered.loading,
	};
}

// ---------------------------------------------------------------------------
// Data hooks (mirrored from EditorPreviewList — see NOTE in header above).
// ---------------------------------------------------------------------------

function usePosts(attributes, enabled) {
	const authorKey = Array.isArray(attributes?.author)
		? attributes.author.join(',')
		: attributes?.author;
	const queryArgs = useMemo(
		() => buildCoreDataQuery(attributes || {}),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[
			attributes?.perPage,
			attributes?.offset,
			attributes?.orderBy,
			attributes?.order,
			attributes?.search,
			authorKey,
		]
	);

	const postType = enabled ? attributes?.postType || 'post' : '';
	const result = useEntityRecords('postType', postType, queryArgs);

	if (!enabled) {
		return { records: null, hasResolved: true };
	}

	const mapped = result.records
		? result.records.map((post) => ({
				...post,
				id: post.id,
				name:
					post.title?.rendered ||
					sprintf(
						/* translators: %d: post ID */
						__('Post %d', 'designsetgo'),
						post.id
					),
		  }))
		: null;

	return { records: mapped, hasResolved: result.hasResolved };
}

function useRemotePreview(attributes, enabled) {
	const [state, setState] = useState({ records: null, hasResolved: false });

	// Serialize the full attributes object so orderBy/order/offset/filters/etc.
	// all invalidate the cache. `attributes` is always a plain serialisable
	// object (block attribute storage), so JSON.stringify is deterministic.
	const cacheKey = enabled ? JSON.stringify(attributes || {}) : null;

	useEffect(() => {
		if (!enabled || !cacheKey) {
			return undefined;
		}

		let cancelled = false;
		setState({ records: null, hasResolved: false });

		apiFetch({
			path: addQueryArgs('/designsetgo/v1/query/preview', {
				attributes,
			}),
		})
			.then((items) => {
				if (!cancelled) {
					setState({ records: items, hasResolved: true });
				}
			})
			.catch(() => {
				if (!cancelled) {
					setState({ records: [], hasResolved: true });
				}
			});

		return () => {
			cancelled = true;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [cacheKey, enabled]);

	if (!enabled) {
		return { records: null, hasResolved: true };
	}

	return state;
}

function buildCoreDataQuery(attributes) {
	const args = {
		per_page: attributes.perPage || 6,
		offset: attributes.offset || 0,
		orderby: (attributes.orderBy || 'date').toLowerCase(),
		order: (attributes.order || 'DESC').toLowerCase(),
		status: 'publish',
		_embed: true,
	};

	if (attributes.search) {
		args.search = attributes.search;
	}

	if (Array.isArray(attributes.author) && attributes.author.length) {
		args.author = attributes.author.join(',');
	}

	return args;
}

// ---------------------------------------------------------------------------
// Per-item context builder. Shared with EditorPreviewList (mirror if changed).
// ---------------------------------------------------------------------------

/**
 * Build the BlockContextProvider value for a single preview item so inner
 * blocks (and Block Bindings) resolve against the iterated post/user/term
 * just like they do on the frontend.
 *
 * @param {Object} item      Preview item.
 * @param {string} source    Query source attribute.
 * @param {number} index     Zero-based item index.
 * @param {Object} [outerCtx] Outer block context (may contain parentItem).
 * @return {Object} Context object.
 */
export function buildItemContext(item, source, index, outerCtx) {
	const enrichment = {
		'designsetgo/itemIndex': index,
		'designsetgo/itemMeta': item.meta || {},
		'designsetgo/itemTerms': buildTermsMap(item),
		'designsetgo/isAuthenticated': true,
	};

	let base;
	if (source === 'posts' || source === 'manual' || source === 'current') {
		base = {
			postId: item.id,
			postType: item.type || 'post',
		};
	} else if (source === 'users') {
		base = {
			'designsetgo/currentItemId': item.id,
			'designsetgo/currentItemType': 'user',
		};
	} else if (source === 'terms') {
		base = {
			'designsetgo/currentItemId': item.id,
			'designsetgo/currentItemType': 'term',
		};
	} else {
		base = {};
	}

	const parentItem =
		outerCtx?.['designsetgo/parentItem'] ??
		(base.postId !== undefined
			? { postId: base.postId, postType: base.postType }
			: { postId: item.id, postType: item.type || 'post' });

	return {
		...base,
		...enrichment,
		'designsetgo/parentItem': parentItem,
	};
}

function buildTermsMap(item) {
	const map = {};
	const embedded = item?._embedded?.['wp:term'];
	if (!Array.isArray(embedded)) {
		return map;
	}
	embedded.forEach((taxTerms) => {
		if (!Array.isArray(taxTerms) || taxTerms.length === 0) {
			return;
		}
		const taxonomy = taxTerms[0]?.taxonomy;
		if (!taxonomy) {
			return;
		}
		map[taxonomy] = taxTerms.map((t) => t.slug).filter(Boolean);
	});
	return map;
}
