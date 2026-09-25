<?php
/**
 * Tests for the frontend icon payload.
 *
 * The injector used to localize all ~340 icons on every page with an icon
 * block. It now sends only the icons rendered markup names, and serves the
 * rest from a public REST route for content that arrives after first paint.
 *
 * @group icons
 */

/**
 * @group icons
 */
class DesignSetGo_Icon_Injector_Test extends WP_UnitTestCase {

	/**
	 * Fresh injector per test so the collected names don't leak.
	 *
	 * @var \DesignSetGo\Icon_Injector
	 */
	private $injector;

	/**
	 * Script registry in place before the test.
	 *
	 * @var WP_Scripts|null
	 */
	private $original_scripts;

	public function set_up() {
		parent::set_up();
		// A fresh registry, so enqueues and inline data don't leak between tests.
		$this->original_scripts = $GLOBALS['wp_scripts'] ?? null;
		$GLOBALS['wp_scripts']  = new WP_Scripts();
		$this->injector         = new \DesignSetGo\Icon_Injector();
		wp_register_script( 'designsetgo-icon-injector', 'https://example.com/injector.js', array(), '1', true );
	}

	public function tear_down() {
		$GLOBALS['wp_scripts'] = $this->original_scripts;
		parent::tear_down();
	}

	/**
	 * Run the render_block hook and return the printed icon map.
	 *
	 * @param array[] $blocks List of [ blockName, html ].
	 * @return array Icon name => SVG from the inline `dsgoIcons` script.
	 */
	private function render_and_print( array $blocks ) {
		foreach ( $blocks as $block ) {
			$this->injector->maybe_enqueue_on_render( $block[1], array( 'blockName' => $block[0] ) );
		}
		$this->injector->print_icon_data();

		$inline = wp_scripts()->get_inline_script_data( 'designsetgo-icon-injector', 'before' );
		// WordPress may append a `//# sourceURL=` line after the statement.
		$this->assertMatchesRegularExpression( '/^var dsgoIcons = (\{.*\});$/m', $inline );
		preg_match( '/^var dsgoIcons = (\{.*\});$/m', $inline, $match );

		return json_decode( $match[1], true );
	}

	public function test_prints_only_icons_named_in_rendered_markup() {
		$icons = $this->render_and_print(
			array(
				array( 'designsetgo/icon-button', '<a class="x"><span class="dsgo-lazy-icon" data-icon-name="arrow-right"></span></a>' ),
				array( 'designsetgo/tab', '<div class="wp-block-designsetgo-tab" data-icon="check"></div>' ),
				array( 'designsetgo/icon-list-item', '<li><span class="dsgo-lazy-icon" data-icon-name=" Arrow-Right "></span></li>' ),
			)
		);

		$this->assertSame( array( 'arrow-right', 'check' ), array_keys( $icons ) );
		$this->assertSame( designsetgo_get_icon_svg( 'arrow-right' ), $icons['arrow-right'] );
		$this->assertLessThan( 20, count( $icons ), 'Must not ship the whole library.' );
	}

	public function test_ignores_icon_attributes_outside_icon_blocks() {
		$icons = $this->render_and_print(
			array(
				array( 'designsetgo/icon-button', '<a><span class="dsgo-lazy-icon" data-icon-name="star"></span></a>' ),
				array( 'core/paragraph', '<p data-icon-name="heart">Hi</p>' ),
			)
		);

		$this->assertSame( array( 'star' ), array_keys( $icons ) );
	}

	public function test_resolves_aliases_and_marks_unknown_names_empty() {
		$icons = $this->render_and_print(
			array(
				array( 'designsetgo/modal-trigger', '<button><span class="dsgo-lazy-icon" data-icon-name="zap"></span><span data-icon-name="not-an-icon"></span></button>' ),
			)
		);

		$this->assertSame( designsetgo_get_icon_svg( 'lightning' ), $icons['zap'] );
		$this->assertSame( '', $icons['not-an-icon'], 'Unknown names are sent as empty so the browser does not fetch them.' );
	}

	public function test_prints_nothing_when_injector_not_enqueued() {
		$this->injector->maybe_enqueue_on_render( '<p data-icon="star"></p>', array( 'blockName' => 'core/paragraph' ) );
		$this->injector->print_icon_data();

		$this->assertSame( '', (string) wp_scripts()->get_inline_script_data( 'designsetgo-icon-injector', 'before' ) );
	}

	public function test_rest_route_returns_requested_icons_only() {
		do_action( 'rest_api_init' );

		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/icons' );
		$request->set_param( 'names', 'check,zap,<script>,nope' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = (array) $response->get_data();
		// sanitize_text_field() drops the tag outright; nothing unsafe reaches a key.
		$this->assertSame( array( 'check', 'zap', 'nope' ), array_keys( $data ) );
		$this->assertStringContainsString( '<svg', $data['check'] );
		$this->assertSame( designsetgo_get_icon_svg( 'lightning' ), $data['zap'] );
		$this->assertSame( '', $data['nope'] );
		$this->assertStringContainsString( 'public', $response->get_headers()['Cache-Control'] ?? '' );
	}

	public function test_rest_route_caps_names_per_request() {
		do_action( 'rest_api_init' );

		$request = new WP_REST_Request( 'GET', '/designsetgo/v1/icons' );
		$request->set_param( 'names', implode( ',', array_map( static fn( $i ) => 'icon-' . $i, range( 1, 250 ) ) ) );
		$data = (array) rest_get_server()->dispatch( $request )->get_data();

		$this->assertCount( \DesignSetGo\Icon_Injector::MAX_REST_NAMES, $data );
	}
}
