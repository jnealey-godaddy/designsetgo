<?php
/**
 * Dynamic Query — URLs for filter forms, active-filter chips and Reset.
 *
 * Mirrors url-scope.js, so the no-JS fallbacks (a submitted form, a chip or
 * Reset link followed without JS) land on the same URL the in-place refresh
 * would. Key rules live in param-scoping.php.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/param-scoping.php';

if ( ! function_exists( 'designsetgo_query_preserved_inputs' ) ) :

	/**
	 * Hidden inputs that carry the rest of the URL through a no-JS form.
	 *
	 * A `<form method="get">` replaces the whole query string with its own
	 * fields, which dropped other Queries' filters and, with plain
	 * permalinks, `page_id` itself. Every current URL arg is carried except
	 * the ones this form writes and this Query's page (which resets to 1).
	 * Read from the page URL rather than $_GET, so a form re-rendered by the
	 * REST refresh (which overlays the page URL) carries the page's args,
	 * not the endpoint's.
	 *
	 * @param string   $query_id Sanitized queryId.
	 * @param string[] $own_keys Keys the form writes (bare and scoped).
	 * @return string Escaped hidden inputs.
	 */
	function designsetgo_query_preserved_inputs( $query_id, array $own_keys ) {
		$multi = designsetgo_query_page_is_multi();
		$skip  = array_merge( $own_keys, array( 'qpage__' . $query_id ) );
		if ( ! $multi ) {
			$skip[] = 'paged';
			$skip[] = 'page';
		}
		list( , $args ) = designsetgo_query_current_url_args();

		$html = '';
		foreach ( $args as $key => $value ) {
			$key = (string) $key;
			if ( in_array( sanitize_key( $key ), $skip, true ) ) {
				continue;
			}
			$name = is_array( $value ) ? $key . '[]' : $key;
			foreach ( (array) $value as $item ) {
				if ( is_array( $item ) ) {
					continue; // Nested arrays aren't DSGo params; don't guess.
				}
				$html .= sprintf(
					'<input type="hidden" name="%1$s" value="%2$s" />',
					esc_attr( $name ),
					esc_attr( (string) $item )
				);
			}
		}
		// A shared `paged` would otherwise keep this Query on page N.
		if ( $multi && ( isset( $args['paged'] ) || isset( $args['page'] ) || absint( get_query_var( 'paged' ) ) > 1 ) ) {
			$html .= sprintf( '<input type="hidden" name="%s" value="1" />', esc_attr( 'qpage__' . $query_id ) );
		}
		return $html;
	}

endif;

if ( ! function_exists( 'designsetgo_query_write_owned' ) ) :

	/**
	 * Write one Query's values for a param into parsed URL args.
	 *
	 * Mirrors writeOwned() in url-scope.js, so a no-JS chip or Reset link
	 * lands on the same URL the in-place refresh would. With several
	 * Queries, clearing a value this Query inherited from a bare key writes
	 * an empty scoped key, which hides the bare value from this Query only.
	 *
	 * @param array  $args     Parsed query args (parse_str() shape), by reference.
	 * @param string $bare     Bare param name.
	 * @param string $query_id Sanitized queryId.
	 * @param array  $values   New values; empty clears.
	 * @param bool   $multi    Whether to write the scoped key.
	 * @param bool   $is_array Write `name[]` entries.
	 */
	function designsetgo_query_write_owned( array &$args, $bare, $query_id, array $values, $multi, $is_array ) {
		$scoped = $bare . '__' . $query_id;
		$clean  = array_values(
			array_filter(
				array_map( 'strval', $values ),
				static function ( $v ) {
					return '' !== $v;
				}
			)
		);

		if ( $multi && '' !== $query_id ) {
			unset( $args[ $scoped ] );
			if ( $clean ) {
				$args[ $scoped ] = $is_array ? $clean : $clean[0];
			} elseif ( isset( $args[ $bare ] ) ) {
				$args[ $scoped ] = $is_array ? array( '' ) : '';
			}
			return;
		}
		unset( $args[ $bare ] );
		if ( '' !== $query_id ) {
			unset( $args[ $scoped ] );
		}
		if ( $clean ) {
			$args[ $bare ] = $is_array ? $clean : $clean[0];
		}
	}

