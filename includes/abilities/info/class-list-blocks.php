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
				'instructions' => 'Returns all DesignSetGo blocks with their attributes and metadata. Use category filter to narrow results. Use detail "full" with specific block names for complete attribute definitions.',
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
				'category' => array(
					'type'        => 'string',
					'description' => __( 'Filter by category', 'designsetgo' ),
					'enum'        => array( 'all', 'layout', 'interactive', 'visual', 'dynamic' ),
					'default'     => 'all',
				),
				'detail'   => array(
					'type'        => 'string',
					'description' => __( 'Level of attribute detail. "summary" returns type/default/enum only. "full" returns complete attribute definitions including minimum, maximum, nested properties, and items for arrays.', 'designsetgo' ),
					'enum'        => array( 'summary', 'full' ),
					'default'     => 'summary',
				),
				'blocks'   => array(
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
								'description' => __( 'Block category', 'designsetgo' ),
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
		$category     = $input['category'] ?? 'all';
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

		// Filter by category if specified.
		if ( 'all' !== $category ) {
			$all_blocks = array_filter(
				$all_blocks,
				function ( $block ) use ( $category ) {
					return $block['category'] === $category;
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
				'category'    => $this->normalize_category( $block_type->category ?? 'designsetgo' ),
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
	 * Normalize block category to simplified categories for filtering.
	 *
	 * Maps various block categories to four main categories:
	 * - layout: Container and layout blocks
	 * - interactive: Blocks with user interaction (accordions, tabs, etc.)
	 * - visual: Display and media blocks
	 * - dynamic: Animated and data-driven blocks
	 *
	 * @param string $category Original block category.
	 * @return string Normalized category.
	 */
	private function normalize_category( string $category ): string {
		$category_map = array(
			// Layout categories.
			'designsetgo'             => 'layout',
			'designsetgo-layout'      => 'layout',
			'designsetgo-containers'  => 'layout',
			'layout'                  => 'layout',

			// Interactive categories.
			'designsetgo-interactive' => 'interactive',
			'designsetgo-navigation'  => 'interactive',
			'interactive'             => 'interactive',

			// Visual categories.
			'designsetgo-visual'      => 'visual',
			'designsetgo-media'       => 'visual',
			'designsetgo-elements'    => 'visual',
			'visual'                  => 'visual',
			'media'                   => 'visual',

			// Dynamic categories.
			'designsetgo-dynamic'     => 'dynamic',
			'designsetgo-animated'    => 'dynamic',
			'dynamic'                 => 'dynamic',
		);

		return $category_map[ $category ] ?? 'visual';
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
