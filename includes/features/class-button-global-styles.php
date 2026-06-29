<?php
/**
 * Button Global Styles
 *
 * Generates CSS so single-element button blocks (icon-button, modal-trigger)
 * and form builder submit buttons inherit WordPress Global Styles button
 * settings. WordPress targets `.wp-block-button .wp-block-button__link`
 * (descendant selector), which doesn't match our single-element structure
 * where both classes sit on one element, nor the form submit button which
 * uses `.wp-element-button` without a `.wp-block-button` parent.
 *
 * @package DesignSetGo
 * @since 2.0.3
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Button Global Styles handler.
 */
class Button_Global_Styles {

	/**
	 * Maximum recursion depth for style merging.
	 *
	 * @var int
	 */
	private const MAX_MERGE_DEPTH = 10;

	/**
	 * Allowlist of style keys to merge from Global Styles.
	 *
	 * Only these top-level keys are relevant to button appearance.
	 * Unknown keys (e.g. 'variations', 'elements', 'css', 'blocks')
	 * are ignored to prevent unexpected behavior with future WP updates.
	 *
	 * @var string[]
	 */
	private const ALLOWED_STYLE_KEYS = array(
		'color',
		'border',
		'spacing',
		'typography',
		'shadow',
		':hover',
	);

	/**
	 * Block selectors targeted by this handler.
	 *
	 * @var string[]
	 */
	private const BLOCK_SELECTORS = array(
		'.dsgo-icon-button.wp-block-button__link',
		'.dsgo-modal-trigger.wp-block-button__link',
		'.dsgo-form__submit.wp-element-button',
	);

	/**
	 * Block names that need Global Styles button CSS.
	 *
	 * @var string[]
	 */
	private const BUTTON_BLOCKS = array(
		'designsetgo/icon-button',
		'designsetgo/modal-trigger',
		'designsetgo/form-builder',
	);

	/**
	 * Cached CSS output.
	 *
	 * @var string|null
	 */
	private $cached_css = null;

	/**
	 * Whether frontend CSS has been injected for this request.
	 *
	 * @var bool
	 */
	private $frontend_injected = false;

	/**
	 * Register hooks.
	 */
	public function init() {
		add_filter( 'render_block', array( $this, 'maybe_inject_frontend' ), 10, 2 );
		add_action( 'enqueue_block_assets', array( $this, 'inject_editor' ) );
	}

	/**
	 * Inject Global Styles button CSS when a button block is rendered.
	 *
	 * Uses render_block filter to detect button blocks wherever they appear:
	 * post content, templates, or template parts (e.g. header/footer).
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The full block, including name and attributes.
	 * @return string The unmodified block content.
	 */
	public function maybe_inject_frontend( $block_content, $block ) {
		if ( is_admin() || $this->frontend_injected ) {
			return $block_content;
		}

		if ( ! in_array( $block['blockName'], self::BUTTON_BLOCKS, true ) ) {
			return $block_content;
		}

		$css = $this->get_css();
		if ( empty( $css ) ) {
			$this->frontend_injected = true;
			return $block_content;
		}

		// Attach to frontend stylesheet with fallback if handle is missing.
		if ( wp_style_is( 'designsetgo-frontend', 'registered' ) ) {
			wp_add_inline_style( 'designsetgo-frontend', $css );
		} else {
			wp_register_style( 'designsetgo-button-global-styles', false, array(), DESIGNSETGO_VERSION );
			wp_enqueue_style( 'designsetgo-button-global-styles' );
			wp_add_inline_style( 'designsetgo-button-global-styles', $css );
		}

		$this->frontend_injected = true;
		return $block_content;
	}

	/**
	 * Inject Global Styles button CSS in the block editor.
	 *
	 * Uses enqueue_block_assets which fires in both editor and frontend.
	 * The is_admin() check limits this to the editor context only.
	 */
	public function inject_editor() {
		if ( ! is_admin() ) {
			return;
		}

		$css = $this->get_css();
		if ( empty( $css ) ) {
			return;
		}

		// Attach to an enqueued block handle, or fall back to a dedicated handle.
		// Using 'enqueued' (not 'registered') ensures the inline CSS outputs
		// even when only some button blocks are present on the page.
		if ( wp_style_is( 'designsetgo-icon-button-style', 'enqueued' ) ) {
			wp_add_inline_style( 'designsetgo-icon-button-style', $css );
		} elseif ( wp_style_is( 'designsetgo-modal-trigger-style', 'enqueued' ) ) {
			wp_add_inline_style( 'designsetgo-modal-trigger-style', $css );
		} elseif ( wp_style_is( 'designsetgo-form-builder-style', 'enqueued' ) ) {
			wp_add_inline_style( 'designsetgo-form-builder-style', $css );
		} else {
			wp_register_style( 'designsetgo-button-global-styles-editor', false, array(), DESIGNSETGO_VERSION );
			wp_enqueue_style( 'designsetgo-button-global-styles-editor' );
			wp_add_inline_style( 'designsetgo-button-global-styles-editor', $css );
		}
	}

