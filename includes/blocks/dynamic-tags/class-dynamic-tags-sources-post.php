<?php
/**
 * Dynamic Tags — post-family sources.
 *
 * Registers scalar binding sources covering the most common post fields:
 * title, excerpt, date, permalink, author info, featured image parts,
 * taxonomy terms. All wrap designsetgo_register_bindings_source() so
 * the shared security gates (password, viewable, protected-meta) apply.
 *
 * @package DesignSetGo
 * @since   2.2.0
 */

namespace DesignSetGo\Blocks\DynamicTags;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `designsetgo/post-*` binding sources.
 */
class PostSources {

	/**
	 * Registers all post-family sources and their registry metadata.
	 *
	 * @param Registry $registry Metadata registry.
	 */
	public static function register( Registry $registry ) {
		if ( ! function_exists( 'designsetgo_register_bindings_source' ) ) {
			return;
		}

		self::register_one(
			$registry,
			'designsetgo/post-title',
			__( 'Post title', 'designsetgo' ),
			array( 'text' ),
			static function ( $args, $block, $attr ) {
				$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
				if ( ! $post_id ) {
					return null;
				}
				$title = get_the_title( $post_id );
				return '' === $title ? null : (string) $title;
			}
		);

		self::register_one(
			$registry,
			'designsetgo/post-excerpt',
			__( 'Post excerpt', 'designsetgo' ),
			array( 'text' ),
			static function ( $args, $block, $attr ) {
				$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
				if ( ! $post_id ) {
					return null;
				}
				$post = get_post( $post_id );
				if ( ! $post ) {
					return null;
				}
				$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '…' );
				return '' === $excerpt ? null : (string) $excerpt;
			}
		);

		self::register_one(
			$registry,
			'designsetgo/post-date',
			__( 'Post publish date', 'designsetgo' ),
			array( 'text', 'date' ),
			static function ( $args, $block, $attr ) {
				$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
				if ( ! $post_id ) {
					return null;
				}
				$format = isset( $args['format'] ) ? (string) $args['format'] : '';
				if ( 'datetime' === $attr || '' === $format ) {
					return (string) get_the_date( 'c', $post_id );
				}
				return (string) get_the_date( $format, $post_id );
			},
			array(
				'format' => array(
					'type'        => 'string',
					'description' => __( 'PHP date format; defaults to the site setting.', 'designsetgo' ),
				),
			)
		);

		self::register_one(
			$registry,
			'designsetgo/post-modified-date',
			__( 'Post modified date', 'designsetgo' ),
			array( 'text', 'date' ),
			static function ( $args, $block, $attr ) {
				$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
				if ( ! $post_id ) {
					return null;
				}
				$format = isset( $args['format'] ) ? (string) $args['format'] : '';
				if ( 'datetime' === $attr || '' === $format ) {
					return (string) get_the_modified_date( 'c', $post_id );
				}
				return (string) get_the_modified_date( $format, $post_id );
			},
			array(
				'format' => array( 'type' => 'string' ),
			)
		);

		self::register_one(
			$registry,
			'designsetgo/post-permalink',
			__( 'Post permalink', 'designsetgo' ),
			array( 'url' ),
			static function ( $args, $block, $attr ) {
				$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
				if ( ! $post_id ) {
					return null;
				}
				$url = get_permalink( $post_id );
				return $url ? (string) $url : null;
			}
		);

		self::register_one(
			$registry,
			'designsetgo/post-id',
			__( 'Post ID', 'designsetgo' ),
			array( 'text', 'number' ),
			static function ( $args, $block, $attr ) {
				$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
				return $post_id ? (string) $post_id : null;
			}
		);

		self::register_one(
			$registry,
			'designsetgo/post-type',
			__( 'Post type', 'designsetgo' ),
			array( 'text' ),
			static function ( $args, $block, $attr ) {
				$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
				if ( ! $post_id ) {
					return null;
				}
				$type = get_post_type( $post_id );
				return $type ? (string) $type : null;
			}
		);

		self::register_one(
			$registry,
			'designsetgo/post-author-name',
			__( 'Post author name', 'designsetgo' ),
			array( 'text' ),
			static function ( $args, $block, $attr ) {
				$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
				if ( ! $post_id ) {
					return null;
				}
				$author_id = (int) get_post_field( 'post_author', $post_id );
				if ( ! $author_id ) {
					return null;
				}
				$name = get_the_author_meta( 'display_name', $author_id );
				return '' === $name ? null : (string) $name;
			}
		);

