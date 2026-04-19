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

$dsgo_query_id = isset( $block->context['designsetgo/queryId'] )
	? sanitize_key( (string) $block->context['designsetgo/queryId'] )
	: '';

if ( '' === $dsgo_query_id ) {
	return;
}

$dsgo_filter_kind        = isset( $attributes['filterKind'] ) ? sanitize_key( (string) $attributes['filterKind'] ) : 'checkbox';
$dsgo_filter_param       = isset( $attributes['paramName'] ) ? sanitize_key( (string) $attributes['paramName'] ) : '';
$dsgo_filter_label       = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
$dsgo_filter_placeholder = isset( $attributes['placeholder'] ) ? (string) $attributes['placeholder'] : '';
$dsgo_filter_taxonomy    = isset( $attributes['taxonomy'] ) ? sanitize_key( (string) $attributes['taxonomy'] ) : 'category';

$dsgo_filter_wrapper = get_block_wrapper_attributes(
	array(
		'class'                 => 'dsgo-query-filter dsgo-query-filter--' . esc_attr( $dsgo_filter_kind ),
		'data-wp-interactive'   => 'designsetgo/query',
		'data-dsgo-query-id'    => $dsgo_query_id,
		'data-dsgo-filter-kind' => $dsgo_filter_kind,
		'data-dsgo-param'       => $dsgo_filter_param,
	)
);

switch ( $dsgo_filter_kind ) {
	case 'search':
		designsetgo_query_filter_render_search( $dsgo_filter_wrapper, $dsgo_filter_param, $dsgo_filter_label, $dsgo_filter_placeholder );
		break;
	case 'sort':
		$dsgo_sort_options = isset( $attributes['sortOptions'] ) ? (array) $attributes['sortOptions'] : array();
		designsetgo_query_filter_render_sort( $dsgo_filter_wrapper, $dsgo_filter_param, $dsgo_filter_label, $dsgo_sort_options );
		break;
	case 'select':
		designsetgo_query_filter_render_select( $dsgo_filter_wrapper, $dsgo_filter_param, $dsgo_filter_label, $dsgo_filter_taxonomy );
		break;
	case 'active':
		designsetgo_query_filter_render_active( $dsgo_filter_wrapper, $dsgo_filter_label );
		break;
	case 'reset':
		designsetgo_query_filter_render_reset( $dsgo_filter_wrapper, $dsgo_filter_label );
		break;
	case 'checkbox':
	default:
		designsetgo_query_filter_render_checkbox( $dsgo_filter_wrapper, $dsgo_filter_param, $dsgo_filter_label, $dsgo_filter_taxonomy );
		break;
}

// ---------------------------------------------------------------------------
// Helper renderers (one per filterKind)
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

		printf(
			'<form %1$s method="get" action="" role="search">%2$s<div class="dsgo-query-filter__search-row"><input type="search" name="%3$s" value="%4$s" placeholder="%5$s" class="dsgo-query-filter__search-input" data-wp-on--change="actions.setFilter" data-wp-on--input="actions.setFilterDebounced" /><button type="submit" class="dsgo-query-filter__submit">%6$s</button></div></form>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output.
			$label ? '<label class="dsgo-query-filter__label">' . esc_html( $label ) . '</label>' : '',
			esc_attr( $param_name ),
			esc_attr( $current ),
			esc_attr( $placeholder ? $placeholder : __( 'Search…', 'designsetgo' ) ),
			esc_html__( 'Search', 'designsetgo' )
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

		printf(
			'<form %1$s method="get" action="">%2$s<select name="%3$s" class="dsgo-query-filter__sort" data-wp-on--change="actions.setFilter"><option value="">%4$s</option>%5$s</select><noscript><button type="submit" class="dsgo-query-filter__nojs-submit">%6$s</button></noscript></form>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$label ? '<label class="dsgo-query-filter__label">' . esc_html( $label ) . '</label>' : '',
			esc_attr( $param_name ),
			esc_html__( 'Default order', 'designsetgo' ),
			$opts_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each option is escaped per-attribute above.
			esc_html__( 'Apply filter', 'designsetgo' )
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
	 */
	function designsetgo_query_filter_render_select( $wrapper, $param_name, $label, $filter_taxonomy ) {
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

		$opts_html = '';
		foreach ( $terms as $term ) {
			$opts_html .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $term->slug ),
				selected( $current, $term->slug, false ),
				esc_html( $term->name )
			);
		}

		printf(
			'<form %1$s method="get" action="">%2$s<select name="%3$s" class="dsgo-query-filter__select" data-wp-on--change="actions.setFilter"><option value="">%4$s</option>%5$s</select><noscript><button type="submit" class="dsgo-query-filter__nojs-submit">%6$s</button></noscript></form>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$label ? '<label class="dsgo-query-filter__label">' . esc_html( $label ) . '</label>' : '',
			esc_attr( $param_name ),
			esc_html__( 'All', 'designsetgo' ),
			$opts_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each option is escaped above.
			esc_html__( 'Apply filter', 'designsetgo' )
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
	 */
	function designsetgo_query_filter_render_checkbox( $wrapper, $param_name, $label, $filter_taxonomy ) {
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

		$items_html = '';
		foreach ( $terms as $term ) {
			$checked     = in_array( $term->slug, $selected_raw, true ) ? 'checked' : '';
			$items_html .= sprintf(
				'<label class="dsgo-query-filter__checkbox-item"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s data-wp-on--change="actions.toggleFilter" /><span>%4$s</span></label>',
				esc_attr( $param_name ),
				esc_attr( $term->slug ),
				esc_attr( $checked ),
				esc_html( $term->name )
			);
		}

		// Fix 4: noscript submit so no-JS users can apply checkbox filters.
		$dsgo_nojs_btn = '<noscript><button type="submit" class="dsgo-query-filter__nojs-submit">' . esc_html__( 'Apply filter', 'designsetgo' ) . '</button></noscript>';
		if ( $label ) {
			printf(
				'<form %1$s method="get" action=""><fieldset class="dsgo-query-filter__fieldset"><legend class="dsgo-query-filter__label">%2$s</legend><div class="dsgo-query-filter__checkbox-list">%3$s</div></fieldset>%4$s</form>',
				$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html( $label ),
				$items_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- per-field escaped above.
				$dsgo_nojs_btn // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() used inside.
			);
		} else {
			printf(
				'<form %1$s method="get" action=""><div class="dsgo-query-filter__checkbox-list">%2$s</div>%3$s</form>',
				$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$items_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$dsgo_nojs_btn // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() used inside.
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
			$chips_html .= sprintf(
				'<a href="%1$s" class="dsgo-query-filter__chip" data-wp-on--click="actions.removeActiveFilter" data-dsgo-filter-key="%2$s" data-dsgo-filter-value="%3$s">%4$s<span aria-hidden="true"> &times;</span><span class="screen-reader-text">%5$s</span></a>',
				esc_url( $new_url ),
				esc_attr( $p['key'] ),
				esc_attr( $p['value'] ),
				esc_html( $p['value'] ),
				esc_html__( 'Remove filter', 'designsetgo' )
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
			'<div %1$s><a href="%2$s" class="dsgo-query-filter__reset" data-wp-on--click="actions.resetAll">%3$s</a></div>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_url( $reset_url ),
			esc_html( $btn_label )
		);
	}

endif;
