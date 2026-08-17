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

		// A static block already carries the attribute from save(). Leave it
		// exactly as stored: re-encoding it here would mean the markup in the
		// database and the markup served could drift apart.
		//
		// Note this is why sanitize() below is a normalisation step for the
		// dynamic path, not a filter applied to all stored content.
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
	 * Read one string field from a raw interaction.
	 *
	 * Block attributes are parsed from post content and are not validated
	 * against the block schema, so a value here can be any JSON type — an
	 * array arrives intact via a direct REST write. Casting that to string
	 * raises "Array to string conversion", which surfaces in the response
	 * when display_errors is on.
	 *
	 * @param array  $interaction Raw interaction.
	 * @param string $key         Field name.
	 * @param string $default     Value to use when absent or non-scalar.
	 * @return string The field value.
	 */
	private static function string_field( array $interaction, $key, $default = '' ) {
		if ( ! isset( $interaction[ $key ] ) || ! is_scalar( $interaction[ $key ] ) ) {
			return $default;
		}

		return (string) $interaction[ $key ];
	}

	/**
	 * Build the runtime payload from stored interaction attributes.
	 *
	 * This is not a security boundary. It runs only on the dynamic-block path,
	 * because static blocks already carry the attribute from save() and are
	 * returned untouched above — so it cannot be relied on to neutralise
	 * anything in stored content generally. What it does do is give the
	 * dynamic path a fixed shape: only keys the frontend actually reads are
	 * emitted, each coerced to the type the runtime expects, so unexpected
	 * stored values cannot produce a malformed attribute or a PHP notice.
	 *
	 * @param array $interactions Raw interaction list.
	 * @return array Normalized list.
	 */
	private static function sanitize( array $interactions ) {
		$clean = array();

		foreach ( $interactions as $interaction ) {
			if ( ! is_array( $interaction ) ) {
				continue;
			}

			$clean[] = array(
				'id'             => self::string_field( $interaction, 'id' ),
				'trigger'        => self::string_field( $interaction, 'trigger', 'click' ),
				'targetMode'     => self::string_field( $interaction, 'targetMode', 'self' ),
				'targetSelector' => self::string_field( $interaction, 'targetSelector' ),
				'action'         => self::string_field( $interaction, 'action' ),
				'value'          => self::string_field( $interaction, 'value' ),
				'attributeName'  => self::string_field( $interaction, 'attributeName' ),
				'key'            => self::string_field( $interaction, 'key' ),
				'once'           => ! empty( $interaction['once'] ),
				'offset'         => isset( $interaction['offset'] ) && is_numeric( $interaction['offset'] )
					? (float) $interaction['offset']
					: 0,
			);
		}

		return $clean;
	}
}
