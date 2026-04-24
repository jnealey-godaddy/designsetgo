<?php
/**
 * LLMS TXT REST Controller
 *
 * Handles REST API endpoints for llms.txt functionality.
 *
 * @package DesignSetGo
 * @since 1.4.0
 */

namespace DesignSetGo\LLMS_Txt;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST_Controller Class
 *
 * Registers and handles REST API endpoints.
 */
class REST_Controller {
	/**
	 * Cache expiration in seconds (1 day).
	 */
	const CACHE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Post meta key for exclusion.
	 */
	const EXCLUDE_META_KEY = '_designsetgo_exclude_llms';

	/**
	 * File manager instance.
	 *
	 * @var File_Manager
	 */
	private $file_manager;

	/**
	 * Generator instance.
	 *
	 * @var Generator
	 */
	private $generator;

	/**
	 * Conflict detector instance.
	 *
	 * @var Conflict_Detector
	 */
	private $conflict_detector;

	/**
	 * Constructor.
	 *
	 * @param File_Manager      $file_manager      File manager instance.
	 * @param Generator         $generator         Generator instance.
	 * @param Conflict_Detector $conflict_detector Conflict detector instance.
	 */
	public function __construct(
		File_Manager $file_manager,
		Generator $generator,
		Conflict_Detector $conflict_detector
	) {
		$this->file_manager      = $file_manager;
		$this->generator         = $generator;
		$this->conflict_detector = $conflict_detector;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'designsetgo/v1',
			'/llms-txt/post-types',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_post_types' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'designsetgo/v1',
			'/llms-txt/flush-cache',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'flush_cache' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'designsetgo/v1',
			'/llms-txt/generate-files',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'generate_files' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'designsetgo/v1',
			'/llms-txt/markdown/(?P<post_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_post_markdown' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array(
						'description'       => __( 'Post ID to convert to markdown.', 'designsetgo' ),
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
				),
			)
		);

		register_rest_route(
			'designsetgo/v1',
			'/llms-txt/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'designsetgo/v1',
			'/llms-txt/resolve-conflict',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'resolve_conflict' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'designsetgo/v1',
			'/llms-txt/dismiss-conflict',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'dismiss_conflict' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Get available post types endpoint.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_post_types(): \WP_REST_Response {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		$result = array();
		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			$result[] = array(
				'name'  => $post_type->name,
				'label' => $post_type->labels->name,
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Flush cache endpoint.
	 *
	 * @return \WP_REST_Response
	 */
	public function flush_cache(): \WP_REST_Response {
		delete_transient( Controller::CACHE_KEY );
		delete_transient( Controller::FULL_CACHE_KEY );

		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_designsetgo_llms_md_%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_designsetgo_llms_md_%'
			)
		);

		// Refresh the physical files if we own them and the feature is still enabled.
		$settings = \DesignSetGo\Admin\Settings::get_settings();
		if ( get_option( Controller::PHYSICAL_FILE_OPTION ) && ! empty( $settings['llms_txt']['enable'] ) ) {
			$content = $this->generator->generate_content();
			if ( $content ) {
				File_Manager::fs_put_contents( ABSPATH . 'llms.txt', $content );
			}
		}

		if ( get_option( Controller::PHYSICAL_FULL_FILE_OPTION ) && ! empty( $settings['llms_txt']['enable'] ) && ! empty( $settings['llms_txt']['generate_full_txt'] ) ) {
			$full_content = $this->generator->generate_full_content();
			if ( $full_content ) {
				File_Manager::fs_put_contents( ABSPATH . 'llms-full.txt', $full_content );
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'llms.txt cache has been cleared.', 'designsetgo' ),
			)
		);
	}

	/**
	 * Generate all markdown files endpoint.
	 *
	 * @return \WP_REST_Response
	 */
	public function generate_files(): \WP_REST_Response {
		$result = $this->file_manager->generate_all_files( $this->generator );

		delete_transient( Controller::CACHE_KEY );
		delete_transient( Controller::FULL_CACHE_KEY );

		// Refresh the physical files only when the feature is enabled.
		$settings = \DesignSetGo\Admin\Settings::get_settings();
		if ( ! empty( $settings['llms_txt']['enable'] ) ) {
			$content = $this->generator->generate_content();
			if ( $content ) {
				$llms_path = ABSPATH . 'llms.txt';
				// Only write if we own the file or it doesn't exist yet.
				if ( get_option( Controller::PHYSICAL_FILE_OPTION ) || ! file_exists( $llms_path ) ) {
					if ( File_Manager::fs_put_contents( $llms_path, $content ) ) {
						update_option( Controller::PHYSICAL_FILE_OPTION, true, true );
					}
				}
			}

			if ( ! empty( $settings['llms_txt']['generate_full_txt'] ) ) {
				$full_content = $this->generator->generate_full_content();
				if ( $full_content ) {
					$full_path = ABSPATH . 'llms-full.txt';
					// Only write if we own the file or it doesn't exist yet.
					if ( get_option( Controller::PHYSICAL_FULL_FILE_OPTION ) || ! file_exists( $full_path ) ) {
						if ( File_Manager::fs_put_contents( $full_path, $full_content ) ) {
							update_option( Controller::PHYSICAL_FULL_FILE_OPTION, true, true );
						}
					}
				}
			}
		}

		$message = sprintf(
			/* translators: %d: Number of files generated */
			_n(
				'Generated %d markdown file.',
				'Generated %d markdown files.',
				$result['generated_count'],
				'designsetgo'
			),
			$result['generated_count']
		);

		// Show actionable error when no files were generated.
		if ( 0 === $result['generated_count'] && ! empty( $result['errors'] ) ) {
			$message = implode( ' ', $result['errors'] );
		}

		return rest_ensure_response(
			array(
				'success'         => $result['success'],
				'generated_count' => $result['generated_count'],
				'errors'          => $result['errors'],
				'message'         => $message,
			)
		);
	}

	/**
	 * Get post markdown endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response or error.
	 */
	public function get_post_markdown( \WP_REST_Request $request ) {
		// Gate the endpoint before doing any work (rate counter included) so
		// disabled installations can't be used to confirm the existence of
		// specific post IDs via timing or rate-limit side channels. Use a
		// plugin-namespaced error code (not WP core's `rest_no_route`) so
		// middleware that inspects error codes for fallthrough behavior
		// isn't confused. The 404 status is what clients actually see.
		$settings = \DesignSetGo\Admin\Settings::get_settings();
		if ( empty( $settings['llms_txt']['enable'] ) ) {
			return new \WP_Error(
				'designsetgo_feature_disabled',
				__( 'No route was found matching the URL and request method.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		// Rate limit: 30 requests per minute per IP.
		$ip       = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
		$rate_key = 'dsgo_llms_rate_' . md5( $ip );
		$count    = (int) get_transient( $rate_key );
		if ( $count > 30 ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Too many requests. Please try again later.', 'designsetgo' ),
				array( 'status' => 429 )
			);
		}
		set_transient( $rate_key, $count + 1, MINUTE_IN_SECONDS );

		$post_id = absint( $request->get_param( 'post_id' ) );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error(
				'not_found',
				__( 'Post not found.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		if ( 'publish' !== $post->post_status ) {
			return new \WP_Error(
				'not_published',
				__( 'Post is not published.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		// Reject password-protected or otherwise non-public posts.
		if ( post_password_required( $post ) || ! is_post_publicly_viewable( $post ) ) {
			return new \WP_Error(
				'not_public',
				__( 'Post is not publicly accessible.', 'designsetgo' ),
				array( 'status' => 404 )
			);
		}

		$excluded = get_post_meta( $post_id, self::EXCLUDE_META_KEY, true );
		if ( $excluded ) {
			return new \WP_Error(
				'excluded',
				__( 'This post is excluded from llms.txt.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		$enabled_post_types = $settings['llms_txt']['post_types'] ?? array( 'page', 'post' );
		if ( ! in_array( $post->post_type, $enabled_post_types, true ) ) {
			return new \WP_Error(
				'post_type_disabled',
				__( 'This post type is not enabled for llms.txt.', 'designsetgo' ),
				array( 'status' => 403 )
			);
		}

		$cache_key = 'designsetgo_llms_md_' . $post_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$converter = new \DesignSetGo\Markdown\Converter();
		$markdown  = $converter->convert( $post );

		$result = array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'url'      => get_permalink( $post ),
			'updated'  => get_post_modified_time( 'c', true, $post ),
			'markdown' => $markdown,
		);

		set_transient( $cache_key, $result, self::CACHE_EXPIRATION );

		return rest_ensure_response( $result );
	}

	/**
	 * Get status endpoint.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_status(): \WP_REST_Response {
		$settings     = \DesignSetGo\Admin\Settings::get_settings();
		$is_enabled   = ! empty( $settings['llms_txt']['enable'] );
		$has_conflict = $this->conflict_detector->has_conflict();

		return rest_ensure_response(
			array(
				'enabled'            => $is_enabled,
				'url'                => home_url( '/llms.txt' ),
				'has_conflict'       => $has_conflict,
				'conflict_dismissed' => $has_conflict ? $this->conflict_detector->is_dismissed() : false,
				'conflict_info'      => $has_conflict ? $this->conflict_detector->get_info() : null,
			)
		);
	}

	/**
	 * Resolve conflict by renaming the physical file.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function resolve_conflict() {
		if ( ! $this->conflict_detector->has_conflict() ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'No conflict to resolve.', 'designsetgo' ),
				)
			);
		}

		$result = $this->conflict_detector->rename_conflicting_file();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'The existing llms.txt file has been renamed. DesignSetGo will now serve the dynamic version.', 'designsetgo' ),
			)
		);
	}

	/**
	 * Dismiss conflict notice.
	 *
	 * @return \WP_REST_Response
	 */
	public function dismiss_conflict(): \WP_REST_Response {
		$this->conflict_detector->dismiss();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Conflict notice dismissed.', 'designsetgo' ),
			)
		);
	}
}
