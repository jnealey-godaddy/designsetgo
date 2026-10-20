<?php
/**
 * Applies save-time extension props to server-rendered blocks.
 *
 * Responsive visibility, max width, grid span, grid mobile order and hover
 * effects write their classes/styles through the JS
 * `blocks.getSaveContent.extraProps` filter. That filter never runs for a
 * dynamic block (save() returns null), so the settings would be stored in the
 * block comment and never reach the frontend. This mirrors each of those JS
 * filters at render time for dynamic blocks only.
 *
 * @package DesignSetGo
 * @since 2.11.1
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/**
 * Render-time mirror of the extraProps-based extensions.
 */
class Dynamic_Block_Extension_Props {

	/**
	 * Blocks that receive hover effects (src/extensions/hover-effects/constants.js).
	 *
	 * @var string[]
	 */
	const HOVER_BLOCKS = array(
		'core/group',
		'core/cover',
		'core/column',
		'core/columns',
		'core/image',
		'core/button',
		'core/buttons',
		'core/media-text',
		'core/post-template',
		'core/query',
	);

	/**
	 * Container blocks that can enable Reveal on Hover
	 * (src/extensions/reveal-control/index.js).
	 *
	 * @var string[]
	 */
	const REVEAL_CONTAINERS = array( 'designsetgo/section', 'designsetgo/row', 'designsetgo/grid' );

	/**
	 * Valid hover effect presets.
	 *
	 * @var string[]
	 */
	const HOVER_EFFECTS = array( 'lift', 'sink', 'grow', 'shrink', 'tilt', 'glow' );

	/**
	 * Top-level tags that render nothing visible, so they never count as a
	 * block's root (a block may print a stylesheet or script before it).
	 *
	 * @var string[]
	 */
	const NON_RENDERING_TAGS = array( 'STYLE', 'SCRIPT', 'LINK', 'META', 'NOSCRIPT', 'TEMPLATE' );

	/**
	 * Register hooks.
	 */
	public function init() {
		// Priority 9: before Assets::maybe_enqueue_frontend_on_render() scans
		// the output for "dsgo-" (see Animation_Defaults_Injector::init()).
		add_filter( 'render_block', array( $this, 'inject' ), 9, 2 );
	}

