<?php
/**
 * Insert HTML Ability.
 *
 * Converts semantic HTML to WordPress blocks and inserts them into a post.
 * AI agents can write standard HTML instead of constructing block markup
 * directly, and this ability handles the conversion automatically.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.1.0
 */

namespace DesignSetGo\Abilities\Inserters;

use DesignSetGo\Abilities\Abstract_Ability;
use DesignSetGo\Abilities\Block_Inserter;
use DesignSetGo\HTML_Converter\Converter;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Insert HTML ability class.
 *
 * Accepts raw HTML, converts it to WordPress block markup using
 * DesignSetGo blocks where appropriate, and inserts into a post.
 */
class Insert_HTML extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/insert-html';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Insert HTML as Blocks', 'designsetgo' ),
			'description'         => __( 'Converts semantic HTML to WordPress blocks and inserts into a post. Write standard HTML (sections, headings, paragraphs, lists, images, forms, etc.) and it will be automatically converted to proper Gutenberg block markup using DesignSetGo blocks where appropriate.', 'designsetgo' ),
			'category'            => 'blocks',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => Block_Inserter::get_default_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'html', 'convert', 'insert', 'semantic' ),
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
				'post_id'          => array(
					'type'        => 'integer',
					'description' => __( 'Target post ID to insert blocks into.', 'designsetgo' ),
				),
				'html'             => array(
					'type'        => 'string',
					'description' => __( 'Semantic HTML to convert to blocks. Supports standard HTML elements (section, h1-h6, p, ul/ol, img, table, form, details, article, etc.) which are automatically mapped to appropriate WordPress/DesignSetGo blocks.', 'designsetgo' ),
				),
				'position'         => array(
					'type'        => 'integer',
					'description' => __( 'Insert position. -1 appends to end (default), 0 prepends, or specify an index.', 'designsetgo' ),
					'default'     => -1,
				),
				'prefer_dsgo'      => array(
					'type'        => 'boolean',
					'description' => __( 'Prefer DesignSetGo blocks over core equivalents. Default true.', 'designsetgo' ),
					'default'     => true,
				),
				'wrap_in_section'  => array(
					'type'        => 'boolean',
					'description' => __( 'Wrap all top-level blocks in a section container. Default false.', 'designsetgo' ),
					'default'     => false,
				),
			),
			'required'             => array( 'post_id', 'html' ),
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
		$html            = $input['html'] ?? '';
		$position        = (int) ( $input['position'] ?? -1 );
		$prefer_dsgo     = (bool) ( $input['prefer_dsgo'] ?? true );
		$wrap_in_section = (bool) ( $input['wrap_in_section'] ?? false );

		// Validate required parameters.
		if ( ! $post_id ) {
			return $this->error(
				'missing_post_id',
				__( 'Post ID is required.', 'designsetgo' )
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->error( 'invalid_post', __( 'Post not found.', 'designsetgo' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->permission_error();
		}

		if ( empty( trim( $html ) ) ) {
			return $this->error(
				'missing_html',
				__( 'HTML content is required.', 'designsetgo' )
			);
		}

		// Convert HTML to blocks.
		$converter = new Converter(
			array(
				'prefer_dsgo'     => $prefer_dsgo,
				'wrap_in_section' => $wrap_in_section,
			)
		);

		$block_markup = $converter->convert( $html );

		if ( empty( trim( $block_markup ) ) ) {
			return $this->error(
				'conversion_failed',
				__( 'Could not convert the provided HTML to blocks.', 'designsetgo' )
			);
		}

		// Parse existing post content.
		$existing_blocks = parse_blocks( $post->post_content );

		// Parse converted blocks.
		$new_blocks = parse_blocks( $block_markup );

		// Filter out empty/null blocks.
		$new_blocks = array_filter(
			$new_blocks,
			function ( $block ) {
				return ! empty( $block['blockName'] );
			}
		);

		if ( empty( $new_blocks ) ) {
			return $this->error(
				'conversion_failed',
				__( 'No valid blocks were generated from the HTML.', 'designsetgo' )
			);
		}

		// Insert at position.
		$new_blocks = array_values( $new_blocks );
		if ( -1 === $position ) {
			$existing_blocks = array_merge( $existing_blocks, $new_blocks );
		} elseif ( 0 === $position ) {
			$existing_blocks = array_merge( $new_blocks, $existing_blocks );
		} else {
			array_splice( $existing_blocks, $position, 0, $new_blocks );
		}

		// Serialize and update post.
		$content = serialize_blocks( $existing_blocks );
		$updated = wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return array(
			'success'     => true,
			'post_id'     => $post->ID,
			'block_count' => count( $new_blocks ),
			'position'    => $position,
			'note'        => sprintf(
				/* translators: %d: number of blocks inserted */
				__( '%d blocks inserted successfully from HTML. Open the post in the WordPress editor to validate and save.', 'designsetgo' ),
				count( $new_blocks )
			),
		);
	}
}
