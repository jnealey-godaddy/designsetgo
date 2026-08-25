<?php
/**
 * Schema JSON-LD head output.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

require_once DESIGNSETGO_PATH . 'includes/features/schema-builders.php';
require_once DESIGNSETGO_PATH . 'includes/features/schema-builders-rating.php';

/**
 * Collects opted-in blocks from the current post and prints one JSON-LD graph.
 *
 * Reads the stored post content rather than filtering render_block: the target
 * blocks are static (a save.js, no render.php), and their data — including the
 * HTML-sourced accordion title — only exists in the saved markup. wp_head also
 * runs before the content, so a render_block collector would be too late.
 *
 * SCOPE: the singular post's own content, plus any synced patterns it
 * references. An accordion placed directly in a block template or template
 * part is deliberately NOT collected. Such a block appears on every page using
 * that template, and FAQPage markup is supposed to describe the page it sits
 * on — emitting one site-wide FAQ across every URL is the structured-data spam
 * this feature's opt-in default exists to avoid. A site that genuinely wants
 * template-level schema can add it through the `designsetgo_schema_nodes`
 * filter, where the decision is explicit and per-site.
 *
 * COST: the strpos() pair below short-circuits a page with no schema in well
 * under a microsecond. A deliberately heavy page — three synced patterns
 * holding sixty question/answer pairs, plus a hundred other blocks — costs
 * about 7.5ms. That is not worth a transient: invalidating one would mean
 * tracking which posts reference which patterns, and stale structured data is
 * a worse failure than the parse it saves.
 */
class SchemaOutput {

	/**
	 * Which builder handles which schema type, per block.
	 *
	 * Keyed by block name first, deliberately. The allowlist otherwise exists
	 * only in the editor UI and in the server-side attribute registration, and
	 * neither constrains what parse_blocks() hands this collector: a
	 * hand-written block comment (the editor's Code view suffices — no
	 * unfiltered_html required) could put dsgoSchema on any block and have a
	 * builder run against its children. Keying on the block name is what makes
	 * "only blocks with a builder behind them" true at runtime rather than
	 * merely in the UI.
	 *
	 * Keep the block names in step with includes/extension-configs/schema.php;
	 * Schema_Config_Parity_Test enforces it.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const BUILDERS = array(
		'designsetgo/accordion'   => array(
			'faq'   => 'designsetgo_schema_build_faq',
			'howto' => 'designsetgo_schema_build_howto',
		),
		'designsetgo/star-rating' => array(
			'aggregate-rating' => 'designsetgo_schema_build_aggregate_rating',
			'review'           => 'designsetgo_schema_build_review',
		),
	);

	/**
	 * Block names this collector will build schema for.
	 *
	 * @return array List of block names.
	 */
	public static function supported_blocks() {
		return array_keys( self::BUILDERS );
	}

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

			$name = isset( $block['blockName'] ) ? $block['blockName'] : '';
			$type = isset( $block['attrs']['dsgoSchema'] ) ? $block['attrs']['dsgoSchema'] : 'none';

			if ( is_string( $type ) && isset( self::BUILDERS[ $name ][ $type ] ) ) {
				$builder = self::BUILDERS[ $name ][ $type ];

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
