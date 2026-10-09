<?php
/** Optional responsive composition attribute schema.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => array_keys( \DesignSetGo\Layout_Support::contract()['blocks'] ),
	'exclude'    => array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block exclusions, not a query.
	'attributes' => array( 'dsgoLayout' => \DesignSetGo\Layout_Support::schema() ),
);
