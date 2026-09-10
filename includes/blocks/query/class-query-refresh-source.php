<?php
/**
 * Dynamic Query — signed refresh source.
 *
 * Filters, sort, search, Load more and infinite scroll re-render a query over
 * the public REST route. That route must only ever render a query the site
 * itself put on a page, wherever it was placed — post content, a template or
 * template part, a synced pattern, a widget, or another query's item.
 *
 * So first paint embeds the query's definition (attributes + inner blocks)
 * with an HMAC signature, and the route renders only a definition whose
 * signature verifies. The payload is base64 in a data attribute because it
 * passes through the_content: filters like capital_P_dangit() rewrite text
 * inside inline <script> JSON, and any rewrite would break the signature.
 *
 * @package DesignSetGo
 * @since 2.7.4
 */

namespace DesignSetGo\Blocks\Query;

defined( 'ABSPATH' ) || exit;

/**
 * Signs, verifies and authorises Dynamic Query refresh sources.
 */
class RefreshSource {

	/**
	 * Payload format version.
	 */
	const VERSION = 1;

	/**
	 * Post IDs whose content is being rendered, innermost last.
	 *
	 * @var int[]
	 */
	private static $content_posts = array();

	/**
	 * Track which post's content is rendering, so a query knows whether it
	 * sits inside a post (and inherits that post's visibility) or in a
	 * template, pattern or widget (public wherever it renders).
	 *
	 * @return void
	 */
	public static function bootstrap() {
		add_filter( 'the_content', array( __CLASS__, 'enter_content' ), 1 );
		add_filter( 'the_content', array( __CLASS__, 'leave_content' ), PHP_INT_MAX );
	}

	/**
	 * Record the post whose content is about to render.
	 *
	 * @param string $content Post content.
	 * @return string Unchanged content.
	 */
	public static function enter_content( $content ) {
		self::$content_posts[] = (int) get_the_ID();
		return $content;
	}

	/**
	 * Forget the post whose content just rendered.
	 *
	 * @param string $content Rendered content.
	 * @return string Unchanged content.
	 */
	public static function leave_content( $content ) {
		array_pop( self::$content_posts );
		return $content;
	}

	/**
	 * The post whose content the current block is rendering inside, or 0.
	 *
	 * @return int Post ID.
	 */
	public static function current_content_post_id() {
		return empty( self::$content_posts ) ? 0 : (int) end( self::$content_posts );
	}

	/**
	 * Render as though inside a post's content.
	 *
	 * A REST refresh runs outside the_content, so without this a query nested
	 * in the refreshed region would be re-signed as public (source 0) even
	 * when the region it belongs to is gated behind a private post.
	 *
	 * @param int      $post_id Post whose content holds the query, or 0.
	 * @param callable $render  Render callback.
	 * @return mixed Callback result.
	 */
	public static function render_within( $post_id, callable $render ) {
		self::$content_posts[] = (int) $post_id;
		try {
			return $render();
		} finally {
			array_pop( self::$content_posts );
		}
	}

	/**
	 * Encode and sign a query definition.
	 *
	 * @param string $query_id       Sanitized query ID.
	 * @param array  $attributes     Query block attributes.
	 * @param string $inner_blocks   Serialized inner blocks.
	 * @param int    $source_post_id Post whose content holds the query, or 0.
	 * @return array{source: string, signature: string}
	 */
	public static function sign( $query_id, array $attributes, $inner_blocks, $source_post_id ) {
		$source = base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- transport encoding, see class docblock.
			(string) wp_json_encode(
				array(
					'v'            => self::VERSION,
					'queryId'      => (string) $query_id,
					'sourcePostId' => absint( $source_post_id ),
					'attributes'   => $attributes,
					'innerBlocks'  => (string) $inner_blocks,
				)
			)
		);

		return array(
			'source'    => $source,
			'signature' => self::signature( $source ),
		);
	}

	/**
	 * Verify and decode a signed source.
	 *
	 * @param mixed  $source    Encoded source from the request.
	 * @param mixed  $signature Signature from the request.
	 * @param string $query_id  Query ID the request names.
	 * @return array{queryId: string, sourcePostId: int, attributes: array, innerBlocks: string}|null Definition, or null when it doesn't verify.
	 */
	public static function verify( $source, $signature, $query_id ) {
		if ( ! is_string( $source ) || '' === $source || ! is_string( $signature ) ) {
			return null;
		}

		if ( ! hash_equals( self::signature( $source ), $signature ) ) {
			return null;
		}

		$data = json_decode( (string) base64_decode( $source, true ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- transport encoding, see class docblock.
		if (
			! is_array( $data ) ||
			! isset( $data['v'], $data['queryId'] ) ||
			self::VERSION !== $data['v'] ||
			$data['queryId'] !== (string) $query_id ||
			! is_array( $data['attributes'] ?? null ) ||
			! is_string( $data['innerBlocks'] ?? null )
		) {
			return null;
		}

		return array(
			'queryId'      => (string) $data['queryId'],
			'sourcePostId' => absint( $data['sourcePostId'] ?? 0 ),
			'attributes'   => $data['attributes'],
			'innerBlocks'  => $data['innerBlocks'],
		);
	}

	/**
	 * Whether the current user may see the post a query was placed in.
	 *
	 * A query outside any post content (sourcePostId 0) rendered for whoever
	 * loaded that template, pattern or widget, so it stays public.
	 *
	 * @param int $source_post_id Post whose content holds the query, or 0.
	 * @return bool
	 */
	public static function can_view_source( $source_post_id ) {
		if ( ! $source_post_id ) {
			return true;
		}

		$post = get_post( $source_post_id );
		if ( ! $post || post_password_required( $post ) ) {
			return false;
		}

		return is_post_publicly_viewable( $post ) || current_user_can( 'read_post', $post->ID );
	}

	/**
	 * HMAC over the encoded source, keyed to this site.
	 *
	 * The blog ID is part of the message because AUTH_SALT is shared across a
	 * multisite network: without it, one site's signed query would render on
	 * another, against that site's content.
	 *
	 * @param string $source Encoded source.
	 * @return string Hex signature.
	 */
	private static function signature( $source ) {
		return hash_hmac( 'sha256', 'designsetgo/query-refresh-source|' . get_current_blog_id() . '|' . $source, wp_salt( 'auth' ) );
	}
}
