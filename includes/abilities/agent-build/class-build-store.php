<?php
/**
 * Post-meta store for agent-submitted block trees awaiting assembly.
 *
 * A remote agent submits a block tree through the designsetgo/build-page
 * ability (Task 19); PHP cannot run a block's save(), so the tree is parked
 * here as a PENDING build rather than written into post_content. An editor
 * plugin (Task 20) later reads the pending tree over REST, assembles it with
 * the real save() in the browser, and posts a report back describing the
 * outcome. This class owns the two post meta keys that make up that
 * handshake and never touches post_content or post_modified_gmt itself.
 *
 * @package DesignSetGo
 * @subpackage Abilities
 * @since 2.6.0
 */

namespace DesignSetGo\Abilities\Agent_Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build_Store class.
 *
 * A plain post-meta wrapper, not an Abstract_Ability. It lives in this
 * directory so Task 19's build-page ability can reach it, but
 * Abilities_Registry only instantiates classes here that are actual
 * Abstract_Ability subclasses, so it is never registered as an ability
 * itself.
 */
class Build_Store {

	/**
	 * Meta key holding the pending tree as a JSON string:
	 * `{ tree, mode, base, submitter, submitterUnfiltered, buildId }`, where
	 * `base` is the post's `post_modified_gmt` at the moment the tree was
	 * stored, `submitter` is the id of the user who stored it,
	 * `submitterUnfiltered` is whether that user held `unfiltered_html`, and
	 * `buildId` is a fresh UUID every report about this build must echo back.
	 * `signature` is an HMAC over those fields and the post id (see sign());
	 * pending() ignores a blob whose signature is missing or does not verify.
	 *
	 * @var string
	 */
	const META_PENDING_TREE = '_dsgo_pending_tree';

	/**
	 * Meta key holding the latest build report as a JSON string.
	 *
	 * @var string
	 */
	const META_BUILD_REPORT = '_dsgo_build_report';

	/**
	 * Constructor. Registers the meta keys on `init`.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Register both meta keys as protected (leading underscore, not
	 * `show_in_rest`) post meta that grants nobody a meta capability.
	 *
	 * The auth callback backs add/edit/delete_post_meta, which is all
	 * XML-RPC custom fields and the classic Custom Fields box check. The
	 * pending blob decides who may auto-save a build and whether its markup
	 * is filtered, so only this class, through update_post_meta() (which
	 * checks no capability), may write either key.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		$args = array(
			'single'        => true,
			'type'          => 'string',
			'show_in_rest'  => false,
			'auth_callback' => '__return_false',
		);

		register_post_meta( '', self::META_PENDING_TREE, $args );
		register_post_meta( '', self::META_BUILD_REPORT, $args );
	}

	/**
	 * Store a submitted tree as the post's pending build.
	 *
	 * Uses update_post_meta() only - never wp_update_post() - so storing a
	 * pending build does not itself change post_modified_gmt. That would
	 * make the base captured here immediately stale and turn the very next
	 * GET into a false conflict.
	 *
	 * The current user is recorded as the build's submitter: the editor only
	 * auto-saves a build for the person who submitted it, and sends the
	 * assembled markup through KSES first unless they held unfiltered_html.
	 *
	 * @param int    $post_id Post the tree targets.
	 * @param array  $tree    Well-formed block tree (already validated by the caller).
	 * @param string $mode    Assembly mode, e.g. 'replace' or 'append'.
	 * @return void
	 */
	public function store( int $post_id, array $tree, string $mode ): void {
		$base     = get_post_field( 'post_modified_gmt', $post_id );
		$build_id = wp_generate_uuid4();

		// Normalized through one JSON round trip first, so the signature is
		// computed over exactly the values pending() will decode.
		$blob = json_decode(
			(string) wp_json_encode(
				array(
					'tree'                => $tree,
					'mode'                => $mode,
					'base'                => is_string( $base ) ? $base : '',
					'submitter'           => get_current_user_id(),
					'submitterUnfiltered' => current_user_can( 'unfiltered_html' ),
					'buildId'             => $build_id,
				)
			),
			true
		);

		$blob['signature'] = self::sign( $blob, $post_id );

		// update_post_meta() unslashes its value, which would strip the
		// backslashes wp_json_encode() escapes quotes, newlines, and
		// non-ASCII with - leaving invalid or silently corrupted JSON.
		update_post_meta( $post_id, self::META_PENDING_TREE, wp_slash( wp_json_encode( $blob ) ) );

		$this->write_report(
			$post_id,
			array(
				'status'   => 'pending',
				'buildId'  => $build_id,
				'treeHash' => $this->hash_tree( $tree ),
			)
		);
	}

