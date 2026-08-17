# Chart Block Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A `designsetgo/chart` block rendering bar, line, and donut charts as inline SVG with no JavaScript charting library, where the data can be typed in by hand or pulled from post meta via the existing bindings sources.

**Architecture:** A **dynamic** block. All geometry lives in one PHP renderer (`render.php` + a `chart-geometry.php` helper); the editor previews through `<ServerSideRender>`. One implementation, no JS/PHP duplication, and dynamic data sources work for free because the render happens server-side inside the normal binding pipeline. Output is a plain `<svg>` with an adjacent `<table class="screen-reader-text">` so the data is available to assistive tech and to search engines.

**Tech Stack:** PHP 7.4+, `@wordpress/server-side-render`, `@wordpress/components`. No Chart.js, no D3.

**Spec:** [2026-08-16-greenshift-gap-roadmap.md](2026-08-16-greenshift-gap-roadmap.md)

## Global Constraints

- Tabs for JS/SCSS/PHP, 2 spaces for JSON. `dsgo-` CSS prefix, `designsetgo_` PHP functions.
- 300 lines max per file excluding constant tables.
- `apiVersion: 3`, `textdomain: designsetgo`, `category: designsetgo`.
- `render.php` must wrap all logic in a named `designsetgo_render_chart()` function guarded
  by `function_exists`, called at the bottom — the `dynamic-image/render.php` pattern. This
  keeps variables function-scoped and avoids `NonPrefixedVariableFound` PCP warnings.
- `defined( 'ABSPATH' ) || exit;` at the top of every PHP file. Escape all output.
- No new runtime dependencies.
- WCAG AA: colour is never the only channel — every series gets a legend entry, and the
  data table is always emitted.
- No `console.log`. Branch `claude/chart-block`. Commit format `type: description`.
- Pre-commit: `npm run build && npm run lint:js && npm run lint:css && npm run lint:php && npm run test:unit && npm run test:php`.

## Why server-rendered

A static block would need the geometry in JS for `save()` and again in PHP for any dynamic
data — two implementations that will drift. `ServerSideRender` costs one REST round-trip
per editor keystroke (debounced by the component) and buys a single source of truth plus
free bindings support. The frontend ships zero JavaScript.

## File Structure

| File | Responsibility |
|------|----------------|
| `src/blocks/chart/block.json` | Metadata, attributes, `render` pointer |
| `src/blocks/chart/render.php` | `designsetgo_render_chart()` — dispatch by type |
| `src/blocks/chart/chart-geometry.php` | Pure scale/point/arc math |
| `src/blocks/chart/index.js` | Registration |
| `src/blocks/chart/edit.js` | Inspector + `<ServerSideRender>` preview |
| `src/blocks/chart/components/DataEditor.js` | Row add/edit/remove grid |
| `src/blocks/chart/style.scss` | Frontend styles |
| `tests/phpunit/test-chart-geometry.php` | Geometry math |
| `tests/phpunit/test-chart-render.php` | Rendered output + escaping |

---

### Task 1: Geometry helpers

**Files:**
- Create: `src/blocks/chart/chart-geometry.php`
- Test: `tests/phpunit/test-chart-geometry.php`

**Interfaces:**
- Produces, all in the global namespace with the `designsetgo_chart_` prefix:
  - `designsetgo_chart_scale( float $value, float $min, float $max, float $out_min, float $out_max ): float`
  - `designsetgo_chart_bounds( array $values ): array` → `array( 'min' => float, 'max' => float )`
  - `designsetgo_chart_line_points( array $values, int $width, int $height, float $min, float $max ): string`
  - `designsetgo_chart_arc_path( float $cx, float $cy, float $radius, float $thickness, float $start_deg, float $end_deg ): string`

- [ ] **Step 1: Write the failing test**

