<?php
/**
 * Applies global per-block-type animation defaults at render time.
 *
 * Blocks in the "inherit" state (no per-block animation, not opted out) whose
 * type has a configured default get the same classes/data-attributes the save
 * path bakes for hand-authored animations, via WP_HTML_Tag_Processor.
 *
 * @package DesignSetGo
 * @since 2.6.0
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/**
 * Render-time injector for global animation defaults.
 */
class Animation_Defaults_Injector {

	/**
	 * Cached effective defaults for the current request.
	 *
	 * Reused across every block in a single request. get_effective() reads
	 * cached settings but re-merges the global+admin lists into a map on each
	 * call; caching the result on this instance (one per request) avoids
	 * repeating that merge for every rendered block.
	 *
	 * @var array|null
	 */
	private $effective = null;

	/**
	 * Register hooks.
	 */
	public function init() {
		// Priority 9: must run BEFORE Assets::maybe_enqueue_frontend_on_render()
		// (registered at the default priority 10). That method decides whether
		// to enqueue the frontend CSS/JS bundle by scanning the already-rendered
		// $block_content for a "dsgo-" substring. If this injector ran at 10 too,
		// registration order would put the enqueue check first, so it would see
		// pre-injection markup (no "dsgo-" yet) and skip enqueuing — leaving an
		// inherited animation's classes/data-attributes on the page with no CSS
		// keyframes or IntersectionObserver JS to drive them.
		add_filter( 'render_block', array( $this, 'inject' ), 9, 2 );
	}

	/**
	 * Inject inherited animation markup onto a rendered block.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block (blockName, attrs, ...).
	 * @return string Possibly-modified HTML.
	 */
	public function inject( $block_content, $block ) {
		if ( is_admin() ) {
			return $block_content;
		}
		if ( empty( $block['blockName'] ) || '' === trim( (string) $block_content ) ) {
			return $block_content;
		}

		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		// Custom state — block owns its animation (already baked at save).
		if ( ! empty( $attrs['dsgoAnimationEnabled'] ) ) {
			return $block_content;
		}
		// Off state — explicit opt-out.
		if ( ! empty( $attrs['dsgoAnimationOptOut'] ) ) {
			return $block_content;
		}

		// Respect the same exclusions as the block-animations extension:
		// its own skip list (single-sourced from the extension config) plus
		// the user-configured excludedBlocks.
		if ( Extension_Attributes::is_block_excluded( $block['blockName'], Extension_Attributes::get_extension_exclusions( 'block-animations' ) ) ) {
			return $block_content;
		}

		if ( null === $this->effective ) {
			$this->effective = Animation_Defaults::get_effective();
		}

		$config = Animation_Defaults::resolve_from_map( $this->effective, $block['blockName'] );
		if ( null === $config ) {
			return $block_content;
		}

		$parts = designsetgo_get_animation_parts(
			array(
				'dsgoAnimationEnabled'  => true,
				'dsgoEntranceAnimation' => $config['entrance'],
				'dsgoExitAnimation'     => $config['exit'],
				'dsgoAnimationTrigger'  => $config['trigger'],
				'dsgoAnimationDuration' => $config['duration'],
				'dsgoAnimationDelay'    => $config['delay'],
				'dsgoAnimationEasing'   => $config['easing'],
				'dsgoAnimationOffset'   => $config['offset'],
				'dsgoAnimationOnce'     => $config['once'],
			)
		);

		if ( empty( $parts['classes'] ) ) {
			return $block_content;
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( ! $processor->next_tag() ) {
			return $block_content;
		}

		// Belt-and-suspenders: never double-apply.
		if ( $processor->has_class( 'has-dsgo-animation' ) ) {
			return $block_content;
		}

		foreach ( $parts['classes'] as $class ) {
			$processor->add_class( $class );
		}
		foreach ( $parts['attrs'] as $key => $value ) {
			$processor->set_attribute( $key, $value );
		}

		return $processor->get_updated_html();
	}
}
