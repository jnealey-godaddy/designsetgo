<?php
/**
 * Block Schema Loader for DesignSetGo Abilities.
 *
 * Reads block.json files and converts block attributes to JSON Schema format
 * for use with the WordPress Abilities API. This provides a single source of
 * truth for block attribute definitions.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.1.0
 */

namespace DesignSetGo\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block Schema Loader class.
 */
class Block_Schema_Loader {

	/**
	 * Maximum file size for JSON files (1MB).
	 *
	 * @var int
	 */
	private const MAX_FILE_SIZE = 1048576;

	/**
	 * Maximum JSON decode depth.
	 *
	 * @var int
	 */
	private const MAX_JSON_DEPTH = 32;

	/**
	 * Maximum length for attribute names (ReDoS protection).
	 *
	 * @var int
	 */
	private const MAX_ATTR_NAME_LENGTH = 200;

	/**
	 * Cache for loaded block schemas.
	 *
	 * @var array<string, array>
	 */
	private static array $schema_cache = array();

	/**
	 * Get the full input schema for a block's attributes.
	 *
	 * Loads the block.json file, converts attributes to JSON Schema format,
	 * adds descriptions, and merges with common ability input parameters.
	 *
	 * @param string               $block_name      Block name (e.g., 'designsetgo/section').
	 * @param array<string>        $include_attrs   Optional. Specific attributes to include. Empty means all.
	 * @param array<string>        $exclude_attrs   Optional. Attributes to exclude.
	 * @param array<string, mixed> $extra_properties Optional. Additional properties to add to the schema.
	 * @return array<string, mixed> Full JSON Schema for ability input.
	 */
	public static function get_ability_input_schema(
		string $block_name,
		array $include_attrs = array(),
		array $exclude_attrs = array(),
		array $extra_properties = array()
	): array {
		$block_schema = self::get_block_attributes_schema( $block_name, $include_attrs, $exclude_attrs );

		// Merge common input schema with block-specific attributes.
		$common = Block_Configurator::get_common_input_schema();

		// Add block_name to common schema.
		$common['block_name'] = array(
			'type'        => 'string',
			'description' => __( 'Block type to configure', 'designsetgo' ),
			'default'     => $block_name,
		);

		$properties = array_merge( $common, $extra_properties );

		// Wrap block attributes in an 'attributes' property if we have any.
		if ( ! empty( $block_schema['properties'] ) ) {
			$properties['attributes'] = array(
				'type'                 => 'object',
				'description'          => sprintf(
					/* translators: %s: Block name */
					__( 'Attributes for the %s block', 'designsetgo' ),
					$block_name
				),
				'properties'           => $block_schema['properties'],
				'additionalProperties' => false,
			);
		}

		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => array( 'post_id', 'attributes' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Get JSON Schema for a block's attributes from its block.json file.
	 *
	 * @param string        $block_name    Block name (e.g., 'designsetgo/section').
	 * @param array<string> $include_attrs Optional. Specific attributes to include.
	 * @param array<string> $exclude_attrs Optional. Attributes to exclude.
	 * @return array<string, mixed> JSON Schema with properties for each attribute.
	 */
	public static function get_block_attributes_schema(
		string $block_name,
		array $include_attrs = array(),
		array $exclude_attrs = array()
	): array {
		$block_data = self::get_block_json( $block_name );

		if ( empty( $block_data ) || empty( $block_data['attributes'] ) ) {
			return array( 'properties' => array() );
		}

		$attributes   = $block_data['attributes'];
		$metadata     = $block_data['schemaMetadata'] ?? array();
		$descriptions = $metadata['attributeDescriptions'] ?? array();

		$properties = array();

		foreach ( $attributes as $attr_name => $attr_def ) {
			// Filter by include list if specified.
			if ( ! empty( $include_attrs ) && ! in_array( $attr_name, $include_attrs, true ) ) {
				continue;
			}

			// Filter by exclude list.
			if ( in_array( $attr_name, $exclude_attrs, true ) ) {
				continue;
			}

			$properties[ $attr_name ] = self::convert_attribute_to_schema(
				$attr_name,
				$attr_def,
				$descriptions[ $attr_name ] ?? null
			);
		}

		return array( 'properties' => $properties );
	}

	/**
	 * Get all available block names from DesignSetGo.
	 *
	 * @return array<string> List of block names.
	 */
	public static function get_available_blocks(): array {
		$blocks_dir = DESIGNSETGO_PATH . 'build/blocks/';

		if ( ! is_dir( $blocks_dir ) ) {
			return array();
		}

		// Resolve to real path for comparison.
		$blocks_dir_real = realpath( $blocks_dir );
		if ( false === $blocks_dir_real ) {
			return array();
		}

		$block_names = array();
		$dirs        = glob( $blocks_dir . '*', GLOB_ONLYDIR );

		if ( ! $dirs ) {
			return array();
		}

		foreach ( $dirs as $dir ) {
			// Verify path stays within blocks directory (path traversal protection).
			$dir_real = realpath( $dir );
			if ( false === $dir_real || 0 !== strpos( $dir_real, $blocks_dir_real ) ) {
				continue;
			}

			$block_json = $dir_real . '/block.json';
			if ( file_exists( $block_json ) ) {
				$data = self::read_json_file( $block_json );
				if ( ! empty( $data['name'] ) ) {
					$block_names[] = $data['name'];
				}
			}
		}

		return $block_names;
	}

	/**
	 * Get parsed block.json data for a block.
	 *
	 * @param string $block_name Block name (e.g., 'designsetgo/section').
	 * @return array<string, mixed>|null Block data or null if not found.
	 */
	public static function get_block_json( string $block_name ): ?array {
		// Check cache first.
		if ( isset( self::$schema_cache[ $block_name ] ) ) {
			return self::$schema_cache[ $block_name ];
		}

		// Validate block name format (namespace/slug pattern, alphanumeric with hyphens).
		if ( ! preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/i', $block_name ) ) {
			return null;
		}

		// Extract the block slug from the name.
		$parts = explode( '/', $block_name );
		$slug  = end( $parts );

		// Additional slug validation - must be alphanumeric with hyphens only.
		if ( ! preg_match( '/^[a-z0-9-]+$/i', $slug ) ) {
			return null;
		}

		// Look in build directory first, then src.
		$base_dirs = array(
			DESIGNSETGO_PATH . 'build/blocks/',
			DESIGNSETGO_PATH . 'src/blocks/',
		);

		foreach ( $base_dirs as $base_dir ) {
			$base_real = realpath( $base_dir );
			if ( false === $base_real ) {
				continue;
			}

			$path      = $base_dir . $slug . '/block.json';
			$path_real = realpath( $path );

			// Verify resolved path stays within expected directory (path traversal protection).
			if ( false === $path_real || 0 !== strpos( $path_real, $base_real ) ) {
				continue;
			}

			if ( file_exists( $path_real ) ) {
				$data = self::read_json_file( $path_real );
				if ( $data ) {
					self::$schema_cache[ $block_name ] = $data;
					return $data;
				}
			}
		}

		return null;
	}

	/**
	 * Convert a block.json attribute definition to JSON Schema format.
	 *
	 * @param string      $attr_name   Attribute name.
	 * @param array       $attr_def    Attribute definition from block.json.
	 * @param string|null $description Optional custom description.
	 * @return array<string, mixed> JSON Schema property definition.
	 */
	private static function convert_attribute_to_schema(
		string $attr_name,
		array $attr_def,
		?string $description = null
	): array {
		$schema = array(
			'type'        => $attr_def['type'] ?? 'string',
			'description' => $description ?? self::generate_description( $attr_name, $attr_def ),
		);

		// Copy over enum if present.
		if ( isset( $attr_def['enum'] ) ) {
			$schema['enum'] = $attr_def['enum'];
		}

		// Copy over default if present.
		if ( array_key_exists( 'default', $attr_def ) ) {
			$schema['default'] = $attr_def['default'];
		}

		// Handle object types with nested properties.
		if ( 'object' === $schema['type'] && isset( $attr_def['properties'] ) ) {
			$schema['properties'] = array();
			foreach ( $attr_def['properties'] as $prop_name => $prop_def ) {
				$schema['properties'][ $prop_name ] = self::convert_attribute_to_schema(
					$prop_name,
					$prop_def,
					null
				);
			}
		}

		// Handle array types with item definitions.
		if ( 'array' === $schema['type'] && isset( $attr_def['items'] ) ) {
			$schema['items'] = self::convert_attribute_to_schema( 'item', $attr_def['items'], null );
		}

		// Add constraints if present.
		if ( isset( $attr_def['minimum'] ) ) {
			$schema['minimum'] = $attr_def['minimum'];
		}
		if ( isset( $attr_def['maximum'] ) ) {
			$schema['maximum'] = $attr_def['maximum'];
		}

		return $schema;
	}

	/**
	 * Generate a human-readable description for an attribute.
	 *
	 * Uses intelligent defaults based on attribute name patterns and types.
	 *
	 * @param string $attr_name Attribute name.
	 * @param array  $attr_def  Attribute definition.
	 * @return string Generated description.
	 */
	private static function generate_description( string $attr_name, array $attr_def ): string {
		// Check for known patterns and generate descriptions.
		$known_descriptions = self::get_known_descriptions();

		if ( isset( $known_descriptions[ $attr_name ] ) ) {
			return $known_descriptions[ $attr_name ];
		}

		// Generate based on naming patterns.
		$type = $attr_def['type'] ?? 'string';

		// Handle common suffixes/prefixes.
		if ( preg_match( '/^hover(.+)Color$/i', $attr_name, $matches ) ) {
			$element = self::camel_to_words( $matches[1] );
			/* translators: %s: Element name (e.g., "background", "text") */
			return sprintf( __( 'Color for %s on hover', 'designsetgo' ), strtolower( $element ) );
		}

		if ( preg_match( '/(.+)Color$/i', $attr_name, $matches ) ) {
			$element = self::camel_to_words( $matches[1] );
			/* translators: %s: Element name (e.g., "background", "border") */
			return sprintf( __( 'Color for %s', 'designsetgo' ), strtolower( $element ) );
		}

		if ( preg_match( '/^show(.+)$/i', $attr_name, $matches ) ) {
			$element = self::camel_to_words( $matches[1] );
			/* translators: %s: Element name to show/hide */
			return sprintf( __( 'Whether to show the %s', 'designsetgo' ), strtolower( $element ) );
		}

		if ( preg_match( '/^enable(.+)$/i', $attr_name, $matches ) ) {
			$element = self::camel_to_words( $matches[1] );
			/* translators: %s: Feature name to enable */
			return sprintf( __( 'Enable %s', 'designsetgo' ), strtolower( $element ) );
		}

		if ( preg_match( '/^allow(.+)$/i', $attr_name, $matches ) ) {
			$element = self::camel_to_words( $matches[1] );
			/* translators: %s: Feature name to allow */
			return sprintf( __( 'Allow %s', 'designsetgo' ), strtolower( $element ) );
		}

		if ( preg_match( '/^is(.+)$/i', $attr_name, $matches ) ) {
			$element = self::camel_to_words( $matches[1] );
			/* translators: %s: State or condition name */
			return sprintf( __( 'Whether the element is %s', 'designsetgo' ), strtolower( $element ) );
		}

		if ( preg_match( '/(.+)Position$/i', $attr_name, $matches ) ) {
			$element = self::camel_to_words( $matches[1] );
			/* translators: %s: Element name for position */
			return sprintf( __( 'Position of the %s', 'designsetgo' ), strtolower( $element ) );
		}

		if ( preg_match( '/(.+)Size$/i', $attr_name, $matches ) ) {
			$element = self::camel_to_words( $matches[1] );
			/* translators: %s: Element name for size */
			return sprintf( __( 'Size of the %s', 'designsetgo' ), strtolower( $element ) );
		}

		if ( preg_match( '/(.+)Style$/i', $attr_name, $matches ) ) {
			$element = self::camel_to_words( $matches[1] );
			/* translators: %s: Element name for style */
			return sprintf( __( 'Style for the %s', 'designsetgo' ), strtolower( $element ) );
		}

		if ( preg_match( '/(.+)Width$/i', $attr_name, $matches ) ) {
			$element = self::camel_to_words( $matches[1] );
			/* translators: %s: Element name for width */
			return sprintf( __( 'Width of the %s', 'designsetgo' ), strtolower( $element ) );
		}

		if ( preg_match( '/(.+)Height$/i', $attr_name, $matches ) ) {
			$element = self::camel_to_words( $matches[1] );
			/* translators: %s: Element name for height */
			return sprintf( __( 'Height of the %s', 'designsetgo' ), strtolower( $element ) );
		}

		if ( preg_match( '/(.+)Gap$/i', $attr_name, $matches ) ) {
			$element = self::camel_to_words( $matches[1] );
			/* translators: %s: Element name for gap spacing */
			return sprintf( __( 'Gap spacing for %s', 'designsetgo' ), strtolower( $element ) );
		}

		if ( preg_match( '/^shapeDivider(Top|Bottom)(.*)$/i', $attr_name, $matches ) ) {
			$position = strtolower( $matches[1] );
			$property = $matches[2] ? self::camel_to_words( $matches[2] ) : 'shape';
			/* translators: 1: Position (top/bottom), 2: Property name */
			return sprintf( __( 'Shape divider %1$s %2$s', 'designsetgo' ), $position, strtolower( $property ) );
		}

		// Generate description from camelCase name.
		$readable_name = self::camel_to_words( $attr_name );

		// Build description based on type.
		switch ( $type ) {
			case 'boolean':
				/* translators: %s: Attribute name */
				return sprintf( __( 'Enable or disable %s', 'designsetgo' ), strtolower( $readable_name ) );
			case 'number':
			case 'integer':
				/* translators: %s: Attribute name */
				return sprintf( __( 'Numeric value for %s', 'designsetgo' ), strtolower( $readable_name ) );
			case 'object':
				/* translators: %s: Attribute name */
				return sprintf( __( 'Settings for %s', 'designsetgo' ), strtolower( $readable_name ) );
			case 'array':
				/* translators: %s: Attribute name */
				return sprintf( __( 'List of %s', 'designsetgo' ), strtolower( $readable_name ) );
			default:
				/* translators: %s: Attribute name */
				return sprintf( __( 'Value for %s', 'designsetgo' ), strtolower( $readable_name ) );
		}
	}

	/**
	 * Get known attribute descriptions.
	 *
	 * Maps common attribute names to their descriptions.
	 *
	 * @return array<string, string> Map of attribute names to descriptions.
	 */
	private static function get_known_descriptions(): array {
		return array(
			// Common layout attributes.
			'align'             => __( 'Block alignment (wide, full, left, center, right)', 'designsetgo' ),
			'tagName'           => __( 'HTML tag to use for the container element', 'designsetgo' ),
			'constrainWidth'    => __( 'Whether to constrain the content width', 'designsetgo' ),
			'contentWidth'      => __( 'Maximum width for the content area', 'designsetgo' ),
			'mobileStack'       => __( 'Stack items vertically on mobile devices', 'designsetgo' ),
			'style'             => __( 'WordPress block style object containing spacing, colors, and typography settings', 'designsetgo' ),

			// Overlay and effects.
			'overlayColor'      => __( 'Background overlay color', 'designsetgo' ),

			// Accordion-specific.
			'allowMultipleOpen' => __( 'Allow multiple accordion items to be open at once', 'designsetgo' ),
			'borderBetween'     => __( 'Show border between accordion items', 'designsetgo' ),
			'itemGap'           => __( 'Spacing between accordion items', 'designsetgo' ),

			// Button/link attributes.
			'url'               => __( 'Link URL', 'designsetgo' ),
			'text'              => __( 'Text content', 'designsetgo' ),
			'linkTarget'        => __( 'Link target (_blank for new tab)', 'designsetgo' ),
			'rel'               => __( 'Link rel attribute (nofollow, noopener, etc.)', 'designsetgo' ),

			// Media attributes.
			'imageId'           => __( 'WordPress media library image ID', 'designsetgo' ),
			'imageUrl'          => __( 'Image URL', 'designsetgo' ),
			'imageAlt'          => __( 'Image alt text for accessibility', 'designsetgo' ),
			'mediaType'         => __( 'Type of media (image, video)', 'designsetgo' ),

			// Icon attributes.
			'icon'              => __( 'Icon identifier', 'designsetgo' ),
			'iconPrefix'        => __( 'Icon library prefix', 'designsetgo' ),
		);
	}

	/**
	 * Convert camelCase to readable words.
	 *
	 * @param string $str CamelCase string.
	 * @return string Words separated by spaces.
	 */
	private static function camel_to_words( string $str ): string {
		// Limit input length to prevent ReDoS attacks.
		if ( strlen( $str ) > self::MAX_ATTR_NAME_LENGTH ) {
			$str = substr( $str, 0, self::MAX_ATTR_NAME_LENGTH );
		}

		// Insert spaces before uppercase letters.
		$result = preg_replace( '/([a-z])([A-Z])/', '$1 $2', $str );
		// Handle consecutive uppercase (e.g., "HTMLParser" -> "HTML Parser").
		$result = preg_replace( '/([A-Z]+)([A-Z][a-z])/', '$1 $2', $result );
		return $result;
	}

	/**
	 * Read and parse a JSON file.
	 *
	 * @param string $path File path.
	 * @return array<string, mixed>|null Parsed data or null on failure.
	 */
	private static function read_json_file( string $path ): ?array {
		if ( ! file_exists( $path ) ) {
			return null;
		}

		// Check file size to prevent DoS attacks.
		$file_size = filesize( $path );
		if ( false === $file_size || $file_size > self::MAX_FILE_SIZE ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Reading local plugin block.json file.
		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			return null;
		}

		// Decode with depth limit to prevent nested structure DoS.
		$data = json_decode( $contents, true, self::MAX_JSON_DEPTH );

		if ( ! is_array( $data ) ) {
			return null;
		}

		return $data;
	}

	/**
	 * Clear the schema cache.
	 *
	 * Useful when block.json files are updated.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$schema_cache = array();
	}

	/**
	 * Get attribute names for a block.
	 *
	 * @param string $block_name Block name.
	 * @return array<string> List of attribute names.
	 */
	public static function get_attribute_names( string $block_name ): array {
		$block_data = self::get_block_json( $block_name );

		if ( empty( $block_data ) || empty( $block_data['attributes'] ) ) {
			return array();
		}

		return array_keys( $block_data['attributes'] );
	}

	/**
	 * Check if a block has a specific attribute.
	 *
	 * @param string $block_name Block name.
	 * @param string $attr_name  Attribute name.
	 * @return bool
	 */
	public static function has_attribute( string $block_name, string $attr_name ): bool {
		$block_data = self::get_block_json( $block_name );

		if ( empty( $block_data ) || empty( $block_data['attributes'] ) ) {
			return false;
		}

		return isset( $block_data['attributes'][ $attr_name ] );
	}

	/**
	 * Get the default value for an attribute.
	 *
	 * @param string $block_name Block name.
	 * @param string $attr_name  Attribute name.
	 * @return mixed Default value or null if not defined.
	 */
	public static function get_attribute_default( string $block_name, string $attr_name ) {
		$block_data = self::get_block_json( $block_name );

		if ( empty( $block_data ) || empty( $block_data['attributes'][ $attr_name ] ) ) {
			return null;
		}

		return $block_data['attributes'][ $attr_name ]['default'] ?? null;
	}

	/**
	 * Get supported features for a block.
	 *
	 * Returns the 'supports' section from block.json which defines
	 * WordPress core features the block supports.
	 *
	 * @param string $block_name Block name.
	 * @return array<string, mixed> Supports configuration.
	 */
	public static function get_block_supports( string $block_name ): array {
		$block_data = self::get_block_json( $block_name );

		if ( empty( $block_data ) || empty( $block_data['supports'] ) ) {
			return array();
		}

		return $block_data['supports'];
	}

	/**
	 * Get HTML mappings for a block.
	 *
	 * Returns the 'htmlMappings' section from schemaMetadata in block.json
	 * which defines how attributes map to data attributes, CSS classes, and CSS variables.
	 *
	 * @param string $block_name Block name.
	 * @return array<string, mixed> HTML mappings configuration.
	 */
	public static function get_html_mappings( string $block_name ): array {
		$block_data = self::get_block_json( $block_name );

		if ( empty( $block_data ) || empty( $block_data['schemaMetadata']['htmlMappings'] ) ) {
			return array();
		}

		return $block_data['schemaMetadata']['htmlMappings'];
	}
}
