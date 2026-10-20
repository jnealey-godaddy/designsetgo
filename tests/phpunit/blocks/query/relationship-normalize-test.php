<?php
/**
 * Relationship meta normalisation must not instantiate stored objects.
 *
 * @group query
 */

class DesignSetGo_Relationship_Normalize_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$path = DESIGNSETGO_PATH . 'build/blocks/query/render-relationship.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — query helpers are served from build/.' );
		require_once $path;
	}

	public function test_serialized_array_of_ids_is_read() {
		$this->assertSame( array( 3, 5 ), designsetgo_query_relationship_normalize_ids( serialize( array( '3', '5' ) ) ) );
	}

	public function test_serialized_object_is_not_instantiated() {
		$GLOBALS['dsgo_wakeup_ran'] = false;
		$payload                    = 'O:8:"stdClass":1:{s:1:"a";i:1;}';
		$this->assertSame( array(), designsetgo_query_relationship_normalize_ids( $payload ) );
		$this->assertSame( array(), designsetgo_query_relationship_normalize_ids( 'a:1:{i:0;O:8:"stdClass":0:{}}' ) );
	}
}
