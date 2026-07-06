<?php
/**
 * Add Tab Ability.
 *
 * Adds a tab block into an existing tabs container.
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
 * Add Tab ability class.
 */
class Add_Tab extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/add-tab';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Add Tab', 'designsetgo' ),
			'description'         => __( 'Adds a tab with title and content into an existing tabs container.', 'designsetgo' ),
			'category'            => 'blocks',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => Block_Inserter::get_default_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'tabs', 'tabbed', 'panel' ),
			'annotations'         => array(
				'idempotent' => false,
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
				'post_id'        => array(
					'type'        => 'integer',
					'description' => __( 'Post ID containing the tabs', 'designsetgo' ),
				),
				'tabs_client_id' => array(
					'type'        => 'string',
					'description' => __( 'Client ID of the parent tabs (optional - uses first tabs if not specified)', 'designsetgo' ),
				),
				'title'          => array(
					'type'        => 'string',
					'description' => __( 'Tab title', 'designsetgo' ),
				),
				'content'        => array(
					'type'        => 'string',
					'description' => __( 'Tab content (HTML supported)', 'designsetgo' ),
				),
				'inner_blocks'   => array(
					'type'        => 'array',
					'description' => __( 'Optional: Complex inner blocks instead of simple content', 'designsetgo' ),
				),
				'position'       => array(
					'type'        => 'integer',
					'description' => __( 'Position within the tabs (-1 = append, 0 = prepend)', 'designsetgo' ),
					'default'     => -1,
				),
			),
			'required'             => array( 'post_id', 'title' ),
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
		$post_id        = (int) ( $input['post_id'] ?? 0 );
		$tabs_client_id = $input['tabs_client_id'] ?? null;
		$title          = sanitize_text_field( $input['title'] ?? '' );
		$content        = wp_kses_post( $input['content'] ?? '' );
		$inner_blocks   = $input['inner_blocks'] ?? null;
		$position       = (int) ( $input['position'] ?? -1 );

		// Validate required parameters.
		if ( ! $post_id ) {
			return $this->error(
				'missing_post_id',
				__( 'Post ID is required.', 'designsetgo' )
			);
		}

		if ( empty( $title ) ) {
			return $this->error(
				'missing_title',
				__( 'Tab title is required.', 'designsetgo' )
			);
		}

		// Either content or inner_blocks required.
		if ( empty( $content ) && empty( $inner_blocks ) ) {
			return $this->error(
				'missing_content',
				__( 'Tab content or inner_blocks is required.', 'designsetgo' )
			);
		}

		// Validate post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->error(
				'invalid_post',
				__( 'Post not found.', 'designsetgo' )
			);
		}

		// Check permission for this specific post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->permission_error();
		}

		// Parse blocks.
		$blocks = parse_blocks( $post->post_content );

		// Find the tabs block.
		$tabs_found = false;
		$blocks     = $this->add_tab_to_tabs(
			$blocks,
			$tabs_client_id,
			$title,
			$content,
			$inner_blocks,
			$position,
			$tabs_found
		);

		if ( ! $tabs_found ) {
			return $this->error(
				'tabs_not_found',
				__( 'No tabs block found in the post.', 'designsetgo' )
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
	 * Add tab to a tabs block.
	 *
	 * @param array<int, array<string, mixed>> $blocks       Blocks array.
	 * @param string|null                      $client_id    Target tabs client ID.
	 * @param string                           $title        Tab title.
	 * @param string                           $content      Tab content.
	 * @param array|null                       $inner_blocks Optional inner blocks.
	 * @param int                              $position     Position to insert.
	 * @param bool                             $found        Reference to track if found.
	 * @return array<int, array<string, mixed>> Modified blocks.
	 */
	private function add_tab_to_tabs(
		array $blocks,
		?string $client_id,
		string $title,
		string $content,
		?array $inner_blocks,
		int $position,
		bool &$found
	): array {
		foreach ( $blocks as &$block ) {
			// Check if this is the target tabs.
			if ( 'designsetgo/tabs' === $block['blockName'] ) {
				// If client_id specified, check it matches.
				if ( $client_id && isset( $block['attrs']['clientId'] ) && $block['attrs']['clientId'] !== $client_id ) {
					continue;
				}

				// Build tab inner blocks.
				$tab_inner_blocks = array();

				if ( $inner_blocks ) {
					// Use provided inner blocks.
					$tab_inner_blocks = Block_Inserter::build_inner_blocks( $inner_blocks );
				} elseif ( $content ) {
					// Create paragraph from content.
					$tab_inner_blocks = array(
						array(
							'blockName'    => 'core/paragraph',
							'attrs'        => array(),
							'innerBlocks'  => array(),
							'innerHTML'    => '<p>' . $content . '</p>',
							'innerContent' => array( '<p>' . $content . '</p>' ),
						),
					);
				}

				// Create the tab.
				$new_tab = array(
					'blockName'    => 'designsetgo/tab',
					'attrs'        => array(
						'title' => $title,
					),
					'innerBlocks'  => $tab_inner_blocks,
					'innerHTML'    => '',
					'innerContent' => array( null ),
				);

				// Initialize innerBlocks if not set.
				if ( ! isset( $block['innerBlocks'] ) ) {
					$block['innerBlocks'] = array();
				}

				// Insert at position.
				if ( -1 === $position ) {
					$block['innerBlocks'][] = $new_tab;
				} elseif ( 0 === $position ) {
					array_unshift( $block['innerBlocks'], $new_tab );
				} else {
					array_splice( $block['innerBlocks'], $position, 0, array( $new_tab ) );
				}

				$found = true;
				return $blocks;
			}

			// Check inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->add_tab_to_tabs(
					$block['innerBlocks'],
					$client_id,
					$title,
					$content,
					$inner_blocks,
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
}
