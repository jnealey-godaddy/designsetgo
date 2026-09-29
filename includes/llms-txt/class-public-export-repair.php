<?php
/**
 * One-time invalidation of public exports created before visibility enforcement.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\LLMS_Txt;

defined( 'ABSPATH' ) || exit;

/** Repair marker is independent of the plugin version (including ZIP updates). */
class Public_Export_Repair {
	/** Completed public-audience export migration. */
	const OPTION = 'designsetgo_llms_public_exports_repaired';

	/**
	 * Delete owned legacy files and caches before any public output reuses them.
	 *
	 * Run even with LLMS disabled: physical files bypass PHP and feature flags.
	 * On a filesystem failure, retry next request and refuse cached file reads.
	 * User-managed physical files without the ownership option are untouched.
	 *
	 * @param File_Manager $files File manager.
	 * @return bool Whether legacy public files have been removed.
	 */
	public static function run( File_Manager $files ): bool {
		if ( get_option( self::OPTION ) ) {
			return true;
		}
		delete_transient( Controller::CACHE_KEY );
		delete_transient( Controller::FULL_CACHE_KEY );
		self::clear_legacy_post_caches();

		$success = true;
		$directory = $files->get_directory();
		if ( file_exists( $directory ) ) {
			$filesystem = File_Manager::filesystem();
			$success = $filesystem && $filesystem->delete( $directory, true );
		}
		foreach ( array(
			'llms.txt' => Controller::PHYSICAL_FILE_OPTION,
			'llms-full.txt' => Controller::PHYSICAL_FULL_FILE_OPTION,
		) as $filename => $option ) {
			if ( ! get_option( $option ) ) {
				continue;
			}
			if ( File_Manager::fs_delete( File_Manager::site_root_path() . $filename ) ) {
				delete_option( $option );
			} else {
				$success = false;
			}
		}
		if ( $success ) {
			update_option( self::OPTION, 1, false );
		}
		return $success;
	}

	/** Delete DB-backed legacy entries through the transient API to clear memory caches too. */
	private static function clear_legacy_post_caches(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy-key enumeration; WordPress has no transient listing API. Deletion below uses its cache-aware API.
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_designsetgo_llms_md_' ) . '%'
			)
		);
		foreach ( $names as $name ) {
			delete_transient( substr( $name, strlen( '_transient_' ) ) );
		}
		// Unenumerable external object-cache entries are ignored by the new public key namespace.
	}

	/** Explain filesystem failures rather than silently marking the migration complete. */
	public static function notice(): void {
		if ( get_option( self::OPTION ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'DesignSetGo could not remove old public Markdown exports. Restore write access to the uploads/designsetgo/llms directory and plugin-owned llms.txt / llms-full.txt files, or remove those exports manually. They may contain content hidden by visibility rules.', 'designsetgo' );
		echo '</p></div>';
	}
}
