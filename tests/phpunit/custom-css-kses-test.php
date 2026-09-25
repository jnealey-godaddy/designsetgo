<?php
/**
 * Custom CSS capability gate tests.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DesignSetGo\Custom_CSS_Kses;

/**
 * Custom CSS kses test case.
 */
class Test_Custom_CSS_Kses extends WP_UnitTestCase {

	/**
	 * Block with custom CSS alongside another attribute.
	 */
	const WITH_CSS = '<!-- wp:paragraph {"dsgoCustomCSS":"body{display:none}","align":"center"} --><p class="has-text-align-center">Hi</p><!-- /wp:paragraph -->';

	/**
	 * Tear down: restore the anonymous user so filters reset.
	 */
	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Save content as a user with the given role and return what was stored.
	 *
	 * @param string $role    Role.
	 * @param string $content Post content.
	 * @return string Stored post content.
	 */
	private function save_as( $role, $content ) {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );

		$post_id = wp_insert_post(
			array(
				'post_author'  => $user_id,
				'post_status'  => 'draft',
				'post_title'   => 'CSS gate',
				'post_content' => wp_slash( $content ),
			)
		);

		return get_post( $post_id )->post_content;
	}

	public function test_author_cannot_save_custom_css() {
		$stored = $this->save_as( 'author', self::WITH_CSS );

		$this->assertStringNotContainsString( 'dsgoCustomCSS', $stored );
		$this->assertStringNotContainsString( 'display:none', $stored );
		// Other attributes and the markup survive untouched.
		$this->assertStringContainsString( '<!-- wp:paragraph {"align":"center"} -->', $stored );
		$this->assertStringContainsString( '<p class="has-text-align-center">Hi</p>', $stored );
	}

	public function test_contributor_cannot_save_custom_css() {
		$stored = $this->save_as( 'contributor', self::WITH_CSS );

		$this->assertStringNotContainsString( 'dsgoCustomCSS', $stored );
	}

	public function test_editor_with_edit_css_keeps_custom_css() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'edit_css is super-admin only on multisite.' );
		}

		$stored = $this->save_as( 'editor', self::WITH_CSS );

		$this->assertStringContainsString( '"dsgoCustomCSS":"body{display:none}"', $stored );
	}

	public function test_attribute_only_comment_loses_its_json_entirely() {
		$stored = $this->save_as(
			'author',
			'<!-- wp:paragraph {"dsgoCustomCSS":"selector{color:red}"} --><p>Hi</p><!-- /wp:paragraph -->'
		);

		$this->assertStringContainsString( '<!-- wp:paragraph --><p>Hi</p>', $stored );
	}

	public function test_nested_and_void_blocks_are_stripped() {
		$stored = $this->save_as(
			'author',
			'<!-- wp:group {"dsgoCustomCSS":"a{}"} --><div class="wp-block-group"><!-- wp:separator {"dsgoCustomCSS":"b{}"} /--></div><!-- /wp:group -->'
		);

		$this->assertStringNotContainsString( 'dsgoCustomCSS', $stored );
		$this->assertCount( 1, parse_blocks( $stored ) );
	}

	public function test_unicode_escaped_attribute_name_is_stripped() {
		// json_decode() turns d into "d", so parse_blocks() would hand
		// the renderer a dsgoCustomCSS attribute from this comment.
		$stored = $this->save_as(
			'author',
			'<!-- wp:paragraph {"dsgoCustomCSS":"body{display:none}"} --><p>Hi</p><!-- /wp:paragraph -->'
		);

		$blocks = parse_blocks( $stored );
		$this->assertArrayNotHasKey( 'dsgoCustomCSS', $blocks[0]['attrs'] );
	}

	public function test_content_without_blocks_is_returned_unchanged() {
		$this->assertSame( 'plain dsgoCustomCSS text', Custom_CSS_Kses::strip( 'plain dsgoCustomCSS text' ) );
	}
}
