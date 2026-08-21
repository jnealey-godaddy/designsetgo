<?php
/**
 * Tests for safe Text Path SVG extraction.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Blocks\Text_Path\Controller;

class DesignSetGo_Text_Path_Controller_Test extends WP_UnitTestCase {

	public function test_parse_svg_path_returns_only_normalized_first_path_data() {
		$result = Controller::parse_svg_path(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0, 0, 100, 50"><path d="M 0 0 L 100 50" /><path d="M 1 1 L 2 2" /></svg>'
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
	 * @dataProvider invalid_svg_provider
	 *
	 * @param string $svg Invalid SVG input.
	 */
	public function test_parse_svg_path_rejects_unsafe_or_invalid_svg( $svg ) {
		$this->assertNull( Controller::parse_svg_path( $svg ) );
	}

	/**
	 * Supplies SVG markup that must never be accepted for Text Path data.
	 *
	 * @return array<string, array{string}>
	 */
	public function invalid_svg_provider() {
		return array(
			'doctype'        => array( '<!DOCTYPE svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M 0 0 L 10 10" /></svg>' ),
			'entity'         => array( '<!ENTITY test "value"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M 0 0 L 10 10" /></svg>' ),
			'script'         => array( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><script>alert(1)</script><path d="M 0 0 L 10 10" /></svg>' ),
			'foreign_object' => array( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><foreignObject><div>Unsafe</div></foreignObject><path d="M 0 0 L 10 10" /></svg>' ),
			'disallowed_tag' => array( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="5" /><path d="M 0 0 L 10 10" /></svg>' ),
			'non_svg_root'   => array( '<html><path d="M 0 0 L 10 10" /></html>' ),
			'missing_path'   => array( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"></svg>' ),
			'malformed_xml'  => array( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M 0 0 L 10 10"></svg>' ),
			'oversized'      => array( str_repeat( ' ', 12 * 1024 + 1 ) ),
		);
	}

	public function test_route_is_registered_as_authenticated_post_endpoint() {
		do_action( 'rest_api_init' );
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/designsetgo/v1/text-path/extract', $routes );
	}

	public function test_permission_callback_denies_user_without_upload_files() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$result = Controller::permissions_check( new WP_REST_Request( 'POST', '/designsetgo/v1/text-path/extract' ) );

		$this->assertWPError( $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

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
