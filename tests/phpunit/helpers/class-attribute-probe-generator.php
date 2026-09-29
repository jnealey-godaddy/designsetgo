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
	 * @return array<int, mixed>|null Probe values; an empty array only for an
	 *                                explicit skip; null when undeclared and
	 *                                underivable, or when every candidate equals
	 *                                the default - which is a test failure.
	 */
	public static function resolve( string $block, string $name, array $definition, array $table ) {
		// Order matters. byBlock is a deliberate statement about THIS attribute
		// on THIS block, so it wins outright. A declared enum comes next: it is
		// the block's own statement of its valid values, and a byName entry -
		// which is only a naming convention - must never quietly narrow or
		// contradict it. Three attributes drifted exactly that way: a byName
		// probe for `layout` and `separator` shadowed the enums breadcrumbs,
		// countdown-timer and timeline declare, and the inserter refused the
		// resulting values.
		$candidates = array( $table['byBlock'][ $block ][ $name ] ?? null );

		if ( empty( $definition['enum'] ) || ! is_array( $definition['enum'] ) ) {
			$candidates[] = $table['byName'][ $name ] ?? null;
		}

		foreach ( $candidates as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			// A skip is a deliberate "this attribute has nothing to prove",
			// distinct from null ("nobody has decided yet").
			if ( isset( $entry['skip'] ) ) {
				return array();
			}

			// `probes` (plural) is a LIST of alternative values to try; `probe`
			// (singular) is ONE value, whatever its type.
			//
			// This used to be one key whose meaning was guessed from the value's
			// shape: a JSON list meant several probes, an object meant one. That
			// guess is unresolvable for an ARRAY-valued attribute, and it guessed
			// wrong - `"probe": [ { ... } ]` for comparison-table's `columns` was
			// unwrapped into a single object, so the payload set `columns` to an
			// object where the block expects an array. Every array attribute was
			// probed with the wrong shape, and those payloads passed for the
			// wrong reason.
			if ( array_key_exists( 'probes', $entry ) && is_array( $entry['probes'] ) ) {
				$values = array_values( $entry['probes'] );
			} elseif ( array_key_exists( 'probe', $entry ) ) {
				$values = array( $entry['probe'] );
			} else {
				continue;
			}

			// A declared probe equal to the attribute's default would not flip
			// anything: the payload would prove nothing while reporting green.
			return self::null_if_empty(
				array_values(
					array_filter(
						$values,
						static function ( $value ) use ( $definition ) {
							return ( $definition['default'] ?? null ) !== $value;
						}
					)
				)
			);
		}

		$derived = self::derive( $name, $definition );

		return null === $derived ? null : self::null_if_empty( $derived );
	}

	/**
	 * Treat "every candidate equals the default" as underivable.
	 *
	 * Only an explicit `skip` may leave an attribute with no probes. An empty
	 * list from filtering used to pass as covered while producing no payload:
	 * counter-group's separator and decimal declared their own defaults, and
	 * slider's transitionEasing and timeline's itemSpacing defaulted to the
	 * heuristic's only value. Returning null makes the probe table test demand
	 * a real probe or a reasoned skip.
	 *
	 * @param array<int, mixed> $values Probe values.
	 * @return array<int, mixed>|null
	 */
	private static function null_if_empty( array $values ) {
		return array() === $values ? null : $values;
	}

	/**
	 * Attribute names this plugin declares, from block.json and the extension
	 * configs.
	 *
	 * WordPress injects attributes of its own onto every block - `style`,
	 * `className`, `lock`, `metadata`, `align`, the colour and typography
	 * presets - and WHICH ones it injects changes between WordPress versions.
	 * CI runs WordPress trunk while the fixtures are generated against the
	 * version .wp-env.json pins, so a check on core's attributes would fail the
	 * build whenever core changed, for a reason unrelated to this plugin.
	 *
	 * Ownership is per BLOCK, not per attribute name. `anchor` is the case that
	 * proves why: designsetgo/tab declares it in its own block.json, while every
	 * other block gets it from core's anchor support - which only exists in PHP
	 * from WordPress 7.0, so trunk registers it and the pinned 6.9 does not.
	 *
	 * @return array{blocks: array<string, array<string, bool>>, extensions: array<string, bool>}
	 */
	public static function plugin_owned_attributes(): array {
		$by_block   = array();
		$extensions = array();
		$root       = dirname( __DIR__, 3 );

		$block_files = glob( $root . '/src/blocks/*/block.json' );

		foreach ( is_array( $block_files ) ? $block_files : array() as $path ) {
			$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Reading a source file in a test.
			$name = (string) ( $json['name'] ?? '' );

			if ( '' === $name ) {
				continue;
			}

			foreach ( array_keys( (array) ( $json['attributes'] ?? array() ) ) as $attribute ) {
				$by_block[ $name ][ (string) $attribute ] = true;
			}
		}

		// Extension attributes are injected uniformly by Extension_Attributes
		// rather than declared per block, so they are owned wherever they appear.
		$config_files = glob( $root . '/includes/extension-configs/*.php' );

		foreach ( is_array( $config_files ) ? $config_files : array() as $path ) {
			$config = require $path;

			foreach ( array_keys( (array) ( $config['attributes'] ?? array() ) ) as $attribute ) {
				$extensions[ (string) $attribute ] = true;
			}
		}

		return array(
			'blocks'     => $by_block,
			'extensions' => $extensions,
		);
	}

	/**
	 * Whether the plugin owns an attribute on a block.
	 *
	 * @param array{blocks: array<string, array<string, bool>>, extensions: array<string, bool>} $owned     From plugin_owned_attributes().
	 * @param string                                                                             $block     Block name.
	 * @param string                                                                             $attribute Attribute name.
	 * @return bool
	 */
	public static function is_plugin_owned( array $owned, string $block, string $attribute ): bool {
		return isset( $owned['blocks'][ $block ][ $attribute ] ) || isset( $owned['extensions'][ $attribute ] );
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
	 * A block.json type may be a list. The first recognised scalar wins;
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
