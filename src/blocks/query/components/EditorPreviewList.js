/**
 * EditorPreviewList — live preview of query results in the block editor.
 *
 * For Posts source: uses `useEntityRecords` from @wordpress/core-data.
 * For Users/Terms source: fetches from /designsetgo/v1/query/preview via apiFetch.
 *
 * Renders item 0 as editable InnerBlocks (the template the author designs).
 * Renders items 1..N as read-only BlockPreview instances with per-item context
 * so Block Bindings in the template resolve with the correct data.
 *
 * @since 2.2.0
 */
import { __, sprintf } from '@wordpress/i18n';
import { useMemo, useState, useEffect } from '@wordpress/element';
import { useEntityRecords } from '@wordpress/core-data';
import { BlockPreview, BlockContextProvider } from '@wordpress/block-editor';
import { Spinner, Placeholder } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

/**
 * @param {Object} props
 * @param {Object} props.attributes       Block attributes.
 * @param {Array}  props.innerBlocks      Current inner blocks (from useSelect getBlocks).
 * @param {Object} props.innerBlocksProps Props from useInnerBlocksProps in edit.js —
 *                                        spread onto item 0's container so the block
 *                                        editor "owns" the inner blocks slot correctly.
 */
export default function EditorPreviewList({
	attributes,
	innerBlocks,
	innerBlocksProps,
}) {
	const source = attributes.source || 'posts';
	const isPosts = source === 'posts';

	// Choose the data-fetching path based on source.
	const postsData = usePosts(attributes, isPosts);
	const remoteData = useRemotePreview(attributes, !isPosts);

	const { records, hasResolved } = isPosts ? postsData : remoteData;

	if (!hasResolved) {
		return (
			<Placeholder className="dsgo-query__editor-placeholder">
				<Spinner />
			</Placeholder>
		);
	}

	if (!records || records.length === 0) {
		return (
			<Placeholder
				className="dsgo-query__editor-placeholder"
				label={__('No results found.', 'designsetgo')}
				instructions={__(
					'Adjust the query settings to match your content.',
					'designsetgo'
				)}
			/>
		);
	}

	return (
		<ul className="dsgo-query__editor-preview-list">
			{records.map((item, idx) => {
				const context = buildContext(item, source);
				return (
					<li
						key={item.id}
						className={
							idx === 0
								? 'dsgo-query__editor-preview-item is-template-source'
								: 'dsgo-query__editor-preview-item is-read-only'
						}
					>
						<BlockContextProvider value={context}>
							{idx === 0 ? (
								<EditableTemplate
									innerBlocksProps={innerBlocksProps}
								/>
							) : (
								<BlockPreview
									blocks={innerBlocks}
									viewportWidth={1000}
									additionalStyles={[]}
								/>
							)}
						</BlockContextProvider>
					</li>
				);
			})}
		</ul>
	);
}

// ---------------------------------------------------------------------------
// Editable template (item 0)
// ---------------------------------------------------------------------------

/**
 * Renders the editable InnerBlocks for the first preview item (the template).
 *
 * Receives `innerBlocksProps` from the parent edit.js (where useInnerBlocksProps
 * is called) so there is exactly one InnerBlocks slot owner per block instance.
 *
 * @param {Object} props
 * @param {Object} props.innerBlocksProps Spread props from useInnerBlocksProps.
 */
function EditableTemplate({ innerBlocksProps }) {
	return <div {...innerBlocksProps} />;
}

// ---------------------------------------------------------------------------
// Data hooks
// ---------------------------------------------------------------------------

/**
 * Fetches posts via @wordpress/core-data useEntityRecords.
 * Only active when `enabled` is true (i.e. source === 'posts').
 *
 * @param {Object}  attributes Block attributes.
 * @param {boolean} enabled    Whether to actually fetch.
 */
function usePosts(attributes, enabled) {
	const queryArgs = useMemo(
		() => buildCoreDataQuery(attributes),
		// Stringify so the memo is stable for objects like taxQuery.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[JSON.stringify(attributes)]
	);

	// useEntityRecords must be called unconditionally (rules of hooks).
	// When not enabled we pass an empty postType which causes an early null return.
	const postType = enabled ? attributes.postType || 'post' : '';
	const result = useEntityRecords('postType', postType, queryArgs);

	if (!enabled) {
		return { records: null, hasResolved: true };
	}

	// Map WP REST post objects → unified { id, name, type } + keep original.
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
				// Preserve post.type for the context builder.
			}))
		: null;

	return { records: mapped, hasResolved: result.hasResolved };
}

/**
 * Fetches users or terms from the /designsetgo/v1/query/preview REST route.
 * Only active when `enabled` is true (i.e. source !== 'posts').
 *
 * Uses useState + useEffect (not useSelect) to avoid a custom store.
 *
 * @param {Object}  attributes Block attributes.
 * @param {boolean} enabled    Whether to actually fetch.
 */
function useRemotePreview(attributes, enabled) {
	const [state, setState] = useState({
		records: null,
		hasResolved: false,
	});

	// Stringify to get a stable dependency key.
	const cacheKey = enabled ? JSON.stringify(attributes) : null;

	useEffect(() => {
		if (!enabled || !cacheKey) {
			return;
		}

		let cancelled = false;
		setState({ records: null, hasResolved: false });

		apiFetch({
			path: '/designsetgo/v1/query/preview',
			method: 'GET',
			// apiFetch GET sends query params via `data`.
			data: { attributes },
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

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build the BlockContextProvider value for a single preview item.
 *
 * Posts use the native `postId` + `postType` shape that core Block Bindings
 * (post-meta etc.) understand. Users and Terms use the designsetgo custom
 * context keys.
 *
 * @param {Object} item   Preview item: { id, type, ... }.
 * @param {string} source The query block source attribute value.
 * @return {Object} Context object for BlockContextProvider.
 */
function buildContext(item, source) {
	if (source === 'posts' || source === 'manual' || source === 'current') {
		return {
			postId: item.id,
			postType: item.type || 'post',
		};
	}
	if (source === 'users') {
		return {
			'designsetgo/currentItemId': item.id,
			'designsetgo/currentItemType': 'user',
		};
	}
	if (source === 'terms') {
		return {
			'designsetgo/currentItemId': item.id,
			'designsetgo/currentItemType': 'term',
		};
	}
	return {};
}

/**
 * Translate block attributes into @wordpress/core-data useEntityRecords args.
 *
 * Covers the core fields (perPage, offset, orderby, order). Tax/meta query
 * mapping is intentionally omitted — they don't have a clean core-data
 * equivalent, and editor preview just needs "some real posts" to show.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} Query args for useEntityRecords.
 */
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
