<?php
/**
 * Tests for the native Block Bindings opt-in layer.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

/**
 * Verifies that DesignSetGo blocks opt into the WordPress native Block
 * Bindings API via the `block_bindings_supported_attributes` filter.
 */
class Test_Block_Bindings_Support extends WP_UnitTestCase {

	/**
	 * Instance under test.
	 *
	 * @var \DesignSetGo\Block_Bindings_Support
	 */
	private $support;

	public function set_up() {
		parent::set_up();
		$this->support = new \DesignSetGo\Block_Bindings_Support();
	}

	public function test_heading_segment_content_is_bindable() {
		$result = $this->support->filter_supported_attributes( array(), 'designsetgo/heading-segment' );
		$this->assertContains( 'content', $result );
	}

	public function test_breadcrumbs_text_attributes_are_bindable() {
		$result = $this->support->filter_supported_attributes( array(), 'designsetgo/breadcrumbs' );
		$this->assertContains( 'homeText', $result );
		$this->assertContains( 'prefixText', $result );
	}

	public function test_query_pagination_labels_are_bindable() {
		$result = $this->support->filter_supported_attributes( array(), 'designsetgo/query-pagination' );
		$this->assertContains( 'labelLoadMore', $result );
		$this->assertContains( 'labelLoading', $result );
		$this->assertContains( 'buttonLabelWhenPaused', $result );
	}

	public function test_unrelated_block_is_untouched() {
		$result = $this->support->filter_supported_attributes( array( 'content' ), 'core/paragraph' );
		$this->assertSame( array( 'content' ), $result );
	}

	public function test_preserves_core_supported_attributes() {
		$result = $this->support->filter_supported_attributes(
			array( 'content' ),
			'designsetgo/heading-segment'
		);
		$this->assertContains( 'content', $result );
		$this->assertCount( 1, $result, 'Duplicate attribute names should be merged.' );
	}

	public function test_non_array_supported_attributes_is_coerced() {
		$result = $this->support->filter_supported_attributes( null, 'designsetgo/heading-segment' );
		$this->assertIsArray( $result );
		$this->assertContains( 'content', $result );
	}

	public function test_map_is_filterable() {
		$filter = static function ( $map ) {
			$map['designsetgo/test-ext'] = array( 'customAttr' );
			return $map;
		};
		add_filter( 'designsetgo_block_bindings_supported_attributes', $filter );
		try {
			$result = $this->support->filter_supported_attributes( array(), 'designsetgo/test-ext' );
			$this->assertContains( 'customAttr', $result );
		} finally {
			remove_filter( 'designsetgo_block_bindings_supported_attributes', $filter );
		}
	}

	/**
	 * Render block markup whose `$attribute` is bound to a test source.
	 *
	 * @param string $block_name Block name.
	 * @param string $attribute  Bound attribute.
	 * @param string $inner_html Stored block markup.
	 * @param mixed  $value      Value the source returns.
	 * @return string Rendered markup.
	 */
	private function render_bound( $block_name, $attribute, $inner_html, $value ) {
		if ( ! function_exists( 'get_block_bindings_supported_attributes' ) ) {
			$this->markTestSkipped( 'Block bindings for non-core blocks need WordPress 6.9.' );
		}

		register_block_bindings_source(
			'dsgo-test/value',
			array(
				'label'              => 'Test value',
				'get_value_callback' => static function () use ( $value ) {
					return $value;
				},
			)
		);

		$comment = wp_json_encode(
			array(
				'metadata' => array(
					'bindings' => array(
						$attribute => array( 'source' => 'dsgo-test/value' ),
					),
				),
			)
		);

		try {
			return do_blocks( "<!-- wp:{$block_name} {$comment} -->{$inner_html}<!-- /wp:{$block_name} -->" );
		} finally {
			unregister_block_bindings_source( 'dsgo-test/value' );
		}
	}

