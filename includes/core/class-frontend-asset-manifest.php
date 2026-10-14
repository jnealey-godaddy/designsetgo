<?php
/**
 * Frontend asset manifest for soft navigations.
 *
 * A soft navigation (Airo's frontend refresh) swaps page content without
 * running the new content's `<script>` tags, so a block or
 * extension that the first page didn't use arrives with no script and, in
 * some cases, no stylesheet. This prints `window.dsgoAssets`: for every
 * DesignSetGo block and extension bundle that is NOT already on the page, what
 * marks its presence in the DOM and which scripts/styles it needs, with each
 * script's full dependency chain and inline data (localized config,
 * translations). src/utils/soft-nav-assets.js reads it after each
 * `dsgo-content-loaded` and loads whatever the new content needs.
 *
 * Printed only for users who can edit posts: Airo's refresh runs in an
 * editing session, and the manifest carries localized script data (Form
 * Builder's nonces) that must not be broadcast to visitors on pages that
 * never render those blocks. Other soft-navigation integrations can opt in
 * through the `designsetgo_frontend_asset_manifest` filter. Query "load
 * more" doesn't need it: Extension_Bundles enqueues the assets of the blocks
 * in a Query's item template when the Query renders.
 *
 * Script modules (the Query block's Interactivity API view) are not covered:
 * a page's import map can't change after load.
 *
 * @package DesignSetGo
 * @since 2.8.3
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and prints the soft-navigation asset manifest.
 */
class Frontend_Asset_Manifest {

	/**
	 * Assets that belong to no single block or extension bundle.
	 *
	 * The shared stylesheet, which a page with no DesignSetGo block lacks, and
	 * the icon injector, enqueued only once an icon block renders (icons the
	 * page never inlined are fetched from the REST route).
	 *
	 * @var array<int,array{match:array<string,string>,scripts:string[],styles:string[]}>
	 */
	private const SHARED_CANDIDATES = array(
		array(
			'match'   => array( 'selector' => '[class*="wp-block-designsetgo-"]' ),
			'scripts' => array(),
			'styles'  => array( 'designsetgo-frontend' ),
		),
		array(
			'match'   => array( 'selector' => '[data-icon-name], [data-icon]' ),
			'scripts' => array( 'designsetgo-icon-injector' ),
			'styles'  => array(),
		),
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Before _wp_footer_scripts (priority 10) prints designsetgo-frontend,
		// and after every block has rendered and enqueued its assets.
		add_action( 'wp_print_footer_scripts', array( $this, 'print_manifest' ), 1 );
	}

