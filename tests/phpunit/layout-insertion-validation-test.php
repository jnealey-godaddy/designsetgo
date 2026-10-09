<?php
/** New layout attributes are validated before child or generation serialization.
 *
 * @package DesignSetGo
 */

use DesignSetGo\Abilities\Block_Configurator;
use DesignSetGo\Abilities\Block_Inserter;
use DesignSetGo\Abilities\Inserters\Add_Tab;

/** Protect both direct insertion and the shared generation preflight. */
class Layout_Insertion_Validation_Test extends WP_UnitTestCase {

	/** Use a permitted editor. */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/** A nested invalid template must refuse before its type can be coerced. */
	public function test_direct_child_insertion_refuses_nested_invalid_layout_attributes(): void {
		$content = Block_Inserter::build_block_markup( 'designsetgo/section' );
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => wp_slash( $content ),
			)
		);
		$result = Block_Configurator::insert_inner_block(
			$id,
			0,
			'designsetgo/section',
			array(),
			array(
				array(
					'name'        => 'designsetgo/grid',
					'attributes'  => array( 'mobileColumnTemplate' => array( '1fr' ) ),
					'innerBlocks' => array(),
				),
			)
		);
		$this->assertWPError( $result );
		$this->assertSame( $content, get_post( $id )->post_content );
	}

	/** The main generation preparer refuses unsupported targets and value types.
	 *
	 * @dataProvider unsupported_fields
	 * @param string $name Requested block name.
	 * @param array  $attributes Invalid attribute patch.
	 */
	public function test_generation_preflight_refuses_unsupported_or_wrong_type_fields( string $name, array $attributes ): void {
		$result = Block_Inserter::prepare_block_definition( $name, $attributes, array() );
		$this->assertFalse( $result['success'] ?? true );
	}

	/**
	 * Unsupported fields and value types cannot create silently invalid content.
	 *
	 * @return array Invalid generation requests.
	 */
	public function unsupported_fields(): array {
		return array(
			array( 'designsetgo/grid', array( 'mobileColumnTemplate' => array( '1fr' ) ) ),
			array( 'designsetgo/grid', array( 'tabletColumnTemplate' => 2 ) ),
			array( 'core/paragraph', array( 'mobileColumnTemplate' => '1fr' ) ),
			array( 'core/quote', array( 'dsgoLayout' => array( 'desktop' => array( 'width' => '100%' ) ) ) ),
		);
	}

	/** The legacy tab builder cannot serialize authored layout wrappers. */
	public function test_specialized_tab_writer_refuses_nested_layout_fields(): void {
		$content = '<!-- wp:designsetgo/tabs --><div class="wp-block-designsetgo-tabs"></div><!-- /wp:designsetgo/tabs -->';
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => $content,
			)
		);
		$result = ( new Add_Tab() )->execute(
			array(
				'post_id'      => $id,
				'title'        => 'New tab',
				'inner_blocks' => array(
					array(
						'name'        => 'designsetgo/section',
						'innerBlocks' => array(
							array(
								'name' => 'designsetgo/grid',
								'attributes' => array( 'mobileColumnTemplate' => '1fr' ),
							),
						),
					),
				),
			)
		);
		$this->assertWPError( $result );
		$this->assertStringContainsString( 'add-child-block', $result->get_error_message() );
		$this->assertSame( $content, get_post( $id )->post_content );
	}
}
