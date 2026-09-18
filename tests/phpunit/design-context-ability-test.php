<?php
/**
 * Theme context regression coverage.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Info\Get_Design_Context;

/** Verify theme defaults are included rather than only saved user overrides. */
class Design_Context_Ability_Test extends WP_UnitTestCase {
	/** The response uses WordPress's resolved design system. */
	public function test_resolved_theme_settings_and_styles(): void {
		$ability = new Get_Design_Context();
		$result  = $ability->execute( array() );
		$this->assertSame( wp_get_global_settings(), (array) $result['settings'] );
		$this->assertSame( wp_get_global_styles(), (array) $result['styles'] );
		$this->assertSame( wp_get_theme()->get_stylesheet(), $result['theme']['stylesheet'] );
		$this->assertIsObject( $result['blockStyles'] );
		$this->assertSame( WP_Font_Face_Resolver::get_fonts_from_theme_json(), $result['fontFaces'] );
	}

	/** Reading design context requires content-editing permission. */
	public function test_permission(): void {
		$ability = new Get_Design_Context();
		wp_set_current_user( 0 );
		$this->assertFalse( $ability->check_permission_callback() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertTrue( $ability->check_permission_callback() );
		wp_set_current_user( 0 );
	}

	/** The registered contract is read-only and rejects unexpected input. */
	public function test_registered_ability_is_read_only_and_accepts_no_parameters(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API requires WordPress 6.9+.' );
		}

		$ability = wp_get_ability( 'designsetgo/get-design-context' );
		$this->assertNotNull( $ability );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest' ) );
		$this->assertTrue( $ability->get_meta_item( 'annotations' )['readonly'] );
		$this->assertFalse( $ability->get_meta_item( 'annotations' )['destructive'] );
		$this->assertTrue( $ability->get_meta_item( 'annotations' )['idempotent'] );

		$result = ( new Get_Design_Context() )->run( array( 'post_id' => 123 ) );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'designsetgo_unknown_parameter', $result['error_code'] );
	}

	/** Only supported blocks and public variation metadata are returned. */
	public function test_block_styles_are_scoped_and_exclude_css(): void {
		$block_names = array( 'designsetgo/section', 'core/heading', 'core/paragraph', 'core/group' );
		$style_name  = 'dsgo-design-context-test';

		try {
			foreach ( $block_names as $block_name ) {
				register_block_style(
					$block_name,
					array(
						'name'         => $style_name,
						'label'        => 'Design context test',
						'is_default'   => true,
						'inline_style' => '.dsgo-design-context-test { color: red; }',
					)
				);
			}

			$result = ( new Get_Design_Context() )->execute( array() );
			$styles = (array) $result['blockStyles'];
			$this->assertArrayNotHasKey( 'core/group', $styles );

			foreach ( array_slice( $block_names, 0, 3 ) as $block_name ) {
				$variations = array_column( $styles[ $block_name ], null, 'name' );
				$this->assertSame(
					array(
						'name'       => $style_name,
						'label'      => 'Design context test',
						'is_default' => true,
					),
					$variations[ $style_name ]
				);
			}
		} finally {
			foreach ( $block_names as $block_name ) {
				unregister_block_style( $block_name, $style_name );
			}
		}
	}
}
