<?php
/**
 * Configure Shape Divider Ability.
 *
 * Provides a user-friendly interface for configuring shape dividers on
 * designsetgo/section blocks. Maps simplified parameters to the section
 * block's 16 shape divider attributes.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.1.0
 */

namespace DesignSetGo\Abilities\Configurators;

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Abilities\Block_Configurator;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configure Shape Divider ability class.
 */
class Configure_Shape_Divider extends Abstract_Ability {

	/**
	 * All 29 valid shape divider names from shape-dividers.js.
	 *
	 * @var array<string>
	 */
	const VALID_SHAPES = array(
		'wave',
		'wave-double',
		'wave-layered',
		'wave-asymmetric',
		'tilt',
		'tilt-reverse',
		'curve',
		'curve-asymmetric',
		'triangle',
		'triangle-asymmetric',
		'arrow',
		'arrow-wide',
		'peaks',
		'peaks-soft',
		'zigzag',
		'book',
		'clouds',
		'drops',
		'split',
		'fan',
		'steps',
		'torn',
		'slime',
		'triangle-layered',
		'triangle-layered-extra',
		'curvy-triangle-layered',
		'symmetric-waves-layered',
		'side-triangle-layered',
		'side-triangle-layered-extra',
	);

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/configure-shape-divider';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Configure Shape Divider', 'designsetgo' ),
			'description'         => __( 'Adds or updates shape dividers on a section block. Supports top, bottom, or both positions with 29 shape types, custom colors, height, width, flip, and layering options.', 'designsetgo' ),
			'category'            => 'blocks',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => Block_Configurator::get_default_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'keywords'            => array( 'separator', 'wave', 'decoration', 'border' ),
			'annotations'         => array(
				'readonly'     => false,
				'destructive'  => false,
				'idempotent'   => true,
				'instructions' => 'Configures the shape divider on an existing section block. Re-sending the same shape and position is a no-op. Use list-blocks with detail "full" on designsetgo/section to see the accepted shape values.',
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
				'post_id'         => array(
					'type'        => 'integer',
					'description' => __( 'Target post ID', 'designsetgo' ),
				),
				'block_index'     => array(
					'type'        => 'integer',
					'description' => __( 'Document-order index of the section block (from get-post-blocks)', 'designsetgo' ),
				),
				'block_client_id' => array(
					'type'        => 'string',
					'description' => __( 'Specific block client ID (alternative to block_index)', 'designsetgo' ),
				),
				'position'        => array(
					'type'        => 'string',
					'description' => __( 'Where to add the shape divider', 'designsetgo' ),
					'enum'        => array( 'top', 'bottom', 'both' ),
					'default'     => 'bottom',
				),
				'shape'           => array(
					'type'        => 'string',
					'description' => __( 'Shape type. Available: wave, wave-double, wave-layered, wave-asymmetric, tilt, tilt-reverse, curve, curve-asymmetric, triangle, triangle-asymmetric, arrow, arrow-wide, peaks, peaks-soft, zigzag, book, clouds, drops, split, fan, steps, torn, slime, triangle-layered, triangle-layered-extra, curvy-triangle-layered, symmetric-waves-layered, side-triangle-layered, side-triangle-layered-extra', 'designsetgo' ),
					'enum'        => self::VALID_SHAPES,
				),
				'color'           => array(
					'type'        => 'string',
					'description' => __( 'Shape fill color (hex, rgb, or CSS variable)', 'designsetgo' ),
				),
				'backgroundColor' => array(
					'type'        => 'string',
					'description' => __( 'Background color behind the shape divider area', 'designsetgo' ),
				),
				'height'          => array(
					'type'        => 'integer',
					'description' => __( 'Shape height in pixels (0-300)', 'designsetgo' ),
					'minimum'     => 0,
					'maximum'     => 300,
				),
				'width'           => array(
					'type'        => 'integer',
					'description' => __( 'Shape width as percentage (50-200)', 'designsetgo' ),
					'minimum'     => 50,
					'maximum'     => 200,
				),
				'flipX'           => array(
					'type'        => 'boolean',
					'description' => __( 'Flip shape horizontally', 'designsetgo' ),
					'default'     => false,
				),
				'flipY'           => array(
					'type'        => 'boolean',
					'description' => __( 'Flip shape vertically', 'designsetgo' ),
					'default'     => false,
				),
				'inFront'         => array(
					'type'        => 'boolean',
					'description' => __( 'Render shape in front of content (above inner blocks)', 'designsetgo' ),
					'default'     => false,
				),
			),
			'required'             => array( 'post_id', 'shape' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	public function check_permission_callback(): bool {
		return $this->check_permission( 'edit_posts' );
	}

	/**
	 * Execute the ability.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( array $input ) {
		$post_id         = (int) ( $input['post_id'] ?? 0 );
		$block_index     = isset( $input['block_index'] ) ? (int) $input['block_index'] : null;
		$block_client_id = $input['block_client_id'] ?? null;
		$position        = $input['position'] ?? 'bottom';
		$shape           = $input['shape'] ?? '';
		$color           = $input['color'] ?? null;
		$bg_color        = $input['backgroundColor'] ?? null;
		$height          = isset( $input['height'] ) ? (int) $input['height'] : null;
		$width           = isset( $input['width'] ) ? (int) $input['width'] : null;
		$flip_x          = isset( $input['flipX'] ) ? (bool) $input['flipX'] : false;
		$flip_y          = isset( $input['flipY'] ) ? (bool) $input['flipY'] : false;
		$in_front        = isset( $input['inFront'] ) ? (bool) $input['inFront'] : false;

		// Validate required parameters.
		if ( ! $post_id ) {
			return $this->error(
				'designsetgo_missing_post_id',
				__( 'Post ID is required.', 'designsetgo' )
			);
		}

		if ( empty( $shape ) ) {
			return $this->error(
				'designsetgo_missing_settings',
				__( 'Shape type is required.', 'designsetgo' )
			);
		}

		// Validate shape is one of the known values.
		if ( ! in_array( $shape, self::VALID_SHAPES, true ) ) {
			return $this->error(
				'designsetgo_validation_failed',
				sprintf(
					/* translators: %s: shape name */
					__( 'Invalid shape "%s". Use list-blocks with detail "full" to see all available shapes.', 'designsetgo' ),
					$shape
				)
			);
		}

		// Validate color values.
		if ( null !== $color && ! $this->is_valid_color( $color ) ) {
			return $this->error(
				'designsetgo_validation_failed',
				__( 'Invalid color value. Must be a hex color (#rgb or #rrggbb), rgb/rgba function, hsl/hsla function, or CSS variable (var(--...)).', 'designsetgo' )
			);
		}
		if ( null !== $bg_color && ! $this->is_valid_color( $bg_color ) ) {
			return $this->error(
				'designsetgo_validation_failed',
				__( 'Invalid backgroundColor value. Must be a hex color (#rgb or #rrggbb), rgb/rgba function, hsl/hsla function, or CSS variable (var(--...)).', 'designsetgo' )
			);
		}

		// Validate height/width ranges and return errors instead of silently clamping.
		if ( null !== $height && ( $height < 0 || $height > 300 ) ) {
			return $this->error(
				'designsetgo_validation_failed',
				__( 'Height must be between 0 and 300 pixels.', 'designsetgo' )
			);
		}
		if ( null !== $width && ( $width < 50 || $width > 200 ) ) {
			return $this->error(
				'designsetgo_validation_failed',
				__( 'Width must be between 50 and 200 percent.', 'designsetgo' )
			);
		}

		// Validate targeting.
		if ( null === $block_index && empty( $block_client_id ) ) {
			return $this->error(
				'designsetgo_invalid_input',
				__( 'Either block_index or block_client_id is required.', 'designsetgo' )
			);
		}

		// Build attributes based on position.
		$attributes = $this->build_shape_attributes(
			$position,
			$shape,
			$color,
			$bg_color,
			$height,
			$width,
			$flip_x,
			$flip_y,
			$in_front
		);

		// Apply configuration using block_index or fallback to block_name + client_id.
		if ( null !== $block_index ) {
			return Block_Configurator::update_block_by_index(
				$post_id,
				$block_index,
				$attributes,
				'designsetgo/section'
			);
		}

		return Block_Configurator::configure_block(
			$post_id,
			'designsetgo/section',
			$attributes,
			$block_client_id,
			false
		);
	}

	/**
	 * Validate a color value.
	 *
	 * Accepts hex colors (#rgb, #rgba, #rrggbb, #rrggbbaa), rgb/rgba/hsl/hsla
	 * functions with strictly numeric arguments, CSS custom properties without
	 * fallback values, and common named CSS colors. Rejects any value that
	 * could contain CSS injection payloads.
	 *
	 * @param string $color Color value to validate.
	 * @return bool Whether the color is valid.
	 */
	private function is_valid_color( string $color ): bool {
		// Hex colors: #rgb, #rgba, #rrggbb, #rrggbbaa.
		if ( preg_match( '/^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color ) ) {
			return true;
		}

		// CSS functions: rgb(r, g, b) / rgba(r, g, b, a) with numeric/percentage values only.
		// Allows: rgb(255, 0, 0), rgba(255, 0, 0, 0.5), rgb(100%, 0%, 0%).
		if ( preg_match( '/^rgba?\(\s*\d{1,3}%?\s*,\s*\d{1,3}%?\s*,\s*\d{1,3}%?\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/', $color ) ) {
			return true;
		}

		// CSS functions: hsl(h, s%, l%) / hsla(h, s%, l%, a) with numeric values only.
		if ( preg_match( '/^hsla?\(\s*\d{1,3}\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/', $color ) ) {
			return true;
		}

		// CSS variables: var(--custom-property) only, no fallback values.
		// Fallback values (var(--x, fallback)) are rejected because the fallback
		// could contain arbitrary content including injection payloads.
		if ( preg_match( '/^var\(\s*--[\w-]+\s*\)$/', $color ) ) {
			return true;
		}

		// Named CSS colors (basic subset commonly used).
		$named_colors = array(
			'transparent',
			'currentcolor',
			'inherit',
			'black',
			'white',
			'red',
			'green',
			'blue',
			'yellow',
			'orange',
			'purple',
			'gray',
			'grey',
			'navy',
			'teal',
			'aqua',
			'maroon',
			'olive',
			'lime',
			'fuchsia',
			'silver',
		);
		if ( in_array( strtolower( $color ), $named_colors, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Build the shape divider block attributes from user-friendly params.
	 *
	 * Maps position + shape params to the actual attribute names
	 * (shapeDividerTop*, shapeDividerBottom*).
	 *
	 * @param string      $position Where to apply (top, bottom, both).
	 * @param string      $shape    Shape name.
	 * @param string|null $color    Shape fill color.
	 * @param string|null $bg_color Background color.
	 * @param int|null    $height   Shape height in pixels.
	 * @param int|null    $width    Shape width percentage.
	 * @param bool        $flip_x   Flip horizontally.
	 * @param bool        $flip_y   Flip vertically.
	 * @param bool        $in_front Render in front of content.
	 * @return array<string, mixed> Block attributes.
	 */
	private function build_shape_attributes(
		string $position,
		string $shape,
		?string $color,
		?string $bg_color,
		?int $height,
		?int $width,
		bool $flip_x,
		bool $flip_y,
		bool $in_front
	): array {
		$attributes = array();
		$positions  = array();

		if ( 'top' === $position || 'both' === $position ) {
			$positions[] = 'Top';
		}
		if ( 'bottom' === $position || 'both' === $position ) {
			$positions[] = 'Bottom';
		}

		foreach ( $positions as $pos ) {
			// Shape name is already validated against VALID_SHAPES enum.
			$attributes[ 'shapeDivider' . $pos ] = $shape;

			if ( null !== $color ) {
				// Color is already validated by is_valid_color() which only allows
				// hex, rgb/hsl functions with numeric args, CSS vars without fallbacks,
				// or named colors - none of which can contain HTML tags or injection.
				$attributes[ 'shapeDivider' . $pos . 'Color' ] = $color;
			}
			if ( null !== $bg_color ) {
				$attributes[ 'shapeDivider' . $pos . 'BackgroundColor' ] = $bg_color;
			}
			if ( null !== $height ) {
				// Already range-validated in execute().
				$attributes[ 'shapeDivider' . $pos . 'Height' ] = $height;
			}
			if ( null !== $width ) {
				// Already range-validated in execute().
				$attributes[ 'shapeDivider' . $pos . 'Width' ] = $width;
			}

			// Deliberately no `shapeDivider{Pos}Spacing` (content clearance) here.
			// Clearance is derived live from the divider's rendered height by the
			// stylesheet fallback (see `_shape-divider.scss` +
			// `getRenderedShapeHeight()` in save.js/edit.js), so an omitted spacing
			// still clears content that matches the divider height. Writing an
			// explicit px snapshot instead would go stale on a partial follow-up
			// call: `Height` uses patch semantics (only written when passed), but a
			// snapshot spacing would be re-written every call and could then reserve
			// the wrong amount against an unchanged height. Leaving it unset keeps
			// this path consistent with the editor's default-clearance behavior.

			$attributes[ 'shapeDivider' . $pos . 'FlipX' ] = $flip_x;
			$attributes[ 'shapeDivider' . $pos . 'FlipY' ] = $flip_y;
			$attributes[ 'shapeDivider' . $pos . 'Front' ] = $in_front;
		}

		return $attributes;
	}
}
