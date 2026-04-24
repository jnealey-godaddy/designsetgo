<?php
/**
 * Assets Management Class
 *
 * @package DesignSetGo
 * @since 1.0.0
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets Class - Handles CSS/JS loading and optimization
 */
class Assets {

	/**
	 * Whether frontend assets have been enqueued for this request.
	 *
	 * @var bool
	 */
	private $frontend_enqueued = false;

	/**
	 * Whether Dashicons have been enqueued for this request.
	 *
	 * @var bool
	 */
	private $dashicons_enqueued = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
		add_filter( 'render_block', array( $this, 'maybe_enqueue_frontend_on_render' ), 10, 2 );

		// Clear block detection cache when post is saved.
		add_action( 'save_post', array( $this, 'clear_block_cache' ) );
		add_action( 'deleted_post', array( $this, 'clear_block_cache' ) );

		// Performance: CSS loading optimization.
		add_filter( 'style_loader_tag', array( $this, 'optimize_css_loading' ), 10, 4 );
		add_action( 'wp_head', array( $this, 'inline_critical_css' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_inlined_css' ), 20 );
	}

	/**
	 * Enqueue editor assets.
	 *
	 * Note: Individual block assets are loaded automatically via block.json.
	 * This method is for global editor assets (extensions, variations, etc.).
	 * Uses enqueue_block_assets hook to properly work with block editor iframe.
	 */
	public function enqueue_editor_assets() {
		// Only run in editor context.
		if ( ! is_admin() ) {
			return;
		}

		// Enqueue Dashicons for tab/accordion icon pickers.
		// Note: WordPress automatically loads Dashicons in admin, but we enqueue
		// explicitly to ensure availability. This is lightweight in editor context.
		wp_enqueue_style( 'dashicons' );

		// Load block extensions and variations.
		$asset_file_path = DESIGNSETGO_PATH . 'build/index.asset.php';

		if ( ! file_exists( $asset_file_path ) || ! is_readable( $asset_file_path ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'DesignSetGo: Editor asset file not found. Run `npm run build`.' );
			}
			return;
		}

		$asset_file = include $asset_file_path;

		if ( ! is_array( $asset_file ) || ! isset( $asset_file['dependencies'] ) || ! isset( $asset_file['version'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'DesignSetGo: Invalid editor asset file format.' );
			}
			return;
		}

		wp_enqueue_script(
			'designsetgo-extensions',
			DESIGNSETGO_URL . 'build/index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_enqueue_style(
			'designsetgo-extensions',
			DESIGNSETGO_URL . 'build/index.css',
			array( 'wp-edit-blocks' ), // Dashicons loads automatically in admin.
			$asset_file['version']
		);
	}

	/**
	 * Check if any DesignSetGo blocks are used (with caching).
	 *
	 * Uses transient caching to avoid repeated content parsing.
	 * Cache is automatically cleared on post save/delete.
	 *
	 * @return bool True if DesignSetGo blocks are present.
	 */
	private function has_designsetgo_blocks() {
		// Not on singular content - can't reliably detect.
		if ( ! is_singular() ) {
			return false;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return false;
		}

		// Check object cache first (faster if Redis/Memcached available).
		// Include post modified time in key for automatic cache invalidation.
		$modified_time = get_post_modified_time( 'U', false, $post_id );
		$cache_key     = 'dsgo_has_blocks_' . $post_id . '_' . $modified_time;
		$cached        = wp_cache_get( $cache_key, 'designsetgo' );

		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$has_blocks = false;

		// Method 1: Check has_blocks() first (fastest).
		if ( ! has_blocks() ) {
			wp_cache_set( $cache_key, 0, 'designsetgo', HOUR_IN_SECONDS );
			return false;
		}

		// Method 2: Single content check for all our patterns.
		global $post;
		if ( ! $post || empty( $post->post_content ) ) {
			wp_cache_set( $cache_key, 0, 'designsetgo', HOUR_IN_SECONDS );
			return false;
		}

		$content = $post->post_content;

		// Check for DesignSetGo blocks (single check for namespace).
		if ( strpos( $content, 'wp:designsetgo/' ) !== false ) {
			$has_blocks = true;
		}

		// Check for DSG extension classes/attributes on any block type.
		// All DSG features use the 'dsgo-' prefix: responsive visibility (dsgo-hide-*),
		// clickable group (dsgo-clickable), animations (data-dsgo-animation-*,
		// has-dsgo-animation), expanding background (data-dsgo-expanding-bg-*,
		// has-dsgo-expanding-background), text style (dsgo-text-style), etc.
		if ( ! $has_blocks && strpos( $content, 'dsgo-' ) !== false ) {
			$has_blocks = true;
		}

		// Cache result for 1 hour using object cache (faster than transients).
		// Falls back to non-persistent cache if Redis/Memcached not available.
		wp_cache_set( $cache_key, (int) $has_blocks, 'designsetgo', HOUR_IN_SECONDS );

		return $has_blocks;
	}

