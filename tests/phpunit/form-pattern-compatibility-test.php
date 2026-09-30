<?php
/**
 * HTML-v / PCRE compatibility boundary for form patterns.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use DesignSetGo\Blocks\Form_Field_Rules;
use WP_UnitTestCase;

/** Tests patterns through the existing field validation entry point. */
class Test_Form_Pattern_Compatibility extends WP_UnitTestCase {

	/**
	 * Keep browser-only patterns separate from shared backend constraints.
	 *
	 * @dataProvider pattern_cases
	 * @param string $pattern Authored HTML pattern.
	 * @param string $value Submitted value.
	 * @param bool   $accepted Whether the backend may accept the value.
	 */
	public function test_pattern_compatibility( $pattern, $value, $accepted ) {
		$result = Form_Field_Rules::check( $value, array( 'pattern' => $pattern ) );
		if ( $accepted ) {
			$this->assertTrue( $result );
		} else {
			$this->assertWPError( $result );
		}
	}

	/**
	 * Shared constraints and browser-only syntax cases.
	 *
	 * @return array
	 */
	public function pattern_cases() {
		return array(
			'valid v intersection'     => array( '[[A-Z]&&[^AEIOU]]+', 'B', true ),
			'v intersection'           => array( '[A-Z&&[^AEIOU]]+', 'B', true ),
			'invalid v hyphen'         => array( '[A-Z-]+', '123', true ),
			'invalid v punctuation'    => array( '[A-Z()]+', '123', true ),
			'PCRE anchors'             => array( '\Afoo\z', 'bar', true ),
			'PCRE quoted literals'     => array( '\Qhello\E', 'other', true ),
			'PCRE inline flags'        => array( '(?i)foo', 'bar', true ),
			'PCRE control verb'        => array( '(a)(*FAIL)', 'a', true ),
			'PCRE atomic group'        => array( '(?>a+)', 'b', true ),
			'PCRE possessive'          => array( 'a++', 'b', true ),
			'whitespace BOM'           => array( '\s+', "\u{FEFF}", true ),
			'whitespace NBSP'          => array( '\s+', "\u{00A0}", true ),
			'class whitespace BOM'     => array( '[A-Z\s]+', "B\u{FEFF}", true ),
			'negated whitespace BOM'   => array( '[^\s]+', "\u{FEFF}", false ),
			'non whitespace NBSP'      => array( '\S+', "\u{00A0}", false ),
			'non whitespace NEL'       => array( '\S+', "\u{0085}", true ),
			'class complement'         => array( '[\S]+', "\u{00A0}", true ),
			'non digit unicode'        => array( '\D+', 'é', true ),
			'non word unicode'         => array( '\W+', 'é', true ),
			'class non digits'         => array( '[\D]+', 'é', true ),
			'class non word'           => array( '[\W]+', 'é', true ),
			'digit remains ASCII'      => array( '\d+', '١', false ),
			'class digit ASCII'        => array( '[\d]+', '١', false ),
			'non digit Arabic'         => array( '\D+', '١', true ),
			'word remains ASCII'       => array( '\w+', 'é', false ),
			'dot CR'                   => array( '.', "\r", false ),
			'dot unicode separator'    => array( '.', "\u{2028}", false ),
			'vertical tab'             => array( '\v', "\u{000B}", true ),
			'vertical tab excludes LF' => array( '\v', "\n", false ),
			'escaped class hyphen'     => array( '[A-Z\-]+', 'AB-', true ),
			'escaped class mismatch'   => array( '[A-Z\-]+', '123', false ),
			'literal delimiters'       => array( 'a/b#c~d', 'a/b#c~e', false ),
			'portable pattern'         => array( '[A-Z]{2}-\d{3}', 'ab-123', false ),
			'full value anchor'        => array( '^\d+$', "123\n", false ),
			'malformed value'          => array( '[0-9]+', "12\xFF", false ),
			'malformed unknown value'  => array( '[A-Z&&[^AEIOU]]+', "B\xFF", false ),
		);
	}

	/** Browser-only custom patterns must retain length checks. */
	public function test_unknown_pattern_does_not_bypass_length_constraints() {
		$rules = array(
			'pattern'   => '[A-Z&&[^AEIOU]]+',
			'maxLength' => 2,
		);
		$this->assertWPError( Form_Field_Rules::check( 'BBB', $rules ) );
		$this->assertTrue( Form_Field_Rules::check( 'B', $rules ) );
	}
}
