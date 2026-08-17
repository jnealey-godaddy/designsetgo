<?php
/**
 * Interaction Layers - Server-side attribute injection.
 *
 * Static blocks get `data-dsgo-interactions` from the JS
 * `blocks.getSaveContent.extraProps` filter at save time. Dynamic blocks
 * cannot: their `save()` returns null, so that filter never runs and the
 * attribute would never reach the frontend. Since the panel is offered on
 * every block, the feature would silently do nothing on the 28 blocks this
 * plugin renders server-side, plus every core dynamic block.
 *
 * This class closes that gap by writing the attribute onto the rendered
 * markup instead.
 *
 * @package DesignSetGo
 * @since   2.7.0
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/**
 * Injects the interactions data attribute into server-rendered block markup.
 */
class Interactions {

	/**
	 * Constructor — registers the render filter.
	 *
	 * Priority 5 so the attribute exists before Assets::maybe_enqueue_frontend_on_render()
	 * looks for it at priority 10 to decide whether to enqueue the runtime.
	 */
	public function __construct() {
		add_filter( 'render_block', array( $this, 'inject_interactions' ), 5, 2 );
	}

	/**
	 * Write the interactions attribute onto a block's outermost tag.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block, including attrs.
	 * @return string Block HTML, with the attribute added when needed.
	 */
	public function inject_interactions( $block_content, $block ) {
		if ( empty( $block_content ) || ! is_string( $block_content ) ) {
			return $block_content;
		}

		$interactions = isset( $block['attrs']['dsgoInteractions'] )
			? $block['attrs']['dsgoInteractions']
			: null;

		if ( ! is_array( $interactions ) || array() === $interactions ) {
			return $block_content;
		}

		if ( ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $block_content;
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );

		if ( ! $processor->next_tag() ) {
			return $block_content;
		}

		// A static block already carries the attribute from save(). Writing it
		// again would be harmless but pointless, and re-encoding risks drift
		// between the stored markup and what we would produce here.
		if ( null !== $processor->get_attribute( 'data-dsgo-interactions' ) ) {
			return $block_content;
		}

		$json = wp_json_encode( self::sanitize( $interactions ) );

		if ( false === $json ) {
			return $block_content;
		}

		$processor->set_attribute( 'data-dsgo-interactions', $json );

		return $processor->get_updated_html();
	}

	/**
	 * Reduce stored interactions to the known schema.
	 *
	 * Attributes arrive from post content, which an editor with `unfiltered_html`
	 * controls. Echoing arbitrary stored JSON back into an attribute would let
	 * unexpected keys ride along; the frontend ignores them, but emitting only
	 * what the runtime reads keeps the payload small and the contract explicit.
	 *
	 * @param array $interactions Raw interaction list.
	 * @return array Sanitized list.
	 */
	private static function sanitize( array $interactions ) {
		$clean = array();

		foreach ( $interactions as $interaction ) {
			if ( ! is_array( $interaction ) ) {
				continue;
			}

			$clean[] = array(
				'id'             => isset( $interaction['id'] ) ? (string) $interaction['id'] : '',
				'trigger'        => isset( $interaction['trigger'] ) ? (string) $interaction['trigger'] : 'click',
				'targetMode'     => isset( $interaction['targetMode'] ) ? (string) $interaction['targetMode'] : 'self',
				'targetSelector' => isset( $interaction['targetSelector'] ) ? (string) $interaction['targetSelector'] : '',
				'action'         => isset( $interaction['action'] ) ? (string) $interaction['action'] : '',
				'value'          => isset( $interaction['value'] ) ? (string) $interaction['value'] : '',
				'attributeName'  => isset( $interaction['attributeName'] ) ? (string) $interaction['attributeName'] : '',
				'key'            => isset( $interaction['key'] ) ? (string) $interaction['key'] : '',
				'once'           => ! empty( $interaction['once'] ),
				'offset'         => isset( $interaction['offset'] ) ? (float) $interaction['offset'] : 0,
			);
		}

		return $clean;
	}
}
