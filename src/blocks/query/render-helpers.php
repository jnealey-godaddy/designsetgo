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
	 * @param array $context    Keys: query_id (string), page (int), inner_html (string), params (array),
	 *                          wrapper_attrs (string|null) — pre-computed get_block_wrapper_attributes() string
	 *                          from render.php; null for REST/tests.
	 * @return array { html: string, totalPages: int, totalItems: int }
	 */
	function designsetgo_query_render( array $attributes, array $context ) {
		$attributes = designsetgo_query_defaults( $attributes );
		$context    = wp_parse_args(
			$context,
			array(
				'query_id'      => '',
				'page'          => 1,
				'inner_html'    => '',
				'params'        => array(),
				'wrapper_attrs' => null,  // first-paint passes string; REST/tests pass null.
			)
		);

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
			case 'relationship':
				require_once __DIR__ . '/render-relationship.php';
				if ( function_exists( 'designsetgo_query_render_relationship' ) ) {
					return designsetgo_query_render_relationship( $attributes, $context );
				}
				break;
			case 'posts':
			case 'manual':
			case 'current':
			default:
				require_once __DIR__ . '/render-posts.php';
				return designsetgo_query_render_posts( $attributes, $context );
		}

		return array(
			'html'       => '',
			'totalPages' => 0,
			'totalItems' => 0,
		);
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
			'taxQuery'       => array(
				'relation' => 'AND',
				'clauses'  => array(),
			),
			'metaQuery'      => array(
				'relation' => 'AND',
				'clauses'  => array(),
			),
			'tagName'              => 'ul',
			'itemTagName'          => 'li',
			'emitSchema'           => true,
			'relationshipField'    => '',
			'relationshipFallback' => 'empty', // empty | all | parent
			'groupBy'              => null,
		);
		return wp_parse_args( $attributes, $defaults );
	}

	/**
	 * Partition an array of post IDs into labelled groups for grouped rendering.
	 *
	 * Supported fields: 'taxonomy' (by term slug), 'meta' (by meta value),
	 * 'date' (by Y, Y-m, or Y-m-d depending on $group_spec['key']).
	 *
	 * Posts with multiple terms (taxonomy field) appear in ALL matching groups.
	 * Posts with no matching term land in the '__none__' / Uncategorized bucket.
	 *
	 * @param int[]  $post_ids   Ordered list of post IDs to partition.
	 * @param array  $group_spec { field: string, key: string }
	 * @return array[] Array of groups: each { label: string, value: string, ids: int[] }
	 */
	function designsetgo_query_partition_items( array $post_ids, array $group_spec ) {
		if ( empty( $group_spec['field'] ) || empty( $group_spec['key'] ) ) {
			return array( array( 'label' => '', 'value' => '', 'ids' => $post_ids ) );
		}
		$field = (string) $group_spec['field'];
		$key   = (string) $group_spec['key'];

		$groups = array();
		foreach ( $post_ids as $pid ) {
			$values = array();
			$labels = array();
			if ( 'taxonomy' === $field ) {
				$terms = get_the_terms( $pid, $key );
				if ( empty( $terms ) || is_wp_error( $terms ) ) {
					$values = array( '__none__' );
					$labels = array( __( 'Uncategorized', 'designsetgo' ) );
				} else {
					$values = wp_list_pluck( $terms, 'slug' );
					$labels = wp_list_pluck( $terms, 'name' );
				}
			} elseif ( 'meta' === $field ) {
				$v      = (string) get_post_meta( $pid, $key, true );
				$values = array( $v );
				$labels = array( $v );
			} elseif ( 'date' === $field ) {
				$d      = get_post_field( 'post_date', $pid );
				$ts     = $d ? strtotime( $d ) : 0;
				$format = 'Y-M-D' === $key ? 'Y-m-d' : ( 'Y-M' === $key ? 'Y-m' : 'Y' );
				$values = array( gmdate( $format, $ts ) );
				$labels = $values;
			} else {
				$values = array( '' );
				$labels = array( '' );
			}
			foreach ( $values as $i => $v ) {
				if ( ! isset( $groups[ $v ] ) ) {
					$groups[ $v ] = array( 'label' => $labels[ $i ] ?? $v, 'value' => $v, 'ids' => array() );
				}
				$groups[ $v ]['ids'][] = $pid;
			}
		}
		return array_values( $groups );
	}

	/**
	 * Emit the list wrapper around accumulated items markup.
	 *
	 * @param string      $inner_items     Accumulated <li>…</li> markup.
	 * @param array       $atts            Attributes (already defaulted).
	 * @param array       $context         Render context.
	 * @param string|null $wrapper_attrs   Optional pre-computed wrapper attrs string
	 *                                     from get_block_wrapper_attributes(). When
	 *                                     provided (first-paint), it carries all
	 *                                     native-supports classes and user inline
	 *                                     styles. When null (REST / tests), fall
	 *                                     back to the minimal attrs we build here.
	 * @return string
	 */
	function designsetgo_query_wrap( $inner_items, array $atts, array $context, $wrapper_attrs = null ) {
		$tag      = in_array( $atts['tagName'], array( 'ul', 'ol', 'div' ), true ) ? $atts['tagName'] : 'ul';
		$query_id = sanitize_key( (string) ( $context['query_id'] ?? '' ) );
		$source   = sanitize_key( (string) $atts['source'] );

		// Column CSS variables drive the responsive grid layout in
		// query-results/style.scss.
		$columns         = isset( $atts['columns'] ) ? max( 1, (int) $atts['columns'] ) : 1;
		$columns_tablet  = isset( $atts['columnsTablet'] ) ? (int) $atts['columnsTablet'] : 0;
		$columns_mobile  = isset( $atts['columnsMobile'] ) ? (int) $atts['columnsMobile'] : 0;
		$column_gap      = isset( $atts['columnGap'] ) ? sanitize_text_field( (string) $atts['columnGap'] ) : '';
		$grid_style      = sprintf( '--dsgo-query-columns:%d;', $columns );
		if ( $columns_tablet > 0 ) {
			$grid_style .= sprintf( '--dsgo-query-columns-tablet:%d;', $columns_tablet );
		}
		if ( $columns_mobile > 0 ) {
			$grid_style .= sprintf( '--dsgo-query-columns-mobile:%d;', $columns_mobile );
		}
		if ( '' !== $column_gap ) {
			// Whitelist a safe subset of CSS length values to defeat `;` / `}` injection.
			if ( preg_match( '/^[0-9]+(?:\.[0-9]+)?(?:px|rem|em|%|vw|vh|ch|ex|pt)$/', $column_gap ) ) {
				$grid_style .= '--dsgo-query-gap:' . $column_gap . ';';
			}
		}

		// Post-restructure: this element IS the query-results grid. IAPI /
		// context / blobs live on the outer dsgo-query-region container
		// emitted by designsetgo_query_render_container(). The first-paint
		// wrapper_attrs param is no longer used here — the grid wrapper is
		// always constructed by this helper since it belongs to query-results,
		// not to the outer query block.
		unset( $wrapper_attrs );
		$attrs_string = sprintf(
			'class="%1$s" style="%3$s" data-dsgo-query-results-role="container" data-dsgo-query-id="%2$s"',
			esc_attr( 'dsgo-query-results dsgo-query-results--source-' . $source ),
			esc_attr( $query_id ),
			esc_attr( $grid_style )
		);

		// Blobs + IAPI attrs are now emitted by designsetgo_query_render_container()
		// on the outer .dsgo-query-region wrapper, so this helper just produces
		// the grid element wrapping the pre-rendered items.
		return sprintf(
			'<%1$s %2$s>%3$s</%1$s>',
			$tag,
			$attrs_string, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_attr()-escaped parts.
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
		// Ensure BlockVisibility is available when this file is required directly
		// (e.g. in integration tests) before the plugin bootstrap has run.
		if ( ! class_exists( '\\DesignSetGo\\BlockVisibility' ) ) {
			require_once DESIGNSETGO_PATH . 'includes/class-block-visibility.php';
		}

		$tag = in_array( $item_tag, array( 'li', 'div', 'article' ), true ) ? $item_tag : 'li';

		$html   = '';
		$parsed = parse_blocks( $inner_html );

		// Push this item's context onto the global parent stack so that nested
		// Query blocks (and Task C2's `scope` arg on bindings) can walk the
		// ancestor chain. The stack is keyed by depth, so parallel items from
		// different queries never collide — each item fully completes before the
		// next begins (PHP is single-threaded, no async interleaving).
		if ( ! isset( $GLOBALS['designsetgo_parent_stack'] ) || ! is_array( $GLOBALS['designsetgo_parent_stack'] ) ) {
			$GLOBALS['designsetgo_parent_stack'] = array();
		}
		array_push( $GLOBALS['designsetgo_parent_stack'], $item_context );

		try {
			foreach ( $parsed as $parsed_block ) {
				if ( empty( $parsed_block['blockName'] ) ) {
					continue;
				}
				// Skip blocks whose dsgoVisibility rules don't match the current item context.
				$visibility = isset( $parsed_block['attrs']['dsgoVisibility'] ) ? $parsed_block['attrs']['dsgoVisibility'] : null;
				if ( ! \DesignSetGo\BlockVisibility::matches( $visibility, $item_context ) ) {
					continue;
				}
				// WP_Block's constructor signature is ( $block, $available_context, $registry ).
				// The $available_context arg is what gets filtered through child blocks'
				// usesContext declarations. Passing it via render_block()'s parsed-block
				// 'context' key would NOT work — that key is not read by WP_Block.
				$block_instance = new WP_Block( $parsed_block, $item_context );
				$html          .= $block_instance->render();
			}
		} finally {
			// Always pop — even if render() throws — so the stack stays consistent
			// for any outer Query block that is still iterating.
			array_pop( $GLOBALS['designsetgo_parent_stack'] );
			if ( empty( $GLOBALS['designsetgo_parent_stack'] ) ) {
				unset( $GLOBALS['designsetgo_parent_stack'] );
			}
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

	/**
	 * Render a full query region — list items + sibling blocks — wrapped in
	 * a <div class="dsgo-query-region"> container used as the JS refresh target.
	 *
	 * This is the preferred entry point for both render.php (first-paint) and
	 * the REST controller (filter/sort refresh). Using a single shared helper
	 * ensures both paths produce byte-identical output so the JS can safely
	 * swap the region's innerHTML.
	 *
	 * Sibling block names (pagination, filter, no-results) are NOT rendered
	 * per-item; they are rendered once, AFTER the query runs (so the state
	 * registry is populated and sibling blocks can read totalPages etc.).
	 *
	 * @param array $attributes Block attributes (raw — will be defaulted internally).
	 * @param array $context    Keys:
	 *                          - query_id   (string)       Unique query identifier.
	 *                          - page       (int)          Current page number.
	 *                          - inner_html (string)       Full serialized innerBlocks
	 *                                                      (template blocks + sibling
	 *                                                      block comment strings). The
	 *                                                      helper splits them here.
	 *                          - params     (array)        URL filter params.
	 *                          - wrapper_attrs (string|null) Pre-computed
	 *                                                      get_block_wrapper_attributes()
	 *                                                      string (first-paint only;
	 *                                                      null for REST/tests).
	 * @return array { html: string, totalPages: int, totalItems: int }
	 */
	function designsetgo_query_render_region( array $attributes, array $context ) {
		$context = wp_parse_args(
			$context,
			array(
				'query_id'      => '',
				'page'          => 1,
				'inner_html'    => '',
				'params'        => array(),
				'wrapper_attrs' => null,
			)
		);

		$query_id        = sanitize_key( (string) $context['query_id'] );
		$full_inner_html = (string) $context['inner_html'];
		$parsed_children = function_exists( 'parse_blocks' ) ? parse_blocks( $full_inner_html ) : array();

		// Delegate to the shared container renderer. The REST path has no outer
		// block wrapper (no $block instance), so we emit a simple class-only
		// wrapper here; designsetgo_query_render_container() appends IAPI attrs.
		$html = designsetgo_query_render_container(
			$attributes,
			$parsed_children,
			(int) $context['page'],
			$query_id,
			'class="dsgo-query dsgo-query-region dsgo-query--source-' . sanitize_key( (string) ( $attributes['source'] ?? 'posts' ) ) . '"',
			array()
		);

		// totalPages/totalItems come from the state registry populated during
		// the render above.
		$state = designsetgo_query_get_last_state( $query_id );
		return array(
			'html'       => $html,
			'totalPages' => isset( $state['totalPages'] ) ? (int) $state['totalPages'] : 0,
			'totalItems' => isset( $state['totalItems'] ) ? (int) $state['totalItems'] : 0,
		);
	}

endif;

if ( ! function_exists( 'designsetgo_query_render_container' ) ) :

	/**
	 * Render the outer Dynamic Query container for first-paint.
	 *
	 * Post-restructure (v2.6): the outer designsetgo/query block is a pure
	 * container — it doesn't emit a list wrapper or run items inline. This
	 * helper walks its parsed innerBlocks, runs a single WP_Query when a
	 * designsetgo/query-results child is present, stashes the items HTML
	 * for the child's render.php to pick up, and then manually renders each
	 * child in tree order so filters/pagination/no-results appear exactly
	 * where the author placed them.
	 *
	 * @param array  $attributes     Raw block attributes.
	 * @param array  $parsed_children parse_blocks() entries of the block's innerBlocks.
	 * @param int    $page           Current pagination page.
	 * @param string $query_id       Sanitized queryId.
	 * @param string $wrapper_attrs  Pre-computed get_block_wrapper_attributes()
	 *                               string for the outer element. IAPI attrs
	 *                               (data-wp-interactive, data-wp-context,
	 *                               data-dsgo-query-id) are appended here.
	 * @param array  $base_context   WP_Block context inherited from the outer
	 *                               render path (passed to each child render).
	 * @return string Full HTML for the outer element, including children.
	 */
	function designsetgo_query_render_container( array $attributes, array $parsed_children, $page, $query_id, $wrapper_attrs, array $base_context = array() ) {
		$attributes = designsetgo_query_defaults( $attributes );
		$source     = sanitize_key( (string) ( $attributes['source'] ?? 'posts' ) );

		// Find the designsetgo/query-results child (only one allowed). Its attrs
		// govern presentation (columns, tagName, groupBy...) and its innerBlocks
		// are the per-item template.
		$results_child = null;
		foreach ( $parsed_children as $child ) {
			if ( ( $child['blockName'] ?? '' ) === 'designsetgo/query-results' ) {
				$results_child = $child;
				break;
			}
		}

		$total_items = 0;

		// Two shapes are accepted:
		//  1. First-paint path — children contain a <query-results> wrapper.
		//     Use its attrs for presentation + its innerBlocks for the template.
		//  2. Editor-preview / legacy path — children ARE the item template
		//     directly (no query-results wrapper). Use parent attrs for
		//     presentation and treat all children as template blocks.
		$effective_attrs = $attributes;
		$template_blocks = array();

		if ( $results_child ) {
			$results_attrs   = is_array( $results_child['attrs'] ?? null ) ? $results_child['attrs'] : array();
			$effective_attrs = array_merge(
				$attributes,
				array(
					'tagName'       => $results_attrs['tagName']       ?? ( $attributes['tagName']       ?? 'ul' ),
					'itemTagName'   => $results_attrs['itemTagName']   ?? ( $attributes['itemTagName']   ?? 'li' ),
					'columns'       => $results_attrs['columns']       ?? ( $attributes['columns']       ?? 1 ),
					'columnsTablet' => $results_attrs['columnsTablet'] ?? ( $attributes['columnsTablet'] ?? 0 ),
					'columnsMobile' => $results_attrs['columnsMobile'] ?? ( $attributes['columnsMobile'] ?? 0 ),
					'columnGap'     => $results_attrs['columnGap']     ?? ( $attributes['columnGap']     ?? '' ),
					'groupBy'       => $results_attrs['groupBy']       ?? ( $attributes['groupBy']       ?? null ),
				)
			);
			$template_blocks = (array) ( $results_child['innerBlocks'] ?? array() );
		} else {
			// No query-results wrapper — fall through with parent-only attrs
			// and treat every non-empty child as a template block.
			foreach ( $parsed_children as $pc ) {
				if ( ! empty( $pc['blockName'] ) ) {
					$template_blocks[] = $pc;
				}
			}
		}

		if ( '' !== $query_id ) {
			$template_html = '';
			foreach ( $template_blocks as $tb ) {
				if ( function_exists( 'serialize_block' ) ) {
					$template_html .= serialize_block( $tb );
				}
			}

			$render_context = array(
				'query_id'        => $query_id,
				'page'            => (int) $page,
				'inner_html'      => $template_html,
				'full_inner_html' => $template_html,
				'params'          => designsetgo_query_extract_params_from_request(),
				'wrapper_attrs'   => null,
			);

			$result      = designsetgo_query_render( $effective_attrs, $render_context );
			$total_items = (int) ( $result['totalItems'] ?? 0 );

			// Stash the rendered items HTML so query-results/render.php can
			// echo it when WordPress walks the tree below. Keyed by queryId so
			// nested queries can't clash.
			if ( ! isset( $GLOBALS['designsetgo_query_results_html'] ) || ! is_array( $GLOBALS['designsetgo_query_results_html'] ) ) {
				$GLOBALS['designsetgo_query_results_html'] = array();
			}
			$GLOBALS['designsetgo_query_results_html'][ $query_id ] = (string) $result['html'];

			// Legacy path (no query-results wrapper): since no child block will
			// emit the items, emit them directly here so the editor preview
			// REST call still returns a usable region.
			if ( ! $results_child ) {
				$children_html_direct = (string) $result['html'];
				$blobs                = designsetgo_query_render_blobs( $query_id, $attributes, $parsed_children );
				$status               = sprintf(
					'<div role="status" aria-live="polite" aria-atomic="true" class="screen-reader-text dsgo-query__status" data-dsgo-query-status="%1$s" data-dsgo-total-items="%2$d"></div>',
					esc_attr( $query_id ),
					(int) $total_items
				);
				$wp_context_legacy = wp_json_encode(
					array(
						'queryId' => $query_id,
						'source'  => $source,
						'page'    => (int) $page,
						'busy'    => false,
						'restUrl' => esc_url_raw( rest_url( 'designsetgo/v1/query/render' ) ),
						'nonce'   => wp_create_nonce( 'wp_rest' ),
					),
					JSON_HEX_APOS
				);
				$iapi_attrs_legacy = sprintf(
					'data-dsgo-query-id="%1$s" data-dsgo-query-region="%1$s" data-wp-interactive="%2$s" data-wp-context=\'%3$s\' aria-live="polite"',
					esc_attr( $query_id ),
					esc_attr( 'designsetgo/query' ),
					$wp_context_legacy // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_APOS output is safe in single-quoted attr.
				);
				$merged_legacy = trim( (string) $wrapper_attrs . ' ' . $iapi_attrs_legacy );
				return sprintf(
					'<div %1$s>%2$s%3$s%4$s</div>',
					$merged_legacy,         // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper + esc_attr IAPI attrs.
					$blobs,                 // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- designsetgo_query_render_blobs escapes internally.
					$status,                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr-assembled.
					$children_html_direct   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- designsetgo_query_wrap + render_item output.
				);
			}
		}

		// Shared context passed to every child block. Must include everything the
		// outer block provides via `providesContext` in block.json.
		$shared_context = array_merge(
			$base_context,
			array(
				'designsetgo/queryId'       => $query_id,
				'designsetgo/querySource'   => $source,
				'designsetgo/queryPostType' => (string) ( $attributes['postType'] ?? 'post' ),
			)
		);

		// Render each child in tree order. The query-results child's render.php
		// reads $GLOBALS['designsetgo_query_results_html'][queryId] and echoes
		// the pre-rendered items HTML.
		$children_html = '';
		foreach ( $parsed_children as $child ) {
			if ( empty( $child['blockName'] ) ) {
				continue;
			}
			if ( class_exists( 'WP_Block' ) ) {
				$children_html .= ( new WP_Block( $child, $shared_context ) )->render();
			}
		}

		// Clean up the stash so it doesn't leak to unrelated queries later in
		// the request (e.g. multiple Dynamic Query blocks on one page).
		if ( '' !== $query_id ) {
			unset( $GLOBALS['designsetgo_query_results_html'][ $query_id ] );
		}

		// IAPI + state-announcement markup. Appended to the block wrapper so
		// view.js has a single refresh target.
		$wp_context = wp_json_encode(
			array(
				'queryId' => $query_id,
				'source'  => $source,
				'page'    => (int) $page,
				'busy'    => false,
				'restUrl' => esc_url_raw( rest_url( 'designsetgo/v1/query/render' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			),
			JSON_HEX_APOS
		);

		$iapi_attrs = sprintf(
			'data-dsgo-query-id="%1$s" data-dsgo-query-region="%1$s" data-wp-interactive="%2$s" data-wp-context=\'%3$s\' aria-live="polite"',
			esc_attr( $query_id ),
			esc_attr( 'designsetgo/query' ),
			$wp_context // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_APOS output is safe in single-quoted attr.
		);

		$merged_wrapper = trim( (string) $wrapper_attrs . ' ' . $iapi_attrs );

		// Blobs + status for IAPI refresh. Blobs carry the attrs + serialized
		// innerBlocks so the REST refresh can rebuild the same region.
		$blobs  = designsetgo_query_render_blobs( $query_id, $attributes, $parsed_children );
		$status = sprintf(
			'<div role="status" aria-live="polite" aria-atomic="true" class="screen-reader-text dsgo-query__status" data-dsgo-query-status="%1$s" data-dsgo-total-items="%2$d"></div>',
			esc_attr( $query_id ),
			(int) $total_items
		);

		return sprintf(
			'<div %1$s>%2$s%3$s%4$s</div>',
			$merged_wrapper,  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper from get_block_wrapper_attributes + esc_attr-assembled IAPI attrs.
			$blobs,           // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- designsetgo_query_render_blobs escapes internally.
			$status,          // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr-assembled; empty text content.
			$children_html    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_Block->render() output (WP escapes/KSES server-side).
		);
	}

endif;

if ( ! function_exists( 'designsetgo_query_render_blobs' ) ) :

	/**
	 * Build the hidden blobs div (attrs + serialized innerBlocks) embedded
	 * alongside the query region. Referenced by view.js during filter/sort
	 * refresh so the REST payload can be reconstructed without a round-trip.
	 *
	 * @param string $query_id       Sanitized queryId.
	 * @param array  $attributes     Query block attributes (already defaulted).
	 * @param array  $parsed_children parse_blocks() entries.
	 * @return string HTML for the blobs div, or empty string when queryId is empty.
	 */
	function designsetgo_query_render_blobs( $query_id, array $attributes, array $parsed_children ) {
		if ( '' === $query_id ) {
			return '';
		}
		$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

		$full_inner_html = '';
		foreach ( $parsed_children as $child ) {
			if ( ! empty( $child['blockName'] ) && function_exists( 'serialize_block' ) ) {
				$full_inner_html .= serialize_block( $child );
			}
		}

		return '<div hidden class="dsgo-query__blobs" data-dsgo-blobs-for="' . esc_attr( $query_id ) . '">'
			. '<script type="application/json" data-dsgo-attrs>'
			. wp_json_encode( $attributes, $flags )
			. '</script>'
			. '<script type="application/json" data-dsgo-inner>'
			. wp_json_encode( $full_inner_html, $flags )
			. '</script>'
			. '</div>';
	}

endif;

if ( ! function_exists( 'designsetgo_query_merge_inline_style' ) ) :

	/**
	 * Merges extra inline CSS declarations into an existing pre-serialized
	 * HTML attribute string. If a `style="..."` attribute already exists
	 * (as emitted by `get_block_wrapper_attributes()` when the user has
	 * applied padding/margin/etc), the new declarations are appended inside
	 * it; otherwise a new `style="..."` attribute is added to the string.
	 *
	 * Values passed in `$extra_style` must already be safe for an HTML
	 * attribute context — this helper only concatenates, it does not
	 * sanitize.
	 *
	 * @param string $wrapper_attrs Existing attributes string from
	 *                              `get_block_wrapper_attributes()`.
	 * @param string $extra_style   Raw CSS declarations to append, terminated
	 *                              with a trailing `;`.
	 * @return string The merged attributes string.
	 */
	function designsetgo_query_merge_inline_style( $wrapper_attrs, $extra_style ) {
		$extra_style = trim( (string) $extra_style );
		if ( '' === $extra_style ) {
			return $wrapper_attrs;
		}

		if ( preg_match( '/\bstyle\s*=\s*"([^"]*)"/i', $wrapper_attrs, $m ) ) {
			$existing = rtrim( $m[1], '; ' );
			$merged   = ( '' === $existing ? '' : $existing . ';' ) . $extra_style;
			return str_replace( $m[0], 'style="' . esc_attr( $merged ) . '"', $wrapper_attrs );
		}

		return $wrapper_attrs . ' style="' . esc_attr( $extra_style ) . '"';
	}

endif;
