<?php
/**
 * Tests for safe Text Path SVG extraction.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Blocks\Text_Path\Controller;

/**
 * Tests the Text Path SVG extraction controller.
 */
class DesignSetGo_Text_Path_Controller_Test extends WP_UnitTestCase {

	/**
	 * Allows standard SVG metadata and grouping elements while extracting a path.
	 */
	public function test_parse_svg_path_returns_only_normalized_first_path_data_from_safe_svg_structure() {
		$result = Controller::parse_svg_path(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0, 0, 100, 50"><title>Example</title><defs><path id="guide" d="M 0 0 L 100 50" /></defs><g><circle cx="50" cy="25" r="10" /></g><path d="M 1 1 L 2 2" /></svg>'
		);

		$this->assertSame(
			array(
				'viewBox' => '0 0 100 50',
				'd'       => 'M 0 0 L 100 50',
			),
			$result
		);
	}

	/**
	 * Accepts comma-separated path data, including zero coordinates.
	 *
	 * The token '0' is falsy in PHP, so a truthiness-based separator guard
	 * silently rejects ordinary path data such as 'M0,0' that the editor's
	 * JavaScript twin accepts.
	 *
	 * @dataProvider comma_separated_path_data_provider
	 *
	 * @param string $path_data Comma-separated path data the editor accepts.
	 */
	public function test_parse_svg_path_accepts_comma_separated_zero_coordinates( $path_data ) {
		$result = Controller::parse_svg_path(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="' . $path_data . '" /></svg>'
		);

		$this->assertNotNull( $result );
		$this->assertSame( $path_data, $result['d'] );
	}

	/**
	 * Provides comma-separated path data the shared editor grammar accepts.
	 *
	 * @return array<string, array{string}>
	 */
	public function comma_separated_path_data_provider() {
		return array(
			'zero_move_and_line'   => array( 'M0,0 L10,10' ),
			'zero_move_and_close'  => array( 'M0,0 L10,10 Z' ),
			'mixed_separators'     => array( 'M0,0 L10 10' ),
			'non_zero_coordinates' => array( 'M0.5,0.5 L1,1' ),
			'zero_curve_arguments' => array( 'M0,0 C1,1 0,0 3,3' ),
		);
	}

	/**
	 * Rejects path data that does not match the editor grammar.
	 *
	 * @dataProvider invalid_path_data_provider
	 *
	 * @param string $path_data Path data that the editor would reject.
	 */
	public function test_parse_svg_path_rejects_path_data_the_editor_would_reject( $path_data ) {
		$this->assertNull(
			Controller::parse_svg_path(
				'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="' . $path_data . '" /></svg>'
			)
		);
	}

	/**
	 * Provides path data that is syntactically permitted by a character
	 * allowlist but invalid according to the shared editor grammar.
	 *
	 * @return array<string, array{string}>
	 */
	public function invalid_path_data_provider() {
		return array(
			'initial_command_is_not_move' => array( 'L 1 1 M 0 0 L 2 2' ),
			'incomplete_move_arguments'   => array( 'M 0 L 1 2' ),
			'illegal_arc_flags'           => array( 'M 0 0 A 5 5 0 2 1 10 10' ),
			'comma_before_command'        => array( 'M0,0,L10,10' ),
			'comma_after_command'         => array( 'M,0 0 L10 10' ),
			'trailing_comma'              => array( 'M0 0 L10 10,' ),
		);
	}

	/**
	 * Rejects unsafe or structurally invalid SVG documents.
	 *
	 * @dataProvider invalid_svg_provider
	 *
	 * @param string $svg Invalid SVG input.
	 */
	public function test_parse_svg_path_rejects_unsafe_or_invalid_svg( $svg ) {
		$this->assertNull( Controller::parse_svg_path( $svg ) );
	}

	/**
	 * Provides SVG markup that must never be accepted for Text Path data.
	 *
	 * @return array<string, array{string}>
	 */
	public function invalid_svg_provider() {
		return array(
			'doctype'                => array( '<!DOCTYPE svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M 0 0 L 10 10" /></svg>' ),
			'entity'                 => array( '<!ENTITY test "value"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M 0 0 L 10 10" /></svg>' ),
			'script'                 => array( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><script>alert(1)</script><path d="M 0 0 L 10 10" /></svg>' ),
			'foreign_object'         => array( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><foreignObject><div>Unsafe</div></foreignObject><path d="M 0 0 L 10 10" /></svg>' ),
			'non_svg_root'           => array( '<html><path d="M 0 0 L 10 10" /></html>' ),
			'non_svg_namespace'      => array( '<svg xmlns="https://example.test/not-svg" viewBox="0 0 10 10"><path d="M 0 0 L 10 10" /></svg>' ),
			'non_svg_path_namespace' => array( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path xmlns="https://example.test/not-svg" d="M 0 0 L 10 10" /></svg>' ),
			'non_finite_view_box'    => array( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1e309 10"><path d="M 0 0 L 10 10" /></svg>' ),
			'missing_path'           => array( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"></svg>' ),
			'malformed_xml'          => array( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M 0 0 L 10 10"></svg>' ),
			'oversized'              => array( str_repeat( ' ', 12 * 1024 + 1 ) ),
		);
	}

	/**
	 * Registers the extraction endpoint with the expected request contract.
	 */
	public function test_route_is_registered_as_authenticated_post_endpoint() {
		do_action( 'rest_api_init' );
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/designsetgo/v1/text-path/extract', $routes );
	}

	/**
	 * Denies the endpoint to users who cannot upload files.
	 */
	public function test_permission_callback_denies_user_without_upload_files() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$result = Controller::permissions_check( new WP_REST_Request( 'POST', '/designsetgo/v1/text-path/extract' ) );

		$this->assertWPError( $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Returns only normalized path data for an authorized request.
	 */
	public function test_authenticated_uploader_receives_only_path_data() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/text-path/extract' );
		$request->set_param( 'svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M 0 0 L 10 10" /></svg>' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'viewBox' => '0 0 10 10',
				'd'       => 'M 0 0 L 10 10',
			),
			$response->get_data()
		);
	}
}
