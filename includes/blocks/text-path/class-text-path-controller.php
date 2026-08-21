<?php
/**
 * Safe Text Path SVG extraction REST controller.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Blocks\Text_Path;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts a normalised SVG path for the Text Path block.
 */
class Controller {

	const MAX_LENGTH = 12288;

	/**
	 * Registers the safe SVG extraction route.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'designsetgo/v1',
			'/text-path/extract',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'extract' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
				'args'                => array(
					'svg' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}

	/**
	 * Limits extraction to users permitted to upload media.
	 *
	 * @param \WP_REST_Request $request REST request context.
	 * @return true|\WP_Error Whether the request is permitted.
	 */
	public static function permissions_check( \WP_REST_Request $request ) {
		unset( $request );

		if ( current_user_can( 'upload_files' ) ) {
			return true;
		}

		return new \WP_Error( 'dsgo_text_path_forbidden', __( 'You do not have permission to extract SVG paths.', 'designsetgo' ), array( 'status' => 403 ) );
	}

	/**
	 * Extracts the first valid path from an SVG payload.
	 *
	 * @param \WP_REST_Request $request REST request containing SVG markup.
	 * @return \WP_REST_Response|\WP_Error Extraction result.
	 */
	public static function extract( \WP_REST_Request $request ) {
		$data = self::parse_svg_path( $request->get_param( 'svg' ) );
		if ( null === $data ) {
			return new \WP_Error( 'dsgo_text_path_invalid_svg', __( 'The SVG does not contain a safe path.', 'designsetgo' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Parses a safe SVG document into a normalised path payload.
	 *
	 * @param mixed $svg SVG markup to validate.
	 * @return array{viewBox: string, d: string}|null Safe path data, if found.
	 */
	public static function parse_svg_path( $svg ) {
		if (
			! is_string( $svg ) ||
			'' === $svg ||
			strlen( $svg ) > self::MAX_LENGTH ||
			preg_match( '/<!\s*(?:doctype|entity)\b/i', $svg )
		) {
			return null;
		}

		$previous = libxml_use_internal_errors( true );
		$document = new \DOMDocument();
		$loaded   = $document->loadXML( $svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument exposes this native property.
		$document_element = $document->documentElement;
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMElement exposes this native property.
		$root_name = $document_element ? $document_element->localName : '';
		if ( ! $loaded || ! $document_element || 'svg' !== strtolower( $root_name ) ) {
			return null;
		}

		$root = $document_element;
		foreach ( $root->getElementsByTagName( '*' ) as $element ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMElement exposes this native property.
			$element_name = $element->localName;
			if ( 'path' !== strtolower( $element_name ) ) {
				return null;
			}
		}

		$view_box = self::normalise_view_box( $root->getAttribute( 'viewBox' ) );
		if ( ! $view_box ) {
			return null;
		}
		foreach ( $root->getElementsByTagName( 'path' ) as $path ) {
			$d = trim( $path->getAttribute( 'd' ) );
			if ( self::is_safe_path( $d ) ) {
				return array(
					'viewBox' => $view_box,
					'd'       => $d,
				);
			}
		}

		return null;
	}

	/**
	 * Normalises a positive four-value SVG viewBox.
	 *
	 * @param mixed $view_box Candidate SVG viewBox.
	 * @return string|null Normalised viewBox or null when invalid.
	 */
	private static function normalise_view_box( $view_box ) {
		$parts = preg_split( '/[\s,]+/', trim( (string) $view_box ) );
		if (
			4 !== count( $parts ) ||
			! is_numeric( $parts[0] ) ||
			! is_numeric( $parts[1] ) ||
			! is_numeric( $parts[2] ) ||
			! is_numeric( $parts[3] ) ||
			(float) $parts[2] <= 0 ||
			(float) $parts[3] <= 0
		) {
			return null;
		}
		return implode( ' ', $parts );
	}

	/**
	 * Determines whether path data is within the block's conservative allowlist.
	 *
	 * @param string $path Candidate SVG path data.
	 * @return bool Whether the path data is safe to store.
	 */
	private static function is_safe_path( $path ) {
		return '' !== $path &&
			self::MAX_LENGTH >= strlen( $path ) &&
			preg_match( '/^[MmLlHhVvCcSsQqTtAaZz0-9eE+\-.,\s]+$/', $path ) &&
			preg_match( '/[Mm]/', $path );
	}
}
