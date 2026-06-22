<?php
/**
 * Dynamic Tags — archive-family sources.
 *
 * Resolves values from the current archive queried object. When used
 * outside an archive context (e.g. on a singular post) these return null.
 *
 * @package DesignSetGo
 * @since   2.2.0
 */

namespace DesignSetGo\Blocks\DynamicTags;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `designsetgo/archive-*` binding sources.
 */
class ArchiveSources {

	/**
	 * Registers archive-family sources.
	 *
	 * @param Registry $registry Metadata registry.
	 */
	public static function register( Registry $registry ) {
		if ( ! function_exists( 'designsetgo_register_bindings_source' ) ) {
			return;
		}

		self::register_one(
			$registry,
			'designsetgo/archive-title',
			__( 'Archive title', 'designsetgo' ),
			array( 'text' ),
			static function ( $args, $block, $attr ) {
				if ( ! self::is_archive_context() ) {
					return null;
				}
				$title = wp_strip_all_tags( (string) get_the_archive_title() );
				return '' === $title ? null : $title;
			}
		);

		self::register_one(
			$registry,
			'designsetgo/archive-description',
			__( 'Archive description', 'designsetgo' ),
			array( 'text' ),
			static function ( $args, $block, $attr ) {
				if ( ! self::is_archive_context() ) {
					return null;
				}
				$desc = get_the_archive_description();
				if ( ! $desc ) {
					return null;
				}
				return wp_strip_all_tags( (string) $desc );
			}
		);

		self::register_one(
			$registry,
			'designsetgo/archive-url',
			__( 'Archive URL', 'designsetgo' ),
			array( 'url' ),
			static function ( $args, $block, $attr ) {
				$object = get_queried_object();
				if ( $object instanceof \WP_Term ) {
					$url = get_term_link( $object );
					return is_wp_error( $url ) ? null : (string) $url;
				}
				if ( $object instanceof \WP_Post_Type ) {
					$url = get_post_type_archive_link( $object->name );
					return $url ? (string) $url : null;
				}
				if ( $object instanceof \WP_User ) {
					return (string) get_author_posts_url( $object->ID );
				}
				return null;
			}
		);
	}

	/**
	 * Whether the current request is an archive of any kind.
	 *
	 * @return bool
	 */
	private static function is_archive_context() {
		return is_archive() || is_home() || is_search();
	}

	/**
	 * Registers one source with both core Bindings and our metadata registry.
	 *
	 * @param Registry $registry Metadata registry.
	 * @param string   $slug     Binding source slug.
	 * @param string   $label    Display label.
	 * @param string[] $returns  Return types.
	 * @param callable $callback Value callback.
	 */
	private static function register_one( Registry $registry, $slug, $label, array $returns, callable $callback ) {
		designsetgo_register_bindings_source(
			$slug,
			$callback,
			array( 'label' => $label )
		);
		$registry->register_source(
			$slug,
			array(
				'label'   => $label,
				'group'   => 'archive',
				'returns' => $returns,
			)
		);
	}
}
