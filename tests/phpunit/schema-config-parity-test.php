<?php
/**
 * The schema allowlist must match on both sides.
 *
 * src/extensions/schema/constants.js decides which blocks get the control;
 * includes/extension-configs/schema.php decides which blocks get the attribute
 * registered server-side. Nothing else ties them together, and the failure mode
 * is silent: a block added only to the JS side would show an author a Schema
 * type control whose value is dropped, because the attribute was never
 * registered on the server.
 *
 * @package DesignSetGo
 */

/**
 * Allowlist parity tests.
 *
 * @group schema
 */
class Schema_Config_Parity_Test extends WP_UnitTestCase {

	/**
	 * Block names listed in the PHP extension config.
	 *
	 * @return array Sorted block names.
	 */
	private function php_blocks() {
		$config = require DESIGNSETGO_PATH . 'includes/extension-configs/schema.php';

		$blocks = $config['blocks'];
		sort( $blocks );

		return $blocks;
	}

	/**
	 * Block names used as keys of SCHEMA_TYPES in the JS constants.
	 *
	 * Read from source rather than executed: the assertion is about the file a
	 * developer edits, and a build artefact could be stale.
	 *
	 * @return array Sorted block names.
	 */
	private function js_blocks() {
		$path = DESIGNSETGO_PATH . 'src/extensions/schema/constants.js';

		$this->assertFileExists( $path );

		$source = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a source file in a test, not a remote resource.

		// Only the object literal assigned to SCHEMA_TYPES.
		$start = strpos( $source, 'export const SCHEMA_TYPES' );
		$this->assertNotFalse( $start, 'SCHEMA_TYPES was renamed or removed.' );

		$end  = strpos( $source, 'export const SCHEMA_BLOCKS', $start );
		$body = false === $end ? substr( $source, $start ) : substr( $source, $start, $end - $start );

		preg_match_all( "/'(designsetgo\/[a-z0-9-]+)'\s*:/", $body, $matches );

		$blocks = array_unique( $matches[1] );
		sort( $blocks );

		return $blocks;
	}

	/**
	 * Both sides list exactly the same blocks.
	 */
	public function test_js_and_php_allowlists_match() {
		$js  = $this->js_blocks();
		$php = $this->php_blocks();

		$this->assertNotEmpty( $js, 'Parsed no blocks out of constants.js — the parser needs updating.' );

		$this->assertSame(
			$js,
			$php,
			'src/extensions/schema/constants.js and includes/extension-configs/schema.php list different blocks. '
			. 'A block on only the JS side gets a control whose value is dropped on save.'
		);
	}

	/**
	 * The runtime builder map lists the same blocks as the config.
	 *
	 * This is the list that actually gates collection. If it drifted from the
	 * other two, a block could carry the control and the registered attribute
	 * yet never produce schema — or the reverse.
	 */
	public function test_builder_map_matches_the_config() {
		$builders = \DesignSetGo\SchemaOutput::supported_blocks();
		sort( $builders );

		$this->assertSame( $this->php_blocks(), $builders );
	}

	/**
	 * The attribute name and default agree with the JS registration.
	 */
	public function test_php_config_declares_the_expected_attribute() {
		$config = require DESIGNSETGO_PATH . 'includes/extension-configs/schema.php';

		$this->assertArrayHasKey( 'dsgoSchema', $config['attributes'] );
		$this->assertSame( 'string', $config['attributes']['dsgoSchema']['type'] );
		$this->assertSame( 'none', $config['attributes']['dsgoSchema']['default'] );
	}

	/**
	 * Every allowlisted block actually exists.
	 */
	public function test_every_allowlisted_block_has_a_block_json() {
		foreach ( $this->php_blocks() as $name ) {
			$slug = str_replace( 'designsetgo/', '', $name );

			$this->assertFileExists(
				DESIGNSETGO_PATH . 'src/blocks/' . $slug . '/block.json',
				$name . ' is allowlisted for schema but has no block.json.'
			);
		}
	}
}