	/**
	 * Apply extension props to a rendered dynamic block.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string Possibly-modified HTML.
	 */
	public function inject( $block_content, $block ) {
		if ( empty( $block['blockName'] ) || empty( $block['attrs'] ) || ! is_array( $block['attrs'] ) || '' === trim( (string) $block_content ) ) {
			return $block_content;
		}

		$name  = $block['blockName'];
		$attrs = $block['attrs'];

		$classes = array();

		foreach ( array(
			'dsgoHideOnDesktop' => 'dsgo-hide-desktop',
			'dsgoHideOnTablet'  => 'dsgo-hide-tablet',
			'dsgoHideOnMobile'  => 'dsgo-hide-mobile',
		) as $attr => $class ) {
			if ( ! empty( $attrs[ $attr ] ) ) {
				$classes[] = $class;
			}
		}
		if ( $classes && Extension_Attributes::is_block_excluded( $name, Extension_Attributes::get_extension_exclusions( 'responsive' ) ) ) {
			$classes = array();
		}

		$hover = isset( $attrs['dsgoHoverEffect'] ) && is_string( $attrs['dsgoHoverEffect'] ) ? $attrs['dsgoHoverEffect'] : '';
		if ( in_array( $name, self::HOVER_BLOCKS, true ) && in_array( $hover, self::HOVER_EFFECTS, true ) ) {
			$classes[] = 'dsgo-hover-effect';
			$classes[] = 'dsgo-hover-effect--' . $hover;
		}

		$reveal_attrs = array();
		if ( ! Extension_Attributes::is_block_excluded( $name, Extension_Attributes::get_extension_exclusions( 'reveal-control' ) ) ) {
			if ( in_array( $name, self::REVEAL_CONTAINERS, true ) && ! empty( $attrs['enableRevealOnHover'] ) ) {
				$classes[]    = 'dsgo-has-reveal';
				$animation    = isset( $attrs['revealAnimationType'] ) && 'collapse' === $attrs['revealAnimationType'] ? 'collapse' : 'fade';
				$reveal_attrs = array( 'data-reveal-animation' => $animation );
			} elseif ( ! empty( $attrs['dsgoRevealOnHover'] ) ) {
				$classes[] = 'dsgo-reveal-item';
			}
		}

		$max_width = $this->max_width_styles( $name, $attrs );
		$grid      = $this->grid_styles( $name, $attrs );

		if ( ! $classes && ! $max_width && ! $grid ) {
			return $block_content;
		}

		// Static blocks are authored by the save filter and validated against
		// it; only server-rendered blocks are ours to modify.
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( $name );
		if ( ! ( $type instanceof \WP_Block_Type ) || ! $type->is_dynamic() ) {
			return $block_content;
		}

		// The props describe the whole block, so they need one element that is
		// the whole block. Synced patterns, patterns, shortcodes and similar
		// print sibling elements with no wrapper; putting the props on the
		// first of them would hide or size only part of the block.
		$processor = self::root_processor( $block_content );
		if ( null === $processor ) {
			return $block_content;
		}

		foreach ( $classes as $class ) {
			$processor->add_class( $class );
		}
		foreach ( $reveal_attrs as $key => $value ) {
			$processor->set_attribute( $key, $value );
		}

		// Hybrid blocks (render_callback plus save()) already carry the saved
		// styles; never declare a property twice.
		$existing = $processor->get_attribute( 'style' );
		$existing = is_string( $existing ) ? trim( $existing ) : '';
		$append   = array();

		if ( $max_width && ! $processor->has_class( 'dsgo-has-max-width' ) ) {
			$processor->add_class( 'dsgo-has-max-width' );
			$append = $max_width;
		}
		foreach ( $grid as $prop => $value ) {
			if ( false === stripos( $existing, $prop ) ) {
				$append[ $prop ] = $value;
			}
		}

		if ( $append ) {
			$css = '';
			foreach ( $append as $prop => $value ) {
				$css .= $prop . ':' . $value . ';';
			}
			if ( '' !== $existing && ';' !== substr( $existing, -1 ) ) {
				$existing .= ';';
			}
			$processor->set_attribute( 'style', trim( $existing . ' ' . $css ) );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Tag processor positioned on the root element of rendered block HTML, or
	 * null when the HTML is not a single element.
	 *
	 * This is the shared guard for every render-time injector: props describe
	 * the whole block, so they need one element that is the whole block.
	 * Synced patterns, patterns, shortcodes and similar print sibling elements
	 * with no wrapper; putting the props on the first of them would affect only
	 * part of the block. Leading non-rendering tags (a printed <style>) are
	 * skipped.
	 *
	 * @param string $html Rendered block HTML.
	 * @return \WP_HTML_Tag_Processor|null
	 */
	public static function root_processor( $html ) {
		if ( ! self::has_single_root( $html ) ) {
			return null;
		}

		$processor = new \WP_HTML_Tag_Processor( $html );
		do {
			if ( ! $processor->next_tag() ) {
				return null;
			}
		} while ( in_array( $processor->get_tag(), self::NON_RENDERING_TAGS, true ) );

		return $processor;
	}

	/**
	 * Whether rendered HTML is a single element (ignoring non-rendering tags
	 * such as a leading <style>) with no visible text beside it.
	 *
	 * The HTML API only implements part of the spec on WordPress 6.7 and 6.8
	 * (no tables, forms and others), and reports "unsupported" for markup it
	 * can't model. Bailing there would silently skip core/search, core/calendar
	 * and similar, so that case falls back to counting top-level tags with the
	 * tag processor. Genuinely malformed markup still counts as not single.
	 *
	 * @param string $html Rendered block HTML.
	 * @return bool
	 */
	public static function has_single_root( $html ) {
		$processor = \WP_HTML_Processor::create_fragment( $html );
		if ( null === $processor ) {
			return self::has_single_root_fallback( $html );
		}

		$roots = 0;
		$base  = null;
		while ( $processor->next_token() ) {
			$depth = $processor->get_current_depth();
			if ( null === $base ) {
				$base = $depth;
			}
			if ( $depth !== $base || $processor->is_tag_closer() ) {
				continue;
			}
			$type = $processor->get_token_type();
			if ( '#tag' === $type && ! in_array( $processor->get_tag(), self::NON_RENDERING_TAGS, true ) ) {
				++$roots;
			} elseif ( '#text' === $type && '' !== trim( $processor->get_modifiable_text() ) ) {
				return false;
			}
			if ( $roots > 1 ) {
				return false;
			}
		}

		$error = $processor->get_last_error();
		if ( null !== $error ) {
			return self::has_single_root_fallback( $html );
		}

		return 1 === $roots;
	}

	/**
	 * Tag-processor version of the single-root check, for markup the HTML
	 * processor reports as unsupported. Tracks nesting depth by hand and
	 * rejects visible text outside the root.
	 *
	 * @param string $html Rendered block HTML.
	 * @return bool
	 */
	private static function has_single_root_fallback( $html ) {
		static $void = array( 'AREA', 'BASE', 'BR', 'COL', 'EMBED', 'HR', 'IMG', 'INPUT', 'LINK', 'META', 'SOURCE', 'TRACK', 'WBR', 'PARAM' );

		// Visible text beside the root: strip every tag and whatever the
		// non-rendering tags enclose, then require the single element to be all
		// that's left.
		$stripped = preg_replace( '#<(style|script|noscript|template)\b[^>]*>.*?</\1\s*>#is', '', $html );
		$stripped = preg_replace( '#<!--.*?-->#s', '', (string) $stripped );
		if ( ! preg_match( '#^\s*<[a-z]#i', (string) $stripped ) || ! preg_match( '#>\s*$#', (string) $stripped ) ) {
			return false;
		}

		$processor = new \WP_HTML_Tag_Processor( (string) $stripped );
		$depth     = 0;
		$roots     = 0;
		while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag = $processor->get_tag();
			if ( in_array( $tag, self::NON_RENDERING_TAGS, true ) ) {
				continue;
			}
			if ( $processor->is_tag_closer() ) {
				--$depth;
				continue;
			}
			if ( 0 === $depth ) {
				++$roots;
				if ( $roots > 1 ) {
					return false;
				}
			}
			if ( ! in_array( $tag, $void, true ) && ! $processor->has_self_closing_flag() ) {
				++$depth;
			}
		}

		// A closer for something never opened would drive depth negative;
		// require the tags to balance.
		return 1 === $roots && 0 === $depth;
	}

	/**
	 * Inline declarations for the max-width extension.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Block attributes.
	 * @return array<string,string> Property => value.
	 */
	private function max_width_styles( $name, array $attrs ) {
		if ( empty( $attrs['dsgoMaxWidth'] ) || ! is_string( $attrs['dsgoMaxWidth'] ) ) {
			return array();
		}
		if ( Extension_Attributes::is_block_excluded( $name, Extension_Attributes::get_extension_exclusions( 'max-width' ) ) ) {
			return array();
		}

		$width = trim( $attrs['dsgoMaxWidth'] );
		// Allow lengths, percentages and CSS math functions only.
		if ( ! preg_match( '/^[A-Za-z0-9\s.%(),+*\/_-]+$/', $width ) || preg_match( '/url|expression|import/i', $width ) ) {
			return array();
		}

		$align      = isset( $attrs['align'] ) ? $attrs['align'] : '';
		$text_align = isset( $attrs['textAlign'] ) ? $attrs['textAlign'] : '';
		$left       = 'auto';
		$right      = 'auto';
		if ( 'left' === $text_align || 'left' === $align ) {
			$left = '0';
		} elseif ( 'right' === $text_align || 'right' === $align ) {
			$right = '0';
		}

		return array(
			'max-width'    => $width,
			'margin-left'  => $left,
			'margin-right' => $right,
		);
	}

	/**
	 * Inline declarations for grid span and grid mobile order.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Block attributes.
	 * @return array<string,string> Property => value.
	 */
	private function grid_styles( $name, array $attrs ) {
		$out = array();

		if ( ! Extension_Attributes::is_block_excluded( $name, Extension_Attributes::get_extension_exclusions( 'grid-span' ) ) ) {
			if ( isset( $attrs['dsgoColumnSpan'] ) && is_numeric( $attrs['dsgoColumnSpan'] ) && $attrs['dsgoColumnSpan'] > 1 ) {
				$out['grid-column'] = 'span ' . (int) $attrs['dsgoColumnSpan'];
			}
			if ( isset( $attrs['dsgoRowSpan'] ) && is_numeric( $attrs['dsgoRowSpan'] ) && $attrs['dsgoRowSpan'] > 1 ) {
				$out['grid-row'] = 'span ' . (int) $attrs['dsgoRowSpan'];
			}
		}

		if ( isset( $attrs['dsgoMobileOrder'] ) && is_numeric( $attrs['dsgoMobileOrder'] )
			&& ! Extension_Attributes::is_block_excluded( $name, Extension_Attributes::get_extension_exclusions( 'grid-mobile-order' ) )
		) {
			$order = max( 0, min( 10, (float) $attrs['dsgoMobileOrder'] ) );
			if ( 1.0 !== $order ) {
				$out['--dsgo-mobile-order'] = (string) $order;
			}
		}

		return $out;
	}
}
