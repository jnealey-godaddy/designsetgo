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
	 * Served value of the hero's own authored top padding.
	 *
	 * The clearance rule in _sticky-header.scss composes
	 * `var(--dsgo-overlay-hero-base-pad, 0px) + clearance` and carries
	 * `!important`, which it has to: WordPress serializes block spacing as an
	 * INLINE style, so a normal rule would lose to any hero that sets its own
	 * top padding. The consequence is that an UNSET base term does not fall
	 * back to the author's padding — it REPLACES it with `0px`. Until this
	 * method existed that dropped the hero's authored padding at first paint on
	 * every cold load and snapped it back when sticky-header.js ran.
	 *
	 * It is the dominant term, not a rounding error. Measured on a product site
	 * whose About page hero carries `spacing|sd-large`: the clearance settles at
	 * 208.2px and a cold load painted it at 100px — a 108.2px jump, of which
	 * 71.0px was this dropped padding and only 37.2px was error in the served
	 * height estimate.
	 *
	 * Unlike the height, this needs no estimate and no measurement: it is
	 * authored content and the server is already holding it.
	 *
	 * WHICH element, though, is the whole difficulty, and the guard in
	 * template_renders_post_content_first() is the load-bearing part of this
	 * method. The clearance lands on `header.nextElementSibling.firstElementChild`
	 * (see applyOverlayHeroPadding() in sticky-header.js), and whether that is
	 * the first block of POST CONTENT depends on the template, not the post:
	 *
	 *   template-part, post-content, template-part   → it is. Read post content.
	 *   template-part, group( post-title, … ), …     → it is NOT: the hero is
	 *                                                  the theme's own wrapper
	 *                                                  group, whose padding has
	 *                                                  nothing to do with the
	 *                                                  post.
	 *
	 * Both shapes ship in Twenty Twenty-Five alone — a customized `page`
	 * template is the first, stock `single` and `index` are the second — so this
	 * cannot be assumed either way and must be checked. Reading post content
	 * under the second shape is worse than serving nothing: it reserves a value
	 * belonging to a block two levels deeper, and a measured page moved from a
	 * +55px cold jump to a −65px one.
	 *
	 * So: serve only under the first shape, and emit nothing under anything
	 * else, which leaves the stylesheet's `0px` fallback and the behaviour that
	 * preceded this method. It can make a cold load better and never worse.
	 *
	 * Only the block's own serialized `style.spacing.padding.top` counts, which
	 * is exactly what sticky-header.js reads back out of `hero.style.paddingTop`.
	 * Padding from a stylesheet or theme.json has no inline string on either
	 * side, so both halves miss it identically and neither can drift.
	 *
	 * Emitted on `:root` for the same reason as the height: sticky-header.js
	 * sets the measured value on the hero ELEMENT, and any declaration nearer
	 * the hero than `:root` would permanently shadow it.
	 *
	 * @return string CSS string, or empty string if not applicable.
	 */
	public function get_overlay_hero_base_pad_css(): string {
		if ( ! $this->is_overlay_request() ) {
			return '';
		}

		if ( ! $this->template_renders_post_content_first() ) {
			return '';
		}

		$post = get_post( get_the_ID() );
		if ( ! $post instanceof \WP_Post || ! has_blocks( $post->post_content ) ) {
			return '';
		}

		$raw = $this->get_first_block_padding_top( $post->post_content );
		if ( '' === $raw ) {
			return '';
		}

		// Resolved through the style engine rather than by hand, so
		// `var:preset|spacing|50` becomes the same `var(--wp--preset--spacing--50)`
		// the block itself serializes inline. Matching the block's own output is
		// the point: sticky-header.js copies that inline string verbatim into
		// this property once it runs, so any difference here would surface as a
		// jump at exactly the moment the script takes over.
		$styles = wp_style_engine_get_styles(
			array(
				'spacing' => array(
					'padding' => array( 'top' => $raw ),
				),
			)
		);

		$value = isset( $styles['declarations']['padding-top'] )
			? (string) $styles['declarations']['padding-top']
			: '';

		if ( ! $this->is_safe_css_length( $value ) ) {
			return '';
		}

		return sprintf(
			':root { --dsgo-overlay-hero-base-pad: %s; }',
			$value
		);
	}

	/**
	 * Whether the resolved template puts post content directly after the header.
	 *
	 * This is the check that decides whether the first block of post content is
	 * the element the clearance actually lands on. See
	 * get_overlay_hero_base_pad_css() for why guessing instead of checking makes
	 * a cold load worse rather than better.
	 *
	 * `$_wp_current_template_content` is set by locate_block_template() on the
	 * `template_include` filter, which runs before the template is included and
	 * therefore before `wp_head` — where both callers of this run. It is empty
	 * on a non-block theme and on REST requests, and an empty result correctly
	 * declines to serve.
	 *
	 * Deliberately shallow: it reads TOP-LEVEL template blocks only and accepts
	 * exactly one shape. Anything less familiar — a wrapper, an extra part, a
	 * pattern in between — returns false and the feature no-ops.
	 *
	 * @return bool
	 */
	private function template_renders_post_content_first(): bool {
		global $_wp_current_template_content;

		if ( empty( $_wp_current_template_content ) || ! is_string( $_wp_current_template_content ) ) {
			return false;
		}

		$seen_header = false;

		foreach ( parse_blocks( $_wp_current_template_content ) as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			if ( ! $seen_header ) {
				// The header has to be the first thing in the template for the
				// CSS selectors to match it at all; anything else and this is
				// not a shape worth reasoning about.
				if ( 'core/template-part' !== $block['blockName'] ) {
					return false;
				}

				$seen_header = true;
				continue;
			}

			return 'core/post-content' === $block['blockName'];
		}

		return false;
	}

	/**
	 * Top padding serialized on the first top-level block, if any.
	 *
	 * `parse_blocks()` yields a nameless block for the whitespace between
	 * top-level blocks, so those are skipped rather than mistaken for the hero.
	 *
	 * @param string $content Raw post content.
	 * @return string Unresolved attribute value, or '' when there is none.
	 */
	private function get_first_block_padding_top( string $content ): string {
		foreach ( parse_blocks( $content ) as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$top = $block['attrs']['style']['spacing']['padding']['top'] ?? '';

			return is_string( $top ) ? $top : '';
		}

		return '';
	}

	/**
	 * Whether a resolved value is safe to interpolate into a stylesheet.
	 *
	 * Block attributes are author input: a contributor-authored block or an
	 * imported pattern can carry any string in `style.spacing.padding.top`, and
	 * this value goes straight into a rule. An allowlist of the two shapes the
	 * spacing control actually produces — a bare length, or a spacing preset
	 * var — is the whole contract.
	 *
	 * Rejecting rather than sanitizing is deliberate. A rejected value emits
	 * nothing, the stylesheet's `0px` fallback applies, and the measured value
	 * still lands when the script runs; a half-sanitized one could reserve the
	 * wrong space for good. Functional values (`clamp()`, `calc()`) are refused
	 * on the same grounds — supporting them means parsing them.
	 *
	 * @param string $value Resolved CSS value.
	 * @return bool
	 */
	private function is_safe_css_length( string $value ): bool {
		if ( '' === $value ) {
			return false;
		}

		if ( 1 === preg_match( '/^(0|-?\d+(\.\d+)?(px|rem|em|vh|vw|%))$/', $value ) ) {
			return true;
		}

		return 1 === preg_match( '/^var\(--wp--preset--spacing--[a-zA-Z0-9_-]+\)$/', $value );
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
		//
		// `b` is applied only when get_overlay_hero_base_pad_css() served
		// nothing, and the two must not both fire. This cache is keyed by
		// viewport bucket rather than by page, so `b` is whatever the LAST
		// overlay page's hero used — on a site whose pages differ (0px on the
		// home hero, spacing|sd-large on the About one) that is simply the wrong
		// number for the page being rendered. It is still a better start than
		// zero where nothing was served, but where the server knows the actual
		// value it must not be allowed to win, and it otherwise would: this
		// script writes inline on <html>, which outranks a :root rule.
		$template = <<<'JS'
try{var m=JSON.parse(localStorage.getItem("dsgoOverlayHeaderHeight")||"{}"),w=innerWidth,v=m[w<600?"s":w<1024?"m":"l"],d=document.documentElement,s=%d;if(v&&v.h){d.style.setProperty("--dsgo-overlay-header-height",v.h);if(v.b&&!s)d.style.setProperty("--dsgo-overlay-hero-base-pad",v.b);}}catch(e){}
JS;

		$script = sprintf( $template, '' === $this->get_overlay_hero_base_pad_css() ? 0 : 1 );

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
			$this->get_overlay_header_height_css()
			. "\n" . $this->get_overlay_hero_base_pad_css()
			. "\n" . $this->get_overlay_text_color_css()
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
