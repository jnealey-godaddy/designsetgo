<?php
/**
 * Patterns Loader Class
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

namespace DesignSetGo\Patterns;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Patterns Loader Class - Registers block patterns
 *
 * Performance optimizations:
 * - Patterns only register on admin/REST/CLI requests (never on front-end page loads).
 * - Full pattern data (including content) is cached in category-level transients to
 *   avoid repeated filesystem reads. Category-level splits keep each transient under
 *   safe size limits for MySQL max_allowed_packet and object cache item limits.
 * - Smart invalidation via file modification time hashing detects file edits without
 *   requiring a plugin version bump.
 * - Pattern context filtering via postTypes reduces the number of patterns sent
 *   to the editor in specific editing contexts.
 */
class Loader {

	/**
	 * Legacy transient name (kept for backward-compatible cleanup).
	 *
	 * @var string
	 */
	const CACHE_TRANSIENT = 'dsgo_pattern_files';

	/**
	 * Transient prefix for category-level pattern data caches.
	 *
	 * @var string
	 */
	const CACHE_TRANSIENT_PREFIX = 'dsgo_pattern_data_';

	/**
	 * Allowed pattern category directory names.
	 *
	 * @var array
	 */
	const ALLOWED_CATEGORIES = array(
		'homepage',
		'header',
		'footer',
		'hero',
		'features',
		'pricing',
		'testimonials',
		'team',
		'cta',
		'content',
		'faq',
		'modal',
		'gallery',
		'contact',
		'services',
		'headings',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Patterns are only used in the block editor. Skip registration entirely
		// on front-end page loads to avoid unnecessary filesystem I/O and memory usage.
		if ( ! self::is_editor_request() ) {
			return;
		}

		add_action( 'init', array( $this, 'register_pattern_categories' ) );
		add_action( 'init', array( $this, 'register_patterns' ) );
	}

