<?php
/**
 * Public Markdown audience and recursive visibility regression tests.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use DesignSetGo\Markdown\Converter;
use DesignSetGo\LLMS_Txt\Generator;
use DesignSetGo\LLMS_Txt\File_Manager;
use DesignSetGo\Admin\Settings;

/** Tests the public conversion boundary instead of HTML render filters. */
class Test_Markdown_Visibility extends \WP_UnitTestCase {
	/** Reset ambient state after every conversion test. */
	public function tear_down() {
		wp_set_current_user( 0 );
		Settings::invalidate_cache();
		parent::tear_down();
	}

	/** Build real stored block comments, including nested blocks. */
	private function block( string $name, string $text, array $rules = array(), array $children = array() ): string {
		return serialize_block(
			array(
				'blockName'    => $name,
				'attrs'        => $rules ? array( 'dsgoVisibility' => $rules ) : array(),
				'innerHTML'    => '<p>' . $text . '</p>',
				'innerBlocks'  => $children,
				'innerContent' => array_merge( array( '<p>' . $text . '</p>' ), array_fill( 0, count( $children ), null ) ),
			)
		);
	}

	/** Authentication rules used by the actual shared evaluator. */
	private function auth( bool $logged_in ): array {
		return array( 'operator' => 'AND', 'rules' => array( array( 'type' => 'auth', 'value' => $logged_in ) ) );
	}

	/** Published page conversion, regardless of caller privileges. */
	private function post( string $content ): \WP_Post {
		return self::factory()->post->create_and_get( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_excerpt' => '', 'post_content' => $content ) );
	}

	/** Hidden ancestors and list descendants must never reach handlers. */
	public function test_anonymous_conversion_gates_hidden_parents_and_list_children() {
		$hidden_child = parse_blocks( $this->block( 'core/paragraph', 'HIDDEN_PARENT_CHILD' ) )[0];
		$list_child   = parse_blocks( $this->block( 'core/list-item', 'HIDDEN_LIST_CHILD', $this->auth( true ) ) )[0];
		$content      = $this->block( 'core/group', 'HIDDEN_PARENT', $this->auth( true ), array( $hidden_child ) );
		$content     .= $this->block( 'core/list', '', array(), array( $list_child ) );
		$content     .= $this->block( 'core/paragraph', 'PUBLIC_VISITOR', $this->auth( false ) );
		$markdown     = ( new Converter() )->convert( $this->post( $content ) );
		$this->assertStringNotContainsString( 'HIDDEN_', $markdown );
		$this->assertStringContainsString( 'PUBLIC_VISITOR', $markdown );
	}

	/** Save hooks and regeneration by an administrator still export anonymously. */
	public function test_admin_conversion_uses_public_audience_and_restores_user() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$post = $this->post( $this->block( 'core/paragraph', 'MEMBERS_ONLY', $this->auth( true ) ) . $this->block( 'core/paragraph', 'PUBLIC_ONLY', $this->auth( false ) ) );
		$markdown = ( new Converter() )->convert( $post );
		$this->assertStringNotContainsString( 'MEMBERS_ONLY', $markdown );
		$this->assertStringContainsString( 'PUBLIC_ONLY', $markdown );
		$this->assertSame( $admin, get_current_user_id() );
	}

	/** Meta/taxonomy rules use the converted post, not the ambient global post. */
	public function test_conversion_uses_explicit_post_context() {
		$rules = array( 'operator' => 'AND', 'rules' => array( array( 'type' => 'meta', 'key' => 'audience', 'op' => 'equals', 'value' => 'public' ), array( 'type' => 'taxonomy', 'taxonomy' => 'category', 'value' => 'export-visible' ) ) );
		$post = self::factory()->post->create_and_get( array( 'post_status' => 'publish', 'post_excerpt' => '', 'post_content' => $this->block( 'core/paragraph', 'VISIBLE_META', $rules ) ) );
		update_post_meta( $post->ID, 'audience', 'public' );
		$term = self::factory()->category->create( array( 'slug' => 'export-visible' ) );
		wp_set_post_categories( $post->ID, array( $term ) );
		$original = $GLOBALS['post'] ?? null;
		$GLOBALS['post'] = $this->post( '' );
		try {
			$this->assertStringContainsString( 'VISIBLE_META', ( new Converter() )->convert( $post ) );
			update_post_meta( $post->ID, 'audience', 'private' );
			$this->assertStringNotContainsString( 'VISIBLE_META', ( new Converter() )->convert( $post ) );
		} finally {
			$GLOBALS['post'] = $original;
		}
	}

	/** Exceptions cannot leave the caller anonymous or carry context to the next call. */
	public function test_conversion_restores_user_after_handler_exception() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$converter = new Converter();
		$converter->register_handler( 'test/throw', function () {
			$this->assertSame( 0, get_current_user_id() );
			throw new \RuntimeException( 'handler failed' );
		} );
		try {
			$converter->convert( $this->post( $this->block( 'test/throw', '' ) ) );
			$this->fail( 'Expected the handler exception.' );
		} catch ( \RuntimeException $error ) {
			$this->assertSame( 'handler failed', $error->getMessage() );
		}
		$this->assertSame( $admin, get_current_user_id() );
	}

	/** A custom handler can re-enter the same converter without changing its post context. */
	public function test_nested_conversion_restores_outer_context() {
		$converter = new Converter();
		$nested = $this->post( '' );
		$converter->register_handler( 'test/nested', function () use ( $converter, $nested ) {
			$converter->convert( $nested );
			return '';
		} );
		$rules = array( 'rules' => array( array( 'type' => 'meta', 'key' => 'audience', 'value' => 'public' ) ) );
		$post = $this->post( $this->block( 'test/nested', '' ) . $this->block( 'core/paragraph', 'OUTER_CONTEXT', $rules ) );
		update_post_meta( $post->ID, 'audience', 'public' );
		$this->assertStringContainsString( 'OUTER_CONTEXT', $converter->convert( $post ) );
	}

	/** The llms.txt description must not reintroduce hidden text from raw post content. */
	public function test_index_excerpt_uses_visible_content() {
		$post = $this->post( $this->block( 'core/paragraph', 'HIDDEN EXCERPT', $this->auth( true ) ) . $this->block( 'core/paragraph', 'VISIBLE EXCERPT' ) );
		update_option( 'designsetgo_settings', array( 'llms_txt' => array( 'enable' => true, 'post_types' => array( 'page' ) ) ) );
		Settings::invalidate_cache();
		$output = ( new Generator( new File_Manager() ) )->generate_content();
		$this->assertStringNotContainsString( 'HIDDEN EXCERPT', $output );
		$this->assertStringContainsString( 'VISIBLE EXCERPT', $output );
	}
	/** Even a custom handler reading the raw child tree sees only visible descendants. */
	public function test_custom_handler_receives_pruned_descendants() {
		$converter = new Converter();
		$converter->register_handler( 'test/raw-tree', function ( $block ) {
			return serialize_block( $block );
		} );
		$secret = parse_blocks( $this->block( 'core/paragraph', 'HIDDEN_CHILD', $this->auth( true ) ) )[0];
		$public = parse_blocks( $this->block( 'core/paragraph', 'PUBLIC_CHILD' ) )[0];
		$post = $this->post( $this->block( 'test/raw-tree', '', array(), array( $secret, $public ) ) );
		$output = $converter->convert( $post );
		$this->assertStringNotContainsString( 'HIDDEN_CHILD', $output );
		$this->assertStringContainsString( 'PUBLIC_CHILD', $output );
		$this->assertSame( 1, substr_count( $output, '<!-- wp:paragraph' ) );
	}

}
