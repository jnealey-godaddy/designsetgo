<?php
/**
 * Nested/array request params must never reach string functions in Query.
 *
 * Loads from build/ (as production does): run `npm run build` first.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

/**
 * @group query
 */
class Query_Nested_Params_Test extends WP_UnitTestCase {

	/**
	 * Original $_GET.
	 *
	 * @var array
	 */
	private $get_backup = array();

	public function set_up() {
		parent::set_up();
		$this->get_backup = $_GET;
		$file             = DESIGNSETGO_PATH . 'build/blocks/query/render-helpers.php';
		$this->assertFileExists( $file, 'Run npm run build first.' );
		require_once $file;
	}

	public function tear_down() {
		$_GET = $this->get_backup;
		parent::tear_down();
	}

	public function test_scalar_param_drops_nested_arrays() {
		$this->assertSame( 'x', designsetgo_query_scalar_param( 'x' ) );
		$this->assertSame( array(), designsetgo_query_scalar_param( array() ) );
		$this->assertSame( array( 'a', '2' ), designsetgo_query_scalar_param( array( 'a', array( 'n' ), 2 ) ) );
		$this->assertSame( array(), designsetgo_query_scalar_param( array( 'a' => array( '1' ) ) ) );
		$this->assertSame( '', designsetgo_query_scalar_param( new stdClass() ) );
	}

	public function test_filter_render_survives_nested_get_value() {
		$_GET = array( 'filter_category' => array( 'a' => array( '1' ) ) );
		$out  = do_blocks(
			'<!-- wp:designsetgo/query-filter {"filterType":"checkbox","taxonomy":"category"} /-->'
		);
		$this->assertIsString( $out );
	}
}
