<?php
/**
 * List Blocks Ability.
 *
 * Provides a complete catalog of all DesignSetGo blocks with their
 * descriptions, categories, and available attributes.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.0.0
 */

namespace DesignSetGo\Abilities\Info;

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List Blocks ability class.
 */
class List_Blocks extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/list-blocks';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'List DesignSetGo Blocks', 'designsetgo' ),
			'description'         => __( 'Returns a comprehensive list of all available DesignSetGo blocks with their capabilities, attributes, and metadata.', 'designsetgo' ),
			'category'            => 'info',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'available', 'library', 'catalog', 'registry' ),
			'annotations'         => array(
				'readonly'     => true,
				'destructive'  => false,
				'idempotent'   => true,
				'instructions' => 'Returns all DesignSetGo blocks with their attributes and metadata. Each block reports both `category` (the block editor category it registers into, almost always "designsetgo") and `group` (the plugin\'s own grouping: containers, ui, interactive, widgets, forms, or uncategorized). Filter with `group`, not `category` — `category` is near-uniform across the library and will not narrow anything. Use detail "full" with specific block names for complete attribute definitions.',
			),
		);
	}

	/**
	 * Get input schema.
	 *
	 * @return array<string, mixed>
	 */
	private function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'group'  => array(
					'type'        => 'string',
					'description' => __( 'Filter by the plugin\'s own block grouping (the same grouping used by the Blocks & Extensions admin screen). "uncategorized" returns blocks that are registered but not yet listed in the block registry.', 'designsetgo' ),
					'enum'        => self::get_group_enum(),
					'default'     => 'all',
				),
				'detail' => array(
					'type'        => 'string',
					'description' => __( 'Level of attribute detail. "summary" returns type/default/enum only. "full" returns complete attribute definitions including minimum, maximum, nested properties, and items for arrays.', 'designsetgo' ),
					'enum'        => array( 'summary', 'full' ),
					'default'     => 'summary',
				),
				'blocks' => array(
					'type'        => 'array',
					'description' => __( 'Filter to specific block names (e.g., ["designsetgo/section", "designsetgo/row"]). When combined with detail "full", limits verbose output to only the requested blocks.', 'designsetgo' ),
					'items'       => array(
						'type' => 'string',
					),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Get output schema.
	 *
	 * @return array<string, mixed>
	 */
	private function get_output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'blocks' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'name'        => array(
								'type'        => 'string',
								'description' => __( 'Block name', 'designsetgo' ),
							),
							'title'       => array(
								'type'        => 'string',
								'description' => __( 'Human-readable title', 'designsetgo' ),
							),
							'description' => array(
								'type'        => 'string',
								'description' => __( 'Block description', 'designsetgo' ),
							),
							'category'    => array(
								'type'        => 'string',
								'description' => __( 'The block editor category the block registers into, verbatim from block.json (almost always "designsetgo").', 'designsetgo' ),
							),
							'group'       => array(
								'type'        => 'string',
								'description' => __( 'The plugin\'s own grouping: containers, ui, interactive, widgets, forms, or uncategorized.', 'designsetgo' ),
							),
							'attributes'  => array(
								'type'        => 'object',
								'description' => __( 'Available block attributes', 'designsetgo' ),
							),
							'supports'    => array(
								'type'        => 'object',
								'description' => __( 'Block support features', 'designsetgo' ),
							),
						),
					),
				),
				'total'  => array(
					'type'        => 'integer',
					'description' => __( 'Total number of blocks returned', 'designsetgo' ),
				),
			),
		);
	}

	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	public function check_permission_callback(): bool {
		// Anyone who can read content can list blocks.
		return $this->check_permission( 'read' );
	}

	/**
	 * Execute the ability.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>
	 */
	public function execute( array $input ): array {
		$group        = $input['group'] ?? 'all';
		$detail       = $input['detail'] ?? 'summary';
		$block_filter = $input['blocks'] ?? array();
		$use_full     = 'full' === $detail;

		// Get all DesignSetGo blocks.
		$all_blocks = $this->get_all_blocks( $use_full );

		// Filter by specific block names if provided.
		if ( ! empty( $block_filter ) ) {
			$all_blocks = array_filter(
				$all_blocks,
				function ( $block ) use ( $block_filter ) {
					return in_array( $block['name'], $block_filter, true );
				}
			);
		}

		// Filter by group if specified.
		if ( 'all' !== $group ) {
			$all_blocks = array_filter(
				$all_blocks,
				function ( $block ) use ( $group ) {
					return $block['group'] === $group;
				}
			);
		}

		return array(
			'blocks' => array_values( $all_blocks ),
			'total'  => count( $all_blocks ),
		);
	}

	/**
	 * Get all DesignSetGo blocks with their metadata.
	 *
	 * Dynamically retrieves blocks from the WordPress block registry,
	 * ensuring the list is always up-to-date with registered blocks.
	 *
	 * @param bool $full_detail Whether to include full attribute definitions.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_all_blocks( bool $full_detail = false ): array {
		$registry = \WP_Block_Type_Registry::get_instance();
		$blocks   = array();

		foreach ( $registry->get_all_registered() as $block_type ) {
			// Only include DesignSetGo blocks.
			if ( 0 !== strpos( $block_type->name, 'designsetgo/' ) ) {
				continue;
			}

			$block_data = array(
				'name'        => $block_type->name,
				'title'       => '' !== $block_type->title ? $block_type->title : $this->generate_title_from_name( $block_type->name ),
				'description' => $block_type->description,
				'category'    => $block_type->category ?? '',
				'group'       => self::get_block_group( $block_type->name ),
				'supports'    => $this->format_supports( $block_type->supports ?? array() ),
				'parent'      => $block_type->parent ?? null,
				'icon'        => is_string( $block_type->icon ) ? $block_type->icon : null,
			);

			if ( $full_detail ) {
				$block_data['attributes'] = $this->format_attributes_full( $block_type->attributes ?? array() );
			} else {
				$block_data['attributes'] = $this->format_attributes( $block_type->attributes ?? array() );
			}

			$blocks[] = $block_data;
		}

		// Sort blocks by name for consistent ordering.
		usort(
			$blocks,
			function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return $blocks;
	}

	/**
	 * Map a block name to the plugin's own grouping.
	 *
	 * The source of truth is includes/admin/blocks-registry.json — the same
	 * file that drives the Blocks & Extensions admin screen — so this
	 * grouping stays in step with what site owners see there. Blocks that
	 * are registered but not yet listed in that file report
	 * "uncategorized" rather than being silently bucketed somewhere wrong.
	 *
	 * @param string $block_name Full block name (e.g. 'designsetgo/section').
	 * @return string Group slug.
	 */
	private static function get_block_group( string $block_name ): string {
		$map = self::get_group_map();

		return $map[ $block_name ] ?? 'uncategorized';
	}

	/**
	 * Build the block-name => group lookup from the block registry.
	 *
	 * @return array<string, string>
	 */
	private static function get_group_map(): array {
		static $map = null;

		if ( null !== $map ) {
			return $map;
		}

		$map = array();

		if ( ! class_exists( Settings::class ) ) {
			return $map;
		}

		foreach ( Settings::get_available_blocks() as $group_slug => $group ) {
			if ( empty( $group['blocks'] ) || ! is_array( $group['blocks'] ) ) {
				continue;
			}

			foreach ( $group['blocks'] as $block ) {
				if ( isset( $block['name'] ) ) {
					$map[ $block['name'] ] = $group_slug;
				}
			}
		}

		return $map;
	}

	/**
	 * Enum of accepted values for the `group` input.
	 *
	 * Derived from the registry so a new group added to
	 * blocks-registry.json becomes filterable without touching this file.
	 *
	 * @return array<int, string>
	 */
	private static function get_group_enum(): array {
		$groups = array_values( array_unique( array_values( self::get_group_map() ) ) );
		sort( $groups );

		return array_merge( array( 'all' ), $groups, array( 'uncategorized' ) );
	}

	/**
	 * Generate a human-readable title from block name.
	 *
	 * @param string $name Block name (e.g., 'designsetgo/icon-button').
	 * @return string Human-readable title.
	 */
	private function generate_title_from_name( string $name ): string {
		// Remove namespace prefix.
		$title = str_replace( 'designsetgo/', '', $name );

		// Convert kebab-case to Title Case.
		$title = str_replace( '-', ' ', $title );
		$title = ucwords( $title );

		return $title;
	}

	/**
	 * Format block attributes for API output.
	 *
	 * Simplifies the attribute definitions for cleaner API responses.
	 *
	 * @param array<string, mixed> $attributes Block attributes from registry.
	 * @return array<string, mixed> Formatted attributes.
	 */
	private function format_attributes( array $attributes ): array {
		$formatted = array();

		foreach ( $attributes as $name => $definition ) {
			$attr = array(
				'type' => $definition['type'] ?? 'string',
			);

			if ( isset( $definition['default'] ) ) {
				$attr['default'] = $definition['default'];
			}

			if ( isset( $definition['enum'] ) ) {
				$attr['enum'] = $definition['enum'];
			}

			$formatted[ $name ] = $attr;
		}

		return $formatted;
	}

	/**
	 * Format block attributes with full detail for API output.
	 *
	 * Returns complete attribute definitions from the block registry including
	 * minimum, maximum, nested properties, items for arrays, and all other
	 * schema metadata. This provides agents with full knowledge of valid values.
	 *
	 * @param array<string, mixed> $attributes Block attributes from registry.
	 * @return array<string, mixed> Full attribute definitions.
	 */
	private function format_attributes_full( array $attributes ): array {
		$formatted = array();

		foreach ( $attributes as $name => $definition ) {
			$attr = array(
				'type' => $definition['type'] ?? 'string',
			);

			// Include all available schema properties.
			$schema_keys = array(
				'default',
				'enum',
				'minimum',
				'maximum',
				'minLength',
				'maxLength',
				'pattern',
				'items',
				'properties',
				'required',
				'format',
				'source',
				'selector',
				'attribute',
			);

			foreach ( $schema_keys as $key ) {
				if ( isset( $definition[ $key ] ) ) {
					$attr[ $key ] = $definition[ $key ];
				}
			}

			$formatted[ $name ] = $attr;
		}

		return $formatted;
	}

	/**
	 * Format block supports for API output.
	 *
	 * Converts supports object to a simplified array of supported features.
	 *
	 * @param array<string, mixed>|object $supports Block supports from registry.
	 * @return array<string> List of supported features.
	 */
	private function format_supports( $supports ): array {
		if ( empty( $supports ) ) {
			return array();
		}

		// Convert object to array if needed.
		$supports = (array) $supports;

		$supported = array();

		// Map common support features.
		$feature_map = array(
			'color'      => array( 'color', '__experimentalColor' ),
			'spacing'    => array( 'spacing', '__experimentalSpacing' ),
			'typography' => array( 'typography', '__experimentalTypography' ),
			'align'      => array( 'align' ),
			'anchor'     => array( 'anchor' ),
			'html'       => array( 'html' ),
			'className'  => array( 'className', 'customClassName' ),
		);

		foreach ( $feature_map as $feature => $keys ) {
			foreach ( $keys as $key ) {
				if ( isset( $supports[ $key ] ) && false !== $supports[ $key ] ) {
					$supported[] = $feature;
					break;
				}
			}
		}

		return array_unique( $supported );
	}
}
