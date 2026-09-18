<?php
/**
 * Shared helpers for the block serializers.
 *
 * These were private statics on Block_Inserter, reachable only from the switch
 * that lived beside them. They are pure functions over attributes - number
 * formatting that matches JavaScript's, colour conversion, the shape-divider
 * renderer, overlay and hover maths - and every per-block serializer needs
 * them, so they move here ahead of the switch itself.
 *
 * Nothing here knows about insertion, posts or abilities. If a helper in this
 * file starts needing that, it belongs back in Block_Inserter.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 */

namespace DesignSetGo\Abilities\Serializers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serializer support helpers.
 */
class Serializer_Support {

	/**
	 * Split a class attribute into individual class names.
	 *
	 * @param string $class_attribute Space-separated class attribute value.
	 * @return array<int, string> Class names.
	 */
	public static function split_class_list( string $class_attribute ): array {
		$parts = preg_split( '/\s+/', $class_attribute, -1, PREG_SPLIT_NO_EMPTY );

		return is_array( $parts ) ? $parts : array();
	}

	/**
	 * Reproduce JavaScript's 32-bit string hash.
	 *
	 * Mirrors hashCode() in src/extensions/custom-css/index.js:
	 *
	 *   hash = ( hash << 5 ) - hash + charCodeAt( i );
	 *   hash = hash & hash;                  // truncate to 32-bit signed
	 *   return Math.abs( hash ).toString( 36 );
	 *
	 * Two details matter. PHP integers are 64-bit, so the wrap to 32-bit signed
	 * has to be done by hand or the values diverge after a few characters. And
	 * charCodeAt() returns UTF-16 code units, not bytes or code points, so the
	 * string is converted to UTF-16LE and read two bytes at a time - otherwise
	 * any non-ASCII character in the CSS produces a different class here than
	 * in the editor.
	 *
	 * @param string $value String to hash.
	 * @return string Base-36 hash, matching the JavaScript output.
	 */
	public static function js_hash_code( string $value ): string {
		$hash  = 0;
		$utf16 = function_exists( 'mb_convert_encoding' )
			? (string) mb_convert_encoding( $value, 'UTF-16LE', 'UTF-8' )
			: $value;
		$length = strlen( $utf16 );
		$step   = function_exists( 'mb_convert_encoding' ) ? 2 : 1;

		for ( $i = 0; $i + $step - 1 < $length; $i += $step ) {
			$char = 2 === $step
				? ( ord( $utf16[ $i ] ) | ( ord( $utf16[ $i + 1 ] ) << 8 ) )
				: ord( $utf16[ $i ] );

			$hash = ( $hash << 5 ) - $hash + $char;

			// Truncate to 32 bits, then reinterpret as signed.
			$hash = $hash & 0xFFFFFFFF;
			if ( $hash & 0x80000000 ) {
				$hash -= 0x100000000;
			}
		}

		return base_convert( (string) abs( $hash ), 10, 36 );
	}

	/**
	 * Mirror JavaScript's `value || fallback` for a numeric attribute
	 * rendered into a data-attribute string, where 0 is falsy exactly as it
	 * is in JS (and so, unlike a plain empty/absent check, falls back too).
	 *
	 * @param mixed  $value    Attribute value.
	 * @param string $fallback Fallback string used when $value is falsy.
	 * @return string Rendered attribute value.
	 */
	public static function js_truthy_numeric( $value, string $fallback ): string {
		if ( is_numeric( $value ) && 0.0 !== (float) $value ) {
			return self::format_js_number( (float) $value );
		}

		return $fallback;
	}

	/**
	 * Render a numeric attribute the way the editor would serialize it.
	 *
	 * Truncation via intval() here was silently dropping every fractional
	 * value: a slider
	 * stored slidesPerView 1.2 in its block comment while the generated HTML
	 * said 1, so the block failed validation the moment it was opened. Integers
	 * still render as integers, so nothing that was already correct changes.
	 *
	 * @param mixed     $value   Attribute value.
	 * @param int|float $default Fallback when the value is not numeric.
	 * @return int|float Numeric value.
	 */
	public static function numeric_attribute( $value, $default = 0 ) {
		if ( ! is_numeric( $value ) ) {
			return $default;
		}

		$number = +$value;

		// A float that lands exactly on an integer renders without a decimal
		// point, which is what JSON.stringify() does in the editor too.
		if ( is_float( $number ) && (float) (int) $number === $number ) {
			return (int) $number;
		}

		return $number;
	}