	/**
	 * Clear block detection cache for a post.
	 *
	 * Called automatically when a post is saved or deleted.
	 * Clears object cache (cache key includes modified time, so it auto-invalidates).
	 *
	 * @param int $post_id Post ID.
	 */
	public function clear_block_cache( $post_id ) {
		// Ignore autosaves.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Ignore revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check user can edit post (optional security check).
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Clear all cache entries for this post (wildcard not supported in wp_cache).
		// The modified time in the cache key handles automatic invalidation,
		// so we don't strictly need to delete old entries (they'll expire).
		// This is here for backwards compatibility with old transient-based cache.
		delete_transient( 'dsgo_has_blocks_' . $post_id );

		// Note: Object cache entries auto-invalidate on post update because
		// the cache key includes the post's modified time.
	}

	/**
	 * Register (but don't enqueue) frontend assets.
	 *
	 * Assets are enqueued later via render_block when a DesignSetGo block
	 * or extension is actually rendered, which catches blocks in template
	 * parts (header/footer) too.
	 */
	public function register_frontend_assets() {
		// Register global frontend styles for extensions.
		wp_register_style(
			'designsetgo-frontend',
			DESIGNSETGO_URL . 'build/style-index.css',
			array(),
			DESIGNSETGO_VERSION
		);

		// Register frontend scripts from build directory.
		$frontend_asset_path = DESIGNSETGO_PATH . 'build/frontend.asset.php';

		if ( ! file_exists( $frontend_asset_path ) || ! is_readable( $frontend_asset_path ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'DesignSetGo: Frontend asset file not found. Run `npm run build`.' );
			}
			return;
		}

		$frontend_asset = include $frontend_asset_path;

		if ( ! is_array( $frontend_asset ) || ! isset( $frontend_asset['dependencies'] ) || ! isset( $frontend_asset['version'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'DesignSetGo: Invalid frontend asset file format.' );
			}
			return;
		}

