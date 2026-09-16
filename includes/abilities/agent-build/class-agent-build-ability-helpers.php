<?php
/**
 * Shared helpers for the agent-build abilities (Task 19).
 *
 * Build_Page and Get_Build_Status both need the same `{ success: false,
 * problems: [...] }` diagnostic shape and the same "get the shared
 * Build_Store instance" lookup. Factored out here rather than duplicated
 * verbatim in both classes, and kept out of Abstract_Ability - that class is
 * consumed by every ability in the plugin, and this shape/lookup is specific
 * to the agent-build pair.
 *
 * A plain static helper, not an Abstract_Ability - see Build_Store's
 * docblock for why this directory holds non-ability classes. Named with the
 * `class-` prefix (not `trait-`) deliberately: Abilities_Registry's
 * directory loader (`load_abilities_from_directory()`) only globs
 * `class-*.php` in this directory, so a `trait-*.php` file would never be
 * `require_once`'d automatically and would need a loader change. A plain
 * static class needs none - it is picked up by the existing glob exactly
 * like Tree_Validator, Build_Store, and Build_Page_Schema, and skipped by
 * the ability-instantiation loop because it does not extend Abstract_Ability.
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
 * Agent_Build_Ability_Helpers class.
 */
class Agent_Build_Ability_Helpers {

	/**
	 * Build a `{ success: false, problems: [ { code, path, message } ] }`
	 * diagnostic. Same shape Tree_Validator::validate() already returns, so
	 * every input problem either agent-build ability reports - whatever
	 * stage caught it - comes back in one consistent form the MCP bridge
	 * passes through intact.
	 *
	 * @param string $code    Problem code.
	 * @param string $path    Path to the offending input, e.g. "post_id" or "new.post_type".
	 * @param string $message Human-readable message.
	 * @return array{success: false, problems: array<int, array{code: string, path: string, message: string}>}
	 */
	public static function problem_response( string $code, string $path, string $message ): array {
		return array(
			'success'  => false,
			'problems' => array(
				array(
					'code'    => $code,
					'path'    => $path,
					'message' => $message,
				),
			),
		);
	}

	/**
	 * Get the shared Build_Store instance the plugin bootstrap created,
	 * rather than constructing a second one - Build_Store's constructor
	 * registers post-meta hooks, so a second instance would double-register
	 * them.
	 *
	 * @return Build_Store
	 */
	public static function get_store(): Build_Store {
		$plugin = \DesignSetGo\Plugin::instance();

		if ( $plugin->agent_build_store instanceof Build_Store ) {
			return $plugin->agent_build_store;
		}

		// Defensive fallback: Plugin::instance() always constructs this in
		// load_dependencies()/init(), but guard in case bootstrap order ever
		// changes so an agent-build ability never fatals.
		return new Build_Store();
	}
}
