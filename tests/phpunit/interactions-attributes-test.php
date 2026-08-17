<?php
/**
 * Interaction layer attribute registration tests.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

/**
 * Tests that the interactions extension config mirrors the JS attribute.
 */
class Test_Interactions_Attributes extends WP_UnitTestCase {

	/**
	 * Extension attributes instance.
	 *
	 * @var \DesignSetGo\Extension_Attributes
	 */
	private $extension_attrs;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		$this->extension_attrs = new \DesignSetGo\Extension_Attributes();
	}

	/**
	 * The config file exists where the loader looks for it.
	 */
	public function test_config_file_exists() {
		$this->assertFileExists(
			DESIGNSETGO_PATH . 'includes/extension-configs/interactions.php'
		);
	}

	/**
	 * A normal block receives the attribute as an array.
	 */
	public function test_normal_block_has_interactions_attribute() {
		$result = $this->extension_attrs->inject_extension_attributes(
			array( 'attributes' => array() ),
			'core/group'
		);

		$this->assertArrayHasKey( 'dsgoInteractions', $result['attributes'] );
		$this->assertSame( 'array', $result['attributes']['dsgoInteractions']['type'] );
	}

	/**
	 * The default is an empty array, matching the JS registration.
	 */
	public function test_default_is_an_empty_array() {
		$result = $this->extension_attrs->inject_extension_attributes(
			array( 'attributes' => array() ),
			'core/group'
		);

		$this->assertSame(
			array(),
			$result['attributes']['dsgoInteractions']['default']
		);
	}

	/**
	 * Classic-editor blocks are excluded, matching the JS guard.
	 */
	public function test_freeform_is_excluded() {
		$result = $this->extension_attrs->inject_extension_attributes(
			array( 'attributes' => array() ),
			'core/freeform'
		);

		$this->assertArrayNotHasKey( 'dsgoInteractions', $result['attributes'] );
	}

	/**
	 * Legacy embed blocks are excluded, matching the JS guard.
	 */
	public function test_core_embed_is_excluded() {
		$result = $this->extension_attrs->inject_extension_attributes(
			array( 'attributes' => array() ),
			'core-embed/youtube'
		);

		$this->assertArrayNotHasKey( 'dsgoInteractions', $result['attributes'] );
	}

	/**
	 * A block that already declares the attribute keeps its own schema.
	 */
	public function test_existing_attribute_is_not_overwritten() {
		$result = $this->extension_attrs->inject_extension_attributes(
			array(
				'attributes' => array(
					'dsgoInteractions' => array(
						'type'    => 'array',
						'default' => array( 'preset' ),
					),
				),
			),
			'core/group'
		);

		$this->assertSame(
			array( 'preset' ),
			$result['attributes']['dsgoInteractions']['default']
		);
	}
}
