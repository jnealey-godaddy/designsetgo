<?php
/**
 * Query Monitor collector for DesignSetGo Dynamic Query.
 *
 * @package DesignSetGo
 * @since 2.5.0
 */

namespace DesignSetGo\QueryMonitor;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\QM_Collector' ) ) {
	return;
}

/**
 * Query Monitor data collector for DesignSetGo queries.
 */
class Collector extends \QM_Collector {

	/**
	 * Collector ID used by QM to reference this collector.
	 *
	 * @var string
	 */
	public $id = 'dsgo_queries';

	/**
	 * Captured query render data entries.
	 *
	 * @var array[]
	 */
	private array $renders = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'designsetgo_query_did_render', array( $this, 'capture' ) );
	}

	/**
	 * Capture a query render data snapshot.
	 *
	 * @param array $data Associative array with keys: query_id, source, wp_args,
	 *                    found_posts, sql, filters, duration_ms.
	 * @return void
	 */
	public function capture( array $data ): void {
		$this->renders[] = $data;
	}

	/**
	 * Process captured data before output.
	 *
	 * @return void
	 */
	public function process(): void {
		$this->data['renders'] = $this->renders;
		$this->data['count']   = count( $this->renders );
	}

	/**
	 * Human-readable collector name shown in the QM panel list.
	 *
	 * @return string
	 */
	public function name(): string {
		return __( 'DSGo Queries', 'designsetgo' );
	}
}

add_filter(
	'qm/collectors',
	static function ( array $collectors ) {
		$collectors['dsgo_queries'] = new Collector();
		return $collectors;
	}
);
