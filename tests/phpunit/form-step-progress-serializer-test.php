<?php
/**
 * Form step progress attribute: serializer parity with save.js.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use DesignSetGo\Abilities\Serializers\Form_Builder_Serializer;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * Form step progress test case.
 */
class Test_Form_Step_Progress extends WP_UnitTestCase {

	/**
	 * Opening markup for the given stepProgress value.
	 *
	 * @param array $extra Extra attributes.
	 * @return string
	 */
	private function opening( array $extra ) {
		$out = Form_Builder_Serializer::wrapper(
			'designsetgo/form-builder',
			array_merge( array( 'formId' => 'x', 'hasFields' => true ), $extra )
		);
		return $out['opening'];
	}

	public function test_bar_and_none_are_written() {
		$this->assertStringContainsString( 'data-dsgo-step-progress="bar"', $this->opening( array( 'stepProgress' => 'bar' ) ) );
		$this->assertStringContainsString( 'data-dsgo-step-progress="none"', $this->opening( array( 'stepProgress' => 'none' ) ) );
	}

	public function test_default_absent_and_invalid_are_omitted() {
		$this->assertStringNotContainsString( 'data-dsgo-step-progress', $this->opening( array( 'stepProgress' => 'steps' ) ) );
		$this->assertStringNotContainsString( 'data-dsgo-step-progress', $this->opening( array() ) );
		$this->assertStringNotContainsString( 'data-dsgo-step-progress', $this->opening( array( 'stepProgress' => '<x>' ) ) );
	}

	public function test_attribute_registered_with_enum_and_default() {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'designsetgo/form-builder' );
		$this->assertNotNull( $type );
		$attr = $type->attributes['stepProgress'];
		$this->assertSame( 'steps', $attr['default'] );
		$this->assertSame( array( 'steps', 'bar', 'none' ), $attr['enum'] );
	}
}
