<?php
/**
 * Keeps the rich-text policy table in step with the block definitions.
 *
 * An attribute declared `source: "html"` or `source: "text"` in block.json is
 * read back out of the MARKUP rather than the block comment. save() renders it,
 * so Block_Configurator has to sanitize it and Block_Inserter has to emit it in
 * a way that agrees.
 *
 * This test is the key diff. The fidelity assertions live in
 * Abilities_Rich_Text_Fidelity_Test, which checks the policies are actually
 * honoured; here we only check the SET of classified attributes matches the
 * registry, in both directions:
 *
 * - a sourced attribute with no policy fails, so adding one to a block.json
 *   forces a decision rather than defaulting silently to plain text;
 * - a policy for an attribute the registry no longer has fails too, so the
 *   table cannot rot into a list of things that used to be true.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Block_Configurator;

/**
 * Rich-text policy coverage test.
 */
class Abilities_Rich_Text_Policy_Test extends WP_UnitTestCase {

	/**
	 * Valid policy names.
	 *
	 * @var array<int, string>
	 */
	private const POLICIES = array( 'inline', 'label', 'plain' );

	/**
	 * Every sourced attribute in the registry, keyed by block name.
	 *
	 * Scoped to the DesignSetGo namespace: `content`, `caption` and `citation`
	 * are core's own rich-text keys and are handled generically for every block,
	 * which is why the per-block table does not list them for core blocks.
	 *
	 * @return array<string, array<int, string>>
	 */
	private function sourced_attributes(): array {
		$sourced = array();

		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block_type ) {
			$name = (string) $name;

			if ( 0 !== strpos( $name, 'designsetgo/' ) ) {
				continue;
			}

			foreach ( (array) $block_type->attributes as $attribute => $definition ) {
				if ( ! is_array( $definition ) ) {
					continue;
				}

				$source = $definition['source'] ?? '';
				if ( 'html' !== $source && 'text' !== $source ) {
					continue;
				}

				$sourced[ $name ][] = (string) $attribute;
			}
		}

		return $sourced;
	}

	/**
	 * Every sourced attribute carries a policy.
	 */
	public function test_every_sourced_attribute_has_a_policy(): void {
		$missing = array();

		foreach ( $this->sourced_attributes() as $block => $attributes ) {
			foreach ( $attributes as $attribute ) {
				if ( '' === Block_Configurator::rich_text_policy( $attribute, $block ) ) {
					$missing[] = $block . '::' . $attribute;
				}
			}
		}

		sort( $missing );

		$this->assertSame(
			array(),
			$missing,
			"These attributes are sourced from markup but have no rich-text policy.\n"
				. "Classify each as 'inline', 'label' or 'plain' in\n"
				. "Block_Configurator::RICH_TEXT_ATTRIBUTES:\n  " . implode( "\n  ", $missing )
		);
	}

	/**
	 * No policy names an attribute the registry no longer has.
	 */
	public function test_no_stale_policies(): void {
		$sourced = $this->sourced_attributes();
		$stale   = array();

		foreach ( Block_Configurator::rich_text_policies() as $block => $attributes ) {
			foreach ( array_keys( $attributes ) as $attribute ) {
				if ( ! isset( $sourced[ $block ] ) || ! in_array( (string) $attribute, $sourced[ $block ], true ) ) {
					$stale[] = $block . '::' . $attribute;
				}
			}
		}

		sort( $stale );

		$this->assertSame(
			array(),
			$stale,
			"These policies name attributes that are no longer sourced from markup:\n  "
				. implode( "\n  ", $stale )
		);
	}

	/**
	 * Every policy name is one the sanitizer understands.
	 */
	public function test_policies_are_recognised(): void {
		$unknown = array();

		foreach ( Block_Configurator::rich_text_policies() as $block => $attributes ) {
			foreach ( $attributes as $attribute => $policy ) {
				if ( ! in_array( $policy, self::POLICIES, true ) ) {
					$unknown[] = $block . '::' . $attribute . ' => ' . var_export( $policy, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Test failure message.
				}
			}
		}

		sort( $unknown );

		$this->assertSame( array(), $unknown, 'Unrecognised rich-text policies: ' . implode( ', ', $unknown ) );
	}
}
