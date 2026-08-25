<?php
/**
 * Add Accordion Item Ability.
 *
 * Adds an accordion item block into an existing accordion container.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.0.0
 */

namespace DesignSetGo\Abilities\Inserters;

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Abilities\Block_Inserter;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Accordion Item ability class.
 */
class Add_Accordion_Item extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/add-accordion-item';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Add Accordion Item', 'designsetgo' ),
			'description'         => __( 'Adds an accordion item with title and content into an existing accordion container.', 'designsetgo' ),
			'category'            => 'blocks',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => Block_Inserter::get_default_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'collapsible', 'expandable', 'faq', 'toggle' ),
			'annotations'         => array(
				'readonly'    => false,
				'destructive' => false,
				// Each call appends another block, so repeating it is not a no-op.
				'idempotent'  => false,
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
				'post_id'             => array(
					'type'        => 'integer',
					'description' => __( 'Post ID containing the accordion', 'designsetgo' ),
				),
				'accordion_client_id' => array(
					'type'        => 'string',
					'description' => __( 'Client ID of the parent accordion (optional - uses first accordion if not specified)', 'designsetgo' ),
				),
				'title'               => array(
					'type'        => 'string',
					'description' => __( 'Accordion item title/question', 'designsetgo' ),
				),
				'content'             => array(
					'type'        => 'string',
					'description' => __( 'Accordion item content/answer (HTML supported)', 'designsetgo' ),
				),
				'is_open'             => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the item should be initially open', 'designsetgo' ),
					'default'     => false,
				),
				'position'            => array(
					'type'        => 'integer',
					'description' => __( 'Position within the accordion (-1 = append, 0 = prepend)', 'designsetgo' ),
					'default'     => -1,
				),
			),
			'required'             => array( 'post_id', 'title', 'content' ),
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
		$post_id             = (int) ( $input['post_id'] ?? 0 );
		$accordion_client_id = $input['accordion_client_id'] ?? null;
		$title               = sanitize_text_field( $input['title'] ?? '' );
		$content             = wp_kses_post( $input['content'] ?? '' );
		$is_open             = (bool) ( $input['is_open'] ?? false );
		$position            = (int) ( $input['position'] ?? -1 );

		// Validate required parameters.
		if ( ! $post_id ) {
			return $this->error(
				'designsetgo_missing_post_id',
				__( 'Post ID is required.', 'designsetgo' )
			);
		}

		if ( empty( $title ) ) {
			return $this->error(
				'missing_title',
				__( 'Accordion item title is required.', 'designsetgo' )
			);
		}

		if ( empty( $content ) ) {
			return $this->error(
				'missing_content',
				__( 'Accordion item content is required.', 'designsetgo' )
			);
		}

		// Validate post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->error(
				'designsetgo_invalid_post',
				__( 'Post not found.', 'designsetgo' )
			);
		}

		// Check permission for this specific post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->permission_error();
		}

		// Parse blocks.
		$blocks = parse_blocks( $post->post_content );

		// Find the accordion block.
		$accordion_found = false;
		$blocks          = $this->add_item_to_accordion(
			$blocks,
			$accordion_client_id,
			$title,
			$content,
			$is_open,
			$position,
			$accordion_found
		);

		if ( ! $accordion_found ) {
			return $this->error(
				'accordion_not_found',
				__( 'No accordion block found in the post.', 'designsetgo' )
			);
		}

		// Serialize and update post.
		$new_content = serialize_blocks( $blocks );

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				// Slash so wp_update_post()'s internal wp_unslash() doesn't strip
				// JSON-escape backslashes from block-comment attributes.
				'post_content' => wp_slash( $new_content ),
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return array(
			'success'  => true,
			'post_id'  => $post_id,
			'block_id' => wp_unique_id( 'block-' ),
			'position' => $position,
		);
	}

	/**
	 * Add accordion item to an accordion block.
	 *
	 * @param array<int, array<string, mixed>> $blocks     Blocks array.
	 * @param string|null                      $client_id  Target accordion client ID.
	 * @param string                           $title      Item title.
	 * @param string                           $content    Item content.
	 * @param bool                             $is_open    Whether item is open.
	 * @param int                              $position   Position to insert.
	 * @param bool                             $found      Reference to track if found.
	 * @return array<int, array<string, mixed>> Modified blocks.
	 */
	private function add_item_to_accordion(
		array $blocks,
		?string $client_id,
		string $title,
		string $content,
		bool $is_open,
		int $position,
		bool &$found
	): array {
		foreach ( $blocks as &$block ) {
			// Check if this is the target accordion.
			if ( 'designsetgo/accordion' === $block['blockName'] ) {
				// If client_id specified, check it matches.
				if ( $client_id && isset( $block['attrs']['clientId'] ) && $block['attrs']['clientId'] !== $client_id ) {
					continue;
				}

				// Get parent accordion settings for icon position.
				$icon_position = $block['attrs']['iconPosition'] ?? 'right';
				$icon_style    = $block['attrs']['iconStyle'] ?? 'chevron';

				// Generate unique ID for the accordion item.
				$unique_id = 'accordion-item-' . wp_generate_password( 8, false, false );

				// Generate the accordion item HTML structure.
				$item_html = $this->generate_accordion_item_html(
					$title,
					$unique_id,
					$is_open,
					$icon_position,
					$icon_style
				);

				// Create the accordion item block.
				$new_item = array(
					'blockName'    => 'designsetgo/accordion-item',
					'attrs'        => array(
						'title'    => $title,
						'isOpen'   => $is_open,
						'uniqueId' => $unique_id,
					),
					'innerBlocks'  => array(
						array(
							'blockName'    => 'core/paragraph',
							'attrs'        => array(),
							'innerBlocks'  => array(),
							'innerHTML'    => '<p>' . $content . '</p>',
							'innerContent' => array( '<p>' . $content . '</p>' ),
						),
					),
					'innerHTML'    => $item_html['full'],
					'innerContent' => $item_html['content'],
				);

				// Initialize innerBlocks if not set.
				if ( ! isset( $block['innerBlocks'] ) ) {
					$block['innerBlocks'] = array();
				}

				// Insert at position.
				if ( -1 === $position ) {
					$block['innerBlocks'][] = $new_item;
				} elseif ( 0 === $position ) {
					array_unshift( $block['innerBlocks'], $new_item );
				} else {
					array_splice( $block['innerBlocks'], $position, 0, array( $new_item ) );
				}

				$found = true;
				return $blocks;
			}

			// Check inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->add_item_to_accordion(
					$block['innerBlocks'],
					$client_id,
					$title,
					$content,
					$is_open,
					$position,
					$found
				);

				if ( $found ) {
					return $blocks;
				}
			}
		}

		return $blocks;
	}

	/**
	 * Generate the HTML structure for an accordion item.
	 *
	 * @param string $title         Item title.
	 * @param string $unique_id     Unique ID for ARIA.
	 * @param bool   $is_open       Whether item is open.
	 * @param string $icon_position Icon position (left/right).
	 * @param string $icon_style    Icon style (chevron/plus-minus/caret/none).
	 * @return array{full: string, content: array} HTML structure.
	 */
	private function generate_accordion_item_html(
		string $title,
		string $unique_id,
		bool $is_open,
		string $icon_position,
		string $icon_style
	): array {
		$header_id = $unique_id . '-header';
		$panel_id  = $unique_id . '-panel';

		$item_class    = 'dsgo-accordion-item ' . ( $is_open ? 'dsgo-accordion-item--open' : 'dsgo-accordion-item--closed' );
		$trigger_class = 'dsgo-accordion-item__trigger dsgo-accordion-item__trigger--icon-' . $icon_position;

		// Generate icon SVG based on style.
		$icon_html = '';
		if ( 'none' !== $icon_style ) {
			$icon_svg  = $this->get_icon_svg( $icon_style, $is_open );
			$icon_html = '<span class="dsgo-accordion-item__icon" aria-hidden="true">' . $icon_svg . '</span>';
		}

		// Build the HTML parts.
		$opening = sprintf(
			'<div class="wp-block-designsetgo-accordion-item %s" data-initially-open="%s"><div class="dsgo-accordion-item__header"><button type="button" class="%s" aria-expanded="%s" aria-controls="%s" id="%s">',
			esc_attr( $item_class ),
			$is_open ? 'true' : 'false',
			esc_attr( $trigger_class ),
			$is_open ? 'true' : 'false',
			esc_attr( $panel_id ),
			esc_attr( $header_id )
		);

		// Add icon on left if needed.
		if ( 'left' === $icon_position ) {
			$opening .= $icon_html;
		}

		// Add title.
		$opening .= '<span class="dsgo-accordion-item__title">' . esc_html( $title ) . '</span>';

		// Add icon on right if needed.
		if ( 'right' === $icon_position ) {
			$opening .= $icon_html;
		}

		$opening .= sprintf(
			'</button></div><div class="dsgo-accordion-item__panel" role="region" aria-labelledby="%s" id="%s"%s><div class="dsgo-accordion-item__content">',
			esc_attr( $header_id ),
			esc_attr( $panel_id ),
			$is_open ? '' : ' hidden'
		);

		$closing = '</div></div></div>';

		return array(
			'full'    => $opening . $closing,
			'content' => array( $opening, null, $closing ),
		);
	}

	/**
	 * Get icon SVG markup.
	 *
	 * @param string $style   Icon style.
	 * @param bool   $is_open Whether item is open.
	 * @return string SVG markup.
	 */
	private function get_icon_svg( string $style, bool $is_open ): string {
		switch ( $style ) {
			case 'plus-minus':
				if ( $is_open ) {
					return '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4 8h8v1H4z"></path></svg>';
				}
				return '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 4v8M4 8h8" stroke="currentColor" stroke-width="1" fill="none"></path></svg>';

			case 'caret':
				return '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M6 7l2 2 2-2z"></path></svg>';

			case 'chevron':
			default:
				return '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4.427 6.427l3.396 3.396a.25.25 0 00.354 0l3.396-3.396A.25.25 0 0011.396 6H4.604a.25.25 0 00-.177.427z"></path></svg>';
		}
	}
}
