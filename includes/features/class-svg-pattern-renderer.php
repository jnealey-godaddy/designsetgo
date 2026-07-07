<?php
/**
 * SVG Pattern Renderer
 *
 * Generates SVG background patterns server-side via render_block filter.
 * Reads data attributes from saved block HTML and injects the CSS custom
 * property for the SVG data URI, replacing the client-side inline version.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

namespace DesignSetGo;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * SVG Pattern Renderer class.
 */
class SVG_Pattern_Renderer {

	/**
	 * Cached pattern data.
	 *
	 * @var array|null
	 */
	private $patterns = null;

	/**
	 * Per-request SVG generation cache.
	 *
	 * Keyed by "{pattern_type}:{color}:{opacity}:{scale}" to avoid
	 * regenerating identical SVGs on pages with repeated blocks.
	 *
	 * @var array<string, array{url: string, size: string}>
	 */
	private $svg_cache = array();

	/**
	 * Per-request cache of the resolved inherited preset.
	 *
	 * The theme preset can't change within a request, so resolve it once
	 * rather than per inheriting block (mirrors the get_patterns() cache).
	 *
	 * @var array{type:string,color:string,opacity:float,scale:float}|null
	 */
	private $inherited_preset = null;

	/**
	 * In-plugin fallback preset for inherited SVG patterns (mirrors the JS
	 * INHERIT_FALLBACK in constants.js). Used when the theme sets nothing.
	 *
	 * @var array{type:string,color:string,opacity:float,scale:float}
	 */
	private const INHERIT_FALLBACK = array(
		'type'    => 'dot-grid',
		'color'   => '#9c92ac',
		'opacity' => 0.4,
		'scale'   => 1.0,
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'render_block', array( $this, 'inject_svg_pattern' ), 10, 2 );
	}

	/**
	 * Lazy-load pattern definitions.
	 *
	 * @return array Pattern data keyed by pattern ID.
	 */
	private function get_patterns() {
		if ( null === $this->patterns ) {
			$this->patterns = \designsetgo_get_svg_pattern_data();
		}
		return $this->patterns;
	}

	/**
	 * Resolve the inherited SVG pattern preset from theme global settings,
	 * applying per-field in-plugin fallbacks.
	 *
	 * @param array $patterns Known pattern definitions (allowlist for type).
	 * @return array{type:string,color:string,opacity:float,scale:float}
	 */
	private function resolve_inherited_pattern( $patterns ) {
		if ( null !== $this->inherited_preset ) {
			return $this->inherited_preset;
		}

		$preset = function_exists( 'wp_get_global_settings' )
			? wp_get_global_settings( array( 'custom', 'designsetgo', 'svgPattern' ) )
			: null;
		if ( ! is_array( $preset ) ) {
			$preset = array();
		}

		$type = isset( $preset['type'] ) && is_string( $preset['type'] ) && isset( $patterns[ $preset['type'] ] )
			? $preset['type']
			: self::INHERIT_FALLBACK['type'];

		$color = isset( $preset['color'] ) && is_string( $preset['color'] ) && '' !== $preset['color']
			? $preset['color']
			: self::INHERIT_FALLBACK['color'];

		// Match the JS resolver's strict numeric check: accept only real
		// int/float values (theme.json numbers parse as such), not numeric
		// strings, so editor preview and frontend never diverge.
		$opacity = isset( $preset['opacity'] ) && ( is_int( $preset['opacity'] ) || is_float( $preset['opacity'] ) )
			? (float) $preset['opacity']
			: self::INHERIT_FALLBACK['opacity'];

		$scale = isset( $preset['scale'] ) && ( is_int( $preset['scale'] ) || is_float( $preset['scale'] ) )
			? (float) $preset['scale']
			: self::INHERIT_FALLBACK['scale'];

		$this->inherited_preset = array(
			'type'    => $type,
			'color'   => $color,
			'opacity' => $opacity,
			'scale'   => $scale,
		);

		return $this->inherited_preset;
	}

	/**
	 * Resolve a CSS variable preset color to its hex value.
	 *
	 * CSS variables cannot be used inside SVG data URIs because the SVG
	 * is an external document that doesn't inherit the page's CSS custom
	 * properties. This method extracts the slug from either the CSS-var form
	 * "var(--wp--preset--color--{slug})" or the block-attribute shorthand
	 * "var:preset|color|{slug}" and looks up the actual hex value in the
	 * global settings palette.
	 *
	 * @param string $color Color value, possibly a CSS variable.
	 * @return string Resolved hex color or the original value. Unresolved preset
	 *               references pass through unchanged; is_valid_color() catches
	 *               them downstream and substitutes the pattern default.
	 */
	private function resolve_color_value( $color ) {
		return designsetgo_resolve_preset_color( $color );
	}

	/**
	 * Validate a CSS color value to prevent SVG attribute injection.
	 *
	 * Mirrors the JS isValidColor() function in patterns.js.
	 *
	 * @param string $color Color value to validate.
	 * @return bool True if the color is safe.
	 */
	private function is_valid_color( $color ) {
		if ( ! is_string( $color ) || '' === $color ) {
			return false;
		}

		$color = trim( $color );

		// Hex: #rgb, #rgba, #rrggbb, #rrggbbaa.
		if ( preg_match( '/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{4}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/', $color ) ) {
			return true;
		}

		// rgb() / rgba().
		if ( preg_match( '/^rgba?\([^)]+\)$/', $color ) ) {
			return true;
		}

		// hsl() / hsla().
		if ( preg_match( '/^hsla?\([^)]+\)$/', $color ) ) {
			return true;
		}

		// Named colors (letters only).
		if ( preg_match( '/^[a-zA-Z]+$/', $color ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Build SVG markup for a pattern definition.
	 *
	 * Mirrors the JS buildPatternSvg() function in patterns.js.
	 *
	 * @param array  $pattern Pattern definition with width, height, paths.
	 * @param string $color   Fill/stroke color.
	 * @param float  $opacity Fill/stroke opacity (0-1).
	 * @return string Complete SVG markup.
	 */
	private function build_pattern_svg( $pattern, $color, $opacity ) {
		$width  = max( 1, (int) $pattern['width'] );
		$height = max( 1, (int) $pattern['height'] );

		// Sanitize color and escape for use in attributes.
		$safe_color = esc_attr( $this->is_valid_color( $color ) ? $color : '#9c92ac' );

		// Clamp opacity.
		$safe_opacity = max( 0, min( 1, (float) $opacity ) );
		if ( 0.0 === $safe_opacity ) {
			$safe_opacity = 0.4;
		}

		// Allowlists for SVG attribute values.
		$valid_linecaps  = array( 'butt', 'round', 'square' );
		$valid_fillrules = array( 'nonzero', 'evenodd' );

		$path_elements = '';

		foreach ( $pattern['paths'] as $p ) {
			if ( ! isset( $p['d'] ) || ! is_string( $p['d'] ) ) {
				continue;
			}

			$attrs = 'd="' . esc_attr( $p['d'] ) . '"';

			if ( ! empty( $p['stroke'] ) ) {
				// Stroke-based path.
				$attrs .= ' fill="none"';
				$attrs .= ' stroke="' . $safe_color . '"';
				$attrs .= ' stroke-opacity="' . $safe_opacity . '"';
				$sw     = isset( $p['strokeWidth'] ) ? max( 0.5, min( 10, (float) $p['strokeWidth'] ) ) : 1;
				$attrs .= ' stroke-width="' . $sw . '"';
				if ( ! empty( $p['strokeLinecap'] ) && in_array( $p['strokeLinecap'], $valid_linecaps, true ) ) {
					$attrs .= ' stroke-linecap="' . esc_attr( $p['strokeLinecap'] ) . '"';
				}
			} else {
				// Fill-based path.
				$attrs .= ' fill="' . $safe_color . '"';
				$attrs .= ' fill-opacity="' . $safe_opacity . '"';
				if ( ! empty( $p['fillRule'] ) && in_array( $p['fillRule'], $valid_fillrules, true ) ) {
					$attrs .= ' fill-rule="' . esc_attr( $p['fillRule'] ) . '"';
				}
			}

			// Per-path opacity multiplier.
			if ( isset( $p['opacity'] ) && is_numeric( $p['opacity'] ) ) {
				$path_opacity = max( 0, min( 1, (float) $p['opacity'] ) );
				$attrs       .= ' opacity="' . $path_opacity . '"';
			}

			$path_elements .= '<path ' . $attrs . '/>';
		}

		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">' . $path_elements . '</svg>';
	}

	/**
	 * Encode SVG markup as a CSS url() data URI.
	 *
	 * Uses rawurlencode() to match JS encodeURIComponent() behavior.
	 *
	 * @param string $svg Raw SVG markup.
	 * @return string CSS url() value with encoded data URI.
	 */
	private function encode_svg( $svg ) {
		return 'url("data:image/svg+xml,' . rawurlencode( $svg ) . '")';
	}

	/**
	 * Inject SVG pattern CSS custom property into block output.
	 *
	 * Reads data attributes from saved block HTML, generates the SVG
	 * server-side, and injects/replaces the --dsgo-svg-pattern-image
	 * CSS variable in the block's inline style.
	 *
	 * @param string $block_content Rendered block content.
	 * @param array  $block         Block data including attrs.
	 * @return string Modified block content.
	 */
	public function inject_svg_pattern( $block_content, $block ) {
		// Fast bail: skip blocks without SVG pattern data attribute.
		if ( false === strpos( $block_content, 'data-dsgo-svg-pattern' ) ) {
			return $block_content;
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );

		if ( ! $processor->next_tag() ) {
			return $block_content;
		}

		// Read pattern configuration from data attributes.
		$pattern_type = $processor->get_attribute( 'data-dsgo-svg-pattern' );
		if ( empty( $pattern_type ) ) {
			return $block_content;
		}

		$patterns = $this->get_patterns();

		if ( 'inherit' === $pattern_type ) {
			$preset       = $this->resolve_inherited_pattern( $patterns );
			$pattern_type = $preset['type'];
			$color        = $preset['color'];
			$opacity      = $preset['opacity'];
			$scale        = $preset['scale'];
		} else {
			// Validate pattern type against known patterns (allowlist).
			if ( ! isset( $patterns[ $pattern_type ] ) ) {
				return $block_content;
			}
			$color   = $processor->get_attribute( 'data-dsgo-svg-pattern-color' );
			$color   = $color ? $color : '#9c92ac';
			$opacity = (float) ( $processor->get_attribute( 'data-dsgo-svg-pattern-opacity' ) ?? 0.4 );
			$scale   = (float) ( $processor->get_attribute( 'data-dsgo-svg-pattern-scale' ) ?? 1 );
		}

		// Shared normalization for both paths.
		$color = sanitize_text_field( $this->resolve_color_value( sanitize_text_field( $color ) ) );

		// Clamp values.
		$opacity = max( 0.05, min( 1, $opacity ) );
		$scale   = max( 0.25, min( 4, $scale ) );

		// Use per-request cache to avoid regenerating identical SVGs.
		$cache_key = $pattern_type . ':' . $color . ':' . $opacity . ':' . $scale;

		if ( isset( $this->svg_cache[ $cache_key ] ) ) {
			$bg_url  = $this->svg_cache[ $cache_key ]['url'];
			$bg_size = $this->svg_cache[ $cache_key ]['size'];
		} else {
			// Generate SVG.
			$pattern = $patterns[ $pattern_type ];
			$svg     = $this->build_pattern_svg( $pattern, $color, $opacity );
			$bg_url  = $this->encode_svg( $svg );

			// Calculate background size.
			$bg_size = ( $pattern['width'] * $scale ) . 'px ' . ( $pattern['height'] * $scale ) . 'px';

			$this->svg_cache[ $cache_key ] = array(
				'url'  => $bg_url,
				'size' => $bg_size,
			);
		}

		// Read and modify the style attribute.
		$existing_style = $processor->get_attribute( 'style' ) ?? '';

		// Replace existing --dsgo-svg-pattern-image if present, or append.
		if ( false !== strpos( $existing_style, '--dsgo-svg-pattern-image' ) ) {
			// Replace existing url() value (stops at the first closing paren).
			$existing_style = preg_replace(
				'/--dsgo-svg-pattern-image\s*:\s*url\([^)]*\)\s*;?/',
				'--dsgo-svg-pattern-image:' . $bg_url . ';',
				$existing_style
			);
		} else {
			// Append the CSS variable.
			$existing_style = rtrim( $existing_style, '; ' );
			if ( '' !== $existing_style ) {
				$existing_style .= ';';
			}
			$existing_style .= '--dsgo-svg-pattern-image:' . $bg_url;
		}

		// Replace existing --dsgo-svg-pattern-size if present, or append.
		if ( false !== strpos( $existing_style, '--dsgo-svg-pattern-size' ) ) {
			$existing_style = preg_replace(
				'/--dsgo-svg-pattern-size\s*:\s*[^;]+;?/',
				'--dsgo-svg-pattern-size:' . $bg_size . ';',
				$existing_style
			);
		} else {
			$existing_style = rtrim( $existing_style, '; ' );
			if ( '' !== $existing_style ) {
				$existing_style .= ';';
			}
			$existing_style .= '--dsgo-svg-pattern-size:' . $bg_size;
		}

		// Handle fixed background from block attributes (not in data attributes).
		$block_attrs = $block['attrs'] ?? array();
		if ( isset( $block_attrs['dsgoSvgPatternFixed'] ) && true === $block_attrs['dsgoSvgPatternFixed'] ) {
			if ( false === strpos( $existing_style, '--dsgo-svg-pattern-attachment' ) ) {
				$existing_style = rtrim( $existing_style, '; ' );
				if ( '' !== $existing_style ) {
					$existing_style .= ';';
				}
				$existing_style .= '--dsgo-svg-pattern-attachment:fixed';
			}
		}

		$processor->set_attribute( 'style', $existing_style );

		return $processor->get_updated_html();
	}
}
