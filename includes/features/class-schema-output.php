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
	 * @param array  $blocks Parsed blocks.
	 * @param string $title  Page title, passed to builders that need it.
	 * @param array  $seen   Synced-pattern IDs already expanded, guarding cycles.
	 * @return array List of schema graph nodes.
	 */
	public function collect( array $blocks, $title = '', array $seen = array() ) {
		$nodes = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			// A synced pattern keeps its markup in a wp_block post; the page
			// only stores a reference. Without expanding it, an FAQ defined
			// once in a shared pattern would emit nothing on every page that
			// uses it, with nothing to tell the author why.
			if ( 'core/block' === ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) ) {
				$nodes = array_merge( $nodes, $this->collect_from_reference( $block, $title, $seen ) );
				continue;
			}

			$type = isset( $block['attrs']['dsgoSchema'] ) ? $block['attrs']['dsgoSchema'] : 'none';

			if ( is_string( $type ) && isset( self::BUILDERS[ $type ] ) ) {
				$builder = self::BUILDERS[ $type ];

				if ( function_exists( $builder ) ) {
					// Builders that do not need the title simply ignore the
					// extra argument.
					$node = call_user_func( $builder, $block, $title );

					if ( is_array( $node ) && ! empty( $node ) ) {
						$nodes[] = $node;
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$nodes = array_merge( $nodes, $this->collect( $block['innerBlocks'], $title, $seen ) );
			}
		}

		return $nodes;
	}

	/**
	 * Expand a synced pattern reference and collect from its content.
	 *
	 * @param array  $block Parsed core/block block.
	 * @param string $title Page title.
	 * @param array  $seen  Reference IDs already expanded on this branch.
	 * @return array List of schema graph nodes.
	 */
	private function collect_from_reference( array $block, $title, array $seen ) {
		$ref = isset( $block['attrs']['ref'] ) ? (int) $block['attrs']['ref'] : 0;

		// A pattern that references itself, directly or through another
		// pattern, would otherwise recurse until the request dies.
		if ( $ref <= 0 || in_array( $ref, $seen, true ) ) {
			return array();
		}

		$seen[] = $ref;

		$reference = get_post( $ref );

		if ( ! $reference instanceof \WP_Post || 'wp_block' !== $reference->post_type ) {
			return array();
		}

		// Mirrors core's render_block_core_block(), which refuses a pattern that
		// is unpublished OR password-protected. Emitting schema for content core
		// declines to render would disclose it through the back door.
		if ( 'publish' !== $reference->post_status || ! empty( $reference->post_password ) ) {
			return array();
		}

		if ( ! $this->may_contain_schema( $reference->post_content ) ) {
			return array();
		}

		return $this->collect( parse_blocks( $reference->post_content ), $title, $seen );
	}


	/**
	 * Could this content hold an opted-in block?
	 *
	 * A cheap string test used to skip the comparatively expensive
	 * parse_blocks() on the overwhelming majority of posts. An opted-in block
	 * carries the attribute name in its block comment — unless it lives in a
	 * synced pattern, in which case only the reference is present here and the
	 * attribute is in the referenced wp_block post.
	 *
	 * @param string $content Post content.
	 * @return bool Whether the content is worth parsing.
	 */
	private function may_contain_schema( $content ) {
		$content = (string) $content;

		return false !== strpos( $content, 'dsgoSchema' )
			|| false !== strpos( $content, 'wp:block ' );
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

		// A protected post is still singular, and get_post() still hands back
		// the full content — WordPress only substitutes the password form at
		// the_content, which runs long after wp_head. Without this the
		// questions, answers and title would sit in view-source for anyone,
		// password or not. Matches the guard in class-style-binding.php,
		// class-query-bindings-helpers.php and the llms-txt classes.
		if ( post_password_required( $post ) ) {
			return;
		}

		if ( ! $this->may_contain_schema( $post->post_content ) ) {
			return;
		}

		$nodes = $this->collect( parse_blocks( $post->post_content ), get_the_title( $post ) );

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
