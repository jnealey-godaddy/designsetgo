<?php
/**
 * Build Page Ability.
 *
 * Lets a remote agent submit a JSON block tree to build into a post. PHP
 * cannot run a block's save(), so this ability only validates the tree's
 * structure (Tree_Validator, Task 17) and parks it as a PENDING build
 * (Build_Store, Task 18) — it never touches post_content directly. An editor
 * plugin (Task 20) later opens the post at the returned finish_url, runs the
 * real save() in the browser, and posts a report back that
 * designsetgo/get-build-status polls for.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.6.0
 */

namespace DesignSetGo\Abilities\Agent_Build;

use DesignSetGo\Abilities\Abstract_Ability;
use WP_Error;
use WP_Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build_Page ability class.
 */
class Build_Page extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/build-page';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Build Page', 'designsetgo' ),
			'description'         => __( 'Submits a block tree — { version: 1, blocks: [...] }, the same shape as a WordPress block object (name/attributes/innerBlocks) — to build into a post. PHP validates the tree\'s structure but cannot run a block\'s save(); the real markup is produced when the target post is opened in the block editor at the returned finish_url. Poll designsetgo/get-build-status afterward for the outcome. A published post\'s build waits for a person to review it in the editor before anything changes.', 'designsetgo' ),
			'category'            => 'blocks',
			'input_schema'        => Build_Page_Schema::input(),
			'output_schema'       => Build_Page_Schema::output(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'page', 'agent', 'draft', 'assemble', 'tree' ),
			'annotations'         => array(
				'readonly'    => false,
				'destructive' => false,
				// A pending tree replaces whatever was previously pending, so
				// repeating a call is not a meaningful no-op.
				'idempotent'  => false,
			),
		);
	}

	/**
	 * Permission callback. Cannot know the concrete target ("post_id" vs
	 * "new") until input is parsed, so this only checks a baseline
	 * capability; execute() checks edit_post (existing) or the target post
	 * type's create_posts cap (new) once the target is known.
	 *
	 * @return bool
	 */
	public function check_permission_callback(): bool {
		return $this->check_permission( 'edit_posts' );
	}

	/**
	 * Execute the ability.
	 *
	 * Order: input shape (post_id XOR new, mode enum) -> tree structure
	 * (Tree_Validator) -> permission for the concrete target -> only then
	 * create the draft (if "new") and store the pending build. A failure at
	 * any earlier stage never creates a post.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( array $input ) {
		if ( isset( $input['new'] ) && ! is_array( $input['new'] ) ) {
			return Agent_Build_Ability_Helpers::problem_response( 'designsetgo_invalid_input', 'new', __( '"new" must be an object with "title" and/or "post_type".', 'designsetgo' ) );
		}

		$has_post_id = isset( $input['post_id'] );
		$has_new     = isset( $input['new'] );

		if ( $has_post_id === $has_new ) {
			return Agent_Build_Ability_Helpers::problem_response(
				'designsetgo_invalid_input',
				$has_post_id ? 'post_id' : 'post_id/new',
				$has_post_id
					? __( 'Provide exactly one of "post_id" or "new", not both.', 'designsetgo' )
					: __( 'Provide either "post_id" (an existing post) or "new" (to create one) to build into.', 'designsetgo' )
			);
		}

		$mode = isset( $input['mode'] ) ? (string) $input['mode'] : 'replace';
		if ( ! in_array( $mode, array( 'replace', 'append' ), true ) ) {
			return Agent_Build_Ability_Helpers::problem_response( 'designsetgo_invalid_input', 'mode', __( '"mode" must be "replace" or "append".', 'designsetgo' ) );
		}

		// Structural tree validation always runs, regardless of target -
		// Tree_Validator::validate() handles a missing/malformed tree on its
		// own, returning a designsetgo_invalid_tree problem for it.
		$problems = Tree_Validator::validate( $input['tree'] ?? null );
		if ( ! empty( $problems ) ) {
			return array(
				'success'  => false,
				'problems' => $problems,
			);
		}

		// Resolve and permission-check the concrete target. Nothing is
		// written to the database until this passes.
		if ( $has_post_id ) {
			$post_id = (int) $input['post_id'];
			$post    = get_post( $post_id );

			if ( ! $post ) {
				return Agent_Build_Ability_Helpers::problem_response( 'designsetgo_invalid_post', 'post_id', __( 'Post not found.', 'designsetgo' ) );
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $this->permission_error();
			}

			if ( ! $this->is_buildable_post_type( get_post_type_object( $post->post_type ) ) ) {
				return Agent_Build_Ability_Helpers::problem_response(
					'designsetgo_invalid_post',
					'post_id',
					sprintf(
						/* translators: %s: post type slug */
						__( 'Posts of type "%s" cannot be built into. The post must be a REST-visible post type with an editor (not a site-structure type like a template, template part, navigation, or global styles).', 'designsetgo' ),
						$post->post_type
					)
				);
			}
		} else {
			$new            = $input['new'];
			$post_type_slug = ! empty( $new['post_type'] ) ? sanitize_key( (string) $new['post_type'] ) : 'page';
			$post_type_obj  = get_post_type_object( $post_type_slug );

			if ( ! $this->is_buildable_post_type( $post_type_obj ) ) {
				return Agent_Build_Ability_Helpers::problem_response(
					'designsetgo_invalid_input',
					'new.post_type',
					sprintf(
						/* translators: %s: post type slug */
						__( '"%s" is not a post type this ability can build a page into. It must be a registered, REST-visible post type with an editor (not a site-structure type like a template, template part, navigation, or global styles).', 'designsetgo' ),
						$post_type_slug
					)
				);
			}

			$create_cap = ! empty( $post_type_obj->cap->create_posts ) ? $post_type_obj->cap->create_posts : 'edit_posts';
			if ( ! current_user_can( $create_cap ) ) {
				return $this->permission_error();
			}
		}

		// Only now write anything: create the draft (if "new") and store the
		// pending build.
		if ( ! $has_post_id ) {
			// wp_insert_post() unslashes its input; slash it first.
			$title       = ! empty( $new['title'] ) ? sanitize_text_field( (string) $new['title'] ) : '';
			$new_post_id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => $post_type_slug,
						'post_status'  => 'draft',
						'post_title'   => $title,
						'post_content' => '',
					)
				),
				true
			);

			if ( is_wp_error( $new_post_id ) || ! $new_post_id ) {
				return Agent_Build_Ability_Helpers::problem_response(
					'designsetgo_post_create_failed',
					'new',
					is_wp_error( $new_post_id ) ? $new_post_id->get_error_message() : __( 'The new post could not be created.', 'designsetgo' )
				);
			}

			$post_id = (int) $new_post_id;
		}

		// The tree is saved later by whoever opens the post in the editor,
		// who may hold unfiltered_html even though the submitter does not.
		$tree = $input['tree'];
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$tree = Tree_Kses::filter_tree( $tree );
		}

		Agent_Build_Ability_Helpers::get_store()->store( $post_id, $tree, $mode );

		$edit_link  = get_edit_post_link( $post_id, 'raw' );
		$finish_url = null !== $edit_link ? add_query_arg( 'dsgo-finish', '1', $edit_link ) : '';

		return $this->success(
			array(
				'status'     => 'pending',
				'post_id'    => $post_id,
				'finish_url' => $finish_url,
			)
		);
	}

	/**
	 * Whether a "new" target post type is one this ability may build a page
	 * into: registered, REST-visible, actually has an editor (rules out
	 * site-structure types such as wp_navigation/wp_template/wp_global_styles
	 * that are show_in_rest but not "a page an agent builds content into"),
	 * and not one of core's internal `wp_`-prefixed types - belt-and-braces
	 * alongside the editor-support check, since a future core type could gain
	 * editor support without becoming a sensible agent-build target.
	 *
	 * @param WP_Post_Type|null $post_type_obj Candidate post type object.
	 * @return bool
	 */
	private function is_buildable_post_type( ?WP_Post_Type $post_type_obj ): bool {
		if ( null === $post_type_obj ) {
			return false;
		}

		if ( empty( $post_type_obj->show_in_rest ) ) {
			return false;
		}

		if ( ! post_type_supports( $post_type_obj->name, 'editor' ) ) {
			return false;
		}

		return 0 !== strpos( $post_type_obj->name, 'wp_' );
	}
}
