<?php
/**
 * Container tagName must never reach markup unless it is an allowed element.
 *
 * Section, Row and Grid store their wrapper element as a `tagName` string in
 * the block comment. kses never inspects that comment as markup, so a
 * Contributor can store `img src=x onerror=...` there. Every PHP path that
 * builds `'<' . $tag` from it - the deactivation migrator and the ability
 * serializers - must replace a disallowed value with `div`.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Serializers\Serializer_Registry;

/**
 * Container tagName test.
 */
class DesignSetGo_Container_Tag_Name_Test extends WP_UnitTestCase {

	/**
	 * The payload from the advisory.
	 */
	private const PAYLOAD = 'img src=x onerror=alert(document.domain)';

	/**
	 * Container blocks that carry a tagName attribute.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function container_blocks(): array {
		return array(
			'section' => array( 'designsetgo/section' ),
			'row'     => array( 'designsetgo/row' ),
			'grid'    => array( 'designsetgo/grid' ),
		);
	}

	/**
	 * Disallowed tagName values.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function disallowed_tags(): array {
		return array(
			'attribute injection' => array( self::PAYLOAD ),
			'script element'      => array( 'script' ),
			'iframe element'      => array( 'iframe' ),
			'closing bracket'     => array( 'div><img src=x onerror=alert(1)' ),
			'wrong case'          => array( 'SECTION' ),
			'empty string'        => array( '' ),
			'array'               => array( array( 'div' ) ),
		);
	}

	/**
	 * Allowed values pass through unchanged.
	 */
	public function test_allowed_tags_pass_through(): void {
		foreach ( array( 'div', 'section', 'article', 'aside', 'header', 'footer', 'main' ) as $tag ) {
			$this->assertSame( $tag, designsetgo_sanitize_container_tag( $tag ) );
		}
	}

	/**
	 * Anything else becomes the fallback.
	 *
	 * @dataProvider disallowed_tags
	 *
	 * @param mixed $tag Stored tagName value.
	 */
	public function test_disallowed_tags_fall_back( $tag ): void {
		$this->assertSame( 'div', designsetgo_sanitize_container_tag( $tag ) );
		$this->assertSame( '', designsetgo_sanitize_container_tag( $tag, '' ) );
	}

	/**
	 * The helper's list and each block.json enum must not drift apart.
	 *
	 * @dataProvider container_blocks
	 *
	 * @param string $block_name Block name.
	 */
	public function test_block_json_enum_matches_allowlist( string $block_name ): void {
		$block = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		$this->assertNotNull( $block, "{$block_name} is not registered." );

		$enum = $block->attributes['tagName']['enum'] ?? array();
		$this->assertNotEmpty( $enum, "{$block_name} tagName declares no enum." );

		foreach ( $enum as $tag ) {
			$this->assertSame( $tag, designsetgo_sanitize_container_tag( $tag, '' ), "{$block_name} enum allows {$tag}, the helper does not." );
		}
		$this->assertSame( 'div', designsetgo_sanitize_container_tag( self::PAYLOAD ) );
	}

	/**
	 * The ability serializers never emit a disallowed tag.
	 *
	 * @dataProvider container_blocks
	 *
	 * @param string $block_name Block name.
	 */
	public function test_serializer_wrapper_replaces_payload_with_div( string $block_name ): void {
		$wrapper = Serializer_Registry::wrapper( $block_name, array( 'tagName' => self::PAYLOAD ) );
		$this->assertIsArray( $wrapper );

		$markup = $wrapper['opening'] . $wrapper['closing'];
		$this->assertStringNotContainsString( 'onerror', $markup );
		$this->assertStringNotContainsString( '<img', $markup );
		$this->assertStringStartsWith( '<div class="wp-block-' . str_replace( '/', '-', $block_name ), $wrapper['opening'] );
	}

	/**
	 * An allowed tag still reaches the serializer's markup.
	 *
	 * @dataProvider container_blocks
	 *
	 * @param string $block_name Block name.
	 */
	public function test_serializer_wrapper_keeps_allowed_tag( string $block_name ): void {
		$wrapper = Serializer_Registry::wrapper( $block_name, array( 'tagName' => 'article' ) );
		$this->assertIsArray( $wrapper );
		$this->assertStringStartsWith( '<article ', $wrapper['opening'] );
		$this->assertStringEndsWith( '</article>', $wrapper['closing'] );
	}

	/**
	 * The migrator's core/group builder never emits a disallowed tag, and
	 * never carries one into the converted block's attributes.
	 *
	 * @dataProvider container_blocks
	 *
	 * @param string $block_name Block name.
	 */
	public function test_migrator_drops_payload_from_group( string $block_name ): void {
		require_once DESIGNSETGO_PATH . 'includes/admin/class-block-migrator.php';

		$block = array(
			'blockName'    => $block_name,
			'attrs'        => array( 'tagName' => self::PAYLOAD ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		$convert = new ReflectionMethod( \DesignSetGo\Admin\Block_Migrator::class, 'convert_blocks_recursive' );
		$convert->setAccessible( true );
		$converted = $convert->invoke( new \DesignSetGo\Admin\Block_Migrator(), array( $block ) );
		$markup    = serialize_blocks( $converted );

		$this->assertSame( 'core/group', $converted[0]['blockName'] );
		$this->assertArrayNotHasKey( 'tagName', $converted[0]['attrs'] );
		$this->assertStringNotContainsString( 'onerror', $markup );
		$this->assertStringNotContainsString( '<img', $markup );
		$this->assertStringContainsString( '<div class="wp-block-group', $markup );
	}
}
