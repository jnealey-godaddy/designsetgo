<?php
/**
 * Style Binding — resolves dsgoStyleBinding attribute into inline CSS.
 *
 * Registers a render_block filter that reads the dsgoStyleBinding attribute,
 * resolves each CSS property → binding source → value, and injects the result
 * as an inline style on the block's root element.
 *
 * @package DesignSetGo
 * @since 2.5.0
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/**
 * StyleBinding class.
 */
class StyleBinding {

	/**
	 * Sources that address a named custom field and are meaningless without one.
	 *
	 * Keyless sources (designsetgo/woo-*, post-*, archive-*) are resolved via
	 * resolve_registered_source() instead.
	 *
	 * @var string[]
	 */
	private const KEYED_SOURCES = array(
		'designsetgo/post-meta',
		'designsetgo/acf',
		'designsetgo/metabox',
		'designsetgo/pods',
		'designsetgo/jetengine',
	);

	/**
	 * Constructor — registers the render_block filter.
	 */
	public function __construct() {
		add_filter( 'render_block', array( $this, 'apply_style_bindings' ), 5, 2 );
	}

	/**
	 * Resolve dsgoStyleBinding entries and inject them as inline styles.
	 *
	 * @param string $html  Rendered block HTML.
	 * @param array  $block Block data.
	 * @return string Modified HTML.
	 */
	public function apply_style_bindings( string $html, array $block ): string {
		$binding = $block['attrs']['dsgoStyleBinding'] ?? null;
		if ( empty( $binding ) || ! is_array( $binding ) ) {
			return $html;
		}

		$styles = array();
		foreach ( $binding as $prop => $config ) {
			// Validate CSS property: custom property (--foo) or standard property
			// including vendor prefix (-webkit-…) and digits (line2-color etc.).
			if ( ! is_string( $prop ) || ! preg_match( '/^--[a-zA-Z][a-zA-Z0-9\-_]*$|^-?[a-z][a-z0-9\-]*$/', $prop ) ) {
				continue;
			}

			if ( ! is_array( $config ) ) {
				continue;
			}

			$source = sanitize_text_field( (string) ( $config['source'] ?? '' ) );
			$args   = is_array( $config['args'] ?? null ) ? $config['args'] : array();

			/**
			 * Filter: resolve a style binding value.
			 *
			 * Third-party sources can hook here to supply values for custom sources.
			 *
			 * @param string|null $value  Resolved value (may be null if unresolved).
			 * @param string      $source Source identifier (e.g. 'designsetgo/post-meta').
			 * @param array       $args   Source-specific arguments.
			 */
			$value = apply_filters(
				'designsetgo_style_binding_resolve',
				$this->resolve( $source, $args ),
				$source,
				$args
			);

			if ( null === $value || '' === $value ) {
				continue;
			}

			// Reject values that could execute code or break out of the
			// declaration: url(), expression(), javascript:/data: schemes,
			// CSS curly braces, and embedded semicolons.
			//
			// Test the ESCAPE-DECODED value, never the raw one. CSS escapes are
			// resolved by the browser before the declaration applies, so a raw
			// match here is trivially bypassed: `\75\72\6c(//evil.test)` is
			// `url(//evil.test)` to a browser but matches none of the patterns
			// below as written. See designsetgo_normalize_css_escapes().
			$probe = designsetgo_normalize_css_escapes( (string) $value );

			if ( preg_match( '/url\s*\(|expression\s*\(|javascript:|data:/i', $probe ) ) {
				continue;
			}
			if ( false !== strpbrk( $probe, ';{}' ) ) {
				continue;
			}

			// Defence in depth: reject any value that carried an escape at all.
			// Style bindings resolve to lengths, colours and keywords, none of
			// which have any legitimate reason to be escaped — so rather than
			// depend on this decoder agreeing with every browser's tokenizer on
			// every edge case, refuse the value outright when the two forms
			// differ. The cost of a false reject is one binding not applying.
			if ( $probe !== (string) $value ) {
				continue;
			}

			$styles[] = $prop . ':' . $value;
		}

		if ( empty( $styles ) ) {
			return $html;
		}

		$processor = new \WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag() ) {
			return $html;
		}

		$existing = trim( (string) ( $processor->get_attribute( 'style' ) ?? '' ) );
		$sep      = ( '' !== $existing && ';' !== substr( $existing, -1 ) ) ? ';' : '';
		$processor->set_attribute( 'style', $existing . $sep . implode( ';', $styles ) );

