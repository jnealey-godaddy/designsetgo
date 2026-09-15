<?php
/**
 * REST coverage for the agent-build markup sanitize route: the browser
 * posts a build's assembled markup here before applying it whenever the
 * build's submitter lacked unfiltered_html, and gets back exactly what
 * core's own KSES filtering would store for that submitter.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Agent_Build\Build_REST;
use DesignSetGo\Abilities\Agent_Build\Build_Sanitize_REST;
use DesignSetGo\Abilities\Agent_Build\Build_Store;

/**
 * Build_Sanitize_REST tests.
 *
 * @group abilities
 * @group agent-build
 */
class Agent_Build_Sanitize_REST_Test extends WP_UnitTestCase {

	/**
	 * Store instance backing the route under test.
	 *
	 * @var Build_Store
	 */
	private $store;

	/**
	 * Editor who owns the test post.
	 *
	 * @var int
	 */
	private $editor_id;

	/**
	 * Post the pending build targets.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up test fixtures and register REST routes.
	 */
	public function set_up() {
		parent::set_up();

		$this->store = new Build_Store();
		new Build_Sanitize_REST( $this->store, new Build_REST( $this->store ) );

		$this->editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->post_id   = self::factory()->post->create( array( 'post_author' => $this->editor_id ) );

		rest_get_server()->override_by_default = true;
		do_action( 'rest_api_init' );

		wp_set_current_user( $this->editor_id );
		$this->store->store(
			$this->post_id,
			array(
				'version' => 1,
				'blocks'  => array( array( 'name' => 'core/paragraph' ) ),
			),
			'replace'
		);
	}

	/**
	 * Dispatch a sanitize request.
	 *
	 * @param string      $markup   Markup to sanitize.
	 * @param string|null $build_id Build id; defaults to the pending build's.
	 * @param int|null    $post_id  Post id; defaults to the test post.
	 * @return WP_REST_Response
	 */
	private function sanitize( string $markup, ?string $build_id = null, ?int $post_id = null ): WP_REST_Response {
		$post_id = $post_id ?? $this->post_id;
		$pending = $this->store->pending( $post_id );
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/agent-build/' . $post_id . '/sanitize' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'buildId' => $build_id ?? ( null !== $pending ? $pending['buildId'] : 'no-build-pending' ),
					'markup'  => $markup,
				)
			)
		);

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Sanitized markup from a successful response.
	 *
	 * @param string $markup Markup to sanitize.
	 * @return string
	 */
	private function sanitized( string $markup ): string {
		$response = $this->sanitize( $markup );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );

		return $response->get_data()['markup'];
	}

	/**
	 * A javascript: URL is removed from a paragraph link and a button link.
	 */
	public function test_strips_javascript_urls_from_links_and_buttons(): void {
		$markup = '<!-- wp:paragraph --><p><a href="javascript:alert(1)">x</a></p><!-- /wp:paragraph -->'
			. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="javascript:alert(2)">Go</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';

		$result = $this->sanitized( $markup );

		$this->assertStringNotContainsString( 'javascript:', $result );
		$this->assertStringContainsString( 'wp-block-button__link', $result );
	}

	/**
	 * Script tags and event-handler attributes are removed.
	 */
	public function test_strips_script_and_event_handlers(): void {
		$markup = '<!-- wp:paragraph --><p>Hi<script>alert(1)</script><img src="x.png" onerror="alert(2)"></p><!-- /wp:paragraph -->';

		$result = $this->sanitized( $markup );

		$this->assertStringNotContainsString( '<script', $result );
		$this->assertStringNotContainsString( 'onerror', $result );
		$this->assertStringContainsString( '<img src="x.png"', $result );
	}

	/**
	 * Inline style values go through core's CSS filtering.
	 */
	public function test_filters_unsafe_inline_css(): void {
		$result = $this->sanitized( '<!-- wp:paragraph --><p style="color:red;background:url(javascript:x)">Hi</p><!-- /wp:paragraph -->' );

		$this->assertStringContainsString( 'color:red', $result );
		$this->assertStringNotContainsString( 'javascript', $result );
	}

	/**
	 * Block comment attributes get core's block-context filtering
	 * (filter_block_kses via pre_kses), e.g. template-part tagName.
	 */
	public function test_filters_template_part_tag_name(): void {
		$result = $this->sanitized( '<!-- wp:template-part {"slug":"header","tagName":"script"} /-->' );

		$this->assertStringNotContainsString( 'script', $result );
		$this->assertStringContainsString( 'wp:template-part', $result );
	}

	/**
	 * Ordinary assembled markup - a DSGo section, an icon button, a normal
	 * https link, core buttons, and non-ASCII text - comes back unchanged.
	 * The fixture is real Node CLI output:
	 * `npm run engine -- assemble tests/phpunit/fixtures/agent-build/ordinary-tree.json`.
	 */
	public function test_ordinary_assembled_markup_is_byte_identical(): void {
		$markup = file_get_contents( __DIR__ . '/fixtures/agent-build/ordinary-markup.html' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test fixture.

		$this->assertStringContainsString( 'href="https://example.com/start"', $markup );
		$this->assertSame( $markup, $this->sanitized( $markup ) );
	}

	/**
	 * A user who cannot edit the post is refused.
	 */
	public function test_forbidden_without_edit_post(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );

		$this->assertSame( 403, $this->sanitize( '<p>x</p>' )->get_status() );
	}

	/**
	 * A missing post is a 404.
	 */
	public function test_missing_post_is_404(): void {
		$this->assertSame( 404, $this->sanitize( '<p>x</p>', 'anything', 999999 )->get_status() );
	}

	/**
	 * A build id that is not the pending build's is a 409 mismatch.
	 */
	public function test_different_build_id_is_409_mismatch(): void {
		$response = $this->sanitize( '<p>x</p>', 'some-other-build' );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'designsetgo_build_mismatch', $response->as_error()->get_error_code() );
	}

	/**
	 * With nothing pending, sanitizing is a 409 mismatch.
	 */
	public function test_nothing_pending_is_409_mismatch(): void {
		$pending = $this->store->pending( $this->post_id );
		$this->store->clear( $this->post_id );

		$response = $this->sanitize( '<p>x</p>', $pending['buildId'] );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'designsetgo_build_mismatch', $response->as_error()->get_error_code() );
	}

	/**
	 * Markup over 1 MB is rejected with 413 before any filtering runs.
	 */
	public function test_oversized_markup_is_413(): void {
		$filtered = 0;
		add_filter(
			'pre_kses',
			static function ( $content ) use ( &$filtered ) {
				++$filtered;
				return $content;
			}
		);

		$response = $this->sanitize( '<p>' . str_repeat( 'x', 1024 * 1024 ) . '</p>' );

		$this->assertSame( 413, $response->get_status() );
		$this->assertSame( 0, $filtered );
	}
}
