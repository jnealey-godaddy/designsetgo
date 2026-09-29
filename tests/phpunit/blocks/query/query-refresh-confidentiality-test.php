<?php
/**
 * Refresh transport must never publish the source template.
 *
 * @group query-block
 */
class DesignSetGo_Query_Refresh_Confidentiality_Test extends WP_UnitTestCase {

	private const MARKER = 'MEMBERS_ONLY_REFRESH_MARKER';

	private function markup( $query_id, $rule = null ) {
		$rule = null === $rule ? array( 'type' => 'auth', 'value' => true ) : $rule;
		$visibility = wp_json_encode( array( 'dsgoVisibility' => array( 'operator' => 'AND', 'rules' => array( $rule ) ) ) );
		return '<!-- wp:designsetgo/query {"queryId":"' . $query_id . '","perPage":1} -->'
			. '<!-- wp:designsetgo/query-results --><!-- wp:group -->'
			. '<div class="wp-block-group"><!-- wp:paragraph ' . $visibility . ' -->'
			. '<p>' . self::MARKER . '</p><!-- /wp:paragraph --></div><!-- /wp:group -->'
			. '<!-- /wp:designsetgo/query-results --><!-- /wp:designsetgo/query -->';
	}

	private function source( $html, $query_id ) {
		$pattern = '#data-dsgo-blobs-for="' . preg_quote( $query_id, '#' ) . '" data-dsgo-refresh-source="([A-Za-z0-9+/=]+)" data-dsgo-signature="([a-f0-9]{64})"#';
		$this->assertSame( 1, preg_match( $pattern, $html, $match ) );
		return array( 'source' => $match[1], 'signature' => $match[2] );
	}

	private function refresh( $query_id, $source, $page = 1 ) {
		$request = new WP_REST_Request( 'POST', '/designsetgo/v1/query/render' );
		$request->set_param( 'queryId', $query_id );
		$request->set_param( 'source', $source['source'] );
		$request->set_param( 'signature', $source['signature'] );
		$request->set_param( 'page', $page );
		return rest_get_server()->dispatch( $request );
	}

	public function test_anonymous_markup_and_decoded_refresh_source_do_not_reveal_hidden_template() {
		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_set_current_user( 0 );
		$html = do_blocks( $this->markup( 'opaque-public' ) );
		$this->assertStringNotContainsString( self::MARKER, $html );
		$source = $this->source( $html, 'opaque-public' );
		$decoded = base64_decode( $source['source'], true );
		$this->assertStringNotContainsString( self::MARKER, $decoded );
		$this->assertArrayNotHasKey( 'innerBlocks', json_decode( $decoded, true ) );
		$this->assertArrayNotHasKey( 'attributes', json_decode( $decoded, true ) );
		$this->assertSame( 200, $this->refresh( 'opaque-public', $source )->get_status() );
	}

