<?php
/**
 * Dynamic Query — Pods binding source.
 *
 * Registers `designsetgo/pods` binding source that delegates to
 * pods_field() for formatted field values (dates, files, URLs, etc.).
 * Only activates when the Pods plugin is installed + active.
 *
 * @package DesignSetGo
 * @since   2.4.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * Binding source for Pods custom fields.
 */
class PodsBindings {

	/**
	 * Callable used to read a field value.
	 *
	 * Defaults to `pods_field`. Tests may swap this to a closure so no
	 * global function stub is required (avoids PHP namespace-shadowing
	 * issues when defining functions inside namespaced test files).
	 *
	 * The closure receives three arguments: $pod_type (string), $post_id (int),
	 * $field_name (string) — mirroring the pods_field() signature.
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
	 * Register the designsetgo/pods binding source.
	 *
	 * Guarded: no-op unless pods_field() is defined (Pods active)
	 * AND the DSGo helper is available.
	 *
	 * @return void
	 */
	public static function register() {
		if ( null === self::$reader && ! function_exists( 'pods_field' ) ) {
			return;
		}
		if ( ! function_exists( 'designsetgo_register_bindings_source' ) ) {
			return;
		}

		designsetgo_register_bindings_source(
			'designsetgo/pods',
			array( __CLASS__, 'get_value' ),
			array( 'label' => __( 'Pods field (DesignSetGo)', 'designsetgo' ) )
		);
	}

	/**
	 * Callback for the binding source — resolves value via pods_field().
	 *
	 * Calls pods_field( $pod_type, $post_id, $field_name ) where $pod_type
	 * is resolved from the post ID via get_post_type().
	 *
	 * @param array          $args           Binding args. Expects 'key' (Pods field name).
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
			if ( ! function_exists( 'pods_field' ) ) {
				return null; // Plugin deactivated between register + call.
			}
			$post_type = get_post_type( $post_id );
			$value     = pods_field( $post_type, $post_id, $key );
		} else {
			$post_type = get_post_type( $post_id );
			$value     = ( $reader )( $post_type, $post_id, $key );
		}

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
