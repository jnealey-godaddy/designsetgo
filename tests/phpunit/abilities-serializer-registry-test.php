<?php
/**
 * The serializer registry must cover the blocks that need serializing.
 *
 * Before the split, "which blocks can the inserter serialize?" could only be
 * answered by calling a 3,100-line switch once per block and checking whether
 * it returned null. The set was real but not enumerable, so nothing could
 * assert anything about it as a whole.
 *
 * Now it is a mapping, and this test is the two-way diff that keeps it honest:
 *
 * - a registry entry naming a block that no longer exists fails, so a deleted
 *   or renamed block cannot leave a serializer behind pointing at nothing;
 * - every block whose save() emits markup must have an entry, which is checked
 *   against the same blocks-with-save-output fixture the older coverage test
 *   uses. That fixture is generated from the REAL block registrations in
 *   JavaScript, because PHP cannot run save() to tell a static block from a
 *   dynamic one.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Serializers\Serializer_Registry;

/**
 * Serializer registry coverage test.
 */
class Abilities_Serializer_Registry_Test extends WP_UnitTestCase {

	/**
	 * No entry names a block that is not registered.
	 */
	public function test_no_entry_names_an_unregistered_block(): void {
		$registry = \WP_Block_Type_Registry::get_instance();
		$unknown  = array();

		foreach ( Serializer_Registry::block_names() as $name ) {
			if ( ! $registry->is_registered( $name ) ) {
				$unknown[] = $name;
			}
		}

		sort( $unknown );

		$this->assertSame(
			array(),
			$unknown,
			"These serializers name blocks that are not registered.\n"
				. "Delete the serializer, or restore the block:\n  " . implode( "\n  ", $unknown )
		);
	}

	/**
	 * Every block whose save() emits markup has a serializer.
	 *
	 * The list comes from tests/unit/blocks-with-save-output.test.js, which asks
	 * the real block registrations. A hybrid block - one with BOTH a render.php
	 * and a save.js - is the case that makes this necessary: is_dynamic_block()
	 * treats it as server-rendered and would skip it, but its stored markup
	 * still has to carry the wrapper save() emits. designsetgo/scroll-slide
	 * shipped that way.
	 */
	public function test_every_block_with_save_output_has_a_serializer(): void {
		$path = dirname( __DIR__ ) . '/unit/__fixtures__/blocks-with-save-output.json';

		$this->assertFileExists(
			$path,
			'Regenerate with: DSGO_UPDATE_FIXTURES=1 npx wp-scripts test-unit-js tests/unit/blocks-with-save-output.test.js'
		);

		$names   = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Test fixture.
		$missing = array();

		foreach ( (array) $names as $name ) {
			if ( ! Serializer_Registry::has( (string) $name ) ) {
				$missing[] = (string) $name;
			}
		}

		sort( $missing );

		$this->assertSame(
			array(),
			$missing,
			"These blocks emit markup from save() but have no serializer, so the\n"
				. "inserter cannot reproduce them:\n  " . implode( "\n  ", $missing )
		);
	}

	/**
	 * Every serializer exposes the interface the registry calls.
	 *
	 * The registry dispatches to a static method by convention rather than
	 * through an interface, since PHP cannot declare a static method abstract in
	 * a useful way here. This is the check that keeps the convention true.
	 */
	public function test_every_serializer_is_callable(): void {
		$broken = array();

		foreach ( Serializer_Registry::block_names() as $name ) {
			$result = Serializer_Registry::wrapper( $name, array() );

			if ( ! is_array( $result ) || ! isset( $result['opening'], $result['closing'] ) ) {
				$broken[] = $name;
			}
		}

		sort( $broken );

		$this->assertSame(
			array(),
			$broken,
			"These serializers did not return an opening/closing pair:\n  " . implode( "\n  ", $broken )
		);
	}

	/**
	 * One file per block slug, matching the registry.
	 *
	 * Fall-through cases in the old switch became several names sharing one
	 * class, so files are counted rather than matched one-to-one against names.
	 */
	public function test_every_serializer_has_its_own_file(): void {
		$dir = dirname( __DIR__, 2 ) . '/includes/abilities/serializers';

		$this->assertDirectoryExists( $dir );

		$files = glob( $dir . '/class-*-serializer.php' );

		$this->assertIsArray( $files );
		$this->assertGreaterThan(
			40,
			count( $files ),
			'The serializer directory collapsed; the registry should map to one file per block.'
		);
	}
}