```php
<?php
/**
 * Chart geometry tests.
 *
 * @package DesignSetGo
 */

require_once DESIGNSETGO_PATH . 'src/blocks/chart/chart-geometry.php';

class Test_Chart_Geometry extends WP_UnitTestCase {

	public function test_scale_maps_the_midpoint() {
		$this->assertSame( 50.0, designsetgo_chart_scale( 5, 0, 10, 0, 100 ) );
	}

	public function test_scale_maps_the_endpoints() {
		$this->assertSame( 0.0, designsetgo_chart_scale( 0, 0, 10, 0, 100 ) );
		$this->assertSame( 100.0, designsetgo_chart_scale( 10, 0, 10, 0, 100 ) );
	}

	public function test_scale_survives_a_zero_range() {
		// All values identical: pin to the middle rather than divide by zero.
		$this->assertSame( 50.0, designsetgo_chart_scale( 5, 5, 5, 0, 100 ) );
	}

	public function test_bounds_pads_a_positive_series_down_to_zero() {
		$bounds = designsetgo_chart_bounds( array( 3, 7, 5 ) );
		$this->assertSame( 0.0, $bounds['min'] );
		$this->assertSame( 7.0, $bounds['max'] );
	}

	public function test_bounds_keeps_a_negative_minimum() {
		$bounds = designsetgo_chart_bounds( array( -3, 7 ) );
		$this->assertSame( -3.0, $bounds['min'] );
		$this->assertSame( 7.0, $bounds['max'] );
	}

	public function test_bounds_of_an_empty_series_is_zero_to_one() {
		$bounds = designsetgo_chart_bounds( array() );
		$this->assertSame( 0.0, $bounds['min'] );
		$this->assertSame( 1.0, $bounds['max'] );
	}

	public function test_line_points_returns_one_pair_per_value() {
		$points = designsetgo_chart_line_points( array( 0, 5, 10 ), 100, 50, 0, 10 );
		$this->assertCount( 3, explode( ' ', $points ) );
	}

	public function test_line_points_inverts_the_y_axis() {
		// The largest value must sit at the top, meaning y = 0.
		$points = designsetgo_chart_line_points( array( 0, 10 ), 100, 50, 0, 10 );
		$pairs  = explode( ' ', $points );
		$last   = explode( ',', $pairs[1] );
		$this->assertSame( '0', $last[1] );
	}

	public function test_line_points_of_a_single_value_is_centred() {
		$points = designsetgo_chart_line_points( array( 5 ), 100, 50, 0, 10 );
		$this->assertStringStartsWith( '50,', $points );
	}

	public function test_arc_path_starts_with_a_move_command() {
		$path = designsetgo_chart_arc_path( 50, 50, 40, 10, 0, 90 );
		$this->assertStringStartsWith( 'M ', $path );
		$this->assertStringContainsString( 'A ', $path );
	}

	public function test_arc_path_of_a_zero_sweep_is_empty() {
		$this->assertSame( '', designsetgo_chart_arc_path( 50, 50, 40, 10, 30, 30 ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:php -- --filter Test_Chart_Geometry`
Expected: FAIL — file not found

- [ ] **Step 3: Write the implementation**

