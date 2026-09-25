<?php
/**
 * Frontend accessibility: server-side pieces of the 2026-09-24 audit sweep.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

/**
 * Tests for script translations, breadcrumb/map semantics and no-JS fallbacks.
 */
class Frontend_Accessibility_Test extends WP_UnitTestCase {

	/**
	 * View scripts that speak to screen readers load translations.
	 *
	 * Core's register_block_script_handle() already calls
	 * wp_set_script_translations() for any block script that depends on
	 * wp-i18n, using block.json's textdomain. The bug was never that wiring:
	 * accordion, tabs, modal, flip-card and slider hard-coded English labels,
	 * so they had no wp-i18n dependency and nothing to translate. This pins
	 * both halves: the dependency exists, and the textdomain is set.
	 */
	public function test_view_scripts_using_i18n_get_translations(): void {
		$scripts = wp_scripts();
		$checked = array();

		foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block_type ) {
			if ( 0 !== strpos( (string) $name, 'designsetgo/' ) ) {
				continue;
			}
			foreach ( (array) $block_type->view_script_handles as $handle ) {
				$script = $scripts->query( $handle, 'registered' );
				if ( ! $script || ! in_array( 'wp-i18n', (array) $script->deps, true ) ) {
					continue;
				}
				$this->assertSame( 'designsetgo', $script->textdomain, "{$handle} has no script translations." );
				$checked[] = $handle;
			}
		}

		// The blocks this sweep translated must be among them.
		foreach ( array( 'accordion', 'tabs', 'modal', 'flip-card', 'slider' ) as $slug ) {
			$this->assertContains( "designsetgo-{$slug}-view-script", $checked, "{$slug} view script should depend on wp-i18n." );
		}
	}

	/**
	 * The current breadcrumb is marked aria-current="page", linked or not.
	 */
	public function test_breadcrumbs_mark_current_page(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Current Post' ) );

		foreach ( array( false, true ) as $link_current ) {
			$block = new WP_Block(
				array(
					'blockName'    => 'designsetgo/breadcrumbs',
					'attrs'        => array(
						'showHome'    => true,
						'showCurrent' => true,
						'linkCurrent' => $link_current,
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				),
				array( 'postId' => $post_id )
			);

			$html = $block->render();

			$this->assertSame( 1, substr_count( $html, 'aria-current="page"' ), 'Exactly one crumb is current.' );
			$tag = $link_current ? '<a ' : '<span ';
			$this->assertMatchesRegularExpression( '/' . preg_quote( $tag, '/' ) . '[^>]*aria-current="page"[^>]*>\s*Current Post/', $html );
		}
	}

	/**
	 * Privacy-mode maps hand view.js the localized region label.
	 *
	 * The view script builds the container after consent, and used to
	 * hard-code the English label "Map" in place of "Map showing {address}".
	 */
	public function test_privacy_mode_map_carries_localized_label(): void {
		$render = static function ( array $attrs ): string {
			return render_block(
				array(
					'blockName'    => 'designsetgo/map',
					'attrs'        => $attrs,
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			);
		};

		$private = $render(
			array(
				'dsgoPrivacyMode' => true,
				'dsgoAddress'     => '1 Main St',
			)
		);
		$this->assertStringContainsString( 'data-dsgo-map-label="Map showing 1 Main St"', $private );

		$public = $render( array( 'dsgoAddress' => '1 Main St' ) );
		$this->assertStringNotContainsString( 'data-dsgo-map-label', $public, 'Server-built containers already carry the label.' );
		$this->assertStringContainsString( 'aria-label="Map showing 1 Main St"', $public );
	}

	/**
	 * No-JS fallback reveals Tabs panels and hides the empty TOC, inside
	 * <noscript> so it is inert when scripts run.
	 */
	public function test_noscript_fallbacks_print_on_frontend(): void {
		ob_start();
		( new ReflectionClass( \DesignSetGo\Assets::class ) )
			->newInstanceWithoutConstructor()
			->print_noscript_fallbacks();
		$html = (string) ob_get_clean();

		$this->assertStringStartsWith( '<noscript><style id="designsetgo-noscript">', $html );
		$this->assertStringContainsString( '.dsgo-tabs .dsgo-tab[hidden]{display:block!important}', $html );
		$this->assertStringContainsString( '.dsgo-tabs__nav,.dsgo-table-of-contents{display:none!important}', $html );
		$this->assertStringEndsWith( "</style></noscript>\n", $html );
	}
}
