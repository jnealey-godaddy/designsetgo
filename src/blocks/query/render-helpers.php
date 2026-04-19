<?php
/**
 * Dynamic Query Block — shared render helpers.
 *
 * Provides:
 *  - designsetgo_query_render()                Top-level dispatcher (by source).
 *  - designsetgo_query_wrap()                  Emits the <ul>/<ol>/<div> wrapper.
 *  - designsetgo_query_render_item()           Renders a single item by parsing
 *                                              and re-rendering the block's
 *                                              innerBlocks with overridden
 *                                              postId / postType context so core
 *                                              blocks and Block Bindings see the
 *                                              iterated item.
 *  - designsetgo_query_extract_params_from_request()
 *                                              Whitelisted filter/search params
 *                                              pulled from $_GET.
 *  - designsetgo_query_set_last_state() / designsetgo_query_get_last_state()
 *                                              In-memory per-request registry
 *                                              of each Query ID's most recent
 *                                              render state (pages, items, page).
 *                                              Used by pagination + no-results
 *                                              siblings (Tasks 13, 15).
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_query_render' ) ) :

	/**
	 * Render a Dynamic Query block for any source.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $context    Keys: query_id (string), page (int), inner_html (string), params (array).
	 * @return array { html: string, totalPages: int, totalItems: int }
	 */
	function designsetgo_query_render( array $attributes, array $context ) {
		$attributes = designsetgo_query_defaults( $attributes );
		$context    = wp_parse_args( $context, array(
			'query_id'   => '',
			'page'       => 1,
			'inner_html' => '',
			'params'     => array(),
		) );

		switch ( $attributes['source'] ) {
			case 'users':
				require_once __DIR__ . '/render-users.php';
				if ( function_exists( 'designsetgo_query_render_users' ) ) {
					return designsetgo_query_render_users( $attributes, $context );
				}
				break;
			case 'terms':
				require_once __DIR__ . '/render-terms.php';
				if ( function_exists( 'designsetgo_query_render_terms' ) ) {
					return designsetgo_query_render_terms( $attributes, $context );
				}
				break;
			case 'posts':
			case 'manual':
			case 'current':
			default:
				require_once __DIR__ . '/render-posts.php';
				return designsetgo_query_render_posts( $attributes, $context );
		}

		return array( 'html' => '', 'totalPages' => 0, 'totalItems' => 0 );
	}

	/**
	 * Apply attribute defaults. Separate so tests and the REST endpoint can
	 * build identical args without re-listing every key.
	 *
	 * @param array $attributes Raw block attributes.
	 * @return array Merged attributes with defaults applied.
	 */
	function designsetgo_query_defaults( array $attributes ) {
		$defaults = array(
			'queryId'        => '',
			'source'         => 'posts',
			'postType'       => 'post',
			'perPage'        => 6,
			'offset'         => 0,
			'orderBy'        => 'date',
			'orderByMetaKey' => '',
			'order'          => 'DESC',
			'search'         => '',
			'bindSearchTo'   => '',
			'author'         => array(),
			'excludeCurrent' => false,
			'ignoreSticky'   => true,
			'manualIds'      => array(),
			'taxQuery'       => array( 'relation' => 'AND', 'clauses' => array() ),
			'metaQuery'      => array( 'relation' => 'AND', 'clauses' => array() ),
			'tagName'        => 'ul',
			'itemTagName'    => 'li',
		);
		return wp_parse_args( $attributes, $defaults );
	}

	/**
	 * Emit the list wrapper around accumulated items markup.
	 *
	 * Builds element attributes manually (not via get_block_wrapper_attributes)
	 * so the function is callable from unit tests and from the REST endpoint
	 * where no block rendering context is active.
	 *
	 * render.php emits the final output through WordPress's normal block
	 * rendering pipeline, so the <ul>/<ol>/<div> wrapper here is nested
	 * inside the wrapper div added by WordPress core — by design, matching
	 * the pattern used by other dynamic blocks (core/query, etc.).
	 *
	 * @param string $inner_items Accumulated <li>…</li> markup for all items.
	 * @param array  $atts        Attributes (already defaulted).
	 * @param array  $context     Render context.
	 * @return string
	 */
	function designsetgo_query_wrap( $inner_items, array $atts, array $context ) {
		$tag      = in_array( $atts['tagName'], array( 'ul', 'ol', 'div' ), true ) ? $atts['tagName'] : 'ul';
		$query_id = sanitize_key( (string) ( $context['query_id'] ?? '' ) );
		$source   = sanitize_key( (string) $atts['source'] );

		$wp_context = wp_json_encode(
			array(
				'queryId' => $query_id,
				'source'  => $source,
				'page'    => (int) $context['page'],
				'busy'    => false,
			)
		);

		$attrs_string = sprintf(
			'class="%1$s" data-dsgo-query-id="%2$s" data-wp-interactive="%3$s" data-wp-context=\'%4$s\' aria-live="polite"',
			esc_attr( 'dsgo-query dsgo-query--source-' . $source ),
			esc_attr( $query_id ),
			esc_attr( 'designsetgo/query' ),
			// Single-quoted to allow JSON double-quotes without entity encoding.
			// wp_json_encode output is safe: no raw HTML, no unescaped control chars.
			$wp_context // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

		return sprintf(
			'<%1$s %2$s>%3$s</%1$s>',
			$tag,
			$attrs_string, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above per attr.
			$inner_items
		);
	}

	/**
	 * Render a single iterated item by parsing innerBlocks and calling
	 * render_block() with per-item context so child blocks and Block Bindings
	 * resolve against the iterated post/user/term.
	 *
	 * IMPORTANT: uses the core `postId` / `postType` context keys (NOT
	 * designsetgo-prefixed) so core blocks (post-title, post-featured-image,
	 * paragraph-with-binding) and our own Block Bindings sources pick up the
	 * iterated item. Users/Terms sources also set designsetgo/currentItemId
	 * and /currentItemType for scenarios where a block needs to distinguish.
	 *
	 * @param string $inner_html   Serialized innerBlocks HTML from block content.
	 * @param array  $item_context Context keys to override.
	 * @param string $item_tag     li / div / article.
	 * @return string
	 */
	function designsetgo_query_render_item( $inner_html, array $item_context, $item_tag ) {
		$tag = in_array( $item_tag, array( 'li', 'div', 'article' ), true ) ? $item_tag : 'li';

		$html   = '';
		$parsed = parse_blocks( $inner_html );
		foreach ( $parsed as $parsed_block ) {
			if ( empty( $parsed_block['blockName'] ) ) {
				continue;
			}
			$existing_context = isset( $parsed_block['context'] ) ? (array) $parsed_block['context'] : array();
			$html .= render_block(
				array_merge(
					$parsed_block,
					array( 'context' => array_merge( $existing_context, $item_context ) )
				)
			);
		}

		return sprintf( '<%1$s class="dsgo-query__item">%2$s</%1$s>', $tag, $html );
	}

	/**
	 * Whitelisted URL params that influence query/filter output. Limited to
	 * `q`, `sort`, plus any `filter_<taxonomy>` key (Task 14). Extensible via
	 * the `designsetgo_query_url_params` filter.
	 *
	 * @return array
	 */
	function designsetgo_query_extract_params_from_request() {
		$allowed = apply_filters( 'designsetgo_query_url_params', array( 'q', 'sort' ) );
		$params  = array();

		if ( empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $params;
		}

		foreach ( (array) $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			if ( ! in_array( $key, $allowed, true ) && 0 !== strpos( $key, 'filter_' ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$params[ $key ] = array_map( 'sanitize_text_field', wp_unslash( $value ) );
			} else {
				$params[ $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
			}
		}

		return $params;
	}

	/**
	 * Store the last-render state for a Query ID so sibling blocks (pagination,
	 * no-results) rendering later in the page can reference it without
	 * re-running the query.
	 *
	 * Not a persistent cache — lives only for the current request.
	 *
	 * @param string $query_id Unique query identifier.
	 * @param array  $state    State data: totalItems, totalPages, page.
	 */
	function designsetgo_query_set_last_state( $query_id, array $state ) {
		if ( ! isset( $GLOBALS['designsetgo_query_states'] ) || ! is_array( $GLOBALS['designsetgo_query_states'] ) ) {
			$GLOBALS['designsetgo_query_states'] = array();
		}
		$GLOBALS['designsetgo_query_states'][ (string) $query_id ] = $state;
	}

	/**
	 * Retrieve the last-render state for a Query ID.
	 *
	 * @param string $query_id Unique query identifier.
	 * @return array|null State array or null if not yet rendered.
	 */
	function designsetgo_query_get_last_state( $query_id ) {
		$states = isset( $GLOBALS['designsetgo_query_states'] ) ? (array) $GLOBALS['designsetgo_query_states'] : array();
		return $states[ (string) $query_id ] ?? null;
	}

endif;