	/**
	 * Convert CSS var() syntax to WordPress shorthand for block comment serialization.
	 *
	 * WordPress stores preset values as `var:preset|spacing|60` in block comments,
	 * which gets converted to `var(--wp--preset--spacing--60)` at render time.
	 *
	 * @param string $value CSS value that may contain var(--wp--preset--*) syntax.
	 * @return string Converted value using WordPress shorthand, or original value.
	 */
	public static function css_var_to_wp_shorthand( string $value ): string {
		if ( preg_match( '/^var\(--wp--preset--([a-zA-Z]+)--(.+)\)$/', $value, $matches ) ) {
			return 'var:preset|' . $matches[1] . '|' . $matches[2];
		}
		return $value;
	}

	/**
	 * Render a float the way JavaScript's String() would.
	 *
	 * @param float $value Value to format.
	 * @return string Formatted number.
	 */
	public static function format_js_number( float $value ): string {
		// JSON/JS print 0.8 as "0.8" and 1 as "1"; PHP's default float cast
		// would give "0.8" but also "1" for 1.0, which matches. Trailing zeros
		// are trimmed so 0.50 does not serialize differently from save().
		$formatted = rtrim( rtrim( sprintf( '%.10F', $value ), '0' ), '.' );

		return '' === $formatted ? '0' : $formatted;
	}

	/**
	 * Clamp a value into a range, falling back when it is not a finite number.
	 *
	 * Mirrors the clamp() helpers in the Text Path save path.
	 *
	 * @param mixed     $value    Value to clamp.
	 * @param int|float $minimum  Lower bound.
	 * @param int|float $maximum  Upper bound.
	 * @param int|float $fallback Value used when $value is not numeric.
	 * @return int|float Clamped value.
	 */
	public static function clamp_number( $value, $minimum, $maximum, $fallback ) {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return self::numeric_attribute( max( $minimum, min( $maximum, (float) $value ) ) );
	}

	/**
	 * Filter a Text Path colour through the same allowlist save() applies.
	 *
	 * @param mixed $color Colour value.
	 * @return string The colour, or an empty string when it is not allowed.
	 */
	public static function safe_text_path_color( $color ): string {
		return self::safe_hotspot_color( $color );
	}

	/**
	 * Filter a Text Path URL through the same allowlist save() applies.
	 *
	 * Mirrors getSafeTextPathUrl(): http, https, mailto, tel, and root-relative
	 * or fragment URLs.
	 *
	 * @param mixed $url URL value.
	 * @return string The URL, or an empty string when it is not allowed.
	 */
	public static function safe_text_path_url( $url ): string {
		if ( ! is_string( $url ) ) {
			return '';
		}

		$trimmed = trim( $url );

		return preg_match( '#^(?:https?:|mailto:|tel:|/|\#)#i', $trimmed ) ? $trimmed : '';
	}

	/**
	 * Resolve Text Path shape data.
	 *
	 * Mirrors getTextPathData() in src/utils/svg-paths.js for the built-in
	 * shapes. `custom` is not resolved here - it is refused before serialization.
	 *
	 * @param string $path_type Shape slug.
	 * @param mixed  $arc_size  Arc size, used only by the arc shape.
	 * @return array{viewBox: string, d: string} Shape data.
	 */
	public static function get_text_path_data( string $path_type, $arc_size ): array {
		$shapes = array(
			'wave'   => array(
				'viewBox' => '0 0 1000 200',
				'd'       => 'M 0 100 C 250 0 750 200 1000 100',
			),
			'arc'    => array(
				'viewBox' => '0 0 1000 200',
				'd'       => 'M 0 200 Q 500 0 1000 200',
			),
			'circle' => array(
				'viewBox' => '0 0 1000 1000',
				'd'       => 'M 500 0 A 500 500 0 1 1 499.9 0',
			),
			'line'   => array(
				'viewBox' => '0 0 1000 200',
				'd'       => 'M 0 100 L 1000 100',
			),
			'oval'   => array(
				'viewBox' => '0 0 1000 500',
				'd'       => 'M 500 0 A 500 250 0 1 1 499.9 0',
			),
			'spiral' => array(
				'viewBox' => '0 0 1000 1000',
				'd'       => 'M 500 500 C 500 250 850 250 850 500 C 850 850 150 850 150 500 C 150 50 950 50 950 500',
			),
		);

		if ( 'arc' === $path_type ) {
			// getTextPathArcSize(): blank means 100, otherwise clamp and round.
			$size = ( null === $arc_size || '' === $arc_size || ! is_numeric( $arc_size ) )
				? 100
				: (int) round( max( 0, min( 100, (float) $arc_size ) ) );

			return array(
				'viewBox' => $shapes['arc']['viewBox'],
				'd'       => 'M 0 200 Q 500 ' . ( 200 - $size * 2 ) . ' 1000 200',
			);
		}

		return $shapes[ $path_type ] ?? $shapes['wave'];
	}

