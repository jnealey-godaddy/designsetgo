<?php
/**
 * Dynamic Query — Relationship source renderer.
 *
 * Reads an ID-bearing field from the nearest parent Query item's
 * post and iterates the referenced posts in their declared order.
 *
 * Supported field storage shapes:
 *  - array of ints or WP_Post objects (ACF relationship)
 *  - comma-separated string of ints ("12, 34, 56")
 *  - serialized array (legacy ACF)
 *  - single int
 *
 * Requires a parent_stack entry; returns empty when the Query
 * block is used outside another Query's item template.
 *
 * @package DesignSetGo
 * @since   2.3.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_query_render_relationship' ) ) :

	function designsetgo_query_render_relationship( array $atts, array $context ) {
		$field = isset( $atts['relationshipField'] ) ? sanitize_text_field( (string) $atts['relationshipField'] ) : '';
		if ( '' === $field ) {
			return array(
				'html'       => '',
				'items_html' => '',
				'totalPages' => 0,
				'totalItems' => 0,
			);
		}

		$parent_stack = array();
		if ( isset( $context['parent_stack'] ) && is_array( $context['parent_stack'] ) && ! empty( $context['parent_stack'] ) ) {
			$parent_stack = $context['parent_stack'];
		} elseif ( isset( $GLOBALS['designsetgo_parent_stack'] ) && is_array( $GLOBALS['designsetgo_parent_stack'] ) ) {
			$parent_stack = $GLOBALS['designsetgo_parent_stack'];
		}
		$parent = empty( $parent_stack ) ? null : end( $parent_stack );

		$parent_post_id = 0;
		if ( is_array( $parent ) && ! empty( $parent['postId'] ) ) {
			$parent_post_id = (int) $parent['postId'];
		}

		if ( ! $parent_post_id ) {
			return designsetgo_query_relationship_fallback( $atts, $context );
		}

		$raw_value = false;
		if ( function_exists( 'get_field' ) ) {
			$raw_value = get_field( $field, $parent_post_id );
		}
		if ( false === $raw_value || null === $raw_value ) {
			$raw_value = get_post_meta( $parent_post_id, $field, true );
		}

		$ids = designsetgo_query_relationship_normalize_ids( $raw_value );

		if ( empty( $ids ) ) {
			return designsetgo_query_relationship_fallback( $atts, $context );
		}

		// Delegate to the Posts renderer — override attributes so WP_Query
		// runs with post__in + orderby=post__in + post_type=any (relationship
		// fields are frequently cross-type).
		require_once __DIR__ . '/render-posts.php';

		$atts_override = array_merge(
			$atts,
			array(
				'source'          => 'manual',
				'manualIds'       => $ids,
				'postType'        => 'any',
				'perPage'         => max( 1, min( count( $ids ), (int) $atts['perPage'] ) ),
				'manualPaginated' => true,
			)
		);

		return designsetgo_query_render_posts( $atts_override, $context );
	}

	/**
	 * Normalize ACF/meta field values to a list of post IDs.
	 */
	function designsetgo_query_relationship_normalize_ids( $value ) {
		if ( is_array( $value ) ) {
			$ids = array();
			foreach ( $value as $v ) {
				if ( $v instanceof \WP_Post ) {
					$ids[] = (int) $v->ID;
				} elseif ( is_numeric( $v ) ) {
					$ids[] = (int) $v;
				}
			}
			return array_values( array_filter( $ids ) );
		}
		if ( is_string( $value ) && '' !== $value ) {
			if ( str_contains( $value, ',' ) ) {
				return array_values( array_filter( array_map( 'absint', explode( ',', $value ) ) ) );
			}
			if ( is_numeric( $value ) ) {
				return array( (int) $value );
			}
			$unserialized = maybe_unserialize( $value );
			if ( is_array( $unserialized ) ) {
				return designsetgo_query_relationship_normalize_ids( $unserialized );
			}
		}
		if ( is_numeric( $value ) ) {
			return array( (int) $value );
		}
		return array();
	}

	/**
	 * Empty / all / parent fallback for no-result relationship queries.
	 */
	function designsetgo_query_relationship_fallback( array $atts, array $context ) {
		$mode = isset( $atts['relationshipFallback'] ) ? (string) $atts['relationshipFallback'] : 'empty';

		if ( 'empty' === $mode ) {
			return array(
				'html'       => designsetgo_query_wrap( '', $atts, $context, $context['wrapper_attrs'] ?? null ),
				'items_html' => '',
				'totalPages' => 0,
				'totalItems' => 0,
			);
		}

		require_once __DIR__ . '/render-posts.php';

		if ( 'all' === $mode ) {
			$atts['source'] = 'posts';
			return designsetgo_query_render_posts( $atts, $context );
		}

		// 'parent' fallback: render a single item using the parent post itself.
		$fallback_stack = array();
		if ( isset( $context['parent_stack'] ) && is_array( $context['parent_stack'] ) && ! empty( $context['parent_stack'] ) ) {
			$fallback_stack = $context['parent_stack'];
		} elseif ( isset( $GLOBALS['designsetgo_parent_stack'] ) && is_array( $GLOBALS['designsetgo_parent_stack'] ) ) {
			$fallback_stack = $GLOBALS['designsetgo_parent_stack'];
		}
		$parent = empty( $fallback_stack ) ? null : end( $fallback_stack );
		if ( ! is_array( $parent ) || empty( $parent['postId'] ) ) {
			return array(
				'html'       => '',
				'items_html' => '',
				'totalPages' => 0,
				'totalItems' => 0,
			);
		}
		$atts['source']    = 'manual';
		$atts['manualIds'] = array( (int) $parent['postId'] );
		$atts['perPage']   = 1;
		return designsetgo_query_render_posts( $atts, $context );
	}

endif;
