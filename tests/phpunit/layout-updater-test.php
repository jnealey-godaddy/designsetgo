<?php
/** Precision layout updates preserve authored block markup.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Configurator;
use DesignSetGo\Abilities\Block_Inserter;
use DesignSetGo\Abilities\Configurators\Update_Block;
use DesignSetGo\Layout_Support;

/** Exercise the public updater's targeting and atomic validation paths. */
class Layout_Updater_Test extends WP_UnitTestCase {

	/** Install a permitted author for the content writes. */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/** A Grid fixture includes unknown comment attrs and sourced rich child HTML.
	 *
	 * @param string $client Stored client identifier.
	 * @return string Grid block markup.
	 */
	private function grid( string $client = 'first' ): string {
		$markup = Block_Inserter::build_block_markup(
			'designsetgo/grid',
			array(
				'tagName'              => 'section',
				'tabletColumnTemplate' => '2fr 1fr',
				'mobileColumnTemplate' => '1fr',
				'dsgoLayout'           => array( 'desktop' => array( 'width' => '90%' ) ),
			)
		);
		$blocks = parse_blocks( $markup );
		$blocks[0]['attrs']['clientId']      = $client;
		$blocks[0]['attrs']['authorCustom']  = array( 'keep' => 'exact' );
		$blocks[0]['innerBlocks']           = parse_blocks( '<!-- wp:paragraph {"className":"child-own"} --><p class="child-own" data-custom="kept">Care <em>begins</em> &amp; stays.</p><!-- /wp:paragraph -->' );
		$blocks[0]['innerContent']          = array( str_replace( '</div></section>', '', $blocks[0]['innerHTML'] ), null, '</div></section>' );
		$blocks[0]['innerContent'][0]       = str_replace( 'display:grid', '--author-note:kept;display:grid', $blocks[0]['innerContent'][0] );
		return serialize_blocks( $blocks );
	}

