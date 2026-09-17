<?php
/**
 * Derives probe values for block attributes.
 *
 * Test-support code. Lives under tests/ so it never ships and never reaches
 * Plugin Check.
 *
 * The attribute coverage matrix works by taking a block's defaults, flipping
 * exactly ONE attribute to a non-default value, and round-tripping the result
 * through the PHP serializer and the real save(). This class supplies that
 * value.
 *
 * A probe does not need to be realistic. The matrix asserts PARITY, not
 * sensibility: an unrealistic value that both runtimes treat identically still
 * proves the mirror reproduces save(). It only has to be a value the inserter
 * accepts — see D0.4 in the spec. When no value can be derived, derive()
 * returns null and the attribute must be declared in
 * tests/fixtures/attribute-probes.json, with either an explicit probe or a
 * reasoned skip. That declaration is key-diffed against the live registry in
 * both directions, so it cannot rot.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

namespace DesignSetGo\Tests\Support;

/**
 * Attribute probe generator.
 */
class Attribute_Probe_Generator {

	/**
	 * Name heuristics for plain strings, in priority order.
	 *
	 * Only consulted for `type: string` attributes that declare no enum. A
	 * string attribute whose name matches none of these cannot be guessed and
	 * must be declared.
	 *
	 * @var array<int, array{pattern: string, values: array<int, string>}>
	 */
	private const STRING_HEURISTICS = array(
		array(
			// Colors take two probes: a raw hex and a preset shorthand. The
			// preset form is the one that drifted in #565 — a shorthand that
			// reached stored HTML as an invalid custom property.
			'pattern' => '/colou?r/i',
			'values'  => array( '#ff0000', 'var:preset|color|contrast' ),
		),
		array(
			// Deliberately unanchored: block.json names are camelCase, so an
			// anchored word boundary misses `imageUrl` and `videoSrc`.
			'pattern' => '/(url|href|src)/i',
			'values'  => array( 'https://example.com/probe' ),
		),
		array(
			'pattern' => '/(width|height|size|gap|radius|spacing|offset|padding|margin)/i',
			'values'  => array( '2rem' ),
		),
		array(
			'pattern' => '/(duration|delay|speed)/i',
			'values'  => array( '2s' ),
		),
		array(
			'pattern' => '/(easing|timing)/i',
			'values'  => array( 'ease-in-out' ),
		),
		array(
			'pattern' => '/id$/i',
			'values'  => array( 'probe-id' ),
		),
		array(
			'pattern' => '/(text|label|title|content|message|caption|heading|description|placeholder)/i',
			'values'  => array( 'Probe' ),
		),
	);