	/**
	 * Get the generated button CSS, with caching.
	 *
	 * @return string Generated CSS or empty string.
	 */
	private function get_css() {
		if ( null !== $this->cached_css ) {
			return $this->cached_css;
		}

		$this->cached_css = $this->generate_css();
		return $this->cached_css;
	}

	/**
	 * Generate the Global Styles button CSS.
	 *
	 * Reads button styles from two sources and merges them:
	 * 1. Element-level: styles.elements.button (Styles > Elements > Buttons)
	 * 2. Block-level: styles.blocks.core/button (Styles > Blocks > Button)
	 * Block-level styles override element-level, matching WordPress's specificity hierarchy.
	 *
	 * @return string Generated CSS or empty string.
	 */
	private function generate_css() {
		// Element-level button styles (Styles > Elements > Buttons).
		$element_styles = wp_get_global_styles( array( 'elements', 'button' ) );
		if ( ! is_array( $element_styles ) ) {
			$element_styles = array();
		}

		// Block-level core/button styles (Styles > Blocks > Button).
		$block_styles = wp_get_global_styles( array(), array( 'block_name' => 'core/button' ) );
		if ( ! is_array( $block_styles ) ) {
			$block_styles = array();
		}

		// Merge: block-level overrides element-level.
		$merged = $this->merge_styles( $element_styles, $block_styles );

		if ( empty( $merged ) ) {
			return '';
		}

		return $this->build_css( $merged );
	}

	/**
	 * Deep-merge two button style arrays. Values from $block override $element.
	 *
	 * Uses an allowlist of known style keys to prevent unexpected behavior
	 * with future WordPress updates or malicious filter interference.
	 *
	 * @param array $element Element-level styles.
	 * @param array $block   Block-level styles.
	 * @param int   $depth   Current recursion depth (internal).
	 * @return array Merged styles.
	 */
	private function merge_styles( $element, $block, $depth = 0 ) {
		if ( $depth > self::MAX_MERGE_DEPTH ) {
			return $element;
		}

		$merged = $element;

		foreach ( $block as $key => $value ) {
			// At top level, only merge known style keys.
			if ( 0 === $depth && ! in_array( $key, self::ALLOWED_STYLE_KEYS, true ) ) {
				continue;
			}

			if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = $this->merge_styles( $merged[ $key ], $value, $depth + 1 );
			} else {
				$merged[ $key ] = $value;
			}
		}

