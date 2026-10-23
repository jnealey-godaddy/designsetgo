<?php
/**
 * Tests for configure-custom-css and position-based delete-block.
 *
 * Both abilities reported success (or a bare "not found") in production while
 * leaving the page unchanged: configure-custom-css wrote attributes nothing
 * renders, and delete-block's position ignored nested blocks.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Block_Inserter;
use DesignSetGo\Abilities\Configurators\Configure_Custom_CSS;
use DesignSetGo\Abilities\Configurators\Delete_Block;
use DesignSetGo\Abilities\Configurators\Update_Block;

/**
 * Custom CSS and delete-block ability tests.
 */
class Abilities_Custom_Css_Delete_Test extends WP_UnitTestCase {

	/**
	 * Group holding a paragraph, a heading and a second paragraph.
	 */
	private const NESTED_CONTENT = '<!-- wp:group --><div class="wp-block-group">'
		. '<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->'
		. '<!-- wp:heading --><h2 class="wp-block-heading">H</h2><!-- /wp:heading -->'
		. '<!-- wp:paragraph --><p>B</p><!-- /wp:paragraph -->'
		. '</div><!-- /wp:group -->';

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * Create a post with block content.
	 *
	 * @param string $content Block content string.
	 * @return int Post ID.
	 */
	private function create_block_post( string $content ): int {
		return self::factory()->post->create(
			array(
				'post_content' => $content,
				'post_status'  => 'publish',
			)
		);
	}

	/**
	 * Parsed top-level blocks of a post, empty (whitespace) blocks dropped.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function saved_blocks( int $post_id ): array {
		return array_values(
			array_filter(
				parse_blocks( get_post( $post_id )->post_content ),
				static function ( $block ) {
					return ! empty( $block['blockName'] );
				}
			)
		);
	}

	/**
	 * Configure the group block's custom CSS.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $css     CSS input.
	 * @return mixed Ability result.
	 */
	private function configure_group_css( int $post_id, array $css ) {
		return ( new Configure_Custom_CSS() )->execute(
			array(
				'post_id'    => $post_id,
				'block_name' => 'core/group',
				'css'        => $css,
			)
		);
	}

	// -------------------------------------------------------------------------
	// configure-custom-css
	// -------------------------------------------------------------------------

