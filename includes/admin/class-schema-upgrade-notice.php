<?php
/**
 * Admin notice for a failed database schema upgrade.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Admin;

use DesignSetGo\Core\SchemaUpgrader;

defined( 'ABSPATH' ) || exit;

/**
 * Tells administrators the filter index table could not be created, and lets
 * them retry or dismiss the notice.
 *
 * A dismissal hides the notice everywhere except DesignSetGo's own pages.
 */
class SchemaUpgradeNotice {

	/**
	 * Action name for the admin-post request that clears the failure and retries.
	 */
	const RETRY_ACTION = 'designsetgo_retry_db_upgrade';

	/**
	 * Action name for the admin-ajax request that dismisses the notice.
	 */
	const DISMISS_ACTION = 'designsetgo_dismiss_db_upgrade_notice';

	/**
	 * User meta holding the signature of the failure this user dismissed.
	 */
	const DISMISS_META = 'designsetgo_db_upgrade_notice_dismissed';

	/**
	 * Script handle for the inline dismiss handler.
	 */
	const DISMISS_SCRIPT = 'designsetgo-db-upgrade-notice';

	/**
	 * Handles the admin-post request behind the notice's "Retry now" link.
	 *
	 * @return void
	 */
	public static function handle_retry(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'designsetgo' ), 403 );
		}
		check_admin_referer( self::RETRY_ACTION );

		SchemaUpgrader::retry();

		$referer = wp_get_referer();
		wp_safe_redirect( $referer ? $referer : admin_url() );
		exit;
	}

	/**
	 * Hides the notice for the current user, outside DesignSetGo's own pages.
	 *
	 * The dismissal is stored against the failure's signature, so a different
	 * error or a later plugin version that also fails shows the notice again.
	 *
	 * @return bool True when a dismissal was stored.
	 */
	public static function dismiss_for_current_user(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$failure = SchemaUpgrader::get_failure();
		if ( null === $failure ) {
			return false;
		}
		update_user_meta( get_current_user_id(), self::DISMISS_META, self::failure_signature( $failure ) );
		return true;
	}

	/**
	 * Handles the admin-ajax request sent when the notice's dismiss button is clicked.
	 *
	 * @return void
	 */
	public static function handle_dismiss(): void {
		check_ajax_referer( self::DISMISS_ACTION );

		if ( ! self::dismiss_for_current_user() ) {
			wp_send_json_error( null, 403 );
		}
		wp_send_json_success();
	}

	/**
	 * Tells administrators the table could not be created, and why.
	 *
	 * Dismissible on every admin screen except DesignSetGo's own pages, where
	 * it always shows: that is where someone goes looking when filters break.
	 *
	 * @return void
	 */
	public static function admin_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$failure = SchemaUpgrader::get_failure();
		if ( null === $failure ) {
			return;
		}

		$on_designsetgo_page = self::is_designsetgo_screen();
		$dismissed           = get_user_meta( get_current_user_id(), self::DISMISS_META, true ) === self::failure_signature( $failure );
		if ( $dismissed && ! $on_designsetgo_page ) {
			return;
		}

		$dismissible = ! $on_designsetgo_page;
		if ( $dismissible ) {
			self::enqueue_dismiss_script();
		}

		$retry_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::RETRY_ACTION ), self::RETRY_ACTION );
		?>
		<div
			class="notice notice-error<?php echo $dismissible ? ' is-dismissible' : ''; ?>"
			data-dsgo-notice="db-upgrade"
			<?php if ( $dismissible ) : ?>
				data-dsgo-dismiss-nonce="<?php echo esc_attr( wp_create_nonce( self::DISMISS_ACTION ) ); ?>"
			<?php endif; ?>
		>
			<p>
				<strong><?php esc_html_e( 'DesignSetGo could not create its Dynamic Query filter index table.', 'designsetgo' ); ?></strong>
				<?php esc_html_e( 'Query filters and filter counts stay unavailable until it exists. DesignSetGo will try again in a day and after each plugin update.', 'designsetgo' ); ?>
				<a href="<?php echo esc_url( $retry_url ); ?>"><?php esc_html_e( 'Retry now', 'designsetgo' ); ?></a>
			</p>
			<?php if ( '' !== $failure['error'] ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: database error message */
						esc_html__( 'Database error: %s', 'designsetgo' ),
						'<code>' . esc_html( $failure['error'] ) . '</code>'
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Whether the current admin screen belongs to DesignSetGo's menu.
	 *
	 * Uses the menu parent rather than screen ids: submenu screen ids are
	 * prefixed with the translated menu title, so they change with the locale.
	 * admin-header.php sets the parent before admin_notices fires.
	 *
	 * @return bool
	 */
	private static function is_designsetgo_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		return 'designsetgo' === $screen->parent_base || 'toplevel_page_designsetgo' === $screen->id;
	}

	/**
	 * Identifies a failure for dismissal purposes: same error, same plugin version.
	 *
	 * @param array{error: string, time: int, plugin_version: string} $failure Recorded failure.
	 * @return string
	 */
	private static function failure_signature( array $failure ): string {
		return md5( $failure['error'] . '|' . $failure['plugin_version'] );
	}

	/**
	 * Persists a click on the notice's dismiss button.
	 *
	 * Core's common.js adds the button to `.is-dismissible` notices and removes
	 * the notice on click; this only tells the server. Printed in the footer.
	 *
	 * @return void
	 */
	private static function enqueue_dismiss_script(): void {
		if ( wp_script_is( self::DISMISS_SCRIPT, 'enqueued' ) ) {
			return;
		}
		wp_register_script( self::DISMISS_SCRIPT, '', array(), DESIGNSETGO_VERSION, true );
		wp_add_inline_script(
			self::DISMISS_SCRIPT,
			sprintf(
				'document.addEventListener( "click", function ( event ) {
	var notice = event.target.closest( "[data-dsgo-notice=\"db-upgrade\"]" );
	if ( ! notice || ! event.target.closest( ".notice-dismiss" ) || ! window.ajaxurl ) {
		return;
	}
	var body = new URLSearchParams();
	body.append( "action", %s );
	body.append( "_ajax_nonce", notice.getAttribute( "data-dsgo-dismiss-nonce" ) || "" );
	window.fetch( window.ajaxurl, { method: "POST", credentials: "same-origin", body: body } );
} );',
				wp_json_encode( self::DISMISS_ACTION )
			)
		);
		wp_enqueue_script( self::DISMISS_SCRIPT );
	}
}
