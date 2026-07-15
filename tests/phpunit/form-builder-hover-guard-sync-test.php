<?php
/**
 * The submit-button hover-bg guard must list every hover animation.
 *
 * `src/blocks/form-builder/style.scss` has an instant-hover-background rule
 * gated by `:hover:not( … )` that excludes the hover-animation modifier classes
 * (they manage their own background). That exclusion list hand-duplicates
 * `Plugin::ALLOWED_HOVER_ANIMATIONS`. If an animation is ever added to the PHP
 * constant but not to the CSS guard, a button using that animation with a custom
 * hover-bg would wrongly get the instant background — the bug this guard exists
 * to prevent. This test fails if the two drift apart.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Plugin;

/**
 * Pins the SCSS hover-bg guard against the PHP animation allowlist.
 *
 * @group form-builder
 */
class Form_Builder_Hover_Guard_Sync_Test extends WP_UnitTestCase {

	/**
	 * Every ALLOWED_HOVER_ANIMATIONS slug appears in the SCSS `:hover:not()` guard.
	 */
	public function test_hover_bg_guard_excludes_every_animation() {
		$scss_path = dirname( __DIR__, 2 ) . '/src/blocks/form-builder/style.scss';
		$this->assertFileExists( $scss_path );

		$scss = file_get_contents( $scss_path );

		// Isolate the exclusion list on the instant hover-bg rule.
		$this->assertSame(
			1,
			preg_match( '/:hover:not\(\s*([^)]+)\)/', $scss, $matches ),
			'Could not find the `:hover:not( … )` hover-bg guard in style.scss.'
		);
		$guard = $matches[1];

		// getConstant() reads the value regardless of visibility.
		$animations = ( new ReflectionClass( Plugin::class ) )->getConstant( 'ALLOWED_HOVER_ANIMATIONS' );
		$this->assertNotEmpty( $animations );

		foreach ( $animations as $slug ) {
			$this->assertStringContainsString(
				'.dsgo-form__submit--' . $slug,
				$guard,
				sprintf(
					'Hover animation "%s" is in Plugin::ALLOWED_HOVER_ANIMATIONS but missing from the style.scss `:hover:not()` guard — add it, or a %1$s button with a custom hover background will regress.',
					$slug
				)
			);
		}
	}
}
