<?php
/**
 * LLMS TXT Controller
 *
 * Main orchestration class for llms.txt functionality.
 *
 * @package DesignSetGo
 * @since 1.4.0
 * @see https://llmstxt.org/
 */

namespace DesignSetGo\LLMS_Txt;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller Class
 *
 * Orchestrates llms.txt feature components.
 */
class Controller {
	/**
	 * Cache key for transient storage.
	 */
	const CACHE_KEY = 'designsetgo_llms_txt_cache';

	/**
	 * Cache key for llms-full.txt transient.
	 */
	const FULL_CACHE_KEY = 'designsetgo_llms_full_txt_cache';

	/**
	 * Cache expiration in seconds (1 day).
	 */
	const CACHE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Post meta key for exclusion.
	 */
	const EXCLUDE_META_KEY = '_designsetgo_exclude_llms';

	/**
	 * Option key tracking ownership of the physical llms.txt file.
	 */
	const PHYSICAL_FILE_OPTION = 'designsetgo_llms_txt_physical';

	/**
	 * Option key tracking ownership of the physical llms-full.txt file.
	 */
	const PHYSICAL_FULL_FILE_OPTION = 'designsetgo_llms_full_txt_physical';

	/**
	 * Option key tracking whether the per-post markdown .htaccess has been backfilled.
	 */
	const HTACCESS_BACKFILL_OPTION = 'designsetgo_llms_htaccess_backfilled';

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
	 * REST controller instance.
	 *
	 * @var REST_Controller
	 */
	private $rest_controller;

	/**
	 * Conflict detector instance.
	 *
	 * @var Conflict_Detector
	 */
	private $conflict_detector;

	/**
	 * Per-URL content-negotiation handler.
	 *
	 * @var Negotiation_Handler
	 */
	private $negotiation_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Initialize components.
		$this->file_manager        = new File_Manager();
		$this->generator           = new Generator( $this->file_manager );
		$this->conflict_detector   = new Conflict_Detector();
		$this->rest_controller     = new REST_Controller(
			$this->file_manager,
			$this->generator,
			$this->conflict_detector
		);
		$this->negotiation_handler = new Negotiation_Handler( $this->file_manager, $this->generator );
		$this->negotiation_handler->register();

