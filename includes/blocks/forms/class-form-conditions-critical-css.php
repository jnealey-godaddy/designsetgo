<?php
/**
 * Critical inline CSS for Form Builder conditional fields.
 *
 * The form block stylesheets are deferred (media="print" + onload swap, see
 * Assets::optimize_css_loading()), so a rule that lives in them applies after
 * first paint. The conditional-field pre-hide has to apply before the fields
 * paint, so it is printed inline ahead of the first form that needs it.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_Conditions_Critical_CSS.
 */
class Form_Conditions_Critical_CSS {

	/**
	 * Conditional fields are hidden until the view script has applied the
	 * rules, but only when scripting is available: without JavaScript every
	 * field shows and the server drops answers to fields whose rules aren't
	 * met. The pre-hide expires: if the view script never runs (blocked,
	 * errored, failed to load), the fields show after 4s instead of staying
	 * hidden while still required. Browsers that can't animate `display`
	 * don't pre-hide.
	 */
	const CSS = '@media (scripting:enabled){.dsgo-form-builder:not([data-dsgo-conditions-ready]) .dsgo-form-field--conditional{animation:dsgo-conditions-pending 4s}}@keyframes dsgo-conditions-pending{0%,99.9%{display:none}}';

	/**
	 * Whether the style has been printed in this request.
	 *
	 * @var bool
	 */
	private static $printed = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'render_block_designsetgo/form-builder', array( $this, 'prepend_style' ), 10, 2 );
	}

	/**
	 * Reset the once-per-request flag (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$printed = false;
	}

	/**
	 * Prepend the critical style to the first form containing a conditional field.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block (unused).
	 * @return string
	 */
	public function prepend_style( $block_content, $block = array() ) {
		unset( $block );

		if ( self::$printed || ! is_string( $block_content ) || false === strpos( $block_content, 'dsgo-form-field--conditional' ) ) {
			return $block_content;
		}

		self::$printed = true;

		return '<style id="dsgo-form-conditions-critical">' . self::CSS . '</style>' . $block_content;
	}
}
