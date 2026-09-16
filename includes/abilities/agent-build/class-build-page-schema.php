<?php
/**
 * Input/output JSON Schema for the designsetgo/build-page ability.
 *
 * Split out of Build_Page purely to keep both files under the plugin's
 * 300-line cap - there is no independent lifecycle here, mirroring how
 * Report_Schema was split out of Build_REST.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.6.0
 */

namespace DesignSetGo\Abilities\Agent_Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build_Page_Schema class.
 *
 * A plain helper, not an Abstract_Ability - see Build_Store's docblock for
 * why this directory holds non-ability classes.
 */
class Build_Page_Schema {

	/**
	 * Input schema for designsetgo/build-page.
	 *
	 * @return array<string, mixed>
	 */
	public static function input(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => __( 'Existing post ID to build into. Mutually exclusive with "new".', 'designsetgo' ),
				),
				'new'     => array(
					'type'        => 'object',
					'description' => __( 'Create a new draft post to build into, instead of an existing "post_id". Mutually exclusive with "post_id".', 'designsetgo' ),
					'properties'  => array(
						'title'     => array(
							'type'        => 'string',
							'description' => __( 'Title for the new post.', 'designsetgo' ),
							'default'     => '',
						),
						'post_type' => array(
							'type'        => 'string',
							'description' => __( 'Post type for the new post. Must be a registered post type available via the REST API. Defaults to "page".', 'designsetgo' ),
							'default'     => 'page',
						),
					),
				),
				'tree'    => array(
					// Deliberately a multi-type schema, not just "object": WP core's
					// own WP_Ability::execute() -> validate_input() runs schema
					// validation BEFORE Build_Page::execute() ever sees the input, so
					// a single declared type of "object" would reject a malformed
					// tree (null, a string, ...) at the schema layer as a
					// bridge-flattened WP_Error - defeating the whole point of
					// reporting bad trees as { success: false, problems: [...] }
					// data. Every builtin JSON Schema type is listed so ANY shape of
					// "tree" clears schema validation and reaches
					// Tree_Validator::validate() in execute(), which is the one
					// place that actually judges it and always answers with data.
					'type'        => array( 'object', 'array', 'string', 'boolean', 'integer', 'number', 'null' ),
					'description' => __( 'REQUIRED. Block tree: { version: 1, blocks: [...] }. Each entry is { name, attributes?, innerBlocks? } — the same shape as a WordPress block object. 1 MB max when JSON-encoded.', 'designsetgo' ),
					'properties'  => array(
						'version' => array(
							'type'        => 'integer',
							'description' => __( 'Tree contract version. Must be 1.', 'designsetgo' ),
						),
						'blocks'  => self::blocks_schema(),
					),
				),
				'mode'    => array(
					'type'        => 'string',
					'enum'        => array( 'replace', 'append' ),
					'default'     => 'replace',
					'description' => __( 'How the tree combines with the post\'s existing content: "replace" overwrites it, "append" adds to the end.', 'designsetgo' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Schema for a list of tree nodes: `{ name, attributes, innerBlocks }`,
	 * described recursively to a bounded depth. Deliberately its own schema,
	 * not Block_Inserter's: the tree contract accepts no aliases, and any
	 * block may hold children here - Tree_Validator judges placement from
	 * block.json metadata, and the browser engine reports any child a
	 * block's save() drops.
	 *
	 * Every nested array declares `items`, or core emits an "Undefined array
	 * key type" warning per entry. Nothing is `required`: core validates
	 * input before execute() runs and the MCP bridge flattens that failure
	 * to "Ability execution failed.", so missing names are reported as data
	 * by Tree_Shape instead.
	 *
	 * @param int $depth Levels to describe explicitly.
	 * @return array<string, mixed>
	 */
	private static function blocks_schema( int $depth = 4 ): array {
		$inner_blocks = $depth > 1
			? self::blocks_schema( $depth - 1 )
			: array(
				'type'  => 'array',
				'items' => array( 'type' => 'object' ),
			);

		return array(
			'type'        => 'array',
			'description' => __( 'Blocks, in order. Each entry is { name, attributes?, innerBlocks? } and accepts no other keys.', 'designsetgo' ),
			'items'       => array(
				'type'       => 'object',
				'properties' => array(
					'name'        => array(
						'type'        => 'string',
						'description' => __( 'REQUIRED. Registered block name, e.g. "core/paragraph" or "designsetgo/section".', 'designsetgo' ),
					),
					'attributes'  => array(
						'type'        => 'object',
						'description' => __( 'Block attributes, as the block\'s own schema defines them. Text-bearing core blocks carry their text here (core/paragraph and core/heading use "content").', 'designsetgo' ),
					),
					'innerBlocks' => $inner_blocks,
				),
			),
		);
	}

	/**
	 * Output schema for designsetgo/build-page.
	 *
	 * @return array<string, mixed>
	 */
	public static function output(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'success'    => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the tree was accepted and parked as a pending build.', 'designsetgo' ),
				),
				'status'     => array(
					'type'        => 'string',
					'description' => __( 'Always "pending" on success — the tree still needs the editor to assemble it.', 'designsetgo' ),
				),
				'post_id'    => array(
					'type'        => 'integer',
					'description' => __( 'The post the tree was parked against.', 'designsetgo' ),
				),
				'finish_url' => array(
					'type'        => 'string',
					'description' => __( 'Editor URL to open so a human (or a browser-driving agent) can trigger assembly.', 'designsetgo' ),
				),
				'problems'   => array(
					'type'        => 'array',
					'description' => __( 'Present only when success is false: structural problems found in the submitted input.', 'designsetgo' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'code'    => array( 'type' => 'string' ),
							'path'    => array( 'type' => 'string' ),
							'message' => array( 'type' => 'string' ),
						),
					),
				),
			),
			'required'   => array( 'success' ),
		);
	}
}
