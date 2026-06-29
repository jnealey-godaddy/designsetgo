<?php
/**
 * Dynamic Tags — field discovery callbacks.
 *
 * Each callback returns an array of `{ key, label, returns, group }`
 * descriptors that the picker presents as a browsable field list.
 *
 * @package DesignSetGo
 * @since   2.2.0
 */

namespace DesignSetGo\Blocks\DynamicTags;

defined( 'ABSPATH' ) || exit;

/**
 * Static helpers invoked as `discovery_cb` on registered sources.
 */
class FieldDiscovery {

	/**
	 * Field discovery for `designsetgo/acf`.
	 *
	 * Uses ACF's `acf_get_field_groups` + `acf_get_fields` APIs to enumerate
	 * every field visible on the target post type, excluding internal
	 * container types (group/repeater/flexible content) that don't resolve
	 * to a scalar. Returns [] when ACF is inactive.
	 *
	 * @param array $context { post_type, returns }.
	 * @return array<int, array<string, mixed>>
	 */
	public static function acf( array $context ) {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return array();
		}
		$post_type = isset( $context['post_type'] ) ? (string) $context['post_type'] : 'post';
		$returns   = isset( $context['returns'] ) ? (string) $context['returns'] : '';

		$groups = \acf_get_field_groups( array( 'post_type' => $post_type ) );
		$fields = array();
		foreach ( $groups as $group ) {
			$group_fields = \acf_get_fields( $group );
			if ( ! is_array( $group_fields ) ) {
				continue;
			}
			foreach ( $group_fields as $field ) {
				$returns_type = self::acf_type_to_returns( $field['type'] ?? '' );
				if ( null === $returns_type ) {
					continue;
				}
				if ( '' !== $returns && $returns !== $returns_type ) {
					continue;
				}
				$fields[] = array(
					'key'     => (string) ( $field['name'] ?? '' ),
					'label'   => (string) ( $field['label'] ?? $field['name'] ?? '' ),
					'returns' => $returns_type,
					'group'   => (string) ( $group['title'] ?? '' ),
				);
			}
		}
		return $fields;
	}

	/**
	 * Field discovery for `designsetgo/post-meta`.
	 *
	 * Uses register_meta()-declared keys (public ones only, `show_in_rest`)
	 * because the raw postmeta table can contain thousands of internal keys
	 * across plugins. This matches WP core's own picker behavior.
	 *
	 * @param array $context { post_type, returns }.
	 * @return array<int, array<string, mixed>>
	 */
	public static function post_meta( array $context ) {
		$post_type = isset( $context['post_type'] ) ? (string) $context['post_type'] : 'post';

		$meta  = get_registered_meta_keys( 'post', $post_type );
		$items = array();
		foreach ( $meta as $key => $meta_data ) {
			if ( 0 === strpos( (string) $key, '_' ) ) {
				continue;
			}
			if ( empty( $meta_data['show_in_rest'] ) ) {
				continue;
			}
			$items[] = array(
				'key'     => (string) $key,
				'label'   => (string) ( $meta_data['label'] ?? $key ),
				'returns' => 'text',
				'group'   => __( 'Registered meta', 'designsetgo' ),
			);
		}
		return $items;
	}

	/**
	 * Maps ACF field types to our return-type vocabulary.
	 *
	 * @param string $acf_type ACF field type name.
	 * @return string|null Return type, or null when the field is skipped.
	 */
	private static function acf_type_to_returns( $acf_type ) {
		switch ( $acf_type ) {
			case 'text':
			case 'textarea':
			case 'email':
			case 'wysiwyg':
			case 'select':
			case 'radio':
			case 'button_group':
			case 'color_picker':
				return 'text';
			case 'number':
			case 'range':
				return 'number';
			case 'url':
			case 'link':
			case 'page_link':
				return 'url';
			case 'image':
			case 'file':
				return 'image';
			case 'date_picker':
			case 'time_picker':
			case 'date_time_picker':
				return 'date';
			// Private / container / repeater / flexible types — skip so
			// sensitive values (passwords) and non-scalar shapes never
			// surface in the Dynamic Tag picker.
			case 'password':
			case 'group':
			case 'repeater':
			case 'flexible_content':
			case 'relationship':
			case 'post_object':
			case 'taxonomy':
			case 'user':
			case 'clone':
				return null;
		}
		return 'text';
	}
}