	/**
	 * Attach the manifest to the shared frontend runtime.
	 */
	public function print_manifest() {
		/**
		 * Whether to print the soft-navigation asset manifest on this page.
		 *
		 * @param bool $emit Default: users who can edit posts.
		 */
		if ( ! apply_filters( 'designsetgo_frontend_asset_manifest', current_user_can( 'edit_posts' ) ) ) {
			return;
		}

		// The runtime (and loader) only enqueues when a DesignSetGo block
		// renders, so a page an Airo edit gives its first block has none.
		if ( ! wp_script_is( 'designsetgo-frontend', 'enqueued' ) ) {
			if ( ! wp_script_is( 'designsetgo-frontend', 'registered' ) ) {
				return;
			}
			wp_enqueue_script( 'designsetgo-frontend' );
		}

		$manifest = self::build();
		if ( empty( $manifest['triggers'] ) ) {
			return;
		}

		wp_add_inline_script(
			'designsetgo-frontend',
			'window.dsgoAssets = ' . wp_json_encode( $manifest, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';',
			'before'
		);
	}

	/**
	 * Build the manifest of assets the page doesn't have yet.
	 *
	 * @return array{triggers:array<int,array<string,mixed>>,scripts:array<string,array<string,mixed>>,styles:array<string,array<string,string>>}
	 */
	public static function build() {
		$manifest = array(
			'triggers' => array(),
			'scripts'  => array(),
			'styles'   => array(),
		);

		foreach ( self::candidates() as $candidate ) {
			$scripts = array();
			foreach ( $candidate['scripts'] as $handle ) {
				if ( ! self::script_on_page( $handle ) && self::describe_script( $handle, $manifest['scripts'] ) ) {
					$scripts[] = $handle;
				}
			}

			$styles = array();
			foreach ( $candidate['styles'] as $handle ) {
				if ( ! wp_style_is( $handle, 'enqueued' ) && ! wp_style_is( $handle, 'done' ) && self::describe_style( $handle, $manifest['styles'] ) ) {
					$styles[] = $handle;
				}
			}

			if ( $scripts || $styles ) {
				$manifest['triggers'][] = array_merge(
					$candidate['match'],
					array_filter(
						array(
							'scripts' => $scripts,
							'styles'  => $styles,
						)
					)
				);
			}
		}

		return $manifest;
	}

	/**
	 * Every extension bundle and DesignSetGo block that ships frontend assets.
	 *
	 * Extension bundles match on the same literal needles PHP uses; blocks
	 * match on their exact wrapper class (a substring would let `…-icon` fire
	 * on `…-icon-button`).
	 *
	 * @return array<int,array{match:array<string,mixed>,scripts:string[],styles:string[]}>
	 */
	private static function candidates() {
		$candidates = self::SHARED_CANDIDATES;

		foreach ( Extension_Bundles::get_features() as $name => $feature ) {
			$handle       = Extension_Bundles::HANDLE_PREFIX . $name;
			$candidates[] = array(
				'match'   => array( 'needles' => $feature['needles'] ),
				'scripts' => empty( $feature['scripts'] ) ? array() : array( $handle ),
				'styles'  => empty( $feature['style'] ) ? array() : array( $handle ),
			);
		}

		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block_type ) {
			if ( 0 !== strpos( $name, 'designsetgo/' ) ) {
				continue;
			}
			$candidates[] = array(
				'match'   => array( 'selector' => '.wp-block-' . str_replace( '/', '-', $name ) ),
				'scripts' => array_values( (array) $block_type->view_script_handles ),
				'styles'  => array_values( (array) $block_type->style_handles ),
			);
		}

		return $candidates;
	}

	/**
	 * Whether a script is printed, or will be printed, on this page.
	 *
	 * `enqueued` also covers dependencies of anything in the queue.
	 *
	 * @param string $handle Script handle.
	 * @return bool
	 */
	private static function script_on_page( $handle ) {
		return wp_script_is( $handle, 'enqueued' ) || wp_script_is( $handle, 'done' );
	}

	/**
	 * Add a script and every dependency missing from the page to the manifest.
	 *
	 * @param string $handle  Script handle.
	 * @param array  $scripts Manifest scripts, by handle (modified).
	 * @param int    $depth   Recursion guard.
	 * @return bool Whether the script can be loaded.
	 */
	private static function describe_script( $handle, array &$scripts, $depth = 0 ) {
		if ( isset( $scripts[ $handle ] ) ) {
			return true;
		}

		$wp_scripts = wp_scripts();
		$script     = $wp_scripts->registered[ $handle ] ?? null;
		if ( ! $script || $depth > 10 ) {
			return false;
		}

		$deps = array();
		foreach ( (array) $script->deps as $dep ) {
			if ( self::script_on_page( $dep ) ) {
				$deps[] = $dep;
				continue;
			}
			if ( ! self::describe_script( $dep, $scripts, $depth + 1 ) ) {
				return false;
			}
			$deps[] = $dep;
		}

		$entry = array( 'deps' => $deps );

		// A handle without a src is an alias that only groups dependencies, as
		// is one whose URL a `script_loader_src` filter blanked.
		if ( $script->src ) {
			$urls = self::printed_urls( $wp_scripts, $handle, 'script_loader_tag' );
			if ( $urls ) {
				$entry['src'] = $urls[0];
			}
		}

		// Localized data and translations run before the script, as WordPress
		// prints them (`<handle>-js-extra`, `-js-translations`, `-js-before`).
		$before = array_filter(
			array(
				(string) $wp_scripts->get_data( $handle, 'data' ),
				(string) $wp_scripts->print_translations( $handle, false ),
				$wp_scripts->get_inline_script_data( $handle, 'before' ),
			)
		);
		if ( $before ) {
			$entry['before'] = implode( "\n", $before );
		}

		$after = $wp_scripts->get_inline_script_data( $handle, 'after' );
		if ( '' !== $after ) {
			$entry['after'] = $after;
		}

		$scripts[ $handle ] = $entry;

		return true;
	}

