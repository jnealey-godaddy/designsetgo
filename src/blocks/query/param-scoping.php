<?php
/**
 * Dynamic Query — URL params scoped per Query.
 *
 * With two Query blocks on a page, a bare `filter_category` in the URL can't
 * say which Query it is for. Filter controls therefore write
 * `{param}__{queryId}` when a page has more than one Query, and every reader
 * applies the same rules:
 *
 * - A key scoped for this Query wins over the same bare key.
 * - A key scoped for another Query is never read.
 * - A bare key (a menu link, a bookmark, a WooCommerce filter block) applies
 *   to every Query, as it always has.
 *
 * One set of helpers so the WP_Query args, the filter controls, the chips,
 * Reset, the no-JS forms and pagination can't disagree about that.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_query_scoping_enabled' ) ) :

	/**
	 * Whether filter controls write per-Query keys.
	 *
	 * Reading scoped keys is always on, so URLs written with scoping keep
	 * working if a site turns it off. Turning it off makes one filter drive
	 * every Query on the page again, as before scoping.
	 *
	 * @return bool
	 */
	function designsetgo_query_scoping_enabled() {
		/**
		 * Filters whether Dynamic Query filter controls write per-Query URL
		 * params (`filter_category__{queryId}`) on pages with several Queries.
		 *
		 * @since 2.8.3
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'designsetgo_query_scope_params', true );
	}

endif;

if ( ! function_exists( 'designsetgo_query_register_rendered_id' ) ) :

	/**
	 * Record a Query rendered in this request, so later helpers know it.
	 *
	 * @param string $query_id Sanitized queryId.
	 */
	function designsetgo_query_register_rendered_id( $query_id ) {
		if ( '' === $query_id ) {
			return;
		}
		if ( ! isset( $GLOBALS['designsetgo_query_rendered_ids'] ) || ! is_array( $GLOBALS['designsetgo_query_rendered_ids'] ) ) {
			$GLOBALS['designsetgo_query_rendered_ids'] = array();
		}
		$GLOBALS['designsetgo_query_rendered_ids'][ $query_id ] = true;
	}

endif;

if ( ! function_exists( 'designsetgo_query_ids_in_markup' ) ) :

	/**
	 * The queryIds of the Query blocks in serialized block markup.
	 *
	 * Reads each block comment's JSON rather than guessing at attribute
	 * order. The serializer escapes `--` inside attributes, so ` -->` can
	 * only end the comment.
	 *
	 * @param string $markup Serialized blocks.
	 * @return string[]
	 */
	function designsetgo_query_ids_in_markup( $markup ) {
		if ( false === strpos( $markup, 'wp:designsetgo/query ' ) ) {
			return array();
		}
		$ids = array();
		if ( preg_match_all( '#<!-- wp:designsetgo/query (\{.*?\}) /?-->#', $markup, $m ) ) {
			foreach ( $m[1] as $json ) {
				$attrs = json_decode( $json, true );
				if ( is_array( $attrs ) && ! empty( $attrs['queryId'] ) && is_string( $attrs['queryId'] ) ) {
					$ids[] = sanitize_key( $attrs['queryId'] );
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

endif;

if ( ! function_exists( 'designsetgo_query_known_ids' ) ) :

	/**
	 * Query ids known for this request: those in the current post's content
	 * (found up front, so the first Query already knows about later ones)
	 * plus any rendered so far (templates, patterns).
	 *
	 * @return string[]
	 */
	function designsetgo_query_known_ids() {
		$sources = array();
		$post    = get_post();
		if ( $post instanceof WP_Post ) {
			$sources[] = $post->post_content;
		}
		// Block themes: the resolved template (not its template parts).
		if ( isset( $GLOBALS['_wp_current_template_content'] ) && is_string( $GLOBALS['_wp_current_template_content'] ) ) {
			$sources[] = $GLOBALS['_wp_current_template_content'];
		}
		static $cache = array();
		$ids          = array();
		foreach ( $sources as $source ) {
			$hash = md5( $source );
			if ( ! isset( $cache[ $hash ] ) ) {
				$cache[ $hash ] = designsetgo_query_ids_in_markup( $source );
			}
			$ids = array_merge( $ids, $cache[ $hash ] );
		}
		if ( isset( $GLOBALS['designsetgo_query_rendered_ids'] ) && is_array( $GLOBALS['designsetgo_query_rendered_ids'] ) ) {
			$ids = array_merge( $ids, array_map( 'strval', array_keys( $GLOBALS['designsetgo_query_rendered_ids'] ) ) );
		}
		return array_values( array_unique( $ids ) );
	}

endif;

if ( ! function_exists( 'designsetgo_query_page_is_multi' ) ) :

	/**
	 * Whether this request renders more than one Query, so controls must
	 * write scoped keys. One Query keeps plain keys: `?q=` stays readable and
	 * site-search analytics (which look for `q`) keep working.
	 *
	 * @return bool
	 */
	function designsetgo_query_page_is_multi() {
		if ( ! designsetgo_query_scoping_enabled() ) {
			return false;
		}
		// A REST refresh renders one Query alone; the browser, which can see
		// the whole page, says whether it shares it with others.
		if ( ! empty( $GLOBALS['designsetgo_query_force_multi'] ) ) {
			return true;
		}
		return count( designsetgo_query_known_ids() ) > 1;
	}

endif;

if ( ! function_exists( 'designsetgo_query_split_param_key' ) ) :

	/**
	 * Split a URL key into its bare name and the Query it is scoped for.
	 *
	 * Only the text after the LAST `__` can be a Query id, and only when it
	 * is one: the caller's own id, an id known for this request, or the shape
	 * DSGo generates (`q` + 8 hex from the editor, `q-` + 10 hex from a
	 * template import). So a custom taxonomy such as `filter_my__tax` stays
	 * an ordinary bare key.
	 *
	 * @param string $key    Sanitized URL key.
	 * @param string $own_id The caller's queryId, if any.
	 * @return array{0:string,1:string} Bare name, and the Query id or ''.
	 */
	function designsetgo_query_split_param_key( $key, $own_id = '' ) {
		$pos = strrpos( $key, '__' );
		if ( false === $pos || 0 === $pos ) {
			return array( $key, '' );
		}
		$id = substr( $key, $pos + 2 );
		if ( '' === $id ) {
			return array( $key, '' );
		}
		if ( ( '' !== $own_id && $id === $own_id )
			|| preg_match( '/^q(?:[0-9a-f]{8}|-[0-9a-f]{10})$/', $id )
			|| in_array( $id, designsetgo_query_known_ids(), true ) ) {
			return array( substr( $key, 0, $pos ), $id );
		}
		return array( $key, '' );
	}

endif;

if ( ! function_exists( 'designsetgo_query_scoped_param_name' ) ) :

	/**
	 * The URL key a filter control for this Query writes.
	 *
	 * @param string $param    Bare param name.
	 * @param string $query_id Sanitized queryId.
	 * @return string `{param}__{queryId}`, or the bare name when scoping is off.
	 */
	function designsetgo_query_scoped_param_name( $param, $query_id ) {
		if ( '' === $query_id || ! designsetgo_query_scoping_enabled() ) {
			return $param;
		}
		return $param . '__' . $query_id;
	}

endif;

if ( ! function_exists( 'designsetgo_query_owned_request_params' ) ) :

	/**
	 * The $_GET entries that apply to one Query, keyed by bare name.
	 *
	 * A key scoped for this Query wins over the same bare key, whatever the
	 * order in $_GET. A key scoped for another Query is skipped. Values are
	 * exactly as in $_GET (slashed, unsanitized), so each caller unslashes
	 * and sanitizes as it needs.
	 *
	 * @param string $query_id Sanitized queryId.
	 * @return array<string, array{value: mixed, key: string, scoped: bool}>
	 */
	function designsetgo_query_owned_request_params( $query_id ) {
		$owned = array();
		foreach ( (array) $_GET as $raw_key => $raw_value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key = sanitize_key( (string) $raw_key );
			if ( '' === $key ) {
				continue;
			}
			list( $bare, $scope ) = designsetgo_query_split_param_key( $key, $query_id );
			if ( '' !== $scope && $scope !== $query_id ) {
				continue;
			}
			$scoped = '' !== $scope;
			// A scoped entry is never replaced; a bare one only by a scoped one.
			if ( isset( $owned[ $bare ] ) && ( $owned[ $bare ]['scoped'] || ! $scoped ) ) {
				continue;
			}
			$owned[ $bare ] = array(
				'value'  => $raw_value,
				'key'    => (string) $raw_key,
				'scoped' => $scoped,
			);
		}
		return $owned;
	}

endif;

if ( ! function_exists( 'designsetgo_query_page_param_name' ) ) :

	/**
	 * The URL key numbered pagination uses for this Query.
	 *
	 * With one Query on the page, WordPress's own `paged` (pretty `/page/2/`
	 * links). With several, `qpage__{queryId}`, so paging one Query doesn't
	 * page the others.
	 *
	 * @param string $query_id Sanitized queryId.
	 * @return string
	 */
	function designsetgo_query_page_param_name( $query_id ) {
		return designsetgo_query_page_is_multi() && '' !== $query_id ? 'qpage__' . $query_id : 'paged';
	}

endif;

if ( ! function_exists( 'designsetgo_query_current_page' ) ) :

	/**
	 * The current page for a Query: its own `qpage__{queryId}` when present,
	 * else WordPress's `paged` / `page` query vars.
	 *
	 * @param string $query_id Sanitized queryId.
	 * @return int
	 */
	function designsetgo_query_current_page( $query_id ) {
		if ( '' !== $query_id && isset( $_GET[ 'qpage__' . $query_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return max( 1, absint( wp_unslash( $_GET[ 'qpage__' . $query_id ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint().
		}
		$page = max( 1, absint( get_query_var( 'paged' ) ) );
		if ( 1 === $page ) {
			$page = max( 1, absint( get_query_var( 'page' ) ) );
		}
		return $page;
	}

endif;
