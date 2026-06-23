<?php
/**
 * Native Block Bindings support for DesignSetGo blocks.
 *
 * WordPress 6.9 added the `block_bindings_supported_attributes` filter that
 * lets non-core blocks opt their attributes into the native Block Bindings
 * API (so authors can drive block values from post meta / ACF / custom
 * sources via the editor's Connections panel). This class declares the
 * DesignSetGo attributes that are safe to bind.
 *
 * Scope today:
 *  - `designsetgo/heading-segment.content` — attribute is sourced from HTML,
 *    so core's HTML API rewrites the rendered markup automatically.
 *  - `designsetgo/breadcrumbs` + `designsetgo/query-pagination` — dynamic
 *    (server-rendered) blocks where bound values flow into `render_callback`
 *    via `$block->attributes` without any further plumbing.
 *
 * On WordPress < 6.9 the filter is inert — `add_filter()` simply registers a
 * callback the core never invokes, so this file is safe to ship regardless.
 *
 * @package DesignSetGo
 * @since   2.1.1
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/**
 * Opts DesignSetGo block attributes into the native Block Bindings API.
 */
class Block_Bindings_Support {

	/**
	 * Default map of block name → supported attribute names.
	 *
	 * Attributes here MUST be renderable from `$block->attributes` at
	 * render time — either via an HTML-sourced attribute selector or via a
	 * `render_callback` that reads the attribute. Attributes that only live
	 * in comment delimiters on a static block will NOT bind correctly.
	 *
	 * @var array<string, string[]>
	 */
	private const DEFAULT_SUPPORTED_ATTRIBUTES = array(
		'designsetgo/heading-segment'  => array( 'content' ),
		'designsetgo/breadcrumbs'      => array( 'homeText', 'prefixText' ),
		'designsetgo/query-pagination' => array( 'labelLoadMore', 'labelLoading', 'buttonLabelWhenPaused' ),
	);

	/**
	 * Register the filter hook.
	 */
	public function register() {
		add_filter(
			'block_bindings_supported_attributes',
			array( $this, 'filter_supported_attributes' ),
			10,
			2
		);
	}

	/**
	 * Append DesignSetGo bindable attributes to the core supported list.
	 *
	 * @param string[] $supported_attributes Attributes already registered as bindable for the block.
	 * @param string   $block_type           Block type being filtered.
	 * @return string[] Potentially extended list of bindable attribute names.
	 */
	public function filter_supported_attributes( $supported_attributes, $block_type ) {
		if ( ! is_array( $supported_attributes ) ) {
			$supported_attributes = array();
		}

		$map = $this->get_supported_map();
		// empty() covers both "not in map" and "explicitly cleared to []" by a filter.
		if ( empty( $map[ $block_type ] ) ) {
			return $supported_attributes;
		}

		return array_values( array_unique( array_merge( $supported_attributes, $map[ $block_type ] ) ) );
	}

	/**
	 * Resolve the full (block_name → attribute_names[]) map.
	 *
	 * Third parties can extend the list with:
	 *
	 *     add_filter(
	 *         'designsetgo_block_bindings_supported_attributes',
	 *         function ( $map ) {
	 *             $map['designsetgo/my-block'][] = 'myAttr';
	 *             return $map;
	 *         }
	 *     );
	 *
	 * @return array<string, string[]>
	 */
	private function get_supported_map() {
		/**
		 * Filter the DesignSetGo block bindings supported attributes map.
		 *
		 * @param array<string, string[]> $map Block name → list of bindable attribute names.
		 */
		return (array) apply_filters(
			'designsetgo_block_bindings_supported_attributes',
			self::DEFAULT_SUPPORTED_ATTRIBUTES
		);
	}
}