```php
<?php
/**
 * Chart block geometry helpers.
 *
 * Pure math. No WordPress dependencies, no output — everything here is
 * unit-testable in isolation.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'designsetgo_chart_scale' ) ) {
	/**
	 * Map a value from one range onto another.
	 *
	 * @param float $value   Input value.
	 * @param float $min     Input range minimum.
	 * @param float $max     Input range maximum.
	 * @param float $out_min Output range minimum.
	 * @param float $out_max Output range maximum.
	 * @return float Mapped value.
	 */
	function designsetgo_chart_scale( $value, $min, $max, $out_min, $out_max ) {
		$range = (float) $max - (float) $min;

		if ( 0.0 === $range ) {
			// A flat series has no meaningful position; centre it.
			return ( (float) $out_min + (float) $out_max ) / 2;
		}

		$ratio = ( (float) $value - (float) $min ) / $range;

		return (float) $out_min + $ratio * ( (float) $out_max - (float) $out_min );
	}
}

if ( ! function_exists( 'designsetgo_chart_bounds' ) ) {
	/**
	 * Compute the axis bounds for a series.
	 *
	 * A wholly positive series is anchored at zero so bar heights read
	 * proportionally rather than being exaggerated by a floating baseline.
	 *
	 * @param array $values Numeric values.
	 * @return array{min: float, max: float} Bounds.
	 */
	function designsetgo_chart_bounds( array $values ) {
		$numbers = array_map( 'floatval', array_filter( $values, 'is_numeric' ) );

		if ( empty( $numbers ) ) {
			return array(
				'min' => 0.0,
				'max' => 1.0,
			);
		}

		$min = min( $numbers );
		$max = max( $numbers );

		if ( $min > 0 ) {
			$min = 0.0;
		}

		if ( $min === $max ) {
			$max = $min + 1.0;
		}

		return array(
			'min' => (float) $min,
			'max' => (float) $max,
		);
	}
}

if ( ! function_exists( 'designsetgo_chart_line_points' ) ) {
	/**
	 * Build an SVG polyline `points` string.
	 *
	 * @param array $values Numeric values.
	 * @param int   $width  Viewport width.
	 * @param int   $height Viewport height.
	 * @param float $min    Axis minimum.
	 * @param float $max    Axis maximum.
	 * @return string Space-separated `x,y` pairs.
	 */
	function designsetgo_chart_line_points( array $values, $width, $height, $min, $max ) {
		$count = count( $values );

		if ( 0 === $count ) {
			return '';
		}

		$points = array();
		$step   = $count > 1 ? $width / ( $count - 1 ) : 0;

		foreach ( array_values( $values ) as $index => $value ) {
			$x = $count > 1 ? $index * $step : $width / 2;
			// SVG y grows downward, so a larger value must produce a smaller y.
			$y = $height - designsetgo_chart_scale( $value, $min, $max, 0, $height );

			$points[] = round( $x, 2 ) . ',' . round( $y, 2 );
		}

		return implode( ' ', $points );
	}
}

if ( ! function_exists( 'designsetgo_chart_arc_path' ) ) {
	/**
	 * Build an SVG donut segment path.
	 *
	 * @param float $cx        Centre x.
	 * @param float $cy        Centre y.
	 * @param float $radius    Outer radius.
	 * @param float $thickness Ring thickness.
	 * @param float $start_deg Start angle, degrees clockwise from 12 o'clock.
	 * @param float $end_deg   End angle.
	 * @return string Path `d` attribute, empty for a zero sweep.
	 */
	function designsetgo_chart_arc_path( $cx, $cy, $radius, $thickness, $start_deg, $end_deg ) {
		$sweep = (float) $end_deg - (float) $start_deg;

		if ( abs( $sweep ) < 0.01 ) {
			return '';
		}

		// Clamp: a full circle cannot be drawn as one arc.
		$sweep   = min( $sweep, 359.99 );
		$end_deg = (float) $start_deg + $sweep;
		$inner   = max( 0.0, (float) $radius - (float) $thickness );

		$point = function ( $r, $deg ) use ( $cx, $cy ) {
			$rad = deg2rad( (float) $deg - 90 );
			return array(
				round( (float) $cx + $r * cos( $rad ), 3 ),
				round( (float) $cy + $r * sin( $rad ), 3 ),
			);
		};

		list( $ox1, $oy1 ) = $point( $radius, $start_deg );
		list( $ox2, $oy2 ) = $point( $radius, $end_deg );
		list( $ix2, $iy2 ) = $point( $inner, $end_deg );
		list( $ix1, $iy1 ) = $point( $inner, $start_deg );

		$large = $sweep > 180 ? 1 : 0;

		return sprintf(
			'M %s %s A %s %s 0 %d 1 %s %s L %s %s A %s %s 0 %d 0 %s %s Z',
			$ox1,
			$oy1,
			$radius,
			$radius,
			$large,
			$ox2,
			$oy2,
			$ix2,
			$iy2,
			$inner,
			$inner,
			$large,
			$ix1,
			$iy1
		);
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test:php -- --filter Test_Chart_Geometry`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/blocks/chart/chart-geometry.php tests/phpunit/test-chart-geometry.php
git commit -m "feat: add chart geometry helpers"
```

---

### Task 2: Block metadata

**Files:**
- Create: `src/blocks/chart/block.json`

**Interfaces:**
- Produces: `designsetgo/chart` with a `data` array of `{ label, value }` rows and a
  `metaKey` escape hatch for dynamic data.

- [ ] **Step 1: Write `block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "designsetgo/chart",
	"version": "1.0.0",
	"title": "Chart",
	"category": "designsetgo",
	"description": "Display data as a bar, line, or donut chart. No JavaScript required.",
	"keywords": [ "chart", "graph", "data", "bar", "donut", "statistics" ],
	"textdomain": "designsetgo",
	"icon": "chart-bar",
	"usesContext": [ "postId", "postType" ],
	"supports": {
		"anchor": true,
		"align": [ "wide", "full" ],
		"html": false,
		"spacing": { "margin": true, "padding": true },
		"typography": {
			"fontSize": true,
			"__experimentalFontFamily": true
		},
		"color": {
			"background": true,
			"text": true,
			"__experimentalDefaultControls": { "text": true }
		}
	},
	"attributes": {
		"chartType": { "type": "string", "default": "bar" },
		"data": {
			"type": "array",
			"default": [],
			"items": { "type": "object" }
		},
		"dataSource": { "type": "string", "default": "manual" },
		"metaKey": { "type": "string", "default": "" },
		"height": { "type": "number", "default": 240 },
		"showValues": { "type": "boolean", "default": true },
		"showLegend": { "type": "boolean", "default": true },
		"showGrid": { "type": "boolean", "default": true },
		"palette": {
			"type": "array",
			"default": [],
			"items": { "type": "string" }
		},
		"label": { "type": "string", "default": "" }
	},
	"example": {
		"attributes": {
			"chartType": "bar",
			"data": [
				{ "label": "Q1", "value": 42 },
				{ "label": "Q2", "value": 68 },
				{ "label": "Q3", "value": 55 }
			]
		}
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./index.css",
	"style": "file:./style-index.css",
	"render": "file:./render.php"
}
```

- [ ] **Step 2: Validate**

Run: `npx wp-scripts test-unit-js tests/unit/block-schema-validation.test.js`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add src/blocks/chart/block.json
git commit -m "feat: add chart block metadata"
```

---

### Task 3: Server render

**Files:**
- Create: `src/blocks/chart/render.php`
- Test: `tests/phpunit/test-chart-render.php`

**Interfaces:**
- Consumes: the geometry helpers (Task 1) and the attributes (Task 2).
- Produces: `designsetgo_render_chart( array $attributes, string $content, WP_Block $block ): string`.

