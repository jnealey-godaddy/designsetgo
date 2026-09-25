<?php
/**
 * Dynamic Query — Filter sibling block.
 *
 * One block, six filterKind variations. Wires to the parent query via
 * queryId (from context) + URL params. All controls live inside a
 * <form method="get"> so no-JS submission falls back cleanly to a
 * server-rendered filter; with JS, the IAPI store intercepts.
 *
 * @package DesignSetGo
 * @since 2.1.0
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Serialized innerBlocks (unused — no inner blocks).
 * @param WP_Block $block      Block instance (provides context).
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Helper renderers (one per filterKind) — defined FIRST so the dispatcher
// below can call them on the first render (conditional function defs only
// become callable after the if-block executes).
// ---------------------------------------------------------------------------

if ( ! function_exists( 'designsetgo_query_filter_collect_term_scope' ) ) :

	/**
	 * Collect the term IDs a query's taxQuery allows (or forbids) for one taxonomy.
	 *
	 * Walks the same nested clause/group shape the server builder understands
	 * (see designsetgo_build_tax_query_entry()), gathering leaf clauses that
	 * name this taxonomy. `IN` narrows the offer list; `NOT IN` removes from
	 * it. Clauses for other taxonomies are ignored — they constrain which
	 * posts match, not which terms of *this* taxonomy are worth offering.
	 *
	 * Deliberately shallow about boolean structure: an `OR` group could in
	 * principle make an `IN` clause non-binding, so the narrowing here is a
	 * best effort at "do not advertise what this query cannot return" rather
	 * than an exact solve. Erring toward a shorter list is the safer side —
	 * the alternative is what shipped, which advertised everything.
	 *
	 * @param array  $entry    A taxQuery, group, or leaf clause.
	 * @param string $taxonomy Taxonomy slug the filter renders.
	 * @param array  $allowed  Accumulator for allowed term IDs (by reference).
	 * @param array  $blocked  Accumulator for forbidden term IDs (by reference).
	 * @return void
	 */
	function designsetgo_query_filter_collect_term_scope( array $entry, $taxonomy, array &$allowed, array &$blocked ) {
		if ( isset( $entry['clauses'] ) && is_array( $entry['clauses'] ) ) {
			foreach ( $entry['clauses'] as $child ) {
				if ( is_array( $child ) ) {
					designsetgo_query_filter_collect_term_scope( $child, $taxonomy, $allowed, $blocked );
				}
			}
			return;
		}

		if ( empty( $entry['taxonomy'] ) || empty( $entry['terms'] ) ) {
			return;
		}
		if ( sanitize_key( (string) $entry['taxonomy'] ) !== $taxonomy ) {
			return;
		}

		$terms    = array_filter( array_map( 'absint', (array) $entry['terms'] ) );
		$operator = isset( $entry['operator'] ) ? strtoupper( (string) $entry['operator'] ) : 'IN';

		if ( 'NOT IN' === $operator ) {
			$blocked = array_merge( $blocked, $terms );
			return;
		}
		// IN and AND both mean "only these terms are in play".
		$allowed = array_merge( $allowed, $terms );
	}

endif;

