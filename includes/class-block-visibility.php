<?php
/**
 * Shared rule evaluator for the dsgoVisibility attribute.
 *
 * Pure static methods — no WordPress hooks, no state. Consumers
 * (render helpers, REST endpoints) pass a rules array and a
 * per-item context; the evaluator returns bool.
 *
 * @package DesignSetGo
 * @since   2.3.0
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates `dsgoVisibility` rules against per-item context during query renders.
 *
 * Pure static helpers plus a single `render_block` filter registered via
 * `register()` that gates nested inner blocks inside a query item template.
 */
class BlockVisibility {

	/**
	 * Register the render_block filter once at plugin bootstrap.
	 *
	 * Idempotent — safe to call multiple times; only hooks once.
	 * This gates nested block rendering (inside core/group, core/columns, etc.)
	 * by reading the top of $GLOBALS['designsetgo_parent_stack'] set by
	 * designsetgo_query_render_item().
	 */
	public static function register() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;
		add_filter( 'render_block', array( __CLASS__, 'filter_render_block' ), 10, 2 );
	}

	/**
	 * Filter callback: suppress nested blocks whose dsgoVisibility rules do not
	 * match the current item context.
	 *
	 * Only active when $GLOBALS['designsetgo_parent_stack'] is non-empty, meaning
	 * we are inside a designsetgo_query_render_item() call. Top-level template
	 * blocks are already gated in the render_item loop (optimisation: they skip
	 * WP_Block instantiation entirely); this filter catches blocks at deeper
	 * nesting levels that are rendered recursively by WP_Block::render().
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $parsed_block  Parsed block array (blockName + attrs).
	 * @return string Empty string when rules do not match; original content otherwise.
	 */
	public static function filter_render_block( $block_content, $parsed_block ) {
		if ( empty( $GLOBALS['designsetgo_parent_stack'] ) ) {
			return $block_content;
		}
		$visibility = $parsed_block['attrs']['dsgoVisibility'] ?? null;
		if ( null === $visibility ) {
			return $block_content;
		}
		$ctx = end( $GLOBALS['designsetgo_parent_stack'] );
		return self::matches( $visibility, is_array( $ctx ) ? $ctx : array() )
			? $block_content
			: '';
	}

	/**
	 * Evaluate a dsgoVisibility rules object against per-item context.
	 *
	 * Returns true when the block should be rendered; false to suppress it.
	 * An empty or null rules value is treated as "always visible".
	 *
	 * @param array|null $rules   The dsgoVisibility attribute value.
	 * @param array      $context Per-item context: postId, postType, index, etc.
	 * @return bool Whether the block should render.
	 */
	public static function matches( $rules, array $context ) {
		if ( empty( $rules ) || ! is_array( $rules ) || empty( $rules['rules'] ) ) {
			return true;
		}

		$operator = isset( $rules['operator'] ) && 'OR' === strtoupper( (string) $rules['operator'] ) ? 'OR' : 'AND';

		foreach ( (array) $rules['rules'] as $rule ) {
			$matched = self::evaluate_rule( (array) $rule, $context );
			if ( 'OR' === $operator && $matched ) {
				return true;
			}
			if ( 'AND' === $operator && ! $matched ) {
				return false;
			}
		}
		return 'AND' === $operator;
	}

	/**
	 * Dispatch a single rule to its type handler.
	 *
	 * @param array $rule    Rule definition array.
	 * @param array $context Per-item context.
	 * @return bool Whether the rule matches.
	 */
	private static function evaluate_rule( array $rule, array $context ) {
		$type = isset( $rule['type'] ) ? (string) $rule['type'] : '';
		switch ( $type ) {
			case 'meta':
				return self::evaluate_meta( $rule, $context );
			case 'taxonomy':
				return self::evaluate_taxonomy( $rule, $context );
			case 'index':
				return self::evaluate_index( $rule, $context );
			case 'auth':
				return self::evaluate_auth( $rule );
		}

		/**
		 * Filter to add custom rule types.
		 *
		 * @param bool|null $match   Return bool to short-circuit; null to fall through (returns false).
		 * @param array     $rule    The rule definition.
		 * @param array     $context The per-item context.
		 */
		$filtered = apply_filters( 'designsetgo_visibility_rule', null, $rule, $context );
		return (bool) $filtered;
	}

	/**
	 * Evaluate a post-meta rule.
	 *
	 * @param array $rule    Rule definition: key, op, value.
	 * @param array $context Per-item context: postId.
	 * @return bool
	 */
	private static function evaluate_meta( array $rule, array $context ) {
		$post_id = isset( $context['postId'] ) ? (int) $context['postId'] : 0;
		$key     = isset( $rule['key'] ) ? sanitize_text_field( (string) $rule['key'] ) : '';
		if ( ! $post_id || '' === $key ) {
			return false;
		}
		$actual = get_post_meta( $post_id, $key, true );
		return self::compare( $actual, $rule['op'] ?? 'equals', $rule['value'] ?? '' );
	}

	/**
	 * Evaluate a taxonomy membership rule.
	 *
	 * @param array $rule    Rule definition: taxonomy, op (has|not_has), value (slug).
	 * @param array $context Per-item context: postId.
	 * @return bool
	 */
	private static function evaluate_taxonomy( array $rule, array $context ) {
		$post_id  = isset( $context['postId'] ) ? (int) $context['postId'] : 0;
		$taxonomy = isset( $rule['taxonomy'] ) ? sanitize_key( (string) $rule['taxonomy'] ) : '';
		if ( ! $post_id || '' === $taxonomy ) {
			return false;
		}
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return 'not_has' === ( $rule['op'] ?? 'has' );
		}
		$slugs    = wp_list_pluck( $terms, 'slug' );
		$needle   = sanitize_title( (string) ( $rule['value'] ?? '' ) );
		$contains = in_array( $needle, $slugs, true );
		return 'not_has' === ( $rule['op'] ?? 'has' ) ? ! $contains : $contains;
	}

	/**
	 * Evaluate an item-index rule.
	 *
	 * @param array $rule    Rule definition: op, value.
	 * @param array $context Per-item context: index (0-based).
	 * @return bool
	 */
	private static function evaluate_index( array $rule, array $context ) {
		$actual = isset( $context['index'] ) ? (int) $context['index'] : -1;
		return self::compare( $actual, $rule['op'] ?? 'equals', (int) ( $rule['value'] ?? 0 ) );
	}

	/**
	 * Evaluate an authentication state rule.
	 *
	 * @param array $rule Rule definition: value (bool — true = logged-in required).
	 * @return bool
	 */
	private static function evaluate_auth( array $rule ) {
		$expect = isset( $rule['value'] ) ? (bool) $rule['value'] : true;
		return is_user_logged_in() === $expect;
	}

	/**
	 * Compare an actual value to an expected value using the given operator.
	 *
	 * Supported operators: equals, not_equals, contains, gt, lt, empty, not_empty.
	 *
	 * @param mixed  $actual   The resolved value.
	 * @param string $op       Comparison operator.
	 * @param mixed  $expected The expected value.
	 * @return bool
	 */
	private static function compare( $actual, $op, $expected ) {
		switch ( $op ) {
			case 'not_equals':
				return (string) $actual !== (string) $expected;
			case 'contains':
				return false !== stripos( (string) $actual, (string) $expected );
			case 'gt':
				return (float) $actual > (float) $expected;
			case 'lt':
				return (float) $actual < (float) $expected;
			case 'empty':
				return '' === (string) $actual || null === $actual;
			case 'not_empty':
				return '' !== (string) $actual && null !== $actual;
			case 'equals':
			default:
				return (string) $actual === (string) $expected;
		}
	}
}