	/**
	 * The alignment class useBlockProps.save() would add, if any.
	 *
	 * Mirrors core's addAssignedAlign: the class is emitted only when the value
	 * is one the block actually supports. Driving it off the registered supports
	 * rather than a hardcoded wide/full pair matters for blocks that allow more
	 * (card and accordion accept left/center/right too), where a hardcoded list
	 * silently drops the class and the block fails validation.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Alignment class, or an empty string.
	 */
	public static function align_class( string $block_name, array $attributes ): string {
		$align = isset( $attributes['align'] ) ? (string) $attributes['align'] : '';
		if ( '' === $align ) {
			return '';
		}

		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		$support    = $block_type->supports['align'] ?? false;

		if ( true === $support ) {
			$valid = array( 'left', 'center', 'right', 'wide', 'full' );
		} elseif ( is_array( $support ) ) {
			$valid = $support;
		} else {
			return '';
		}

		return in_array( $align, $valid, true ) ? 'align' . $align : '';
	}

	/**
	 * Padding declarations for a block that skip-serializes padding and
	 * re-applies it to an inner element.
	 *
	 * Icon Button and Modal Trigger both declare
	 * `spacing.__experimentalSkipSerialization: ["padding"]`, so WordPress puts
	 * no padding on the block root and each save() writes it onto the button
	 * instead. get_routed_visual_attributes() cannot cover this: it works from
	 * the Style Engine, and `spacing` also carries margin, which is NOT
	 * skip-serialized and must stay on the root.
	 *
	 * The two blocks differ in one respect, so the caller says which it wants:
	 * Icon Button runs each side through convertPaddingValue() (turning
	 * `var:preset|spacing|40` into a CSS var), while Modal Trigger writes the
	 * value through untouched.
	 *
	 * @param array<string, mixed> $attributes      Block attributes.
	 * @param bool                 $convert_presets Whether to resolve preset shorthand.
	 * @return array<int, string> CSS declarations, in save()'s order.
	 */
	public static function routed_padding_styles( array $attributes, bool $convert_presets ): array {
		$padding = $attributes['style']['spacing']['padding'] ?? null;

		if ( ! is_array( $padding ) ) {
			return array();
		}

		$declarations = array();

		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$value = $padding[ $side ] ?? null;

			// React drops a style property whose value is undefined or an empty
			// string, and convertPaddingValue() returns undefined for a falsy
			// value, so an unset side produces no declaration either way.
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}

