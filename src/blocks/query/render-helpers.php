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
			'tagName'        => 'ul',
			'itemTagName'    => 'li',
			'emitSchema'     => true,
		);
		return wp_parse_args( $attributes, $defaults );
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

		// restUrl + nonce travel in the IAPI context so view.js works under
		// plain-permalink installs where /wp-json/ rewrites aren't available
		// and wpApiSettings isn't localised on the frontend. JSON_HEX_APOS
		// defends the single-quoted attr boundary against a stray apostrophe
		// in any future context value.
		$wp_context = wp_json_encode(
			array(
				'queryId' => $query_id,
				'source'  => $source,
				'page'    => (int) $context['page'],
				'busy'    => false,
				'restUrl' => esc_url_raw( rest_url( 'designsetgo/v1/query/render' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			),
			JSON_HEX_APOS
		);

		// Column CSS variables drive the responsive grid layout applied in
		// style.scss. `columns` attr may be missing for pre-grid blocks, in
		// which case we default to 1 column and skip the optional vars.
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

		if ( is_string( $wrapper_attrs ) && '' !== $wrapper_attrs ) {
			// First-paint path: get_block_wrapper_attributes() already produced an
			// escaped attrs string that includes wp-block-designsetgo-query,
			// all native-supports classes/styles, anchor id, and user className.
			// Append the IAPI + query-id + aria-live attrs inline, plus the grid
			// CSS vars (merged into an existing style="" attr if present).
			$wrapper_with_style = designsetgo_query_merge_inline_style( $wrapper_attrs, $grid_style );
			$iapi_attrs         = sprintf(
				'data-dsgo-query-id="%1$s" data-dsgo-query-role="container" data-wp-interactive="%2$s" data-wp-context=\'%3$s\' aria-live="polite"',
				esc_attr( $query_id ),
				esc_attr( 'designsetgo/query' ),
				// wp_json_encode output is safe inside a single-quoted HTML attribute.
				$wp_context // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output inside single-quoted attr.
			);
			$attrs_string = $wrapper_with_style . ' ' . $iapi_attrs;
		} else {
			// REST / unit-test path: build minimal attrs manually.
			$attrs_string = sprintf(
				'class="%1$s" style="%5$s" data-dsgo-query-id="%2$s" data-dsgo-query-role="container" data-wp-interactive="%3$s" data-wp-context=\'%4$s\' aria-live="polite"',
				esc_attr( 'dsgo-query dsgo-query--source-' . $source ),
				esc_attr( $query_id ),
				esc_attr( 'designsetgo/query' ),
				$wp_context, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_attr( $grid_style )
			);
		}

		// Emit JSON blobs so view.js (load-more / filter refresh) can re-send the
		// block's attribute state and innerBlocks template without needing a
		// server-side lookup. JSON_HEX_* flags ensure no literal <, >, &, ', "
		// appear in the output, making the strings safe to embed inside a <script>
		// element in any context.
		// The blobs live OUTSIDE the list element (as a preceding sibling hidden div)
		// because <script> elements are not valid children of <ul>/<ol> per the HTML
		// spec, and they would break ul > li:first-child CSS selectors.
		//
		// Fix 1 + Fix 2: store full_inner_html (template + siblings) in the blob so
		// the REST endpoint can re-split siblings correctly during filter refresh and
		// produce a region-level response that includes pagination + no-results HTML.
		$blob_wrapper = '';
		if ( '' !== $query_id ) {
			$flags        = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
			// Prefer full_inner_html (set by designsetgo_query_render_region) so the
			// REST refresh can re-render siblings; fall back to inner_html for backwards
			// compatibility when called directly (e.g. unit tests, load-more endpoint).
			$blob_inner   = isset( $context['full_inner_html'] ) && '' !== (string) $context['full_inner_html']
				? (string) $context['full_inner_html']
				: (string) ( $context['inner_html'] ?? '' );
			$blob_wrapper = '<div hidden class="dsgo-query__blobs" data-dsgo-blobs-for="' . esc_attr( $query_id ) . '">'
				. '<script type="application/json" data-dsgo-attrs>'
				. wp_json_encode( $atts, $flags )
				. '</script>'
				. '<script type="application/json" data-dsgo-inner>'
				. wp_json_encode( $blob_inner, $flags )
				. '</script>'
				. '</div>';
		}

		return sprintf(
			'%1$s<%2$s %3$s>%4$s</%2$s>',
			$blob_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr() + JSON_HEX_* assembled; no literal HTML special chars.
			$tag,
			$attrs_string, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_attr()-escaped parts + get_block_wrapper_attributes() output.
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
			// WP_Block's constructor signature is ( $block, $available_context, $registry ).
			// The $available_context arg is what gets filtered through child blocks'
			// usesContext declarations. Passing it via render_block()'s parsed-block
			// 'context' key would NOT work — that key is not read by WP_Block.
			$block_instance = new WP_Block( $parsed_block, $item_context );
			$html          .= $block_instance->render();
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

		// Block names that are sibling concerns — render ONCE after the query.
		$sibling_names = array(
			'designsetgo/query-pagination',
			'designsetgo/query-filter',
			'designsetgo/query-no-results',
		);

		// Parse the full inner block HTML and split into template vs sibling lists.
		$parsed_blocks   = function_exists( 'parse_blocks' ) ? parse_blocks( $full_inner_html ) : array();
		$template_blocks = array();
		$sibling_blocks  = array();
		foreach ( $parsed_blocks as $parsed ) {
			if ( empty( $parsed['blockName'] ) ) {
				continue;
			}
			if ( in_array( $parsed['blockName'], $sibling_names, true ) ) {
				$sibling_blocks[] = $parsed;
			} else {
				$template_blocks[] = $parsed;
			}
		}

		// Serialize only the template blocks back to a WP comment string for the
		// per-item renderer.
		$template_html = '';
		foreach ( $template_blocks as $tb ) {
			if ( function_exists( 'serialize_block' ) ) {
				$template_html .= serialize_block( $tb );
			}
		}

		// Pass template-only HTML as inner_html to the per-item render, but store
		// the FULL inner HTML in full_inner_html so the blob (written inside
		// designsetgo_query_wrap) carries the complete set — enabling the REST
		// endpoint to re-split siblings on filter refresh.
		$render_context                    = $context;
		$render_context['inner_html']      = $template_html;
		$render_context['full_inner_html'] = $full_inner_html;

		// Run the query. This populates the state registry so siblings can read
		// totalPages / totalItems without re-running the query.
		$result = designsetgo_query_render( $attributes, $render_context );

		// Render each sibling block ONCE, passing queryId + source via context so
		// pagination, filter, and no-results blocks know which query they belong to.
		//
		// Filter-count intersection (B4): filter blocks read active filters from
		// $_GET. On first-paint, $_GET reflects the live request. On AJAX refresh,
		// the REST controller (class-query.php::handle_render) overlays $_GET with
		// the incoming `params` payload before calling this function, so when filter
		// blocks call FilterIndex::count_for_options() their active_filters are always
		// current. No additional context threading is needed.
		$source           = isset( $attributes['source'] ) ? (string) $attributes['source'] : 'posts';
		$sibling_context  = array(
			'designsetgo/queryId'     => $query_id,
			'designsetgo/querySource' => $source,
		);
		$siblings_html = '';
		foreach ( $sibling_blocks as $sb ) {
			if ( class_exists( 'WP_Block' ) ) {
				$siblings_html .= ( new WP_Block( $sb, $sibling_context ) )->render();
			}
		}

		// Terse status region for AT — populated by view.js after every refresh
		// with "N results found". Keeps the chatty list-update announcements from
		// aria-live="polite" on the list from drowning screen-reader users.
		$status_region = sprintf(
			'<div role="status" aria-live="polite" aria-atomic="true" class="screen-reader-text dsgo-query__status" data-dsgo-query-status="%1$s" data-dsgo-total-items="%2$d"></div>',
			esc_attr( $query_id ),
			(int) $result['totalItems']
		);

		// Wrap everything in the region container — this is the JS swap target.
		// The inner list HTML (from $result['html']) already contains the blobs div
		// and the <ul>/<ol>/<div> list element with data-dsgo-query-role="container".
		$region_html = sprintf(
			'<div class="dsgo-query-region" data-dsgo-query-region="%1$s">%2$s%3$s%4$s</div>',
			esc_attr( $query_id ),
			$result['html'],          // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled in designsetgo_query_wrap() from esc_attr()-escaped parts.
			$siblings_html,           // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block render() output is escaped by WordPress.
			$status_region            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr-assembled; empty text content.
		);

		return array(
			'html'       => $region_html,
			'totalPages' => $result['totalPages'],
			'totalItems' => $result['totalItems'],
		);
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