		// Register hooks.
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_filter( 'redirect_canonical', array( $this, 'prevent_trailing_slash' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'handle_request' ) );
		add_action( 'save_post', array( $this, 'handle_post_save' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'handle_post_delete' ) );
		add_action( 'transition_post_status', array( $this, 'invalidate_cache' ) );
		add_action( 'update_option_designsetgo_settings', array( $this, 'handle_settings_update' ), 10, 2 );
		add_action( 'init', array( $this, 'register_post_meta' ) );
		add_action( 'init', array( $this, 'maybe_backfill_htaccess' ), 20 );
		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
		add_action( 'admin_notices', array( $this->conflict_detector, 'maybe_show_notice' ) );
		add_action( 'admin_init', array( $this->conflict_detector, 'handle_dismiss_action' ) );
		add_filter( 'robots_txt', array( $this, 'add_to_robots_txt' ), 10, 2 );
	}

	/**
	 * Rewrite rule pattern for llms.txt.
	 */
	const REWRITE_PATTERN = '^llms\\.txt/?$';

	/**
	 * Rewrite rule pattern for llms-full.txt.
	 */
	const FULL_REWRITE_PATTERN = '^llms-full\\.txt/?$';

	/**
	 * Add rewrite rules for llms.txt and llms-full.txt.
	 */
	public function add_rewrite_rule(): void {
		add_rewrite_rule( self::REWRITE_PATTERN, 'index.php?llms_txt=1', 'top' );
		add_rewrite_rule( self::FULL_REWRITE_PATTERN, 'index.php?llms_full_txt=1', 'top' );

		if ( get_transient( 'designsetgo_llms_txt_flush_rules' ) ) {
			delete_transient( 'designsetgo_llms_txt_flush_rules' );
			flush_rewrite_rules();
			return;
		}

		// Flush if our rules are missing from stored rules (covers updates without activation).
		$rules = get_option( 'rewrite_rules' );
		if ( is_array( $rules ) && ( ! isset( $rules[ self::REWRITE_PATTERN ] ) || ! isset( $rules[ self::FULL_REWRITE_PATTERN ] ) ) ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Schedule rewrite rules flush.
	 */
	public static function schedule_flush_rewrite_rules(): void {
		set_transient( 'designsetgo_llms_txt_flush_rules', true, HOUR_IN_SECONDS );
	}

	/**
	 * Add query var for llms.txt.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = 'llms_txt';
		$vars[] = 'llms_full_txt';
		return $vars;
	}

	/**
	 * Prevent WordPress from adding a trailing slash to llms.txt and llms-full.txt.
	 *
	 * @param string|false $redirect_url  The redirect URL, or false if no redirect.
	 * @param string       $requested_url The requested URL before redirect.
	 * @return string|false The redirect URL, or false to cancel the redirect.
	 */
	public function prevent_trailing_slash( $redirect_url, $requested_url = '' ) {
		$is_llms     = get_query_var( 'llms_txt' ) === '1';
		$is_llms_full = get_query_var( 'llms_full_txt' ) === '1';

		if ( ! $is_llms && ! $is_llms_full ) {
			return $redirect_url;
		}

		// Verify we're actually on a valid path to prevent abuse via query vars.
		if ( ! empty( $requested_url ) ) {
			$requested_path = wp_parse_url( $requested_url, PHP_URL_PATH );
			$normalized     = rtrim( $requested_path, '/' );
			$valid_paths    = array( '/llms.txt', '/llms-full.txt' );
			if ( ! in_array( $normalized, $valid_paths, true ) ) {
				return $redirect_url;
			}
		}

		return false;
	}

	/**
	 * Handle llms.txt and llms-full.txt requests.
	 */
	public function handle_request(): void {
		$is_llms      = (bool) get_query_var( 'llms_txt' );
		$is_llms_full = (bool) get_query_var( 'llms_full_txt' );

		if ( ! $is_llms && ! $is_llms_full ) {
			return;
		}

		$settings = \DesignSetGo\Admin\Settings::get_settings();
		if ( empty( $settings['llms_txt']['enable'] ) ) {
			status_header( 404 );
			exit;
		}

		if ( $is_llms_full ) {
			if ( empty( $settings['llms_txt']['generate_full_txt'] ) ) {
				status_header( 404 );
				exit;
			}
			$this->serve_llms_full_txt();
		} else {
			$this->serve_llms_txt();
		}
		exit;
	}

	/**
	 * Serve the llms.txt content with cache headers.
	 */
	private function serve_llms_txt(): void {
		$content = get_transient( self::CACHE_KEY );

		if ( false === $content ) {
			$content = $this->generator->generate_content();
			set_transient( self::CACHE_KEY, $content, self::CACHE_EXPIRATION );
		}

		$this->send_text_response( $content );
	}

	/**
	 * Serve the llms-full.txt content with cache headers.
	 */
	private function serve_llms_full_txt(): void {
		$content = get_transient( self::FULL_CACHE_KEY );

		if ( false === $content ) {
			$content = $this->generator->generate_full_content();
			set_transient( self::FULL_CACHE_KEY, $content, self::CACHE_EXPIRATION );
		}

		$this->send_text_response( $content );
	}

	/**
	 * Send a plain text response with appropriate cache headers.
	 *
	 * @param string $content The text content to send.
	 */
	private function send_text_response( string $content ): void {
		// Generate ETag for conditional request support.
		$etag = '"' . md5( $content ) . '"';

		// Handle conditional requests (304 Not Modified).
		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) {
			$client_etag = sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) );
			// Validate ETag format (quoted hex string or weak validator).
			if ( preg_match( '/^(W\/)?"[a-f0-9]+"$/i', $client_etag ) && $client_etag === $etag ) {
				header( 'ETag: ' . $etag );
				header( 'Cache-Control: public, max-age=86400' );
				status_header( 304 );
				exit;
			}
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Cache-Control: public, max-age=86400' );
		header( 'ETag: ' . $etag );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text file output.
	}

	/**
	 * Handle post save - invalidate cache and regenerate markdown file.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function handle_post_save( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->invalidate_cache( $post_id );
		$this->file_manager->generate_file( $post_id );
		$this->refresh_physical_file();
	}

	/**
	 * Handle post delete - remove markdown file and invalidate cache.
	 *
	 * @param int $post_id Post ID.
	 */
	public function handle_post_delete( int $post_id ): void {
		$this->invalidate_cache( $post_id );
		$this->file_manager->delete_file( $post_id );
		$this->refresh_physical_file();
	}

	/**
	 * Refresh physical llms.txt and llms-full.txt files if we maintain them.
	 */
	private function refresh_physical_file(): void {
		if ( get_option( self::PHYSICAL_FILE_OPTION ) ) {
			$this->write_physical_file();
		}

		$settings = \DesignSetGo\Admin\Settings::get_settings();
		if ( ! empty( $settings['llms_txt']['generate_full_txt'] ) ) {
			$this->write_physical_full_file();
		}
	}

	/**
	 * Invalidate the llms.txt cache.
	 *
	 * @param int|null $post_id Optional post ID for individual markdown cache.
	 */
	public function invalidate_cache( $post_id = null ): void {
		delete_transient( self::CACHE_KEY );
		delete_transient( self::FULL_CACHE_KEY );

		if ( $post_id && is_numeric( $post_id ) ) {
			delete_transient( 'designsetgo_llms_md_' . absint( $post_id ) );
		}
	}

	/**
	 * Handle settings update — invalidate cache and flush rewrite rules when needed.
	 *
	 * @param array $old_value Previous settings value.
	 * @param array $new_value Updated settings value.
	 */
	public function handle_settings_update( $old_value, $new_value ): void {
		// Clear the static settings cache so subsequent reads see the new values.
		\DesignSetGo\Admin\Settings::invalidate_cache();
		$this->invalidate_cache();

		$was_enabled = ! empty( $old_value['llms_txt']['enable'] );
		$is_enabled  = ! empty( $new_value['llms_txt']['enable'] );

		if ( $is_enabled !== $was_enabled ) {
			// Flush immediately so the rule is active before the next request.
			// Must register the rules first since this may run outside of init.
			add_rewrite_rule( self::REWRITE_PATTERN, 'index.php?llms_txt=1', 'top' );
			add_rewrite_rule( self::FULL_REWRITE_PATTERN, 'index.php?llms_full_txt=1', 'top' );
			flush_rewrite_rules();

			if ( $is_enabled ) {
				$this->file_manager->generate_all_files( $this->generator );
				$this->write_physical_file();
				if ( ! empty( $new_value['llms_txt']['generate_full_txt'] ) ) {
					$this->write_physical_full_file();
				}
			} else {
				$this->delete_physical_file();
				$this->delete_physical_full_file();
			}
		}

		// Handle llms-full.txt toggle independently.
		if ( $is_enabled ) {
			$was_full = ! empty( $old_value['llms_txt']['generate_full_txt'] );
			$is_full  = ! empty( $new_value['llms_txt']['generate_full_txt'] );

			if ( $is_full && ! $was_full ) {
				$this->write_physical_full_file();
			} elseif ( ! $is_full && $was_full ) {
				$this->delete_physical_full_file();
			}
		}
	}

	/**
	 * One-time backfill: drop an .htaccess into the markdown dir on legacy installs.
	 *
	 * New installs pick it up via ensure_directory() on the next post save; this
	 * covers sites that enabled llms.txt before this fix shipped and haven't
	 * re-saved settings or published a post since.
	 */
	public function maybe_backfill_htaccess(): void {
		if ( get_option( self::HTACCESS_BACKFILL_OPTION ) ) {
			return;
		}

		$settings = \DesignSetGo\Admin\Settings::get_settings();
		if ( empty( $settings['llms_txt']['enable'] ) ) {
			return;
		}

		$directory = $this->file_manager->get_directory();

		// If the legacy dir doesn't exist yet, there is nothing to backfill.
		// The normal post-save path will create both the dir and the .htaccess
		// on first publish, so mark done to avoid repeating the init-time check.
		if ( ! is_dir( $directory ) ) {
			update_option( self::HTACCESS_BACKFILL_OPTION, 1, true );
			return;
		}

		$this->file_manager->ensure_directory();

		// Only mark done once the .htaccess is actually on disk; leaving the
		// option unset means we retry next request if the write failed
		// (e.g. permissions).
		if ( file_exists( trailingslashit( $directory ) . '.htaccess' ) ) {
			update_option( self::HTACCESS_BACKFILL_OPTION, 1, true );
		}
	}

	/**
	 * Register post meta for exclusion toggle.
	 */
	public function register_post_meta(): void {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type ) {
				continue;
			}

			register_post_meta(
				$post_type,
				self::EXCLUDE_META_KEY,
				array(
					'type'              => 'boolean',
					'description'       => __( 'Exclude this content from llms.txt', 'designsetgo' ),
					'single'            => true,
					'default'           => false,
					'show_in_rest'      => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						if ( ! $post_id || ! is_numeric( $post_id ) ) {
							return false;
						}
						return current_user_can( 'edit_post', (int) $post_id );
					},
				)
			);
		}
	}

	/**
	 * Write a physical llms.txt file to the site root.
	 *
	 * This ensures /llms.txt works regardless of permalink structure.
	 * Apache serves physical files directly, bypassing WordPress rewrite rules.
	 *
	 * @return bool True on success.
	 */
	public function write_physical_file(): bool {
		$settings = \DesignSetGo\Admin\Settings::get_settings();
		if ( empty( $settings['llms_txt']['enable'] ) ) {
			return false;
		}

		$content = $this->generator->generate_content();
		if ( empty( $content ) ) {
			return false;
		}

		$file_path = ABSPATH . 'llms.txt';

		if ( File_Manager::fs_put_contents( $file_path, $content ) ) {
			update_option( self::PHYSICAL_FILE_OPTION, true, true );
			return true;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging for file write failures.
		error_log( 'DesignSetGo: Failed to write llms.txt file to ' . $file_path );
		return false;
	}

	/**
	 * Delete the physical llms.txt file if we own it.
	 */
	public function delete_physical_file(): void {
		if ( ! get_option( self::PHYSICAL_FILE_OPTION ) ) {
			return;
		}

		File_Manager::fs_delete( ABSPATH . 'llms.txt' );

		delete_option( self::PHYSICAL_FILE_OPTION );
	}

	/**
	 * Write a physical llms-full.txt file to the site root.
	 *
	 * @return bool True on success.
	 */
	public function write_physical_full_file(): bool {
		$settings = \DesignSetGo\Admin\Settings::get_settings();
		if ( empty( $settings['llms_txt']['enable'] ) || empty( $settings['llms_txt']['generate_full_txt'] ) ) {
			return false;
		}

		$file_path = ABSPATH . 'llms-full.txt';

		// Avoid overwriting a user-managed file the plugin didn't create.
		if ( file_exists( $file_path ) && ! get_option( self::PHYSICAL_FULL_FILE_OPTION ) ) {
			return false;
		}

		$content = $this->generator->generate_full_content();
		if ( empty( $content ) ) {
			return false;
		}

		if ( File_Manager::fs_put_contents( $file_path, $content ) ) {
			update_option( self::PHYSICAL_FULL_FILE_OPTION, true, true );
			return true;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging for file write failures.
		error_log( 'DesignSetGo: Failed to write llms-full.txt file to ' . $file_path );
		return false;
	}

	/**
	 * Delete the physical llms-full.txt file.
	 */
	public function delete_physical_full_file(): void {
		if ( ! get_option( self::PHYSICAL_FULL_FILE_OPTION ) ) {
			return;
		}

		File_Manager::fs_delete( ABSPATH . 'llms-full.txt' );

		delete_option( self::PHYSICAL_FULL_FILE_OPTION );
	}

	/**
	 * Add llms.txt reference to robots.txt output.
	 *
	 * @param string $output  The robots.txt content.
	 * @param bool   $public  Whether the site is public.
	 * @return string Modified robots.txt content.
	 */
	public function add_to_robots_txt( string $output, bool $public ): string {
		if ( ! $public ) {
			return $output;
		}

		$settings = \DesignSetGo\Admin\Settings::get_settings();
		if ( empty( $settings['llms_txt']['enable'] ) ) {
			return $output;
		}

		$output .= "\n# llms.txt - AI language model content index\n";
		$output .= '# ' . home_url( '/llms.txt' ) . "\n";

		if ( ! empty( $settings['llms_txt']['generate_full_txt'] ) ) {
			$output .= '# ' . home_url( '/llms-full.txt' ) . "\n";
		}

		return $output;
	}

	/**
	 * Get the file manager instance.
	 *
	 * @return File_Manager
	 */
	public function get_file_manager(): File_Manager {
		return $this->file_manager;
	}

	/**
	 * Get the generator instance.
	 *
	 * @return Generator
	 */
	public function get_generator(): Generator {
		return $this->generator;
	}
}