if ( ! function_exists( 'designsetgo_query_filter_get_scoped_value' ) ) :

	/**
	 * Read a filter's current value from $_GET, preferring the query-scoped
	 * key (`{param}__{queryId}`) over the bare/legacy key.
	 *
	 * Query param scoping (v2.6): once a page carries two Query blocks whose
	 * filter blocks happen to share a `paramName`, the bare key alone can't
	 * tell them apart — see designsetgo_query_extract_params_from_request()
	 * in render-helpers.php for the server-side (WP_Query args) half of this.
	 * This is the read-for-display half, used by every filterKind to decide
	 * what's "currently selected".
	 *
	 * @param string $param_name Bare (unscoped) URL parameter name.
	 * @param string $query_id   Sanitized queryId this filter belongs to.
	 * @return string|array Raw (unslashed, NOT sanitized) value — array for
	 *                       `name[]`-style multi-value params, else string.
	 *                       Empty string when neither key is present.
	 */
	function designsetgo_query_filter_get_scoped_value( $param_name, $query_id ) {
		$scoped_name = '' !== $query_id ? $param_name . '__' . $query_id : $param_name;
		if ( isset( $_GET[ $scoped_name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return wp_unslash( $_GET[ $scoped_name ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		if ( isset( $_GET[ $param_name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return wp_unslash( $_GET[ $param_name ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		return '';
	}

endif;

if ( ! function_exists( 'designsetgo_query_filter_owned_get_params' ) ) :

	/**
	 * Resolve the `q` / `sort` / `filter_*` $_GET entries that belong to a
	 * specific query, keyed by their bare (unscoped) name.
	 *
	 * Mirrors designsetgo_query_extract_params_from_request() in
	 * render-helpers.php (scoped-key-wins, bare key reaches every query) but
	 * scoped to this block's own concerns (no `query_type_*` / WooCommerce
	 * keys — those never reach a DSGo query-filter block) and returning raw,
	 * not-yet-sanitized values so each caller can sanitize the way it always
	 * has (sanitize_text_field for display, sanitize_title for term slugs…).
	 *
	 * A bare key is intentionally ungated: it's already honored for every
	 * query by the server-side WP_Query args (see render-posts.php), so
	 * showing it as "active" and letting Reset strip it here keeps the chip
	 * strip truthful about what's actually filtering the results. Only a key
	 * scoped for a DIFFERENT query is excluded — it never touches this one.
	 *
	 * Used for both the active-filter-count queries (FilterIndex) and the
	 * active/reset chip strips, so a filter's cross-option counts and its
	 * "currently active" chips always agree on which query's selections
	 * they're describing.
	 *
	 * @param string $query_id Sanitized queryId.
	 * @return array<string, mixed> Bare key => raw (unslashed-pending) value.
	 */
	function designsetgo_query_filter_owned_get_params( $query_id ) {
		$query_id = sanitize_key( (string) $query_id );
		$suffix   = '' !== $query_id ? '__' . $query_id : '';
		$owned    = array();

		foreach ( (array) $_GET as $raw_key => $raw_value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key = sanitize_key( (string) $raw_key );
			if ( '' === $key ) {
				continue;
			}

			$is_scoped_for_this_query = false;
			if ( '' !== $suffix && strlen( $suffix ) < strlen( $key ) && substr( $key, -strlen( $suffix ) ) === $suffix ) {
				$key                      = substr( $key, 0, -strlen( $suffix ) );
				$is_scoped_for_this_query = true;
			} elseif ( false !== strpos( $key, '__' ) ) {
				continue; // Scoped for a different query — never ours.
			}

			if ( 0 !== strpos( $key, 'filter_' ) && 'q' !== $key && 'sort' !== $key ) {
				continue;
			}

			if ( $is_scoped_for_this_query || ! isset( $owned[ $key ] ) ) {
				$owned[ $key ] = $raw_value;
			}
		}

		return $owned;
	}

endif;

if ( ! function_exists( 'designsetgo_query_filter_render_search' ) ) :

	/**
	 * Render the search-input variation.
	 *
	 * @param string $wrapper     Pre-computed get_block_wrapper_attributes() string.
	 * @param string $param_name  URL parameter name (usually 'q').
	 * @param string $label       Optional visible label.
	 * @param string $placeholder Input placeholder text.
	 * @param string $query_id    Sanitized queryId this filter belongs to.
	 */
	function designsetgo_query_filter_render_search( $wrapper, $param_name, $label, $placeholder, $query_id = '' ) {
		// $param_name is already sanitize_key()'d at the call site. Coerce array
		// GET (?q[]=value) to a scalar so the input doesn't render "Array".
		$raw     = designsetgo_query_filter_get_scoped_value( $param_name, $query_id );
		$raw     = is_array( $raw ) ? ( isset( $raw[0] ) ? $raw[0] : '' ) : $raw;
		$current = sanitize_text_field( (string) $raw );

		// The rendered `name` attribute is query-scoped: both the JS actions
		// (which read paramName straight off the DOM `name`) and a no-JS
		// `<form method="get">` submission therefore write the scoped key
		// without any further code needing to know about scoping.
		$scoped_name = '' !== $query_id ? $param_name . '__' . $query_id : $param_name;
		$input_id    = 'dsgo-filter-' . sanitize_html_class( $scoped_name );
		$aria_label  = $label ? '' : ' aria-label="' . esc_attr__( 'Search', 'designsetgo' ) . '"';

		printf(
			'<form %1$s method="get" action="" role="search" data-wp-on--submit="actions.setFilter">%2$s<div class="dsgo-query-filter__search-row"><input type="search" id="%7$s" name="%3$s" value="%4$s" placeholder="%5$s" class="dsgo-query-filter__search-input"%8$s /><button type="submit" class="dsgo-query-filter__submit">%6$s</button></div></form>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output + appended data-wp-context JSON (sanitized values + wp_json_encode with JSON_HEX_APOS).
			$label ? '<label for="' . esc_attr( $input_id ) . '" class="dsgo-query-filter__label">' . esc_html( $label ) . '</label>' : '',
			esc_attr( $scoped_name ),
			esc_attr( $current ),
			esc_attr( $placeholder ? $placeholder : __( 'Search…', 'designsetgo' ) ),
			esc_html__( 'Search', 'designsetgo' ),
			esc_attr( $input_id ),
			$aria_label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr__ used inside.
		);
	}

endif;

if ( ! function_exists( 'designsetgo_query_filter_render_sort' ) ) :

	/**
	 * Render the sort-dropdown variation.
	 *
	 * @param string $wrapper    Pre-computed wrapper attributes string.
	 * @param string $param_name URL parameter name (usually 'sort').
	 * @param string $label      Optional visible label.
	 * @param array  $options    Array of {value, label} option definitions.
	 * @param string $query_id   Sanitized queryId this filter belongs to.
	 */
	function designsetgo_query_filter_render_sort( $wrapper, $param_name, $label, array $options, $query_id = '' ) {
		$raw     = designsetgo_query_filter_get_scoped_value( $param_name, $query_id );
		// Coerce array GET (?sort[]=value) to a scalar so `selected()` compares
		// a string, not the literal "Array".
		$raw     = is_array( $raw ) ? ( isset( $raw[0] ) ? $raw[0] : '' ) : $raw;
		$current = sanitize_text_field( (string) $raw );

		$opts_html = '';
		foreach ( $options as $opt ) {
			$val        = isset( $opt['value'] ) ? (string) $opt['value'] : '';
			$opt_label  = isset( $opt['label'] ) ? (string) $opt['label'] : $val;
			$opts_html .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $val ),
				selected( $current, $val, false ),
				esc_html( $opt_label )
			);
		}

		$scoped_name = '' !== $query_id ? $param_name . '__' . $query_id : $param_name;
		$select_id   = 'dsgo-filter-' . sanitize_html_class( $scoped_name );
		$aria_label  = $label ? '' : ' aria-label="' . esc_attr__( 'Sort', 'designsetgo' ) . '"';

		printf(
			'<form %1$s method="get" action="">%2$s<select id="%7$s" name="%3$s" class="dsgo-query-filter__sort" data-wp-on--change="actions.setFilter"%8$s><option value="">%4$s</option>%5$s</select><noscript><button type="submit" class="dsgo-query-filter__nojs-submit">%6$s</button></noscript></form>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$label ? '<label for="' . esc_attr( $select_id ) . '" class="dsgo-query-filter__label">' . esc_html( $label ) . '</label>' : '',
			esc_attr( $scoped_name ),
			esc_html__( 'Default order', 'designsetgo' ),
			$opts_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each option is escaped per-attribute above.
			esc_html__( 'Apply filter', 'designsetgo' ),
			esc_attr( $select_id ),
			$aria_label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr__ used inside.
		);
	}

endif;

if ( ! function_exists( 'designsetgo_query_filter_render_select' ) ) :

	/**
	 * Render the taxonomy single-select dropdown variation.
	 *
	 * @param string $wrapper         Pre-computed wrapper attributes string.
	 * @param string $param_name      URL parameter name.
	 * @param string $label           Optional visible label.
	 * @param string $filter_taxonomy Taxonomy slug.
	 * @param bool   $show_counts     Whether to append (N) counts to option labels.
	 * @param array  $active_filters  Current active filter state for intersection counts.
	 * @param string $post_type       Optional post-type scope for counts.
	 * @param array  $term_include    Term IDs the parent query allows, if any.
	 * @param array  $term_exclude    Term IDs the parent query excludes, if any.
	 * @param string $query_id        Sanitized queryId this filter belongs to.
	 */
	function designsetgo_query_filter_render_select( $wrapper, $param_name, $label, $filter_taxonomy, $show_counts = false, $active_filters = array(), $post_type = '', $term_include = array(), $term_exclude = array(), $query_id = '' ) {
		if ( ! taxonomy_exists( $filter_taxonomy ) ) {
			return;
		}
		$term_args = array(
			'taxonomy'   => $filter_taxonomy,
			'hide_empty' => false,
		);
		// Narrowed by the parent query's own taxQuery, so the filter cannot
		// offer a term the query has already excluded.
		if ( ! empty( $term_include ) ) {
			$term_args['include'] = array_values( array_unique( $term_include ) );
		}
		if ( ! empty( $term_exclude ) ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- a NOT IN clause on the parent query is exactly an exclusion; the list is the author's own small set, not user input.
			$term_args['exclude'] = array_values( array_unique( $term_exclude ) );
		}
		$terms = get_terms( $term_args );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		$raw     = designsetgo_query_filter_get_scoped_value( $param_name, $query_id );
		// Coerce array GET to scalar for the single-select variation.
		$raw     = is_array( $raw ) ? ( isset( $raw[0] ) ? $raw[0] : '' ) : $raw;
		$current = sanitize_title( (string) $raw );

		// Resolve counts if requested.
		$counts = array();
		if ( $show_counts && class_exists( '\DesignSetGo\Blocks\Query\FilterIndex' ) ) {
			$term_ids = array_map(
				function ( $t ) {
					return (string) $t->term_id;
				},
				$terms
			);
			$counts = \DesignSetGo\Blocks\Query\FilterIndex::count_for_options(
				$filter_taxonomy,
				$term_ids,
				$active_filters,
				$post_type
			);
		}

		$opts_html = '';
		foreach ( $terms as $term ) {
			// Browsers strip HTML from <option>, so append the count as plain text —
			// a <span> wrapper would render literally in Firefox and be stripped in
			// Chrome/Safari.
			$label_text = $term->name;
			if ( $show_counts && isset( $counts[ (string) $term->term_id ] ) ) {
				$label_text .= ' (' . (int) $counts[ (string) $term->term_id ] . ')';
			}
			$opts_html .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $term->slug ),
				selected( $current, $term->slug, false ),
				esc_html( $label_text )
			);
		}

		$scoped_name = '' !== $query_id ? $param_name . '__' . $query_id : $param_name;
		$select_id   = 'dsgo-filter-' . sanitize_html_class( $scoped_name );
		$aria_label  = $label ? '' : ' aria-label="' . esc_attr( $filter_taxonomy ) . '"';

		printf(
			'<form %1$s method="get" action="">%2$s<select id="%7$s" name="%3$s" class="dsgo-query-filter__select" data-wp-on--change="actions.setFilter"%8$s><option value="">%4$s</option>%5$s</select><noscript><button type="submit" class="dsgo-query-filter__nojs-submit">%6$s</button></noscript></form>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$label ? '<label for="' . esc_attr( $select_id ) . '" class="dsgo-query-filter__label">' . esc_html( $label ) . '</label>' : '',
			esc_attr( $scoped_name ),
			esc_html__( 'All', 'designsetgo' ),
			$opts_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each option is escaped above.
			esc_html__( 'Apply filter', 'designsetgo' ),
			esc_attr( $select_id ),
			$aria_label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr used inside.
		);
	}

endif;

if ( ! function_exists( 'designsetgo_query_filter_render_checkbox' ) ) :

	/**
	 * Render the taxonomy checkbox-list variation.
	 *
	 * @param string $wrapper         Pre-computed wrapper attributes string.
	 * @param string $param_name      URL parameter name (e.g. filter_category).
	 * @param string $label           Optional legend label.
	 * @param string $filter_taxonomy Taxonomy slug.
	 * @param bool   $show_counts     Whether to append (N) counts to option labels.
	 * @param array  $active_filters  Current active filter state for intersection counts.
	 * @param string $post_type       Optional post-type scope for counts.
	 * @param string $orientation     Layout direction for the checkbox list.
	 * @param string $style           Visual style variant.
	 * @param array  $term_include    Term IDs the parent query allows, if any.
	 * @param array  $term_exclude    Term IDs the parent query excludes, if any.
	 * @param string $query_id        Sanitized queryId this filter belongs to.
	 */
	function designsetgo_query_filter_render_checkbox( $wrapper, $param_name, $label, $filter_taxonomy, $show_counts = false, $active_filters = array(), $post_type = '', $orientation = 'vertical', $style = 'default', $term_include = array(), $term_exclude = array(), $query_id = '' ) {
		if ( ! taxonomy_exists( $filter_taxonomy ) ) {
			return;
		}
		$term_args = array(
			'taxonomy'   => $filter_taxonomy,
			'hide_empty' => false,
		);
		// Narrowed by the parent query's own taxQuery, so the filter cannot
		// offer a term the query has already excluded.
		if ( ! empty( $term_include ) ) {
			$term_args['include'] = array_values( array_unique( $term_include ) );
		}
		if ( ! empty( $term_exclude ) ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- a NOT IN clause on the parent query is exactly an exclusion; the list is the author's own small set, not user input.
			$term_args['exclude'] = array_values( array_unique( $term_exclude ) );
		}
		$terms = get_terms( $term_args );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		// Support both ?filter_category[]=slug and ?filter_category=slug,slug.
		$raw_input    = designsetgo_query_filter_get_scoped_value( $param_name, $query_id );
		$selected_raw = is_array( $raw_input )
			? array_map( 'sanitize_title', $raw_input )
			: array_filter( array_map( 'sanitize_title', explode( ',', (string) $raw_input ) ) );

		// Resolve per-option counts from the filter index if requested.
		$counts = array();
		if ( $show_counts && class_exists( '\DesignSetGo\Blocks\Query\FilterIndex' ) ) {
			$term_ids = array_map(
				function ( $t ) {
					return (string) $t->term_id;
				},
				$terms
			);
			$counts = \DesignSetGo\Blocks\Query\FilterIndex::count_for_options(
				$filter_taxonomy,
				$term_ids,
				$active_filters,
				$post_type
			);
		}

		$scoped_name = '' !== $query_id ? $param_name . '__' . $query_id : $param_name;

		$items_html = '';
		foreach ( $terms as $term ) {
			$checked    = in_array( $term->slug, $selected_raw, true ) ? 'checked' : '';
			$name_label = esc_html( $term->name );
			if ( $show_counts && isset( $counts[ (string) $term->term_id ] ) ) {
				$name_label .= ' <span class="dsgo-query-filter__count">(' . (int) $counts[ (string) $term->term_id ] . ')</span>';
			}
			$items_html .= sprintf(
				'<label class="dsgo-query-filter__checkbox-item"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s data-wp-on--change="actions.toggleFilter" /><span>%4$s</span></label>',
				esc_attr( $scoped_name ),
				esc_attr( $term->slug ),
				esc_attr( $checked ),
				$name_label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() on term->name + our own <span> markup.
			);
		}

		// Fix 4: noscript submit so no-JS users can apply checkbox filters.
		$designsetgo_nojs_btn      = '<noscript><button type="submit" class="dsgo-query-filter__nojs-submit">' . esc_html__( 'Apply filter', 'designsetgo' ) . '</button></noscript>';
		$list_class_parts   = array( 'dsgo-query-filter__checkbox-list' );
		if ( 'horizontal' === $orientation ) {
			$list_class_parts[] = 'is-horizontal';
		}
		if ( in_array( $style, array( 'pill', 'underline' ), true ) ) {
			// `is-style-pill` / `is-style-underline` → SCSS hides the native
			// checkbox and styles the label as a pill or underlined tab. The
			// input stays in the DOM so keyboard/screen-reader users still
			// toggle filters the same way.
			$list_class_parts[] = 'is-style-' . $style;
			// Pill + underline variants always read better as a horizontal row.
			if ( 'horizontal' !== $orientation ) {
				$list_class_parts[] = 'is-horizontal';
			}
		}
		$list_class = implode( ' ', $list_class_parts );
		if ( $label ) {
			printf(
				'<form %1$s method="get" action=""><fieldset class="dsgo-query-filter__fieldset"><legend class="dsgo-query-filter__label">%2$s</legend><div class="%5$s">%3$s</div></fieldset>%4$s</form>',
				$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html( $label ),
				$items_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- per-field escaped above.
				$designsetgo_nojs_btn, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() used inside.
				esc_attr( $list_class )
			);
		} else {
			printf(
				'<form %1$s method="get" action=""><div class="%4$s">%2$s</div>%3$s</form>',
				$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$items_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$designsetgo_nojs_btn, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() used inside.
				esc_attr( $list_class )
			);
		}
	}

endif;

if ( ! function_exists( 'designsetgo_query_filter_render_active' ) ) :

	/**
	 * Render the active-filters chip strip.
	 *
	 * Each chip links to the current URL with that specific filter value
	 * removed, providing an accessible no-JS fallback.
	 *
	 * Query param scoping (v2.6): shows chips for params that belong to THIS
	 * query — a `{key}__{queryId}`-scoped key, or ANY bare/legacy key (a bare
	 * key is honored by every query server-side, same as before this task —
	 * see render-posts.php — so it's shown/removable here too, or the chip
	 * strip would lie about what's actually filtering the results). A key
	 * scoped for a DIFFERENT query is the only thing excluded, so two
	 * queries' active-filter strips no longer show — or let you remove —
	 * each other's own SCOPED selections.
	 *
	 * @param string $wrapper  Pre-computed wrapper attributes string.
	 * @param string $label    Optional visible label.
	 * @param string $query_id Sanitized queryId this filter belongs to.
	 */
	function designsetgo_query_filter_render_active( $wrapper, $label, $query_id = '' ) {
		$suffix        = '' !== $query_id ? '__' . $query_id : '';
		$active_params = array();
		foreach ( (array) $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw_key = sanitize_key( (string) $k );
			if ( '' === $raw_key ) {
				continue;
			}

			$display_key = $raw_key;
			if ( '' !== $suffix && strlen( $suffix ) < strlen( $raw_key ) && substr( $raw_key, -strlen( $suffix ) ) === $suffix ) {
				$display_key = substr( $raw_key, 0, -strlen( $suffix ) );
			} elseif ( false !== strpos( $raw_key, '__' ) ) {
				continue; // Scoped for a different query — never ours.
			}

			if ( 0 === strpos( $display_key, 'filter_' ) || 'q' === $display_key || 'sort' === $display_key ) {
				$values = is_array( $v ) ? $v : array( $v );
				foreach ( $values as $val ) {
					$val = sanitize_text_field( wp_unslash( (string) $val ) );
					if ( '' !== $val ) {
						$active_params[] = array(
							'key'         => $raw_key, // Literal $_GET key — may be query-scoped.
							'display_key' => $display_key, // Bare key — for the human-readable label.
							'value'       => $val,
						);
					}
				}
			}
		}

		if ( empty( $active_params ) ) {
			return;
		}

		$chips_html  = '';
		$current_url = add_query_arg( array() );
		$qs          = wp_parse_url( $current_url, PHP_URL_QUERY );
		parse_str( (string) $qs, $parsed_base );
		$base        = strtok( $current_url, '?' );

		foreach ( $active_params as $p ) {
			// Clone and remove this specific key/value.
			$parsed = $parsed_base;
			if ( isset( $parsed[ $p['key'] ] ) ) {
				if ( is_array( $parsed[ $p['key'] ] ) ) {
					$parsed[ $p['key'] ] = array_values(
						array_diff( $parsed[ $p['key'] ], array( $p['value'] ) )
					);
					if ( empty( $parsed[ $p['key'] ] ) ) {
						unset( $parsed[ $p['key'] ] );
					}
				} else {
					unset( $parsed[ $p['key'] ] );
				}
			}

			// Fix 3 (PHP): strip both WordPress pagination params from chip hrefs.
			unset( $parsed['paged'], $parsed['page'] );

			// Fix 5: use http_build_query so nested associative arrays (e.g.
			// foo[bar]=baz) are preserved correctly, then normalize only our known
			// filter-related keys' numeric-indexed brackets (filter_foo[0]=x →
			// filter_foo[]=x) without corrupting arbitrary nested params. The
			// `filter_[a-z0-9_-]+` branch already matches a query-scoped key
			// like `filter_category__qa1b2c3d4` unchanged — `__` is just two
			// characters from that same allowed set.
			$qs_encoded = http_build_query( $parsed );
			$qs_encoded = preg_replace_callback(
				'/(^|&)((?:filter_[a-z0-9_-]+|q|sort))%5B\d+%5D=/i',
				function ( $m ) {
					return $m[1] . $m[2] . '%5B%5D=';
				},
				$qs_encoded
			);
			$new_url = $qs_encoded ? $base . '?' . $qs_encoded : $base;
			// Derive a human dimension label from the bare key: "filter_post_tag" → "post tag".
			$designsetgo_dimension = 'q' === $p['display_key']
				? __( 'search', 'designsetgo' )
				: str_replace( array( 'filter_', '_' ), array( '', ' ' ), $p['display_key'] );
			$chips_html .= sprintf(
				'<a href="%1$s" role="button" class="dsgo-query-filter__chip" data-wp-on--click="actions.removeActiveFilter" data-dsgo-filter-key="%2$s" data-dsgo-filter-value="%3$s">%4$s<span aria-hidden="true"> &times;</span><span class="screen-reader-text">%5$s</span></a>',
				esc_url( $new_url ),
				esc_attr( $p['display_key'] ),
				esc_attr( $p['value'] ),
				esc_html( $p['value'] ),
				esc_html(
					sprintf(
						/* translators: 1: filter dimension name (e.g. "category"), 2: filter value (e.g. "photography") */
						__( 'Remove %1$s: %2$s', 'designsetgo' ),
						$designsetgo_dimension,
						$p['value']
					)
				)
			);
		}

		printf(
			'<div %1$s>%2$s%3$s</div>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$label ? '<span class="dsgo-query-filter__label">' . esc_html( $label ) . '</span>' : '',
			$chips_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each chip escaped above.
		);
	}

endif;

if ( ! function_exists( 'designsetgo_query_filter_render_reset' ) ) :

	/**
	 * Render the reset-all-filters button.
	 *
	 * The href strips filter_*, q, sort, and paged from the URL so the
	 * no-JS fallback works: clicking the link navigates to a clean URL.
	 *
	 * Query param scoping (v2.6): strips params that belong to THIS query —
	 * a scoped-for-this-query key, or ANY bare/legacy key (honored by every
	 * query server-side, same as before this task, so Reset must clear it
	 * here too or it would silently keep filtering after a "reset"). Only a
	 * key scoped for a DIFFERENT query is left untouched. `paged`/`page` are
	 * always stripped regardless, since WordPress's own pagination query
	 * vars aren't query-scoped at all.
	 *
	 * @param string $wrapper  Pre-computed wrapper attributes string.
	 * @param string $label    Optional button text (default "Reset filters").
	 * @param string $query_id Sanitized queryId this filter belongs to.
	 */
	function designsetgo_query_filter_render_reset( $wrapper, $label, $query_id = '' ) {
		$current_url = add_query_arg( array() );
		$qs          = wp_parse_url( $current_url, PHP_URL_QUERY );
		parse_str( (string) $qs, $parsed );

		$suffix = '' !== $query_id ? '__' . $query_id : '';

		foreach ( array_keys( $parsed ) as $k ) {
			$k = (string) $k;
			if ( 'paged' === $k || 'page' === $k ) {
				unset( $parsed[ $k ] );
				continue;
			}

			$display_key = $k;
			if ( '' !== $suffix && strlen( $suffix ) < strlen( $k ) && substr( $k, -strlen( $suffix ) ) === $suffix ) {
				$display_key = substr( $k, 0, -strlen( $suffix ) );
			} elseif ( false !== strpos( $k, '__' ) ) {
				continue; // Scoped for a different query — leave it alone.
			}

			if ( 0 === strpos( $display_key, 'filter_' ) || 'q' === $display_key || 'sort' === $display_key ) {
				unset( $parsed[ $k ] );
			}
		}

		$base       = strtok( $current_url, '?' );
		// Fix 5: use http_build_query to handle nested associative arrays correctly,
		// then normalize only filter-related numeric brackets to empty brackets.
		$qs_encoded = http_build_query( $parsed );
		$qs_encoded = preg_replace_callback(
			'/(^|&)((?:filter_[a-z0-9_-]+|q|sort))%5B\d+%5D=/i',
			function ( $m ) {
				return $m[1] . $m[2] . '%5B%5D=';
			},
			$qs_encoded
		);
		$reset_url  = $qs_encoded ? $base . '?' . $qs_encoded : $base;
		$btn_label = $label ? $label : __( 'Reset filters', 'designsetgo' );

		printf(
			'<div %1$s><a href="%2$s" role="button" class="dsgo-query-filter__reset" data-wp-on--click="actions.resetAll">%3$s</a></div>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_url( $reset_url ),
			esc_html( $btn_label )
		);
	}

endif;

// ---------------------------------------------------------------------------
// Dispatcher — runs on every block render. Lives at the bottom of the file so
// every helper above is defined by the time we invoke one.
// ---------------------------------------------------------------------------

$designsetgo_query_id = isset( $block->context['designsetgo/queryId'] )
	? sanitize_key( (string) $block->context['designsetgo/queryId'] )
	: '';

if ( '' === $designsetgo_query_id ) {
	return;
}

$designsetgo_filter_kind        = isset( $attributes['filterKind'] ) ? sanitize_key( (string) $attributes['filterKind'] ) : 'checkbox';
$designsetgo_filter_param       = isset( $attributes['paramName'] ) ? sanitize_key( (string) $attributes['paramName'] ) : '';
$designsetgo_filter_label       = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
$designsetgo_filter_placeholder = isset( $attributes['placeholder'] ) ? (string) $attributes['placeholder'] : '';
$designsetgo_filter_taxonomy    = isset( $attributes['taxonomy'] ) ? sanitize_key( (string) $attributes['taxonomy'] ) : 'category';
$designsetgo_filter_orientation = ( isset( $attributes['orientation'] ) && 'horizontal' === $attributes['orientation'] ) ? 'horizontal' : 'vertical';
// Default `filterStyle` is `default` (classic checkboxes) to preserve the
// look of pre-existing saved blocks that have no `filterStyle` attribute.
// New inserts through the inserter variation opt into `underline` explicitly
// — see the variation attributes in src/blocks/query-filter/variations.js.
$designsetgo_filter_style       = isset( $attributes['filterStyle'] ) && in_array( $attributes['filterStyle'], array( 'pill', 'underline' ), true )
	? $attributes['filterStyle']
	: 'default';

// Post-type scope for counts: only non-empty when the parent query targets a
// specific post type (source === 'posts'). Users/terms sources leave it empty
// so the count query runs unscoped (those rows carry post_type='' anyway).
$designsetgo_query_source    = isset( $block->context['designsetgo/querySource'] )
	? sanitize_key( (string) $block->context['designsetgo/querySource'] )
	: 'posts';
$designsetgo_query_post_type = '';
if ( 'posts' === $designsetgo_query_source && isset( $block->context['designsetgo/queryPostType'] ) ) {
	$designsetgo_query_post_type = sanitize_key( (string) $block->context['designsetgo/queryPostType'] );
}

// Term scope: a filter must not offer a term its own query has already ruled
// out. The parent query's `taxQuery` reaches us through context; clauses for
// THIS taxonomy narrow the option list, so a query restricted to four
// categories stops advertising every other category on the site — each of
// which would have shown a non-zero count and then returned no results,
// because the count comes from the index and knows nothing about the query.
$designsetgo_term_include = array();
$designsetgo_term_exclude = array();
if ( isset( $block->context['designsetgo/queryTaxQuery'] ) ) {
	designsetgo_query_filter_collect_term_scope(
		(array) $block->context['designsetgo/queryTaxQuery'],
		$designsetgo_filter_taxonomy,
		$designsetgo_term_include,
		$designsetgo_term_exclude
	);
}

// Whether to show (N) counts next to filter options (default: true).
$designsetgo_show_counts = ! isset( $attributes['showCounts'] ) || (bool) $attributes['showCounts'];

// Extract active filters from $_GET so count queries respect the current
// filter state. On the REST-refresh path, $_GET has been overlaid by the
// REST controller with the incoming params, so this is always up-to-date.
// Scoped to THIS query (see designsetgo_query_filter_owned_get_params()) so
// a sibling query's same-named SCOPED filter_* selection never skews these
// counts; a bare/legacy key still counts, since it filters this query too.
$designsetgo_active_filters = array();
foreach ( designsetgo_query_filter_owned_get_params( $designsetgo_query_id ) as $designsetgo_k => $designsetgo_v ) {
	if ( 0 === strpos( $designsetgo_k, 'filter_' ) ) {
		if ( is_array( $designsetgo_v ) ) {
			$designsetgo_active_filters[ $designsetgo_k ] = array_map( 'sanitize_text_field', wp_unslash( $designsetgo_v ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} else {
			$designsetgo_val = sanitize_text_field( wp_unslash( (string) $designsetgo_v ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( '' !== $designsetgo_val ) {
				$designsetgo_active_filters[ $designsetgo_k ] = array( $designsetgo_val );
			}
		}
	}
}

// Re-key active_filters to use the bare taxonomy slug (strip "filter_" prefix)
// because FilterIndex::count_for_options() expects filter keys, not URL param names.
$designsetgo_active_filters_by_key = array();
foreach ( $designsetgo_active_filters as $designsetgo_param_key => $designsetgo_param_values ) {
	$designsetgo_filter_key = 'filter_' === substr( $designsetgo_param_key, 0, 7 )
		? substr( $designsetgo_param_key, 7 )
		: $designsetgo_param_key;
	$designsetgo_active_filters_by_key[ $designsetgo_filter_key ] = $designsetgo_param_values;
}

// Translate taxonomy slugs in active_filters_by_key to term IDs.
//
// URL params carry taxonomy slugs (e.g. filter_category=news), but the filter
// index stores term IDs (integers as strings). Without this translation, the
// intersection subquery in count_for_options() looks for filter_value='news'
// but the index has filter_value='42', so all cross-filter counts collapse to 0.
//
// Meta filters store their value verbatim — no translation needed.
if ( class_exists( '\DesignSetGo\Blocks\Query\FilterRegistry' ) ) {
	$designsetgo_registered_filters = \DesignSetGo\Blocks\Query\FilterRegistry::all();
	foreach ( $designsetgo_active_filters_by_key as $designsetgo_fk => $designsetgo_fv ) {
		$designsetgo_filter_config = $designsetgo_registered_filters[ $designsetgo_fk ] ?? null;
		if ( ! $designsetgo_filter_config || 'taxonomy' !== ( $designsetgo_filter_config['type'] ?? '' ) ) {
			continue; // Meta or unknown — values are already in the correct format.
		}
		// Keep the loop variable distinct from $designsetgo_filter_taxonomy, which holds
		// this block's own taxonomy and is needed later for the is_available()
		// check and the render-dispatch switch.
		$designsetgo_iter_taxonomy = (string) ( $designsetgo_filter_config['source'] ?? '' );
		if ( '' === $designsetgo_iter_taxonomy ) {
			continue;
		}
		$designsetgo_translated = array();
		foreach ( (array) $designsetgo_fv as $designsetgo_slug_or_id ) {
			$designsetgo_slug_or_id = (string) $designsetgo_slug_or_id;
			if ( ctype_digit( $designsetgo_slug_or_id ) ) {
				// Already a numeric ID — pass through as-is.
				$designsetgo_translated[] = $designsetgo_slug_or_id;
				continue;
			}
			$designsetgo_term = get_term_by( 'slug', $designsetgo_slug_or_id, $designsetgo_iter_taxonomy );
			if ( $designsetgo_term instanceof \WP_Term ) {
				$designsetgo_translated[] = (string) $designsetgo_term->term_id;
			}
		}
		$designsetgo_active_filters_by_key[ $designsetgo_fk ] = $designsetgo_translated;
	}
	unset( $designsetgo_registered_filters, $designsetgo_filter_config, $designsetgo_iter_taxonomy, $designsetgo_translated, $designsetgo_slug_or_id, $designsetgo_term );
}

// Only render counts when the filter is indexed AND showCounts is enabled.
$designsetgo_counts_enabled = $designsetgo_show_counts
	&& class_exists( '\DesignSetGo\Blocks\Query\FilterIndex' )
	&& \DesignSetGo\Blocks\Query\FilterIndex::is_available( $designsetgo_filter_taxonomy );

$designsetgo_filter_wrapper = get_block_wrapper_attributes(
	array(
		'class'                 => 'dsgo-query-filter dsgo-query-filter--' . esc_attr( $designsetgo_filter_kind ),
		'data-wp-interactive'   => 'designsetgo/query',
		'data-dsgo-query-id'    => $designsetgo_query_id,
		'data-dsgo-filter-kind' => $designsetgo_filter_kind,
		'data-dsgo-param'       => $designsetgo_filter_param,
	)
);
// Seed IAPI context so `getContext()` inside setFilter / toggleFilter /
// removeActiveFilter / resetAll resolves ctx.queryId. Appended
// outside get_block_wrapper_attributes() because that helper runs esc_attr()
// on values, which would mangle JSON quotes.
// JSON_HEX_APOS defends the single-quoted attribute boundary against a
// stray apostrophe in any future context value (today all four are quote-
// free: sanitize_key / false / esc_url_raw / wp_create_nonce).
$designsetgo_filter_wrapper .= sprintf(
	" data-wp-context='%s'",
	wp_json_encode(
		array(
			'queryId' => $designsetgo_query_id,
			'busy'    => false,
			'restUrl' => esc_url_raw( rest_url( 'designsetgo/v1/query/render' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		),
		JSON_HEX_APOS
	)
);

switch ( $designsetgo_filter_kind ) {
	case 'search':
		designsetgo_query_filter_render_search( $designsetgo_filter_wrapper, $designsetgo_filter_param, $designsetgo_filter_label, $designsetgo_filter_placeholder, $designsetgo_query_id );
		break;
	case 'sort':
		$designsetgo_sort_options = isset( $attributes['sortOptions'] ) ? (array) $attributes['sortOptions'] : array();
		designsetgo_query_filter_render_sort( $designsetgo_filter_wrapper, $designsetgo_filter_param, $designsetgo_filter_label, $designsetgo_sort_options, $designsetgo_query_id );
		break;
	case 'select':
		designsetgo_query_filter_render_select( $designsetgo_filter_wrapper, $designsetgo_filter_param, $designsetgo_filter_label, $designsetgo_filter_taxonomy, $designsetgo_counts_enabled, $designsetgo_active_filters_by_key, $designsetgo_query_post_type, $designsetgo_term_include, $designsetgo_term_exclude, $designsetgo_query_id );
		break;
	case 'active':
		designsetgo_query_filter_render_active( $designsetgo_filter_wrapper, $designsetgo_filter_label, $designsetgo_query_id );
		break;
	case 'reset':
		designsetgo_query_filter_render_reset( $designsetgo_filter_wrapper, $designsetgo_filter_label, $designsetgo_query_id );
		break;
	case 'checkbox':
	default:
		designsetgo_query_filter_render_checkbox( $designsetgo_filter_wrapper, $designsetgo_filter_param, $designsetgo_filter_label, $designsetgo_filter_taxonomy, $designsetgo_counts_enabled, $designsetgo_active_filters_by_key, $designsetgo_query_post_type, $designsetgo_filter_orientation, $designsetgo_filter_style, $designsetgo_term_include, $designsetgo_term_exclude, $designsetgo_query_id );
		break;
}
