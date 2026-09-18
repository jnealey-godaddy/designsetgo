<?php
/**
 * Keeps the attribute probe table in step with the live block registry.
 *
 * The attribute coverage matrix can only prove the PHP serializer mirrors
 * save() for attributes it has a value to flip. This test is what stops that
 * set silently shrinking:
 *
 * - An attribute with no derivable probe and no declaration fails. Adding an
 *   attribute to a block.json therefore forces a decision - supply a probe, or
 *   write down why the attribute has nothing to prove.
 * - A declaration naming an attribute the registry no longer has fails too.
 *   Without that half, the table would rot into a list of things that used to
 *   be true, and a `skip` written for a deleted attribute would sit there
 *   silently excusing an attribute that came back later under the same name.
 *
 * Enumerating WP_Block_Type_Registry rather than the block.json files is
 * deliberate: extension attributes (dsgoVisibility, the animation attributes,
 * grid span, style bindings) are injected via `register_block_type_args` by
 * Extension_Attributes, so only the registry sees the whole set.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Block_Inserter;
use DesignSetGo\Tests\Support\Attribute_Probe_Generator;

/**
 * Probe table coverage test.
 */
class Abilities_Attribute_Probe_Table_Test extends WP_UnitTestCase {

	/**
	 * Blocks whose registration depends on WooCommerce being active.
	 *
	 * Including them would make the table's contents depend on which plugins
	 * the environment has, so it would pass locally and fail in CI or the
	 * reverse. Both are server-rendered and serialize to a bare comment.
	 *
	 * @var array<int, string>
	 */
	private const ENVIRONMENT_DEPENDENT_BLOCKS = array(
		'designsetgo/product-showcase-hero',
		'designsetgo/product-categories-grid',
	);

	/**
	 * Path to the declaration table.
	 *
	 * @return string
	 */
	private function table_path(): string {
		return dirname( __DIR__ ) . '/fixtures/attribute-probes.json';
	}

	/**
	 * Decoded declaration table.
	 *
	 * @return array<string, mixed>
	 */
	private function table(): array {
		$decoded = json_decode( (string) file_get_contents( $this->table_path() ), true );

		$this->assertIsArray( $decoded, 'attribute-probes.json is not valid JSON.' );

		return $decoded;
	}

	/**
	 * Every attribute the matrix will probe, keyed by block name.
	 *
	 * Scoped to the blocks the matrix actually serializes. A block the inserter
	 * refuses (get_serialization_gap()) never reaches stored markup, so its
	 * attributes have nothing to prove - and demanding probes for them would be
	 * noise that trains people to write meaningless skips. If such a block later
	 * gains a serializer it starts requiring probes here, which is correct.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private function covered_attributes(): array {
		$covered = array();

		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block_type ) {
			$name = (string) $name;

			if ( 0 !== strpos( $name, 'designsetgo/' ) ) {
				continue;
			}

			if ( null !== Block_Inserter::get_serialization_gap( $name ) ) {
				continue;
			}

			if ( in_array( $name, self::ENVIRONMENT_DEPENDENT_BLOCKS, true ) ) {
				continue;
			}

			$covered[ $name ] = is_array( $block_type->attributes ) ? $block_type->attributes : array();
		}

		return $covered;
	}

	/**
	 * Attribute names this plugin declares, from block.json and the extension
	 * configs.
	 *
	 * WordPress injects attributes of its own onto every block - `style`,
	 * `className`, `lock`, `metadata`, `align`, the colour and typography
	 * presets - and WHICH ones it injects changes between WordPress versions.
	 * CI runs WordPress trunk while the fixtures are generated against the
	 * version .wp-env.json pins, so demanding a declaration for core's
	 * attributes would fail the build whenever core changed, for a reason that
	 * has nothing to do with this plugin.
	 *
	 * Core's attributes are still PROBED wherever the heuristics can derive a
	 * value; they are simply not required to be declared. Everything the plugin
	 * owns - which is everything a change here can break - still is.
	 *
	 * @return array<string, bool> Attribute name => true.
	 */
	private function plugin_owned_attribute_names(): array {
		$names = array();

		foreach ( glob( dirname( __DIR__, 2 ) . '/src/blocks/*/block.json' ) ?: array() as $path ) {
			$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Reading a source file in a test.

			foreach ( array_keys( (array) ( $json['attributes'] ?? array() ) ) as $name ) {
				$names[ (string) $name ] = true;
			}
		}

		foreach ( glob( dirname( __DIR__, 2 ) . '/includes/extension-configs/*.php' ) ?: array() as $path ) {
			$config = require $path;

			foreach ( array_keys( (array) ( $config['attributes'] ?? array() ) ) as $name ) {
				$names[ (string) $name ] = true;
			}
		}

		return $names;
	}

