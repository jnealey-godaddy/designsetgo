<?php
/**
 * Test the `guidance` field on the designsetgo/list-blocks ability (Task 13).
 *
 * Per-block agent guidance lives at src/blocks/{block}/agent.json and is
 * copied by webpack to build/blocks/{block}/agent.json alongside block.json.
 * The ability reads the build copy — never src/ — so `npm run build` must
 * run before these tests, matching how every other block asset is verified.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Abilities_Registry;

/**
 * Tests for the list-blocks ability's `guidance` field.
 */
class Test_Abilities_List_Blocks_Guidance extends WP_UnitTestCase {

	/**
	 * The ability reads build/blocks/<dir>/agent.json, never src/. This
	 * fails loudly (rather than the ability silently returning null) when
	 * `npm run build` hasn't run, or webpack's copy pattern regresses.
	 */
	public function test_build_copy_of_agent_json_exists() {
		$this->assertFileExists(
			DESIGNSETGO_PATH . 'build/blocks/section/agent.json',
			'npm run build must copy src/blocks/section/agent.json to build/blocks/section/agent.json before this test can pass.'
		);
	}

	/**
	 * With detail=full, a guided block reports its decoded agent.json under
	 * `guidance`, keyed exactly as the schema documents.
	 */
	public function test_guidance_full_detail_reports_when_to_use() {
		$registry = Abilities_Registry::get_instance();
		$ability  = $registry->get_ability( 'designsetgo/list-blocks' );

		$this->assertNotNull( $ability );

		$result = $ability->execute(
			array(
				'detail' => 'full',
				'blocks' => array( 'designsetgo/section' ),
			)
		);

		$this->assertCount( 1, $result['blocks'] );
		$block = $result['blocks'][0];

		$this->assertArrayHasKey( 'guidance', $block );
		$this->assertIsArray( $block['guidance'] );
		$this->assertArrayHasKey( 'whenToUse', $block['guidance'] );
		$this->assertIsString( $block['guidance']['whenToUse'] );
		$this->assertNotEmpty( $block['guidance']['whenToUse'] );
		$this->assertArrayHasKey( 'avoid', $block['guidance'] );
		$this->assertIsArray( $block['guidance']['avoid'] );
		$this->assertNotEmpty( $block['guidance']['avoid'] );
		$this->assertArrayHasKey( 'examples', $block['guidance'] );
		$this->assertIsArray( $block['guidance']['examples'] );
		$this->assertNotEmpty( $block['guidance']['examples'] );
	}

	/**
	 * Every one of the seven guided blocks (Task 13) reports guidance with
	 * detail=full — not just designsetgo/section.
	 */
	public function test_guidance_present_for_every_guided_block() {
		$guided_blocks = array(
			'designsetgo/section',
			'designsetgo/row',
			'designsetgo/grid',
			'designsetgo/card',
			'designsetgo/icon-button',
			'designsetgo/accordion',
			'designsetgo/tabs',
		);

		$registry = Abilities_Registry::get_instance();
		$ability  = $registry->get_ability( 'designsetgo/list-blocks' );

		$result = $ability->execute(
			array(
				'detail' => 'full',
				'blocks' => $guided_blocks,
			)
		);

		$this->assertCount( count( $guided_blocks ), $result['blocks'] );

		foreach ( $result['blocks'] as $block ) {
			$this->assertArrayHasKey( 'guidance', $block, "{$block['name']} should carry guidance." );
			$this->assertIsArray( $block['guidance'], "{$block['name']}'s guidance should be an array." );
			$this->assertNotEmpty( $block['guidance']['whenToUse'] ?? '', "{$block['name']}'s guidance should have whenToUse." );
		}
	}

	/**
	 * A block with no agent.json reports `guidance: null` with detail=full
	 * — absent, not omitted, so callers can rely on the key always existing.
	 */
	public function test_guidance_is_null_for_a_block_without_agent_json() {
		$this->assertFileDoesNotExist(
			DESIGNSETGO_PATH . 'build/blocks/icon/agent.json',
			'This test assumes designsetgo/icon has no agent.json; pick a different unguided block if this starts failing.'
		);

		$registry = Abilities_Registry::get_instance();
		$ability  = $registry->get_ability( 'designsetgo/list-blocks' );

		$result = $ability->execute(
			array(
				'detail' => 'full',
				'blocks' => array( 'designsetgo/icon' ),
			)
		);

		$this->assertCount( 1, $result['blocks'] );
		$block = $result['blocks'][0];

		$this->assertArrayHasKey( 'guidance', $block );
		$this->assertNull( $block['guidance'] );
	}

	/**
	 * `guidance` is only meaningful — and only computed — for detail=full;
	 * summary responses don't carry it at all.
	 */
	public function test_guidance_absent_for_summary_detail() {
		$registry = Abilities_Registry::get_instance();
		$ability  = $registry->get_ability( 'designsetgo/list-blocks' );

		$result = $ability->execute(
			array(
				'detail' => 'summary',
				'blocks' => array( 'designsetgo/section' ),
			)
		);

		$this->assertCount( 1, $result['blocks'] );
		$this->assertArrayNotHasKey( 'guidance', $result['blocks'][0] );
	}
}