endif;

if ( ! function_exists( 'designsetgo_query_reset_own_page' ) ) :

	/**
	 * Send one Query back to its first page in parsed URL args.
	 *
	 * Mirrors resetOwnPage() in url-scope.js.
	 *
	 * @param array  $args     Parsed query args, by reference.
	 * @param string $query_id Sanitized queryId.
	 * @param bool   $multi    Whether the page holds several Queries.
	 */
	function designsetgo_query_reset_own_page( array &$args, $query_id, $multi ) {
		$own = 'qpage__' . $query_id;
		unset( $args[ $own ] );
		if ( ! $multi || '' === $query_id ) {
			unset( $args['paged'], $args['page'] );
			return;
		}
		// A shared `paged` pages every Query; leave it for the others and
		// pin this one to page 1.
		if ( isset( $args['paged'] ) || isset( $args['page'] ) || absint( get_query_var( 'paged' ) ) > 1 ) {
			$args[ $own ] = '1';
		}
	}

endif;

if ( ! function_exists( 'designsetgo_query_current_url_args' ) ) :

	/**
	 * The current URL split into its path and parsed query args.
	 *
	 * @return array{0:string,1:array} Base URL without the query, and the args.
	 */
	function designsetgo_query_current_url_args() {
		$current_url = add_query_arg( array() );
		parse_str( (string) wp_parse_url( $current_url, PHP_URL_QUERY ), $args );
		return array( (string) strtok( $current_url, '?' ), $args );
	}

endif;

if ( ! function_exists( 'designsetgo_query_build_url' ) ) :

	/**
	 * Rebuild a URL from a base and parsed args.
	 *
	 * Uses http_build_query(), which keeps nested arrays (foo[bar]=baz) intact; the
	 * numeric brackets it writes for our own list params (filter_x[0]=a)
	 * are normalized back to filter_x[]=a.
	 *
	 * @param string $base URL without the query string.
	 * @param array  $args Parsed query args.
	 * @return string Unescaped URL.
	 */
	function designsetgo_query_build_url( $base, array $args ) {
		$qs = preg_replace_callback(
			'/(^|&)((?:filter_[a-z0-9_-]+|q|sort))%5B\d+%5D=/i',
			static function ( $m ) {
				return $m[1] . $m[2] . '%5B%5D=';
			},
			http_build_query( $args )
		);
		return $qs ? $base . '?' . $qs : $base;
	}

endif;

