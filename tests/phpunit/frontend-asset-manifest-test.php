<?php
/**
 * Tests for the soft-navigation asset manifest.
 *
 * @group assets
 */

/**
 * @group assets
 */
class DesignSetGo_Frontend_Asset_Manifest_Test extends WP_UnitTestCase {

	/**
	 * Queue state before the test, restored afterwards.
	 *
	 * @var array
	 */
	private $saved = array();

	public function set_up() {
		parent::set_up();
		// The plugin's real registrations are needed, so keep the registries
		// and only reset what is queued or printed.
		$scripts     = wp_scripts();
		$styles      = wp_styles();
		$this->saved = array( $scripts->queue, $scripts->done, $styles->queue, $styles->done );

		$scripts->queue = array();
		$scripts->done  = array();
		$styles->queue  = array();
		$styles->done   = array();

		do_action( 'wp_enqueue_scripts' );
		// wp_enqueue_scripts may enqueue unrelated assets; start from nothing.
		$scripts->queue = array();
		$styles->queue  = array();
		// Inline data printed by an earlier test must not satisfy this one.
		if ( isset( $scripts->registered['designsetgo-frontend'] ) ) {
			$scripts->registered['designsetgo-frontend']->extra = array();
		}
	}

	public function tear_down() {
		list( wp_scripts()->queue, wp_scripts()->done, wp_styles()->queue, wp_styles()->done ) = $this->saved;
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Find the trigger for a selector or needle.
	 *
	 * @param array  $manifest Built manifest.
	 * @param string $key      'selector' or 'needles'.
	 * @param mixed  $value    Selector string, or one needle.
	 * @return array|null Trigger.
	 */
	private static function trigger( array $manifest, $key, $value ) {
		foreach ( $manifest['triggers'] as $trigger ) {
			if ( 'selector' === $key && ( $trigger['selector'] ?? '' ) === $value ) {
				return $trigger;
			}
			if ( 'needles' === $key && in_array( $value, $trigger['needles'] ?? array(), true ) ) {
				return $trigger;
			}
		}

		return null;
	}

	public function test_blocks_match_on_their_exact_wrapper_class() {
		$manifest = \DesignSetGo\Frontend_Asset_Manifest::build();
		$tabs     = self::trigger( $manifest, 'selector', '.wp-block-designsetgo-tabs' );

		$this->assertNotNull( $tabs );
		$this->assertContains( 'designsetgo-tabs-view-script', $tabs['scripts'] );
		$this->assertContains( 'designsetgo-tabs-style', $tabs['styles'] );
		$this->assertStringContainsString( 'build/blocks/tabs/view.js', $manifest['scripts']['designsetgo-tabs-view-script']['src'] );
		$this->assertStringContainsString( 'ver=', $manifest['styles']['designsetgo-tabs-style']['href'] );
	}

	public function test_extension_bundles_match_on_needles() {
		$manifest   = \DesignSetGo\Frontend_Asset_Manifest::build();
		$animations = self::trigger( $manifest, 'needles', 'has-dsgo-animation' );

		$this->assertSame( array( 'designsetgo-ext-block-animations' ), $animations['scripts'] );
		$this->assertSame( array( 'designsetgo-ext-block-animations' ), $animations['styles'] );
	}

	public function test_assets_already_on_the_page_are_left_out() {
		wp_enqueue_script( 'designsetgo-tabs-view-script' );
		wp_styles()->done[] = 'designsetgo-tabs-style';

		$tabs = self::trigger( \DesignSetGo\Frontend_Asset_Manifest::build(), 'selector', '.wp-block-designsetgo-tabs' );

		$this->assertNull( $tabs, 'Nothing left to load for Tabs.' );
	}

	public function test_missing_dependencies_are_included_with_inline_data() {
		$manifest = \DesignSetGo\Frontend_Asset_Manifest::build();
		$form     = $manifest['scripts']['designsetgo-form-builder-view-script'];

		// wp-i18n is not on the page, so it (and wp-hooks) must load first.
		$this->assertContains( 'wp-i18n', $form['deps'] );
		$this->assertArrayHasKey( 'wp-i18n', $manifest['scripts'] );
		$this->assertArrayHasKey( 'wp-hooks', $manifest['scripts'] );
		$this->assertStringContainsString( 'wp-includes/js/dist/i18n', $manifest['scripts']['wp-i18n']['src'] );

		// The localized REST config view.js reads.
		$this->assertStringContainsString( 'designsetgoForm', $form['before'] );
	}

	public function test_dependencies_on_the_page_are_not_repeated() {
		wp_enqueue_script( 'wp-i18n' );

		$manifest = \DesignSetGo\Frontend_Asset_Manifest::build();

		$this->assertContains( 'wp-i18n', $manifest['scripts']['designsetgo-form-builder-view-script']['deps'] );
		$this->assertArrayNotHasKey( 'wp-i18n', $manifest['scripts'] );
		$this->assertArrayNotHasKey( 'wp-hooks', $manifest['scripts'] );
	}

	public function test_query_script_module_is_not_listed() {
		$query = self::trigger( \DesignSetGo\Frontend_Asset_Manifest::build(), 'selector', '.wp-block-designsetgo-query' );

		$this->assertEmpty( $query['scripts'] ?? array(), 'A script module cannot be added after load.' );
	}

	/**
	 * Print the manifest as a given user and return the runtime's inline data.
	 *
	 * @param int $user_id User to print as (0 = visitor).
	 * @return string Inline "before" data on designsetgo-frontend.
	 */
	private function print_as( $user_id ) {
		wp_register_script( 'designsetgo-frontend', 'https://example.com/frontend.js', array(), '1', true );
		wp_enqueue_script( 'designsetgo-frontend' );
		wp_set_current_user( $user_id );

		( new \DesignSetGo\Frontend_Asset_Manifest() )->print_manifest();

		return (string) wp_scripts()->get_inline_script_data( 'designsetgo-frontend', 'before' );
	}

	/**
	 * The manifest carries localized script data (Form Builder's nonces), so
	 * visitors must never get it, even on a Query page.
	 */
	public function test_not_printed_for_visitors() {
		apply_filters( 'render_block_designsetgo/query', '<div></div>', array( 'innerBlocks' => array() ) );

		$this->assertStringNotContainsString( 'dsgoAssets', $this->print_as( 0 ) );
	}

	public function test_not_printed_for_users_who_cannot_edit() {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertStringNotContainsString( 'dsgoAssets', $this->print_as( $subscriber ) );
	}

	public function test_printed_for_editors() {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );

		$this->assertStringContainsString( 'window.dsgoAssets = {', $this->print_as( $editor ) );
	}

	public function test_integrations_can_opt_in_through_the_filter() {
		add_filter( 'designsetgo_frontend_asset_manifest', '__return_true' );
		try {
			$this->assertStringContainsString( 'window.dsgoAssets = {', $this->print_as( 0 ) );
		} finally {
			remove_filter( 'designsetgo_frontend_asset_manifest', '__return_true' );
		}
	}
}
