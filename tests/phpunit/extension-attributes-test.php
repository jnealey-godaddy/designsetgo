<?php
/**
 * Test Extension Attributes
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

/**
 * Tests for Extension_Attributes class.
 */
class Test_Extension_Attributes extends WP_UnitTestCase {

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
	 * Test that the class exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertInstanceOf( \DesignSetGo\Extension_Attributes::class, $this->extension_attrs );
	}

	/**
	 * Test that extension config files exist in the expected directory.
	 */
	public function test_config_files_exist() {
		$config_dir = DESIGNSETGO_PATH . 'includes/extension-configs/';
		$this->assertDirectoryExists( $config_dir );

		$files = glob( $config_dir . '*.php' );
		$this->assertNotEmpty( $files, 'Extension config directory should contain PHP files.' );
	}

	/**
	 * Test that each config file returns a valid schema.
	 */
	public function test_config_files_return_valid_schemas() {
		$config_dir = DESIGNSETGO_PATH . 'includes/extension-configs/';
		$files      = glob( $config_dir . '*.php' );

		foreach ( $files as $file ) {
			$name   = basename( $file, '.php' );
			$config = include $file;

			$this->assertIsArray( $config, "Config file {$name}.php should return an array." );
			$this->assertArrayHasKey( 'blocks', $config, "Config file {$name}.php missing 'blocks' key." );
			$this->assertArrayHasKey( 'exclude', $config, "Config file {$name}.php missing 'exclude' key." );
			$this->assertArrayHasKey( 'attributes', $config, "Config file {$name}.php missing 'attributes' key." );
			$this->assertIsArray( $config['attributes'], "Config file {$name}.php 'attributes' should be an array." );
			$this->assertNotEmpty( $config['attributes'], "Config file {$name}.php 'attributes' should not be empty." );

			// Validate each attribute has type and default.
			foreach ( $config['attributes'] as $attr_name => $attr_schema ) {
				$this->assertArrayHasKey( 'type', $attr_schema, "Attribute {$attr_name} in {$name}.php missing 'type'." );
				$this->assertArrayHasKey( 'default', $attr_schema, "Attribute {$attr_name} in {$name}.php missing 'default'." );
			}
		}
	}

	/**
	 * Test that extension attributes are injected into a block with 'all' targeting.
	 */
	public function test_injects_attributes_for_all_blocks() {
		$args = array(
			'attributes' => array(
				'existingAttr' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
		);

		$result = $this->extension_attrs->inject_extension_attributes( $args, 'core/paragraph' );

		// Responsive extension targets 'all', so dsgoHideOnDesktop should be present.
		$this->assertArrayHasKey( 'dsgoHideOnDesktop', $result['attributes'] );
		$this->assertArrayHasKey( 'dsgoHideOnTablet', $result['attributes'] );
		$this->assertArrayHasKey( 'dsgoHideOnMobile', $result['attributes'] );

		// Existing attribute should still be there.
		$this->assertArrayHasKey( 'existingAttr', $result['attributes'] );
	}

	/**
	 * Test that extension attributes are injected for explicitly targeted blocks.
	 */
	public function test_injects_attributes_for_targeted_blocks() {
		$args   = array( 'attributes' => array() );
		$result = $this->extension_attrs->inject_extension_attributes( $args, 'core/template-part' );

		// Sticky header controls targets core/template-part explicitly.
		$this->assertArrayHasKey( 'dsgoStickyEnabled', $result['attributes'] );
		$this->assertArrayHasKey( 'dsgoStickyShadow', $result['attributes'] );
		$this->assertArrayHasKey( 'dsgoStickyShrink', $result['attributes'] );
		$this->assertArrayHasKey( 'dsgoStickyShrinkAmount', $result['attributes'] );
		$this->assertArrayHasKey( 'dsgoStickyHideOnScroll', $result['attributes'] );
		$this->assertArrayHasKey( 'dsgoStickyBackground', $result['attributes'] );
	}

	/**
	 * Test that sticky header attributes are NOT injected into non-template-part blocks.
	 */
	public function test_sticky_header_not_injected_into_non_target() {
		$args   = array( 'attributes' => array() );
		$result = $this->extension_attrs->inject_extension_attributes( $args, 'core/paragraph' );

		$this->assertArrayNotHasKey( 'dsgoStickyEnabled', $result['attributes'] );
	}

	/**
	 * Test that exact exclusions are respected.
	 */
	public function test_exact_exclusions() {
		$args   = array( 'attributes' => array() );
		$result = $this->extension_attrs->inject_extension_attributes( $args, 'core/freeform' );

		// block-animations excludes core/freeform, so animation attrs should not be present.
		$this->assertArrayNotHasKey( 'dsgoAnimationEnabled', $result['attributes'] );
	}

	/**
	 * Test that namespace wildcard exclusions are respected.
	 */
	public function test_namespace_wildcard_exclusions() {
		$args   = array( 'attributes' => array() );
		$result = $this->extension_attrs->inject_extension_attributes( $args, 'core-embed/youtube' );

		// block-animations excludes 'core-embed/*'.
		$this->assertArrayNotHasKey( 'dsgoAnimationEnabled', $result['attributes'] );
	}

	/**
	 * Test that existing block.json attributes are not overwritten.
	 */
	public function test_does_not_overwrite_existing_attributes() {
		$args = array(
			'attributes' => array(
				'dsgoHideOnDesktop' => array(
					'type'    => 'string',
					'default' => 'custom-value',
				),
			),
		);

		$result = $this->extension_attrs->inject_extension_attributes( $args, 'core/paragraph' );

		// Should keep the original definition, not the extension one.
		$this->assertSame( 'string', $result['attributes']['dsgoHideOnDesktop']['type'] );
		$this->assertSame( 'custom-value', $result['attributes']['dsgoHideOnDesktop']['default'] );
	}

	/**
	 * Test that args without attributes key are handled gracefully.
	 */
	public function test_handles_missing_attributes_key() {
		$args   = array();
		$result = $this->extension_attrs->inject_extension_attributes( $args, 'core/paragraph' );

		$this->assertIsArray( $result['attributes'] );
		$this->assertArrayHasKey( 'dsgoHideOnDesktop', $result['attributes'] );
	}

	/**
	 * Test that text-reveal attributes are only injected for targeted blocks.
	 */
	public function test_text_reveal_only_for_text_blocks() {
		$paragraph_result = $this->extension_attrs->inject_extension_attributes(
			array( 'attributes' => array() ),
			'core/paragraph'
		);
		$heading_result   = $this->extension_attrs->inject_extension_attributes(
			array( 'attributes' => array() ),
			'core/heading'
		);
		$image_result     = $this->extension_attrs->inject_extension_attributes(
			array( 'attributes' => array() ),
			'core/image'
		);

		$this->assertArrayHasKey( 'dsgoTextRevealEnabled', $paragraph_result['attributes'] );
		$this->assertArrayHasKey( 'dsgoTextRevealEnabled', $heading_result['attributes'] );
		$this->assertArrayNotHasKey( 'dsgoTextRevealEnabled', $image_result['attributes'] );
	}

	/**
	 * Test that custom-css excludes core/html and core/code.
	 */
	public function test_custom_css_exclusions() {
		$html_result = $this->extension_attrs->inject_extension_attributes(
			array( 'attributes' => array() ),
			'core/html'
		);
		$code_result = $this->extension_attrs->inject_extension_attributes(
			array( 'attributes' => array() ),
			'core/code'
		);

		$this->assertArrayNotHasKey( 'dsgoCustomCSS', $html_result['attributes'] );
		$this->assertArrayNotHasKey( 'dsgoCustomCSS', $code_result['attributes'] );
	}

	/**
	 * Test that max-width excludes container blocks.
	 */
	public function test_max_width_excludes_containers() {
		$section_result = $this->extension_attrs->inject_extension_attributes(
			array( 'attributes' => array() ),
			'designsetgo/section'
		);

		$this->assertArrayNotHasKey( 'dsgoMaxWidth', $section_result['attributes'] );
	}

	/**
	 * Test the total number of extension configs matches expectations.
	 */
	public function test_expected_extension_count() {
		$config_dir = DESIGNSETGO_PATH . 'includes/extension-configs/';
		$files      = glob( $config_dir . '*.php' );

		// 19 extensions: background-video, block-animations, clickable-group,
		// custom-css, expanding-background, grid-mobile-order, grid-span,
		// interactions, max-width, responsive, reveal-container, reveal-control,
		// schema, sticky-header-controls, style-binding, svg-patterns,
		// text-reveal, vertical-parallax, visibility.
		$this->assertCount( 19, $files, 'Should have 19 extension config files.' );
	}

	/**
	 * Every attribute a JS extension adds must also be known to PHP.
	 *
	 * Core's block-renderer REST route validates the payload with
	 * `additionalProperties: false`, and ServerSideRender expands the payload
	 * to every client-registered attribute. An attribute registered only in JS
	 * therefore makes every server-rendered preview fail with
	 * "Invalid parameter(s): attributes".
	 */
	public function test_js_only_extension_attributes_are_mirrored_server_side() {
		$args = apply_filters(
			'register_block_type_args',
			array( 'attributes' => array() ),
			'designsetgo/chart'
		);

		$this->assertArrayHasKey( 'dsgoVisibility', $args['attributes'] );
		$this->assertArrayHasKey( 'dsgoStyleBinding', $args['attributes'] );
	}

	/**
	 * The blocks those two extensions skip in JS are skipped in PHP too.
	 */
	public function test_visibility_and_style_binding_respect_their_blocklist() {
		$args = apply_filters(
			'register_block_type_args',
			array( 'attributes' => array() ),
			'core/template-part'
		);

		$this->assertArrayNotHasKey( 'dsgoVisibility', $args['attributes'] );
		$this->assertArrayNotHasKey( 'dsgoStyleBinding', $args['attributes'] );
	}

	/**
	 * Reads an extension's own exclude list from its config file.
	 *
	 * The getter is the single source the animation-defaults injector reads
	 * instead of hardcoding core/freeform + core-embed/*.
	 */
	public function test_get_extension_exclusions_returns_config_list() {
		$exclusions = \DesignSetGo\Extension_Attributes::get_extension_exclusions( 'block-animations' );
		$this->assertContains( 'core/freeform', $exclusions );
		$this->assertContains( 'core-embed/*', $exclusions );

		// An unknown extension slug yields an empty list, not a warning.
		$this->assertSame( array(), \DesignSetGo\Extension_Attributes::get_extension_exclusions( 'does-not-exist' ) );
	}
}
