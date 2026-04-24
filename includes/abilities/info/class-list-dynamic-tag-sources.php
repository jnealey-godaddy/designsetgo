<?php
/**
 * List Dynamic Tag Sources Ability.
 *
 * Returns the catalog of registered DesignSetGo Dynamic Tag sources —
 * post / site / archive / user fields plus custom-field providers
 * (ACF, Meta Box, Pods, JetEngine). AI agents use this to discover
 * which `metadata.bindings.<attr>.source` values are available before
 * writing bindings to a block via update-block.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.2.0
 */

namespace DesignSetGo\Abilities\Info;

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Blocks\DynamicTags\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List Dynamic Tag Sources ability.
 */
class List_Dynamic_Tag_Sources extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/list-dynamic-tag-sources';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'List Dynamic Tag Sources', 'designsetgo' ),
			'description'         => __( 'Returns the catalog of Dynamic Tag binding sources (post/site/archive/user fields, ACF, Meta Box, Pods, JetEngine). Each entry includes the source slug, label, group, return types, and arg schema. Use the slug as `metadata.bindings.<attribute>.source` when writing bindings via update-block.', 'designsetgo' ),
			'category'            => 'info',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'binding', 'dynamic', 'token', 'tag', 'source', 'data' ),
			'annotations'         => array(
				'readonly'     => true,
				'instructions' => 'Read-only catalog. Filter by `returns` (text|image|url|number|date) to narrow to sources compatible with the bound attribute, or by `group` to scope to one family. To bind a source, write `attributes.metadata.bindings.<attr> = { source: "<slug>", args: { ... } }` via update-block.',
			),
		);
	}

	/**
	 * Capability gate — same baseline as the editor.
	 *
	 * @return bool
	 */
	public function check_permission_callback(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Input schema.
	 *
	 * @return array<string, mixed>
	 */
	private function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'returns' => array(
					'type'        => 'string',
					'enum'        => array( 'text', 'image', 'url', 'number', 'date' ),
					'description' => __( 'Filter to sources that can return the given type.', 'designsetgo' ),
				),
				'group'   => array(
					'type'        => 'string',
					'enum'        => array( 'post', 'site', 'archive', 'user', 'custom-fields' ),
					'description' => __( 'Filter to a single group.', 'designsetgo' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Output schema.
	 *
	 * @return array<string, mixed>
	 */
	private function get_output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'sources' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'slug'    => array( 'type' => 'string' ),
							'label'   => array( 'type' => 'string' ),
							'group'   => array( 'type' => 'string' ),
							'returns' => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							'args'    => array( 'type' => 'object' ),
						),
					),
				),
				'groups'  => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'slug'  => array( 'type' => 'string' ),
							'label' => array( 'type' => 'string' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	public function execute( array $input ) {
		$registry = Registry::instance();

		$filters = array();
		if ( ! empty( $input['returns'] ) ) {
			$filters['returns'] = (array) $input['returns'];
		}
		if ( ! empty( $input['group'] ) ) {
			$filters['group'] = (string) $input['group'];
		}

		$sources = array_map(
			static function ( $source ) {
				return array(
					'slug'    => $source['slug'],
					'label'   => $source['label'],
					'group'   => $source['group'],
					'returns' => array_values( (array) $source['returns'] ),
					'args'    => (object) ( $source['args'] ?? array() ),
				);
			},
			$registry->all_sources( $filters )
		);

		$groups = array_map(
			static function ( $group ) {
				return array(
					'slug'  => $group['slug'],
					'label' => $group['label'],
				);
			},
			$registry->all_groups()
		);

		return array(
			'sources' => $sources,
			'groups'  => $groups,
		);
	}
}
