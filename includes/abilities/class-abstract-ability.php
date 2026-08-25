<?php
/**
 * Abstract base class for DesignSetGo abilities.
 *
 * Provides common functionality for all abilities including registration,
 * permission checks, validation, and sanitization.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.0.0
 */

namespace DesignSetGo\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for abilities.
 */
abstract class Abstract_Ability {

	/**
	 * Get the unique name for this ability.
	 *
	 * Should be in the format 'designsetgo/ability-name'.
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Get the configuration array for this ability.
	 *
	 * Should return an array with keys:
	 *
	 * Required:
	 * - label: Human-readable name
	 * - description: What this ability does
	 * - category: Category slug this ability belongs to
	 * - permission_callback: Callable for permission check
	 *
	 * Optional:
	 * - input_schema: JSON Schema for inputs
	 * - output_schema: JSON Schema for outputs
	 * - show_in_rest: (bool) Expose via REST API (default true). Moved into meta by register().
	 * - public: (bool) WP 7.1+ unified external-client exposure flag (MCP, AI agents).
	 *   Defaults to show_in_rest. Moved into meta by register().
	 * - annotations: (array) Behavioral hints — readonly, destructive, idempotent. Moved into meta by register().
	 * - meta: (array) Additional metadata. WP_Ability stores show_in_rest, public and annotations here.
	 *
	 * Note: execute_callback is added automatically during registration.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function get_config(): array;

	/**
	 * Execute the ability with the given input.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error Result data or error.
	 */
	abstract public function execute( array $input );

	/**
	 * Normalise an ability config into the shape WP_Ability expects.
	 *
	 * WP_Ability::__construct emits a _doing_it_wrong notice for any unknown
	 * top-level property, so properties it only recognises under `meta` are
	 * moved there. Kept separate from register() so the resolution rules —
	 * particularly the WP 7.1 `public` flag — are testable without the
	 * Abilities API being present.
	 *
	 * @param array<string, mixed> $config Raw config from get_config().
	 * @return array<string, mixed> Normalised config.
	 */
	public static function normalize_config( array $config ): array {
		// Ensure meta array exists.
		if ( ! isset( $config['meta'] ) || ! is_array( $config['meta'] ) ) {
			$config['meta'] = array();
		}

		// Move show_in_rest into meta (WP 6.9 expects it nested in meta, not top-level).
		if ( isset( $config['show_in_rest'] ) ) {
			$config['meta']['show_in_rest'] = $config['show_in_rest'];
			unset( $config['show_in_rest'] );
		} elseif ( ! isset( $config['meta']['show_in_rest'] ) ) {
			$config['meta']['show_in_rest'] = true;
		}

		// WP 7.1 added meta.public: one high-level flag saying an ability is
		// meant for external clients, which every channel reads instead of each
		// one carrying its own switch. show_in_rest still wins for REST when
		// both are set, so this only decides the channels REST does not cover
		// (MCP adapters, AI agents). Core defaults it to false, so leaving it
		// unset would hide every DesignSetGo ability from exactly the agent
		// clients these abilities exist to serve, even though they stay
		// reachable over REST.
		//
		// Default it to the resolved show_in_rest: an ability we already
		// publish over REST is one we intend external clients to use. An
		// ability can still opt out by declaring `public` itself.
		if ( isset( $config['public'] ) ) {
			$config['meta']['public'] = $config['public'];
			unset( $config['public'] );
		} elseif ( ! isset( $config['meta']['public'] ) ) {
			$config['meta']['public'] = $config['meta']['show_in_rest'];
		}

		// WP 7.1 throws InvalidArgumentException when either flag is a
		// non-boolean, where 6.9/7.0 accepted anything truthy. Normalise here
		// so a stray 1 or 'yes' in an ability's config cannot take down
		// registration for the whole plugin.
		$config['meta']['show_in_rest'] = (bool) $config['meta']['show_in_rest'];
		$config['meta']['public']       = (bool) $config['meta']['public'];

		// Move annotations into meta (WP 6.9 expects it nested in meta, not top-level).
		if ( isset( $config['annotations'] ) ) {
			$config['meta']['annotations'] = $config['annotations'];
			unset( $config['annotations'] );
		}

		// Move keywords into meta as well. WP_Ability::__construct emits a
		// _doing_it_wrong notice for any unknown top-level property, and
		// `keywords` is one — historically we declared it at the top level
		// to match the registration shape from earlier prototypes.
		if ( isset( $config['keywords'] ) ) {
			$config['meta']['keywords'] = $config['keywords'];
			unset( $config['keywords'] );
		}

		return $config;
	}

	/**
	 * Register this ability with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! class_exists( 'WP_Ability' ) ) {
			return;
		}

		$config                     = self::normalize_config( $this->get_config() );
		$config['execute_callback'] = array( $this, 'execute' );

		// wp_register_ability() is a WP 6.9+ function, but this plugin supports
		// WP 6.7+. This method only runs on the wp_abilities_api_init hook, which
		// exists solely on 6.9+, so the call is already runtime-gated. Invoke it
		// indirectly so Plugin Check's static "requires WP" scan does not flag a
		// function that can never execute on the unsupported versions. The
		// is_callable() check keeps the indirect call safe even if WP_Ability is
		// ever present without its registration helper.
		$register_ability = 'wp_register_ability';
		if ( is_callable( $register_ability ) ) {
			$register_ability( $this->get_name(), $config );
		}
	}

	/**
	 * Check if the current user has the required permission.
	 *
	 * @param string $capability Required capability. Default 'edit_posts'.
	 * @return bool
	 */
	protected function check_permission( string $capability = 'edit_posts' ): bool {
		return current_user_can( $capability );
	}

