<?php
/**
 * Dynamic Tags — user-family sources.
 *
 * Resolves values from the currently logged-in user. These sources
 * produce nothing for anonymous visitors, which is the intended
 * behavior for members-area UIs.
 *
 * @package DesignSetGo
 * @since   2.2.0
 */

namespace DesignSetGo\Blocks\DynamicTags;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `designsetgo/current-user-*` binding sources.
 */
class UserSources {

	/**
	 * Registers user-family sources.
	 *
	 * @param Registry $registry Metadata registry.
	 */
	public static function register( Registry $registry ) {
		if ( ! function_exists( 'designsetgo_register_bindings_source' ) ) {
			return;
		}

		self::register_one(
			$registry,
			'designsetgo/current-user-name',
			__( 'Current user name', 'designsetgo' ),
			array( 'text' ),
			static function ( $args, $block, $attr ) {
				$user = wp_get_current_user();
				if ( 0 === (int) $user->ID ) {
					return null;
				}
				return (string) $user->display_name;
			}
		);

		self::register_one(
			$registry,
			'designsetgo/current-user-avatar',
			__( 'Current user avatar', 'designsetgo' ),
			array( 'image', 'url' ),
			static function ( $args, $block, $attr ) {
				$user = wp_get_current_user();
				if ( 0 === (int) $user->ID ) {
					return null;
				}
				$size = isset( $args['size'] ) ? (int) $args['size'] : 96;
				$url  = get_avatar_url( $user->ID, array( 'size' => max( 24, $size ) ) );
				return $url ? (string) $url : null;
			},
			array(
				'size' => array( 'type' => 'integer' ),
			)
		);

		self::register_one(
			$registry,
			'designsetgo/current-user-url',
			__( 'Current user website URL', 'designsetgo' ),
			array( 'url' ),
			static function ( $args, $block, $attr ) {
				$user = wp_get_current_user();
				if ( 0 === (int) $user->ID ) {
					return null;
				}
				$url = (string) $user->user_url;
				return '' === $url ? null : $url;
			}
		);

		// Always register the email source so plugins hooking `init` at the
		// default priority (10) can still enable it; the value callback
		// evaluates `designsetgo_dynamic_tags_allow_email` lazily at render
		// time. Disabled by default because exposing an email on the public
		// frontend is almost always a privacy mistake.
		self::register_one(
			$registry,
			'designsetgo/current-user-email',
			__( 'Current user email', 'designsetgo' ),
			array( 'text' ),
			static function ( $args, $block, $attr ) {
				/**
				 * Enables the `designsetgo/current-user-email` source.
				 *
				 * @since 2.2.0
				 *
				 * @param bool $enabled Whether to resolve the email source.
				 */
				if ( ! apply_filters( 'designsetgo_dynamic_tags_allow_email', false ) ) {
					return null;
				}
				$user = wp_get_current_user();
				if ( 0 === (int) $user->ID ) {
					return null;
				}
				return (string) $user->user_email;
			},
			array(),
			'read'
		);
	}

	/**
	 * Registers one source with both core Bindings and our metadata registry.
	 *
	 * @param Registry $registry    Metadata registry.
	 * @param string   $slug        Binding source slug.
	 * @param string   $label       Display label.
	 * @param string[] $returns     Return types.
	 * @param callable $callback    Value callback.
	 * @param array    $args_schema Optional arg schema.
	 * @param string   $capability  Optional capability gate.
	 */
	private static function register_one( Registry $registry, $slug, $label, array $returns, callable $callback, array $args_schema = array(), $capability = '' ) {
		designsetgo_register_bindings_source(
			$slug,
			$callback,
			array( 'label' => $label )
		);
		$registry->register_source(
			$slug,
			array(
				'label'      => $label,
				'group'      => 'user',
				'returns'    => $returns,
				'args'       => $args_schema,
				'capability' => $capability,
			)
		);
	}
}
