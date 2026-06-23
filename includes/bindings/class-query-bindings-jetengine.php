<?php
/**
 * Dynamic Query — JetEngine binding source.
 *
 * Registers `designsetgo/jetengine` binding source that delegates to
 * jet_engine()->listings->data->get_meta() for formatted field values
 * (dates, files, relations, etc.). Only activates when the JetEngine
 * plugin is installed + active.
 *
 * @package DesignSetGo
 * @since   2.4.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * Binding source for JetEngine custom fields.
 */
class JetEngineBindings {

	/**
	 * Callable used to read a field value.
	 *
	 * Defaults to jet_engine()->listings->data->get_meta(). Tests may swap
	 * this to a closure so no global function stub is required (avoids PHP
	 * namespace-shadowing issues when defining functions inside namespaced
	 * test files).
	 *
	 * The closure receives two arguments: $key (string), $post_id (int) —
	 * mirroring the get_meta() signature.
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
	 * Register the designsetgo/jetengine binding source.
	 *
	 * Guarded: no-op unless JetEngine is active (class_exists('Jet_Engine')
	 * && function_exists('jet_engine')) AND the DSGo helper is available.
	 * A non-null $reader bypasses the class/function check for testing.
	 *
	 * @return void
	 */
	public static function register() {
		if ( null === self::$reader && ! ( class_exists( 'Jet_Engine' ) && function_exists( 'jet_engine' ) ) ) {
			return;
		}
		if ( ! function_exists( 'designsetgo_register_bindings_source' ) ) {
			return;
		}

		designsetgo_register_bindings_source(
			'designsetgo/jetengine',
			array( __CLASS__, 'get_value' ),
			array( 'label' => __( 'JetEngine field (DesignSetGo)', 'designsetgo' ) )
		);
	}

	/**
	 * Callback for the binding source — resolves value via JetEngine's data API.
	 *
	 * Prefers jet_engine()->listings->data->get_meta( $key, $post_id ) which
	 * applies JetEngine field-type formatting (dates, files, relations). Falls
	 * back to raw get_post_meta() when the listings data object is unavailable.
	 *
	 * @param array          $args           Binding args. Expects 'key' (JetEngine field name).
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

		if ( self::$reader ) {
			$value = call_user_func( self::$reader, $key, $post_id );
		} elseif ( function_exists( 'jet_engine' ) && isset( jet_engine()->listings->data ) && method_exists( jet_engine()->listings->data, 'get_meta' ) ) {
			$value = jet_engine()->listings->data->get_meta( $key, $post_id );
		} else {
			$value = get_post_meta( $post_id, $key, true );
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
