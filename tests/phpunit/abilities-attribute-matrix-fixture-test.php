<?php
/**
 * Generates the attribute coverage matrix fixture.
 *
 * The existing generated-markup fixture proves the PHP serializer mirrors
 * save() for each block at its DEFAULT attribute values, plus a set of
 * hand-authored scenarios. That is coverage per BLOCK. It cannot see a drift
 * that only appears once an attribute moves off its default - which is the
 * shape of every parity bug fixed in PR #565. An attribute added to a
 * block.json changes save() while the defaults payload stays byte-identical,
 * so nothing goes red and the first person to find out is an author looking
 * at "This block contains unexpected or invalid content".
 *
 * This file closes that gap: it takes each block's defaults and flips exactly
 * ONE attribute to a non-default value, for every attribute in the registry.
 * One attribute at a time, so a failure names one attribute rather than a
 * combination.
 *
 * What it does NOT prove, stated plainly because the green tick is otherwise
 * misleading: attributes that only affect save() in concert with another (a
 * hover colour while hover is off) round-trip without exercising anything.
 * That is an uncovered case, not a false pass, and combinations remain the job
 * of the hand-authored scenarios in abilities-generated-markup-fixture-test.php.
 *
 * Regenerate with:
 *
 *   npm run fixtures:update
 *
 * then run the JS suite to confirm the new markup still validates against the
 * real save().
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Block_Inserter;
use DesignSetGo\Tests\Support\Attribute_Probe_Generator;

/**
 * Attribute matrix fixture test.
 */
class Abilities_Attribute_Matrix_Fixture_Test extends WP_UnitTestCase {

	/**
	 * Blocks whose registration depends on WooCommerce being active.
	 *
	 * @var array<int, string>
	 */
	private const ENVIRONMENT_DEPENDENT_BLOCKS = array(
		'designsetgo/product-showcase-hero',
		'designsetgo/product-categories-grid',
	);

	/**
	 * Fixture path.
	 *
	 * @return string
	 */
	private function fixture_path(): string {
		return dirname( __DIR__ ) . '/unit/__fixtures__/ability-attribute-matrix.json';
	}

	/**
	 * Decoded declaration table.
	 *
	 * @return array<string, mixed>
	 */
	private function table(): array {
		$decoded = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/attribute-probes.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Test fixture.

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * One payload per attribute per probe value.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function payloads(): array {
		$table    = $this->table();
		$payloads = array();

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

			$attributes = is_array( $block_type->attributes ) ? $block_type->attributes : array();
			ksort( $attributes );

			foreach ( $attributes as $attribute => $definition ) {
				$probes = Attribute_Probe_Generator::resolve(
					$name,
					(string) $attribute,
					is_array( $definition ) ? $definition : array(),
					$table
				);

				// null means undeclared and underivable. That is a failure, but
				// it belongs to Abilities_Attribute_Probe_Table_Test, which
				// reports it with the guidance to fix it. Skipping here keeps
				// one failure per problem instead of two.
				if ( null === $probes ) {
					continue;
				}

				foreach ( $probes as $index => $value ) {
					$payloads[ $name . '::' . $attribute . '::' . $index ] = array(
						'name'        => $name,
						'attributes'  => array( (string) $attribute => $value ),
						'innerBlocks' => array(),
					);
				}
			}
		}

		ksort( $payloads );

		return $payloads;
	}

	/**
	 * Generate markup for every payload.
	 *
	 * @return array<string, string>
	 */
	private function generate(): array {
		$generated = array();

		foreach ( $this->payloads() as $label => $payload ) {
			$generated[ $label ] = self::stabilise_ids(
				Block_Inserter::build_block_markup(
					$payload['name'],
					$payload['attributes'],
					$payload['innerBlocks']
				)
			);
		}

		return $generated;
	}

