<?php
/**
 * Plugin Name: DSGo e2e classic meta box
 * Description: Test-only. agent-build-remote.spec.js copies this into the wp-env site's mu-plugins for one test and deletes it afterwards. It never ships (/tests is in .distignore).
 *
 * A classic meta box on posts makes the block editor's Preview pass
 * `forceIsAutosaveable`, which skips the autosave lock.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'add_meta_boxes',
	static function () {
		add_meta_box(
			'dsgo-e2e-classic-meta-box',
			'DSGo e2e classic meta box',
			static function () {
				echo '<p>DSGo e2e classic meta box</p>';
			},
			'post',
			'normal'
		);
	}
);
