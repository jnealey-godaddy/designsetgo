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

		// Custom state — the block owns its animation. For a static block the
		// save filter already baked it into the stored markup; a dynamic block
		// has no save output for that filter to touch, so its settings only
		// reach the frontend if they are applied here.
		if ( ! empty( $attrs['dsgoAnimationEnabled'] ) || ! empty( $attrs['dsgoSvgDraw'] ) ) {
			return $this->apply_parts(
				$block_content,
				designsetgo_get_animation_parts( $attrs ),
				$block['blockName']
			);
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

		return $this->apply_parts( $block_content, $parts );
	}

	/**
	 * Write animation classes/attributes onto a rendered block's root tag.
	 *
	 * @param string      $block_content Rendered block HTML.
	 * @param array       $parts         Output of designsetgo_get_animation_parts().
	 * @param string|null $block_name    Block name, when the caller needs the
	 *                                   static/dynamic distinction below.
	 * @return string Possibly-modified HTML.
	 */
	private function apply_parts( $block_content, $parts, $block_name = null ) {
		if ( empty( $parts['classes'] ) && empty( $parts['attrs'] ) ) {
			return $block_content;
		}

		// A static block's markup is authored by the save filter and validated
		// against it in the editor. Touching it here would both duplicate the
		// classes and desync stored content from save(). Only server-rendered
		// blocks, which that filter cannot reach, are ours to modify.
		if ( null !== $block_name && ! $this->is_dynamic( $block_name ) ) {
			return $block_content;
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( ! $processor->next_tag() ) {
			return $block_content;
		}

		// Never double-apply. This is what keeps hybrid block types honest:
		// core/button and friends declare a render_callback *and* a save(),
		// so they read as dynamic while their stored markup already carries
		// whatever the save filter baked. Seeing it here means the save path
		// won, and this one must not touch it.
		if ( $processor->has_class( 'has-dsgo-animation' )
			|| null !== $processor->get_attribute( 'data-dsgo-svg-draw' ) ) {
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

	/**
	 * Whether a block type renders on the server.
	 *
	 * An unregistered type is treated as static: without a registration there
	 * is nothing to prove it renders server-side, and leaving markup alone is
	 * the safe default.
	 *
	 * @param string $block_name Block name.
	 * @return bool True when the type has a render callback.
	 */
	private function is_dynamic( $block_name ) {
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

		return $type instanceof \WP_Block_Type && $type->is_dynamic();
	}
}
