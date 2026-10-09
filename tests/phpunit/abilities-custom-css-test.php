<?php
/**
 * The custom CSS ability must write the attribute used by editor and frontend.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Configurators\Configure_Custom_CSS;
use DesignSetGo\Abilities\Inserters\Add_Blocks;

/** Regression coverage for abilities custom css test. */
class Abilities_Custom_CSS_Test extends WP_UnitTestCase {
	/** @var int Target test post. */
	private $post_id;

	/** Set up. */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->post_id = self::factory()->post->create( array( 'post_content' => '' ) );
		$result        = ( new Add_Blocks() )->run(
			array(
				'post_id' => $this->post_id,
				'blocks'  => array(
					array(
						'block_name' => 'core/paragraph',
						'attributes' => array(
							'content'       => 'One',
							'dsgoCustomCSS' => 'selector { color: purple; }',
						),
					),
					array(
						'block_name' => 'core/paragraph',
						'attributes' => array(
							'content'       => 'Two',
							'dsgoCustomCSS' => 'selector { color: orange; }',
						),
					),
				),
			)
		);
		$this->assertTrue( $result['success'] );
	}

	/** Configure. */
	private function configure( array $css, bool $all = false ) {
		return ( new Configure_Custom_CSS() )->run(
			array(
				'post_id'    => $this->post_id,
				'block_name' => 'core/paragraph',
				'css'        => $css,
				'update_all' => $all,
			)
		);
	}

	/** Css. */
	private function css( int $index = 0 ): string {
		$blocks = parse_blocks( get_post_field( 'post_content', $this->post_id ) );
		return $blocks[ $index ]['attrs']['dsgoCustomCSS'] ?? '';
	}

	/** Test breakpoints use canonical attribute and partial updates preserve rules. */
	public function test_breakpoints_use_canonical_attribute_and_partial_updates_preserve_rules(): void {
		$result = $this->configure(
			array(
				'desktop' => 'selector { color: red; }',
				'tablet'  => 'selector { color: blue; }',
				'mobile'  => 'selector { color: green; }',
			)
		);
		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'color: red', $this->css() );
		$this->assertStringContainsString( '@media (max-width: 1024px)', $this->css() );
		$this->assertStringContainsString( '@media (max-width: 767px)', $this->css() );
		$this->configure( array( 'mobile' => 'selector { color: black; }' ) );
		$this->assertStringContainsString( 'color: red', $this->css() );
		$this->assertStringContainsString( 'color: blue', $this->css() );
		$this->assertStringContainsString( 'color: black', $this->css() );
		$this->assertStringNotContainsString( 'color: green', $this->css() );
		$this->assertSame( 'selector { color: orange; }', $this->css( 1 ) );
	}

	/** Test clear and disable. */
	public function test_clear_and_disable(): void {
		$this->configure(
			array(
				'desktop' => 'selector { color: red; }',
				'tablet'  => 'selector { color: blue; }',
			)
		);
		$this->configure( array( 'tablet' => '' ) );
		$this->assertStringNotContainsString( 'color: blue', $this->css() );
		$this->assertStringContainsString( 'color: red', $this->css() );
		$this->configure( array( 'enabled' => false ) );
		$this->assertSame( '', $this->css() );
	}

	/** Test all matches preserve each blocks existing css. */
	public function test_all_matches_preserve_each_blocks_existing_css(): void {
		$this->configure( array( 'mobile' => 'selector { display: none; }' ), true );
		$this->assertStringContainsString( 'color: purple', $this->css() );
		$this->assertStringContainsString( 'color: orange', $this->css( 1 ) );
		$this->assertStringContainsString( 'display: none', $this->css( 1 ) );
		$before = $this->css();
		$this->configure( array( 'mobile' => 'selector { display: none; }' ), true );
		$this->assertSame( $before, $this->css() );
	}

	/** Test permission failure does not change content. */
	public function test_permission_failure_does_not_change_content(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$before = get_post_field( 'post_content', $this->post_id );
		$result = $this->configure( array( 'desktop' => 'selector { color: red; }' ) );
		$this->assertWPError( $result );
		$this->assertSame( $before, get_post_field( 'post_content', $this->post_id ) );
	}
	/** Saved selectors and emitted CSS must agree, including Unicode and clear. */
	/** Test updated selector class matches emitted styles. */
	public function test_updated_selector_class_matches_emitted_styles(): void {
		$this->configure( array( 'desktop' => 'selector { color: #123456; } /* é😀 */' ) );
		$blocks   = parse_blocks( get_post_field( 'post_content', $this->post_id ) );
		$expected = \DesignSetGo\Abilities\Block_Inserter::build_block_markup(
			'core/paragraph',
			array(
				'content'       => 'One',
				'dsgoCustomCSS' => $this->css(),
			)
		);
		$this->assertSame( $expected, serialize_block( $blocks[0] ) );
		preg_match( '/dsgo-custom-css-[a-z0-9]+/', $blocks[0]['innerHTML'], $match );
		$this->assertNotEmpty( $match );
		$renderer = new \DesignSetGo\Custom_CSS_Renderer();
		$renderer->collect_custom_css( $blocks[0]['innerHTML'], $blocks[0] );
		ob_start();
		$renderer->render_custom_css();
		$output = ob_get_clean();
		$this->assertStringContainsString( '.' . $match[0] . ' { color: #123456;', $output );
		$this->configure( array( 'enabled' => false ) );
		$this->assertStringNotContainsString( 'dsgo-custom-css-', serialize_block( parse_blocks( get_post_field( 'post_content', $this->post_id ) )[0] ) );
	}

	/** A block with no CSS initially must acquire its selector class. */
	/** Test adding css to unstyled core block. */
	public function test_adding_css_to_unstyled_core_block(): void {
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => '<!-- wp:paragraph --><p>Plain</p><!-- /wp:paragraph -->',
			)
		);
		$this->configure( array( 'desktop' => 'selector { opacity: .5; }' ) );
		$this->assertStringContainsString( 'dsgo-custom-css-', get_post_field( 'post_content', $this->post_id ) );
	}

	/** Dynamic blocks acquire the same selector at render time. */
	/** Test dynamic render attaches css class. */
	public function test_dynamic_render_attaches_css_class(): void {
		$css      = 'selector { color: red; }';
		$block    = array(
			'blockName' => 'designsetgo/form-email-field',
			'attrs'     => array( 'dsgoCustomCSS' => $css ),
		);
		$renderer = new \DesignSetGo\Custom_CSS_Renderer();
		$html     = $renderer->collect_custom_css( '<div class="dsgo-field">Email</div>', $block );
		$class    = 'dsgo-custom-css-' . \DesignSetGo\Abilities\Serializers\Serializer_Support::js_hash_code( $css . $block['blockName'] );
		$this->assertStringContainsString( $class, $html );
	}

	/** Excluded blocks must not receive classes absent from their save filter. */
	public function test_excluded_block_refused_without_mutation(): void {
		$before = get_post_field( 'post_content', $this->post_id );
		$result = ( new Configure_Custom_CSS() )->run(
			array(
				'post_id'    => $this->post_id,
				'block_name' => 'core/code',
				'css'        => array( 'desktop' => 'selector { color: red; }' ),
			)
		);
		$this->assertWPError( $result );
		$this->assertSame( $before, get_post_field( 'post_content', $this->post_id ) );
	}

	/** Wrapperless dynamic output must retain its child's own CSS selector. */
	public function test_runtime_parent_preserves_child_selector(): void {
		$renderer = new \DesignSetGo\Custom_CSS_Renderer();
		$child    = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array( 'dsgoCustomCSS' => 'selector { color: red; }' ),
		);
		$html     = $renderer->collect_custom_css( '<p>Child</p>', $child );
		preg_match( '/dsgo-custom-css-[a-z0-9]+/', $html, $match );
		$parent = array(
			'blockName' => 'core/block',
			'attrs'     => array( 'dsgoCustomCSS' => 'selector { font-weight: 700; }' ),
		);
		$html   = $renderer->collect_custom_css( $html, $parent );
		$this->assertStringContainsString( $match[0], $html );
		$this->assertSame( 2, preg_match_all( '/dsgo-custom-css-[a-z0-9]+/', $html ) );
	}

	/** Authors cannot persist custom CSS; refusal must precede markup changes. */
	public function test_edit_css_permission_required_before_mutation(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_update_post(
			array(
				'ID'          => $this->post_id,
				'post_author' => $user_id,
			)
		);
		wp_set_current_user( $user_id );
		$this->assertTrue( current_user_can( 'edit_post', $this->post_id ) );
		$this->assertFalse( current_user_can( 'edit_css' ) );
		$before = get_post_field( 'post_content', $this->post_id );
		$this->assertFalse( ( new Configure_Custom_CSS() )->check_permission_callback() );
		$this->assertWPError( $this->configure( array( 'desktop' => 'selector { color: red; }' ) ) );
		$this->assertSame( $before, get_post_field( 'post_content', $this->post_id ) );
	}
}
