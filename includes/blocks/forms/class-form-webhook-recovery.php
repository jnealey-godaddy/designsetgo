<?php
/**
 * Recovers form webhook deliveries stranded in `pending`.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_Webhook_Recovery.
 */
class Form_Webhook_Recovery {

	/** Most stranded submissions one daily sweep reschedules. */
	const SWEEP_LIMIT = 50;

	/** Seconds between swept retries, so a dead receiver can't stall one cron run. */
	const SWEEP_SPACING = 30;

	/** A submission attempted more recently than this may still be in flight. */
	const IN_FLIGHT_SECONDS = 600;

	/**
	 * Schedule a retry for submissions stuck in `pending` with none scheduled.
	 *
	 * Recovers deliveries lost to PHP dying mid-call or a deactivated plugin.
	 * Submissions under ten minutes old, or attempted in the last ten minutes,
	 * are skipped (possibly in flight). Oldest first, at most SWEEP_LIMIT, spaced
	 * SWEEP_SPACING seconds apart.
	 *
	 * @return int Number of retries scheduled.
	 */
	public static function reschedule_stranded(): int {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- Bounded daily sweep.
		$ids = get_posts(
			array(
				'post_type'      => 'dsgo_form_submission',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => self::SWEEP_LIMIT,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_key'       => '_dsg_webhook_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded daily sweep.
				'meta_value'     => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded daily sweep.
				'date_query'     => array(
					array(
						'column' => 'post_date_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - self::IN_FLIGHT_SECONDS ), // UTC, matching the column.
					),
				),
			)
		);

		update_meta_cache( 'post', $ids );
		$scheduled = 0;
		foreach ( $ids as $id ) {
			$args = array( (int) $id );
			if ( (int) get_post_meta( (int) $id, '_dsg_webhook_last_attempt', true ) > time() - self::IN_FLIGHT_SECONDS || false !== wp_next_scheduled( Form_Webhooks::RETRY_HOOK, $args ) ) {
				continue;
			}
			if ( wp_schedule_single_event( time() + $scheduled * self::SWEEP_SPACING, Form_Webhooks::RETRY_HOOK, $args ) ) {
				++$scheduled;
			}
		}

		return $scheduled;
	}
}
