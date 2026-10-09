<?php
/**
 * Numbers form steps per form, for the "Step N" heading fallback.
 *
 * render_block_data fires for the form block before its inner blocks render,
 * so the count restarts for every form on a page.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_Step_Counter.
 */
class Form_Step_Counter {

	/**
	 * Steps rendered so far in the current form.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'render_block_data', array( $this, 'reset_for_form' ) );
	}

	/**
	 * Reset the count when a form starts rendering.
	 *
	 * @param array $parsed_block Parsed block.
	 * @return array Unchanged block.
	 */
	public function reset_for_form( $parsed_block ) {
		if ( is_array( $parsed_block ) && isset( $parsed_block['blockName'] ) && 'designsetgo/form-builder' === $parsed_block['blockName'] ) {
			self::reset();
		}
		return $parsed_block;
	}

	/**
	 * The next step number in the current form.
	 *
	 * @return int 1-based number.
	 */
	public static function next(): int {
		return ++self::$count;
	}

	/**
	 * Restart numbering.
	 */
	public static function reset(): void {
		self::$count = 0;
	}
}
