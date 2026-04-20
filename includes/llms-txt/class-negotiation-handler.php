<?php
/**
 * Content-Negotiation Handler
 *
 * Serves per-URL Markdown when the request advertises
 * `Accept: text/markdown` with higher preference than `text/html`.
 *
 * Mirrors the acceptmarkdown.com readiness contract: honor q-values,
 * set `Vary: Accept`, reject fully-unsupported Accept headers with 406.
 *
 * @package DesignSetGo
 * @since   1.5.0
 */

namespace DesignSetGo\LLMS_Txt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Negotiation_Handler
 *
 * Hooks `template_redirect` and, when the Accept header prefers Markdown,
 * serves the post's pre-generated `.md` file (or converts on the fly) and
 * short-circuits normal template loading.
 */
class Negotiation_Handler {

	/**
	 * File manager.
	 *
	 * @var File_Manager
	 */
	private $file_manager;

	/**
	 * Markdown generator (fallback source).
	 *
	 * @var Generator
	 */
	private $generator;

	/**
	 * Constructor.
	 *
	 * @param File_Manager $file_manager File manager.
	 * @param Generator    $generator    Generator.
	 */
	public function __construct( File_Manager $file_manager, Generator $generator ) {
		$this->file_manager = $file_manager;
		$this->generator    = $generator;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * Priority 11 so `redirect_canonical` (priority 10) settles first —
	 * we only want to negotiate on the final canonical URL.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle_request' ), 11 );
	}

	/**
	 * Template_redirect callback.
	 */
	public function handle_request(): void {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return;
		}

		if ( ! isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			return;
		}

