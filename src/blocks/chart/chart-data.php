<?php
/**
 * Chart block data resolution.
 *
 * Turns block attributes into a clean list of rows and renders the accessible
 * data table and legend. Colour resolution lives in `chart-colors.php`.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_chart_max_rows' ) ) {
	/**
	 * The most rows a single chart will draw.
	 *
	 * @return int Row cap, never below one.
	 */
	function designsetgo_chart_max_rows() {
		/**
		 * Filter the chart row cap.
		 *
		 * Raise it for a data set that genuinely needs more, bearing in mind
		 * that every row adds markup to the response on every request.
		 *
		 * @param int $max Maximum rows drawn. Default 200.
		 */
		$max = (int) apply_filters( 'designsetgo_chart_max_rows', 200 );

		return max( 1, $max );
	}
}

if ( ! function_exists( 'designsetgo_chart_rows' ) ) {
	/**
	 * Resolve the chart's rows from attributes or post meta.
	 *
	 * @param array         $attributes Block attributes.
	 * @param WP_Block|null $block      Block instance, for post context.
	 * @param int|null      $total      Receives the source row count before the
	 *                                  cap, so the caller can say what was cut
	 *                                  without decoding the source twice.
	 * @return array List of array{label: string, value: float}.
	 */
	function designsetgo_chart_rows( array $attributes, $block = null, &$total = null ) {
		$raw = isset( $attributes['data'] ) && is_array( $attributes['data'] )
			? $attributes['data']
			: array();

		$source = isset( $attributes['dataSource'] ) ? $attributes['dataSource'] : 'manual';

		if ( 'meta' === $source && ! empty( $attributes['metaKey'] ) ) {
			$raw = designsetgo_chart_meta_rows( (string) $attributes['metaKey'], $block );
		}

		// The meta path reads whatever an import script or a field plugin wrote,
		// which is not bounded by an author's patience the way typed rows are.
		// The cap is a legibility limit first and a cost limit second: the plot
		// is 600 units wide, so 200 rows already puts each bar under three units
		// and the chart stops carrying meaning long before it gets expensive.
		$raw   = (array) $raw;
		$total = count( $raw );
		$raw   = array_slice( $raw, 0, designsetgo_chart_max_rows() );

		$rows = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['value'] ) || ! is_numeric( $row['value'] ) ) {
				continue;
			}

			// Meta-sourced labels come from arbitrary stored JSON, so a
			// non-scalar would raise "Array to string conversion" on cast.
			$label = isset( $row['label'] ) && is_scalar( $row['label'] )
				? (string) $row['label']
				: '';

			$rows[] = array(
				'label' => $label,
				'value' => (float) $row['value'],
			);
		}

		return $rows;
	}
}

if ( ! function_exists( 'designsetgo_chart_donut_rows' ) ) {
	/**
	 * Drop the rows a donut cannot represent, keeping colours aligned.
	 *
	 * A slice is a share of a total, and a negative has no share. Rendering one
	 * as its absolute value would show a positive slice for a negative number,
	 * so the row is removed from the chart, the legend, and the data table
	 * alike rather than being silently misrepresented in one of them.
	 *
	 * @param array $rows   Chart rows.
	 * @param array $colors Series colours, positional against $rows.
	 * @return array{rows: array, colors: array} The filtered pair.
	 */
	function designsetgo_chart_donut_rows( array $rows, array $colors ) {
		$kept_rows   = array();
		$kept_colors = array();

		foreach ( array_values( $rows ) as $i => $row ) {
			if ( $row['value'] <= 0 ) {
				continue;
			}

			$kept_rows[]   = $row;
			$kept_colors[] = isset( $colors[ $i ] ) ? $colors[ $i ] : '';
		}

		return array(
			'rows'   => $kept_rows,
			'colors' => $kept_colors,
		);
	}
}

