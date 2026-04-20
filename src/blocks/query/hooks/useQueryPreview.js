import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';
import { useEntityRecords } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';

/**
 * Unified query preview hook.
 *
 * For `source === 'relationship'`:
 *   Reads the field value from the editor's current post, normalizes it to an
 *   array of post IDs, then fetches those posts via useEntityRecords. Returns
 *   `{ records, hasResolved }`.
 *
 * For all other sources:
 *   Runs the same render helper server-side via REST so the editor can show
 *   a live "N matches" badge without reimplementing WP_Query in the browser.
 *   Debounced by React re-render cadence + JSON.stringify key so rapid
 *   attribute changes coalesce into a single request.
 *   Returns `{ loading, totalItems, error }`.
 *
 * @param {Object} root0                     The hook options object.
 * @param {string} [root0.source]            Query source ('posts', 'relationship', …).
 * @param {string} [root0.relationshipField] Meta/ACF key when source is 'relationship'.
 * @param {number} [root0.perPage]           Maximum number of items to fetch.
 * @param {Object} [root0.attributes]        Full query block attributes (non-relationship path).
 * @param {string} [root0.queryId]           The unique query ID (non-relationship path).
 */
/**
 * Normalize an ACF/postmeta relationship field value to an array of post IDs.
 *
 * Mirrors PHP's `designsetgo_query_relationship_normalize_ids()` in
 * `render-relationship.php`. Handles every storage shape the WP REST API
 * can surface for meta fields:
 *  - Array of ints:          [12, 34]
 *  - Array of post objects:  [{ id: 12 }, { ID: 34 }]
 *  - Comma-separated string: "12, 34, 56"
 *  - Single numeric string:  "12"
 *  - Plain number:           12
 *
 * PHP's `maybe_unserialize()` branch is intentionally omitted — the WP
 * REST API always pre-deserializes meta values before exposing them.
 *
 * @param {*} value Raw field value from the REST response.
 * @return {number[]} Filtered, non-zero integer post IDs.
 */
export function normalizeRelationshipIds(value) {
	if (Array.isArray(value)) {
		return value
			.map((v) =>
				typeof v === 'object' && v !== null
					? Number(v.id ?? v.ID)
					: Number(v)
			)
			.filter(Boolean);
	}
	if (typeof value === 'string' && value !== '') {
		if (value.includes(',')) {
			return value
				.split(',')
				.map((s) => Number(s.trim()))
				.filter(Boolean);
		}
		const n = Number(value);
		return Number.isFinite(n) && n > 0 ? [n] : [];
	}
	if (typeof value === 'number' && value > 0) {
		return [Math.floor(value)];
	}
	return [];
}

export default function useQueryPreview({
	source,
	relationshipField = '',
	perPage = 6,
	attributes,
	queryId,
}) {
	const isRelationship = source === 'relationship';

	// ── Relationship: read parent post's field value ──────────────────────────
	// These selectors run unconditionally (rules of hooks). They return null
	// when not applicable (no editor context, or wrong source).

	const parentId = useSelect(
		(s) => {
			if (!isRelationship) {
				return null;
			}
			return s('core/editor')?.getCurrentPostId?.() ?? null;
		},
		[isRelationship]
	);

	const parentPostType = useSelect(
		(s) => {
			if (!isRelationship) {
				return null;
			}
			return s('core/editor')?.getCurrentPostType?.() ?? null;
		},
		[isRelationship]
	);

	const fieldValue = useSelect(
		(s) => {
			if (
				!isRelationship ||
				!parentId ||
				!relationshipField ||
				!parentPostType
			) {
				return null;
			}
			const record = s('core').getEntityRecord(
				'postType',
				parentPostType,
				parentId
			);
			return record?.meta?.[relationshipField] ?? null;
		},
		[isRelationship, parentId, parentPostType, relationshipField]
	);

	// Normalize the raw field value into an array of integer post IDs.
	// Mirrors PHP's designsetgo_query_relationship_normalize_ids() in
	// render-relationship.php, covering all storage shapes ACF/postmeta can
	// produce. PHP's maybe_unserialize() branch is omitted — the WP REST
	// API always pre-deserializes meta values before exposing them.
	const ids = isRelationship
		? normalizeRelationshipIds(fieldValue)
		: [];

	// useEntityRecords must always be called (rules of hooks). When not in
	// relationship mode, or when ids is empty, we pass a sentinel [0] so
	// the call is valid but returns nothing useful.
	//
	// TODO(v2.4): Add a `relationshipPostType` block attribute so CPT
	// relationship fields (e.g. product, event) can be fetched correctly.
	// For now we default to 'post', which covers the common case. Passing
	// 'any' is invalid — there is no /wp/v2/any REST endpoint.
	const entityResult = useEntityRecords('postType', 'post', {
		include: ids.length ? ids : [0],
		per_page: Math.max(1, perPage),
		orderby: 'include',
	});

	// ── Non-relationship: REST-based totalItems badge ─────────────────────────
	const [restState, setRestState] = useState({
		loading: false,
		totalItems: null,
		error: null,
	});

	const payloadKey = JSON.stringify(attributes);

	useEffect(() => {
		if (isRelationship || !queryId) {
			return undefined;
		}
		let cancelled = false;
		setRestState((s) => ({ ...s, loading: true }));

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
				setRestState({
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
				setRestState({ loading: false, totalItems: null, error: err });
			});

		return () => {
			cancelled = true;
		};
		// payloadKey makes the effect rerun on attribute changes (cheaper than deep compare).
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [payloadKey, queryId, isRelationship]);

	// ── Return the appropriate shape for the active source ────────────────────

	if (isRelationship) {
		return {
			records: ids.length ? entityResult.records : [],
			hasResolved: entityResult.hasResolved,
		};
	}

	return restState;
}
