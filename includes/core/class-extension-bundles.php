<?php
/**
 * Per-feature frontend bundles for block extensions.
 *
 * Each extension's frontend script and stylesheet is its own bundle, built
 * from includes/data/frontend-extensions.json (webpack reads the same file).
 * A bundle is enqueued only when one of its needles appears in a rendered
 * block, so a page with one hover effect no longer downloads the animation,
 * parallax, text-reveal and background-video runtimes as well.
 *
 * Bundles depend on the shared `designsetgo-frontend` handles, which carry
 * the page-wide utilities and the bfcache → `dsgo-content-loaded` dispatch.
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
 * Registers extension bundles and enqueues the ones a page uses.
 */
class Extension_Bundles {

	/**
	 * Handle prefix for every extension bundle (script and style).
	 */
	const HANDLE_PREFIX = 'designsetgo-ext-';

	/**
	 * Features whose bundles are already enqueued this request, as keys.
	 *
	 * @var array<string,true>
	 */
	private $enqueued = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register' ) );
		// After the render-time injectors that write needles into markup:
		// animation defaults (priority 9) and the parallax fallback (10).
		add_filter( 'render_block', array( $this, 'maybe_enqueue' ), 11, 2 );
		add_filter( 'render_block_designsetgo/query', array( $this, 'enqueue_query_template_blocks' ), 10, 2 );
	}

	/**
	 * Read the feature manifest.
	 *
	 * @return array<string,array{needles:string[],scripts?:string[],style?:string}> Features by name.
	 */
	public static function get_features() {
		static $features = null;

		if ( null !== $features ) {
			return $features;
		}

		$file = DESIGNSETGO_PATH . 'includes/data/frontend-extensions.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Reading local plugin file.
		$json     = is_readable( $file ) ? file_get_contents( $file ) : '';
		$features = is_string( $json ) ? json_decode( $json, true ) : null;

		if ( ! is_array( $features ) ) {
			$features = array();
		}

		return $features;
	}

	/**
	 * Register every built extension bundle.
	 *
	 * Block themes render templates before `wp_enqueue_scripts`, so a bundle
	 * may already be queued by handle when this runs; registering it then is
	 * enough for WordPress to print it.
	 */
	public function register() {
		foreach ( self::get_features() as $name => $feature ) {
			$handle = self::HANDLE_PREFIX . $name;

			if ( ! empty( $feature['scripts'] ) ) {
				$asset_path = DESIGNSETGO_PATH . "build/extensions/{$name}/frontend.asset.php";

				if ( is_readable( $asset_path ) ) {
					$asset = include $asset_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- build artifact; path resolved from plugin directory

					if ( is_array( $asset ) && isset( $asset['dependencies'], $asset['version'] ) ) {
						wp_register_script(
							$handle,
							DESIGNSETGO_URL . "build/extensions/{$name}/frontend.js",
							array_merge( $asset['dependencies'], array( 'designsetgo-frontend' ) ),
							$asset['version'],
							true
						);
					}
				}
			}

			if ( ! empty( $feature['style'] ) && is_readable( DESIGNSETGO_PATH . "build/extensions/{$name}/style.css" ) ) {
				wp_register_style(
					$handle,
					DESIGNSETGO_URL . "build/extensions/{$name}/style.css",
					array( 'designsetgo-frontend' ),
					DESIGNSETGO_VERSION
				);
			}
		}
	}

	/**
	 * Enqueue the bundles whose needles appear in a rendered block.
	 *
	 * A Query block's item template is scanned too: items that first arrive
	 * over REST (a filter on a query that rendered no results, "load more")
	 * would otherwise need a bundle the first paint never asked for.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The full block, including name and attributes.
	 * @return string The unmodified block content.
	 */
	public function maybe_enqueue( $block_content, $block ) {
		if ( is_admin() || ! is_string( $block_content ) ) {
			return $block_content;
		}

		$features = self::get_features();
		if ( count( $this->enqueued ) >= count( $features ) ) {
			return $block_content;
		}

		$haystack = $block_content;
		if ( isset( $block['blockName'] ) && 'designsetgo/query' === $block['blockName'] && ! empty( $block['innerBlocks'] ) ) {
			$haystack .= serialize_blocks( $block['innerBlocks'] );
		}

		if ( false === strpos( $haystack, 'dsgo' ) ) {
			return $block_content;
		}

		foreach ( $features as $name => $feature ) {
			if ( isset( $this->enqueued[ $name ] ) || ! self::contains_any( $haystack, $feature['needles'] ) ) {
				continue;
			}

			$handle = self::HANDLE_PREFIX . $name;
			if ( ! empty( $feature['scripts'] ) ) {
				wp_enqueue_script( $handle );
			}
			if ( ! empty( $feature['style'] ) ) {
				wp_enqueue_style( $handle );
			}
			$this->enqueued[ $name ] = true;
		}

		return $block_content;
	}

	/**
	 * Enqueue the scripts and styles of every block in a Query's item template.
	 *
	 * WordPress enqueues a block's assets when the block renders. A Query
	 * whose first paint has no items (a filter matched nothing) renders none
	 * of its item blocks, so results that arrive later over REST would have no
	 * view script or stylesheet. Only blocks the template actually contains are
	 * enqueued, so their localized data (a form's nonce) reaches only pages
	 * that can show them.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The full Query block.
	 * @return string The unmodified block content.
	 */
	public function enqueue_query_template_blocks( $block_content, $block ) {
		if ( is_admin() || empty( $block['innerBlocks'] ) ) {
			return $block_content;
		}

		$registry = \WP_Block_Type_Registry::get_instance();
		foreach ( self::block_names( $block['innerBlocks'] ) as $name ) {
			$block_type = $registry->get_registered( $name );
			if ( ! $block_type ) {
				continue;
			}
			foreach ( (array) $block_type->view_script_handles as $handle ) {
				wp_enqueue_script( $handle );
			}
			foreach ( (array) $block_type->style_handles as $handle ) {
				wp_enqueue_style( $handle );
			}
		}

		return $block_content;
	}

	/**
	 * Every block name in a tree of parsed blocks.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return string[] Unique block names.
	 */
	private static function block_names( array $blocks ) {
		$names = array();
		foreach ( $blocks as $inner ) {
			if ( ! empty( $inner['blockName'] ) ) {
				$names[ $inner['blockName'] ] = true;
			}
			if ( ! empty( $inner['innerBlocks'] ) ) {
				$names += array_fill_keys( self::block_names( $inner['innerBlocks'] ), true );
			}
		}

		return array_keys( $names );
	}

	/**
	 * Whether a string contains any of the needles.
	 *
	 * @param string   $haystack String to search.
	 * @param string[] $needles  Literal needles.
	 * @return bool
	 */
	private static function contains_any( $haystack, $needles ) {
		foreach ( (array) $needles as $needle ) {
			if ( is_string( $needle ) && '' !== $needle && false !== strpos( $haystack, $needle ) ) {
				return true;
			}
		}

		return false;
	}
}
