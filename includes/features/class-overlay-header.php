<?php
/**
 * Overlay Header Class
 *
 * Handles per-page overlay header functionality via post meta.
 *
 * @package DesignSetGo
 * @since 2.1.0
 */

namespace DesignSetGo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overlay Header Class
 */
class Overlay_Header {
	/**
	 * Post meta key for overlay header toggle.
	 */
	const META_KEY = 'dsgo_overlay_header';

	/**
	 * Post meta key for overlay header text color.
	 */
	const TEXT_COLOR_META_KEY = 'dsgo_overlay_header_text_color';

	/**
	 * Post meta key for skip top bar toggle.
	 */
	const SKIP_TOP_BAR_META_KEY = 'dsgo_overlay_skip_top_bar';

	/**
	 * Fallback estimate for the overlay header height, used only until
	 * sticky-header.js measures the real one. See
	 * get_overlay_header_height_css() for why an estimate is the right shape
	 * here and how to override it.
	 *
	 * @var string
	 */
	private const DEFAULT_HEADER_HEIGHT_ESTIMATE = '100px';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_meta' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_overlay_styles' ), 20 );
		// Priority 1: this has to run before first paint to be worth anything.
		add_action( 'wp_head', array( $this, 'print_cached_height_script' ), 1 );
	}

	/**
	 * Register post meta for overlay header toggle.
	 */
	public function register_post_meta(): void {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type ) {
				continue;
			}

			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'boolean',
					'description'       => __( 'Enable overlay header on this page', 'designsetgo' ),
					'single'            => true,
					'default'           => false,
					'show_in_rest'      => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', (int) $post_id );
					},
				)
			);

			register_post_meta(
				$post_type,
				self::TEXT_COLOR_META_KEY,
				array(
					'type'              => 'string',
					'description'       => __( 'Overlay header text color slug', 'designsetgo' ),
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_key',
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', (int) $post_id );
					},
				)
			);

			register_post_meta(
				$post_type,
				self::SKIP_TOP_BAR_META_KEY,
				array(
					'type'              => 'boolean',
					'description'       => __( 'Skip top bar when using overlay header', 'designsetgo' ),
					'single'            => true,
					'default'           => false,
					'show_in_rest'      => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', (int) $post_id );
					},
				)
			);
		}
	}

	/**
	 * Add body class when overlay header is enabled for the current post.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[] Modified body classes.
	 */
	public function add_body_class( $classes ) {
		if ( ! is_singular() ) {
			return $classes;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $classes;
		}

		if ( get_post_meta( $post_id, self::META_KEY, true ) ) {
			$classes[] = 'dsgo-page-overlay-header';

			if ( get_post_meta( $post_id, self::SKIP_TOP_BAR_META_KEY, true ) ) {
				$classes[] = 'dsgo-page-overlay-skip-top-bar';
			}
		}

		return $classes;
	}

	/**
	 * Whether the current request renders an overlay header.
	 *
	 * @return bool True when this singular view has the overlay meta set.
	 */
	private function is_overlay_request(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return false;
		}

		return (bool) get_post_meta( $post_id, self::META_KEY, true );
	}

	/**
	 * Served estimate of the overlay header's height.
	 *
	 * The overlay header is `position: fixed`, so it reserves no space of its
	 * own and the first content block has to carry that clearance as padding
	 * (see "Overlay Hero Clearance" in _sticky-header.scss). Only the browser
	 * can measure the real height — it depends on the logo, the nav, the fonts
	 * and the viewport — so sticky-header.js measures it and overwrites this
	 * value. Without a served starting point, though, the clearance resolves to
	 * `0px` until that script runs, and the hero's content snaps down by a full
	 * header height at first paint.
	 *
	 * This is therefore a deliberate approximation whose only job is to make
	 * the first-paint correction small instead of total. Sites whose header is
	 * materially taller or shorter than the default can tune it with the
	 * `designsetgo_overlay_header_height_estimate` filter; the measured value
	 * still wins as soon as JS runs, so the final layout is unaffected either
	 * way.
	 *
	 * Emitted on `:root`, NOT on `body`. Custom properties inherit from the
	 * nearest ancestor that declares them, and sticky-header.js sets the
	 * measured value on `document.documentElement` — a `body` declaration would
	 * sit closer to the hero and permanently shadow it, pinning every site to
	 * the estimate.
	 *
	 * @return string CSS string, or empty string if not applicable.
	 */
	public function get_overlay_header_height_css(): string {
		if ( ! $this->is_overlay_request() ) {
			return '';
		}

		/**
		 * Filters the served estimate for the overlay header height.
		 *
		 * @param string $estimate A CSS length, e.g. '100px'.
		 * @param int    $post_id  Post being rendered.
		 */
		$estimate = apply_filters(
			'designsetgo_overlay_header_height_estimate',
			self::DEFAULT_HEADER_HEIGHT_ESTIMATE,
			(int) get_the_ID()
		);

		// The value is interpolated into a stylesheet, so accept only a bare CSS
		// length. Anything else (a filter returning a calc(), a var(), or markup)
		// falls back to the default rather than reaching the page.
		if ( ! is_string( $estimate ) || ! preg_match( '/^\d+(\.\d+)?(px|rem|em|vh|vw)$/', $estimate ) ) {
			$estimate = self::DEFAULT_HEADER_HEIGHT_ESTIMATE;
		}

		return sprintf(
			':root { --dsgo-overlay-header-height: %s; }',
			$estimate
		);
	}

	/**
	 * Generate CSS for overlay header text color.
	 *
	 * @return string CSS string, or empty string if not applicable.
	 */
	public function get_overlay_text_color_css(): string {
		if ( ! $this->is_overlay_request() ) {
			return '';
		}

		$post_id = get_the_ID();

		$text_color_slug = get_post_meta( $post_id, self::TEXT_COLOR_META_KEY, true );
		if ( empty( $text_color_slug ) ) {
			return '';
		}

		// Defense-in-depth: sanitize_callback only runs via REST; direct update_post_meta bypasses it.
		$text_color_slug = sanitize_key( $text_color_slug );

		return sprintf(
			'body.dsgo-page-overlay-header { --dsgo-overlay-header-text-color: var(--wp--preset--color--%s); }',
			$text_color_slug
		);
	}

	/**
	 * Apply the visitor's cached header height before first paint.
	 *
	 * `get_overlay_header_height_css()` can only serve an estimate, because the
	 * real height depends on the rendered logo, nav, fonts and viewport. This
	 * closes that gap from the other side: sticky-header.js caches the measured
	 * height per viewport bucket in localStorage, and this reads it back on the
	 * next load — so the first page a visitor sees uses the estimate, and every
	 * page after that (including a reload of the same one) reserves the exact
	 * height and does not shift at all.
	 *
	 * localStorage rather than a cookie or a stored option, specifically so the
	 * HTML stays byte-identical for every visitor. A cookie would vary the
	 * response and defeat full-page caching on managed hosts, which is a far
	 * worse trade than a few pixels of first-paint correction.
	 *
	 * Printed via wp_print_inline_script_tag() so the `wp_inline_script_attributes`
	 * filter can add a CSP nonce on sites that enforce one.
	 *
	 * The script only reads a string and sets a custom property — it forces no
	 * layout, so it is cheap despite being parser-blocking. Everything is
	 * wrapped in try/catch: storage ACCESS THROWS (rather than returning null)
	 * in Safari private mode and under a blocked-cookie policy, and an
	 * uncaught error in <head> would take the rest of the page's inline
	 * scripts with it.
	 */
	public function print_cached_height_script(): void {
		if ( ! $this->is_overlay_request() ) {
			return;
		}

		// Bucket scheme and key are a shared contract with
		// OVERLAY_HEIGHT_CACHE_KEY / overlayHeightBucket() in
		// src/utils/sticky-header.js. Keep the thresholds in sync.
		// Both terms are applied, not just the height: the clearance rule composes
		// `base + height`, and leaving `base` at 0 until the main script runs left
		// the authored hero padding unreserved and the content still shifting by
		// it. `h`/`b` match the shape cacheOverlayClearance() writes.
		$script = <<<'JS'
try{var m=JSON.parse(localStorage.getItem("dsgoOverlayHeaderHeight")||"{}"),w=innerWidth,v=m[w<600?"s":w<1024?"m":"l"],d=document.documentElement;if(v&&v.h){d.style.setProperty("--dsgo-overlay-header-height",v.h);if(v.b)d.style.setProperty("--dsgo-overlay-hero-base-pad",v.b);}}catch(e){}
JS;

		wp_print_inline_script_tag( $script, array( 'id' => 'designsetgo-overlay-header-height' ) );
	}

	/**
	 * Enqueue inline styles for overlay header text color.
	 */
	public function enqueue_overlay_styles(): void {
		// The height reservation is independent of the text colour — a page can
		// set one and not the other — so both are collected and the bail only
		// happens when there is genuinely nothing to emit. Gating the height on
		// the colour would have silently disabled the first-paint clearance on
		// every overlay page that left the colour at its default.
		$css = trim(
			$this->get_overlay_header_height_css() . "\n" . $this->get_overlay_text_color_css()
		);

		if ( empty( $css ) ) {
			return;
		}

		// Attach to the sticky-header stylesheet when available; otherwise register
		// a minimal inline-only handle so the custom property is always output.
		if ( wp_style_is( 'designsetgo-sticky-header', 'enqueued' ) ) {
			wp_add_inline_style( 'designsetgo-sticky-header', $css );
		} else {
			wp_register_style( 'designsetgo-overlay-header-color', false, array(), DESIGNSETGO_VERSION );
			wp_enqueue_style( 'designsetgo-overlay-header-color' );
			wp_add_inline_style( 'designsetgo-overlay-header-color', $css );
		}
	}
}
