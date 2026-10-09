<?php
/** Collect layout styles outside block markup, for early and late rendering.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/** One deduplicated stylesheet queue per page render. */
class Layout_Renderer {
	/**
	 * Rules not yet printed.
	 *
	 * @var array<string,string>
	 */
	private $pending = array();
	/**
	 * Already printed rules.
	 *
	 * @var array<string,bool>
	 */
	private $printed = array();

	/** Register initial-page and late-render stylesheet output. */
	public function __construct() {
		add_filter( 'render_block_data', array( $this, 'collect_tree' ), 10, 3 );
		add_filter( 'render_block', array( $this, 'collect' ), 20, 2 );
		add_action( 'wp_head', array( $this, 'print_styles' ), 30 );
		add_action( 'wp_footer', array( $this, 'print_styles' ), PHP_INT_MAX );
	}

	/**
	 * Precollect static templates so query refreshes retain layout styles,
	 * even when the first query result is empty or visibility hides an item.
	 * Layout attributes cannot bind to per-item dynamic data.
	 *
	 * @param array          $block Parsed block.
	 * @param array|null     $source_block Unfiltered source block.
	 * @param \WP_Block|null $parent_block Parent instance, or null at the root.
	 * @return array Unmodified parsed block.
	 */
	public function collect_tree( array $block, $source_block = null, $parent_block = null ): array {
		if ( null === $parent_block ) {
			$this->queue_tree( $block );
		}
		return $block;
	}

	/**
	 * Visit a root's static descendants once, avoiding repeated subtree scans.
	 *
	 * @param array $block Parsed block.
	 */
	private function queue_tree( array $block ): void {
		$this->queue( $block );
		foreach ( $block['innerBlocks'] ?? array() as $child ) {
			$this->queue_tree( $child );
		}
	}

	/**
	 * Synchronize static or dynamic wrapper classes, without style siblings.
	 *
	 * @param string $html Rendered block HTML.
	 * @param array  $block Parsed block.
	 * @return string HTML with current optional class.
	 */
	public function collect( string $html, array $block ): string {
		if ( ! isset( $block['attrs']['dsgoLayout'] ) || ! $this->supported( $block ) ) {
			return $html;
		}
		$class     = $this->queue( $block );
		$processor = new \WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag() ) {
			return $html;
		}
		foreach ( preg_split( '/\s+/', (string) $processor->get_attribute( 'class' ) ) as $saved_class ) {
			if ( preg_match( '/^dsgo-layout-[a-z0-9]+$/', $saved_class ) ) {
				$processor->remove_class( $saved_class );
			}
		}
		if ( '' !== $class ) {
			$processor->add_class( $class );
		}
		return $processor->get_updated_html();
	}

	/** Print only new validated rules. A footer flush handles post-head blocks. */
	public function print_styles(): void {
		if ( empty( $this->pending ) ) {
			return;
		}
		// All CSS comes from typed, validated values and fixed selectors.
		// esc_html would corrupt quoted Grid area rows and math operators.
		echo '<style class="dsgo-layout-styles">' . implode( '', $this->pending ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Compiled from the strict layout allowlist; angle brackets are refused.
		$this->printed = array_merge( $this->printed, array_fill_keys( array_keys( $this->pending ), true ) );
		$this->pending = array();
	}

	/**
	 * Queue one validated rule.
	 *
	 * @param array $block Parsed block.
	 * @return string Optional class.
	 */
	private function queue( array $block ): string {
		if ( ! isset( $block['attrs']['dsgoLayout'] ) || ! $this->supported( $block ) ) {
			return '';
		}
		$name  = $block['blockName'];
		$class = Layout_Support::class_name( $name, $block['attrs']['dsgoLayout'] );
		if ( '' !== $class && ! isset( $this->printed[ $class ] ) && ! isset( $this->pending[ $class ] ) ) {
			$this->pending[ $class ] = Layout_Support::compile_css( $name, $block['attrs']['dsgoLayout'] );
		}
		return $class;
	}

	/**
	 * Honor registered extension exclusions and avoid wrapperless blocks.
	 *
	 * @param array $block Parsed block.
	 * @return bool Whether the extension is registered.
	 */
	private function supported( array $block ): bool {
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] ?? '' );
		return $type && isset( $type->attributes['dsgoLayout'] ) && ! Extension_Attributes::is_block_excluded( $type->name );
	}
}
