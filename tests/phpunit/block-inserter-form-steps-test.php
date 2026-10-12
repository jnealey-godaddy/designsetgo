<?php
/**
 * Multi-step forms written through the Abilities API.
 *
 * A form is either single-page (fields) or multi-step (steps only), and its
 * submit button can't sit inline while it has steps — the inline button lives
 * outside every step. The editor enforces both; the inserter must too.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Inserters\Add_Block;

/**
 * Form steps through the inserter.
 *
 * @group abilities
 * @group form-builder
 */
class Block_Inserter_Form_Steps_Test extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * Insert a form-builder into an empty page.
	 *
	 * @param array<string, mixed> $attributes   Form attributes.
	 * @param array<int, mixed>    $inner_blocks Form children.
	 * @return array{0: mixed, 1: int} Result and post ID.
	 */
	private function insert_form( array $attributes, array $inner_blocks ): array {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '',
			)
		);
		$result  = ( new Add_Block() )->run(
			array(
				'post_id'      => $post_id,
				'block_name'   => 'designsetgo/form-builder',
				'attributes'   => array_merge( array( 'formId' => 'steps-ai' ), $attributes ),
				'inner_blocks' => $inner_blocks,
			)
		);
		return array( $result, $post_id );
	}

	/**
	 * A step holding one text field.
	 *
	 * @param string $field Field name.
	 * @return array<string, mixed> Step definition.
	 */
	private function step( string $field ): array {
		return array(
			'name'        => 'designsetgo/form-step',
			'innerBlocks' => array( $this->field( $field ) ),
		);
	}

	/**
	 * A text field.
	 *
	 * @param string $field Field name.
	 * @return array<string, mixed> Field definition.
	 */
	private function field( string $field ): array {
		return array(
			'name'       => 'designsetgo/form-text-field',
			'attributes' => array( 'fieldName' => $field ),
		);
	}

	/**
	 * Steps mixed with loose fields are refused, and nothing is written.
	 */
	public function test_steps_mixed_with_loose_fields_are_refused(): void {
		list( $result, $post_id ) = $this->insert_form(
			array(),
			array( $this->step( 'first' ), $this->field( 'loose' ) )
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'designsetgo_invalid_child_placement', $result['error_code'] );
		$this->assertStringContainsString( 'designsetgo/form-step', (string) $result['message'] );
		$this->assertContains( '', $result['invalid_paths'], 'The form itself is the offending block.' );
		$this->assertSame( '', get_post( $post_id )->post_content, 'A refused call must not write.' );
	}

	/**
	 * A form of steps only is written.
	 */
	public function test_steps_only_form_is_written(): void {
		list( $result, $post_id ) = $this->insert_form(
			array(),
			array( $this->step( 'first' ), $this->step( 'second' ) )
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 2, substr_count( get_post( $post_id )->post_content, '<!-- wp:designsetgo/form-step' ) );
	}

	/**
	 * An inline submit on a stepped form is written as below.
	 */
	public function test_inline_submit_becomes_below_when_the_form_has_steps(): void {
		list( $result, $post_id ) = $this->insert_form(
			array( 'submitButtonPosition' => 'inline' ),
			array( $this->step( 'first' ), $this->step( 'second' ) )
		);

		$this->assertTrue( $result['success'] );
		$content = get_post( $post_id )->post_content;
		$this->assertStringNotContainsString( 'dsgo-form__submit--inline', $content );
		$this->assertStringNotContainsString( 'dsgo-form-builder--button-inline', $content );
		$this->assertStringNotContainsString( '"submitButtonPosition":"inline"', $content );
		$this->assertStringContainsString( '<div class="dsgo-form__footer">', $content );
	}

	/**
	 * A single-page form keeps its inline submit.
	 */
	public function test_inline_submit_is_kept_on_a_single_page_form(): void {
		list( $result, $post_id ) = $this->insert_form(
			array( 'submitButtonPosition' => 'inline' ),
			array( $this->field( 'email' ) )
		);

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'dsgo-form__submit--inline', get_post( $post_id )->post_content );
	}
}