	/**
	 * Resolve probe values for one attribute, declarations first.
	 *
	 * Layered most-specific-first: a byBlock entry wins over a byName entry,
	 * which wins over the type/name heuristics. A declared value is always
	 * preferred over a guessed one - it is reviewed, and the heuristics are not.
	 *
	 * @param string              $block      Block name.
	 * @param string              $name       Attribute name.
	 * @param array<string,mixed> $definition Attribute definition from the registry.
	 * @param array<string,mixed> $table      Decoded attribute-probes.json.
	 * @return array<int, mixed>|null Probe values, or null when undeclared and
	 *                                underivable - which is a test failure.
	 */
	public static function resolve( string $block, string $name, array $definition, array $table ) {
		foreach ( array( $table['byBlock'][ $block ][ $name ] ?? null, $table['byName'][ $name ] ?? null ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			// A skip is a deliberate "this attribute has nothing to prove",
			// distinct from null ("nobody has decided yet").
			if ( isset( $entry['skip'] ) ) {
				return array();
			}

			if ( array_key_exists( 'probe', $entry ) ) {
				$values = is_array( $entry['probe'] ) && ! self::is_structured( $entry['probe'] )
					? array_values( $entry['probe'] )
					: array( $entry['probe'] );

				// A declared probe equal to the attribute's default would not
				// flip anything: the payload would prove nothing while
				// reporting green.
				return array_values(
					array_filter(
						$values,
						static function ( $value ) use ( $definition ) {
							return $value !== ( $definition['default'] ?? null );
						}
					)
				);
			}
		}

		return self::derive( $name, $definition );
	}

	/**
	 * Whether a declared probe is one structured value rather than a list.
	 *
	 * An object probe (a focal point, a style object) is itself an array once
	 * decoded, so a bare array_values() would scatter it into several nonsense
	 * probes. A JSON list is a list of probes; a JSON object is one probe.
	 *
	 * @param array<mixed> $probe Declared probe.
	 * @return bool True when the probe is a single structured value.
	 */
	private static function is_structured( array $probe ): bool {
		return array_keys( $probe ) !== range( 0, count( $probe ) - 1 );
	}

	/**
	 * Derive probe values for one attribute.
	 *
	 * @param string              $name       Attribute name.
	 * @param array<string,mixed> $definition Attribute definition from the registry.
	 * @return array<int, mixed>|null Probe values, empty array when there is
	 *                                nothing to flip, or null when no value can
	 *                                be derived and a declaration is required.
	 */
	public static function derive( string $name, array $definition ) {
		$default = $definition['default'] ?? null;

		// An enum is the single highest-value case: it is exactly where a
		// hand-restated list drifts from the block that owns it, so every
		// member is probed rather than just one.
		if ( ! empty( $definition['enum'] ) && is_array( $definition['enum'] ) ) {
			return array_values(
				array_filter(
					$definition['enum'],
					static function ( $member ) use ( $default ) {
						return $member !== $default;
					}
				)
			);
		}

		$type = self::scalar_type( $definition['type'] ?? null );

		if ( 'boolean' === $type ) {
			return array( ! ( true === $default ) );
		}

		if ( 'number' === $type || 'integer' === $type ) {
			return self::derive_number( $definition, $default );
		}

		if ( 'string' === $type ) {
			return self::derive_string( $name, $default );
		}

		// object, array, null, or an unrecognised type. Not guessable.
		return null;
	}

	/**
	 * Reduce a declared type to a single scalar type name.
	 *
	 * block.json allows a list of types. The first recognised scalar wins;
	 * a list containing only object/array yields no scalar and falls through
	 * to the declaration requirement.
	 *
	 * @param mixed $type Declared type.
	 * @return string Type name, or '' when none applies.
	 */
	private static function scalar_type( $type ): string {
		foreach ( (array) $type as $candidate ) {
			if ( in_array( $candidate, array( 'boolean', 'number', 'integer', 'string' ), true ) ) {
				return (string) $candidate;
			}
		}

		return '';
	}

	/**
	 * Probe a numeric attribute, honouring declared bounds.
	 *
	 * @param array<string,mixed> $definition Attribute definition.
	 * @param mixed               $default    Declared default.
	 * @return array<int, mixed> Probe values; empty when bounds leave no room.
	 */
	private static function derive_number( array $definition, $default ): array {
		$minimum = $definition['minimum'] ?? null;
		$maximum = $definition['maximum'] ?? null;
		$base    = is_numeric( $default ) ? $default + 0 : 0;

		foreach ( array( $base + 1, $base - 1, $base + 2 ) as $candidate ) {
			if ( null !== $minimum && $candidate < $minimum ) {
				continue;
			}
			if ( null !== $maximum && $candidate > $maximum ) {
				continue;
			}
			if ( $candidate === $base ) {
				continue;
			}

			return array( 'integer' === self::scalar_type( $definition['type'] ?? null ) ? (int) $candidate : $candidate );
		}

		// A fixed-point attribute (minimum === maximum) has nothing to flip.
		return array();
	}

	/**
	 * Probe a string attribute by name convention.
	 *
	 * @param string $name    Attribute name.
	 * @param mixed  $default Declared default.
	 * @return array<int, string>|null Probe values, or null when unguessable.
	 */
	private static function derive_string( string $name, $default ) {
		foreach ( self::STRING_HEURISTICS as $heuristic ) {
			if ( ! preg_match( $heuristic['pattern'], $name ) ) {
				continue;
			}

			$values = array_values(
				array_filter(
					$heuristic['values'],
					static function ( $value ) use ( $default ) {
						return $value !== $default;
					}
				)
			);

			return $values;
		}

		return null;
	}
}
