<?php
/**
 * Dynamic Query Block — Block Bindings sources.
 *
 * Returns raw meta/ACF values to the Block Bindings API. The consumer
 * block (core/paragraph, core/heading, etc.) is responsible for escaping
 * at render. This is consistent with WP core's own core/post-meta source.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

class Bindings {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ), 5 );
	}

	public function register() {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return; // WP < 6.5.
		}

		// Guard against double-registration (e.g. when called directly in tests after plugin bootstrap).
		if ( ! get_block_bindings_source( 'designsetgo/post-meta' ) ) {
			register_block_bindings_source(
				'designsetgo/post-meta',
				array(
					'label'              => __( 'Post meta (DesignSetGo)', 'designsetgo' ),
					'get_value_callback' => array( $this, 'get_post_meta_value' ),
					'uses_context'       => array( 'postId' ),
				)
			);
		}

		if ( function_exists( 'get_field' ) && ! get_block_bindings_source( 'designsetgo/acf' ) ) {
			register_block_bindings_source(
				'designsetgo/acf',
				array(
					'label'              => __( 'ACF Field (DesignSetGo)', 'designsetgo' ),
					'get_value_callback' => array( $this, 'get_acf_value' ),
					'uses_context'       => array( 'postId' ),
				)
			);
		}
	}

	public function get_post_meta_value( array $args, $block = null, $attribute_name = 'content' ) {
		// sanitize_text_field() strips HTML/null-bytes but preserves case and common characters.
		// sanitize_key() would silently lowercase keys like "SEOTitle" → "seotitle", breaking lookups.
		$key = isset( $args['key'] ) ? sanitize_text_field( $args['key'] ) : '';
		if ( '' === $key ) {
			return null;
		}

		$post_id = 0;
		if ( $block && isset( $block->context['postId'] ) ) {
			$post_id = (int) $block->context['postId'];
		}
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		// Mirror WP core's core/post-meta security gates (minus show_in_rest strictness).
		if ( post_password_required( $post ) ) {
			return null;
		}
		if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) {
			return null;
		}
		if ( is_protected_meta( $key, 'post' ) ) {
			return null;
		}

		$value = get_post_meta( $post_id, $key, true );
		return '' === $value ? null : $value;
	}

	public function get_acf_value( array $args, $block = null, $attribute_name = 'content' ) {
		if ( ! function_exists( 'get_field' ) ) {
			return null;
		}

		// sanitize_text_field() strips HTML/null-bytes but preserves case and common characters.
		// sanitize_key() would silently lowercase ACF field names, breaking lookups.
		$key = isset( $args['key'] ) ? sanitize_text_field( $args['key'] ) : '';
		if ( '' === $key ) {
			return null;
		}

		$post_id = 0;
		if ( $block && isset( $block->context['postId'] ) ) {
			$post_id = (int) $block->context['postId'];
		}
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		// ACF stores values in postmeta — apply same security gates as post-meta source.
		if ( post_password_required( $post ) ) {
			return null;
		}
		if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) {
			return null;
		}
		if ( is_protected_meta( $key, 'post' ) ) {
			return null;
		}

		$value = get_field( $key, $post_id ?: false );
		if ( is_array( $value ) || is_object( $value ) ) {
			return null; // Scalar-only source; complex values need a specific render path.
		}

		if ( '' === $value || null === $value || false === $value ) {
			return null;
		}
		return (string) $value;
	}
}