		self::register_one(
			$registry,
			'designsetgo/post-author-url',
			__( 'Post author archive URL', 'designsetgo' ),
			array( 'url' ),
			static function ( $args, $block, $attr ) {
				$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
				if ( ! $post_id ) {
					return null;
				}
				$author_id = (int) get_post_field( 'post_author', $post_id );
				if ( ! $author_id ) {
					return null;
				}
				$url = get_author_posts_url( $author_id );
				return $url ? (string) $url : null;
			}
		);

		self::register_one(
			$registry,
			'designsetgo/post-author-avatar-url',
			__( 'Post author avatar', 'designsetgo' ),
			array( 'image', 'url' ),
			static function ( $args, $block, $attr ) {
				$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
				if ( ! $post_id ) {
					return null;
				}
				$author_id = (int) get_post_field( 'post_author', $post_id );
				if ( ! $author_id ) {
					return null;
				}
				$size = isset( $args['size'] ) ? (int) $args['size'] : 96;
				$url  = get_avatar_url( $author_id, array( 'size' => max( 24, $size ) ) );
				return $url ? (string) $url : null;
			},
			array(
				'size' => array( 'type' => 'integer' ),
			)
		);

		self::register_one(
			$registry,
			'designsetgo/post-featured-image',
			__( 'Post featured image', 'designsetgo' ),
			array( 'image' ),
			static function ( $args, $block, $attr ) {
				$post_id = (int) ( $args['__dsgo_post_id'] ?? 0 );
				if ( ! $post_id ) {
					return null;
				}
				$attachment_id = (int) get_post_thumbnail_id( $post_id );
				if ( ! $attachment_id ) {
					return null;
				}
				$subkey = isset( $args['subkey'] ) ? (string) $args['subkey'] : 'url';
				$size   = isset( $args['size'] ) ? (string) $args['size'] : 'full';
				return ImageResolver::resolve_subvalue( $attachment_id, $subkey, $size );
			},
			array(
				'subkey' => array(
					'type' => 'string',
					'enum' => array( 'url', 'id', 'alt', 'width', 'height', 'title', 'caption' ),
				),
				'size'   => array( 'type' => 'string' ),
			)
		);

		self::register_one(
			$registry,
			'designsetgo/post-taxonomy',
			__( 'Post taxonomy terms', 'designsetgo' ),
			array( 'text' ),
			static function ( $args, $block, $attr ) {
				$post_id  = (int) ( $args['__dsgo_post_id'] ?? 0 );
				$taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( (string) $args['taxonomy'] ) : 'category';
				if ( ! $post_id || '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
					return null;
				}
				$separator = isset( $args['separator'] ) ? sanitize_text_field( (string) $args['separator'] ) : ', ';
				$terms     = get_the_term_list( $post_id, $taxonomy, '', $separator );
				if ( is_wp_error( $terms ) || ! $terms ) {
					return null;
				}
				return wp_strip_all_tags( (string) $terms );
			},
			array(
				'taxonomy'  => array(
					'type'     => 'string',
					'required' => true,
				),
				'separator' => array( 'type' => 'string' ),
			)
		);
	}

	/**
	 * Shared helper that registers one source with both core Bindings and
	 * our metadata registry, using the identical slug and default group.
	 *
	 * @param Registry $registry     Metadata registry.
	 * @param string   $slug         Binding source slug.
	 * @param string   $label        Display label.
	 * @param string[] $returns      Return types.
	 * @param callable $callback     Value callback. Receives
	 *                               ($args, $block, $attribute_name); the
	 *                               helper has already injected
	 *                               $args['__dsgo_post_id'].
	 * @param array    $args_schema  Optional arg schema for UI discovery.
	 */
	private static function register_one( Registry $registry, $slug, $label, array $returns, callable $callback, array $args_schema = array() ) {
		designsetgo_register_bindings_source(
			$slug,
			$callback,
			array( 'label' => $label )
		);

		$registry->register_source(
			$slug,
			array(
				'label'   => $label,
				'group'   => 'post',
				'returns' => $returns,
				'args'    => $args_schema,
			)
		);
	}
}
