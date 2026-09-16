<?php
/**
 * Get Build Status Ability.
 *
 * Lets a remote agent poll the outcome of a designsetgo/build-page
 * submission: whether the tree is still parked awaiting assembly in the
 * editor, plus the latest report Build_REST recorded once the editor opened
 * the post at its finish_url.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.6.0
 */

namespace DesignSetGo\Abilities\Agent_Build;

use DesignSetGo\Abilities\Abstract_Ability;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get_Build_Status ability class.
 */
class Get_Build_Status extends Abstract_Ability {

	/**
	 * Get ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'designsetgo/get-build-status';
	}

	/**
	 * Get ability configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return array(
			'label'               => __( 'Get Build Status', 'designsetgo' ),
			'description'         => __( 'Polls the outcome of a designsetgo/build-page submission. Returns whether a tree is still pending assembly, plus the latest report once the target post has been opened in the block editor at its finish_url and the tree assembled there.', 'designsetgo' ),
			'category'            => 'blocks',
			'input_schema'        => $this->get_input_schema(),
			'output_schema'       => $this->get_output_schema(),
			'permission_callback' => array( $this, 'check_permission_callback' ),
			'show_in_rest'        => true,
			'keywords'            => array( 'poll', 'status', 'report', 'agent' ),
			'annotations'         => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
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
				'post_id' => array(
					'type'        => 'integer',
					'description' => __( 'REQUIRED. Post ID a designsetgo/build-page call targeted.', 'designsetgo' ),
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
				'success' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the status could be read.', 'designsetgo' ),
				),
				'post_id' => array(
					'type'        => 'integer',
					'description' => __( 'The post the status was read for.', 'designsetgo' ),
				),
				'pending' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether a build is still parked awaiting assembly in the editor.', 'designsetgo' ),
				),
				'report'  => array(
					'type'        => 'object',
					'description' => __( 'Latest build report, or an empty object when no build has ever been submitted for this post.', 'designsetgo' ),
				),
			),
			'required'   => array( 'success' ),
		);
	}

	/**
	 * Permission callback. Cannot know the target post until input is
	 * parsed, so this only checks a baseline capability; execute() checks
	 * edit_post once the post is known.
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
		if ( empty( $input['post_id'] ) ) {
			return Agent_Build_Ability_Helpers::problem_response( 'designsetgo_missing_post_id', 'post_id', __( 'post_id is required.', 'designsetgo' ) );
		}

		$post_id = (int) $input['post_id'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return Agent_Build_Ability_Helpers::problem_response( 'designsetgo_invalid_post', 'post_id', __( 'Post not found.', 'designsetgo' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->permission_error();
		}

		$store = Agent_Build_Ability_Helpers::get_store();

		return $this->success(
			array(
				'post_id' => $post_id,
				'pending' => null !== $store->pending( $post_id ),
				'report'  => (object) $store->report( $post_id ),
			)
		);
	}
}
