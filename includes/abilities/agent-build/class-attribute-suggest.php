<?php
/**
 * "Did you mean" attribute-name suggestions for Tree_Attributes.
 *
 * A PHP port of src/engine/attributes.js's distance function - same
 * Damerau-Levenshtein algorithm, same case-insensitive-is-zero rule, same
 * thresholds - so an agent gets the identical reason text whether the
 * unknown attribute is caught here (PHP, up front) or in the browser engine
 * (the authoritative check). No shared implementation: PHP has no access to
 * the JS module, so this is a deliberate, tested duplicate.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.7.0
 */

namespace DesignSetGo\Abilities\Agent_Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attribute_Suggest class.
 */
class Attribute_Suggest {

	/** Matches LONG_NAME_LENGTH in src/engine/attributes.js. */
	const LONG_NAME_LENGTH = 10;

	/**
	 * Finds the closest known attribute name to `$name`, when one is close
	 * enough to plausibly be what the agent meant. Matches
	 * closestAttributeName() in src/engine/attributes.js: distance <= 2, or
	 * <= 3 when the longer of the two names is at least LONG_NAME_LENGTH
	 * characters. Ties break on `$known_names`' own order.
	 *
	 * @param string             $name        Submitted (unknown) attribute name.
	 * @param array<int, string> $known_names Candidate known attribute names.
	 * @return string|null The closest name, or null when none is close enough.
	 */
	public static function closest( string $name, array $known_names ): ?string {
		$best          = null;
		$best_distance = PHP_INT_MAX;

		foreach ( $known_names as $candidate ) {
			$distance  = self::distance( $name, $candidate );
			$threshold = max( strlen( $name ), strlen( $candidate ) ) >= self::LONG_NAME_LENGTH ? 3 : 2;

			if ( $distance <= $threshold && $distance < $best_distance ) {
				$best          = $candidate;
				$best_distance = $distance;
			}
		}

		return $best;
	}

	/**
	 * Edit distance between an attribute name and a candidate, with a
	 * case-insensitive match always counting as distance 0. Matches
	 * attributeDistance() in src/engine/attributes.js.
	 *
	 * @param string $name      Submitted (unknown) attribute name.
	 * @param string $candidate Known attribute name.
	 * @return int Edit distance.
	 */
	public static function distance( string $name, string $candidate ): int {
		if ( strtolower( $name ) === strtolower( $candidate ) ) {
			return 0;
		}
		return self::damerau_levenshtein( $name, $candidate );
	}

	/**
	 * Full Damerau-Levenshtein distance (arbitrary-position transpositions),
	 * via the classic Damerau/Levenshtein dynamic program. Matches
	 * damerauLevenshteinDistance() in src/engine/attributes.js exactly -
	 * PHP's built-in levenshtein() has no transposition support and silently
	 * caps at 255 characters, neither of which is acceptable here.
	 *
	 * @param string $a First string.
	 * @param string $b Second string.
	 * @return int Edit distance.
	 */
	private static function damerau_levenshtein( string $a, string $b ): int {
		$len_a = strlen( $a );
		$len_b = strlen( $b );

		if ( $a === $b ) {
			return 0;
		}
		if ( 0 === $len_a ) {
			return $len_b;
		}
		if ( 0 === $len_b ) {
			return $len_a;
		}

		$max_dist = $len_a + $len_b;
		$last_row = array();

		$d = array();
		for ( $i = 0; $i <= $len_a + 1; $i++ ) {
			$d[ $i ] = array_fill( 0, $len_b + 2, 0 );
		}
		$d[0][0] = $max_dist;
		for ( $i = 0; $i <= $len_a; $i++ ) {
			$d[ $i + 1 ][0] = $max_dist;
			$d[ $i + 1 ][1] = $i;
		}
		for ( $j = 0; $j <= $len_b; $j++ ) {
			$d[0][ $j + 1 ] = $max_dist;
			$d[1][ $j + 1 ] = $j;
		}

		for ( $i = 1; $i <= $len_a; $i++ ) {
			$last_match_col = 0;
			for ( $j = 1; $j <= $len_b; $j++ ) {
				$i1   = $last_row[ $b[ $j - 1 ] ] ?? 0;
				$j1   = $last_match_col;
				$cost = 1;

				if ( $a[ $i - 1 ] === $b[ $j - 1 ] ) {
					$cost           = 0;
					$last_match_col = $j;
				}

				$d[ $i + 1 ][ $j + 1 ] = min(
					$d[ $i ][ $j ] + $cost, // substitution (or match).
					$d[ $i + 1 ][ $j ] + 1, // insertion.
					$d[ $i ][ $j + 1 ] + 1, // deletion.
					$d[ $i1 ][ $j1 ] + ( $i - $i1 - 1 ) + 1 + ( $j - $j1 - 1 ) // transposition.
				);
			}
			$last_row[ $a[ $i - 1 ] ] = $i;
		}

		return $d[ $len_a + 1 ][ $len_b + 1 ];
	}
}
