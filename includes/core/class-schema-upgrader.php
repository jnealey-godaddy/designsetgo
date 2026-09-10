<?php
/**
 * Database schema upgrade gate.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Core;

use DesignSetGo\Admin\SchemaUpgradeNotice;
use DesignSetGo\Blocks\Query\FilterIndex;

defined( 'ABSPATH' ) || exit;

/**
 * Installs the plugin's custom tables once per schema version and backs off
 * when the database refuses the schema.
 *
 * Before #551 a failed CREATE TABLE re-ran on every admin_init: the gate
 * sealed itself only once the table existed, so a server that rejected the
 * schema (MyISAM's 1000-byte key limit) logged the same error on every
 * wp-admin request and dragged out core updates. A failure is now recorded
 * with its database error and retried once a day, after a plugin update, or
 * when an administrator asks. SchemaUpgradeNotice tells administrators.
 */
class SchemaUpgrader {

	/**
	 * Option holding the installed schema version.
	 */
	const OPTION_VERSION = 'designsetgo_db_version';

	/**
	 * Option holding the last install failure: error, time, plugin_version.
	 */
	const OPTION_FAILURE = 'designsetgo_db_upgrade_failure';

	/**
	 * Schema version the plugin currently requires.
	 */
	const SCHEMA_VERSION = '2.2.0';

	/**
	 * Seconds to wait before retrying a failed install on the same plugin version.
	 */
	const RETRY_INTERVAL = DAY_IN_SECONDS;

	/**
	 * Installs missing schema, unless a recent failure says not to bother yet.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$stored = get_option( self::OPTION_VERSION, '0.0.0' );
		if ( version_compare( $stored, self::SCHEMA_VERSION, '>=' ) ) {
			return;
		}

		if ( ! self::should_retry() ) {
			return;
		}

		if ( FilterIndex::install() ) {
			update_option( self::OPTION_VERSION, self::SCHEMA_VERSION, false );
			delete_option( self::OPTION_FAILURE );
			// Nothing left to dismiss; don't let a stale dismissal hide a future failure.
			delete_metadata( 'user', 0, SchemaUpgradeNotice::DISMISS_META, '', true );
			return;
		}

		update_option(
			self::OPTION_FAILURE,
			array(
				'error'          => FilterIndex::last_install_error(),
				'time'           => time(),
				'plugin_version' => DESIGNSETGO_VERSION,
			),
			false
		);
	}

	/**
	 * Returns the recorded install failure, or null when the last install succeeded.
	 *
	 * @return array{error: string, time: int, plugin_version: string}|null
	 */
	public static function get_failure(): ?array {
		$failure = get_option( self::OPTION_FAILURE );
		if ( ! is_array( $failure ) ) {
			return null;
		}
		return array(
			'error'          => isset( $failure['error'] ) ? (string) $failure['error'] : '',
			'time'           => isset( $failure['time'] ) ? (int) $failure['time'] : 0,
			'plugin_version' => isset( $failure['plugin_version'] ) ? (string) $failure['plugin_version'] : '',
		);
	}

	/**
	 * Forgets the recorded failure so the next admin_init retries.
	 *
	 * @return void
	 */
	public static function clear_failure(): void {
		delete_option( self::OPTION_FAILURE );
	}

	/**
	 * Whether an install should be attempted on this request.
	 *
	 * True with no recorded failure, after a plugin update (the new release
	 * may carry a schema fix, which is exactly the #551 upgrade path), or once
	 * RETRY_INTERVAL has elapsed since the failure.
	 *
	 * @return bool
	 */
	public static function should_retry(): bool {
		$failure = self::get_failure();
		if ( null === $failure ) {
			return true;
		}
		if ( DESIGNSETGO_VERSION !== $failure['plugin_version'] ) {
			return true;
		}
		return ( $failure['time'] + self::RETRY_INTERVAL ) <= time();
	}

	/**
	 * Clears the failure and runs the upgrade now.
	 *
	 * @return bool True when the schema is installed afterwards.
	 */
	public static function retry(): bool {
		self::clear_failure();
		self::maybe_upgrade();
		return null === self::get_failure();
	}
}
