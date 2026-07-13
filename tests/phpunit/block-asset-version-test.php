<?php
/**
 * Tests that block assets are versioned with the plugin version, not block.json's.
 *
 * WordPress uses a block's `version` field from block.json as the cache-busting
 * `?ver=` on the assets that block.json declares (wp-includes/blocks.php):
 *
 *     $block_version = ! $is_core_block && isset( $metadata['version'] ) ? $metadata['version'] : false;
 *     $version       = $style_path_norm && SCRIPT_DEBUG ? filemtime( $style_path_norm ) : $block_version;
 *
 * Note the SCRIPT_DEBUG branch: in development the version is a filemtime, so it
 * always busts and the bug is invisible. In PRODUCTION it falls through to
 * block.json's `version` — which was the scaffolded "1.0.0" on nearly every block
 * and had never been bumped. `build/blocks/{block}/index.css?ver=1.0.0` was
 * therefore byte-identical across every release, so browsers and CDNs kept
 * serving the copy they cached from an older plugin version, indefinitely.
 *
 * The consequence is that a CSS-only fix to any block never reached existing
 * users. It shipped in 2.4: Icon Button's icon layout moved out of the saved
 * markup into the stylesheet, so the markup started depending on CSS that cached
 * browsers never received — the icon span lost its box and its SVG expanded to
 * fill the button.
 *
 * @package DesignSetGo
 */

/**
 * @group block-asset-version
 */
class Block_Asset_Version_Test extends WP_UnitTestCase {

	public function test_designsetgo_block_metadata_version_is_the_plugin_version() {
		$metadata = apply_filters(
			'block_type_metadata',
			array(
				'name'    => 'designsetgo/icon-button',
				'version' => '1.0.0',
			)
		);

		$this->assertSame(
			DESIGNSETGO_VERSION,
			$metadata['version'],
			'Block assets must be versioned with the plugin version so they bust on release.'
		);
	}

	/**
	 * The override is deliberately unconditional. scroll-marquee is the one block
	 * that really pins its own version (1.2.0), and that pin is discarded: a
	 * per-block pin only busts when someone remembers to bump it, which is the
	 * failure mode this fix exists to remove. Read its real block.json rather than
	 * a fabricated fixture, so this test tracks the codebase.
	 */
	public function test_it_overrides_a_block_that_pins_its_own_version() {
		$file = DESIGNSETGO_PATH . 'src/blocks/scroll-marquee/block.json';

		$this->assertFileExists( $file );

		$metadata = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test fixture.

		$this->assertNotSame(
			DESIGNSETGO_VERSION,
			$metadata['version'],
			'This test is only meaningful while scroll-marquee pins a version of its own.'
		);

		$filtered = apply_filters( 'block_type_metadata', $metadata );

		$this->assertSame( DESIGNSETGO_VERSION, $filtered['version'] );
	}

	public function test_it_supplies_a_version_when_block_json_omits_one() {
		$metadata = apply_filters(
			'block_type_metadata',
			array( 'name' => 'designsetgo/section' )
		);

		$this->assertSame( DESIGNSETGO_VERSION, $metadata['version'] );
	}

	public function test_it_leaves_non_designsetgo_blocks_alone() {
		$metadata = apply_filters(
			'block_type_metadata',
			array(
				'name'    => 'core/paragraph',
				'version' => '1.0.0',
			)
		);

		$this->assertSame( '1.0.0', $metadata['version'], 'Core and third-party blocks must not be touched.' );
	}

	public function test_it_tolerates_metadata_without_a_name() {
		$metadata = apply_filters( 'block_type_metadata', array( 'style' => 'file:./style.css' ) );

		$this->assertArrayNotHasKey( 'version', $metadata );
	}

	/**
	 * The real-world guard, run against every block.json on disk.
	 *
	 * This deliberately does NOT read the registered style handles: the test suite
	 * runs with SCRIPT_DEBUG on, so WP versions those handles with a filemtime and
	 * the assertion would pass whether or not the filter exists — a vacuous test.
	 * Feeding each real block.json through the filter exercises the `$block_version`
	 * branch WordPress actually takes in production.
	 */
	public function test_every_block_json_resolves_to_a_release_busting_version() {
		$files = glob( DESIGNSETGO_PATH . 'src/blocks/*/block.json' );

		$this->assertNotEmpty( $files, 'Expected to find block.json files to check.' );

		$stale = array();

		foreach ( $files as $file ) {
			$metadata = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test fixture.

			if ( ! isset( $metadata['name'] ) ) {
				continue;
			}

			$filtered = apply_filters( 'block_type_metadata', $metadata );
			$version  = isset( $filtered['version'] ) ? $filtered['version'] : '(none)';

			if ( DESIGNSETGO_VERSION !== $version ) {
				$stale[] = $metadata['name'] . ' => ' . $version;
			}
		}

		$this->assertSame(
			array(),
			$stale,
			"These blocks' assets would not cache-bust on release:\n" . implode( "\n", $stale )
		);
	}
}
