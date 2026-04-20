<?php
/**
 * Tests for the infinite-scroll pagination variation render path.
 *
 * Verifies that when paginationKind='infinite' the render.php emits:
 *  - A sentinel div with data-wp-init="callbacks.initInfiniteObserver"
 *  - A fallback button with the `hidden` attribute
 *  - Correct data-dsgo-auto-pause-after and data-dsgo-sentinel-offset values
 *
 * @group query-block
 */
class DesignSetGo_Query_Pagination_Infinite_Render_Test extends WP_UnitTestCase {

	/**
	 * Render the pagination block with the given attributes using the same
	 * pipeline the block editor uses at first-paint.
	 *
	 * @param array $block_attrs Attributes to pass to the block render.
	 * @return string Rendered HTML.
	 */
	private function render_block( array $block_attrs ): string {
		// Register the block so do_blocks() can resolve it.
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( 'designsetgo/query-pagination' ) ) {
			register_block_type(
				DESIGNSETGO_PATH . 'build/blocks/query-pagination/block.json'
			);
		}

		// Seed the query state registry so the render helper can read it.
		$helpers = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists(
			$helpers,
			'Run `npm run build` before PHPUnit — render helpers are served from build/.'
		);
		require_once $helpers;

		// Seed a minimal query state (totalPages = 2) so the early-return guard
		// for non-infinite modes doesn't bail out. Infinite ignores totalPages.
		designsetgo_query_set_last_state(
			'inf-test',
			array(
				'totalPages' => 2,
				'totalItems' => 8,
			)
		);

		$attrs_json = wp_json_encode( $block_attrs );

		// Render via do_blocks so that $block->context is populated correctly.
		// We inject the queryId context manually by building a block comment.
		$block_markup = sprintf(
			'<!-- wp:designsetgo/query-pagination %s /-->',
			$attrs_json
		);

		// Use parse_blocks + render_block so context can be injected.
		$parsed = parse_blocks( $block_markup );
		if ( empty( $parsed ) || empty( $parsed[0] ) ) {
			return '';
		}

		// Inject context directly into a WP_Block instance.
		$block_obj = new \WP_Block(
			$parsed[0],
			array( 'designsetgo/queryId' => 'inf-test' )
		);

		return $block_obj->render();
	}

	/**
	 * Infinite variation renders the sentinel with data-wp-init callback.
	 */
	public function test_infinite_renders_sentinel_with_init_callback() {
		$html = $this->render_block(
			array( 'paginationKind' => 'infinite' )
		);

		$this->assertStringContainsString(
			'data-wp-init="callbacks.initInfiniteObserver"',
			$html,
			'Sentinel div must carry the IAPI init callback attribute.'
		);

		$this->assertStringContainsString(
			'dsgo-query-pagination__sentinel',
			$html,
			'Sentinel element must have the expected CSS class.'
		);
	}

	/**
	 * Infinite variation renders a fallback button with the hidden attribute.
	 */
	public function test_infinite_renders_hidden_fallback_button() {
		$html = $this->render_block(
			array( 'paginationKind' => 'infinite' )
		);

		$this->assertStringContainsString(
			'<button',
			$html,
			'Fallback button must be present in the infinite render output.'
		);

		$this->assertMatchesRegularExpression(
			'/<button[^>]+\bhidden\b/i',
			$html,
			'Fallback button must carry the `hidden` attribute so it is not visible by default.'
		);
	}

	/**
	 * Data attributes reflect the block attributes.
	 */
	public function test_infinite_data_attributes_reflect_block_attrs() {
		$html = $this->render_block(
			array(
				'paginationKind'   => 'infinite',
				'autoPauseAfter'   => 5,
				'sentinelOffsetPx' => 350,
			)
		);

		$this->assertStringContainsString(
			'data-dsgo-auto-pause-after="5"',
			$html,
			'data-dsgo-auto-pause-after must match the autoPauseAfter attribute.'
		);

		$this->assertStringContainsString(
			'data-dsgo-sentinel-offset="350"',
			$html,
			'data-dsgo-sentinel-offset must match the sentinelOffsetPx attribute.'
		);
	}

	/**
	 * Infinite variation uses the data-dsgo-pagination="infinite" marker.
	 */
	public function test_infinite_wrapper_carries_pagination_marker() {
		$html = $this->render_block(
			array( 'paginationKind' => 'infinite' )
		);

		$this->assertStringContainsString(
			'data-dsgo-pagination="infinite"',
			$html,
			'The wrapper must carry data-dsgo-pagination="infinite" for the JS observer to find it.'
		);
	}

	/**
	 * Button label attribute is respected in the render output.
	 */
	public function test_infinite_button_label_is_used() {
		$html = $this->render_block(
			array(
				'paginationKind'       => 'infinite',
				'buttonLabelWhenPaused' => 'Show more posts',
			)
		);

		$this->assertStringContainsString(
			'Show more posts',
			$html,
			'The buttonLabelWhenPaused attribute must appear in the rendered button text.'
		);
	}

	/**
	 * When the query has only one page of results, the pagination block should
	 * render NOTHING — no sentinel, no button, no wrapper. Regression guard for
	 * the single-page guard added in v2.2: without it, the IntersectionObserver
	 * would fire loadMore(), hit an empty page 2, and flicker briefly.
	 */
	public function test_infinite_renders_nothing_when_single_page() {
		// Prime the block registration + helpers first (render_block will try to
		// seed totalPages=2 by default, which we need to override after).
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( 'designsetgo/query-pagination' ) ) {
			register_block_type( DESIGNSETGO_PATH . 'build/blocks/query-pagination/block.json' );
		}
		require_once DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';

		// Override with a single-page state BEFORE invoking the renderer, and
		// do it via a direct block instantiation so render_block()'s default
		// seeding never runs.
		designsetgo_query_set_last_state(
			'inf-test',
			array(
				'totalPages' => 1,
				'totalItems' => 3,
			)
		);

		$block_markup = '<!-- wp:designsetgo/query-pagination ' . wp_json_encode(
			array( 'paginationKind' => 'infinite' )
		) . ' /-->';
		$parsed       = parse_blocks( $block_markup );
		$block_obj    = new \WP_Block(
			$parsed[0],
			array( 'designsetgo/queryId' => 'inf-test' )
		);
		$html         = $block_obj->render();

		$this->assertStringNotContainsString(
			'data-wp-init="callbacks.initInfiniteObserver"',
			$html,
			'Single-page result set must NOT emit the infinite sentinel — loadMore would flicker against an empty page 2.'
		);
		$this->assertStringNotContainsString(
			'dsgo-query-pagination__sentinel',
			$html,
			'Single-page result set must NOT emit the sentinel container.'
		);
		$this->assertStringNotContainsString(
			'data-dsgo-pagination="infinite"',
			$html,
			'Single-page result set must NOT emit the infinite wrapper marker.'
		);
	}
}
