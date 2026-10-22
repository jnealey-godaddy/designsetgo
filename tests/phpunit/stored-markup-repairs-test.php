<?php
/**
 * Tests for render-time repairs of markup older block versions stored.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use DesignSetGo\Stored_Markup_Repairs;
use WP_UnitTestCase;

/**
 * Stored_Markup_Repairs test case.
 */
class Stored_Markup_Repairs_Test extends WP_UnitTestCase {

	/**
	 * Repairs instance.
	 *
	 * @var Stored_Markup_Repairs
	 */
	private $repairs;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		$this->repairs = new Stored_Markup_Repairs();
		// The plugin registers the repair at boot; render rows without it so
		// each test sees the stored markup. Hooks are restored after the test.
		remove_all_filters( 'render_block_designsetgo/row' );
	}

	public function test_raw_preset_padding_becomes_a_css_variable() {
		$html = '<div class="wp-block-designsetgo-modal-trigger"><button style="color:red;padding-top:var:preset|spacing|40;padding-left:var:preset|spacing|50">Open</button></div>';

		$out = $this->repairs->repair_preset_padding( $html );

		$this->assertStringContainsString( 'padding-top:var(--wp--preset--spacing--40)', $out );
		$this->assertStringContainsString( 'padding-left:var(--wp--preset--spacing--50)', $out );
		$this->assertStringContainsString( 'color:red', $out );
		$this->assertStringNotContainsString( 'var:preset|', $out );
	}

	public function test_plain_padding_is_left_alone() {
		$html = '<div><button style="padding-top:12px">Open</button></div>';

		$this->assertSame( $html, $this->repairs->repair_preset_padding( $html ) );
	}

	public function test_default_badge_loses_its_role_and_label() {
		$html = '<div class="dsgo-card"><span class="dsgo-card__badge" role="status" aria-label="Badge">Sale</span></div>';

		$out = $this->repairs->repair_card_badge( $html );

		$this->assertStringNotContainsString( 'role=', $out );
		$this->assertStringNotContainsString( 'aria-label', $out );
		$this->assertStringContainsString( '>Sale</span>', $out );
	}

	public function test_an_author_set_badge_label_is_kept() {
		$html = '<div class="dsgo-card"><span class="dsgo-card__badge" role="status" aria-label="Limited offer">Sale</span></div>';

		$out = $this->repairs->repair_card_badge( $html );

		$this->assertStringNotContainsString( 'role=', $out );
		$this->assertStringContainsString( 'aria-label="Limited offer"', $out );
	}

	public function test_other_status_regions_in_a_card_are_untouched() {
		$html = '<div class="dsgo-card"><div role="status" aria-label="Badge">Inner</div></div>';

		$this->assertSame( $html, $this->repairs->repair_card_badge( $html ) );
	}

	/**
	 * A Row as the pre-2.12 logo patterns stored it.
	 *
	 * @param string   $wrap     Stored flex-wrap value.
	 * @param string[] $children Paragraph block comments' attribute JSON, one per child.
	 * @return array Parsed block.
	 */
	private function row_block( $wrap, array $children ) {
		$inner = '';
		foreach ( $children as $i => $attrs ) {
			$inner .= '<!-- wp:paragraph ' . $attrs . ' --><p class="has-large-font-size" style="letter-spacing:1px">Name ' . $i . '</p><!-- /wp:paragraph -->';
		}
		$markup = '<!-- wp:designsetgo/row {"layout":{"type":"flex","justifyContent":"center","flexWrap":"' . $wrap . '"}} -->'
			. '<div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint"><div class="dsgo-flex__inner" style="display:flex;justify-content:center;flex-wrap:' . $wrap . '">'
			. $inner
			. '</div></div><!-- /wp:designsetgo/row -->';

		return parse_blocks( $markup )[0];
	}

	public function test_a_nowrap_wordmark_row_wraps_on_narrow_screens() {
		$wordmark = '{"style":{"typography":{"fontWeight":"600","letterSpacing":"1px"}},"textColor":"contrast-2","fontSize":"large"}';
		$block    = $this->row_block( 'nowrap', array_fill( 0, 5, $wordmark ) );

		$out = $this->repairs->repair_wordmark_row( render_block( $block ), $block );

		$this->assertMatchesRegularExpression( '/<div class="[^"]*\bdsgo-flex--wrap-narrow\b[^"]*"/', $out );
		$this->assertStringContainsString( 'flex-wrap:nowrap', $out, 'Desktop keeps the stored layout.' );
		$this->assertStringNotContainsString( 'dsgo-flex__inner dsgo-flex--wrap-narrow', $out, 'The class goes on the Row, not its inner box.' );
	}

	public function test_the_repair_runs_when_the_row_renders() {
		$wordmark = '{"style":{"typography":{"letterSpacing":"2px"}},"fontSize":"x-large"}';
		$block    = $this->row_block( 'nowrap', array_fill( 0, 4, $wordmark ) );

		( new Stored_Markup_Repairs() )->init();

		$this->assertStringContainsString( 'dsgo-flex--wrap-narrow', render_block( $block ) );
	}

	public function test_rows_that_already_wrap_are_untouched() {
		$wordmark = '{"style":{"typography":{"letterSpacing":"1px"}},"fontSize":"large"}';
		$block    = $this->row_block( 'wrap', array_fill( 0, 5, $wordmark ) );
		$html     = render_block( $block );

		$this->assertSame( $html, $this->repairs->repair_wordmark_row( $html, $block ) );
	}

	public function test_rows_of_ordinary_paragraphs_are_untouched() {
		// No letter spacing: a run of text the author chose to keep on one line.
		$block = $this->row_block( 'nowrap', array_fill( 0, 4, '{"fontSize":"large"}' ) );
		$html  = render_block( $block );

		$this->assertSame( $html, $this->repairs->repair_wordmark_row( $html, $block ) );
	}

	public function test_mixed_rows_are_untouched() {
		$markup = '<!-- wp:designsetgo/row {"layout":{"type":"flex","flexWrap":"nowrap"}} -->'
			. '<div class="wp-block-designsetgo-row dsgo-flex"><div class="dsgo-flex__inner" style="display:flex;flex-wrap:nowrap">'
			. '<!-- wp:designsetgo/icon {"icon":"phone"} /-->'
			. '<!-- wp:paragraph {"style":{"typography":{"letterSpacing":"1px"}},"fontSize":"large"} --><p>One</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph {"style":{"typography":{"letterSpacing":"1px"}},"fontSize":"large"} --><p>Two</p><!-- /wp:paragraph -->'
			. '</div></div><!-- /wp:designsetgo/row -->';
		$block  = parse_blocks( $markup )[0];
		$html   = '<div class="wp-block-designsetgo-row dsgo-flex"><div class="dsgo-flex__inner" style="display:flex;flex-wrap:nowrap"><p>One</p><p>Two</p></div></div>';

		$this->assertSame( $html, $this->repairs->repair_wordmark_row( $html, $block ) );
	}
}