	/**
	 * Add a stylesheet (with its inline additions) to the manifest.
	 *
	 * @param string $handle Style handle.
	 * @param array  $styles Manifest styles, by handle (modified).
	 * @return bool Whether the style can be loaded.
	 */
	private static function describe_style( $handle, array &$styles ) {
		$wp_styles = wp_styles();
		$style     = $wp_styles->registered[ $handle ] ?? null;
		if ( ! $style || ! $style->src ) {
			return false;
		}

		$urls = self::printed_urls( $wp_styles, $handle, 'style_loader_tag' );
		if ( ! $urls ) {
			return false;
		}

		// An RTL site gets the stylesheet's tag, then its RTL one. `replace`
		// prints only the RTL one; otherwise it adds to the main stylesheet.
		$rtl   = $style->extra['rtl'] ?? null;
		$entry = array( 'href' => 'replace' === $rtl ? $urls[ count( $urls ) - 1 ] : $urls[0] );

		$after = $wp_styles->get_data( $handle, 'after' );
		if ( is_array( $after ) && $after ) {
			$entry['after'] = implode( "\n", array_filter( $after, 'is_string' ) );
		}

		$styles[ $handle ] = $entry;

		return true;
	}

	/**
	 * The URLs WordPress prints for a registered asset, in tag order.
	 *
	 * Core renders the tag, so the URL gets its version argument and
	 * `script_loader_src` / `style_loader_src` exactly as the printed tags do,
	 * and a CDN or asset-optimization plugin rewrites it the same way. The URL
	 * is read from the `*_loader_tag` filter's own argument, ahead of every
	 * other callback, so a plugin that rewrites the tag itself (delay-JS moving
	 * `src` to `data-src`, a changed `id`) can't hide it.
	 *
	 * @param \WP_Dependencies $registry Scripts or styles registry.
	 * @param string           $handle   Asset handle.
	 * @param string           $hook     `script_loader_tag` or `style_loader_tag`.
	 * @return string[] URLs; none when WordPress prints no tag (a blanked or
	 *                  conditional asset).
	 */
	private static function printed_urls( $registry, $handle, $hook ) {
		$urls    = array();
		$capture = static function ( $tag, $tag_handle, $url ) use ( $handle, &$urls ) {
			if ( $tag_handle === $handle && is_string( $url ) && '' !== $url ) {
				$urls[] = $url;
			}
			return $tag;
		};

		// Concatenation would queue the tag instead of printing it.
		$do_concat           = $registry->do_concat;
		$registry->do_concat = false;
		add_filter( $hook, $capture, PHP_INT_MIN, 3 );
		ob_start();
		try {
			$registry->do_item( $handle );
		} finally {
			ob_end_clean();
			remove_filter( $hook, $capture, PHP_INT_MIN );
			$registry->do_concat = $do_concat;
		}

		// A stylesheet's `href` arrives escaped for the attribute (`&#038;`);
		// the manifest carries plain URLs for JavaScript.
		return 'style_loader_tag' === $hook
			? array_map( array( \WP_HTML_Decoder::class, 'decode_attribute' ), $urls )
			: $urls;
	}
}