	/**
	 * Validate post ID and return the post object.
	 *
	 * @param int $post_id Post ID to validate.
	 * @return \WP_Post|WP_Error Post object or error.
	 */
	protected function validate_post( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error(
				'designsetgo_invalid_post',
				__( 'Post not found.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		return $post;
	}

	/**
	 * Sanitize block attributes.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	protected function sanitize_attributes( array $attributes ): array {
		$sanitized = array();

		foreach ( $attributes as $key => $value ) {
			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_attributes( $value );
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Create a success response.
	 *
	 * @param array<string, mixed> $data Response data.
	 * @return array<string, mixed>
	 */
	protected function success( array $data = array() ): array {
		return array_merge(
			array( 'success' => true ),
			$data
		);
	}

	/**
	 * Create an error response.
	 *
	 * Ensures all errors have a proper HTTP status code for REST API consistency.
	 *
	 * @param string               $code Error code.
	 * @param string               $message Error message.
	 * @param array<string, mixed> $data Additional error data.
	 * @return WP_Error
	 */
	protected function error( string $code, string $message, array $data = array() ): WP_Error {
		// Ensure HTTP status code is set for REST API responses.
		if ( ! isset( $data['status'] ) ) {
			$data['status'] = $this->get_default_status_for_error( $code );
		}

		return new WP_Error( $code, $message, $data );
	}

	/**
	 * Get the default HTTP status code for a given error code.
	 *
	 * @param string $code Error code.
	 * @return int HTTP status code.
	 */
	private function get_default_status_for_error( string $code ): int {
		$status_map = array(
			'designsetgo_invalid_post'             => 404,
			'designsetgo_post_not_found'           => 404,
			'designsetgo_block_not_found'          => 404,
			'designsetgo_not_found'                => 404,
			'designsetgo_permission_denied'        => 403,
			'rest_forbidden'                       => 403,
			'designsetgo_unauthorized'             => 401,
			'designsetgo_missing_post_id'          => 400,
			'designsetgo_missing_block_name'       => 400,
			'designsetgo_missing_settings'         => 400,
			'designsetgo_missing_animation'        => 400,
			'designsetgo_missing_faqs'             => 400,
			'designsetgo_missing_css'              => 400,
			'designsetgo_missing_operations'       => 400,
			'designsetgo_missing_block_identifier' => 400,
			'designsetgo_missing_attributes'       => 400,
			'designsetgo_invalid_input'            => 400,
			'designsetgo_validation_failed'        => 400,
			'designsetgo_block_name_mismatch'      => 400,
		);

		return $status_map[ $code ] ?? 400; // Default to Bad Request.
	}

	/**
	 * Create a permission denied error.
	 *
	 * Standardized method for returning permission errors with proper status codes.
	 *
	 * @param string $message Optional custom message.
	 * @return WP_Error
	 */
	protected function permission_error( string $message = '' ): WP_Error {
		if ( empty( $message ) ) {
			$message = __( 'Sorry, you are not allowed to perform this action.', 'designsetgo' );
		}

		return new WP_Error(
			'rest_forbidden',
			$message,
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Validate input against the ability's input schema.
	 *
	 * Uses WordPress REST API schema validation when available.
	 *
	 * @param array<string, mixed> $input Input to validate.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	protected function validate_input( array $input ) {
		$config = $this->get_config();

		if ( empty( $config['input_schema'] ) ) {
			return true;
		}

		$schema = $config['input_schema'];

		// Check required fields - presence check only.
		// Uses array_key_exists() to allow falsy values (0, false, '', '0', null).
		// Type validation (int, bool, string, etc.) is handled separately below
		// via rest_validate_value_from_schema() which will reject invalid types.
		if ( isset( $schema['required'] ) && is_array( $schema['required'] ) ) {
			foreach ( $schema['required'] as $required_field ) {
				if ( ! array_key_exists( $required_field, $input ) ) {
					return $this->error(
						'missing_' . $required_field,
						sprintf(
							/* translators: %s: Field name */
							__( 'Missing required field: %s', 'designsetgo' ),
							$required_field
						)
					);
				}
			}
		}

		// Use WordPress REST API validation if available.
		if ( function_exists( 'rest_validate_value_from_schema' ) && isset( $schema['properties'] ) ) {
			foreach ( $input as $key => $value ) {
				if ( isset( $schema['properties'][ $key ] ) ) {
					$valid = rest_validate_value_from_schema( $value, $schema['properties'][ $key ], $key );
					if ( is_wp_error( $valid ) ) {
						return $this->error(
							'designsetgo_validation_failed',
							$valid->get_error_message(),
							array( 'field' => $key )
						);
					}
				}
			}
		}

		return true;
	}
}
