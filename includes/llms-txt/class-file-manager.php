<?php
/**
 * LLMS TXT File Manager
 *
 * Manages static markdown file generation and storage.
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
 * File_Manager Class
 *
 * Handles static markdown file operations.
 */
class File_Manager {
	/**
	 * Directory for static markdown files (relative to uploads).
	 */
	const MARKDOWN_DIR = 'designsetgo/llms';

	/**
	 * Post meta key for exclusion.
	 */
	const EXCLUDE_META_KEY = '_designsetgo_exclude_llms';

	/**
	 * Post meta key for storing the markdown filename.
	 */
	const FILENAME_META_KEY = '_designsetgo_llms_filename';

	/**
	 * Get the absolute filesystem path to the public site root.
	 *
	 * Physical llms.txt / llms-full.txt are served by the web server directly by
	 * URL (https://example.com/llms.txt), so they must live at the path that maps
	 * to home_url() — NOT necessarily ABSPATH. The two differ when WordPress core
	 * lives in its own subdirectory ("Giving WordPress its own directory"): there
	 * ABSPATH points at the core subdir (e.g. /var/www/wp/) while the served root
	 * is its parent (e.g. /var/www/). get_home_path() resolves the served root and
	 * is what WordPress core itself uses to locate the root .htaccess; it falls
	 * back to ABSPATH when the home and site URLs match (the common install).
	 *
	 * get_home_path() lives in wp-admin/includes/file.php, which is not loaded on
	 * the frontend, REST, or save_post contexts where the writers run, so load it
	 * on demand (mirroring fs_put_contents()/fs_delete()).
	 *
	 * @return string Trailing-slashed absolute path to the site root.
	 */
	public static function site_root_path(): string {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		/**
		 * Filter the absolute filesystem path to the public site root used for
		 * physical llms.txt / llms-full.txt files. Lets ops override resolution on
		 * setups where get_home_path() cannot derive the path (e.g. some WP-CLI or
		 * cron contexts on subdirectory installs).
		 *
		 * @since 2.2.0
		 *
		 * @param string $path Trailing-slashed absolute site-root path.
		 */
		return trailingslashit( apply_filters( 'designsetgo_llms_txt_site_root', get_home_path() ) );
	}

	/**
	 * Initialise WP_Filesystem on demand and return the global instance.
	 *
	 * Loads the WP_Filesystem API on demand so callers are safe to use outside
	 * of an `admin_init` context (e.g. from a REST route or a save_post hook).
	 *
	 * WP_Filesystem is the idiomatic WordPress file API and is always available
	 * in a standard WP environment. It abstracts the underlying transport so the
	 * same code works on hosts that restrict direct filesystem access and require
	 * FTP or SSH credentials — falling back to raw PHP functions (file_put_contents,
	 * unlink, etc.) would bypass that abstraction entirely and silently break those
	 * hosts. Returning null signals a genuine, unrecoverable error that callers
	 * must surface to the user rather than paper over with a direct-write fallback.
	 *
	 * file.php is always loaded here (not only when $wp_filesystem is unset) so
	 * that FS_CHMOD_FILE is guaranteed to be defined before fs_put_contents() uses
	 * it. If $wp_filesystem were already set by external code that loaded file.php
	 * via a different path, skipping the require_once would leave FS_CHMOD_FILE
	 * undefined and PHP 8 would throw a fatal Undefined constant error.
	 *
	 * @return \WP_Filesystem_Base|null Filesystem instance, or null if it could not be initialised.
	 */
	public static function filesystem(): ?\WP_Filesystem_Base {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! $wp_filesystem ) {
			if ( false === WP_Filesystem() ) {
				return null;
			}
		}