- [ ] **Step 1: Write the failing test**

```php
<?php
/**
 * Chart render tests.
 *
 * @package DesignSetGo
 */

class Test_Chart_Render extends WP_UnitTestCase {

	private function render( array $attributes ) {
		return do_blocks(
			'<!-- wp:designsetgo/chart ' . wp_json_encode( $attributes ) . ' /-->'
		);
	}

	private function sample() {
		return array(
			array(
				'label' => 'Q1',
				'value' => 10,
			),
			array(
				'label' => 'Q2',
				'value' => 20,
			),
		);
	}

	public function test_bar_chart_renders_one_rect_per_row() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
			)
		);
		$this->assertSame( 2, substr_count( $html, '<rect class="dsgo-chart__bar"' ) );
	}

	public function test_line_chart_renders_a_polyline() {
		$html = $this->render(
			array(
				'chartType' => 'line',
				'data'      => $this->sample(),
			)
		);
		$this->assertStringContainsString( '<polyline', $html );
	}

	public function test_donut_chart_renders_one_path_per_row() {
		$html = $this->render(
			array(
				'chartType' => 'donut',
				'data'      => $this->sample(),
			)
		);
		$this->assertSame( 2, substr_count( $html, '<path class="dsgo-chart__slice"' ) );
	}

	public function test_always_emits_an_accessible_data_table() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
			)
		);
		$this->assertStringContainsString( '<table', $html );
		$this->assertStringContainsString( 'screen-reader-text', $html );
		$this->assertStringContainsString( 'Q1', $html );
	}

	public function test_svg_is_hidden_from_assistive_tech_since_the_table_carries_the_data() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => $this->sample(),
			)
		);
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
	}

	public function test_escapes_a_malicious_label() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => array(
					array(
						'label' => '<script>alert(1)</script>',
						'value' => 5,
					),
				),
			)
		);
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_ignores_a_non_numeric_value() {
		$html = $this->render(
			array(
				'chartType' => 'bar',
				'data'      => array(
					array(
						'label' => 'Bad',
						'value' => 'not a number',
					),
				),
			)
		);
		$this->assertStringNotContainsString( 'not a number', $html );
	}

	public function test_empty_data_renders_nothing() {
		$this->assertSame(
			'',
			trim(
				$this->render(
					array(
						'chartType' => 'bar',
						'data'      => array(),
					)
				)
			)
		);
	}

	public function test_reads_data_from_post_meta_when_the_source_is_meta() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'sales', wp_json_encode( $this->sample() ) );

		$this->go_to( get_permalink( $post_id ) );
		the_post();

		$html = $this->render(
			array(
				'chartType'  => 'bar',
				'dataSource' => 'meta',
				'metaKey'    => 'sales',
			)
		);

		$this->assertStringContainsString( 'Q1', $html );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:php -- --filter Test_Chart_Render`
Expected: FAIL — the block is not registered / renders empty

- [ ] **Step 3: Write `render.php`**