	/**
	 * Breakpoints are folded into dsgoCustomCSS, the one attribute rendered.
	 */
	public function test_configure_custom_css_writes_the_rendered_attribute(): void {
		$post_id = $this->create_block_post( self::NESTED_CONTENT );

		$result = $this->configure_group_css(
			$post_id,
			array(
				'enabled' => true,
				'desktop' => 'selector { color: red; }',
				'tablet'  => 'selector { color: green; }',
				'mobile'  => 'selector { color: blue; }',
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );

		$attrs = $this->saved_blocks( $post_id )[0]['attrs'];
		$css   = $attrs['dsgoCustomCSS'];

		$this->assertStringContainsString( 'color: red', $css );
		$this->assertStringContainsString( '@media (min-width: 768px) and (max-width: 1023px){', $css );
		$this->assertStringContainsString( '@media (max-width: 767px){', $css );
		$this->assertStringContainsString( 'color: blue', $css );
		foreach ( array( 'dsgoCustomCss', 'dsgoCustomCssEnabled', 'dsgoCustomCssTablet', 'dsgoCustomCssMobile' ) as $unrendered ) {
			$this->assertArrayNotHasKey( $unrendered, $attrs );
		}
	}

	/**
	 * The wrapper carries the class the renderer scopes the CSS to.
	 */
	public function test_configure_custom_css_adds_scoping_class_to_wrapper(): void {
		$post_id = $this->create_block_post( self::NESTED_CONTENT );

		$this->configure_group_css( $post_id, array( 'desktop' => 'selector { color: red; }' ) );

		$block = $this->saved_blocks( $post_id )[0];
		$class = Block_Inserter::custom_css_class( 'core/group', $block['attrs']['dsgoCustomCSS'] );

		$this->assertNotNull( $class );
		$this->assertStringContainsString( 'class="wp-block-group ' . $class . '"', $block['innerHTML'] );
	}

	/**
	 * Replacing the CSS swaps the class instead of stacking a second one.
	 */
	public function test_reconfiguring_custom_css_replaces_the_class(): void {
		$post_id = $this->create_block_post( self::NESTED_CONTENT );

		$this->configure_group_css( $post_id, array( 'desktop' => 'selector { color: red; }' ) );
		$this->configure_group_css( $post_id, array( 'desktop' => 'selector { color: blue; }' ) );

		$block = $this->saved_blocks( $post_id )[0];
		$class = Block_Inserter::custom_css_class( 'core/group', $block['attrs']['dsgoCustomCSS'] );

		$this->assertSame( 1, preg_match_all( '/dsgo-custom-css-[a-z0-9]+/', $block['innerHTML'] ) );
		$this->assertStringContainsString( $class, $block['innerHTML'] );
	}

	/**
	 * Disabling clears the CSS and removes the class.
	 */
	public function test_disabling_custom_css_clears_it(): void {
		$post_id = $this->create_block_post( self::NESTED_CONTENT );

		$this->configure_group_css( $post_id, array( 'desktop' => 'selector { color: red; }' ) );
		$this->configure_group_css( $post_id, array( 'enabled' => false ) );

		$block = $this->saved_blocks( $post_id )[0];

		$this->assertSame( '', $block['attrs']['dsgoCustomCSS'] );
		$this->assertStringNotContainsString( 'dsgo-custom-css-', get_post( $post_id )->post_content );
		$this->assertStringContainsString( '<div class="wp-block-group">', $block['innerHTML'] );
	}

	/**
	 * A wrapper with no class attribute gets one.
	 */
	public function test_update_block_adds_custom_css_class_to_classless_wrapper(): void {
		$post_id = $this->create_block_post( '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->' );

		$result = ( new Update_Block() )->execute(
			array(
				'post_id'     => $post_id,
				'block_index' => 0,
				'block_name'  => 'core/paragraph',
				'attributes'  => array( 'dsgoCustomCSS' => 'selector { color: red; }' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );

		$block = $this->saved_blocks( $post_id )[0];
		$class = Block_Inserter::custom_css_class( 'core/paragraph', $block['attrs']['dsgoCustomCSS'] );

		$this->assertStringContainsString( '<p class="' . $class . '">Text</p>', get_post( $post_id )->post_content );
	}

	// -------------------------------------------------------------------------
	// delete-block
	// -------------------------------------------------------------------------

	/**
	 * Position counts nested blocks, so a block inside a group can be targeted.
	 */
	public function test_delete_block_position_reaches_nested_blocks(): void {
		$post_id = $this->create_block_post( self::NESTED_CONTENT );

		$result = ( new Delete_Block() )->execute(
			array(
				'post_id'    => $post_id,
				'block_name' => 'core/paragraph',
				'position'   => 1,
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['deleted_count'] );
		$this->assertSame(
			'<!-- wp:group --><div class="wp-block-group">'
				. '<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->'
				. '<!-- wp:heading --><h2 class="wp-block-heading">H</h2><!-- /wp:heading -->'
				. '</div><!-- /wp:group -->',
			get_post( $post_id )->post_content
		);
	}

	/**
	 * A position past the last match still reports not found.
	 */
	public function test_delete_block_position_out_of_range_is_not_found(): void {
		$post_id = $this->create_block_post( self::NESTED_CONTENT );

		$result = ( new Delete_Block() )->execute(
			array(
				'post_id'    => $post_id,
				'block_name' => 'core/paragraph',
				'position'   => 2,
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'designsetgo_block_not_found', $result->get_error_code() );
		$this->assertSame( self::NESTED_CONTENT, get_post( $post_id )->post_content );
	}

	/**
	 * Same-name blocks nested in a match are not counted, matching get-post-blocks.
	 */
	public function test_delete_block_position_skips_matches_nested_in_a_match(): void {
		$content = '<!-- wp:group --><div class="wp-block-group">'
			. '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Inner</p><!-- /wp:paragraph --></div><!-- /wp:group -->'
			. '</div><!-- /wp:group -->'
			. '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Second</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
		$post_id = $this->create_block_post( $content );

		( new Delete_Block() )->execute(
			array(
				'post_id'    => $post_id,
				'block_name' => 'core/group',
				'position'   => 1,
			)
		);

		$saved = get_post( $post_id )->post_content;
		$this->assertStringContainsString( 'Inner', $saved );
		$this->assertStringNotContainsString( 'Second', $saved );
	}

	/**
	 * Deleting a nested block also drops its innerContent placeholder.
	 */
	public function test_delete_first_nested_block_keeps_inner_content_in_step(): void {
		$post_id = $this->create_block_post( self::NESTED_CONTENT );

		( new Delete_Block() )->execute(
			array(
				'post_id'    => $post_id,
				'block_name' => 'core/heading',
			)
		);

		$group = $this->saved_blocks( $post_id )[0];

		$this->assertCount( 2, $group['innerBlocks'] );
		$this->assertCount( 2, array_filter( $group['innerContent'], 'is_null' ) );
		$this->assertStringNotContainsString( '<h2', get_post( $post_id )->post_content );
	}
}
