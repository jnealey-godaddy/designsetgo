<?php
/**
 * Max width extension attribute schema.
 *
 * @see src/extensions/max-width/index.js
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'blocks'     => 'all',
	'exclude'    => array( // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Extension block-exclusion list, not a get_posts() query.
		'core/spacer',
		'core/separator',
		'core/page-list',
		'core/navigation',
		'designsetgo/section',
		'designsetgo/row',
		'designsetgo/grid',
		// Blobs ships its own native, kit-controllable max-width control
		// (attribute `maxWidth` -> `--dsgo-blob-max-width`), so it opts out of
		// the generic extension. src/extensions/max-width/index.js has always
		// excluded it; this list did not, so the attribute was registered on a
		// block whose save() never writes it.
		'designsetgo/blobs',
	),
	'attributes' => array(
		'dsgoMaxWidth' => array(
			'type'    => 'string',
			'default' => '',
		),
	),
);