```php
<?php
/**
 * Chart block server render.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/chart-geometry.php';

if ( ! function_exists( 'designsetgo_chart_rows' ) ) {
	/**
	 * Resolve the chart's rows from attributes or post meta.
	 *
	 * @param array         $attributes Block attributes.
	 * @param WP_Block|null $block      Block instance, for post context.
	 * @return array List of array{label: string, value: float}.
	 */
	function designsetgo_chart_rows( array $attributes, $block = null ) {
		$raw = isset( $attributes['data'] ) && is_array( $attributes['data'] )
			? $attributes['data']
			: array();

		if ( 'meta' === ( $attributes['dataSource'] ?? 'manual' ) && ! empty( $attributes['metaKey'] ) ) {
			$post_id = $block->context['postId'] ?? get_the_ID();

			if ( $post_id && is_protected_meta( $attributes['metaKey'], 'post' ) ) {
				return array();
			}

			$stored = $post_id ? get_post_meta( $post_id, $attributes['metaKey'], true ) : '';

			if ( is_string( $stored ) && '' !== $stored ) {
				$decoded = json_decode( $stored, true );
				$raw     = is_array( $decoded ) ? $decoded : array();
			} elseif ( is_array( $stored ) ) {
				$raw = $stored;
			}
		}

		$rows = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['value'] ) || ! is_numeric( $row['value'] ) ) {
				continue;
			}

			$rows[] = array(
				'label' => isset( $row['label'] ) ? (string) $row['label'] : '',
				'value' => (float) $row['value'],
			);
		}

		return $rows;
	}
}

if ( ! function_exists( 'designsetgo_chart_palette' ) ) {
	/**
	 * Resolve the series colours.
	 *
	 * Falls back to theme.json custom properties so charts inherit the site
	 * palette rather than hard-coding brand colours.
	 *
	 * @param array $attributes Block attributes.
	 * @param int   $count      Number of series needed.
	 * @return array List of CSS colour strings.
	 */
	function designsetgo_chart_palette( array $attributes, $count ) {
		$palette = isset( $attributes['palette'] ) && is_array( $attributes['palette'] )
			? array_filter( array_map( 'sanitize_text_field', $attributes['palette'] ) )
			: array();

		if ( empty( $palette ) ) {
			$palette = array(
				'var(--wp--preset--color--primary, #3858e9)',
				'var(--wp--preset--color--secondary, #4ab866)',
				'var(--wp--preset--color--tertiary, #f0b849)',
				'var(--wp--preset--color--accent, #d94f4f)',
			);
		}

		$out = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$out[] = $palette[ $i % count( $palette ) ];
		}

		return $out;
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
	 * @return string Table markup.
	 */
	function designsetgo_chart_data_table( array $rows, $label ) {
		$out = '<table class="screen-reader-text">';

		if ( '' !== $label ) {
			$out .= '<caption>' . esc_html( $label ) . '</caption>';
		}

		$out .= '<thead><tr><th scope="col">' . esc_html__( 'Label', 'designsetgo' )
			. '</th><th scope="col">' . esc_html__( 'Value', 'designsetgo' )
			. '</th></tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$out .= '<tr><th scope="row">' . esc_html( $row['label'] ) . '</th><td>'
				. esc_html( (string) $row['value'] ) . '</td></tr>';
		}

		return $out . '</tbody></table>';
	}
}

if ( ! function_exists( 'designsetgo_render_chart' ) ) {
	/**
	 * Render the chart block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner content, unused.
	 * @param WP_Block $block      Block instance.
	 * @return string Rendered markup.
	 */
	function designsetgo_render_chart( $attributes, $content = '', $block = null ) {
		$rows = designsetgo_chart_rows( (array) $attributes, $block );

		if ( empty( $rows ) ) {
			return '';
		}

		$type    = in_array( $attributes['chartType'] ?? 'bar', array( 'bar', 'line', 'donut' ), true )
			? $attributes['chartType']
			: 'bar';
		$height  = max( 80, min( 800, (int) ( $attributes['height'] ?? 240 ) ) );
		$width   = 600;
		$colors  = designsetgo_chart_palette( (array) $attributes, count( $rows ) );
		$label   = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';
		$values  = wp_list_pluck( $rows, 'value' );
		$bounds  = designsetgo_chart_bounds( $values );
		$svg     = '';

		if ( 'bar' === $type ) {
			$slot = $width / count( $rows );
			$gap  = $slot * 0.2;

			foreach ( array_values( $rows ) as $i => $row ) {
				$bar_h = designsetgo_chart_scale( $row['value'], $bounds['min'], $bounds['max'], 0, $height );
				$svg  .= sprintf(
					'<rect class="dsgo-chart__bar" x="%s" y="%s" width="%s" height="%s" fill="%s"></rect>',
					esc_attr( (string) round( $i * $slot + $gap / 2, 2 ) ),
					esc_attr( (string) round( $height - $bar_h, 2 ) ),
					esc_attr( (string) round( $slot - $gap, 2 ) ),
					esc_attr( (string) round( max( 0, $bar_h ), 2 ) ),
					esc_attr( $colors[ $i ] )
				);
			}
		} elseif ( 'line' === $type ) {
			$svg .= sprintf(
				'<polyline class="dsgo-chart__line" points="%s" fill="none" stroke="%s" stroke-width="2" stroke-linejoin="round"></polyline>',
				esc_attr( designsetgo_chart_line_points( $values, $width, $height, $bounds['min'], $bounds['max'] ) ),
				esc_attr( $colors[0] )
			);
		} else {
			$total  = array_sum( array_map( 'abs', $values ) );
			$cursor = 0.0;
			$radius = min( $width, $height ) / 2 - 4;
			$cx     = $width / 2;
			$cy     = $height / 2;

			foreach ( array_values( $rows ) as $i => $row ) {
				$sweep = $total > 0 ? ( abs( $row['value'] ) / $total ) * 360 : 0;
				$path  = designsetgo_chart_arc_path( $cx, $cy, $radius, $radius * 0.4, $cursor, $cursor + $sweep );
				$cursor += $sweep;

				if ( '' === $path ) {
					continue;
				}

				$svg .= sprintf(
					'<path class="dsgo-chart__slice" d="%s" fill="%s"></path>',
					esc_attr( $path ),
					esc_attr( $colors[ $i ] )
				);
			}
		}

		$legend = '';
		if ( ! empty( $attributes['showLegend'] ) ) {
			$legend = '<ul class="dsgo-chart__legend">';
			foreach ( array_values( $rows ) as $i => $row ) {
				$legend .= sprintf(
					'<li class="dsgo-chart__legend-item"><span class="dsgo-chart__swatch" style="background:%s" aria-hidden="true"></span>%s</li>',
					esc_attr( $colors[ $i ] ),
					esc_html( $row['label'] )
				);
			}
			$legend .= '</ul>';
		}

		$wrapper = get_block_wrapper_attributes(
			array( 'class' => 'dsgo-chart dsgo-chart--' . $type )
		);

		return sprintf(
			'<figure %1$s><svg class="dsgo-chart__canvas" viewBox="0 0 %2$d %3$d" preserveAspectRatio="none" role="presentation" aria-hidden="true" focusable="false">%4$s</svg>%5$s%6$s</figure>',
			$wrapper, // Escaped by get_block_wrapper_attributes().
			(int) $width,
			(int) $height,
			$svg, // Every interpolation above is individually escaped.
			$legend,
			designsetgo_chart_data_table( $rows, $label )
		);
	}
}

echo designsetgo_render_chart( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All interpolations escaped inside the renderer.
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test:php -- --filter Test_Chart_Render`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/blocks/chart/render.php tests/phpunit/test-chart-render.php
git commit -m "feat: add chart block server render"
```

---

### Task 4: Editor

**Files:**
- Create: `src/blocks/chart/components/DataEditor.js`
- Create: `src/blocks/chart/edit.js`
- Create: `src/blocks/chart/index.js`
- Test: `tests/unit/blocks/chart-data-editor.test.js`

**Interfaces:**
- Consumes: the `data` attribute (Task 2).
- Produces: `<DataEditor value onChange />` where `value` is
  `Array<{ label: string, value: number }>`.

- [ ] **Step 1: Write the failing test**

```js
// tests/unit/blocks/chart-data-editor.test.js
import { render, screen, fireEvent } from '@testing-library/react';
import { DataEditor } from '../../../src/blocks/chart/components/DataEditor';

