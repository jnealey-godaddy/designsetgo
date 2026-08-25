<?php
/**
 * Add Timeline Item Ability.
 *
 * Adds a timeline item block into an existing timeline container.
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
 * Add Timeline Item ability class.
 */
class Add_Timeline_Item extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/add-timeline-item';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Add Timeline Item', 'designsetgo' ),
			'description'         => __( 'Adds a timeline item with date, title, and content into an existing timeline container.', 'designsetgo' ),
			'category'            => 'blocks',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => Block_Inserter::get_default_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'chronological', 'history', 'steps' ),
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
				'post_id'            => array(
					'type'        => 'integer',
					'description' => __( 'Post ID containing the timeline', 'designsetgo' ),
				),
				'timeline_client_id' => array(
					'type'        => 'string',
					'description' => __( 'Client ID of the parent timeline (optional - uses first timeline if not specified)', 'designsetgo' ),
				),
				'date'               => array(
					'type'        => 'string',
					'description' => __( 'Date or label for the timeline item (e.g., "2024", "January 2024", "Phase 1")', 'designsetgo' ),
				),
				'title'              => array(
					'type'        => 'string',
					'description' => __( 'Timeline item title/heading', 'designsetgo' ),
				),
				'content'            => array(
					'type'        => 'string',
					'description' => __( 'Timeline item description content (HTML supported)', 'designsetgo' ),
				),
				'is_active'          => array(
					'type'        => 'boolean',
					'description' => __( 'Whether this item should be highlighted as active/current', 'designsetgo' ),
					'default'     => false,
				),
				'link_url'           => array(
					'type'        => 'string',
					'description' => __( 'Optional link URL for this timeline item', 'designsetgo' ),
				),
				'link_target'        => array(
					'type'        => 'string',
					'description' => __( 'Link target (_self or _blank)', 'designsetgo' ),
					'enum'        => array( '_self', '_blank' ),
					'default'     => '_self',
				),
				'position'           => array(
					'type'        => 'integer',
					'description' => __( 'Position within the timeline (-1 = append, 0 = prepend)', 'designsetgo' ),
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
		$post_id            = (int) ( $input['post_id'] ?? 0 );
		$timeline_client_id = $input['timeline_client_id'] ?? null;
		$date               = isset( $input['date'] ) ? sanitize_text_field( wp_unslash( $input['date'] ) ) : '';
		$title              = isset( $input['title'] ) ? sanitize_text_field( wp_unslash( $input['title'] ) ) : '';
		$content            = isset( $input['content'] ) ? wp_kses_post( wp_unslash( $input['content'] ) ) : '';
		$is_active          = (bool) ( $input['is_active'] ?? false );
		$link_url           = isset( $input['link_url'] ) ? esc_url_raw( $input['link_url'] ) : '';
		$link_target_input  = $input['link_target'] ?? '_self';
		$link_target        = in_array( $link_target_input, array( '_self', '_blank' ), true ) ? $link_target_input : '_self';
		$position           = (int) ( $input['position'] ?? -1 );

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
				__( 'Timeline item title is required.', 'designsetgo' )
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

		// Find the timeline block.
		$timeline_found = false;
		$blocks         = $this->add_item_to_timeline(
			$blocks,
			$timeline_client_id,
			$date,
			$title,
			$content,
			$is_active,
			$link_url,
			$link_target,
			$position,
			$timeline_found
		);

		if ( ! $timeline_found ) {
			return $this->error(
				'timeline_not_found',
				__( 'No timeline block found in the post.', 'designsetgo' )
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
	 * Add timeline item to a timeline block.
	 *
	 * @param array<int, array<string, mixed>> $blocks      Blocks array.
	 * @param string|null                      $client_id   Target timeline client ID.
	 * @param string                           $date        Item date/label.
	 * @param string                           $title       Item title.
	 * @param string                           $content     Item content.
	 * @param bool                             $is_active   Whether item is active.
	 * @param string                           $link_url    Optional link URL.
	 * @param string                           $link_target Link target.
	 * @param int                              $position    Position to insert.
	 * @param bool                             $found       Reference to track if found.
	 * @return array<int, array<string, mixed>> Modified blocks.
	 */
	private function add_item_to_timeline(
		array $blocks,
		?string $client_id,
		string $date,
		string $title,
		string $content,
		bool $is_active,
		string $link_url,
		string $link_target,
		int $position,
		bool &$found
	): array {
		foreach ( $blocks as &$block ) {
			// Check if this is the target timeline.
			if ( 'designsetgo/timeline' === $block['blockName'] ) {
				// If client_id specified, check it matches.
				if ( $client_id && isset( $block['attrs']['clientId'] ) && $block['attrs']['clientId'] !== $client_id ) {
					continue;
				}

				// Build attributes array.
				$item_attrs = array(
					'date'     => $date,
					'title'    => $title,
					'isActive' => $is_active,
				);

				if ( ! empty( $link_url ) ) {
					$item_attrs['linkUrl']    = $link_url;
					$item_attrs['linkTarget'] = $link_target;
				}

				// Create the timeline item.
				$new_item = array(
					'blockName'    => 'designsetgo/timeline-item',
					'attrs'        => $item_attrs,
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array( null ),
				);

				// Add content paragraph if provided.
				if ( ! empty( $content ) ) {
					$new_item['innerBlocks'][] = array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>' . $content . '</p>',
						'innerContent' => array( '<p>' . $content . '</p>' ),
					);
				}

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
				$block['innerBlocks'] = $this->add_item_to_timeline(
					$block['innerBlocks'],
					$client_id,
					$date,
					$title,
					$content,
					$is_active,
					$link_url,
					$link_target,
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
