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
 *  - HTML-sourced text on static blocks: `heading-segment.content`,
 *    `accordion-item.title`, `modal-trigger.text` and `icon-button.text`.
 *    Core applies a bound value to the markup by matching the attribute's
 *    selector as a tag name, and these selectors are class names, so
 *    apply_class_selector_bindings() finishes the job. Only elements that
 *    save() always renders qualify: a block's stored markup holds no value
 *    for a bound attribute, so an element that renders only when its text is
 *    non-empty (Card title, Timeline Item title, Counter label) would never
 *    exist to be filled.
 *  - `designsetgo/breadcrumbs` + `designsetgo/query-pagination` — dynamic
 *    (server-rendered) blocks where bound values flow into `render_callback`
 *    via `$block->attributes` without any further plumbing.
 *  - `designsetgo/star-rating` — same mechanism, and the reason that block
 *    is dynamic at all: `rating` is meant to come from post meta, ACF, or
 *    `designsetgo/woo-average-rating`, not from a number typed once.
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
		'designsetgo/accordion-item'   => array( 'title' ),
		'designsetgo/modal-trigger'    => array( 'text' ),
		'designsetgo/icon-button'      => array( 'text' ),
		'designsetgo/breadcrumbs'      => array( 'homeText', 'prefixText' ),
		'designsetgo/query-pagination' => array( 'labelLoadMore', 'labelLoading', 'buttonLabelWhenPaused' ),
		'designsetgo/star-rating'      => array( 'rating', 'ratingCount' ),
	);

	/**
	 * Bound values waiting for their block's render_block pass, keyed by
	 * spl_object_id() of the WP_Block, then by attribute name.
	 *
	 * @var array<int, array<string, array{0: string, 1: string}>>
	 */
	private $pending = array();

	/**
	 * Register the filter hooks.
	 */
	public function register() {
		add_filter(
			'block_bindings_supported_attributes',
			array( $this, 'filter_supported_attributes' ),
			10,
			2
		);

		// Last, so the captured value is the one core merges into the block.
		add_filter( 'block_bindings_source_value', array( $this, 'capture_bound_value' ), PHP_INT_MAX, 5 );

		// Early, so later render_block filters see the bound markup.
		add_filter( 'render_block', array( $this, 'apply_class_selector_bindings' ), 5, 3 );
	}

	/**
	 * Remember a bound value that core will not be able to place in the markup.
	 *
	 * @param mixed     $value          Value the source resolved.
	 * @param string    $source_name    Bindings source name.
	 * @param array     $source_args    Source arguments.
	 * @param \WP_Block $block_instance Block being rendered.
	 * @param string    $attribute_name Bound attribute.
	 * @return mixed The value, unchanged.
	 */
	public function capture_bound_value( $value, $source_name, $source_args, $block_instance, $attribute_name ) {
		if ( ! is_scalar( $value ) || ! $block_instance instanceof \WP_Block ) {
			return $value;
		}

		$attribute = isset( $block_instance->block_type->attributes[ $attribute_name ] )
			? $block_instance->block_type->attributes[ $attribute_name ]
			: array();
		$selector  = isset( $attribute['selector'] ) ? $attribute['selector'] : '';
		$source    = isset( $attribute['source'] ) ? $attribute['source'] : '';

		if (
			0 !== strpos( $block_instance->name, 'designsetgo/' ) ||
			! in_array( $source, array( 'html', 'rich-text', 'text' ), true ) ||
			! preg_match( '/^\.([A-Za-z0-9_-]+)$/', $selector, $class )
		) {
			return $value;
		}

		$this->pending[ spl_object_id( $block_instance ) ][ $attribute_name ] = array(
			$class[1],
			'text' === $source ? esc_html( (string) $value ) : wp_kses_post( (string) $value ),
		);

		return $value;
	}

	/**
	 * Write captured bound values into the elements their selectors name.
	 *
	 * @param string    $block_content Rendered block markup.
	 * @param array     $parsed_block  Parsed block.
	 * @param \WP_Block $instance      Block instance.
	 * @return string Markup with bound values applied.
	 */
	public function apply_class_selector_bindings( $block_content, $parsed_block, $instance = null ) {
		if ( ! $instance instanceof \WP_Block ) {
			return $block_content;
		}

		$id = spl_object_id( $instance );
		if ( empty( $this->pending[ $id ] ) ) {
			return $block_content;
		}

		$values = $this->pending[ $id ];
		unset( $this->pending[ $id ] );

		if ( ! is_string( $block_content ) || '' === $block_content ) {
			return $block_content;
		}

		foreach ( $values as $value ) {
			$block_content = Bindings_HTML_Processor::replace_inner_html_by_class( $block_content, $value[0], $value[1] );
		}

		return $block_content;
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
