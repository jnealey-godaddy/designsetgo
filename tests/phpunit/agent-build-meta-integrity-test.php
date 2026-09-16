<?php
/**
 * The pending agent build blob decides who may auto-save a build and whether
 * its markup is filtered, so nobody but Build_Store may write it: the meta
 * keys grant no meta capability (XML-RPC custom fields, the classic Custom
 * Fields box), and a blob Build_Store did not sign is never pending.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

use DesignSetGo\Abilities\Agent_Build\Build_REST;
use DesignSetGo\Abilities\Agent_Build\Build_Store;

/**
 * Pending build meta integrity tests.
 *
 * @group abilities
 * @group agent-build
 */
class Agent_Build_Meta_Integrity_Test extends WP_UnitTestCase {

	/**
	 * Store instance under test.
	 *
	 * @var Build_Store
	 */
	private $store;

	/**
	 * Contributor who owns the draft.
	 *
	 * @var int
	 */
	private $contributor_id;

	/**
	 * The contributor's own draft.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up a contributor's draft.
	 */
	public function set_up() {
		parent::set_up();

		$this->store          = new Build_Store();
		$this->contributor_id = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$this->post_id        = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => $this->contributor_id,
			)
		);
		wp_set_current_user( $this->contributor_id );
	}

	/**
	 * A minimal tree.
	 *
	 * @param string $content Paragraph content.
	 * @return array
	 */
	private function tree( string $content = 'Hello' ): array {
		return array(
			'version' => 1,
			'blocks'  => array(
				array(
					'name'       => 'core/paragraph',
					'attributes' => array( 'content' => $content ),
				),
			),
		);
	}

	/**
	 * Rewrite the stored pending blob after changing it.
	 *
	 * @param callable $change Receives and returns the decoded blob.
	 * @return void
	 */
	private function tamper( callable $change ): void {
		$blob = json_decode( get_post_meta( $this->post_id, Build_Store::META_PENDING_TREE, true ), true );
		$this->assertIsArray( $blob );
		update_post_meta( $this->post_id, Build_Store::META_PENDING_TREE, wp_slash( wp_json_encode( $change( $blob ) ) ) );
	}

	/**
	 * The contributor can edit their draft but holds no meta capability for
	 * either agent-build key.
	 */
	public function test_contributor_has_no_meta_capabilities_for_the_build_keys(): void {
		$this->assertTrue( current_user_can( 'edit_post', $this->post_id ) );

		foreach ( array( Build_Store::META_PENDING_TREE, Build_Store::META_BUILD_REPORT ) as $key ) {
			foreach ( array( 'add_post_meta', 'edit_post_meta', 'delete_post_meta' ) as $cap ) {
				$this->assertFalse( current_user_can( $cap, $this->post_id, $key ), "$cap on $key" );
			}
		}
	}

	/**
	 * XML-RPC custom fields (wp.editPost / metaWeblog.editPost) cannot write
	 * either key.
	 */
	public function test_xmlrpc_custom_fields_cannot_write_the_build_keys(): void {
		require_once ABSPATH . WPINC . '/class-wp-xmlrpc-server.php';
		$server = new wp_xmlrpc_server();

		$server->set_custom_fields(
			$this->post_id,
			array(
				array(
					'key'   => Build_Store::META_PENDING_TREE,
					'value' => '{"forged":true}',
				),
				array(
					'key'   => Build_Store::META_BUILD_REPORT,
					'value' => '{"status":"finished"}',
				),
			)
		);

		$this->assertSame( '', get_post_meta( $this->post_id, Build_Store::META_PENDING_TREE, true ) );
		$this->assertSame( '', get_post_meta( $this->post_id, Build_Store::META_BUILD_REPORT, true ) );
	}

	/**
	 * Data provider: ways to tamper with a signed blob.
	 *
	 * @return array<string, array{0: callable}>
	 */
	public function tamperings(): array {
		return array(
			'flip submitterUnfiltered' => array(
				static function ( array $blob ): array {
					$blob['submitterUnfiltered'] = true;
					return $blob;
				},
			),
			'change submitter'         => array(
				static function ( array $blob ): array {
					$blob['submitter'] = 1;
					return $blob;
				},
			),
			'change tree'              => array(
				static function ( array $blob ): array {
					$blob['tree']['blocks'][0]['attributes']['content'] = '<script>alert(1)</script>';
					return $blob;
				},
			),
			'drop signature'           => array(
				static function ( array $blob ): array {
					unset( $blob['signature'] );
					return $blob;
				},
			),
		);
	}

	/**
	 * A blob changed after Build_Store signed it is not pending, and the
	 * editor's GET reports nothing pending.
	 *
	 * @dataProvider tamperings
	 *
	 * @param callable $change Tampering.
	 */
	public function test_tampered_pending_blob_is_not_pending( callable $change ): void {
		$this->store->store( $this->post_id, $this->tree(), 'replace' );
		$this->assertNotNull( $this->store->pending( $this->post_id ) );

		$this->tamper( $change );

		$this->assertNull( $this->store->pending( $this->post_id ) );
		$this->assertFalse( $this->store->is_conflict( $this->post_id ) );

		new Build_REST( $this->store );
		rest_get_server()->override_by_default = true;
		do_action( 'rest_api_init' );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/designsetgo/v1/agent-build/' . $this->post_id ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['pending'] );
	}

	/**
	 * A validly signed blob copied onto another post is not pending there:
	 * the signature is bound to the post it was stored for.
	 */
	public function test_signed_blob_copied_to_another_post_is_not_pending(): void {
		$this->store->store( $this->post_id, $this->tree(), 'replace' );
		$other_post_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => $this->contributor_id,
			)
		);

		update_post_meta(
			$other_post_id,
			Build_Store::META_PENDING_TREE,
			wp_slash( get_post_meta( $this->post_id, Build_Store::META_PENDING_TREE, true ) )
		);

		$this->assertSame(
			get_post_meta( $this->post_id, Build_Store::META_PENDING_TREE, true ),
			get_post_meta( $other_post_id, Build_Store::META_PENDING_TREE, true )
		);
		$this->assertNull( $this->store->pending( $other_post_id ) );
		$this->assertNotNull( $this->store->pending( $this->post_id ) );
	}

	/**
	 * An untouched signed blob still round-trips, including text that JSON
	 * escapes.
	 */
	public function test_signed_blob_round_trips(): void {
		$tree = $this->tree( "Café \"quoted\" <a href=\"https://x\">link</a>\nline \\ slash" );
		$this->store->store( $this->post_id, $tree, 'append' );

		$pending = $this->store->pending( $this->post_id );

		$this->assertSame( $tree, $pending['tree'] );
		$this->assertSame( 'append', $pending['mode'] );
		$this->assertSame( $this->contributor_id, $pending['submitter'] );
		$this->assertFalse( $pending['submitterUnfiltered'] );
		$this->assertArrayNotHasKey( 'signature', $pending );
	}
}
