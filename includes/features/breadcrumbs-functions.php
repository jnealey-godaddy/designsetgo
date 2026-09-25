<?php
/**
 * Breadcrumbs Helper Functions
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_trail' ) ) {
	/**
	 * Get breadcrumb trail for current post/page
	 *
	 * Hierarchical post types (pages, and any hierarchical custom post type)
	 * list their ancestors. Every other post type lists its archive, when it
	 * has one, and then the first term of its trail taxonomy with that term's
	 * ancestors — `category` for posts, `product_cat` for WooCommerce
	 * products, otherwise the first public hierarchical taxonomy.
	 *
	 * @param WP_Block $block Block instance with context.
	 * @param array    $attributes Block attributes.
	 * @return array Breadcrumb items with title and url.
	 */
	function designsetgo_get_breadcrumb_trail( $block, $attributes ) {
		$trail = array();

		// Get post from block context.
		$post_id = null;
		if ( isset( $block->context['postId'] ) ) {
			$post_id = $block->context['postId'];
		}

		if ( ! $post_id ) {
			return $trail;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $trail;
		}

		// Add home link if enabled.
		if ( ! empty( $attributes['showHome'] ) ) {
			$trail[] = array(
				'title' => ! empty( $attributes['homeText'] ) ? sanitize_text_field( $attributes['homeText'] ) : __( 'Home', 'designsetgo' ),
				'url'   => home_url( '/' ),
			);
		}

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			$parent_ids = array_reverse( get_post_ancestors( $post->ID ) );
			foreach ( $parent_ids as $parent_id ) {
				$parent = get_post( $parent_id );
				if ( $parent ) {
					$trail[] = array(
						'title' => get_the_title( $parent->ID ),
						'url'   => get_permalink( $parent->ID ),
					);
				}
			}
		} else {
			$trail = array_merge(
				$trail,
				designsetgo_get_breadcrumb_archive_items( $post ),
				designsetgo_get_breadcrumb_term_items( $post )
			);
		}

		// Add current page if enabled.
		if ( ! empty( $attributes['showCurrent'] ) ) {
			$current_item = array(
				'title'      => get_the_title( $post->ID ),
				'url'        => get_permalink( $post->ID ),
				'is_current' => true,
			);
			$trail[]      = $current_item;
		}

		// Allow filtering of breadcrumb trail.
		return apply_filters( 'designsetgo_breadcrumbs_trail', $trail, $post, $attributes );
	}
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_archive_items' ) ) {
	/**
	 * Post type archive crumb for a custom post type (e.g. the Woo shop page).
	 *
	 * Posts are skipped: their archive is the blog index, which is usually the
	 * home link already.
	 *
	 * @param WP_Post $post Post.
	 * @return array Zero or one breadcrumb item.
	 */
	function designsetgo_get_breadcrumb_archive_items( $post ) {
		if ( 'post' === $post->post_type ) {
			return array();
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type || empty( $post_type->has_archive ) ) {
			return array();
		}

		$url = get_post_type_archive_link( $post->post_type );
		if ( ! $url ) {
			return array();
		}

		$title = $post_type->labels->name;

		// Woo's product archive is a real page the shop owner named.
		if ( 'product' === $post->post_type && function_exists( 'wc_get_page_id' ) ) {
			$shop_id = wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) {
				$title = get_the_title( $shop_id );
			}
		}

		return array(
			array(
				'title' => $title,
				'url'   => $url,
			),
		);
	}
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_taxonomy' ) ) {
	/**
	 * Which taxonomy a post type's trail passes through.
	 *
	 * @param string $post_type Post type name.
	 * @return string Taxonomy name, or '' for none.
	 */
	function designsetgo_get_breadcrumb_taxonomy( $post_type ) {
		$defaults = array(
			'post'    => 'category',
			'product' => 'product_cat',
		);

		$taxonomy = '';
		if ( isset( $defaults[ $post_type ] ) ) {
			$taxonomy = $defaults[ $post_type ];
		} else {
			foreach ( get_object_taxonomies( $post_type, 'objects' ) as $candidate ) {
				if ( $candidate->hierarchical && $candidate->public ) {
					$taxonomy = $candidate->name;
					break;
				}
			}
		}

		/**
		 * Filter the taxonomy a post type's breadcrumb trail passes through.
		 *
		 * Return '' to show no term crumb for that post type.
		 *
		 * @param string $taxonomy  Taxonomy name, or ''.
		 * @param string $post_type Post type name.
		 */
		$taxonomy = apply_filters( 'designsetgo_breadcrumbs_taxonomy', $taxonomy, $post_type );

		return is_string( $taxonomy ) && taxonomy_exists( $taxonomy ) ? $taxonomy : '';
	}
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_term_items' ) ) {
	/**
	 * Crumbs for a post's first term in its trail taxonomy, ancestors first.
	 *
	 * @param WP_Post $post Post.
	 * @return array Breadcrumb items.
	 */
	function designsetgo_get_breadcrumb_term_items( $post ) {
		$taxonomy = designsetgo_get_breadcrumb_taxonomy( $post->post_type );
		if ( '' === $taxonomy ) {
			return array();
		}

		$terms = get_the_terms( $post, $taxonomy );
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return array();
		}

		$term_ids   = array_reverse( get_ancestors( $terms[0]->term_id, $taxonomy, 'taxonomy' ) );
		$term_ids[] = $terms[0]->term_id;
		$items      = array();

		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, $taxonomy );
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$url = get_term_link( $term );
			if ( is_wp_error( $url ) ) {
				continue;
			}

			$items[] = array(
				'title' => $term->name,
				'url'   => $url,
			);
		}

		return $items;
	}
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_schema' ) ) {
	/**
	 * Build a Schema.org BreadcrumbList node for a trail.
	 *
	 * @param array $trail Breadcrumb items (title, url).
	 * @return array BreadcrumbList, or an empty array when the trail is empty.
	 */
	function designsetgo_get_breadcrumb_schema( array $trail ) {
		$elements = array();

		foreach ( array_values( $trail ) as $index => $item ) {
			$element = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => wp_strip_all_tags( (string) $item['title'] ),
			);

			if ( ! empty( $item['url'] ) ) {
				$element['item'] = esc_url_raw( $item['url'] );
			}

			$elements[] = $element;
		}

		if ( empty( $elements ) ) {
			return array();
		}

		return array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $elements,
		);
	}
}

if ( ! function_exists( 'designsetgo_get_breadcrumb_separator' ) ) {
	/**
	 * Get separator character based on attributes
	 *
	 * @param array $attributes Block attributes.
	 * @return string Separator character.
	 */
	function designsetgo_get_breadcrumb_separator( $attributes ) {
		$separator = ! empty( $attributes['separator'] ) ? $attributes['separator'] : 'slash';

		$separators = array(
			'slash'       => '/',
			'chevron'     => '›',
			'greater'     => '>',
			'bullet'      => '•',
			'arrow-right' => '→',
		);

		return isset( $separators[ $separator ] ) ? $separators[ $separator ] : '/';
	}
}
