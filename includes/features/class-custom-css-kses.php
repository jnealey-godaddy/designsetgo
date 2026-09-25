<?php
/**
 * Save-time capability gate for the Custom CSS extension.
 *
 * `dsgoCustomCSS` reaches the front end as a site-wide `<style>` block, and
 * `selector` is only a convenience: `body{…}`, `url()` beacons and rules that
 * restyle other people's content all pass through. That is the same power as
 * core's Additional CSS, so it gets the same gate — `edit_css`, which maps to
 * `unfiltered_html` (Editors and Administrators on a single site, super admins
 * on multisite). Contributors and Authors could previously write it through
 * the inspector, the Code editor, or the REST API.
 *
 * The attribute is stripped on save for anyone without the capability,
 * mirroring core's own block-level custom CSS gate in WordPress 7.0
 * (`wp_strip_custom_css_from_blocks()` in wp-includes/block-supports/custom-css.php):
 * same hooks, same import handling, and the same token-level splice so the
 * rest of the post content is left byte-for-byte untouched.
 *
 * Like kses, this applies to whoever saves. An Author who edits a post an
 * Administrator gave Custom CSS removes that CSS — the same trade-off core
 * makes for markup the Author's role cannot write.
 *
 * @package DesignSetGo
 * @since   2.8.3
 */

namespace DesignSetGo;

defined( 'ABSPATH' ) || exit;

/**
 * Strips `dsgoCustomCSS` from saved block content for users without `edit_css`.
 */
class Custom_CSS_Kses {

	/**
	 * Attribute that carries the per-block CSS.
	 */
	const ATTRIBUTE = 'dsgoCustomCSS';

	/**
	 * Hook the capability check.
	 *
	 * Idempotent — safe to call multiple times; only hooks once. Runs after
	 * core's kses_init (init priority 10) and again whenever the current user
	 * changes, exactly as core's gate does.
	 */
	public static function register() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;
		add_action( 'init', array( __CLASS__, 'init' ), 20 );
		add_action( 'set_current_user', array( __CLASS__, 'init' ) );
		add_filter( 'force_filtered_html_on_import', array( __CLASS__, 'force_on_import' ), 999 );
	}

	/**
	 * Add or remove the content filters for the current user.
	 */
	public static function init() {
		self::remove_filters();
		if ( ! current_user_can( 'edit_css' ) ) {
			self::add_filters();
		}
	}

	/**
	 * Filter imported content regardless of the importing user's capabilities.
	 *
	 * @param mixed $arg Filter input; truthy when the import should be filtered.
	 * @return mixed Unchanged input.
	 */
	public static function force_on_import( $arg ) {
		if ( $arg ) {
			self::add_filters();
		}
		return $arg;
	}

	/**
	 * Hook the strip. Priority 8 runs before kses (10), as core's gate does.
	 */
	private static function add_filters() {
		add_filter( 'content_save_pre', array( __CLASS__, 'strip' ), 8 );
		add_filter( 'content_filtered_save_pre', array( __CLASS__, 'strip' ), 8 );
	}

	/**
	 * Unhook the strip.
	 */
	private static function remove_filters() {
		remove_filter( 'content_save_pre', array( __CLASS__, 'strip' ), 8 );
		remove_filter( 'content_filtered_save_pre', array( __CLASS__, 'strip' ), 8 );
	}

	/**
	 * Remove `dsgoCustomCSS` from every block comment in slashed post content.
	 *
	 * Walks block tokens with WP_Block_Parser::next_token() and rewrites only
	 * the attribute JSON of blocks that carried the attribute — no
	 * parse_blocks() + serialize_blocks() round-trip.
	 *
	 * @param string $content Post content, slashed.
	 * @return string Content without custom CSS, slashed.
	 */
	public static function strip( $content ) {
		// No strpos() shortcut on the attribute name: block JSON may spell it
		// with \u escapes, which json_decode() — and so parse_blocks() — honours.
		if ( ! is_string( $content ) || ! has_blocks( $content ) ) {
			return $content;
		}

		$unslashed = stripslashes( $content );

		$parser           = new \WP_Block_Parser();
		$parser->document = $unslashed;
		$parser->offset   = 0;
		$end              = strlen( $unslashed );
		$replacements     = array();

		while ( (int) $parser->offset < $end ) {
			$next_token = $parser->next_token();

			if ( 'no-more-tokens' === $next_token[0] ) {
				break;
			}

			list( $token_type, , $attrs, $start_offset, $token_length ) = $next_token;

			$parser->offset = $start_offset + $token_length;

			if ( ( 'block-opener' !== $token_type && 'void-block' !== $token_type ) || ! is_array( $attrs ) || ! array_key_exists( self::ATTRIBUTE, $attrs ) ) {
				continue;
			}

			unset( $attrs[ self::ATTRIBUTE ] );

			// Locate the JSON portion within the token.
			$token_string   = substr( $unslashed, $start_offset, $token_length );
			$json_rel_start = strcspn( $token_string, '{' );
			$json_rel_end   = strrpos( $token_string, '}' );
			$json_start     = $start_offset + $json_rel_start;
			$json_length    = $json_rel_end - $json_rel_start + 1;

			if ( empty( $attrs ) ) {
				// Drop the JSON and the space that follows it.
				$replacements[] = array( $json_start, $json_length + 1, '' );
			} else {
				$replacements[] = array( $json_start, $json_length, serialize_block_attributes( $attrs ) );
			}
		}

		if ( empty( $replacements ) ) {
			return $content;
		}

		$result = '';
		$was_at = 0;
		foreach ( $replacements as $replacement ) {
			list( $offset, $length, $new_json ) = $replacement;

			$result .= substr( $unslashed, $was_at, $offset - $was_at ) . $new_json;
			$was_at  = $offset + $length;
		}
		if ( $was_at < $end ) {
			$result .= substr( $unslashed, $was_at );
		}

		return addslashes( $result );
	}
}
