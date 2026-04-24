<?php
/**
 * Dynamic Query — Meta Box binding source.
 *
 * Registers `designsetgo/metabox` binding source that delegates to
 * rwmb_meta() for formatted field values (dates, files, URLs, etc.).
 * Only activates when the Meta Box plugin is installed + active.
 *
 * @package DesignSetGo
 * @since   2.4.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * Binding source for Meta Box custom fields.
 */
class MetaBoxBindings {

	/**
	 * Callable used to read a field value.
	 *
	 * Defaults to `rwmb_meta`. Tests may swap this to a closure so no
	 * global function stub is required (avoids PHP namespace-shadowing
	 * issues when defining functions inside namespaced test files).
	 *
	 * @since 2.4.0
	 * @var callable|null
	 */
	public static $reader = null;

	/**
	 * Attach to init at priority 5 (before WP 6.5+ default bindings registration).
	 *
	 * @return void
	 */
	public static function bootstrap() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
	}

	/**
	 * Register the designsetgo/metabox binding source.
	 *
	 * Guarded: no-op unless rwmb_meta() is defined (Meta Box active)
	 * AND the DSGo helper is available.
	 *
	 * @return void
	 */
	public static function register() {
		if ( null === self::$reader && ! function_exists( 'rwmb_meta' ) ) {
			return;
		}
		if ( ! function_exists( 'designsetgo_register_bindings_source' ) ) {
			return;
		}

		designsetgo_register_bindings_source(
			'designsetgo/metabox',
			array( __CLASS__, 'get_value' ),
			array( 'label' => __( 'Meta Box field (DesignSetGo)', 'designsetgo' ) )
		);
	}

	/**
	 * Callback for the binding source — resolves value via rwmb_meta().
	 *
	 * @param array          $args           Binding args. Expects 'key' (Meta Box field id).
	 * @param \WP_Block|null $block          The current block instance.
	 * @param string         $attribute_name The bound attribute name.
	 * @return string|null
	 */
	public static function get_value( $args, $block = null, $attribute_name = 'content' ) {
		$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
		$key     = isset( $args['key'] ) ? sanitize_text_field( (string) $args['key'] ) : '';
		if ( ! $post_id || '' === $key ) {
			return null;
		}

		$reader = self::$reader;
		if ( null === $reader ) {
			if ( ! function_exists( 'rwmb_meta' ) ) {
				return null; // Plugin deactivated between register + call.
			}
			$reader = 'rwmb_meta';
		}

		$value = call_user_func( $reader, $key, array(), $post_id );

		// Image / file-group fields may return arrays — extract a scalar
		// sub-value (url, ID, alt, …) when the binding args request one.
		if ( is_array( $value ) && isset( $args['subkey'] ) && function_exists( 'designsetgo_extract_bindings_subvalue' ) ) {
			$value = designsetgo_extract_bindings_subvalue( $value, (string) $args['subkey'] );
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			return null; // Complex fields need their own render path.
		}
		if ( '' === $value || null === $value || false === $value ) {
			return null;
		}
		return (string) $value;
	}
}
