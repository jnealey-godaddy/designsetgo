<?php
/**
 * Tests for the Block Migrator scan/count database queries.
 *
 * Exercises the private $wpdb-backed methods that locate posts containing
 * convertible DesignSetGo blocks: count_matching_posts(), scan_for_dsgo_blocks()
 * and the recursive count_dsgo_blocks() helper.
 *
 * @group block-migrator
 */
class DesignSetGo_Block_Migrator_Test extends WP_UnitTestCase {

	/**
	 * Block markup snippet for a single convertible block of the given name.
	 *
	 * @param string $block_name Full block name, e.g. 'designsetgo/section'.
	 * @return string Serialized block comment delimiters.
	 */
	private function block_markup( $block_name ) {
		return "<!-- wp:{$block_name} --><!-- /wp:{$block_name} -->";
	}

	/**
	 * Instantiate the migrator. The constructor only registers admin hooks,
	 * which is harmless under the test harness (reset between tests).
	 *
	 * @return \DesignSetGo\Admin\Block_Migrator
	 */
	private function migrator() {
		require_once DESIGNSETGO_PATH . 'includes/admin/class-block-migrator.php';
		return new \DesignSetGo\Admin\Block_Migrator();
	}

	/**
	 * Invoke a private/protected method on a fresh migrator instance.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Positional arguments.
	 * @return mixed Method return value.
	 */
	private function invoke( $method, array $args = array() ) {
		$ref = new ReflectionMethod( \DesignSetGo\Admin\Block_Migrator::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( $this->migrator(), $args );
	}

	public function test_count_matching_posts_is_zero_without_convertible_blocks() {
		self::factory()->post->create_many(
			3,
			array(
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>Plain</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertSame( 0, $this->invoke( 'count_matching_posts' ) );
	}

	public function test_count_matching_posts_counts_only_posts_with_convertible_blocks() {
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $this->block_markup( 'designsetgo/section' ),
			)
		);
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $this->block_markup( 'designsetgo/grid' ),
			)
		);
		// Non-convertible post should be ignored.
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>Plain</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertSame( 2, $this->invoke( 'count_matching_posts' ) );
	}

	public function test_scan_returns_post_id_and_block_count() {
		$matching = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $this->block_markup( 'designsetgo/section' ) . $this->block_markup( 'designsetgo/row' ),
			)
		);
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>Plain</p><!-- /wp:paragraph -->',
			)
		);

		$results = $this->invoke( 'scan_for_dsgo_blocks' );

		$this->assertCount( 1, $results, 'Only the post containing convertible blocks should be returned.' );
		$this->assertSame( $matching, $results[0]['post_id'] );
		$this->assertSame( 2, $results[0]['count'], 'Both the section and row blocks should be counted.' );
	}

	public function test_scan_respects_limit_and_offset() {
		for ( $i = 0; $i < 3; $i++ ) {
			self::factory()->post->create(
				array(
					'post_status'  => 'publish',
					'post_content' => $this->block_markup( 'designsetgo/section' ),
				)
			);
		}

		$first_batch  = $this->invoke( 'scan_for_dsgo_blocks', array( 2, 0 ) );
		$second_batch = $this->invoke( 'scan_for_dsgo_blocks', array( 2, 2 ) );

		$this->assertCount( 2, $first_batch, 'First batch should honour LIMIT 2.' );
		$this->assertCount( 1, $second_batch, 'Second batch should return the remaining post via OFFSET.' );

		// Batches must not overlap (ORDER BY ID ASC guarantees disjoint ranges).
		$first_ids  = wp_list_pluck( $first_batch, 'post_id' );
		$second_ids = wp_list_pluck( $second_batch, 'post_id' );
		$this->assertEmpty( array_intersect( $first_ids, $second_ids ) );
	}

	public function test_count_dsgo_blocks_counts_nested_blocks_recursively() {
		$blocks = array(
			array(
				'blockName'   => 'designsetgo/section',
				'innerBlocks' => array(
					array( 'blockName' => 'core/paragraph', 'innerBlocks' => array() ),
					array(
						'blockName'   => 'designsetgo/row',
						'innerBlocks' => array(
							array( 'blockName' => 'designsetgo/icon-button', 'innerBlocks' => array() ),
						),
					),
				),
			),
			array( 'blockName' => 'core/heading', 'innerBlocks' => array() ),
		);

		// section + row + icon-button = 3 convertible blocks; core blocks ignored.
		$this->assertSame( 3, $this->invoke( 'count_dsgo_blocks', array( $blocks ) ) );
	}
}