	/**
	 * Read the post's pending build, if any. A blob this class did not sign,
	 * or one changed since, is not a pending build.
	 *
	 * @param int $post_id Post to read.
	 * @return array{tree: array, mode: string, base: string, submitter: int, submitterUnfiltered: bool, buildId: string}|null Decoded pending build, or null when there is none.
	 */
	public function pending( int $post_id ): ?array {
		$raw = get_post_meta( $post_id, self::META_PENDING_TREE, true );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}

		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) || ! isset( $decoded['tree'], $decoded['mode'], $decoded['base'] ) ) {
			return null;
		}

		if ( ! isset( $decoded['signature'] ) || ! is_string( $decoded['signature'] ) || ! hash_equals( self::sign( $decoded, $post_id ), $decoded['signature'] ) ) {
			return null;
		}
		unset( $decoded['signature'] );

		$decoded['submitter'] = isset( $decoded['submitter'] ) ? (int) $decoded['submitter'] : 0;
		$decoded['buildId']   = isset( $decoded['buildId'] ) && is_string( $decoded['buildId'] ) ? $decoded['buildId'] : '';
		// Strictly true: a build stored without the flag is treated as filtered.
		$decoded['submitterUnfiltered'] = isset( $decoded['submitterUnfiltered'] ) && true === $decoded['submitterUnfiltered'];

		return $decoded;
	}

	/**
	 * HMAC over the canonical JSON of every field a decision reads, plus the
	 * post the blob belongs to, keyed with the site's auth salt. Binding the
	 * post id means a valid blob copied onto another post never verifies.
	 *
	 * @param array<string, mixed> $blob    Decoded pending blob.
	 * @param int                  $post_id Post the blob is stored on.
	 * @return string Hex HMAC-SHA256.
	 */
	private static function sign( array $blob, int $post_id ): string {
		$fields = array( 'postId' => $post_id );
		foreach ( array( 'submitter', 'submitterUnfiltered', 'buildId', 'base', 'mode', 'tree' ) as $key ) {
			$fields[ $key ] = $blob[ $key ] ?? null;
		}

		return hash_hmac( 'sha256', (string) wp_json_encode( $fields ), wp_salt( 'auth' ) );
	}

	/**
	 * Whether a build id names the build currently pending for a post. A
	 * stale tab, or a request sent after the tree was cleared, must never act
	 * on a different build.
	 *
	 * @param int    $post_id  Post to check.
	 * @param string $build_id Build id the caller claims.
	 * @return bool
	 */
	public function is_pending_build( int $post_id, string $build_id ): bool {
		$pending = $this->pending( $post_id );

		return null !== $pending && '' !== $pending['buildId'] && hash_equals( $pending['buildId'], $build_id );
	}

	/**
	 * Read the post's latest build report.
	 *
	 * @param int $post_id Post to read.
	 * @return array<string, mixed> Decoded report, or an empty array when there is none.
	 */
	public function report( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_BUILD_REPORT, true );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Overwrite the post's build report.
	 *
	 * @param int   $post_id Post to write.
	 * @param array $report  Report payload.
	 * @return void
	 */
	public function write_report( int $post_id, array $report ): void {
		// Slashed for the same reason as store(): update_post_meta() unslashes.
		update_post_meta( $post_id, self::META_BUILD_REPORT, wp_slash( wp_json_encode( $report ) ) );
	}

	/**
	 * Clear the post's pending tree. Leaves the build report alone - a
	 * terminal report is still meaningful after the tree it describes is
	 * gone.
	 *
	 * @param int $post_id Post to clear.
	 * @return void
	 */
	public function clear( int $post_id ): void {
		delete_post_meta( $post_id, self::META_PENDING_TREE );
	}

	/**
	 * Whether the post has changed since its pending build's base was
	 * captured.
	 *
	 * @param int $post_id Post to check.
	 * @return bool True when the post's current post_modified_gmt no longer matches the stored base.
	 */
	public function is_conflict( int $post_id ): bool {
		$pending = $this->pending( $post_id );

		if ( null === $pending ) {
			return false;
		}

		$current = get_post_field( 'post_modified_gmt', $post_id );

		return ( is_string( $current ) ? $current : '' ) !== $pending['base'];
	}

	/**
	 * Fingerprint a tree so a later report can be tied back to the tree it
	 * was assembled from.
	 *
	 * @param array $tree Block tree.
	 * @return string Hex-encoded SHA-256 hash, or '' if the tree could not be encoded.
	 */
	private function hash_tree( array $tree ): string {
		$json = wp_json_encode( $tree );

		return is_string( $json ) ? hash( 'sha256', $json ) : '';
	}
}