		// Register bundled frontend scripts (animations, group enhancements, etc.).
		wp_register_script(
			'designsetgo-frontend',
			DESIGNSETGO_URL . 'build/frontend.js',
			$frontend_asset['dependencies'],
			$frontend_asset['version'],
			true
		);
	}

	/**
	 * Enqueue frontend assets when a DesignSetGo block or extension is rendered.
	 *
	 * Uses render_block filter to detect blocks wherever they appear:
	 * post content, templates, or template parts (e.g. header/footer).
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The full block, including name and attributes.
	 * @return string The unmodified block content.
	 */
	public function maybe_enqueue_frontend_on_render( $block_content, $block ) {
		if ( is_admin() ) {
			return $block_content;
		}

		// Enqueue frontend bundle if a DSG block or extension output is detected.
		if ( ! $this->frontend_enqueued ) {
			$is_dsgo_block = isset( $block['blockName'] )
				&& strpos( $block['blockName'], 'designsetgo/' ) === 0;

			// Check rendered output for extension classes/attributes (dsgo- prefix).
			$has_dsgo_extension = ! empty( $block_content )
				&& strpos( $block_content, 'dsgo-' ) !== false;

			if ( $is_dsgo_block || $has_dsgo_extension ) {
				wp_enqueue_style( 'designsetgo-frontend' );
				wp_enqueue_script( 'designsetgo-frontend' );
				$this->frontend_enqueued = true;
			}
		}

		// Enqueue Dashicons only for tabs/accordion blocks (saves 40KB otherwise).
		if ( ! $this->dashicons_enqueued && isset( $block['blockName'] ) ) {
			if (
				'designsetgo/tabs' === $block['blockName'] ||
				'designsetgo/accordion' === $block['blockName']
			) {
				wp_enqueue_style( 'dashicons' );
				$this->dashicons_enqueued = true;
			}
		}

		return $block_content;
	}

	/**
	 * Optimize CSS loading for performance.
	 *
	 * Defers non-critical block CSS using media attribute technique.
	 * Reduces render-blocking CSS by ~100-160ms per PageSpeed Insights.
	 *
	 * @param string $html   Link tag HTML.
	 * @param string $handle Style handle.
	 * @param string $href   Style URL.
	 * @param string $media  Media attribute.
	 * @return string Modified link tag.
	 */
	public function optimize_css_loading( $html, $handle, $href, $media ) {
		// Only modify our block styles on frontend.
		if ( is_admin() || strpos( $handle, 'designsetgo-' ) === false ) {
			return $html;
		}

		// Non-critical blocks (typically below-the-fold or interactive).
		$noncritical_blocks = array(
			'tabs',
			'tab',
			'slider',
			'slide',
			'accordion',
			'scroll-accordion',
			'scroll-marquee',
			'image-accordion',
			'flip-card',
			'countdown-timer',
			'counter',
			'progress-bar',
			'map',
			'blobs',
			'divider',
			'form-', // All form blocks.
		);

		// Check if this is a non-critical block.
		foreach ( $noncritical_blocks as $block ) {
			if ( strpos( $handle, $block ) !== false ) {
				// Defer CSS using media attribute trick.
				// Tells browser to load CSS async, then switch to 'all' media.
				// Handle both double quotes (WordPress default) and single quotes.
				if ( strpos( $html, 'media="all"' ) !== false ) {
					$html = str_replace(
						'media="all"',
						'media="print" onload="this.media=\'all\'; this.onload=null;"',
						$html
					);

					// Add noscript fallback for users without JavaScript.
					$noscript = '<noscript>' . str_replace(
						'media="print" onload="this.media=\'all\'; this.onload=null;"',
						'media="all"',
						$html
					) . '</noscript>';

					return $html . $noscript;
				} elseif ( strpos( $html, "media='all'" ) !== false ) {
					$html = str_replace(
						"media='all'",
						"media='print' onload=\"this.media='all'; this.onload=null;\"",
						$html
					);

					// Add noscript fallback for users without JavaScript.
					$noscript = '<noscript>' . str_replace(
						"media='print' onload=\"this.media='all'; this.onload=null;\"",
						"media='all'",
						$html
					) . '</noscript>';

					return $html . $noscript;
				}
			}
		}

		return $html;
	}

	/**
	 * Inline critical CSS for above-the-fold blocks.
	 *
	 * Eliminates render-blocking for most common/critical blocks.
	 * Improves First Contentful Paint (FCP) by ~60-100ms.
	 *
	 * Only inlines on frontend when DesignSetGo blocks are present.
	 */
	public function inline_critical_css() {
		// Only on frontend and when our blocks are present.
		if ( is_admin() || ! $this->has_designsetgo_blocks() ) {
			return;
		}

		// Critical blocks (most likely above-the-fold).
		// These are small enough to inline without significantly bloating HTML.
		$critical_blocks = array(
			'grid',      // 7K - Common layout block.
			'row',       // 4.6K - Common layout block.
			'icon',      // 3.6K - Common decorative element.
			'pill',      // 3.1K - Common UI element.
		);

		$critical_css = '';

		foreach ( $critical_blocks as $block ) {
			$css_file = DESIGNSETGO_PATH . "build/blocks/{$block}/style-index.css";

			if ( file_exists( $css_file ) && is_readable( $css_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading own plugin build asset, not user-supplied path.
				$css = file_get_contents( $css_file );

				// Minify: Remove comments and extra whitespace.
				$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
				$css = preg_replace( '/\s+/', ' ', $css );
				$css = trim( $css );

				$critical_css .= $css;
			}
		}

		if ( ! empty( $critical_css ) ) {
			// Output raw CSS (safe - comes from our build files, not user input).
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<style id="designsetgo-critical-css">' . $critical_css . '</style>' . "\n";
		}
	}

	/**
	 * Dequeue block stylesheets for critical blocks whose CSS is inlined.
	 *
	 * Prevents duplicate CSS loading - WordPress enqueues block CSS via block.json,
	 * but we're inlining critical CSS. This method dequeues the original stylesheets
	 * to avoid loading the same CSS twice.
	 *
	 * Runs at priority 20 to ensure it runs after block styles are enqueued.
	 */
	public function dequeue_inlined_css() {
		// Only on frontend and when our blocks are present.
		if ( is_admin() || ! $this->has_designsetgo_blocks() ) {
			return;
		}

		// Critical blocks whose CSS is inlined (must match inline_critical_css).
		$critical_blocks = array( 'grid', 'row', 'icon', 'pill' );

		foreach ( $critical_blocks as $block ) {
			// Dequeue the block's style-index.css that WordPress automatically enqueues.
			// Handle names match WordPress's automatic style handle generation.
			wp_dequeue_style( "designsetgo-{$block}-style" );
			wp_deregister_style( "designsetgo-{$block}-style" );
		}
	}

	/**
	 * Get asset URL.
	 *
	 * @param string $path Path relative to plugin root.
	 * @return string Full URL.
	 */
	public static function get_url( $path ) {
		return DESIGNSETGO_URL . ltrim( $path, '/' );
	}
}