	/** Create a page without altering JSON escapes in block comments.
	 *
	 * @param string $content Authored block content.
	 * @return int Page ID.
	 */
	private function page( string $content ): int {
		return self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_content' => wp_slash( $content ),
			)
		);
	}

	/** Read the Grid's actual owning inner style.
	 *
	 * @param array $block Parsed Grid.
	 * @return string CSS on its inner wrapper.
	 */
	private function inner_style( array $block ): string {
		$processor = new WP_HTML_Tag_Processor( $block['innerHTML'] );
		$this->assertTrue( $processor->next_tag( array( 'class_name' => 'dsgo-grid__inner' ) ) );
		return (string) $processor->get_attribute( 'style' );
	}

	/** Index updates synchronize wrappers without reconstructing sourced children. */
	public function test_index_update_syncs_layout_hash_and_grid_templates(): void {
		$id     = $this->page( $this->grid() );
		$before = parse_blocks( get_post( $id )->post_content )[0];
		$layout = array( 'mobile' => array( 'gap' => '12px' ) );
		$result = ( new Update_Block() )->execute(
			array(
				'post_id'     => $id,
				'block_index' => 0,
				'attributes'  => array(
					'dsgoLayout' => $layout,
					'tabletColumnTemplate' => 'repeat(auto-fit, minmax(10rem, 1fr))',
					'mobileColumnTemplate' => '[start] 1fr [end]',
				),
			)
		);
		$this->assertIsArray( $result );
		$after = parse_blocks( get_post( $id )->post_content )[0];
		$this->assertStringContainsString( Layout_Support::class_name( 'designsetgo/grid', $layout ), $after['innerHTML'] );
		$this->assertStringNotContainsString( Layout_Support::class_name( 'designsetgo/grid', $before['attrs']['dsgoLayout'] ), $after['innerHTML'] );
		$this->assertStringContainsString( '--dsgo-grid-columns-tablet:repeat(auto-fit, minmax(10rem, 1fr))', $this->inner_style( $after ) );
		$this->assertStringContainsString( '--dsgo-grid-columns-mobile:[start] 1fr [end]', $this->inner_style( $after ) );
		$this->assertStringContainsString( '--author-note:kept', $this->inner_style( $after ) );
		$this->assertSame( $before['innerBlocks'], $after['innerBlocks'] );
		$this->assertSame( $before['attrs']['authorCustom'], $after['attrs']['authorCustom'] );
		$this->assertSame( null, $after['innerContent'][1] );
		$this->assertStringStartsWith( '<section ', $after['innerContent'][0] );
	}

	/** Client selection changes only that Grid, including removal of stale styling. */
	public function test_client_target_clears_only_the_selected_grid(): void {
		$id     = $this->page( $this->grid() . $this->grid( 'second' ) );
		$before = parse_blocks( get_post( $id )->post_content );
		$result = Block_Configurator::update_block_attributes(
			$id,
			'designsetgo/grid',
			array(
				'dsgoLayout' => array(),
				'tabletColumnTemplate' => '',
				'mobileColumnTemplate' => '',
			),
			'second'
		);
		$this->assertIsArray( $result );
		$after = parse_blocks( get_post( $id )->post_content );
		$this->assertSame( $before[0], $after[0] );
		$this->assertStringNotContainsString( 'dsgo-layout-', $after[1]['innerHTML'] );
		$this->assertStringNotContainsString( '--dsgo-grid-columns-', $this->inner_style( $after[1] ) );
		$this->assertSame( $before[1]['innerBlocks'], $after[1]['innerBlocks'] );
	}

	/** A late invalid transform prevents every content write. */
	public function test_update_all_is_atomic_when_a_later_patch_is_invalid(): void {
		$id      = $this->page( $this->grid() . $this->grid( 'second' ) );
		$content = get_post( $id )->post_content;
		$result  = Block_Configurator::transform_block_attributes(
			$id,
			'designsetgo/grid',
			static fn( $attrs ) => array( 'dsgoLayout' => array( 'mobile' => array( 'gap' => 'first' === $attrs['clientId'] ? '10px' : '10px;color:red' ) ) ),
			null,
			true
		);
		$this->assertWPError( $result );
		$this->assertSame( $content, get_post( $id )->post_content );
	}

	/** All matching roots receive the same valid layout hash. */
	public function test_name_update_all_syncs_every_matching_root(): void {
		$id     = $this->page( $this->grid() . $this->grid( 'second' ) );
		$layout = array( 'tablet' => array( 'gap' => '24px' ) );
		$result = Block_Configurator::update_block_attributes( $id, 'designsetgo/grid', array( 'dsgoLayout' => $layout ), null, true );
		$this->assertSame( 2, $result['updated_count'] );
		$this->assertSame( 2, substr_count( get_post( $id )->post_content, Layout_Support::class_name( 'designsetgo/grid', $layout ) ) );
	}

	/** Raw invalid values must not become valid by generic string sanitization.
	 *
	 * @dataProvider invalid_patches
	 * @param array $patch Invalid authored patch.
	 */
	public function test_unsafe_or_wrong_type_patch_refuses_without_writing( array $patch ): void {
		foreach ( array( array( 'block_index' => 0 ), array( 'block_name' => 'designsetgo/grid' ) ) as $target ) {
			$id      = $this->page( $this->grid() );
			$content = get_post( $id )->post_content;
			$result  = ( new Update_Block() )->execute(
				array_merge(
					array(
						'post_id' => $id,
						'attributes' => $patch,
					),
					$target
				)
			);
			$this->assertWPError( $result );
			$this->assertSame( $content, get_post( $id )->post_content );
		}
	}

	/** Invalid values cover raw HTML, CSS injection, shapes and types.
	 *
	 * @return array Test cases.
	 */
	public function invalid_patches(): array {
		return array(
			array( array( 'mobileColumnTemplate' => '<b>1fr</b>' ) ),
			array( array( 'tabletColumnTemplate' => '1fr;color:red' ) ),
			array( array( 'mobileColumnTemplate' => array( '1fr' ) ) ),
			array( array( 'tabletColumnTemplate' => 2 ) ),
			array( array( 'dsgoLayout' => array( 'mobile' => array( 'gap' => '<b>12px</b>' ) ) ) ),
			array( array( 'dsgoLayout' => '12px' ) ),
			array( array( 'dsgoLayout' => array( 'watch' => array( 'gap' => '12px' ) ) ) ),
		);
	}

	/** Index-only targeting still enforces registered layout support. */
	public function test_index_target_refuses_layout_on_an_unsupported_block(): void {
		$id      = $this->page( '<!-- wp:quote --><blockquote class="wp-block-quote"><p>Stay.</p></blockquote><!-- /wp:quote -->' );
		$content = get_post( $id )->post_content;
		$result  = Block_Configurator::update_block_by_index( $id, 0, array( 'dsgoLayout' => array( 'mobile' => array( 'gap' => '12px' ) ) ) );
		$this->assertWPError( $result );
		$this->assertSame( $content, get_post( $id )->post_content );
	}

	/** Document-order nesting leaves the parent wrapper and adjacent sibling intact. */
	public function test_nested_index_update_changes_only_the_nested_owning_grid(): void {
		$child = parse_blocks( $this->grid( 'nested' ) )[0];
		$outer = parse_blocks( $this->grid() )[0];
		$outer['innerBlocks'][] = $child;
		array_splice( $outer['innerContent'], -1, 0, array( null ) );
		$id     = $this->page( serialize_blocks( array( $outer ) ) );
		$before = parse_blocks( get_post( $id )->post_content )[0];
		$result = Block_Configurator::update_block_by_index( $id, 2, array( 'mobileColumnTemplate' => '2fr 1fr' ), 'designsetgo/grid' );
		$this->assertIsArray( $result );
		$after = parse_blocks( get_post( $id )->post_content )[0];
		$this->assertSame( $before['innerHTML'], $after['innerHTML'] );
		$this->assertSame( $before['innerContent'], $after['innerContent'] );
		$this->assertSame( $before['innerBlocks'][0], $after['innerBlocks'][0] );
		$this->assertStringContainsString( '--dsgo-grid-columns-mobile:2fr 1fr', $this->inner_style( $after['innerBlocks'][1] ) );
	}

	/** A CSS custom property's text must not be confused with owned declarations. */
	public function test_template_clear_preserves_semicolons_in_unrelated_css_values(): void {
		$block = parse_blocks( $this->grid() )[0];
		$block['innerContent'][0] = str_replace( '--author-note:kept', '--author-note:&quot;;--dsgo-grid-columns-mobile:inside&quot;;/*;--dsgo-grid-columns-tablet:comment;*/color:red', $block['innerContent'][0] );
		$id     = $this->page( serialize_blocks( array( $block ) ) );
		$result = Block_Configurator::update_block_by_index(
			$id,
			0,
			array(
				'mobileColumnTemplate' => '',
				'tabletColumnTemplate' => '',
			)
		);
		$this->assertIsArray( $result );
		$style = $this->inner_style( parse_blocks( get_post( $id )->post_content )[0] );
		$this->assertStringContainsString( '--author-note:";--dsgo-grid-columns-mobile:inside"', $style );
		$this->assertStringContainsString( '/*;--dsgo-grid-columns-tablet:comment;*/color:red', $style );
		$this->assertStringNotContainsString( '--dsgo-grid-columns-tablet:2fr 1fr', $style );
	}

	/** Missing the immediate owning inner wrapper is an atomic validation failure. */
	public function test_template_update_refuses_a_sibling_lookalike_inner_wrapper(): void {
		$id = $this->page( '<!-- wp:designsetgo/grid --><div class="wp-block-designsetgo-grid"></div><div class="dsgo-grid__inner" style="display:grid"></div><!-- /wp:designsetgo/grid -->' );
		$content = get_post( $id )->post_content;
		$result = Block_Configurator::update_block_by_index( $id, 0, array( 'mobileColumnTemplate' => '1fr' ) );
		$this->assertWPError( $result );
		$this->assertSame( $content, get_post( $id )->post_content );
	}

	/** CSS comments around owned declaration keys cannot prevent clearing. */
	public function test_template_clear_removes_owned_declarations_surrounded_by_comments(): void {
		foreach ( array( '/*note*/--dsgo-grid-columns-mobile:1fr', '/*note*/--dsgo-grid-columns-mobile/*key*/ /*colon*/:1fr' ) as $owned ) {
			$block = parse_blocks( $this->grid() )[0];
			$block['innerContent'][0] = str_replace( '--dsgo-grid-columns-mobile:1fr', $owned, $block['innerContent'][0] );
			$id     = $this->page( serialize_blocks( array( $block ) ) );
			$result = Block_Configurator::update_block_by_index( $id, 0, array( 'mobileColumnTemplate' => '' ) );
			$this->assertIsArray( $result );
			$style = $this->inner_style( parse_blocks( get_post( $id )->post_content )[0] );
			$this->assertStringNotContainsString( '--dsgo-grid-columns-mobile', $style );
			$this->assertStringContainsString( '/*note*/', $style );
			$this->assertStringContainsString( '--author-note:kept;display:grid;grid-template-columns:repeat(3, 1fr);align-items:stretch;row-gap:var(--wp--preset--spacing--50);column-gap:var(--wp--preset--spacing--50);--dsgo-grid-columns-tablet:2fr 1fr', $style );
		}
	}

	/** CSS custom property names are case-sensitive and uppercase vars are unrelated. */
	public function test_template_clear_preserves_case_distinct_custom_properties(): void {
		$block = parse_blocks( $this->grid() )[0];
		$block['innerContent'][0] = str_replace( '--author-note:kept', '--author-note:kept;--DSGO-GRID-COLUMNS-MOBILE:7fr', $block['innerContent'][0] );
		$id     = $this->page( serialize_blocks( array( $block ) ) );
		$result = Block_Configurator::update_block_by_index( $id, 0, array( 'mobileColumnTemplate' => '' ) );
		$this->assertIsArray( $result );
		$style = $this->inner_style( parse_blocks( get_post( $id )->post_content )[0] );
		$this->assertStringContainsString( '--DSGO-GRID-COLUMNS-MOBILE:7fr', $style );
		$this->assertStringNotContainsString( '--dsgo-grid-columns-mobile', $style );
	}
}
