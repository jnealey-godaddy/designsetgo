<?php
/**
 * Regression test for the Query Monitor `qm/outputter/html` callback.
 *
 * Guards the fix in #473. Query Monitor's dispatcher fires the outputter filter
 * with the `QM_Collectors` singleton *object*, not an array:
 *
 *     // query-monitor/classes/Dispatcher.php
 *     $collectors = QM_Collectors::init();
 *     apply_filters( "qm/outputter/html", array(), $collectors );
 *
 * The callback previously type-hinted `array $collectors`, so PHP threw a
 * `TypeError` at argument binding on every request while QM was active. And
 * because `QM_Collectors` implements only `IteratorAggregate` (not
 * `ArrayAccess`), the old `isset( $collectors['dsgo_queries'] )` /
 * `$collectors['dsgo_queries']` array access was a second latent fatal.
 *
 * The stubs below faithfully mirror those two properties of the real QM
 * classes (an object passed by value; `IteratorAggregate` without
 * `ArrayAccess`; a static `get()` accessor), so a reintroduction of either
 * mistake fails this test exactly as it fatals in production.
 *
 * @group query-block
 */

// Minimal stand-ins for the Query Monitor base classes, only defined when the
// real plugin is absent (the CI case). They match QM's real class shapes.
//
// NOTE: these are process-global and guarded only by class_exists(), so they
// are shared by every test in the run. This is currently the only QM
// stub-based test; if more are added, extract these into a shared fixture or
// trait so the stub shapes live in one place and can't drift or depend on load
// order.
if ( ! class_exists( 'QM_Collector' ) ) {
	class QM_Collector {
		public $id   = '';
		public $data = array();
		public function get_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'QM_Output_Html' ) ) {
	class QM_Output_Html {
		protected $collector;
		public function __construct( $collector ) {
			$this->collector = $collector;
		}
	}
}

if ( ! class_exists( 'QM_Collectors' ) ) {
	class QM_Collectors implements IteratorAggregate {
		/** @var array<string, QM_Collector> */
		public $items = array();

		/** @var QM_Collectors|null */
		private static $instance;

		public static function init(): QM_Collectors {
			if ( ! self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public static function add( QM_Collector $collector ): void {
			self::init()->items[ $collector->id ] = $collector;
		}

		public static function get( $id ) {
			return self::init()->items[ $id ] ?? null;
		}

		public function getIterator(): Iterator {
			return new ArrayIterator( $this->items );
		}
	}
}

/**
 * @group query-block
 */
class DSGo_QM_Outputter_Test extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();

		// Load the file under test. Its own guard bails unless QM_Output_Html
		// exists — which the stubs above guarantee — and its bottom-of-file
		// add_filter() registers the callback we exercise here.
		require_once DESIGNSETGO_PLUGIN_DIR . '/includes/integrations/query-monitor/class-query-qm-output.php';
	}

	/**
	 * The callback must accept the QM_Collectors *object* the dispatcher passes
	 * and register the DSGo outputter — without a TypeError or array-access
	 * fatal.
	 */
	public function test_outputter_callback_accepts_collectors_object() {
		if ( defined( 'QM_VERSION' ) ) {
			$this->markTestSkipped( 'Real Query Monitor is active; this test exercises the stubbed class shapes.' );
		}

		$this->assertTrue(
			class_exists( 'DesignSetGo\QueryMonitor\OutputHtml' ),
			'The QM output integration file should have loaded against the stubs.'
		);

		$collector       = new QM_Collector();
		$collector->id   = 'dsgo_queries';
		$collector->data = array(
			'renders' => array(),
			'count'   => 0,
		);
		QM_Collectors::add( $collector );

		// Exactly what QM's dispatcher passes: the QM_Collectors object itself.
		$collectors = QM_Collectors::init();
		$outputters = apply_filters( 'qm/outputter/html', array(), $collectors );

		$this->assertArrayHasKey(
			'dsgo_queries',
			$outputters,
			'The DSGo outputter should be registered for its collector.'
		);
		$this->assertInstanceOf( 'QM_Output_Html', $outputters['dsgo_queries'] );
	}

	/**
	 * When no DSGo collector is registered, the callback must add nothing (and
	 * still not fatal on the object argument).
	 */
	public function test_outputter_callback_no_ops_without_collector() {
		if ( defined( 'QM_VERSION' ) ) {
			$this->markTestSkipped( 'Real Query Monitor is active; this test exercises the stubbed class shapes.' );
		}

		// Fresh collectors pool with no dsgo_queries entry.
		QM_Collectors::init()->items = array();

		$outputters = apply_filters( 'qm/outputter/html', array(), QM_Collectors::init() );

		$this->assertArrayNotHasKey( 'dsgo_queries', $outputters );
	}
}
