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
}
