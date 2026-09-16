<?php
/**
 * Attribute_Suggest is a PHP port of src/engine/attributes.js's distance
 * function - same Damerau-Levenshtein algorithm, same case-insensitive-is-
 * zero rule, same thresholds. These tests mirror
 * src/engine/test/attributes.test.js's `attributeDistance`/
 * `closestAttributeName` cases so the two stay provably in sync.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Agent_Build\Attribute_Suggest;

/**
 * Attribute_Suggest tests.
 *
 * @group abilities
 * @group agent-build
 */
class Agent_Build_Attribute_Suggest_Test extends WP_UnitTestCase {

	/**
	 * Identical strings are distance 0.
	 */
	public function test_identical_strings_are_distance_0() {
		$this->assertSame( 0, Attribute_Suggest::distance( 'backgroundColor', 'backgroundColor' ) );
	}

	/**
	 * A case-only difference is distance 0.
	 */
	public function test_a_case_only_difference_is_distance_0() {
		$this->assertSame( 0, Attribute_Suggest::distance( 'backgroundcolor', 'backgroundColor' ) );
	}

	/**
	 * A single substitution is distance 1.
	 */
	public function test_a_single_substitution_is_distance_1() {
		$this->assertSame( 1, Attribute_Suggest::distance( 'backgroundColour', 'backgroundColor' ) );
	}

	/**
	 * An adjacent transposition is distance 1.
	 */
	public function test_an_adjacent_transposition_is_distance_1() {
		$this->assertSame( 1, Attribute_Suggest::distance( 'taxt', 'text' ) );
	}

	/**
	 * Unrelated strings are far apart.
	 */
	public function test_unrelated_strings_are_far_apart() {
		$this->assertGreaterThan( 3, Attribute_Suggest::distance( 'backgroundColor', 'zzz' ) );
	}

	/**
	 * Finds a close match within the short-name threshold.
	 */
	public function test_finds_a_close_match_within_the_short_name_threshold() {
		$this->assertSame( 'text', Attribute_Suggest::closest( 'taxt', array( 'text', 'heading' ) ) );
	}

	/**
	 * Returns null when nothing is close enough.
	 */
	public function test_returns_null_when_nothing_is_close_enough() {
		$this->assertNull( Attribute_Suggest::closest( 'zzz', array( 'text', 'heading' ) ) );
	}

	/**
	 * Allows a wider distance for long names.
	 */
	public function test_allows_a_wider_distance_for_long_names() {
		// 3 edits away from a 16-character name - too far for the short-name
		// threshold (2), allowed for a name >= 10 characters (3).
		$this->assertSame( 'backgroundColor', Attribute_Suggest::closest( 'bakgroundColur', array( 'backgroundColor' ) ) );
	}

	/**
	 * Empty candidate list never suggests anything.
	 */
	public function test_empty_candidate_list_never_suggests_anything() {
		$this->assertNull( Attribute_Suggest::closest( 'anything', array() ) );
	}
}
