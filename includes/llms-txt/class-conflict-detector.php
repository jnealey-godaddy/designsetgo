<?php
/**
 * LLMS TXT Conflict Detector
 *
 * Detects and reports conflicts with physical llms.txt files.
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
 * Conflict_Detector Class
 *
 * Handles detection and reporting of file conflicts.
 */
class Conflict_Detector {
	/**
	 * User meta key for dismissing the conflict notice.
	 */
	const DISMISS_META_KEY = 'designsetgo_llms_conflict_dismissed';

	/**
	 * Check if a physical llms.txt file exists that would conflict.
	 *
	 * When a physical file exists in the site root, the web server serves it
	 * directly before WordPress can handle the request via rewrite rules.
	 * Files written by DesignSetGo itself are not considered conflicts.
	 *
	 * @return bool True if a conflicting file exists.
	 */
	public function has_conflict(): bool {
		if ( ! file_exists( File_Manager::site_root_path() . 'llms.txt' ) ) {
			return false;
		}

		// If we wrote the file, it's not a conflict.
		if ( get_option( Controller::PHYSICAL_FILE_OPTION ) ) {
			return false;
		}

		// Physical file exists and we didn't write it — conflict.
		return true;
	}

	/**
	 * Get information about the conflicting file.
	 *
	 * @return array|null File info or null if no conflict.
	 */
	public function get_info(): ?array {
		$file_path = File_Manager::site_root_path() . 'llms.txt';

		if ( ! file_exists( $file_path ) ) {
			return null;
		}

		$parent_dir = dirname( $file_path );

		// Use WP_Filesystem for writability checks — the idiomatic WordPress way.
		// WP_Filesystem abstracts direct, FTP, and SSH transports, so a raw
		// is_writable() fallback would silently misreport writability on FTP/SSH
		// hosts where direct access is intentionally restricted. Treating the file
		// as non-writable when WP_Filesystem is unavailable is correct: it surfaces
		// the problem to the user (via the "rename manually" notice) rather than
		// allowing an operation that may not actually work.
		$filesystem = File_Manager::filesystem();

		$file_writable = $filesystem ? $filesystem->is_writable( $file_path ) : false;
		$dir_writable  = $filesystem ? $filesystem->is_writable( $parent_dir ) : false;

		return array(
			'path'       => $file_path,
			'size'       => filesize( $file_path ),
			'modified'   => filemtime( $file_path ),
			'writable'   => $file_writable,
			'renameable' => $file_writable && $dir_writable,
		);
	}

	/**
	 * Check if the current user has dismissed the conflict notice.
	 *
	 * @return bool True if dismissed.
	 */
	public function is_dismissed(): bool {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		return (bool) get_user_meta( $user_id, self::DISMISS_META_KEY, true );
	}

	/**
	 * Dismiss the conflict notice for the current user.
	 *
	 * @return bool True on success.
	 */
	public function dismiss(): bool {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		return (bool) update_user_meta( $user_id, self::DISMISS_META_KEY, 1 );
	}

	/**
	 * Reset the dismissal (show notice again).
	 *
	 * @return bool True on success.
	 */
	public function reset_dismissal(): bool {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		return delete_user_meta( $user_id, self::DISMISS_META_KEY );
	}

	/**
	 * Rename the conflicting file to allow plugin to take over.
	 *
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function rename_conflicting_file() {
		$file_path = File_Manager::site_root_path() . 'llms.txt';

		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error(
				'no_conflict',
				__( 'No conflicting file exists.', 'designsetgo' )
			);
		}

		$info = $this->get_info();
		if ( empty( $info['renameable'] ) ) {
			return new \WP_Error(
				'not_writable',
				__( 'The file or directory is not writable. Please rename or delete the file manually via FTP or your hosting file manager.', 'designsetgo' )
			);
		}

		// Generate a unique backup filename with safety limit.
		$site_root    = File_Manager::site_root_path();
		$backup_path  = $site_root . 'llms.txt.bak';
		$counter      = 1;
		$max_attempts = 100;
		while ( file_exists( $backup_path ) && $counter <= $max_attempts ) {
			$backup_path = $site_root . 'llms.txt.bak.' . $counter;
			++$counter;
		}

		if ( file_exists( $backup_path ) ) {
			return new \WP_Error(
				'backup_limit_reached',
				__( 'Unable to generate a backup filename after multiple attempts. Please rename or delete existing backup files manually.', 'designsetgo' )
			);
		}

		$filesystem = File_Manager::filesystem();

		if ( ! $filesystem ) {
			return new \WP_Error(
				'filesystem_unavailable',
				__( 'Could not access the filesystem. Please rename or delete the file manually via FTP or your hosting file manager.', 'designsetgo' )
			);
		}

		$result = $filesystem->move( $file_path, $backup_path );

		if ( ! $result ) {
			return new \WP_Error(
				'rename_failed',
				__( 'Failed to rename the file. Please try manually via FTP or your hosting file manager.', 'designsetgo' )
			);
		}

		// Reset dismissal since the conflict is resolved.
		$this->reset_dismissal();

		return true;
	}

	/**
	 * Display admin notice when a file conflict is detected.
	 */
	public function maybe_show_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = \DesignSetGo\Admin\Settings::get_settings();
		if ( empty( $settings['llms_txt']['enable'] ) ) {
			return;
		}

		if ( ! $this->has_conflict() ) {
			return;
		}

		// Check if user has dismissed the notice.
		if ( $this->is_dismissed() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'settings_page_designsetgo', 'plugins' ), true ) ) {
			return;
		}

		$conflict_info = $this->get_info();
		$dismiss_url   = wp_nonce_url(
			add_query_arg( 'dsgo_dismiss_llms_conflict', '1' ),
			'dsgo_dismiss_llms_conflict'
		);
		$resolve_url   = admin_url( 'options-general.php?page=designsetgo&tab=llms-txt' );
		?>
		<div class="notice notice-warning is-dismissible" data-dsgo-notice="llms-conflict">
			<p>
				<strong><?php esc_html_e( 'DesignSetGo: llms.txt File Conflict Detected', 'designsetgo' ); ?></strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: File path */
					esc_html__( 'A physical llms.txt file exists at %s. This file is being served by your web server instead of the dynamic version generated by DesignSetGo.', 'designsetgo' ),
					'<code>' . esc_html( $conflict_info['path'] ) . '</code>'
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $resolve_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Resolve Conflict', 'designsetgo' ); ?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button dsgo-notice-dismiss-btn">
					<?php esc_html_e( 'Dismiss', 'designsetgo' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle dismiss action from URL.
	 */
	public function handle_dismiss_action(): void {
		if ( ! isset( $_GET['dsgo_dismiss_llms_conflict'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is being verified in this very line.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dsgo_dismiss_llms_conflict' ) ) {
			return;
		}

		$this->dismiss();

		// Redirect to remove the query args.
		wp_safe_redirect( remove_query_arg( array( 'dsgo_dismiss_llms_conflict', '_wpnonce' ) ) );
		exit;
	}
}
