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

	public function test_register_hooks_the_core_filter() {
		$fresh = new \DesignSetGo\Block_Bindings_Support();
		$fresh->register();

		$this->assertNotFalse(
			has_filter( 'block_bindings_supported_attributes', array( $fresh, 'filter_supported_attributes' ) ),
			'Block_Bindings_Support::register() must hook into block_bindings_supported_attributes.'
		);
	}
}