if ( ! function_exists( 'designsetgo_query_filter_render_active' ) ) :

	/**
	 * Render the active-filters chip strip.
	 *
	 * One chip per value this Query is filtering by: its own scoped values,
	 * and bare values it inherits (a bare key filters every Query) unless a
	 * scoped key overrides them. Each chip links to the current URL with that
	 * value removed for this Query only, as a no-JS fallback; with JS,
	 * view.js recomputes the removal from the live URL.
	 *
	 * @param string $wrapper  Pre-computed wrapper attributes string.
	 * @param string $label    Optional visible label.
	 * @param string $query_id Sanitized queryId this filter belongs to.
	 */
	function designsetgo_query_filter_render_active( $wrapper, $label, $query_id = '' ) {
		$active = array();
		foreach ( designsetgo_query_owned_request_params( $query_id ) as $bare => $entry ) {
			$bare = (string) $bare;
			if ( 0 !== strpos( $bare, 'filter_' ) && 'q' !== $bare && 'sort' !== $bare ) {
				continue;
			}
			foreach ( (array) wp_unslash( $entry['value'] ) as $val ) {
				if ( is_array( $val ) ) {
					continue;
				}
				$val = sanitize_text_field( (string) $val );
				if ( '' !== $val ) {
					$active[] = array(
						'key'      => $entry['key'],
						'bare'     => $bare,
						'value'    => $val,
						'is_array' => is_array( $entry['value'] ),
					);
				}
			}
		}

		if ( empty( $active ) ) {
			return;
		}

		list( $base, $args ) = designsetgo_query_current_url_args();
		$multi               = designsetgo_query_page_is_multi();
		$chips_html          = '';

		foreach ( $active as $p ) {
			$next    = $args;
			$current = isset( $next[ $p['key'] ] ) ? (array) $next[ $p['key'] ] : array();
			designsetgo_query_write_owned(
				$next,
				$p['bare'],
				$query_id,
				array_diff( array_map( 'strval', $current ), array( $p['value'] ) ),
				$multi,
				$p['is_array']
			);
			designsetgo_query_reset_own_page( $next, $query_id, $multi );

			// A human dimension label from the bare key: "filter_post_tag" → "post tag".
			$dimension   = 'q' === $p['bare']
				? __( 'search', 'designsetgo' )
				: str_replace( array( 'filter_', '_' ), array( '', ' ' ), $p['bare'] );
			$chips_html .= sprintf(
				'<a href="%1$s" role="button" class="dsgo-query-filter__chip" data-wp-on--click="actions.removeActiveFilter" data-dsgo-filter-key="%2$s" data-dsgo-filter-param="%3$s" data-dsgo-filter-value="%4$s">%5$s<span aria-hidden="true"> &times;</span><span class="screen-reader-text">%6$s</span></a>',
				esc_url( designsetgo_query_build_url( $base, $next ) ),
				esc_attr( $p['key'] ),
				esc_attr( $p['bare'] ),
				esc_attr( $p['value'] ),
				esc_html( $p['value'] ),
				esc_html(
					sprintf(
						/* translators: 1: filter dimension name (e.g. "category"), 2: filter value (e.g. "photography") */
						__( 'Remove %1$s: %2$s', 'designsetgo' ),
						$dimension,
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
	 * The href clears every filter / search / sort param this Query reads,
	 * and its page, while leaving other Queries' keys alone — a no-JS
	 * fallback; with JS, view.js recomputes it from the live URL.
	 *
	 * @param string $wrapper  Pre-computed wrapper attributes string.
	 * @param string $label    Optional button text (default "Reset filters").
	 * @param string $query_id Sanitized queryId this filter belongs to.
	 */
	function designsetgo_query_filter_render_reset( $wrapper, $label, $query_id = '' ) {
		list( $base, $args ) = designsetgo_query_current_url_args();
		$multi               = designsetgo_query_page_is_multi();

		$bares = array();
		foreach ( $args as $key => $value ) {
			list( $bare, $scope ) = designsetgo_query_split_param_key( sanitize_key( (string) $key ), $query_id );
			if ( ( '' === $scope || $scope === $query_id )
				&& ( 0 === strpos( $bare, 'filter_' ) || 'q' === $bare || 'sort' === $bare ) ) {
				$bares[ $bare ] = ! empty( $bares[ $bare ] ) || is_array( $value );
			}
		}
		foreach ( $bares as $bare => $is_array ) {
			designsetgo_query_write_owned( $args, (string) $bare, $query_id, array(), $multi, $is_array );
		}
		designsetgo_query_reset_own_page( $args, $query_id, $multi );

		printf(
			'<div %1$s><a href="%2$s" role="button" class="dsgo-query-filter__reset" data-wp-on--click="actions.resetAll">%3$s</a></div>',
			$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_url( designsetgo_query_build_url( $base, $args ) ),
			esc_html( $label ? $label : __( 'Reset filters', 'designsetgo' ) )
		);
	}

endif;
