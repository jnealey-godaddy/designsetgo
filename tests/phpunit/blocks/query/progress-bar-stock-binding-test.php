<?php
/**
 * A bound --dsgo-progress custom property lands on a progress-bar rendered
 * per-item inside a designsetgo/query loop.
 *
 * This is the "stock bar" recipe documented in .claude/CLAUDE.md's WooCommerce
 * section: progress-bar + `--dsgo-progress` bound to
 * `designsetgo/woo-stock-quantity`.
 *
 * Exercises StyleBinding::apply_style_bindings() directly against
 * `$GLOBALS['designsetgo_parent_stack']` set to exactly the shape
 * designsetgo_query_render_item() (src/blocks/query/render-helpers.php,
 * every query host's per-item entry point) pushes for a Posts-source item —
 * a `postId`-keyed array — rather than routing through a real
 * `designsetgo/query` render. DesignSetGo_Woo_Bindings_Test's
 * test_stock_quantity_drives_a_style_binding() establishes this as the
 * project's pattern for pinning the binding mechanics; this test applies it
 * to the actual progress-bar markup shape (save.js's `clamp()`/`calc()` fill
 * width, not a bare placeholder div) and additionally proves per-item
 * isolation across a simulated loop of more than one item.
 *
 * @package DesignSetGo
 */

/**
 * Progress Bar stock-binding test case.
 *
 * @group woocommerce
 * @group query-block
 */
class DesignSetGo_Progress_Bar_Stock_Binding_Test extends WP_UnitTestCase {

	/**
	 * Skips the whole class when WooCommerce is not installed.
	 */
	public function set_up() {
		parent::set_up();
		DesignSetGo_Woo_Product_Factory::skip_if_unavailable( $this );
	}

	/**
	 * Tears down any parent-stack leftovers from a failed assertion so later
	 * tests in the suite never inherit stale query-loop context.
	 */
	public function tear_down() {
		unset( $GLOBALS['designsetgo_parent_stack'] );
		parent::tear_down();
	}

	/**
	 * A progress-bar's parsed block array, with a dsgoStyleBinding on
	 * `--dsgo-progress` bound to `designsetgo/woo-stock-quantity` and inner
	 * markup shaped like save.js's actual output (src/blocks/progress-bar/save.js):
	 * a `clamp(0%, calc(100% * var(--dsgo-progress, 75) / max(1, var(--dsgo-progress-max, 100))), 100%)`
	 * fill width, ready for the binding to add a custom property alongside it.
	 *
	 * @return array Block array shaped like render_block's $block argument.
	 */
	private function progress_bar_block() {
		return array(
			'blockName'   => 'designsetgo/progress-bar',
			'attrs'       => array(
				'dsgoStyleBinding' => array(
					'--dsgo-progress' => array(
						'source' => 'designsetgo/woo-stock-quantity',
						'args'   => array(),
					),
				),
			),
			'innerBlocks' => array(),
			'innerHTML'   => '',
		);
	}

	/**
	 * The saved fill markup a progress bar with default attributes emits,
	 * mirroring save.js's STATIC_WIDTH_FORMULA exactly (percentage: 75).
	 *
	 * @return string
	 */
	private function progress_bar_html() {
		return '<div class="wp-block-designsetgo-progress-bar dsgo-progress-bar">'
			. '<div class="dsgo-progress-bar__container" style="width:100%;height:20px;border-radius:4px;overflow:hidden;position:relative">'
			. '<div class="dsgo-progress-bar__fill" style="width:clamp(0%, calc(100% * var(--dsgo-progress, 75) / max(1, var(--dsgo-progress-max, 100))), 100%);height:100%;transition:width 1.5s ease-out;border-radius:4px"></div>'
			. '</div></div>';
	}

	/**
	 * The bound stock quantity lands as a `--dsgo-progress` custom property
	 * on the progress-bar's root element, alongside the existing static
	 * width formula (the binding adds a property; it does not rewrite the
	 * `width` declaration save.js already wrote).
	 */
	public function test_stock_binding_resolves_inside_a_query_item_context() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple(
			array( 'stock_quantity' => 12 )
		);

		// Exactly what designsetgo_query_render_item() pushes for a Posts-source
		// item (see render-posts.php: array('postId' => $post_id, 'postType' =>
		// get_post_type(), ...)) — StyleBinding::current_post_id() reads the
		// 'postId' key off the top of this stack.
		$GLOBALS['designsetgo_parent_stack'] = array(
			array(
				'postId'   => $product_id,
				'postType' => 'product',
			),
		);

		$style_binding = new \DesignSetGo\StyleBinding();
		$html          = $style_binding->apply_style_bindings(
			$this->progress_bar_html(),
			$this->progress_bar_block()
		);

		unset( $GLOBALS['designsetgo_parent_stack'] );

		$this->assertStringContainsString( '--dsgo-progress:12', $html );
		$this->assertStringContainsString(
			'width:clamp(0%, calc(100% * var(--dsgo-progress, 75) / max(1, var(--dsgo-progress-max, 100))), 100%)',
			$html
		);
	}

	/**
	 * A product with no managed stock resolves to null, not zero — the
	 * binding must add nothing rather than claim an empty bar.
	 */
	public function test_unmanaged_stock_adds_no_custom_property() {
		$product_id = DesignSetGo_Woo_Product_Factory::create_simple();

		$GLOBALS['designsetgo_parent_stack'] = array(
			array(
				'postId'   => $product_id,
				'postType' => 'product',
			),
		);

		$style_binding = new \DesignSetGo\StyleBinding();
		$html          = $style_binding->apply_style_bindings(
			$this->progress_bar_html(),
			$this->progress_bar_block()
		);

		unset( $GLOBALS['designsetgo_parent_stack'] );

		$this->assertStringNotContainsString( '--dsgo-progress:', $html );
	}

	/**
	 * Two items rendered in sequence resolve independently — the same
	 * push-then-pop discipline designsetgo_query_render_item() applies to
	 * $GLOBALS['designsetgo_parent_stack'] around each item's render() call,
	 * so one item's bound value can never leak into the next.
	 */
	public function test_each_item_in_the_loop_resolves_its_own_stock() {
		$first_id  = DesignSetGo_Woo_Product_Factory::create_simple( array( 'stock_quantity' => 3 ) );
		$second_id = DesignSetGo_Woo_Product_Factory::create_simple( array( 'stock_quantity' => 40 ) );

		$style_binding = new \DesignSetGo\StyleBinding();
		$block         = $this->progress_bar_block();
		$html          = $this->progress_bar_html();

		$GLOBALS['designsetgo_parent_stack'] = array();

		array_push(
			$GLOBALS['designsetgo_parent_stack'],
			array(
				'postId'   => $first_id,
				'postType' => 'product',
			) 
		);
		$first_html = $style_binding->apply_style_bindings( $html, $block );
		array_pop( $GLOBALS['designsetgo_parent_stack'] );

		array_push(
			$GLOBALS['designsetgo_parent_stack'],
			array(
				'postId'   => $second_id,
				'postType' => 'product',
			) 
		);
		$second_html = $style_binding->apply_style_bindings( $html, $block );
		array_pop( $GLOBALS['designsetgo_parent_stack'] );

		unset( $GLOBALS['designsetgo_parent_stack'] );

		$this->assertStringContainsString( '--dsgo-progress:3', $first_html );
		$this->assertStringNotContainsString( '--dsgo-progress:40', $first_html );

		$this->assertStringContainsString( '--dsgo-progress:40', $second_html );
		$this->assertStringNotContainsString( '--dsgo-progress:3', $second_html );
	}
}
