<?php
/**
 * List Abilities Info Ability.
 *
 * Returns a manifest of all registered DesignSetGo abilities with their
 * names, descriptions, categories, and input schemas. Enables agent systems
 * to discover available tools at startup and generate tool definitions
 * dynamically.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.1.0
 */

namespace DesignSetGo\Abilities\Info;

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Abilities\Abilities_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List Abilities info ability class.
 */
class List_Abilities extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/list-abilities';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'List DesignSetGo Abilities', 'designsetgo' ),
			'description'         => __( 'Returns a manifest of all registered DesignSetGo abilities with their names, descriptions, categories, and input schemas. Use this to discover what DesignSetGo can do.', 'designsetgo' ),
			'category'            => 'info',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'discover', 'tools', 'capabilities', 'help' ),
			'annotations'         => array(
				'readonly'     => true,
				'instructions' => 'Call this first to discover all available DesignSetGo abilities. Use category filter to narrow by type: inserter, configurator, or info.',
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
					'description' => __( 'Filter by ability category', 'designsetgo' ),
					'enum'        => array( 'all', 'inserter', 'configurator', 'info', 'settings' ),
					'default'     => 'all',
				),
				'search'   => array(
					'type'        => 'string',
					'description' => __( 'Search abilities by name, description, or keyword. Matches against ability names, labels, descriptions, and keyword aliases (e.g., searching "group" finds section-related abilities).', 'designsetgo' ),
					'default'     => '',
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
				'abilities' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'name'         => array(
								'type'        => 'string',
								'description' => __( 'Ability name (e.g., designsetgo/insert-section)', 'designsetgo' ),
							),
							'label'        => array(
								'type'        => 'string',
								'description' => __( 'Human-readable label', 'designsetgo' ),
							),
							'description'  => array(
								'type'        => 'string',
								'description' => __( 'What this ability does', 'designsetgo' ),
							),
							'category'     => array(
								'type'        => 'string',
								'description' => __( 'Ability category: inserter, configurator, or info', 'designsetgo' ),
							),
							'input_schema' => array(
								'type'        => 'object',
								'description' => __( 'Full JSON Schema for the ability inputs', 'designsetgo' ),
							),
						),
					),
				),
				'total'     => array(
					'type'        => 'integer',
					'description' => __( 'Total number of abilities returned', 'designsetgo' ),
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
		return $this->check_permission( 'read' );
	}

	/**
	 * Execute the ability.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>
	 */
	public function execute( array $input ): array {
		$category_filter = $input['category'] ?? 'all';
		$search_term     = $input['search'] ?? '';

		$registry  = Abilities_Registry::get_instance();
		$abilities = $registry->get_abilities();
		$result    = array();

		foreach ( $abilities as $ability ) {
			$config   = $ability->get_config();
			$name     = $ability->get_name();
			$category = $this->infer_category( $name );

			// Filter by category if specified.
			if ( 'all' !== $category_filter && $category !== $category_filter ) {
				continue;
			}

			$label       = $config['label'] ?? $name;
			$description = $config['description'] ?? '';
			$keywords    = $config['keywords'] ?? array();

			// Filter by search term if specified.
			if ( '' !== $search_term && ! $this->matches_search( $search_term, $name, $label, $description, $keywords ) ) {
				continue;
			}

			$result[] = array(
				'name'         => $name,
				'label'        => $label,
				'description'  => $description,
				'category'     => $category,
				'input_schema' => $config['input_schema'] ?? array(),
			);
		}

		// Sort by category then name for consistent ordering.
		usort(
			$result,
			function ( $a, $b ) {
				$cat_cmp = strcmp( $a['category'], $b['category'] );
				if ( 0 !== $cat_cmp ) {
					return $cat_cmp;
				}
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return array(
			'abilities' => $result,
			'total'     => count( $result ),
		);
	}

	/**
	 * Check if an ability matches the search term.
	 *
	 * Performs case-insensitive matching against the ability name,
	 * label, description, and keyword aliases.
	 *
	 * @param string        $search_term Search term.
	 * @param string        $name        Ability name.
	 * @param string        $label       Ability label.
	 * @param string        $description Ability description.
	 * @param array<string> $keywords    Keyword aliases.
	 * @return bool Whether the ability matches.
	 */
	private function matches_search( string $search_term, string $name, string $label, string $description, array $keywords ): bool {
		$term = strtolower( $search_term );

		// Check name, label, and description.
		if ( false !== strpos( strtolower( $name ), $term ) ) {
			return true;
		}
		if ( false !== strpos( strtolower( $label ), $term ) ) {
			return true;
		}
		if ( false !== strpos( strtolower( $description ), $term ) ) {
			return true;
		}

		// Check keywords.
		foreach ( $keywords as $keyword ) {
			if ( false !== strpos( strtolower( $keyword ), $term ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Infer the ability category from its name.
	 *
	 * Uses naming conventions:
	 * - insert-*, add-* → inserter
	 * - configure-*, apply-*, batch-*, delete-*, update-* → configurator
	 * - list-*, get-*, find-* → info
	 * - *-settings → settings (takes precedence over the prefix rules)
	 *
	 * @param string $name Ability name (e.g., 'designsetgo/add-block').
	 * @return string Category: inserter, configurator, info, or settings.
	 */
	private function infer_category( string $name ): string {
		// Remove namespace prefix.
		$short_name = str_replace( 'designsetgo/', '', $name );

		// Suffix-based overrides run first so names like "update-settings"
		// aren't mis-bucketed as configurators.
		$suffix_map = array(
			'-settings' => 'settings',
		);

		foreach ( $suffix_map as $suffix => $category ) {
			$suffix_len = strlen( $suffix );
			if ( strlen( $short_name ) >= $suffix_len && 0 === substr_compare( $short_name, $suffix, -$suffix_len ) ) {
				return $category;
			}
		}

		$prefix_map = array(
			'insert-'    => 'inserter',
			'add-'       => 'inserter',
			'configure-' => 'configurator',
			'apply-'     => 'configurator',
			'batch-'     => 'configurator',
			'delete-'    => 'configurator',
			'update-'    => 'configurator',
			'list-'      => 'info',
			'get-'       => 'info',
			'find-'      => 'info',
		);

		foreach ( $prefix_map as $prefix => $category ) {
			if ( 0 === strpos( $short_name, $prefix ) ) {
				return $category;
			}
		}

		return 'configurator';
	}
}
