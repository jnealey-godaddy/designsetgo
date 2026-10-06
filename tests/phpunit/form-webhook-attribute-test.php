<?php
/**
 * The webhook URL is a server-only form setting.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use WP_Block_Type_Registry;
use DesignSetGo\Abilities\Serializers\Form_Builder_Serializer;

/**
 * Webhook attribute test case.
 */
class Test_Form_Webhook_Attribute extends WP_UnitTestCase {

	public function test_attribute_is_registered_with_empty_default() {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'designsetgo/form-builder' );
		$this->assertSame( 'string', $type->attributes['webhookUrl']['type'] );
		$this->assertSame( '', $type->attributes['webhookUrl']['default'] );
	}

	public function test_serializer_never_emits_the_webhook_url() {
		$markup = Form_Builder_Serializer::wrapper(
			'designsetgo/form-builder',
			array(
				'formId'     => 'ser1',
				'webhookUrl' => 'https://hooks.example.com/secret-token',
			)
		);

		$this->assertIsArray( $markup );
		$this->assertStringNotContainsString( 'secret-token', implode( '', $markup ) );
	}
}