		return $wp_filesystem instanceof \WP_Filesystem_Base ? $wp_filesystem : null;
	}

	/**
	 * Write content to a file via WP_Filesystem.
	 *
	 * Returns false (without a direct-PHP fallback) when WP_Filesystem cannot be
	 * initialised. This is intentional: WP_Filesystem is the canonical WordPress
	 * file API and transparently handles direct, FTP, and SSH transports. A raw
	 * file_put_contents() fallback would silently bypass FTP/SSH support on
	 * managed or restricted hosts.
	 *
	 * @param string $path    Absolute path to the file to write.
	 * @param string $content File content.
	 * @return bool True on success, false if the filesystem is unavailable or the write fails.
	 */
	public static function fs_put_contents( string $path, string $content ): bool {
		$filesystem = self::filesystem();

		if ( ! $filesystem ) {
			return false;
		}

		return $filesystem->put_contents( $path, $content, FS_CHMOD_FILE );
	}

	/**
	 * Delete a file via WP_Filesystem.
	 *
	 * Returns false (without a direct-PHP fallback) when WP_Filesystem cannot be
	 * initialised — same rationale as fs_put_contents(): WP_Filesystem covers
	 * FTP/SSH hosts and falling back to unlink() would bypass that.
	 *
	 * @param string $path Absolute path to the file to delete.
	 * @return bool True on success or when the file did not exist, false if the filesystem is unavailable or the delete fails.
	 */
	public static function fs_delete( string $path ): bool {
		if ( ! file_exists( $path ) ) {
			return true;
		}

		$filesystem = self::filesystem();

		if ( ! $filesystem ) {
			return false;
		}

		return $filesystem->delete( $path );
	}

	/**
	 * Get the full path to the markdown files directory.
	 *
	 * @return string Directory path.
	 */
	public function get_directory(): string {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . self::MARKDOWN_DIR;
	}

	/**
	 * Get the filename (without extension) for a post.
	 *
	 * Uses the post slug for readable URLs. For hierarchical post types (pages),
	 * uses the full path (e.g., "blocks/layout-systems" for nested pages).
	 * Falls back to post ID if no slug is available (plain permalinks).
	 *
	 * @param \WP_Post|int $post Post object or ID.
	 * @return string Filename without extension.
	 */
	public function get_filename( $post ): string {
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}

		if ( ! $post ) {
			return '';
		}

		// Check if post has a valid slug.
		$has_slug = ! empty( $post->post_name ) && ! is_numeric( $post->post_name );

		// Fall back to post ID if no slug (plain permalinks or auto-draft).
		if ( ! $has_slug ) {
			return (string) $post->ID;
		}

		// For hierarchical post types, build the full path.
		if ( is_post_type_hierarchical( $post->post_type ) && $post->post_parent ) {
			$ancestors = get_post_ancestors( $post );
			$slugs     = array();

			// Ancestors are returned from immediate parent to root, so reverse.
			foreach ( array_reverse( $ancestors ) as $ancestor_id ) {
				$ancestor = get_post( $ancestor_id );
				if ( $ancestor && ! empty( $ancestor->post_name ) ) {
					$slugs[] = $ancestor->post_name;
				}
			}

			$slugs[] = $post->post_name;
			$slug    = implode( '/', $slugs );
		} else {
			$slug = $post->post_name;
		}

		// Handle empty slug (e.g., home page with no slug set).
		if ( empty( $slug ) ) {
			return (string) $post->ID;
		}

		// Sanitize for filesystem safety while preserving path separators.
		$parts = explode( '/', $slug );
		$parts = array_map( 'sanitize_file_name', $parts );
		$slug  = implode( '/', $parts );

		return $slug;
	}

	/**
	 * Get the URL for a markdown file.
	 *
	 * @param int $post_id Post ID.
	 * @return string File URL.
	 */
	public function get_url( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$filename   = $this->get_filename( $post );
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['baseurl'] ) . self::MARKDOWN_DIR . '/' . $filename . '.md';
	}

	/**
	 * Check if a static markdown file exists for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if file exists.
	 */
	public function file_exists( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$filename  = $this->get_filename( $post );
		$file_path = $this->get_directory() . '/' . $filename . '.md';

		return file_exists( $file_path );
	}

	/**
	 * Ensure the markdown directory exists.
	 *
	 * @param string $subdirectory Optional subdirectory path within the main directory.
	 * @return bool True if directory exists or was created.
	 */
	public function ensure_directory( string $subdirectory = '' ): bool {
		$root = $this->get_directory();
		$dir  = $root;

		if ( $subdirectory ) {
			$dir = trailingslashit( $root ) . $subdirectory;
		}

		if ( ! file_exists( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return false;
			}
			$this->maybe_write_htaccess( $root );
			return true;
		}

		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$this->maybe_write_htaccess( $root );

		$filesystem = self::filesystem();

		if ( ! $filesystem ) {
			return false;
		}

		return $filesystem->is_writable( $dir );
	}

	/**
	 * Drop a root-level .htaccess that tells Apache to serve .md as text/markdown.
	 *
	 * Without it, most servers fall back to application/octet-stream for .md and
	 * browsers download the file instead of rendering it inline. Idempotent: only
	 * writes when missing so any admin-authored overrides survive. Nginx cannot
	 * honour .htaccess — those users need a server-level text/markdown MIME entry.
	 *
	 * Uses `AddType` + `AddCharset` (separate directives) rather than a
	 * parameterised media-type string, which older Apache versions silently drop.
	 *
	 * @param string $root Absolute path to the markdown root directory.
	 */
	private function maybe_write_htaccess( string $root ): void {
		if ( ! is_dir( $root ) ) {
			return;
		}

		$path = trailingslashit( $root ) . '.htaccess';
		if ( file_exists( $path ) ) {
			return;
		}

		$contents = "# DesignSetGo llms.txt - serve Markdown inline\n"
			. "<IfModule mod_mime.c>\n"
			. "\tAddType text/markdown .md\n"
			. "\tAddCharset UTF-8 .md\n"
			. "</IfModule>\n";

		$result = self::fs_put_contents( $path, $contents );

		if ( ! $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only logging for non-fatal filesystem failure.
			error_log( 'DesignSetGo: Failed to write llms.txt .htaccess file to ' . $path );
		}
	}

	/**
	 * Generate a static markdown file for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function generate_file( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', 'designsetgo' ) );
		}

		if ( 'publish' !== $post->post_status ) {
			$this->delete_file( $post_id );
			return new \WP_Error( 'not_published', __( 'Post is not published.', 'designsetgo' ) );
		}

		// Reject password-protected or otherwise non-public posts.
		if ( post_password_required( $post ) || ! is_post_publicly_viewable( $post ) ) {
			$this->delete_file( $post_id );
			return new \WP_Error( 'not_public', __( 'Post is not publicly accessible.', 'designsetgo' ) );
		}

		// Check if post is excluded.
		$excluded = get_post_meta( $post_id, self::EXCLUDE_META_KEY, true );
		if ( $excluded ) {
			$this->delete_file( $post_id );
			return new \WP_Error( 'excluded', __( 'Post is excluded from llms.txt.', 'designsetgo' ) );
		}

		// Check if feature is enabled.
		$settings = \DesignSetGo\Admin\Settings::get_settings();
		if ( empty( $settings['llms_txt']['enable'] ) ) {
			return new \WP_Error( 'feature_disabled', __( 'llms.txt feature is not enabled.', 'designsetgo' ) );
		}

		// Check if post type is enabled.
		$enabled_post_types = $settings['llms_txt']['post_types'] ?? array( 'page', 'post' );
		if ( ! in_array( $post->post_type, $enabled_post_types, true ) ) {
			$this->delete_file( $post_id );
			return new \WP_Error( 'post_type_disabled', __( 'Post type is not enabled.', 'designsetgo' ) );
		}

		// Get the filename and check for changes.
		$filename     = $this->get_filename( $post );
		$old_filename = get_post_meta( $post_id, self::FILENAME_META_KEY, true );

		// Delete old file if filename changed (e.g., slug was updated).
		if ( $old_filename && $old_filename !== $filename ) {
			$old_file_path = $this->get_directory() . '/' . $old_filename . '.md';
			self::fs_delete( $old_file_path );
		}

		// Ensure directory exists (including subdirectories for hierarchical content).
		$subdirectory = dirname( $filename );
		if ( '.' !== $subdirectory ) {
			if ( ! $this->ensure_directory( $subdirectory ) ) {
				return new \WP_Error( 'directory_error', __( 'Could not create markdown directory.', 'designsetgo' ) );
			}
		} elseif ( ! $this->ensure_directory() ) {
			return new \WP_Error( 'directory_error', __( 'Could not create markdown directory.', 'designsetgo' ) );
		}

		// Convert to markdown.
		$converter = new \DesignSetGo\Markdown\Converter();
		$markdown  = $converter->convert( $post );

		// Write the file.
		$file_path = $this->get_directory() . '/' . $filename . '.md';

		if ( ! self::fs_put_contents( $file_path, $markdown ) ) {
			return new \WP_Error( 'write_error', __( 'Could not write markdown file.', 'designsetgo' ) );
		}

		// Store the filename for future reference (to handle slug changes).
		update_post_meta( $post_id, self::FILENAME_META_KEY, $filename );

		return true;
	}

	/**
	 * Delete a static markdown file for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if file was deleted or didn't exist.
	 */
	public function delete_file( int $post_id ): bool {
		$deleted = true;

		// Try to delete using stored filename first.
		$stored_filename = get_post_meta( $post_id, self::FILENAME_META_KEY, true );
		if ( $stored_filename ) {
			$file_path = $this->get_directory() . '/' . $stored_filename . '.md';
			$deleted   = self::fs_delete( $file_path );
			delete_post_meta( $post_id, self::FILENAME_META_KEY );
		}

		// Also try current filename (in case meta wasn't set).
		$post = get_post( $post_id );
		if ( $post ) {
			$filename  = $this->get_filename( $post );
			$file_path = $this->get_directory() . '/' . $filename . '.md';
			$deleted   = self::fs_delete( $file_path ) && $deleted;
		}

		// Clean up legacy ID-based files.
		$legacy_path = $this->get_directory() . '/' . $post_id . '.md';
		self::fs_delete( $legacy_path );

		return $deleted;
	}

	/**
	 * Generate markdown files for all enabled posts.
	 *
	 * @param Generator $generator Content generator instance.
	 * @return array Result with generated count and errors.
	 */
	public function generate_all_files( Generator $generator ): array {
		$settings = \DesignSetGo\Admin\Settings::get_settings();

		if ( empty( $settings['llms_txt']['enable'] ) ) {
			return array(
				'success'         => false,
				'generated_count' => 0,
				'errors'          => array( __( 'llms.txt feature is not enabled.', 'designsetgo' ) ),
			);
		}

		$post_types = $settings['llms_txt']['post_types'] ?? array( 'page', 'post' );
		$generated  = 0;
		$errors     = array();

		foreach ( $post_types as $post_type ) {
			$posts = $generator->get_public_content( $post_type );

			foreach ( $posts as $post ) {
				$result = $this->generate_file( $post->ID );

				if ( is_wp_error( $result ) ) {
					$errors[] = sprintf(
						/* translators: 1: Post title, 2: Error message */
						__( 'Failed to generate %1$s: %2$s', 'designsetgo' ),
						$post->post_title,
						$result->get_error_message()
					);
				} else {
					++$generated;
				}
			}
		}

		return array(
			'success'         => empty( $errors ),
			'generated_count' => $generated,
			'errors'          => $errors,
		);
	}
}
