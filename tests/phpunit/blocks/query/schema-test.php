<?php
/**
 * PHPUnit tests for JSON-LD ItemList schema emission in the query block.
 *
 * @package DesignSetGo
 */

/**
 * Test class for query block schema emission.
 *
 * @group query-block
 */
class DesignSetGo_Query_Schema_Test extends WP_UnitTestCase {

	/**
	 * Ensure build helpers exist before requiring them.
	 */
	private function load_helpers() {
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render helpers are served from build/.' );
		require_once $path;
	}

	/**
	 * When emitSchema is true (default), a Posts-source query should append
	 * a <script type="application/ld+json"> ItemList element after the list.
	 */
	public function test_emits_json_ld_itemlist_when_emit_schema_true() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'     => 'posts',
				'postType'   => 'post',
				'perPage'    => 10,
				'emitSchema' => true,
			),
			array(
				'query_id'   => 'sch',
				'page'       => 1,
				'inner_html' => '',
			)
		);

		$this->assertStringContainsString( 'application/ld+json', $result['html'] );
		$this->assertStringContainsString( '"ItemList"', $result['html'] );
		$this->assertStringContainsString( '"@context"', $result['html'] );
		$this->assertStringContainsString( 'https://schema.org', $result['html'] );
		$this->assertStringContainsString( '"ListItem"', $result['html'] );
	}

	/**
	 * When emitSchema is false, no JSON-LD block should appear in the output.
	 */
	public function test_omits_schema_when_emit_schema_false() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'     => 'posts',
				'postType'   => 'post',
				'perPage'    => 10,
				'emitSchema' => false,
			),
			array(
				'query_id'   => 'sch2',
				'page'       => 1,
				'inner_html' => '',
			)
		);

		$this->assertStringNotContainsString( 'application/ld+json', $result['html'] );
	}

	/**
	 * Schema is only emitted for source=posts — not for users or terms.
	 */
	public function test_schema_not_emitted_for_non_posts_source() {
		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'     => 'users',
				'perPage'    => 10,
				'emitSchema' => true,
			),
			array(
				'query_id'   => 'sch3',
				'page'       => 1,
				'inner_html' => '',
			)
		);

		$this->assertStringNotContainsString( 'application/ld+json', $result['html'] );
	}

	/**
	 * The schema script element is a sibling of the list wrapper, not inside it,
	 * so it does not break ul > li:first-child selectors.
	 */
	public function test_schema_emitted_outside_list_wrapper() {
		self::factory()->post->create_many( 2, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'     => 'posts',
				'postType'   => 'post',
				'perPage'    => 10,
				'emitSchema' => true,
			),
			array(
				'query_id'   => 'sch4',
				'page'       => 1,
				'inner_html' => '',
			)
		);

		$html = $result['html'];

		// Find positions to verify schema comes AFTER the closing </ul>.
		$close_ul_pos  = strrpos( $html, '</ul>' );
		$schema_pos    = strpos( $html, 'application/ld+json' );

		$this->assertNotFalse( $close_ul_pos, 'List wrapper closing tag must be present.' );
		$this->assertNotFalse( $schema_pos, 'Schema script must be present.' );
		$this->assertGreaterThan( $close_ul_pos, $schema_pos, 'Schema must appear after the closing list tag.' );
	}

	/**
	 * The itemListElement array positions are 1-based and sequential.
	 */
	public function test_schema_item_positions_are_one_based() {
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );
		$this->load_helpers();

		$result = designsetgo_query_render(
			array(
				'source'     => 'posts',
				'postType'   => 'post',
				'perPage'    => 10,
				'emitSchema' => true,
			),
			array(
				'query_id'   => 'sch5',
				'page'       => 1,
				'inner_html' => '',
			)
		);

		// Extract JSON-LD from output.
		preg_match( '/<script type="application\/ld\+json">(.*?)<\/script>/s', $result['html'], $matches );
		$this->assertNotEmpty( $matches[1], 'JSON-LD content must be present.' );

		$schema = json_decode( $matches[1], true );
		$this->assertIsArray( $schema );
		$this->assertSame( 'ItemList', $schema['@type'] );

		$elements = $schema['itemListElement'];
		$this->assertCount( 3, $elements );
		$this->assertSame( 1, $elements[0]['position'] );
		$this->assertSame( 2, $elements[1]['position'] );
		$this->assertSame( 3, $elements[2]['position'] );

		// Each element must have a url.
		foreach ( $elements as $el ) {
			$this->assertArrayHasKey( 'url', $el );
			$this->assertNotEmpty( $el['url'] );
		}
	}
}
