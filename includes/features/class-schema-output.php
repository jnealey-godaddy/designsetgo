<?php
/**
 * Schema JSON-LD head output.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

require_once DESIGNSETGO_PATH . 'includes/features/schema-builders.php';

/**
 * Collects opted-in blocks from the current post and prints one JSON-LD graph.
 *
 * Reads the stored post content rather than filtering render_block: the target
 * blocks are static (a save.js, no render.php), and their data — including the
 * HTML-sourced accordion title — only exists in the saved markup. wp_head also
 * runs before the content, so a render_block collector would be too late.
 */
class SchemaOutput {

	/**
	 * Map a dsgoSchema value to its builder function.
	 *
	 * @var array<string, string>
	 */
	private const BUILDERS = array(
		'faq'   => 'designsetgo_schema_build_faq',
		'howto' => 'designsetgo_schema_build_howto',
	);

	/**
	 * Hook the head output.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'render' ), 20 );
	}

	/**
	 * Walk parsed blocks and build every schema node they opt into.
	 *
	 * Public so it can be unit-tested without a request.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return array List of schema graph nodes.
	 */
	public function collect( array $blocks ) {
		$nodes = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$type = isset( $block['attrs']['dsgoSchema'] ) ? $block['attrs']['dsgoSchema'] : 'none';

			if ( is_string( $type ) && isset( self::BUILDERS[ $type ] ) ) {
				$builder = self::BUILDERS[ $type ];

				if ( function_exists( $builder ) ) {
					$node = call_user_func( $builder, $block );

					if ( is_array( $node ) && ! empty( $node ) ) {
						$nodes[] = $node;
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$nodes = array_merge( $nodes, $this->collect( $block['innerBlocks'] ) );
			}
		}

		return $nodes;
	}

	/**
	 * Print the JSON-LD script tag.
	 */
	public function render() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();

		if ( ! $post instanceof \WP_Post || ! has_blocks( $post->post_content ) ) {
			return;
		}

		// Cheap bail before the comparatively expensive parse. Every opted-in
		// block carries the attribute name in its block comment.
		if ( false === strpos( $post->post_content, 'dsgoSchema' ) ) {
			return;
		}

		$nodes = $this->collect( parse_blocks( $post->post_content ) );

		/**
		 * Filter the schema graph nodes before output.
		 *
		 * Return an empty array to suppress the script entirely.
		 *
		 * @param array    $nodes Schema graph nodes.
		 * @param \WP_Post $post  Current post.
		 */
		$nodes = apply_filters( 'designsetgo_schema_nodes', $nodes, $post );

		if ( ! is_array( $nodes ) || empty( $nodes ) ) {
			return;
		}

		$graph = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $nodes ),
		);

		$json = wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $json ) {
			return;
		}

		// A literal `</script>` inside a JSON string would close the element and
		// let the remaining content run as markup. Escaping the slash keeps the
		// JSON semantically identical — "<\/script>" decodes to "</script>".
		// JSON_UNESCAPED_SLASHES is what makes this necessary, and it is kept
		// because escaping every slash bloats URLs in the output.
		$json = str_replace( '</', '<\/', $json );

		echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() output with script-closing sequences neutralised on the line above.
	}
}
