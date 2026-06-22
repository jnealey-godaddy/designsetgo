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

		// Add parent pages for hierarchical post types (pages).
		if ( 'page' === $post->post_type && $post->post_parent ) {
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
		}

		// Add category for posts (only first category).
		if ( 'post' === $post->post_type ) {
			$categories = get_the_category( $post->ID );
			if ( ! empty( $categories ) ) {
				$category = $categories[0];
				$trail[]  = array(
					'title' => $category->name,
					'url'   => get_category_link( $category->term_id ),
				);
			}
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
