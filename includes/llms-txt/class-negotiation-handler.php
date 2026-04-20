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

		$preferred = self::preferred_type( $accept );

		if ( 'none' === $preferred ) {
			$this->send_406();
			return;
		}

		if ( 'markdown' !== $preferred ) {
			return; // HTML wins; let WordPress render normally.
		}

		// Feature must be enabled to serve per-URL Markdown.
		$settings = \DesignSetGo\Admin\Settings::get_settings();
		if ( empty( $settings['llms_txt']['enable'] ) ) {
			return;
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
	 * Wildcards (`text/*`, `*\/*`) count for both html and markdown.
	 *
	 * @param string $accept Raw Accept header value.
	 * @return string One of 'markdown' | 'html' | 'none'.
	 */
	public static function preferred_type( string $accept ): string {
		$md_q   = 0.0;
		$html_q = 0.0;
		$saw    = false;

		foreach ( explode( ',', $accept ) as $raw ) {
			$raw = trim( $raw );
			if ( '' === $raw ) {
				continue;
			}

			$parts = array_map( 'trim', explode( ';', $raw ) );
			$type  = strtolower( (string) array_shift( $parts ) );
			$q     = 1.0;

			foreach ( $parts as $param ) {
				if ( 0 === stripos( $param, 'q=' ) ) {
					$q = (float) substr( $param, 2 );
					break;
				}
			}

			if ( $q <= 0 ) {
				continue; // Explicit rejection.
			}

			$saw = true;

			switch ( $type ) {
				case 'text/markdown':
					$md_q = max( $md_q, $q );
					break;
				case 'text/html':
					$html_q = max( $html_q, $q );
					break;
				case 'text/*':
				case '*/*':
					$md_q   = max( $md_q, $q );
					$html_q = max( $html_q, $q );
					break;
			}
		}

		if ( ! $saw || ( 0.0 === $md_q && 0.0 === $html_q ) ) {
			return 'none';
		}

		return ( $md_q > $html_q ) ? 'markdown' : 'html';
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
				$path = $this->file_manager->get_directory() . '/' . $filename . '.md';
				if ( is_readable( $path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file, size-bounded.
					$content = file_get_contents( $path );
					if ( is_string( $content ) && '' !== $content ) {
						return $content;
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
		header( 'Content-Length: ' . strlen( $markdown ) );
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