		return $merged;
	}

	/**
	 * Build CSS rules from merged Global Styles button data.
	 *
	 * Generates CSS at specificity (0,3,0) via `:root .block.wp-block-button__link`
	 * which beats WP's element button rules at (0,1,0). Per-instance inline styles
	 * still win over any class-based specificity.
	 *
	 * @param array $styles Merged button styles from Global Styles.
	 * @return string Generated CSS.
	 */
	private function build_css( $styles ) {
		$declarations = $this->extract_declarations( $styles );

		if ( empty( $declarations ) ) {
			return '';
		}

		$rule = implode( ";\n\t", $declarations );

		// Build selector: :root .dsgo-icon-button.wp-block-button__link, ...
		$selector_parts = array();
		foreach ( self::BLOCK_SELECTORS as $sel ) {
			$selector_parts[] = ':root ' . $sel;
		}
		$selector = implode( ",\n", $selector_parts );

		$css = $selector . " {\n\t" . $rule . ";\n}\n";

		// Handle hover state if present.
		if ( ! empty( $styles[':hover'] ) ) {
			$hover_declarations = $this->extract_hover_declarations( $styles[':hover'] );

			if ( ! empty( $hover_declarations ) ) {
				$hover_rule = implode( ";\n\t", $hover_declarations );

				$hover_parts = array();
				foreach ( self::BLOCK_SELECTORS as $sel ) {
					$hover_parts[] = ':root ' . $sel . ':hover';
				}
				$hover_selector = implode( ",\n", $hover_parts );

				$css .= $hover_selector . " {\n\t" . $hover_rule . ";\n}\n";
			}
		}

		return $css;
	}

	/**
	 * Extract CSS declarations from Global Styles button data.
	 *
	 * @param array $styles Button styles.
	 * @return string[] Array of "property:value" strings.
	 */
	private function extract_declarations( $styles ) {
		$declarations = array();

		// Background color.
		if ( ! empty( $styles['color']['background'] ) ) {
			$declarations[] = 'background-color:' . $this->sanitize_css_value( $styles['color']['background'] );
		}

		// Text color.
		if ( ! empty( $styles['color']['text'] ) ) {
			$declarations[] = 'color:' . $this->sanitize_css_value( $styles['color']['text'] );
		}

		// Border radius (can be shorthand or individual sides).
		if ( ! empty( $styles['border']['radius'] ) ) {
			$radius = $styles['border']['radius'];
			if ( is_string( $radius ) ) {
				$declarations[] = 'border-radius:' . $this->sanitize_css_value( $radius );
			} elseif ( is_array( $radius ) ) {
				$corners = array(
					'topLeft'     => 'border-top-left-radius',
					'topRight'    => 'border-top-right-radius',
					'bottomLeft'  => 'border-bottom-left-radius',
					'bottomRight' => 'border-bottom-right-radius',
				);
				foreach ( $corners as $key => $prop ) {
					if ( ! empty( $radius[ $key ] ) ) {
						$declarations[] = $prop . ':' . $this->sanitize_css_value( $radius[ $key ] );
					}
				}
			}
		}

		// Border width.
		if ( ! empty( $styles['border']['width'] ) ) {
			$declarations[] = 'border-width:' . $this->sanitize_css_value( $styles['border']['width'] );
		}

		// Border style.
		if ( ! empty( $styles['border']['style'] ) ) {
			$declarations[] = 'border-style:' . $this->sanitize_css_value( $styles['border']['style'] );
		}

		// Border color.
		if ( ! empty( $styles['border']['color'] ) ) {
			$declarations[] = 'border-color:' . $this->sanitize_css_value( $styles['border']['color'] );
		}

		// Padding.
		if ( ! empty( $styles['spacing']['padding'] ) ) {
			$padding = $styles['spacing']['padding'];
			$sides   = array(
				'top'    => 'padding-top',
				'right'  => 'padding-right',
				'bottom' => 'padding-bottom',
				'left'   => 'padding-left',
			);
			foreach ( $sides as $key => $prop ) {
				if ( ! empty( $padding[ $key ] ) ) {
					$declarations[] = $prop . ':' . $this->sanitize_css_value( $padding[ $key ] );
				}
			}
		}

		// Typography.
		$typography_map = array(
			'fontSize'   => 'font-size',
			'fontFamily' => 'font-family',
			'fontWeight' => 'font-weight',
			'lineHeight' => 'line-height',
		);
		foreach ( $typography_map as $key => $prop ) {
			if ( ! empty( $styles['typography'][ $key ] ) ) {
				$declarations[] = $prop . ':' . $this->sanitize_css_value( $styles['typography'][ $key ] );
			}
		}

		// Box shadow.
		if ( ! empty( $styles['shadow'] ) ) {
			$declarations[] = 'box-shadow:' . $this->sanitize_css_value( $styles['shadow'] );
		}

		return $declarations;
	}

	/**
	 * Extract hover CSS declarations from Global Styles hover data.
	 *
	 * @param array $hover Hover styles.
	 * @return string[] Array of "property:value" strings.
	 */
	private function extract_hover_declarations( $hover ) {
		$declarations = array();

		if ( ! empty( $hover['color']['background'] ) ) {
			$declarations[] = 'background-color:' . $this->sanitize_css_value( $hover['color']['background'] );
		}
		if ( ! empty( $hover['color']['text'] ) ) {
			$declarations[] = 'color:' . $this->sanitize_css_value( $hover['color']['text'] );
		}
		if ( ! empty( $hover['border']['color'] ) ) {
			$declarations[] = 'border-color:' . $this->sanitize_css_value( $hover['border']['color'] );
		}

		return $declarations;
	}

	/**
	 * Sanitize a CSS value from Global Styles.
	 *
	 * Uses safecss_filter_attr() to strip dangerous CSS content (expressions,
	 * data URIs, JavaScript protocols) while preserving safe CSS functions
	 * like var(), color-mix(), clamp(), etc.
	 *
	 * @param string $value Raw CSS value.
	 * @return string Sanitized value.
	 */
	private function sanitize_css_value( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		$value = trim( $value );

		// Build a temporary declaration for safecss_filter_attr(), which
		// expects a full "property:value" string.
		$declaration = 'color:' . $value;
		$sanitized   = safecss_filter_attr( $declaration );

		// Extract the value portion after the first colon.
		$parts = explode( ':', $sanitized, 2 );
		if ( count( $parts ) === 2 ) {
			return trim( rtrim( $parts[1], ';' ) );
		}

		// If the declaration was stripped entirely, treat as unsafe.
		return '';
	}
}
