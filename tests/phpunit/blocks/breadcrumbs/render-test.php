<?php
/**
 * Tests for the breadcrumbs block server render.
 *
 * Regression guard for the PrefixAllGlobals refactor that wrapped render.php in
 * designsetgo_render_breadcrumbs(). The trail is built from
 * $block->context['postId'], so we hand the render a lightweight context object
 * pointing at a real page and assert the <nav> markup is emitted.
 *
 * @group breadcrumbs
 */

/**
 * @group breadcrumbs
 */
class DesignSetGo_Breadcrumbs_Render_Test extends WP_UnitTestCase {

	/**
	 * Include the built render template with the given attributes/context and
	 * capture its echoed output.
	 *
	 * @param array       $attributes Block attributes.
	 * @param object|null $block      Block instance (carries context).
	 * @return string Rendered HTML ('' when the template bails).
	 */
	private function render( array $attributes, $block = null ) {
		$path = DESIGNSETGO_PATH . 'build/blocks/breadcrumbs/render.php';
		$this->assertFileExists( $path, 'Run `npm run build` before PHPUnit — render templates are served from build/.' );

		$content = '';

		$previous_block                     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'designsetgo/breadcrumbs',
			'attrs'     => array(),
		);

		ob_start();
		$returned = include $path;
		$html     = ob_get_clean();

		WP_Block_Supports::$block_to_render = $previous_block;