		$accept = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) );
		if ( '' === $accept ) {
			return;
		}

		// Feature must be enabled before we change any HTTP semantics.
		// Without this guard, a disabled plugin would still answer 406
		// to unrelated Accept headers on every page.
		$settings = \DesignSetGo\Admin\Settings::get_settings();
		if ( empty( $settings['llms_txt']['enable'] ) ) {
			return;
		}

		$preferred = self::preferred_type( $accept );

		if ( 'none' === $preferred ) {
			$this->send_406();
			return;
		}

		if ( 'markdown' !== $preferred ) {
			return; // HTML wins; let WordPress render normally.
		}

		// Front page or blog posts index: serve the llms.txt listing as Markdown.
		if ( is_front_page() || is_home() ) {
			$markdown = $this->generator->generate_content();
			if ( '' !== $markdown ) {
				$this->send_markdown( $markdown );
				exit;
			}
			return;
		}

		$post = self::resolve_post();
		if ( ! $post ) {
			return;
		}

		$enabled_types = $settings['llms_txt']['post_types'] ?? array( 'page', 'post' );
		if ( ! in_array( $post->post_type, $enabled_types, true ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! empty( $post->post_password ) ) {
			return;
		}

		if ( get_post_meta( $post->ID, Controller::EXCLUDE_META_KEY, true ) ) {
			return;
		}

		$markdown = $this->read_markdown( $post );
		if ( '' === $markdown ) {
			return; // Conversion failed; fall through to HTML.
		}

		$this->send_markdown( $markdown );
		exit;
	}

	/**
	 * Parse an Accept header and return the preferred representation.
	 *
	 * Returns one of:
	 *  - `'markdown'` — `text/markdown` outranks `text/html` (strictly greater q).
	 *  - `'html'`     — `text/html` is acceptable at q > 0 (ties go to html).
	 *  - `'none'`     — Accept was present but rejects html AND markdown.
	 *
	 * Honors RFC 7231 §5.3.2 media-range specificity: an explicit
	 * `text/html` or `text/markdown` q-value always overrides a wildcard
	 * (`text/*`, `*\/*`). Without this, `Accept: *\/*;q=0.9, text/html;q=0.5`
	 * would leak wildcard weight onto `text/markdown` and misresolve the tie.
	 *
	 * Invalid q-values (`q=abc`, `q=1.5`, `q=-1`) are treated as missing and
	 * default to 1.0 rather than 0.0, so a malformed parameter cannot silently
	 * turn a positive preference into a rejection.
	 *
	 * @param string $accept Raw Accept header value.
	 * @return string One of 'markdown' | 'html' | 'none'.
	 */
	public static function preferred_type( string $accept ): string {
		$md_explicit   = null; // null = no explicit text/markdown entry seen.
		$html_explicit = null;
		$wild_q        = 0.0;  // best q from text/* or */*.
		$saw           = false;

		foreach ( explode( ',', $accept ) as $raw ) {
			$raw = trim( $raw );
			if ( '' === $raw ) {
				continue;
			}

			$parts = array_map( 'trim', explode( ';', $raw ) );
			$type  = strtolower( (string) array_shift( $parts ) );
			$q     = self::parse_q_value( $parts );

			if ( null === $q ) {
				continue; // Explicit rejection (q=0) or nonsense we can't interpret as a preference.
			}

			$saw = true;

			switch ( $type ) {
				case 'text/markdown':
					$md_explicit = ( null === $md_explicit ) ? $q : max( $md_explicit, $q );
					break;
				case 'text/html':
					$html_explicit = ( null === $html_explicit ) ? $q : max( $html_explicit, $q );
					break;
				case 'text/*':
				case '*/*':
					$wild_q = max( $wild_q, $q );
					break;
			}
		}

		// Specificity: explicit entries override wildcards (RFC 7231 §5.3.2).
		$md_q   = ( null !== $md_explicit ) ? $md_explicit : $wild_q;
		$html_q = ( null !== $html_explicit ) ? $html_explicit : $wild_q;

		if ( ! $saw || ( 0.0 === $md_q && 0.0 === $html_q ) ) {
			return 'none';
		}

		return ( $md_q > $html_q ) ? 'markdown' : 'html';
	}

	/**
	 * Extract the q-value from a media-range parameter list.
	 *
	 * Returns `null` when the entry is an explicit rejection (`q=0`),
	 * otherwise a float in (0, 1]. Malformed q-values (`q=abc`, `q=1.5`,
	 * negative) default to 1.0 per "treat invalid as missing."
	 *
	 * @param array $params Semicolon-delimited parameter list (already trimmed).
	 * @return float|null
	 */
	private static function parse_q_value( array $params ): ?float {
		foreach ( $params as $param ) {
			if ( 0 !== stripos( $param, 'q=' ) ) {
				continue;
			}
			$raw = substr( $param, 2 );
			if ( ! is_numeric( $raw ) ) {
				return 1.0; // Malformed → treat as missing.
			}
			$q = (float) $raw;
			if ( $q <= 0 ) {
				return null; // Explicit rejection.
			}
			if ( $q > 1 ) {
				return 1.0; // Out-of-range → clamp.
			}
			return $q;
		}
		return 1.0; // No q= param → default weight.
	}

	/**
	 * Resolve the singular post for this request, if any.
	 *
	 * @return \WP_Post|null
	 */
	private static function resolve_post(): ?\WP_Post {
		if ( ! is_singular() ) {
			return null;
		}
		$obj = get_queried_object();
		return ( $obj instanceof \WP_Post ) ? $obj : null;
	}

	/**
	 * Read the post's Markdown — prefer the static file, fall back to conversion.
	 *
	 * @param \WP_Post $post Post.
	 * @return string Markdown body (empty string on failure).
	 */
	private function read_markdown( \WP_Post $post ): string {
		if ( $this->file_manager->file_exists( $post->ID ) ) {
			$filename = $this->file_manager->get_filename( $post );
			if ( '' !== $filename ) {
				$directory     = $this->file_manager->get_directory();
				$path          = $directory . '/' . $filename . '.md';
				$resolved_dir  = realpath( $directory );
				$resolved_path = realpath( $path );

				// Defense-in-depth: confirm the resolved file is actually under
				// the markdown directory. `get_filename()` sanitizes each slug
				// segment, but this mirrors the containment check used in
				// `Generator::generate_full_content()`.
				if ( false !== $resolved_dir && false !== $resolved_path ) {
					$normalized_dir  = wp_normalize_path( trailingslashit( $resolved_dir ) );
					$normalized_path = wp_normalize_path( $resolved_path );

					if ( 0 === strpos( $normalized_path, $normalized_dir ) && is_readable( $resolved_path ) ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file, containment-checked.
						$content = file_get_contents( $resolved_path );
						if ( is_string( $content ) && '' !== $content ) {
							return $content;
						}
					}
				}
			}
		}

		$converter = new \DesignSetGo\Markdown\Converter();
		return (string) $converter->convert( $post );
	}

	/**
	 * Emit a 200 Markdown response.
	 *
	 * @param string $markdown Body.
	 */
	private function send_markdown( string $markdown ): void {
		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Vary: Accept' );
		header( 'X-Robots-Tag: noindex' );
		// Intentionally no Content-Length: it becomes incorrect under
		// mod_deflate / ob_gzhandler, causing truncated or hung responses.
		echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown body.
	}

	/**
	 * Emit 406 Not Acceptable when the client rejects both html and markdown.
	 */
	private function send_406(): void {
		nocache_headers();
		status_header( 406 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Vary: Accept' );
		echo "Not Acceptable. Supported media types: text/html, text/markdown.\n";
		exit;
	}
}