if ( ! function_exists( 'designsetgo_chart_meta_rows' ) ) {
	/**
	 * Read a row array out of a post meta field.
	 *
	 * The field may hold either a JSON string or an already-decoded array.
	 * Protected meta is never readable — it is not authored for display.
	 *
	 * @param string        $meta_key Meta key.
	 * @param WP_Block|null $block    Block instance, for post context.
	 * @return array Raw rows.
	 */
	function designsetgo_chart_meta_rows( $meta_key, $block = null ) {
		if ( is_protected_meta( $meta_key, 'post' ) ) {
			return array();
		}

		$post_id = isset( $block->context['postId'] ) ? $block->context['postId'] : get_the_ID();

		if ( ! $post_id ) {
			return array();
		}

		// Same gates as the block bindings adapter and StyleBinding::resolve()
		// (includes/features/class-style-binding.php) so a chart cannot leak
		// meta those paths would withhold — e.g. a chart inside a Query Loop
		// that lands on a private or password-protected post.
		$post = get_post( $post_id );

		if ( ! $post || post_password_required( $post ) ) {
			return array();
		}

		if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) {
			return array();
		}

		$stored = get_post_meta( $post_id, $meta_key, true );

		if ( is_array( $stored ) ) {
			return $stored;
		}

		if ( is_string( $stored ) && '' !== $stored ) {
			$decoded = json_decode( $stored, true );

			return is_array( $decoded ) ? $decoded : array();
		}

		return array();
	}
}

if ( ! function_exists( 'designsetgo_chart_data_table' ) ) {
	/**
	 * Render the visually-hidden data table.
	 *
	 * The SVG is aria-hidden, so this table is the accessible representation
	 * of the chart. It is always emitted.
	 *
	 * @param array  $rows  Chart rows.
	 * @param string $label Chart label.
	 * @param int    $total Source row count, to disclose a truncated chart.
	 * @return string Table markup.
	 */
	function designsetgo_chart_data_table( array $rows, $label, $total = 0 ) {
		$out     = '<table class="screen-reader-text">';
		$caption = '' !== $label ? $label : '';

		// A truncated chart that says nothing is indistinguishable from a
		// complete one, and this table is the only place the full count could
		// have been checked, so it is where the shortfall has to be declared.
		if ( $total > count( $rows ) ) {
			$note = sprintf(
				/* translators: 1: rows shown, 2: rows in the source. */
				__( 'Showing the first %1$s of %2$s rows.', 'designsetgo' ),
				number_format_i18n( count( $rows ) ),
				number_format_i18n( $total )
			);

			$caption = '' === $caption ? $note : $caption . ' ' . $note;
		}

		if ( '' !== $caption ) {
			$out .= '<caption>' . esc_html( $caption ) . '</caption>';
		}

		$out .= '<thead><tr><th scope="col">' . esc_html__( 'Label', 'designsetgo' )
			. '</th><th scope="col">' . esc_html__( 'Value', 'designsetgo' )
			. '</th></tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$out .= '<tr><th scope="row">' . esc_html( $row['label'] ) . '</th><td>'
				. esc_html( designsetgo_chart_format_value( $row['value'] ) ) . '</td></tr>';
		}

		return $out . '</tbody></table>';
	}
}

if ( ! function_exists( 'designsetgo_chart_legend' ) ) {
	/**
	 * Render the legend.
	 *
	 * Colour is never the only channel identifying a series — the legend pairs
	 * each swatch with its label, and the data table repeats both.
	 *
	 * @param array $rows   Chart rows.
	 * @param array $colors Series colours.
	 * @return string Legend markup.
	 */
	function designsetgo_chart_legend( array $rows, array $colors ) {
		$out = '<ul class="dsgo-chart__legend">';

		foreach ( array_values( $rows ) as $i => $row ) {
			$out .= sprintf(
				'<li class="dsgo-chart__legend-item"><span class="dsgo-chart__swatch" style="background:%s" aria-hidden="true"></span>%s</li>',
				esc_attr( $colors[ $i ] ),
				esc_html( $row['label'] )
			);
		}

		return $out . '</ul>';
	}
}
