<?php
/**
 * @group query-block
 */
class DSGo_QM_Collector_Test extends WP_UnitTestCase {

	public function test_collector_ignores_data_when_qm_absent() {
		if ( defined( 'QM_VERSION' ) ) {
			$this->markTestSkipped( 'QM is active in this environment; test only applies when QM is absent.' );
		}
		$this->assertFalse( class_exists( 'DesignSetGo\QueryMonitor\Collector' ) );
	}

	public function test_data_structure_is_serialisable() {
		$data = array(
			'query_id'    => 'abc123',
			'source'      => 'posts',
			'wp_args'     => array( 'post_type' => 'post', 'posts_per_page' => 6 ),
			'found_posts' => 12,
			'sql'         => 'SELECT ...',
			'filters'     => array(),
			'duration_ms' => 14.5,
		);
		$this->assertIsString( wp_json_encode( $data ) );
	}
}