		return $processor->get_updated_html();
	}

	/**
	 * Resolve a binding value from a built-in source.
	 *
	 * @param string $source Source identifier.
	 * @param array  $args   Source arguments.
	 * @return string|null Resolved value, or null if unresolvable.
	 */
	private function resolve( string $source, array $args ): ?string {
		$post_id = $this->current_post_id();
		$key     = sanitize_text_field( (string) ( $args['key'] ?? '' ) );

		if ( ! $post_id ) {
			return null;
		}

		// The custom-field sources below are meaningless without a key, and this
		// used to be an unconditional guard. Keyless sources (designsetgo/woo-*,
		// post-*, archive-*) resolve through the registered-source path instead.
		if ( '' === $key && in_array( $source, self::KEYED_SOURCES, true ) ) {
			return null;
		}

		// Apply the same security gates the v2.4 block bindings adapter uses
		// (see includes/bindings/class-query-bindings-helpers.php) so style
		// bindings cannot leak data block bindings would withhold.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		if ( post_password_required( $post ) ) {
			return null;
		}
		if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) {
			return null;
		}
		if ( '' !== $key && is_protected_meta( $key, 'post' ) ) {
			return null;
		}

		switch ( $source ) {
			case 'designsetgo/post-meta':
				$val = get_post_meta( $post_id, $key, true );
				return is_scalar( $val ) ? (string) $val : null;

			case 'designsetgo/acf':
				if ( ! function_exists( 'get_field' ) ) {
					return null;
				}
				$val = get_field( $key, $post_id );
				return is_scalar( $val ) ? (string) $val : null;

			case 'designsetgo/metabox':
				if ( ! function_exists( 'rwmb_meta' ) ) {
					return null;
				}
				$val = rwmb_meta( $key, array(), $post_id );
				return is_scalar( $val ) ? (string) $val : null;

			case 'designsetgo/pods':
				if ( ! function_exists( 'pods_field' ) ) {
					return null;
				}
				$val = pods_field( $key, $post_id );
				return is_scalar( $val ) ? (string) $val : null;

			case 'designsetgo/jetengine':
				if ( function_exists( 'jet_engine' ) && isset( jet_engine()->listings->data ) && method_exists( jet_engine()->listings->data, 'get_meta' ) ) {
					$val = jet_engine()->listings->data->get_meta( $key, $post_id );
				} else {
					$val = get_post_meta( $post_id, $key, true );
				}
				return is_scalar( $val ) ? (string) $val : null;

			default:
				return $this->resolve_registered_source( $source, $args, $post_id );
		}
	}

	/**
	 * Resolve a value from a registered DesignSetGo block-bindings source.
	 *
	 * The switch above predates the keyless sources (`designsetgo/woo-*`,
	 * `post-*`, `archive-*`), which take no `key` argument and so could never
	 * satisfy it. Rather than extend the switch every time a source is added,
	 * delegate to whatever is registered.
	 *
	 * This is what makes a stock bar possible: `progress-bar` with
	 * `--dsgo-progress` bound to `designsetgo/woo-stock-quantity`. No
	 * WooCommerce block exposes a numeric stock value, so nothing else can.
	 *
	 * Restricted to the `designsetgo/` prefix so third-party bindings sources
	 * are not silently exposed to the style layer, and the resolved value still
	 * passes through the dangerous-value rejection in the caller.
	 *
	 * @param string $source  Source identifier.
	 * @param array  $args    Source arguments.
	 * @param int    $post_id Resolved post ID.
	 * @return string|null Resolved value, or null if unresolvable.
	 */
	private function resolve_registered_source( string $source, array $args, int $post_id ): ?string {
		if ( 0 !== strpos( $source, 'designsetgo/' ) ) {
			return null;
		}

		if ( ! function_exists( 'get_block_bindings_source' ) ) {
			return null;
		}

		$registered = get_block_bindings_source( $source );
		if ( null === $registered ) {
			return null;
		}

		// A style binding produces a CSS value, so a source that returns markup
		// has no business here. `designsetgo/woo-price-html` is the live example:
		// its `<span class="woocommerce-Price-amount">…</span>` would be handed to
		// a custom property. Not an XSS route — WP_HTML_Tag_Processor escapes into
		// the style attribute and the caller rejects `;{}` — but it would silently
		// produce a garbled declaration with no feedback to the author.
		//
		// Sources unknown to the Dynamic Tags registry are still allowed, so a
		// third-party source registered only through
		// designsetgo_register_bindings_source() keeps working.
		if ( $this->source_returns_markup( $source ) ) {
			return null;
		}

		// Pre-resolved so the source does not re-derive it from block context,
		// which does not exist on the style-binding path.
		$args['__dsgo_post_id'] = $post_id;

		$value = $registered->get_value( $args, null, 'style' );

		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$value = (string) $value;

		// Structural backstop, independent of the declared `returns` above. That
		// check trusts registry metadata, and nothing validates a source's
		// declaration against what it actually returns — a source could claim
		// `text` and emit markup. No valid CSS value contains angle brackets, so
		// reject them outright and the guarantee no longer rests on metadata
		// being accurate.
		if ( false !== strpbrk( $value, '<>' ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Whether a source declares that it returns markup rather than a scalar.
	 *
	 * Reads the Dynamic Tags registry metadata, which is where `returns` lives.
	 * Returns false for sources the registry does not know about, so unregistered
	 * third-party sources keep their existing behaviour.
	 *
	 * MAINTAINER NOTE: this is an advisory check, not a guarantee. Nothing at
	 * registration time validates that a source's declared `returns` matches what
	 * its callback actually produces, so a source could declare `text` and emit
	 * markup. The caller therefore also applies a structural `<>` rejection that
	 * does not depend on this metadata. When adding a source that returns markup,
	 * declare `returns` as `array( 'html' )` so it is refused here explicitly
	 * rather than relying on that backstop.
	 *
	 * @param string $source Source identifier.
	 * @return bool True when the source declares an `html` return type.
	 */
	private function source_returns_markup( string $source ): bool {
		if ( ! class_exists( '\DesignSetGo\Blocks\DynamicTags\Registry' ) ) {
			return false;
		}

		$meta = \DesignSetGo\Blocks\DynamicTags\Registry::instance()->get_source( $source );
		if ( null === $meta ) {
			return false;
		}

		return in_array( 'html', (array) ( $meta['returns'] ?? array() ), true );
	}

	/**
	 * Determine the current post ID, preferring the DSGo parent stack.
	 *
	 * @return int|null Post ID, or null if not in a post context.
	 */
	private function current_post_id(): ?int {
		$stack = $GLOBALS['designsetgo_parent_stack'] ?? array();
		if ( ! empty( $stack ) ) {
			$top    = end( $stack );
			$top_id = (int) ( $top['postId'] ?? 0 );
			return $top_id ? $top_id : null;
		}
		$id = get_the_ID();
		return $id ? (int) $id : null;
	}
}
