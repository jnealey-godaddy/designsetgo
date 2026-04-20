<?php
/**
 * Query Monitor HTML output for DesignSetGo Dynamic Query.
 *
 * @package DesignSetGo
 * @since 2.5.0
 */

namespace DesignSetGo\QueryMonitor;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\QM_Output_Html' ) ) {
	return;
}

/**
 * Query Monitor HTML output for DesignSetGo queries.
 */
class OutputHtml extends \QM_Output_Html {

	/**
	 * Constructor.
	 *
	 * @param \QM_Collector $collector The data collector instance.
	 */
	public function __construct( \QM_Collector $collector ) {
		parent::__construct( $collector );
		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 80 );
	}

	/**
	 * Human-readable panel name shown in QM UI.
	 *
	 * @return string
	 */
	public function name(): string {
		return __( 'DSGo Queries', 'designsetgo' );
	}

	/**
	 * Render the QM panel HTML.
	 *
	 * @return void
	 */
	public function output(): void {
		$data    = $this->collector->get_data();
		$renders = $data['renders'] ?? array();

		$this->before_non_tabular_output();

		if ( empty( $renders ) ) {
			echo '<p>' . esc_html__( 'No DSGo queries ran on this request.', 'designsetgo' ) . '</p>';
			$this->after_non_tabular_output();
			return;
		}

		echo '<p>' . esc_html(
			sprintf(
				/* translators: %d: number of queries */
				_n( '%d DSGo query ran on this request.', '%d DSGo queries ran on this request.', count( $renders ), 'designsetgo' ),
				count( $renders )
			)
		) . '</p>';

		echo '<style>.dsgo-qm-pre{margin:0;white-space:pre-wrap}</style>';

		foreach ( $renders as $i => $r ) {
			echo '<h3>' . esc_html( sprintf( '#%d — %s (%s)', $i + 1, $r['query_id'], $r['source'] ) ) . '</h3>';
			echo '<table class="qm-sortable"><thead><tr>';
			echo '<th>' . esc_html__( 'Property', 'designsetgo' ) . '</th>';
			echo '<th>' . esc_html__( 'Value', 'designsetgo' ) . '</th>';
			echo '</tr></thead><tbody>';

			$rows = array(
				__( 'Found posts', 'designsetgo' )   => $r['found_posts'],
				__( 'Duration (ms)', 'designsetgo' ) => $r['duration_ms'],
				__( 'WP_Query args', 'designsetgo' ) => '<pre class="dsgo-qm-pre">' . esc_html( wp_json_encode( $r['wp_args'], JSON_PRETTY_PRINT ) ) . '</pre>',
				__( 'SQL', 'designsetgo' )            => '<code>' . esc_html( $r['sql'] ) . '</code>',
			);

			foreach ( $rows as $label => $value ) {
				echo '<tr><td>' . esc_html( $label ) . '</td><td>' . wp_kses_post( $value ) . '</td></tr>';
			}

			echo '</tbody></table>';
		}

		$this->after_non_tabular_output();
	}

	/**
	 * Register this panel in the QM admin bar menu.
	 *
	 * @param array $menu Existing QM menu entries.
	 * @return array Modified menu entries.
	 */
	public function admin_menu( array $menu ): array {
		$data  = $this->collector->get_data();
		$count = $data['count'] ?? 0;

		$menu[ $this->collector->id ] = $this->menu(
			array(
				'title' => esc_html(
					sprintf(
						/* translators: %d: query count */
						_n( 'DSGo (%d)', 'DSGo (%d)', $count, 'designsetgo' ),
						$count
					)
				),
			)
		);

		return $menu;
	}
}

add_filter(
	'qm/outputter/html',
	static function ( array $outputters, array $collectors ) {
		if ( isset( $collectors['dsgo_queries'] ) ) {
			$outputters['dsgo_queries'] = new OutputHtml( $collectors['dsgo_queries'] );
		}
		return $outputters;
	},
	80,
	2
);