			$declarations[] = 'padding-' . $side . ':' .
				( $convert_presets ? self::wp_shorthand_to_css_var( $value ) : $value );
		}

		return $declarations;
	}

	/**
	 * Whether a container block renders an overlay.
	 *
	 * Mirrors the shared JS helper: an explicit overlayColor, or an
	 * `is-style-overlay-*` variation class supplying the colour from its own
	 * stylesheet. Each container adds its own `--has-overlay` marker class when
	 * this is true, and none of them emitted it.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return bool Whether the overlay marker class applies.
	 */
	public static function has_overlay( array $attributes ): bool {
		if ( ! empty( $attributes['overlayColor'] ) ) {
			return true;
		}

		$class_name = isset( $attributes['className'] ) ? (string) $attributes['className'] : '';
		foreach ( self::split_class_list( $class_name ) as $token ) {
			if ( 0 === strpos( $token, 'is-style-overlay-' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Hover and overlay custom properties the container blocks serialize.
	 *
	 * Section, Row and Grid all write the same five custom properties from the
	 * same five attributes, each only when set. None of them were emitted here,
	 * so any container given a hover or overlay colour stored markup save()
	 * would not reproduce.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<int, string> CSS declarations.
	 */
	public static function container_hover_styles( array $attributes ): array {
		$declarations = array();

		$hover_vars = array(
			'hoverBackgroundColor'       => '--dsgo-hover-bg-color',
			'hoverTextColor'             => '--dsgo-hover-text-color',
			'hoverIconBackgroundColor'   => '--dsgo-parent-hover-icon-bg',
			'hoverButtonBackgroundColor' => '--dsgo-parent-hover-button-bg',
		);

		foreach ( $hover_vars as $attribute_name => $custom_property ) {
			$colour = isset( $attributes[ $attribute_name ] ) ? (string) $attributes[ $attribute_name ] : '';
			if ( '' !== $colour ) {
				$declarations[] = $custom_property . ':' . self::convert_color_value_to_css_var( $colour );
			}
		}

		// The overlay writes its opacity alongside the colour, as one unit.
		$overlay = isset( $attributes['overlayColor'] ) ? (string) $attributes['overlayColor'] : '';
		if ( '' !== $overlay ) {
			$declarations[] = '--dsgo-overlay-color:' . self::convert_color_value_to_css_var( $overlay );
			$declarations[] = '--dsgo-overlay-opacity:' . self::overlay_opacity( $attributes );
		}

		return $declarations;
	}

	/**
	 * Resolve `--dsgo-overlay-opacity` for a container block's attributes.
	 *
	 * PHP twin of getOverlayOpacity( overlayColor, overlayOpacity ) in
	 * src/utils/overlay-opacity.js. An explicit integer `overlayOpacity`
	 * percentage wins - clamped to 0-100, as overlayOpacityFraction() does -
	 * and anything else (a float, a numeric string) falls back to the
	 * colour-aware default, exactly as the JS Number.isInteger() check does.
	 * Integers only because n/100 prints identically in PHP and JS for them.
	 *
	 * @param array $attributes Block attributes.
	 * @return string CSS opacity value.
	 */
	public static function overlay_opacity( array $attributes ): string {
		$percent = $attributes['overlayOpacity'] ?? null;
		if ( is_int( $percent ) ) {
			return self::format_js_number( max( 0.0, min( 100.0, (float) $percent ) ) / 100 );
		}

		return self::overlay_opacity_for_color( isset( $attributes['overlayColor'] ) ? (string) $attributes['overlayColor'] : '' );
	}

	/**
	 * Resolve `--dsgo-overlay-opacity` for a container overlay colour.
	 *
	 * PHP twin of getOverlayOpacity() in src/utils/overlay-opacity.js, used by
	 * the Section, Row, Grid and Scroll Accordion Item save() functions. A colour
	 * carrying its own alpha below 1 (`#RGBA`, `#RRGGBBAA`, `rgba(…)`, `hsla(…)`,
	 * `rgb(… / a)` and the other functional notations) is emitted at opacity 1
	 * so its alpha alone sets the translucency; everything else, including
	 * preset slugs and CSS variables, uses the 0.65 default. Must return the
	 * same string as the JS helper for every input.
	 *
	 * @param string $color Overlay colour attribute.
	 * @return string '1' or '0.65'.
	 */
	public static function overlay_opacity_for_color( string $color ): string {
		$alpha = self::declared_color_alpha( $color );

		return ( null !== $alpha && $alpha < 1 ) ? '1' : '0.65';
	}

	/**
	 * Read the alpha channel a colour value declares, when it declares one.
	 *
	 * @param string $color Colour value.
	 * @return float|null Alpha, or null when the value declares none.
	 */
	public static function declared_color_alpha( string $color ): ?float {
		// Same whitespace set and alpha grammar as getDeclaredAlpha() in
		// src/utils/overlay-opacity.js: space, tab, LF, CR, form feed, vertical
		// tab and NBSP; a plain decimal alpha, optionally a percentage.
		$whitespace = '/^[ \t\n\r\f\x{0B}\x{A0}]+|[ \t\n\r\f\x{0B}\x{A0}]+$/u';

		$trimmed = preg_replace( $whitespace, '', $color );
		if ( null === $trimmed ) {
			return null;
		}
		$value = strtolower( $trimmed );

		if ( preg_match( '/^#([0-9a-f]{4}|[0-9a-f]{8})$/D', $value, $hex ) ) {
			return 4 === strlen( $hex[1] )
				? hexdec( $hex[1][3] ) / 15
				: hexdec( substr( $hex[1], 6 ) ) / 255;
		}

		if ( ! preg_match( '/^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\((.*)\)$/D', $value, $fn ) ) {
			return null;
		}

		$args = $fn[1];
		if ( false !== strpos( $args, '/' ) ) {
			$alpha = substr( $args, strrpos( $args, '/' ) + 1 );
		} else {
			$parts = explode( ',', $args );
			if ( 4 !== count( $parts ) ) {
				return null;
			}
			$alpha = $parts[3];
		}

		$alpha = preg_replace( $whitespace, '', $alpha );
		if ( null === $alpha || ! preg_match( '/^([+-]?(?:\d+(?:\.\d*)?|\.\d+))(%?)$/D', $alpha, $match ) ) {
			return null;
		}

		return '%' === $match[2] ? (float) $match[1] / 100 : (float) $match[1];
	}

	/**
	 * Clamp a hotspot coordinate to 0-100 the way save() does.
	 *
	 * @param mixed $value Coordinate value.
	 * @return int|float Clamped coordinate.
	 */
	public static function clamp_hotspot_coordinate( $value ) {
		$number = is_numeric( $value ) ? (float) $value : 50;

		return self::numeric_attribute( max( 0, min( 100, $number ) ) );
	}

	/**
	 * Filter a hotspot URL through the same allowlist save() applies.
	 *
	 * Mirrors getSafeHotspotUrl(): only http, https, mailto and tel survive, so
	 * a rejected URL turns the marker into a <button> in both paths.
	 *
	 * @param mixed $url URL value.
	 * @return string The URL, or an empty string when it is not allowed.
	 */
	public static function safe_hotspot_url( $url ): string {
		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return '';
		}

		$trimmed = trim( $url );
		$scheme  = wp_parse_url( $trimmed, PHP_URL_SCHEME );

		if ( null === $scheme || '' === $scheme ) {
			// Relative URLs resolve against the page, matching the JS helper's
			// use of a base URL.
			return $trimmed;
		}

		return in_array( strtolower( $scheme ), array( 'http', 'https', 'mailto', 'tel' ), true ) ? $trimmed : '';
	}

	/**
	 * Filter a hotspot colour through the same allowlist save() applies.
	 *
	 * Mirrors getSafeHotspotColor() in src/blocks/hotspot-item/utils.js: a value
	 * outside the allowlist is dropped by save(), so emitting it here would
	 * produce a custom property save() never writes.
	 *
	 * @param mixed $color Colour value.
	 * @return string The colour, or an empty string when it is not allowed.
	 */
	public static function safe_hotspot_color( $color ): string {
		if ( ! is_string( $color ) ) {
			return '';
		}

		$value = trim( $color );

		$is_preset     = (bool) preg_match( '/^var:preset\|color\|[a-z0-9-]+$/i', $value );
		$is_hex        = (bool) preg_match( '/^#[0-9a-f]{3,8}$/i', $value );
		$is_functional = (bool) preg_match( '#^(?:rgb|hsl)a?\([0-9.%\s,/+-]+\)$#i', $value );

		return ( $is_preset || $is_hex || $is_functional ) ? $value : '';
	}

	/**
	 * Whether a shape size was explicitly authored.
	 *
	 * Mirrors isExplicitShapeSize() in src/utils/shape-size.js: null, zero and
	 * negatives all mean "inherit the theme token", and serializing them would
	 * write a custom property save() never emits.
	 *
	 * @param mixed $value Attribute value.
	 * @return bool Whether the value is an explicit size.
	 */
	public static function is_explicit_shape_size( $value ): bool {
		return is_numeric( $value ) && is_finite( (float) $value ) && (float) $value > 0;
	}

	/**
	 * Clamp a shape divider size attribute, mirroring normalizeShapeSize() in
	 * src/utils/shape-size.js: anything that is not an explicit, positive,
	 * finite size collapses to null ("inherit the theme token"); an explicit
	 * value is clamped into range.
	 *
	 * @param mixed $value Raw size attribute.
	 * @param float $min   Lower clamp bound.
	 * @param float $max   Upper clamp bound.
	 * @return float|null Clamped size, or null when unset.
	 */
	public static function normalize_shape_size( $value, float $min, float $max ): ?float {
		if ( ! self::is_explicit_shape_size( $value ) ) {
			return null;
		}

		return max( $min, min( $max, (float) $value ) );
	}

	/**
	 * Sanitize a color value the way
	 * src/blocks/section/utils/sanitize-color.js does: CSS custom properties,
	 * hex (3/4/6/8 digit), rgb()/rgba(), hsl()/hsla() (with required `%` on
	 * saturation/lightness), or a bare alphabetic named color. Anything else
	 * — including a malformed value that could break out of an attribute —
	 * is rejected.
	 *
	 * @param string $color Candidate color value.
	 * @return string Sanitized value, or '' when invalid or empty.
	 */
	public static function sanitize_shape_color( string $color ): string {
		$trimmed = trim( $color );
		if ( '' === $trimmed ) {
			return '';
		}

		$patterns = array(
			'/^var\(--[\w-]+(?:,\s*[^)]+)?\)$/i',
			'/^#(?:[\da-f]{3,4}|[\da-f]{6}|[\da-f]{8})$/i',
			'/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*[\d.]+)?\s*\)$/i',
			'/^hsla?\(\s*\d{1,3}\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%\s*(?:,\s*[\d.]+)?\s*\)$/i',
			'/^[a-z]+$/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $trimmed ) ) {
				return $trimmed;
			}
		}

		return '';
	}

	/**
	 * Resolve a shape divider's band color — the color shown beside the
	 * shape, through the CSS mask knockout. Mirrors
	 * shapeDividerTopBandColor / shapeDividerBottomBandColor in save.js
	 * (convertColorToCSSVar) followed by ShapeDivider's own sanitizeColor().
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $prefix     Attribute prefix ('shapeDividerTop' or 'shapeDividerBottom').
	 * @return string Sanitized CSS color, or '' when unset or invalid.
	 */
	public static function shape_divider_band_color( array $attributes, string $prefix ): string {
		$background = $attributes[ $prefix . 'BackgroundColor' ] ?? '';
		if ( ! is_string( $background ) || '' === $background ) {
			return '';
		}

		return self::sanitize_shape_color( self::convert_color_value_to_css_var( $background ) );
	}

	/**
	 * Render a Section block's top or bottom shape divider.
	 *
	 * Mirrors src/blocks/section/components/ShapeDivider.js exactly: the
	 * shape itself is painted by CSS via `mask-image` (see
	 * src/blocks/section/styles/_shape-divider.scss), so this emits only the
	 * marker classes and CSS custom properties the stylesheet reads — no
	 * inline `<svg>`, and no size custom property unless the author set an
	 * explicit value.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $prefix     Attribute prefix ('shapeDividerTop' or 'shapeDividerBottom').
	 * @param string               $position   'top' or 'bottom'.
	 * @return string Divider markup, or '' when no shape is selected.
	 */
	public static function render_shape_divider( array $attributes, string $prefix, string $position ): string {
		$shape = isset( $attributes[ $prefix ] ) && is_string( $attributes[ $prefix ] ) ? $attributes[ $prefix ] : '';
		if ( '' === $shape ) {
			return '';
		}

		$safe_height = self::normalize_shape_size( $attributes[ $prefix . 'Height' ] ?? null, 10, 500 );
		$safe_width  = self::normalize_shape_size( $attributes[ $prefix . 'Width' ] ?? null, 100, 300 );
		$flip_x      = ! empty( $attributes[ $prefix . 'FlipX' ] );
		$flip_y      = ! empty( $attributes[ $prefix . 'FlipY' ] );
		$front       = ! empty( $attributes[ $prefix . 'Front' ] );
		$band_color  = self::shape_divider_band_color( $attributes, $prefix );

		// Bottom dividers flip vertically by default: the shapes are
		// authored with their solid edge at the bottom of the viewBox (i.e.
		// facing the section for a TOP divider), so a bottom divider must
		// flip to face its section. flipY inverts the per-position default.
		$flip_y_active = ( 'bottom' === $position ) ? ! $flip_y : $flip_y;

		$class_parts = array( 'dsgo-shape-divider', 'dsgo-shape-divider--' . $position, 'is-shape-' . $shape );
		if ( $flip_x ) {
			$class_parts[] = 'is-flip-x';
		}
		if ( $flip_y_active ) {
			$class_parts[] = 'is-flip-y';
		}
		if ( $front ) {
			$class_parts[] = 'is-front';
		}

		$style_parts = array();
		if ( null !== $safe_height ) {
			$style_parts[] = '--dsgo-shape-height:' . self::format_js_number( $safe_height ) . 'px';
		}
		if ( null !== $safe_width ) {
			$style_parts[] = '--dsgo-shape-width:' . self::format_js_number( $safe_width ) . '%';
		}
		if ( '' !== $band_color ) {
			$style_parts[] = '--dsgo-shape-band:' . $band_color;
		}

		// Only attach the style attribute when there is something to set, so
		// a default divider serializes as a bare <div> with no empty
		// style="" — matching save()'s styleProps conditional exactly.
		$style_attr = empty( $style_parts ) ? '' : ' style="' . esc_attr( implode( ';', $style_parts ) ) . '"';

		return '<div class="' . esc_attr( implode( ' ', $class_parts ) ) . '"' . $style_attr . ' aria-hidden="true"></div>';
	}

	/**
	 * Match convertPresetToCSSVar for container gaps, including string zero.
	 *
	 * @param mixed $value Gap or a WordPress top/left gap object.
	 * @return string|null CSS gap, or null when JavaScript would return undefined.
	 */
	public static function spacing_gap( $value ): ?string {
		if ( is_array( $value ) ) {
			$top   = $value['top'] ?? null;
			$value = ( null !== $top && '' !== $top && false !== $top && 0 !== $top ) ? $top : ( $value['left'] ?? null );
		}
		if ( null === $value || '' === $value || false === $value || 0 === $value ) {
			return null;
		}
		return self::wp_shorthand_to_css_var( (string) $value );
	}

	/**
	 * Convert WordPress preset shorthand to a CSS custom property reference.
	 *
	 * @param string $value Value to convert.
	 * @return string Converted value.
	 */
	public static function wp_shorthand_to_css_var( string $value ): string {
		if ( preg_match( '/^var:preset\|([a-zA-Z]+)\|(.+)$/', $value, $matches ) ) {
			return 'var(--wp--preset--' . $matches[1] . '--' . $matches[2] . ')';
		}
		return $value;
	}

	/**
	 * Convert a color value to CSS var() syntax, mirroring the JS save helper.
	 *
	 * Supports WordPress preset shorthand (`var:preset|color|slug`), already-
	 * valid CSS values, and bare preset slugs such as `accent-3`.
	 *
	 * @param string $value Color value.
	 * @return string Converted CSS value.
	 */
	public static function convert_color_value_to_css_var( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( 0 === strpos( $value, 'var(--' ) ) {
			return $value;
		}

		if ( 0 === strpos( $value, 'var:preset|' ) ) {
			return self::wp_shorthand_to_css_var( $value );
		}

		if ( preg_match( '/^(#|rgb|hsl|hwb|lab|lch|oklch|oklab|color\(|var\(|url\(|\d)/i', $value ) ) {
			return $value;
		}

		$css_keywords = array(
			'transparent',
			'inherit',
			'initial',
			'unset',
			'revert',
			'revert-layer',
			'currentcolor',
			'none',
			'auto',
			'normal',
		);

		if ( in_array( strtolower( $value ), $css_keywords, true ) ) {
			return $value;
		}

		return 'var(--wp--preset--color--' . $value . ')';
	}

	/**
	 * Recursively convert CSS var() syntax to WordPress shorthand in style arrays.
	 *
	 * @param array<string, mixed> $style_array Style attribute array.
	 * @return array<string, mixed> Converted style array.
	 */
	public static function convert_style_vars( array $style_array ): array {
		foreach ( $style_array as $key => $value ) {
			if ( is_array( $value ) ) {
				$style_array[ $key ] = self::convert_style_vars( $value );
			} elseif ( is_string( $value ) ) {
				$style_array[ $key ] = self::css_var_to_wp_shorthand( $value );
			}
		}
		return $style_array;
	}

	/**
	 * Extract block support classes and inline styles from the style attribute.
	 *
	 * Processes color, spacing, and other block supports into CSS classes and
	 * inline style strings, mirroring what useBlockProps.save() does in JS.
	 *
	 * @param array<string, mixed> $style Style attribute from block.
	 * @return array{classes: string[], styles: string[]} Classes and style declarations.
	 */
	public static function get_block_support_styles( array $style ): array {
		$classes = array();
		$styles  = array();

		// Color support.
		if ( ! empty( $style['color']['background'] ) ) {
			$classes[] = 'has-background';
			$styles[]  = 'background-color:' . esc_attr( $style['color']['background'] );
		}
		if ( ! empty( $style['color']['text'] ) ) {
			$classes[] = 'has-text-color';
			$styles[]  = 'color:' . esc_attr( $style['color']['text'] );
		}
		if ( ! empty( $style['color']['gradient'] ) ) {
			$classes[] = 'has-background';
			$styles[]  = 'background:' . esc_attr( $style['color']['gradient'] );
		}

		// Style Engine can express preset border colors as classes; save() keeps
		// a style.border.color value inline, so preserve that declaration.
		if ( ! empty( $style['border']['color'] ) ) {
			$styles[] = 'border-color:' . esc_attr( self::convert_color_value_to_css_var( $style['border']['color'] ) );
		}

		// Spacing support - padding.
		if ( ! empty( $style['spacing']['padding'] ) ) {
			$padding = $style['spacing']['padding'];
			if ( ! empty( $padding['top'] ) ) {
				$styles[] = 'padding-top:' . esc_attr( self::wp_shorthand_to_css_var( $padding['top'] ) );
			}
			if ( ! empty( $padding['right'] ) ) {
				$styles[] = 'padding-right:' . esc_attr( self::wp_shorthand_to_css_var( $padding['right'] ) );
			}
			if ( ! empty( $padding['bottom'] ) ) {
				$styles[] = 'padding-bottom:' . esc_attr( self::wp_shorthand_to_css_var( $padding['bottom'] ) );
			}
			if ( ! empty( $padding['left'] ) ) {
				$styles[] = 'padding-left:' . esc_attr( self::wp_shorthand_to_css_var( $padding['left'] ) );
			}
		}

		// Spacing support - margin.
		if ( ! empty( $style['spacing']['margin'] ) ) {
			$margin = $style['spacing']['margin'];
			if ( ! empty( $margin['top'] ) ) {
				$styles[] = 'margin-top:' . esc_attr( self::wp_shorthand_to_css_var( $margin['top'] ) );
			}
			if ( ! empty( $margin['bottom'] ) ) {
				$styles[] = 'margin-bottom:' . esc_attr( self::wp_shorthand_to_css_var( $margin['bottom'] ) );
			}
		}

		// Dimensions support.
		if ( ! empty( $style['dimensions']['minHeight'] ) ) {
			$styles[] = 'min-height:' . esc_attr( self::wp_shorthand_to_css_var( $style['dimensions']['minHeight'] ) );
		}

		return array(
			'classes' => $classes,
			'styles'  => $styles,
		);
	}

	/**
	 * Visual support classes and styles for a block that routes them inward.
	 *
	 * Some blocks skip-serialize their visual supports on the block root and
	 * re-apply them to an inner element - Icon Button's root is a positioning
	 * wrapper, so its colours belong on the <a> inside. For those,
	 * apply_block_support_attributes() correctly returns nothing (WordPress is
	 * told to skip), and the values have to be resolved here instead. Without
	 * this the attributes were stored in the block comment and no matching
	 * class ever reached the markup.
	 *
	 * Mirrors the getColorClassesAndStyles / getTypographyClassesAndStyles /
	 * getBorderClassesAndStyles helpers the save() functions use.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array{classes: array<int, string>, styles: array<int, string>} Classes and declarations.
	 */
	public static function get_routed_visual_attributes( array $attributes ): array {
		$classes = array();
		$styles  = array();

		// Preset attributes become `has-*` classes; the second entry is the
		// companion flag class WordPress adds alongside.
		$preset_classes = array(
			'textColor'       => array( 'has-%s-color', 'has-text-color' ),
			'backgroundColor' => array( 'has-%s-background-color', 'has-background' ),
			'gradient'        => array( 'has-%s-gradient-background', 'has-background' ),
			'fontSize'        => array( 'has-%s-font-size', null ),
			'fontFamily'      => array( 'has-%s-font-family', null ),
			'borderColor'     => array( 'has-%s-border-color', 'has-border-color' ),
		);

		foreach ( $preset_classes as $attribute => $definition ) {
			$value = $attributes[ $attribute ] ?? '';
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}

			$classes[] = sprintf( $definition[0], $value );
			if ( null !== $definition[1] ) {
				$classes[] = $definition[1];
			}
		}

		$style = ( isset( $attributes['style'] ) && is_array( $attributes['style'] ) ) ? $attributes['style'] : array();

		// Custom values get the flag class without a preset class.
		if ( ! empty( $style['color']['text'] ) ) {
			$classes[] = 'has-text-color';
		}
		if ( ! empty( $style['color']['background'] ) || ! empty( $style['color']['gradient'] ) ) {
			$classes[] = 'has-background';
		}
		if ( ! empty( $style['border']['color'] ) ) {
			$classes[] = 'has-border-color';
		}

		$routed = array_intersect_key(
			$style,
			array_flip( array( 'color', 'typography', 'border', 'shadow' ) )
		);

		if ( ! empty( $routed ) && function_exists( 'wp_style_engine_get_styles' ) ) {
			$engine = wp_style_engine_get_styles( $routed );
			// JS border support retains an inline preset color as well as its flag class.
			if ( ! empty( $routed['border']['color'] ) ) {
				$engine['declarations']['border-color'] = self::convert_color_value_to_css_var( $routed['border']['color'] );
			}
			foreach ( $engine['declarations'] as $property => $value ) {
				$styles[] = $property . ':' . $value;
			}
		}

		return array(
			'classes' => array_values( array_unique( $classes ) ),
			'styles'  => $styles,
		);
	}
}
