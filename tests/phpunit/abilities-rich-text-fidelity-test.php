<?php
/**
 * Inline markup must survive into stored content, per the declared policy.
 *
 * WHY THIS IS NOT A VALIDITY TEST
 *
 * For an attribute declared `source: "html"`, the value lives in the MARKUP,
 * not the block comment. If the sanitizer strips `<em>` before the inserter
 * writes it, the parser reads the attribute back as plain text, save() of that
 * plain text reproduces exactly what is stored, and the block is PERFECTLY
 * VALID. Nothing in the attribute coverage matrix, or in any other
 * isValid-based check, can see it.
 *
 * What the author sees is that `Care <em>begins</em>` was published as
 * `Care begins`. That shipped, and it was found by reading a page rather than
 * by a test.
 *
 * So this file asserts FIDELITY: insert real inline markup, read the stored
 * post back, and check what survived against the policy the attribute
 * declares. Abilities_Rich_Text_Policy_Test separately guarantees every
 * sourced attribute HAS a policy, so the two together leave no attribute
 * unchecked.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Block_Configurator;
use DesignSetGo\Abilities\Block_Inserter;

/**
 * Rich-text fidelity test.
 */
class Abilities_Rich_Text_Fidelity_Test extends WP_UnitTestCase {

	/**
	 * Markup exercising each allow-list tier at once.
	 *
	 * @var string
	 */
	private const PROBE = 'Care <em>begins</em> <a href="https://example.com">here</a>';

	/**
	 * Sanitize one attribute the way a real ability entry point would.
	 *
	 * @param string $block_name Block name.
	 * @param string $attribute  Attribute key.
	 * @return string Sanitized value.
	 */
	private function sanitize( string $block_name, string $attribute ): string {
		$clean = Block_Configurator::sanitize_attributes(
			array( $attribute => self::PROBE ),
			$block_name
		);

		return (string) ( $clean[ $attribute ] ?? '' );
	}

	/**
	 * Each policy, with what must and must not survive it.
	 *
	 * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<int, string>}>
	 */
	public function provide_policies(): array {
		$cases = array();

		foreach ( Block_Configurator::rich_text_policies() as $block => $attributes ) {
			foreach ( $attributes as $attribute => $policy ) {
				if ( 'inline' === $policy ) {
					$survives = array( '<em>', '<a ', 'href="https://example.com"' );
					$stripped = array();
				} elseif ( 'label' === $policy ) {
					// A label is rendered inside an <a> or <button>, so a nested
					// link would be invalid HTML and would not survive save().
					$survives = array( '<em>' );
					$stripped = array( '<a ' );
				} else {
					$survives = array();
					$stripped = array( '<em>', '<a ' );
				}

				$cases[ $block . '::' . $attribute . ' (' . $policy . ')' ] = array(
					$block,
					(string) $attribute,
					$survives,
					$stripped,
				);
			}
		}

		return $cases;
	}

	/**
	 * Sanitization keeps exactly what the policy allows.
	 *
	 * @dataProvider provide_policies
	 *
	 * @param string             $block_name Block name.
	 * @param string             $attribute  Attribute key.
	 * @param array<int, string> $survives   Fragments that must remain.
	 * @param array<int, string> $stripped   Fragments that must be gone.
	 */
	public function test_sanitization_matches_the_policy( string $block_name, string $attribute, array $survives, array $stripped ): void {
		$clean = $this->sanitize( $block_name, $attribute );

		foreach ( $survives as $fragment ) {
			$this->assertStringContainsString(
				$fragment,
				$clean,
				$block_name . '::' . $attribute . ' should keep ' . $fragment
			);
		}

		foreach ( $stripped as $fragment ) {
			$this->assertStringNotContainsString(
				$fragment,
				$clean,
				$block_name . '::' . $attribute . ' should not keep ' . $fragment
			);
		}
	}

	/**
	 * What survives sanitization must also survive serialization.
	 *
	 * The sanitizer keeping `<em>` is worth nothing if the inserter then writes
	 * the attribute with esc_html(). accordion-item did exactly that: its title
	 * was emitted escaped, so the markup would have shown the author's tags as
	 * literal text. It went unnoticed because the sanitizer stripped them first,
	 * making the two bugs cancel out.
	 *
	 * @dataProvider provide_policies
	 *
	 * @param string             $block_name Block name.
	 * @param string             $attribute  Attribute key.
	 * @param array<int, string> $survives   Fragments that must remain.
	 */
	public function test_markup_reaches_the_generated_content( string $block_name, string $attribute, array $survives ): void {
		if ( empty( $survives ) ) {
			$this->addToAssertionCount( 1 );
			return;
		}

		if ( null !== Block_Inserter::get_serialization_gap( $block_name ) ) {
			$this->addToAssertionCount( 1 );
			return;
		}

		$markup = Block_Inserter::build_block_markup(
			$block_name,
			array( $attribute => $this->sanitize( $block_name, $attribute ) ),
			array()
		);

		foreach ( $survives as $fragment ) {
			$this->assertStringContainsString(
				$fragment,
				$markup,
				$block_name . '::' . $attribute . ' survived sanitization but not serialization: '
					. $fragment . ' is missing from the generated markup.'
			);
		}
	}
}