describe( 'DataEditor', () => {
	it( 'renders one label input per row', () => {
		render(
			<DataEditor
				value={ [
					{ label: 'A', value: 1 },
					{ label: 'B', value: 2 },
				] }
				onChange={ () => {} }
			/>
		);
		expect( screen.getAllByLabelText( /label/i ) ).toHaveLength( 2 );
	} );

	it( 'appends an empty row on add', () => {
		const onChange = jest.fn();
		render( <DataEditor value={ [] } onChange={ onChange } /> );
		fireEvent.click( screen.getByRole( 'button', { name: /add row/i } ) );
		expect( onChange ).toHaveBeenCalledWith( [ { label: '', value: 0 } ] );
	} );

	it( 'removes the row at the given index', () => {
		const onChange = jest.fn();
		render(
			<DataEditor
				value={ [
					{ label: 'A', value: 1 },
					{ label: 'B', value: 2 },
				] }
				onChange={ onChange }
			/>
		);
		fireEvent.click( screen.getAllByRole( 'button', { name: /remove row/i } )[ 0 ] );
		expect( onChange ).toHaveBeenCalledWith( [ { label: 'B', value: 2 } ] );
	} );

	it( 'coerces a typed value to a number', () => {
		const onChange = jest.fn();
		render(
			<DataEditor value={ [ { label: 'A', value: 1 } ] } onChange={ onChange } />
		);
		fireEvent.change( screen.getByLabelText( /value/i ), {
			target: { value: '42' },
		} );
		expect( onChange ).toHaveBeenCalledWith( [ { label: 'A', value: 42 } ] );
	} );

	it( 'coerces unparseable input to zero rather than NaN', () => {
		const onChange = jest.fn();
		render(
			<DataEditor value={ [ { label: 'A', value: 1 } ] } onChange={ onChange } />
		);
		fireEvent.change( screen.getByLabelText( /value/i ), {
			target: { value: 'abc' },
		} );
		expect( onChange ).toHaveBeenCalledWith( [ { label: 'A', value: 0 } ] );
	} );
} );
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx wp-scripts test-unit-js tests/unit/blocks/chart-data-editor.test.js`
Expected: FAIL — module not found

- [ ] **Step 3: Write `DataEditor.js`**

```js
/**
 * Chart Block - Data row editor
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import {
	TextControl,
	Button,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';

/**
 * Edit the chart's data rows.
 *
 * @param {Object}   props          Component props.
 * @param {Array}    props.value    Rows.
 * @param {Function} props.onChange Receives the next rows array.
 * @return {Element} The editor.
 */
