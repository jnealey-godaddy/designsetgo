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
		if ( ! wp_script_is( 'designsetgo-frontend', 'enqueued' ) ) {
			return;
		}

		/**
		 * Whether to print the soft-navigation asset manifest on this page.
		 *
		 * @param bool $emit Default: users who can edit posts.
		 */
		if ( ! apply_filters( 'designsetgo_frontend_asset_manifest', current_user_can( 'edit_posts' ) ) ) {
			return;
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
		$candidates = array();

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

		// A handle without a src is an alias that only groups dependencies.
		if ( $script->src ) {
			$entry['src'] = self::asset_url( $wp_scripts, $script, 'script_loader_src' );
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

		$entry = array( 'href' => self::asset_url( $wp_styles, $style, 'style_loader_src' ) );

		$after = $wp_styles->get_data( $handle, 'after' );
		if ( is_array( $after ) && $after ) {
			$entry['after'] = implode( "\n", array_filter( $after, 'is_string' ) );
		}

		$styles[ $handle ] = $entry;

		return true;
	}

	/**
	 * Resolve a registered asset's URL the way WordPress does when printing it.
	 *
	 * @param \WP_Dependencies $registry Scripts or styles registry.
	 * @param \_WP_Dependency  $asset    Registered asset.
	 * @param string           $filter   `script_loader_src` or `style_loader_src`.
	 * @return string URL.
	 */
	private static function asset_url( $registry, $asset, $filter ) {
		$src = (string) $asset->src;

		if (
			! preg_match( '|^(https?:)?//|', $src )
			&& ! ( $registry->content_url && str_starts_with( $src, $registry->content_url ) )
		) {
			$src = $registry->base_url . $src;
		}

		$ver = null === $asset->ver ? '' : ( $asset->ver ? $asset->ver : $registry->default_version );
		if ( '' !== (string) $ver ) {
			$src = add_query_arg( 'ver', $ver, $src );
		}

		// Core's own filters, so a CDN or asset-optimization plugin rewrites
		// these URLs exactly as it does the printed tags.
		if ( 'style_loader_src' === $filter ) {
			/** This filter is documented in wp-includes/class-wp-styles.php */
			return (string) apply_filters( 'style_loader_src', $src, $asset->handle ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
		}

		/** This filter is documented in wp-includes/class-wp-scripts.php */
		return (string) apply_filters( 'script_loader_src', $src, $asset->handle ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
	}
}
