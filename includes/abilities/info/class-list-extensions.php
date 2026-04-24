<?php
/**
 * List Extensions Ability.
 *
 * Returns all DesignSetGo block extensions with their attribute schemas,
 * applicable blocks, and descriptions. Extensions add attributes to
 * existing blocks (e.g., animation, parallax, responsive visibility)
 * and are not blocks themselves — they won't appear in list-blocks.
 *
 * Use this to discover available extensions, then apply them via
 * update-block with the extension's attribute names.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.2.0
 */

namespace DesignSetGo\Abilities\Info;

use DesignSetGo\Abilities\Abstract_Ability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List Extensions ability class.
 */
class List_Extensions extends Abstract_Ability {

	/**
	 * Human-readable labels for extensions.
	 *
	 * @var array<string, array{label: string, description: string}>
	 */
	private const EXTENSION_META = array(
		'block-animations'       => array(
			'label'       => 'Block Animations',
			'description' => 'Entrance and exit animations triggered on scroll or load. Set dsgoAnimationEnabled to true, then configure the animation type, duration, delay, and easing.',
			'keywords'    => array( 'motion', 'animate', 'entrance', 'fade', 'slide' ),
		),
		'vertical-parallax'      => array(
			'label'       => 'Vertical Scroll Parallax',
			'description' => 'Parallax scrolling effects with configurable speed, direction, and optional rotation. Set dsgoParallaxEnabled to true to activate.',
			'keywords'    => array( 'parallax', 'scroll', 'depth', 'effect' ),
		),
		'expanding-background'   => array(
			'label'       => 'Expanding Background',
			'description' => 'Background that expands on scroll to fill the viewport. Set dsgoExpandingBgEnabled to true to activate.',
			'keywords'    => array( 'background', 'expand', 'hover', 'effect' ),
		),
		'text-reveal'            => array(
			'label'       => 'Text Reveal',
			'description' => 'Animated text reveal effect for headings and paragraphs. Set dsgoTextRevealEnabled to true to activate.',
			'keywords'    => array( 'text', 'typing', 'reveal', 'typewriter' ),
		),
		'background-video'       => array(
			'label'       => 'Background Video',
			'description' => 'Video background for container blocks. Set dsgoBackgroundVideoEnabled to true, then provide dsgoBackgroundVideoUrl.',
			'keywords'    => array( 'video', 'media', 'motion' ),
		),
		'clickable-group'        => array(
			'label'       => 'Clickable Group',
			'description' => 'Makes an entire container block clickable as a link. Set dsgoClickableEnabled to true and provide dsgoClickableUrl.',
			'keywords'    => array( 'link', 'anchor', 'clickable', 'wrapper' ),
		),
		'custom-css'             => array(
			'label'       => 'Custom CSS',
			'description' => 'Per-block custom CSS with responsive breakpoints (desktop, tablet, mobile). WARNING: Use the configure-custom-css ability instead of update-block for this extension — it sanitizes CSS to prevent XSS.',
			'keywords'    => array( 'style', 'code', 'stylesheet' ),
		),
		'responsive'             => array(
			'label'       => 'Responsive Visibility',
			'description' => 'Hide blocks on specific breakpoints. Set dsgoHideOnDesktop, dsgoHideOnTablet, or dsgoHideOnMobile to true.',
			'keywords'    => array( 'show', 'hide', 'mobile', 'desktop', 'tablet' ),
		),
		'max-width'              => array(
			'label'       => 'Max Width',
			'description' => 'Per-block max-width constraint with responsive values. Set dsgoMaxWidthEnabled to true and provide dsgoMaxWidth (desktop), dsgoMaxWidthTablet, dsgoMaxWidthMobile.',
			'keywords'    => array( 'width', 'constrain', 'narrow', 'container' ),
		),
		'reveal-container'       => array(
			'label'       => 'Reveal Container',
			'description' => 'Reveal animation for container blocks.',
			'keywords'    => array( 'reveal', 'animate', 'scroll' ),
		),
		'reveal-control'         => array(
			'label'       => 'Reveal Control',
			'description' => 'Controls for reveal animation behavior.',
			'keywords'    => array( 'reveal', 'trigger', 'scroll' ),
		),
		'grid-span'              => array(
			'label'       => 'Grid Span',
			'description' => 'Control how many columns/rows a child block spans within a grid container.',
			'keywords'    => array( 'columns', 'rows', 'span', 'layout' ),
		),
		'grid-mobile-order'      => array(
			'label'       => 'Grid Mobile Order',
			'description' => 'Override the display order of grid children on mobile devices.',
			'keywords'    => array( 'order', 'reorder', 'mobile', 'responsive' ),
		),
		'svg-patterns'           => array(
			'label'       => 'SVG Patterns',
			'description' => 'Decorative SVG pattern overlays for container blocks.',
			'keywords'    => array( 'pattern', 'decoration', 'overlay', 'texture' ),
		),
		'sticky-header-controls' => array(
			'label'       => 'Sticky Header Controls',
			'description' => 'Configuration for sticky/fixed header behavior.',
			'keywords'    => array( 'sticky', 'fixed', 'header', 'navigation' ),
		),
		'dynamic-tags'           => array(
			'label'       => 'Dynamic Tags',
			'description' => 'Bind block attributes (paragraph content, heading content, image url/alt, button text/url, etc.) to post / site / archive / user data or custom fields (ACF, Meta Box, Pods, JetEngine). Authors trigger the picker from the inline toolbar (database icon) or the Inspector "Dynamic Tags" panel. Bindings are written to attributes.metadata.bindings; use designsetgo/list-dynamic-tag-sources to enumerate sources.',
			'keywords'    => array( 'binding', 'dynamic', 'data', 'acf', 'meta', 'token' ),
		),
	);

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/list-extensions';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'List Extensions', 'designsetgo' ),
			'description'         => __( 'Returns all DesignSetGo block extensions with their attribute schemas and applicable blocks. Extensions add features like animation, parallax, and responsive visibility to existing blocks. Use update-block with the extension attribute names to apply them.', 'designsetgo' ),
			'category'            => 'info',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'discover', 'features', 'add-ons' ),
			'annotations'         => array(
				'readonly'     => true,
				'instructions' => 'Returns all DesignSetGo extensions with attribute schemas and applicable blocks. After discovering extensions, use update-block to apply extension attributes to any applicable block. Exception: use configure-custom-css for custom CSS (it sanitizes CSS for security).',
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
				'extension' => array(
					'type'        => 'string',
					'description' => __( 'Filter to a specific extension by name (e.g., "block-animations"). Omit to list all.', 'designsetgo' ),
				),
				'search'    => array(
					'type'        => 'string',
					'description' => __( 'Search extensions by name, description, or keyword. Matches against extension names, labels, descriptions, and keyword aliases (e.g., searching "parallax" or "scroll" finds the vertical-parallax extension).', 'designsetgo' ),
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
				'extensions' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'name'        => array(
								'type'        => 'string',
								'description' => __( 'Extension identifier', 'designsetgo' ),
							),
							'label'       => array(
								'type'        => 'string',
								'description' => __( 'Human-readable label', 'designsetgo' ),
							),
							'description' => array(
								'type'        => 'string',
								'description' => __( 'What the extension does and how to use it', 'designsetgo' ),
							),
							'blocks'      => array(
								'description' => __( 'Which blocks this extension applies to. "all" means all blocks (with possible exclusions).', 'designsetgo' ),
							),
							'exclude'     => array(
								'type'        => 'array',
								'description' => __( 'Blocks excluded from this extension', 'designsetgo' ),
							),
							'attributes'  => array(
								'type'        => 'object',
								'description' => __( 'Attribute schemas — use these names with update-block', 'designsetgo' ),
							),
						),
					),
				),
				'total'      => array(
					'type'        => 'integer',
					'description' => __( 'Total extensions returned', 'designsetgo' ),
				),
			),
		);
	}

	/**
	 * Check if an extension matches the search term.
	 *
	 * Performs case-insensitive matching against the extension name,
	 * label, description, and keyword aliases.
	 *
	 * @param string        $search_term Search term.
	 * @param string        $name        Extension name.
	 * @param string        $label       Extension label.
	 * @param string        $description Extension description.
	 * @param array<string> $keywords    Keyword aliases.
	 * @return bool Whether the extension matches.
	 */
	private function matches_search( string $search_term, string $name, string $label, string $description, array $keywords ): bool {
		$term = strtolower( $search_term );

		if ( false !== strpos( strtolower( $name ), $term ) ) {
			return true;
		}
		if ( false !== strpos( strtolower( $label ), $term ) ) {
			return true;
		}
		if ( false !== strpos( strtolower( $description ), $term ) ) {
			return true;
		}

		foreach ( $keywords as $keyword ) {
			if ( false !== strpos( strtolower( $keyword ), $term ) ) {
				return true;
			}
		}

		return false;
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
		$filter      = isset( $input['extension'] ) ? sanitize_text_field( $input['extension'] ) : '';
		$search_term = $input['search'] ?? '';

		$config_dir = DESIGNSETGO_PATH . 'includes/extension-configs/';
		if ( ! is_dir( $config_dir ) ) {
			return array(
				'extensions' => array(),
				'total'      => 0,
			);
		}

		$files = glob( $config_dir . '*.php' );
		if ( empty( $files ) ) {
			return array(
				'extensions' => array(),
				'total'      => 0,
			);
		}

		$extensions = array();

		foreach ( $files as $file ) {
			$name   = basename( $file, '.php' );
			$config = include $file;

			if ( ! is_array( $config ) || ! isset( $config['attributes'] ) ) {
				continue;
			}

			// Filter to specific extension if requested.
			if ( ! empty( $filter ) && $name !== $filter ) {
				continue;
			}

			$meta = self::EXTENSION_META[ $name ] ?? array(
				'label'       => ucwords( str_replace( '-', ' ', $name ) ),
				'description' => '',
			);

			$label       = $meta['label'];
			$description = $meta['description'];
			$keywords    = $meta['keywords'] ?? array();

			// Filter by search term if specified.
			if ( '' !== $search_term && ! $this->matches_search( $search_term, $name, $label, $description, $keywords ) ) {
				continue;
			}

			$extensions[] = array(
				'name'        => $name,
				'label'       => $label,
				'description' => $description,
				'blocks'      => $config['blocks'],
				'exclude'     => $config['exclude'] ?? array(),
				'attributes'  => $config['attributes'],
			);
		}

		// Sort by name.
		usort(
			$extensions,
			function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return array(
			'extensions' => $extensions,
			'total'      => count( $extensions ),
		);
	}
}