	/**
	 * Every attribute the plugin declares must resolve to a probe or a skip.
	 */
	public function test_every_covered_attribute_is_probeable_or_declared() {
		$table   = $this->table();
		$owned   = $this->plugin_owned_attribute_names();
		$missing = array();

		foreach ( $this->covered_attributes() as $block => $attributes ) {
			foreach ( $attributes as $attribute => $definition ) {
				if ( ! isset( $owned[ (string) $attribute ] ) ) {
					continue;
				}

				if ( ! is_array( $definition ) ) {
					$definition = array();
				}

				if ( null !== Attribute_Probe_Generator::resolve( $block, (string) $attribute, $definition, $table ) ) {
					continue;
				}

				$missing[] = $block . '::' . $attribute;
			}
		}

		sort( $missing );

		$this->assertSame(
			array(),
			$missing,
			"These attributes have no derivable probe and no declaration in\n"
				. "tests/fixtures/attribute-probes.json. Add either a \"probe\" value or a\n"
				. "\"skip\" with a reason:\n  " . implode( "\n  ", $missing )
		);
	}

	/**
	 * A byBlock declaration must name a block and attribute that still exist.
	 */
	public function test_no_stale_by_block_declarations() {
		$table   = $this->table();
		$covered = $this->covered_attributes();
		$stale   = array();

		foreach ( (array) ( $table['byBlock'] ?? array() ) as $block => $entries ) {
			if ( ! isset( $covered[ $block ] ) ) {
				$stale[] = $block . ' (block is not registered, or the inserter refuses it)';
				continue;
			}

			foreach ( (array) $entries as $attribute => $entry ) {
				if ( ! array_key_exists( $attribute, $covered[ $block ] ) ) {
					$stale[] = $block . '::' . $attribute . ' (attribute no longer exists)';
				}
			}
		}

		sort( $stale );

		$this->assertSame(
			array(),
			$stale,
			"Stale byBlock declarations in tests/fixtures/attribute-probes.json:\n  "
				. implode( "\n  ", $stale )
		);
	}

	/**
	 * A byName declaration must still apply to at least one covered attribute.
	 *
	 * An unused byName entry is not harmless: it is a reviewed decision about an
	 * attribute that no longer exists, and leaving it in place means the next
	 * block to reuse that name silently inherits a probe nobody chose for it.
	 */
	public function test_no_unused_by_name_declarations() {
		$table = $this->table();
		$used  = array();

		foreach ( $this->covered_attributes() as $attributes ) {
			foreach ( array_keys( $attributes ) as $attribute ) {
				$used[ (string) $attribute ] = true;
			}
		}

		$unused = array_values( array_diff( array_keys( (array) ( $table['byName'] ?? array() ) ), array_keys( $used ) ) );
		sort( $unused );

		$this->assertSame(
			array(),
			$unused,
			"Unused byName declarations in tests/fixtures/attribute-probes.json:\n  "
				. implode( "\n  ", $unused )
		);
	}

	/**
	 * Every skip must carry a reason.
	 */
	public function test_every_skip_states_a_reason() {
		$table     = $this->table();
		$reasonless = array();

		foreach ( array( 'byName', 'byBlock' ) as $section ) {
			foreach ( (array) ( $table[ $section ] ?? array() ) as $key => $entry ) {
				$entries = isset( $entry['skip'] ) || isset( $entry['probe'] ) ? array( $key => $entry ) : (array) $entry;

				foreach ( $entries as $attribute => $declaration ) {
					if ( ! is_array( $declaration ) || ! array_key_exists( 'skip', $declaration ) ) {
						continue;
					}

					if ( ! is_string( $declaration['skip'] ) || strlen( trim( $declaration['skip'] ) ) < 20 ) {
						$reasonless[] = $section . ' ' . $key . '::' . $attribute;
					}
				}
			}
		}

		sort( $reasonless );

		$this->assertSame(
			array(),
			$reasonless,
			"These skips do not state a reason. A skip removes an attribute from\n"
				. "coverage permanently, so it has to say why:\n  " . implode( "\n  ", $reasonless )
		);
	}
}
