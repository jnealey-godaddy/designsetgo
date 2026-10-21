<?php
/**
 * Tests for the Block Migrator conversion AJAX handler (batching + slashing).
 *
 * @group block-migrator
 */
class DesignSetGo_Block_Migrator_Ajax_Test extends WP_Ajax_UnitTestCase {

	/**
	 * Set up: admin user, migrator hooks.
	 */
	public function set_up() {
		parent::set_up();
		require_once DESIGNSETGO_PATH . 'includes/admin/class-block-migrator.php';
		new \DesignSetGo\Admin\Block_Migrator();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Run one conversion batch.
	 *
	 * @param int $offset Offset.
	 * @return array Response data.
	 */
	private function run_batch( $offset ) {
		$_POST['nonce']      = wp_create_nonce( 'designsetgo_block_migrator' );
		$_POST['offset']     = $offset;
		$_POST['batch_size'] = 10;
		$this->_last_response = '';

		try {
			$this->_handleAjax( 'designsetgo_convert_blocks' );
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'] );
		return $response['data'];
	}

	/**
	 * Every post converts across batches even though converted posts leave the
	 * result set, and a quote/backslash in an attribute survives.
	 */
	public function test_converts_all_posts_across_batches_and_preserves_escapes() {
		$ids = array();
		for ( $i = 0; $i < 25; $i++ ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_status'  => 'publish',
					'post_content' => wp_slash( '<!-- wp:designsetgo/section {"anchor":"a\\"b\\\\c"} --><div class="wp-block-designsetgo-section"></div><!-- /wp:designsetgo/section -->' ),
				)
			);
		}

		$offset    = 0;
		$converted = 0;
		for ( $guard = 0; $guard < 10; $guard++ ) {
			$data       = $this->run_batch( $offset );
			$converted += $data['converted'];
			if ( ! $data['hasMore'] ) {
				break;
			}
			$offset = $data['nextOffset'];
		}

		$this->assertSame( 25, $converted );
		foreach ( $ids as $id ) {
			$content = get_post_field( 'post_content', $id );
			$this->assertStringNotContainsString( 'wp:designsetgo/section', $content );
			$this->assertStringContainsString( 'wp:group', $content );
			$blocks = parse_blocks( $content );
			$this->assertSame( 'core/group', $blocks[0]['blockName'] );
			$this->assertSame( 'a"b\\c', $blocks[0]['attrs']['anchor'] );
		}
	}

	/**
	 * A post that still matches (section-divider) must not loop forever or be
	 * skipped past.
	 */
	public function test_still_matching_posts_advance_offset_and_terminate() {
		for ( $i = 0; $i < 12; $i++ ) {
			self::factory()->post->create(
				array(
					'post_status'  => 'publish',
					'post_content' => '<!-- wp:designsetgo/section-divider /-->',
				)
			);
		}

		$offset = 0;
		$steps  = 0;
		do {
			$data   = $this->run_batch( $offset );
			$offset = $data['nextOffset'];
			++$steps;
		} while ( $data['hasMore'] && $steps < 5 );

		$this->assertFalse( $data['hasMore'] );
		$this->assertLessThanOrEqual( 2, $steps );
	}
}
