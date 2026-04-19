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

		// Use WP_Filesystem for writability check, with fallback to native function.
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Fallback when WP_Filesystem fails.
		return $wp_filesystem ? $wp_filesystem->is_writable( $dir ) : is_writable( $dir );
	}

	/**
	 * Drop a root-level .htaccess that tells Apache to serve .md as text/markdown.
	 *
	 * Without it, most servers fall back to application/octet-stream for .md and
	 * browsers download the file instead of rendering it inline. Idempotent: only
	 * writes when missing so any admin-authored overrides survive. Nginx cannot
	 * honour .htaccess — those users need a server-level text/markdown MIME entry.
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
			. "AddType text/markdown .md\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Small MIME-hint file.
		@file_put_contents( $path, $contents ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort; failures are non-fatal.
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
			if ( file_exists( $old_file_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Direct file operation required.
				unlink( $old_file_path );
			}
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

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Direct file write required for performance.
		$result = file_put_contents( $file_path, $markdown );

		if ( false === $result ) {
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
			if ( file_exists( $file_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Direct file operation required.
				$deleted = unlink( $file_path );
			}
			delete_post_meta( $post_id, self::FILENAME_META_KEY );
		}

		// Also try current filename (in case meta wasn't set).
		$post = get_post( $post_id );
		if ( $post ) {
			$filename  = $this->get_filename( $post );
			$file_path = $this->get_directory() . '/' . $filename . '.md';
			if ( file_exists( $file_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Direct file operation required.
				$deleted = unlink( $file_path ) && $deleted;
			}
		}

		// Clean up legacy ID-based files.
		$legacy_path = $this->get_directory() . '/' . $post_id . '.md';
		if ( file_exists( $legacy_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Direct file operation required.
			unlink( $legacy_path );
		}

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
