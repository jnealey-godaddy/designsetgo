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

if ( ! function_exists( 'designsetgo_query_filter_render_search' ) ) :

	/**
	 * Render the search-input variation.
	 *
	 * @param string $wrapper     Pre-computed get_block_wrapper_attributes() string.
	 * @param string $param_name  URL parameter name (usually 'q').
	 * @param string $label       Optional visible label.
	 * @param string $placeholder Input placeholder text.
	 */
	function designsetgo_query_filter_render_search( $wrapper, $param_name, $label, $placeholder ) {
		// $param_name is already sanitize_key()'d at the call site. Coerce array
		// GET (?q[]=value) to a scalar so the input doesn't render "Array".
		$raw     = isset( $_GET[ $param_name ] ) ? wp_unslash( $_GET[ $param_name ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw     = is_array( $raw ) ? ( isset( $raw[0] ) ? $raw[0] : '' ) : $raw;
		$current = sanitize_text_field( (string) $raw );

		$input_id   = 'dsgo-filter-' . sanitize_html_class( $param_name );
		$aria_label = $label ? '' : ' aria-label="' . esc_attr__( 'Search', 'designsetgo' ) . '"';

		printf(
			'<form %1$s method="get" action="" role="search" data-wp-on--submit="actions.setFilter">%2$s<div class="dsgo-query-filter__search-row"><input type="search" id="%7$s" name="%3$s" value="%4$s" placeholder="%5$s" class="dsgo-query-filter__search-input"%8$s /><button type="submit" class="dsgo-query-filter__submit">%6$s</button></div></form>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output + appended data-wp-context JSON (sanitized values + wp_json_encode with JSON_HEX_APOS).
			$label ? '<label for="' . esc_attr( $input_id ) . '" class="dsgo-query-filter__label">' . esc_html( $label ) . '</label>' : '',
			esc_attr( $param_name ),
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
	 */
	function designsetgo_query_filter_render_sort( $wrapper, $param_name, $label, array $options ) {
		$raw     = isset( $_GET[ $param_name ] ) ? wp_unslash( $_GET[ $param_name ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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

		$select_id  = 'dsgo-filter-' . sanitize_html_class( $param_name );
		$aria_label = $label ? '' : ' aria-label="' . esc_attr__( 'Sort', 'designsetgo' ) . '"';

		printf(
			'<form %1$s method="get" action="">%2$s<select id="%7$s" name="%3$s" class="dsgo-query-filter__sort" data-wp-on--change="actions.setFilter"%8$s><option value="">%4$s</option>%5$s</select><noscript><button type="submit" class="dsgo-query-filter__nojs-submit">%6$s</button></noscript></form>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$label ? '<label for="' . esc_attr( $select_id ) . '" class="dsgo-query-filter__label">' . esc_html( $label ) . '</label>' : '',
			esc_attr( $param_name ),
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
	 */
	function designsetgo_query_filter_render_select( $wrapper, $param_name, $label, $filter_taxonomy, $show_counts = false, $active_filters = array(), $post_type = '' ) {
		if ( ! taxonomy_exists( $filter_taxonomy ) ) {
			return;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $filter_taxonomy,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		$raw     = isset( $_GET[ $param_name ] ) ? wp_unslash( $_GET[ $param_name ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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

		$select_id  = 'dsgo-filter-' . sanitize_html_class( $param_name );
		$aria_label = $label ? '' : ' aria-label="' . esc_attr( $filter_taxonomy ) . '"';

		printf(
			'<form %1$s method="get" action="">%2$s<select id="%7$s" name="%3$s" class="dsgo-query-filter__select" data-wp-on--change="actions.setFilter"%8$s><option value="">%4$s</option>%5$s</select><noscript><button type="submit" class="dsgo-query-filter__nojs-submit">%6$s</button></noscript></form>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$label ? '<label for="' . esc_attr( $select_id ) . '" class="dsgo-query-filter__label">' . esc_html( $label ) . '</label>' : '',
			esc_attr( $param_name ),
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
	 */
	function designsetgo_query_filter_render_checkbox( $wrapper, $param_name, $label, $filter_taxonomy, $show_counts = false, $active_filters = array(), $post_type = '', $orientation = 'vertical', $style = 'default' ) {
		if ( ! taxonomy_exists( $filter_taxonomy ) ) {
			return;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $filter_taxonomy,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		// Support both ?filter_category[]=slug and ?filter_category=slug,slug.
		$selected_raw = array();
		if ( isset( $_GET[ $param_name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw_input    = wp_unslash( $_GET[ $param_name ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$selected_raw = is_array( $raw_input )
				? array_map( 'sanitize_title', $raw_input )
				: array_filter( array_map( 'sanitize_title', explode( ',', (string) $raw_input ) ) );
		}

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

		$items_html = '';
		foreach ( $terms as $term ) {
			$checked    = in_array( $term->slug, $selected_raw, true ) ? 'checked' : '';
			$name_label = esc_html( $term->name );
			if ( $show_counts && isset( $counts[ (string) $term->term_id ] ) ) {
				$name_label .= ' <span class="dsgo-query-filter__count">(' . (int) $counts[ (string) $term->term_id ] . ')</span>';
			}
			$items_html .= sprintf(
				'<label class="dsgo-query-filter__checkbox-item"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s data-wp-on--change="actions.toggleFilter" /><span>%4$s</span></label>',
				esc_attr( $param_name ),
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
	 * @param string $wrapper Pre-computed wrapper attributes string.
	 * @param string $label   Optional visible label.
	 */
	function designsetgo_query_filter_render_active( $wrapper, $label ) {
		$active_params = array();
		foreach ( (array) $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$k = sanitize_key( (string) $k );
			if ( '' === $k ) {
				continue;
			}
			if ( 0 === strpos( $k, 'filter_' ) || 'q' === $k || 'sort' === $k ) {
				$values = is_array( $v ) ? $v : array( $v );
				foreach ( $values as $val ) {
					$val = sanitize_text_field( wp_unslash( (string) $val ) );
					if ( '' !== $val ) {
						$active_params[] = array(
							'key'   => $k,
							'value' => $val,
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
			// filter_foo[]=x) without corrupting arbitrary nested params.
			$qs_encoded = http_build_query( $parsed );
			$qs_encoded = preg_replace_callback(
				'/(^|&)((?:filter_[a-z0-9_-]+|q|sort))%5B\d+%5D=/i',
				function ( $m ) {
					return $m[1] . $m[2] . '%5B%5D=';
				},
				$qs_encoded
			);
			$new_url = $qs_encoded ? $base . '?' . $qs_encoded : $base;
			// Derive a human dimension label from the URL key: "filter_post_tag" → "post tag".
			$designsetgo_dimension = 'q' === $p['key']
				? __( 'search', 'designsetgo' )
				: str_replace( array( 'filter_', '_' ), array( '', ' ' ), $p['key'] );
			$chips_html .= sprintf(
				'<a href="%1$s" role="button" class="dsgo-query-filter__chip" data-wp-on--click="actions.removeActiveFilter" data-dsgo-filter-key="%2$s" data-dsgo-filter-value="%3$s">%4$s<span aria-hidden="true"> &times;</span><span class="screen-reader-text">%5$s</span></a>',
				esc_url( $new_url ),
				esc_attr( $p['key'] ),
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
	 * @param string $wrapper Pre-computed wrapper attributes string.
	 * @param string $label   Optional button text (default "Reset filters").
	 */
	function designsetgo_query_filter_render_reset( $wrapper, $label ) {
		$current_url = add_query_arg( array() );
		$qs          = wp_parse_url( $current_url, PHP_URL_QUERY );
		parse_str( (string) $qs, $parsed );

		foreach ( array_keys( $parsed ) as $k ) {
			// Fix 3 (PHP): strip both WordPress pagination params.
			if ( 0 === strpos( (string) $k, 'filter_' ) || 'q' === $k || 'sort' === $k || 'paged' === $k || 'page' === $k ) {
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

// Whether to show (N) counts next to filter options (default: true).
$designsetgo_show_counts = ! isset( $attributes['showCounts'] ) || (bool) $attributes['showCounts'];

// Extract active filters from $_GET so count queries respect the current
// filter state. On the REST-refresh path, $_GET has been overlaid by the
// REST controller with the incoming params, so this is always up-to-date.
$designsetgo_active_filters = array();
foreach ( (array) $_GET as $designsetgo_k => $designsetgo_v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$designsetgo_k = sanitize_key( (string) $designsetgo_k );
	if ( '' === $designsetgo_k ) {
		continue;
	}
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
		designsetgo_query_filter_render_search( $designsetgo_filter_wrapper, $designsetgo_filter_param, $designsetgo_filter_label, $designsetgo_filter_placeholder );
		break;
	case 'sort':
		$designsetgo_sort_options = isset( $attributes['sortOptions'] ) ? (array) $attributes['sortOptions'] : array();
		designsetgo_query_filter_render_sort( $designsetgo_filter_wrapper, $designsetgo_filter_param, $designsetgo_filter_label, $designsetgo_sort_options );
		break;
	case 'select':
		designsetgo_query_filter_render_select( $designsetgo_filter_wrapper, $designsetgo_filter_param, $designsetgo_filter_label, $designsetgo_filter_taxonomy, $designsetgo_counts_enabled, $designsetgo_active_filters_by_key, $designsetgo_query_post_type );
		break;
	case 'active':
		designsetgo_query_filter_render_active( $designsetgo_filter_wrapper, $designsetgo_filter_label );
		break;
	case 'reset':
		designsetgo_query_filter_render_reset( $designsetgo_filter_wrapper, $designsetgo_filter_label );
		break;
	case 'checkbox':
	default:
		designsetgo_query_filter_render_checkbox( $designsetgo_filter_wrapper, $designsetgo_filter_param, $designsetgo_filter_label, $designsetgo_filter_taxonomy, $designsetgo_counts_enabled, $designsetgo_active_filters_by_key, $designsetgo_query_post_type, $designsetgo_filter_orientation, $designsetgo_filter_style );
		break;
}