export function DataEditor( { value, onChange } ) {
	const rows = Array.isArray( value ) ? value : [];

	const update = ( index, patch ) =>
		onChange( rows.map( ( row, i ) => ( i === index ? { ...row, ...patch } : row ) ) );

	return (
		<VStack spacing={ 3 }>
			{ rows.map( ( row, index ) => (
				<HStack key={ index } alignment="bottom" spacing={ 2 }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Label', 'designsetgo' ) }
						value={ row.label ?? '' }
						onChange={ ( label ) => update( index, { label } ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						type="number"
						label={ __( 'Value', 'designsetgo' ) }
						value={ row.value ?? 0 }
						onChange={ ( next ) => {
							const parsed = parseFloat( next );
							update( index, {
								value: Number.isNaN( parsed ) ? 0 : parsed,
							} );
						} }
					/>
					<Button
						isDestructive
						variant="tertiary"
						size="small"
						label={ __( 'Remove row', 'designsetgo' ) }
						onClick={ () =>
							onChange( rows.filter( ( _, i ) => i !== index ) )
						}
					>
						{ __( 'Remove row', 'designsetgo' ) }
					</Button>
				</HStack>
			) ) }

			<Button
				variant="secondary"
				onClick={ () => onChange( [ ...rows, { label: '', value: 0 } ] ) }
			>
				{ __( 'Add row', 'designsetgo' ) }
			</Button>
		</VStack>
	);
}
```

- [ ] **Step 4: Write `edit.js`**

```js
/**
 * Chart Block - Edit
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { SelectControl, RangeControl, ToggleControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { DsgoInspectorPanel } from '../../components/shared';
import { DataEditor } from './components/DataEditor';

const TYPES = [
	{ value: 'bar', label: __( 'Bar', 'designsetgo' ) },
	{ value: 'line', label: __( 'Line', 'designsetgo' ) },
	{ value: 'donut', label: __( 'Donut', 'designsetgo' ) },
];

const SOURCES = [
	{ value: 'manual', label: __( 'Enter data', 'designsetgo' ) },
	{ value: 'meta', label: __( 'Post meta field', 'designsetgo' ) },
];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { chartType, data, dataSource, metaKey, height, showLegend, label } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<DsgoInspectorPanel
					title={ __( 'Settings', 'designsetgo' ) }
					panelName="settings"
					panelId={ clientId }
				>
					<DsgoInspectorPanel.Item
						label={ __( 'Chart type', 'designsetgo' ) }
						hasValue={ () => 'bar' !== chartType }
						onDeselect={ () => setAttributes( { chartType: 'bar' } ) }
						isShownByDefault
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Chart type', 'designsetgo' ) }
							value={ chartType }
							options={ TYPES }
							onChange={ ( value ) => setAttributes( { chartType: value } ) }
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={ __( 'Description', 'designsetgo' ) }
						hasValue={ () => !! label }
						onDeselect={ () => setAttributes( { label: '' } ) }
						isShownByDefault
					>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Description', 'designsetgo' ) }
							value={ label }
							onChange={ ( value ) => setAttributes( { label: value } ) }
							help={ __(
								'Read by screen readers as the data table caption.',
								'designsetgo'
							) }
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={ __( 'Data source', 'designsetgo' ) }
						hasValue={ () => 'manual' !== dataSource }
						onDeselect={ () => setAttributes( { dataSource: 'manual' } ) }
						isShownByDefault
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Data source', 'designsetgo' ) }
							value={ dataSource }
							options={ SOURCES }
							onChange={ ( value ) => setAttributes( { dataSource: value } ) }
						/>
					</DsgoInspectorPanel.Item>

					{ 'meta' === dataSource && (
						<DsgoInspectorPanel.Item
							label={ __( 'Meta key', 'designsetgo' ) }
							hasValue={ () => !! metaKey }
							onDeselect={ () => setAttributes( { metaKey: '' } ) }
							isShownByDefault
						>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Meta key', 'designsetgo' ) }
								value={ metaKey }
								onChange={ ( value ) => setAttributes( { metaKey: value } ) }
								help={ __(
									'The field must hold a JSON array of {label, value} objects.',
									'designsetgo'
								) }
							/>
						</DsgoInspectorPanel.Item>
					) }

					{ 'manual' === dataSource && (
						<DsgoInspectorPanel.Item
							label={ __( 'Data', 'designsetgo' ) }
							hasValue={ () => !! data?.length }
							onDeselect={ () => setAttributes( { data: [] } ) }
							isShownByDefault
						>
							<DataEditor
								value={ data }
								onChange={ ( value ) => setAttributes( { data: value } ) }
							/>
						</DsgoInspectorPanel.Item>
					) }
				</DsgoInspectorPanel>

				<DsgoInspectorPanel
					title={ __( 'Style', 'designsetgo' ) }
					panelName="style"
					panelId={ clientId }
				>
					<DsgoInspectorPanel.Item
						label={ __( 'Height', 'designsetgo' ) }
						hasValue={ () => 240 !== height }
						onDeselect={ () => setAttributes( { height: 240 } ) }
						isShownByDefault
					>
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Height', 'designsetgo' ) }
							value={ height }
							min={ 80 }
							max={ 800 }
							step={ 10 }
							onChange={ ( value ) => setAttributes( { height: value } ) }
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={ __( 'Legend', 'designsetgo' ) }
						hasValue={ () => true !== showLegend }
						onDeselect={ () => setAttributes( { showLegend: true } ) }
						isShownByDefault
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Show legend', 'designsetgo' ) }
							checked={ showLegend }
							onChange={ ( value ) => setAttributes( { showLegend: value } ) }
						/>
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="designsetgo/chart"
					attributes={ attributes }
					EmptyResponsePlaceholder={ () => (
						<p>
							{ __(
								'Add at least one data row to preview the chart.',
								'designsetgo'
							) }
						</p>
					) }
				/>
			</div>
		</>
	);
}
```

- [ ] **Step 5: Write `index.js`**

```js
/**
 * Chart Block
 *
 * @since 1.3.0
 */
import { registerBlockType } from '@wordpress/blocks';

import edit from './edit';
import metadata from './block.json';
import { ICON_COLOR } from '../shared/constants';

import './style.scss';

registerBlockType( metadata.name, {
	...metadata,
	icon: {
		src: (
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
				<path
					d="M4 19h2v-8H4v8zm5 0h2V5H9v14zm5 0h2v-6h-2v6zm5 0h2V9h-2v10z"
					fill="currentColor"
				/>
			</svg>
		),
		foreground: ICON_COLOR,
	},
	edit,
	// Dynamic block: no save(), the server renders it.
	save: () => null,
} );
```

- [ ] **Step 6: Add the `@wordpress/server-side-render` dependency**

```bash
npm install --save-dev @wordpress/server-side-render
```

It is a WordPress-provided script handle at runtime, so it becomes an external — verify
after building that `build/blocks/chart/index.asset.php` lists
`wp-server-side-render` in its dependency array.

- [ ] **Step 7: Run tests**

```bash
npx wp-scripts test-unit-js tests/unit/blocks/chart-data-editor.test.js
npm run build
grep -o "wp-server-side-render" build/blocks/chart/index.asset.php
```

Expected: unit test PASS; the grep prints `wp-server-side-render`.

- [ ] **Step 8: Commit**

```bash
git add src/blocks/chart package.json package-lock.json tests/unit/blocks/chart-data-editor.test.js
git commit -m "feat: add chart block editor"
```

---

### Task 5: Styles and verification

**Files:**
- Create: `src/blocks/chart/style.scss`

- [ ] **Step 1: Write the stylesheet**

```scss
/**
 * Chart Block - Frontend styles
 */

.wp-block-designsetgo-chart {
	margin: 0;

	.dsgo-chart__canvas {
		display: block;
		width: 100%;
		height: auto;
		overflow: visible;
	}

	.dsgo-chart__legend {
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem 1rem;
		margin: 0.75rem 0 0;
		padding: 0;
		list-style: none;
		font-size: 0.875em;
	}

	.dsgo-chart__legend-item {
		display: flex;
		align-items: center;
		gap: 0.4em;
	}

	.dsgo-chart__swatch {
		width: 0.75em;
		height: 0.75em;
		border-radius: 2px;
		flex-shrink: 0;
	}

	&.dsgo-chart--donut .dsgo-chart__canvas {
		max-width: 24rem;
		margin-inline: auto;
	}
}
```

`screen-reader-text` is a core class — do not redefine it.

- [ ] **Step 2: Verify the CSS shipped**

```bash
npm run build
grep -c "dsgo-chart__legend" build/blocks/chart/style-index.css
```

Expected: `1` or more.

- [ ] **Step 3: Register the block server-side**

Run: `grep -n "glob\|blocks_to_register" includes/blocks/class-loader.php | head`
If the loader keeps an explicit list, add `'chart'`. Otherwise nothing to do.

- [ ] **Step 4: Full gate**

```bash
npm run build && npm run lint:js && npm run lint:css && npm run lint:php && npm run test:unit && npm run test:php
```

Expected: all pass.

- [ ] **Step 5: Manual check**

1. Insert Chart, add three rows, confirm the preview updates in the editor.
2. Switch through bar → line → donut. Confirm all three render.
3. View the frontend. In DevTools, confirm **no** JS request is made for the chart.
4. Inspect the DOM: the `<svg>` carries `aria-hidden="true"` and a
   `<table class="screen-reader-text">` follows it with the same numbers.
5. Enter `<script>alert(1)</script>` as a label. Confirm it renders as text.
6. Switch a theme with a different palette and confirm the bars pick up the new
   `--wp--preset--color--*` values.

- [ ] **Step 6: Commit**

```bash
git add src/blocks/chart/style.scss includes/blocks/class-loader.php
git commit -m "style: add chart block styles"
```

---

## Follow-ups, explicitly out of scope

- Multi-series charts (grouped bars, multiple lines). The attribute shape would become
  `series: [{ name, data: [] }]`; do it as a deprecation-free additive change.
- Binding the chart to a `designsetgo/query` result set rather than a single meta field.
- Axis ticks and gridlines. **`showGrid` and `showValues` are declared in the attribute
  schema but no task implements them** — either wire them here or remove them from
  `block.json` before shipping, so the block never exposes a control that does nothing.
- Animated draw-on-scroll, which pairs naturally with the Animation Depth plan's
  SVG path-draw work.