		return is_string( $returned ) ? $returned : $html;
	}

	/**
	 * Build a minimal block-context stand-in (duck-typed: render reads
	 * $block->context['postId']).
	 *
	 * @param array $context Context map.
	 * @return object
	 */
	private function block_with_context( array $context ) {
		$block          = new stdClass();
		$block->context = $context;
		return $block;
	}

	public function test_renders_nav_with_home_and_current_for_a_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'About Our Team',
				'post_status' => 'publish',
			)
		);

		$html = $this->render(
			array(
				'showHome'    => true,
				'showCurrent' => true,
			),
			$this->block_with_context( array( 'postId' => $page_id ) )
		);

		$this->assertStringContainsString( '<nav', $html );
		$this->assertStringContainsString( 'dsgo-breadcrumbs', $html );
		$this->assertStringContainsString( 'About Our Team', $html );
		$this->assertStringContainsString( 'Home', $html );
	}

	public function test_post_trail_includes_parent_categories() {
		$parent  = self::factory()->category->create( array( 'name' => 'Guides' ) );
		$child   = self::factory()->category->create(
			array(
				'name'   => 'Gardening',
				'parent' => $parent,
			)
		);
		$post_id = self::factory()->post->create( array( 'post_title' => 'Planting Tomatoes' ) );
		wp_set_post_categories( $post_id, array( $child ) );

		$titles = wp_list_pluck(
			designsetgo_get_breadcrumb_trail(
				$this->block_with_context( array( 'postId' => $post_id ) ),
				array(
					'showHome'    => true,
					'showCurrent' => true,
				)
			),
			'title'
		);

		$this->assertSame( array( 'Home', 'Guides', 'Gardening', 'Planting Tomatoes' ), $titles );
	}

	public function test_custom_post_type_trail_uses_archive_and_hierarchical_taxonomy() {
		register_post_type(
			'dsgo_recipe',
			array(
				'public'      => true,
				'has_archive' => true,
				'label'       => 'Recipes',
			)
		);
		register_taxonomy(
			'dsgo_cuisine',
			'dsgo_recipe',
			array(
				'public'       => true,
				'hierarchical' => true,
			)
		);

		try {
			$region  = self::factory()->term->create(
				array(
					'taxonomy' => 'dsgo_cuisine',
					'name'     => 'Asian',
				)
			);
			$cuisine = self::factory()->term->create(
				array(
					'taxonomy' => 'dsgo_cuisine',
					'name'     => 'Thai',
					'parent'   => $region,
				)
			);
			$post_id = self::factory()->post->create(
				array(
					'post_type'  => 'dsgo_recipe',
					'post_title' => 'Green Curry',
				)
			);
			wp_set_object_terms( $post_id, array( $cuisine ), 'dsgo_cuisine' );

			$trail = designsetgo_get_breadcrumb_trail(
				$this->block_with_context( array( 'postId' => $post_id ) ),
				array(
					'showHome'    => true,
					'showCurrent' => true,
				)
			);

			$this->assertSame( array( 'Home', 'Recipes', 'Asian', 'Thai', 'Green Curry' ), wp_list_pluck( $trail, 'title' ) );
			$this->assertSame( get_post_type_archive_link( 'dsgo_recipe' ), $trail[1]['url'] );
		} finally {
			unregister_taxonomy( 'dsgo_cuisine' );
			unregister_post_type( 'dsgo_recipe' );
		}
	}

	public function test_hierarchical_custom_post_type_trail_lists_ancestors() {
		register_post_type(
			'dsgo_doc',
			array(
				'public'       => true,
				'hierarchical' => true,
			)
		);

		try {
			$parent  = self::factory()->post->create(
				array(
					'post_type'  => 'dsgo_doc',
					'post_title' => 'Getting Started',
				)
			);
			$post_id = self::factory()->post->create(
				array(
					'post_type'   => 'dsgo_doc',
					'post_title'  => 'Installation',
					'post_parent' => $parent,
				)
			);

			$titles = wp_list_pluck(
				designsetgo_get_breadcrumb_trail(
					$this->block_with_context( array( 'postId' => $post_id ) ),
					array( 'showCurrent' => true )
				),
				'title'
			);

			$this->assertSame( array( 'Getting Started', 'Installation' ), $titles );
		} finally {
			unregister_post_type( 'dsgo_doc' );
		}
	}

	public function test_product_trail_uses_product_categories() {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$category = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Mugs',
			)
		);
		$post_id  = self::factory()->post->create(
			array(
				'post_type'  => 'product',
				'post_title' => 'Blue Mug',
			)
		);
		wp_set_object_terms( $post_id, array( $category ), 'product_cat' );

		$titles = wp_list_pluck(
			designsetgo_get_breadcrumb_trail(
				$this->block_with_context( array( 'postId' => $post_id ) ),
				array( 'showCurrent' => true )
			),
			'title'
		);

		$this->assertContains( 'Mugs', $titles );
		$this->assertSame( 'Blue Mug', end( $titles ) );
	}

	public function test_trail_taxonomy_is_filterable() {
		$category = self::factory()->category->create( array( 'name' => 'News' ) );
		$post_id  = self::factory()->post->create( array( 'post_title' => 'Launch Day' ) );
		wp_set_post_categories( $post_id, array( $category ) );

		add_filter( 'designsetgo_breadcrumbs_taxonomy', '__return_empty_string' );

		$titles = wp_list_pluck(
			designsetgo_get_breadcrumb_trail(
				$this->block_with_context( array( 'postId' => $post_id ) ),
				array( 'showCurrent' => true )
			),
			'title'
		);

		$this->assertSame( array( 'Launch Day' ), $titles );
	}

	public function test_prints_breadcrumb_schema_once_for_the_viewed_post() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Pricing </script><b>x</b>',
			)
		);
		$this->go_to( get_permalink( $page_id ) );

		$block = $this->block_with_context( array( 'postId' => $page_id ) );
		$first = $this->render( array( 'showCurrent' => true ), $block );
		$again = $this->render( array( 'showCurrent' => true ), $block );

		$this->assertSame( 1, preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $first, $match ) );
		$schema = json_decode( $match[1], true );

		$this->assertSame( 'BreadcrumbList', $schema['@type'] );
		$this->assertSame( 1, $schema['itemListElement'][0]['position'] );
		$this->assertStringNotContainsString( '</script><b>', $first, 'A title must not close the JSON-LD script early.' );
		$this->assertStringNotContainsString( 'application/ld+json', $again, 'Only the first breadcrumbs block prints schema.' );
	}

	public function test_schema_names_are_decoded_text() {
		// WordPress stores and returns these HTML-encoded (`It&#8217;s`,
		// `A &amp; B`); JSON-LD is read literally, so they must be decoded.
		$category = self::factory()->category->create( array( 'name' => 'Arts & Crafts' ) );
		$post_id  = self::factory()->post->create( array( 'post_title' => "It's a test & more" ) );
		wp_set_post_categories( $post_id, array( $category ) );
		$this->go_to( get_permalink( $post_id ) );

		$html = $this->render( array( 'showCurrent' => true ), $this->block_with_context( array( 'postId' => $post_id ) ) );

		$this->assertSame( 1, preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $html, $match ) );
		$names = wp_list_pluck( json_decode( $match[1], true )['itemListElement'], 'name' );

		$this->assertContains( 'Arts & Crafts', $names );
		$this->assertContains( "It\u{2019}s a test & more", $names, 'wptexturize() curls the apostrophe; the entity must not survive.' );
		$this->assertStringNotContainsString( '&#', $match[1] );
		$this->assertStringNotContainsString( '&amp;', $match[1] );
	}

	public function test_schema_can_be_turned_off_for_seo_plugins() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $page_id ) );
		add_filter( 'designsetgo_breadcrumbs_schema_enabled', '__return_false' );

		$html = $this->render( array( 'showCurrent' => true ), $this->block_with_context( array( 'postId' => $page_id ) ) );

		$this->assertStringContainsString( 'dsgo-breadcrumbs', $html, 'The visible trail still renders.' );
		$this->assertStringNotContainsString( 'application/ld+json', $html );
	}

	public function test_skips_schema_for_a_loop_item_that_is_not_the_viewed_post() {
		$page_id  = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$other_id = self::factory()->post->create( array( 'post_title' => 'Some Loop Item' ) );
		$this->go_to( get_permalink( $page_id ) );

		$html = $this->render(
			array( 'showCurrent' => true ),
			$this->block_with_context( array( 'postId' => $other_id ) )
		);

		$this->assertStringContainsString( 'Some Loop Item', $html );
		$this->assertStringNotContainsString( 'application/ld+json', $html );
	}

	public function test_renders_nothing_without_post_context() {
		// No postId in context -> empty trail -> the template returns early.
		$html = $this->render(
			array(
				'showHome'    => true,
				'showCurrent' => true,
			),
			$this->block_with_context( array() )
		);

		$this->assertSame( '', $html );
	}

	public function test_trail_omits_ancestors_the_visitor_cannot_read() {
		$parent = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Secret Draft Parent',
				'post_status' => 'draft',
			)
		);
		$child  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Public Child',
				'post_parent' => $parent,
			)
		);
		wp_set_current_user( 0 );

		$block  = $this->block_with_context( array( 'postId' => $child ) );
		$trail  = designsetgo_get_breadcrumb_trail( $block, array( 'showCurrent' => true ) );
		$titles = wp_list_pluck( $trail, 'title' );

		$this->assertSame( array( 'Public Child' ), $titles );

		$this->go_to( get_permalink( $child ) );
		$html = $this->render( array( 'showCurrent' => true ), $block );
		$this->assertStringNotContainsString( 'Secret Draft Parent', $html );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$titles = wp_list_pluck( designsetgo_get_breadcrumb_trail( $block, array( 'showCurrent' => true ) ), 'title' );
		$this->assertSame( array( 'Secret Draft Parent', 'Public Child' ), $titles );
	}

	public function test_term_choice_is_the_lowest_term_id_not_the_first_by_name() {
		$zebra = self::factory()->category->create( array( 'name' => 'Zebra' ) );
		$apple = self::factory()->category->create( array( 'name' => 'Apple' ) );
		$post  = self::factory()->post->create( array( 'post_title' => 'Two Cats' ) );
		wp_set_post_categories( $post, array( $apple, $zebra ) );

		$titles = wp_list_pluck(
			designsetgo_get_breadcrumb_trail(
				$this->block_with_context( array( 'postId' => $post ) ),
				array( 'showCurrent' => true )
			),
			'title'
		);

		$this->assertSame( array( 'Zebra', 'Two Cats' ), $titles );
	}

	public function test_separator_mirrors_directional_glyphs_in_rtl() {
		$this->assertSame( '›', designsetgo_get_breadcrumb_separator( array( 'separator' => 'chevron' ) ) );

		$GLOBALS['wp_locale']->text_direction = 'rtl';
		try {
			$this->assertSame( '‹', designsetgo_get_breadcrumb_separator( array( 'separator' => 'chevron' ) ) );
			$this->assertSame( '<', designsetgo_get_breadcrumb_separator( array( 'separator' => 'greater' ) ) );
			$this->assertSame( '←', designsetgo_get_breadcrumb_separator( array( 'separator' => 'arrow-right' ) ) );
			$this->assertSame( '/', designsetgo_get_breadcrumb_separator( array( 'separator' => 'slash' ) ) );
		} finally {
			$GLOBALS['wp_locale']->text_direction = 'ltr';
		}
	}

	public function test_category_archive_trail_has_term_ancestors() {
		$parent = self::factory()->category->create( array( 'name' => 'Guides' ) );
		$child  = self::factory()->category->create(
			array(
				'name'   => 'Gardening',
				'parent' => $parent,
			)
		);
		self::factory()->post->create( array( 'post_category' => array( $child ) ) );
		$this->go_to( get_category_link( $child ) );

		$trail = designsetgo_get_breadcrumb_trail(
			$this->block_with_context( array() ),
			array(
				'showHome'    => true,
				'showCurrent' => true,
			)
		);

		$this->assertSame( array( 'Home', 'Guides', 'Gardening' ), wp_list_pluck( $trail, 'title' ) );
		$this->assertTrue( $trail[2]['is_current'] );

		$html = $this->render(
			array(
				'showHome'    => true,
				'showCurrent' => true,
			),
			$this->block_with_context( array() )
		);
		$this->assertStringContainsString( 'Gardening', $html );
		$this->assertStringContainsString( 'application/ld+json', $html );
	}

	public function test_search_and_404_trails() {
		$this->go_to( home_url( '/?s=tomatoes' ) );
		$titles = wp_list_pluck(
			designsetgo_get_breadcrumb_trail(
				$this->block_with_context( array() ),
				array(
					'showHome'    => true,
					'showCurrent' => true,
				)
			),
			'title'
		);
		$this->assertSame( array( 'Home', 'Search results for “tomatoes”' ), $titles );

		$this->go_to( home_url( '/?p=999999' ) );
		$titles = wp_list_pluck(
			designsetgo_get_breadcrumb_trail(
				$this->block_with_context( array() ),
				array(
					'showHome'    => true,
					'showCurrent' => true,
				)
			),
			'title'
		);
		$this->assertSame( array( 'Home', 'Page not found' ), $titles );
	}

	public function test_loop_item_on_an_archive_still_describes_its_post() {
		$cat  = self::factory()->category->create( array( 'name' => 'News' ) );
		$post = self::factory()->post->create( array( 'post_title' => 'Loop Item' ) );
		$this->go_to( get_category_link( $cat ) );

		$titles = wp_list_pluck(
			designsetgo_get_breadcrumb_trail(
				$this->block_with_context(
					array(
						'postId'  => $post,
						'queryId' => 1,
					)
				),
				array( 'showCurrent' => true )
			),
			'title'
		);

		$this->assertContains( 'Loop Item', $titles );
	}
}