	public function test_bound_accordion_title_replaces_the_stored_title() {
		$html = $this->render_bound(
			'designsetgo/accordion-item',
			'title',
			'<div class="wp-block-designsetgo-accordion-item dsgo-accordion-item"><div class="dsgo-accordion-item__header"><button type="button" class="dsgo-accordion-item__trigger"><span class="dsgo-accordion-item__title">Stored <strong>title</strong></span></button></div><div class="dsgo-accordion-item__panel" hidden><div class="dsgo-accordion-item__content"><p>Body</p></div></div></div>',
			'Bound <em>question</em><script>alert(1)</script>'
		);

		$this->assertStringContainsString( '<span class="dsgo-accordion-item__title">Bound <em>question</em>alert(1)</span>', $html );
		$this->assertStringNotContainsString( 'Stored', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '<p>Body</p>', $html, 'Only the title element changes.' );
	}

	public function test_bound_icon_button_text_replaces_the_stored_text() {
		$html = $this->render_bound(
			'designsetgo/icon-button',
			'text',
			'<div class="wp-block-designsetgo-icon-button"><a class="dsgo-icon-button" href="#"><span class="dsgo-icon-button__text">Buy now</span></a></div>',
			'Only 3 left'
		);

		$this->assertStringContainsString( '<span class="dsgo-icon-button__text">Only 3 left</span>', $html );
	}

	public function test_a_null_bound_value_keeps_the_stored_markup() {
		$html = $this->render_bound(
			'designsetgo/modal-trigger',
			'text',
			'<div class="wp-block-designsetgo-modal-trigger"><button type="button" class="dsgo-modal-trigger__button"><span class="dsgo-modal-trigger__text">Open</span></button></div>',
			null
		);

		$this->assertStringContainsString( '<span class="dsgo-modal-trigger__text">Open</span>', $html );
	}

	public function test_widened_blocks_are_bindable() {
		$this->assertContains( 'title', $this->support->filter_supported_attributes( array(), 'designsetgo/accordion-item' ) );
		$this->assertContains( 'text', $this->support->filter_supported_attributes( array(), 'designsetgo/modal-trigger' ) );
		$this->assertContains( 'text', $this->support->filter_supported_attributes( array(), 'designsetgo/icon-button' ) );
	}

	/**
	 * Every bindable attribute on a static block must be one this class can
	 * place: HTML-sourced, with a single-class selector.
	 */
	public function test_every_static_bindable_attribute_has_a_placeable_selector() {
		$registry = WP_Block_Type_Registry::get_instance();
		$map      = ( new ReflectionClassConstant( \DesignSetGo\Block_Bindings_Support::class, 'DEFAULT_SUPPORTED_ATTRIBUTES' ) )->getValue();

		foreach ( $map as $block_name => $attributes ) {
			$type = $registry->get_registered( $block_name );
			$this->assertNotNull( $type, "{$block_name} is not registered." );

			if ( $type->is_dynamic() ) {
				continue;
			}

			foreach ( $attributes as $attribute ) {
				$definition = $type->attributes[ $attribute ];
				$this->assertContains( $definition['source'] ?? '', array( 'html', 'rich-text', 'text' ), "{$block_name}.{$attribute} must be HTML-sourced." );
				$this->assertMatchesRegularExpression( '/^\.[A-Za-z0-9_-]+$/', $definition['selector'] ?? '', "{$block_name}.{$attribute} needs a single-class selector." );
			}
		}
	}

	public function test_register_hooks_the_core_filter() {
		$fresh = new \DesignSetGo\Block_Bindings_Support();
		$fresh->register();

		try {
			$this->assertNotFalse(
				has_filter( 'block_bindings_supported_attributes', array( $fresh, 'filter_supported_attributes' ) ),
				'Block_Bindings_Support::register() must hook into block_bindings_supported_attributes.'
			);
		} finally {
			remove_filter(
				'block_bindings_supported_attributes',
				array( $fresh, 'filter_supported_attributes' ),
				10
			);
		}
	}
}