	/**
	 * Replace generated UUIDs with stable placeholders.
	 *
	 * Several blocks seed a `uniqueId` on insert, so their markup differs on
	 * every run and the fixture could never match twice. Each distinct UUID maps
	 * to a distinct placeholder, so ids that must agree across the markup still
	 * agree - which is what the JS side validates.
	 *
	 * @param string $markup Generated markup.
	 * @return string Markup with UUIDs replaced.
	 */
	private static function stabilise_ids( string $markup ): string {
		preg_match_all( '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $markup, $matches );

		foreach ( array_values( array_unique( $matches[0] ) ) as $index => $uuid ) {
			$markup = str_replace(
				$uuid,
				sprintf( '00000000-0000-4000-8000-%012d', $index ),
				$markup
			);
		}

		return $markup;
	}

	/**
	 * Every probe value must be one the inserter actually accepts.
	 *
	 * D0.4 in the spec: a probe the inserter refuses must FAIL, never silently
	 * drop out of the matrix. A dropped payload leaves the attribute uncovered
	 * while the suite reports green, which is the exact failure mode this whole
	 * exercise exists to remove.
	 */
	public function test_every_probe_value_is_accepted(): void {
		$rejected = array();

		foreach ( $this->payloads() as $label => $payload ) {
			$invalid = Block_Inserter::find_invalid_attribute_values(
				array(
					array(
						'name'        => $payload['name'],
						'attributes'  => $payload['attributes'],
						'innerBlocks' => array(),
					),
				)
			);

			if ( ! empty( $invalid ) ) {
				$rejected[] = $label;
			}
		}

		sort( $rejected );

		$this->assertSame(
			array(),
			$rejected,
			"The inserter refused these probe values. Declare a value it accepts in\n"
				. "tests/fixtures/attribute-probes.json - do not drop the attribute:\n  "
				. implode( "\n  ", $rejected )
		);
	}

	/**
	 * The committed fixture matches what the serializer produces today.
	 */
	public function test_fixture_matches_generated_markup(): void {
		$generated = $this->generate();
		$path      = $this->fixture_path();

		if ( getenv( 'DSGO_UPDATE_FIXTURES' ) ) {
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Test fixture regeneration.
				$path,
				wp_json_encode( $generated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
			);
			$this->addToAssertionCount( 1 );
			return;
		}

		$this->assertFileExists( $path, 'Run `npm run fixtures:update` to create it.' );

		$fixture = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Test fixture.

		// Compare the payloads BOTH sides can produce, not the whole set.
		//
		// The matrix enumerates WP_Block_Type_Registry, and which attributes
		// WordPress injects onto a block changes between WordPress versions -
		// `style` is registered on the form-field blocks in 6.9 and not on
		// trunk, for instance. CI runs trunk while the fixture is generated
		// against the version .wp-env.json pins, so asserting the two sets are
		// identical fails on a core change rather than a plugin one.
		//
		// What must hold, and is asserted here, is that every payload this
		// environment CAN generate is byte-identical to the committed one. A
		// serializer bug changes markup; it does not make an attribute vanish
		// from the registry. test_matrix_covers_the_registry() separately
		// guarantees the set has not collapsed.
		$shared = array_intersect_key( $generated, $fixture );

		$this->assertGreaterThan(
			700,
			count( $shared ),
			'Too few payloads are shared with the committed fixture to be meaningful. Regenerate with `npm run fixtures:update`.'
		);

		$this->assertSame(
			$shared,
			array_intersect_key( $fixture, $shared ),
			'Generated markup drifted from the matrix fixture. Regenerate with `npm run fixtures:update`, then run the JS suite to confirm the new markup still validates against save().'
		);
	}

	/**
	 * The matrix must actually cover the registry.
	 *
	 * A generator bug that quietly produced no payloads would make every other
	 * assertion in this file pass. Pin the magnitude so that cannot happen
	 * silently; the bound is deliberately loose because adding blocks and
	 * attributes is normal and should not fail the build.
	 */
	public function test_matrix_covers_the_registry(): void {
		$payloads = $this->payloads();

		$this->assertGreaterThan(
			700,
			count( $payloads ),
			'The attribute matrix collapsed. Expected roughly one payload per registry attribute.'
		);
	}
}