	/**
	 * Check if the current request may need block patterns.
	 *
	 * Returns true for admin pages (classic editor, Site Editor), REST API
	 * requests (block editor fetches patterns via REST), and WP-CLI.
	 *
	 * @return bool
	 */
	private static function is_editor_request() {
		// Admin requests (editor page loads, Site Editor).
		if ( is_admin() ) {
			return true;
		}

		// REST API — constant may already be set by another plugin or mu-plugin.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// WP-CLI commands may need pattern data.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		// Detect REST API requests by URL path. The REST_REQUEST constant is not
		// defined until parse_request (which fires after this constructor), so
		// URL-based detection is the reliable early check for REST requests.
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$rest_prefix = rest_get_url_prefix();
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			// Pretty permalinks: /wp-json/...
			if ( false !== strpos( $request_uri, '/' . $rest_prefix . '/' ) ) {
				return true;
			}
			// Plain permalinks: ?rest_route=/...
			if ( false !== strpos( $request_uri, 'rest_route=' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Register pattern categories.
	 */
	public function register_pattern_categories() {
		$categories = array(
			'dsgo-homepage'     => __( 'DesignSetGo: Homepage', 'designsetgo' ),
			'dsgo-header'       => __( 'DesignSetGo: Header', 'designsetgo' ),
			'dsgo-footer'       => __( 'DesignSetGo: Footer', 'designsetgo' ),
			'dsgo-hero'         => __( 'DesignSetGo: Hero', 'designsetgo' ),
			'dsgo-features'     => __( 'DesignSetGo: Features', 'designsetgo' ),
			'dsgo-pricing'      => __( 'DesignSetGo: Pricing', 'designsetgo' ),
			'dsgo-testimonials' => __( 'DesignSetGo: Testimonials', 'designsetgo' ),
			'dsgo-team'         => __( 'DesignSetGo: Team', 'designsetgo' ),
			'dsgo-cta'          => __( 'DesignSetGo: Call to Action', 'designsetgo' ),
			'dsgo-content'      => __( 'DesignSetGo: Content', 'designsetgo' ),
			'dsgo-faq'          => __( 'DesignSetGo: FAQ', 'designsetgo' ),
			'dsgo-modal'        => __( 'DesignSetGo: Modals', 'designsetgo' ),
			'dsgo-gallery'      => __( 'DesignSetGo: Gallery', 'designsetgo' ),
			'dsgo-contact'      => __( 'DesignSetGo: Contact', 'designsetgo' ),
			'dsgo-services'     => __( 'DesignSetGo: Services', 'designsetgo' ),
			'dsgo-headings'     => __( 'DesignSetGo: Headings', 'designsetgo' ),
		);

		// Get pattern categories registry.
		$registry = \WP_Block_Pattern_Categories_Registry::get_instance();

		foreach ( $categories as $slug => $label ) {
			// Only register if not already registered.
			if ( ! $registry->is_registered( $slug ) ) {
				register_block_pattern_category(
					$slug,
					array( 'label' => $label )
				);
			}
		}
	}

	/**
	 * Get the post types mapping for pattern categories.
	 *
	 * Categories listed here will have their patterns restricted to the
	 * specified post types. Patterns in unlisted categories are available
	 * everywhere.
	 *
	 * @return array<string, string[]> Category slug => array of post type slugs.
	 */
	private static function get_category_post_types() {
		$map = array(
			'homepage' => array( 'page' ),
		);

		/**
		 * Filters the category-to-postTypes mapping for pattern context filtering.
		 *
		 * @since 1.6.0
		 *
		 * @param array $map Category directory name => array of post type slugs.
		 */
		return apply_filters( 'designsetgo_pattern_post_types_map', $map );
	}

	/**
	 * Compute a hash of file modification times for a set of files.
	 *
	 * Used to detect file edits without requiring a plugin version bump.
	 * The hash changes when any file in the category is added, removed, or modified.
	 *
	 * @param string[] $files Absolute file paths.
	 * @return string MD5 hash.
	 */
	private static function compute_files_hash( $files ) {
		$parts = array();
		foreach ( $files as $file ) {
			if ( ! file_exists( $file ) ) {
				continue;
			}
			$mtime = filemtime( $file );
			if ( false !== $mtime ) {
				$parts[] = basename( $file ) . ':' . $mtime;
			}
		}
		sort( $parts );
		return md5( implode( '|', $parts ) );
	}

	/**
	 * Validate a relative pattern path before use.
	 *
	 * Ensures cached paths cannot be exploited for directory traversal
	 * or arbitrary file inclusion if the transient data is compromised.
	 *
	 * @param string $relative_path Relative path from the cache.
	 * @return bool True if the path is safe to use.
	 */
	private static function is_valid_relative_path( $relative_path ) {
		// Must be a non-empty string.
		if ( ! is_string( $relative_path ) || '' === $relative_path ) {
			return false;
		}

		// Must end with .php.
		if ( '.php' !== substr( $relative_path, -4 ) ) {
			return false;
		}

		// Must not contain directory traversal sequences.
		if ( false !== strpos( $relative_path, '..' ) ) {
			return false;
		}

		// Must not start with a path separator (absolute path).
		if ( '/' === $relative_path[0] || '\\' === $relative_path[0] ) {
			return false;
		}

		return true;
	}

	/**
	 * Get cached pattern data for a single category.
	 *
	 * Returns an associative array of slug => pattern data arrays. On cache miss,
	 * scans the category directory, requires each file, validates, and caches.
	 *
	 * @param string $category Category directory name (must be in ALLOWED_CATEGORIES).
	 * @return array<string, array> Pattern slug => pattern data array.
	 */
	private function get_category_patterns( $category ) {
		$patterns_dir  = DESIGNSETGO_PATH . 'patterns/';
		$category_dir  = $patterns_dir . $category . '/';
		$transient_key = self::CACHE_TRANSIENT_PREFIX . $category;

		// Skip cache in debug mode so new/removed patterns are picked up immediately.
		$cache_enabled = ! defined( 'WP_DEBUG' ) || ! WP_DEBUG;

		/**
		 * Filters whether pattern caching is enabled.
		 *
		 * By default, caching is disabled when WP_DEBUG is true.
		 *
		 * @since 2.0.1
		 *
		 * @param bool $cache_enabled Whether caching is enabled.
		 */
		$cache_enabled = apply_filters( 'designsetgo_pattern_cache_enabled', $cache_enabled );

		if ( $cache_enabled ) {
			$cached = get_transient( $transient_key );

			if (
				is_array( $cached )
				&& isset( $cached['version'], $cached['hash'] )
				&& DESIGNSETGO_VERSION === $cached['version']
			) {
				$patterns_data = null;
				if ( isset( $cached['compressed'] ) && is_string( $cached['compressed'] ) ) {
					$raw = base64_decode( $cached['compressed'], true );
					// Validate zlib header: CMF byte 0x78 (deflate, 32 KB window) + header checksum ((CMF*256+FLG)%31===0).
					if ( false !== $raw && strlen( $raw ) >= 2 && 0x78 === ord( $raw[0] ) && 0 === ( ( ord( $raw[0] ) * 256 + ord( $raw[1] ) ) % 31 ) ) {
						$decompressed = @gzuncompress( $raw, 10 * 1024 * 1024 ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Forbidden, WordPress.PHP.NoSilencedErrors.Discouraged -- gzuncompress() emits E_WARNING on corrupted data; no exception-based alternative exists. 10 MiB cap guards against a zip-bomb in a tampered transient.
						if ( false !== $decompressed ) {
							$patterns_data = json_decode( $decompressed, true );
						}
					}
				}

				if ( is_array( $patterns_data ) ) {
					// Verify file hash still matches (catches manual file edits).
					if ( is_dir( $category_dir ) ) {
						$files = glob( $category_dir . '*.php' );
						if ( is_array( $files ) && self::compute_files_hash( $files ) === $cached['hash'] ) {
							return $patterns_data;
						}
					} elseif ( empty( $patterns_data ) ) {
						// Directory doesn't exist and cache is empty — still valid.
						return $patterns_data;
					}
				}
			}
		}

		// Cache miss — scan directory and require each file.
		$patterns = array();

		if ( ! is_dir( $category_dir ) ) {
			return $patterns;
		}

		$files = glob( $category_dir . '*.php' );

		// glob() returns false on error, empty array when no matches.
		if ( false === $files || empty( $files ) ) {
			if ( false === $files ) {
				wp_trigger_error( __METHOD__, sprintf( 'DesignSetGo: glob() failed for pattern directory: %s', $category_dir ), E_USER_NOTICE );
			}
			return $patterns;
		}

		$real_dir = realpath( $patterns_dir );
		if ( ! $real_dir ) {
			return $patterns;
		}
		$real_dir = rtrim( $real_dir, '/' ) . '/';

		foreach ( $files as $file ) {
			$relative_path = $category . '/' . basename( $file );

			// Validate relative path structure.
			if ( ! self::is_valid_relative_path( $relative_path ) ) {
				wp_trigger_error( __METHOD__, sprintf( 'DesignSetGo: Skipped invalid relative pattern path: %s', $relative_path ), E_USER_NOTICE );
				continue;
			}

			// Security: Verify resolved file is within expected directory.
			$real_file = realpath( $file );
			if ( ! $real_file || 0 !== strpos( $real_file, $real_dir ) ) {
				wp_trigger_error( __METHOD__, sprintf( 'DesignSetGo: Skipped pattern file outside allowed directory: %s', $file ), E_USER_NOTICE );
				continue;
			}

			// Load pattern file.
			$pattern = require $real_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- $real_file is validated via realpath() and directory-traversal check above

			// Validate pattern structure.
			if ( is_array( $pattern ) && isset( $pattern['content'] ) ) {
				$slug              = 'designsetgo/' . sanitize_key( $category ) . '/' . sanitize_key( basename( $file, '.php' ) );
				$patterns[ $slug ] = $pattern;
			}
		}

		/** This filter is documented in includes/patterns/class-loader.php */
		if ( apply_filters( 'designsetgo_pattern_cache_enabled', ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) ) {
			/** This filter is documented in includes/patterns/class-loader.php */
			$cache_duration = (int) apply_filters( 'designsetgo_pattern_cache_duration', DAY_IN_SECONDS );

			set_transient(
				$transient_key,
				array(
					'version'    => DESIGNSETGO_VERSION,
					'hash'       => self::compute_files_hash( $files ),
					'compressed' => base64_encode( gzcompress( wp_json_encode( $patterns ) ) ),
				),
				$cache_duration
			);
		}

		return $patterns;
	}

	/**
	 * Register all patterns.
	 */
	public function register_patterns() {
		$post_types_map = self::get_category_post_types();

		foreach ( self::ALLOWED_CATEGORIES as $category ) {
			// Validate category is in the allowed list (defense-in-depth).
			$patterns = $this->get_category_patterns( $category );

			foreach ( $patterns as $slug => $pattern ) {
				// Inject postTypes if the category has a restriction and the
				// pattern doesn't already declare its own.
				if ( isset( $post_types_map[ $category ] ) && ! isset( $pattern['postTypes'] ) ) {
					$pattern['postTypes'] = $post_types_map[ $category ];
				}

				// Replace placeholder tokens with local image URLs.
				if ( isset( $pattern['content'] ) ) {
					$pattern['content'] = designsetgo_replace_pattern_placeholders( $pattern['content'] );
				}

				register_block_pattern( $slug, $pattern );
			}
		}
	}

	/**
	 * Clear all pattern caches.
	 *
	 * Should be called on plugin activation to ensure a fresh scan.
	 */
	public static function clear_cache() {
		// Delete legacy transient.
		delete_transient( self::CACHE_TRANSIENT );

		// Delete all category-level transients.
		foreach ( self::ALLOWED_CATEGORIES as $category ) {
			delete_transient( self::CACHE_TRANSIENT_PREFIX . $category );
		}
	}
}