	public function test_replayed_member_token_rechecks_current_viewer_and_keeps_nested_template() {
		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$member_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $member_id );
		$source = $this->source( do_blocks( $this->markup( 'viewer-replay' ) ), 'viewer-replay' );
		$this->assertStringNotContainsString( self::MARKER, base64_decode( $source['source'], true ) );
		$this->assertStringContainsString( self::MARKER, $this->refresh( 'viewer-replay', $source )->get_data()['html'] );
		wp_set_current_user( 0 );
		$response = $this->refresh( 'viewer-replay', $source );
		$this->assertSame( 403, $response->get_status() );
		wp_set_current_user( $member_id );
		$this->assertStringContainsString( self::MARKER, $this->refresh( 'viewer-replay', $source )->get_data()['html'] );
	}

	private function unrestricted_children_markup( $query_id, $attributes = array() ) {
		$attributes['queryId'] = $query_id;
		$attributes['perPage'] = 1;
		return '<!-- wp:designsetgo/query ' . wp_json_encode( $attributes ) . ' -->'
			. '<!-- wp:designsetgo/query-results --><!-- wp:paragraph --><p>' . self::MARKER
			. '</p><!-- /wp:paragraph --><!-- /wp:designsetgo/query-results --><!-- /wp:designsetgo/query -->';
	}

	public function test_member_only_query_cannot_be_replayed_by_anonymous_viewer() {
		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$visibility = array( 'operator' => 'AND', 'rules' => array( array( 'type' => 'auth', 'value' => true ) ) );
		$html = do_blocks( $this->unrestricted_children_markup( 'gated-query', array( 'dsgoVisibility' => $visibility ) ) );
		$this->assertStringContainsString( self::MARKER, $html );
		$source = $this->source( $html, 'gated-query' );
		$this->assertSame( 200, $this->refresh( 'gated-query', $source )->get_status() );
		wp_set_current_user( 0 );
		$this->assertSame( 403, $this->refresh( 'gated-query', $source )->get_status() );
	}

	public function test_member_only_parent_query_cannot_be_replayed_by_anonymous_viewer() {
		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$wrapper = '<!-- wp:group {"dsgoVisibility":{"operator":"AND","rules":[{"type":"auth","value":true}]}} -->'
			. '<div class="wp-block-group">' . $this->unrestricted_children_markup( 'gated-parent' ) . '</div><!-- /wp:group -->';
		$source = $this->source( do_blocks( $wrapper ), 'gated-parent' );
		wp_set_current_user( 0 );
		$this->assertSame( 403, $this->refresh( 'gated-parent', $source )->get_status() );
	}

	public function test_anonymous_source_is_shared_only_with_other_anonymous_requests() {
		wp_set_current_user( 0 );
		$source = \DesignSetGo\Blocks\Query\RefreshSource::sign( 'visitor-source', array( 'source' => 'posts' ), '', 0 );
		$this->assertSame( 200, $this->refresh( 'visitor-source', $source )->get_status() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, $this->refresh( 'visitor-source', $source )->get_status() );
		wp_set_current_user( 0 );
		$this->assertSame( $source, \DesignSetGo\Blocks\Query\RefreshSource::sign( 'visitor-source', array( 'source' => 'posts' ), '', 0 ) );
	}

	public function test_member_source_cannot_be_replayed_by_another_member() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$source = \DesignSetGo\Blocks\Query\RefreshSource::sign( 'other-member', array( 'source' => 'posts' ), '', 0 );
		$this->assertSame( 200, $this->refresh( 'other-member', $source )->get_status() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, $this->refresh( 'other-member', $source )->get_status() );
	}

	public function test_item_rules_are_rechecked_on_later_pages_instead_of_pruned_at_first_paint() {
		$older = self::factory()->post->create( array( 'post_status' => 'publish', 'post_date' => '2026-01-01 12:00:00' ) );
		self::factory()->post->create( array( 'post_status' => 'publish', 'post_date' => '2026-01-02 12:00:00' ) );
		update_post_meta( $older, 'show_marker', 'yes' );
		wp_set_current_user( 0 );
		$html = do_blocks( $this->markup( 'later-item', array( 'type' => 'meta', 'key' => 'show_marker', 'op' => 'equals', 'value' => 'yes' ) ) );
		$this->assertStringNotContainsString( self::MARKER, $html );
		$source = $this->source( $html, 'later-item' );
		$this->assertStringContainsString( self::MARKER, $this->refresh( 'later-item', $source, 2 )->get_data()['html'] );
		$this->assertStringNotContainsString( self::MARKER, base64_decode( $source['source'], true ) );
	}

	public function test_repeated_definition_uses_one_reference_and_changed_definition_uses_another() {
		$first = \DesignSetGo\Blocks\Query\RefreshSource::sign( 'stable-ref', array( 'source' => 'posts' ), 'PRIVATE_TEMPLATE', 0 );
		$again = \DesignSetGo\Blocks\Query\RefreshSource::sign( 'stable-ref', array( 'source' => 'posts' ), 'PRIVATE_TEMPLATE', 0 );
		$changed = \DesignSetGo\Blocks\Query\RefreshSource::sign( 'stable-ref', array( 'source' => 'posts' ), 'CHANGED_TEMPLATE', 0 );
		$this->assertSame( $first, $again );
		$this->assertNotSame( $first, $changed );
		$this->assertStringNotContainsString( 'PRIVATE_TEMPLATE', base64_decode( $first['source'], true ) );
		$this->assertSame( 'PRIVATE_TEMPLATE', \DesignSetGo\Blocks\Query\RefreshSource::verify( $first['source'], $first['signature'], 'stable-ref' )['innerBlocks'] );
	}

	public function test_fresh_render_renews_existing_reference_lifetime() {
		$first = \DesignSetGo\Blocks\Query\RefreshSource::sign( 'renewed-ref', array(), self::MARKER, 0 );
		$data = json_decode( base64_decode( $first['source'], true ), true );
		$this->assertArrayHasKey( 'ref', $data );
		$key = 'dsgo_query_source_' . $data['ref'];
		update_option( '_transient_timeout_' . $key, time() + 60 );
		$again = \DesignSetGo\Blocks\Query\RefreshSource::sign( 'renewed-ref', array(), self::MARKER, 0 );
		$this->assertSame( $first, $again );
		$this->assertGreaterThan( time() + 29 * DAY_IN_SECONDS, get_option( '_transient_timeout_' . $key ) );
	}

	public function test_missing_expired_or_corrupt_storage_fails_closed() {
		$signed = \DesignSetGo\Blocks\Query\RefreshSource::sign( 'missing-ref', array( 'source' => 'posts' ), self::MARKER, 0 );
		$data = json_decode( base64_decode( $signed['source'], true ), true );
		$this->assertArrayHasKey( 'ref', $data );
		$key = 'dsgo_query_source_' . $data['ref'];
		$definition = get_transient( $key );
		$this->assertIsArray( $definition );
		$this->assertSame( self::MARKER, $definition['innerBlocks'] );
		$this->assertGreaterThan( time(), get_option( '_transient_timeout_' . $key ) );
		$this->assertLessThanOrEqual( time() + 30 * DAY_IN_SECONDS, get_option( '_transient_timeout_' . $key ) );
		delete_transient( $key );
		$this->assertSame( 403, $this->refresh( 'missing-ref', $signed )->get_status() );
		set_transient( $key, array( 'innerBlocks' => self::MARKER ), DAY_IN_SECONDS );
		$this->assertSame( 403, $this->refresh( 'missing-ref', $signed )->get_status() );
		set_transient( $key, $definition, DAY_IN_SECONDS );
		update_option( '_transient_timeout_' . $key, time() - 1 );
		$this->assertSame( 403, $this->refresh( 'missing-ref', $signed )->get_status() );
	}

	public function test_legacy_plaintext_source_is_rejected_even_with_a_valid_signature() {
		$source = base64_encode( wp_json_encode( array( 'v' => 1, 'queryId' => 'legacy', 'sourcePostId' => 0, 'attributes' => array(), 'innerBlocks' => self::MARKER ) ) );
		$signature = hash_hmac( 'sha256', 'designsetgo/query-refresh-source|' . get_current_blog_id() . '|' . $source, wp_salt( 'auth' ) );
		$this->assertNull( \DesignSetGo\Blocks\Query\RefreshSource::verify( $source, $signature, 'legacy' ) );
	}

	public function test_opaque_reference_cannot_be_tampered_rebound_or_replayed_on_another_site() {
		$signed = \DesignSetGo\Blocks\Query\RefreshSource::sign( 'bound-ref', array( 'source' => 'posts' ), self::MARKER, 0 );
		$data = json_decode( base64_decode( $signed['source'], true ), true );
		$this->assertArrayHasKey( 'ref', $data );
		$this->assertNull( \DesignSetGo\Blocks\Query\RefreshSource::verify( $signed['source'], $signed['signature'], 'other-query' ) );
		$data['ref'] = str_repeat( '0', 64 );
		$this->assertNull( \DesignSetGo\Blocks\Query\RefreshSource::verify( base64_encode( wp_json_encode( $data ) ), $signed['signature'], 'bound-ref' ) );
		$blog_id = $GLOBALS['blog_id'];
		$GLOBALS['blog_id'] = $blog_id + 1;
		try {
			$this->assertNull( \DesignSetGo\Blocks\Query\RefreshSource::verify( $signed['source'], $signed['signature'], 'bound-ref' ) );
		} finally {
			$GLOBALS['blog_id'] = $blog_id;
		}
	}
}
