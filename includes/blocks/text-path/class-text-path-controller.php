<?php
/**
 * Safe Text Path SVG extraction REST controller.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Blocks\Text_Path;

defined( 'ABSPATH' ) || exit;

class Controller {

	const MAX_LENGTH = 12288;

	public static function register_routes() {
		register_rest_route(
			'designsetgo/v1',
			'/text-path/extract',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'extract' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
				'args'                => array( 'svg' => array( 'required' => true, 'type' => 'string' ) ),
			)
		);
	}

	public static function permissions_check( \WP_REST_Request $request ) {
		if ( current_user_can( 'upload_files' ) ) {
			return true;
		}

		return new \WP_Error( 'dsgo_text_path_forbidden', __( 'You do not have permission to extract SVG paths.', 'designsetgo' ), array( 'status' => 403 ) );
	}

	public static function extract( \WP_REST_Request $request ) {
		$data = self::parse_svg_path( $request->get_param( 'svg' ) );
		if ( null === $data ) {
			return new \WP_Error( 'dsgo_text_path_invalid_svg', __( 'The SVG does not contain a safe path.', 'designsetgo' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( $data );
	}

	public static function parse_svg_path( $svg ) {
		if ( ! is_string( $svg ) || '' === $svg || strlen( $svg ) > self::MAX_LENGTH || preg_match( '/<!\s*(?:doctype|entity)\b/i', $svg ) ) {
			return null;
		}

		$previous = libxml_use_internal_errors( true );
		$document = new \DOMDocument();
		$loaded   = $document->loadXML( $svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded || ! $document->documentElement || 'svg' !== strtolower( $document->documentElement->localName ) ) {
			return null;
		}

		$root = $document->documentElement;
		foreach ( $root->getElementsByTagName( '*' ) as $element ) {
			if ( 'path' !== strtolower( $element->localName ) ) {
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
				return array( 'viewBox' => $view_box, 'd' => $d );
			}
		}

		return null;
	}

	private static function normalise_view_box( $view_box ) {
		$parts = preg_split( '/[\s,]+/', trim( (string) $view_box ) );
		if ( 4 !== count( $parts ) || ! is_numeric( $parts[0] ) || ! is_numeric( $parts[1] ) || ! is_numeric( $parts[2] ) || ! is_numeric( $parts[3] ) || (float) $parts[2] <= 0 || (float) $parts[3] <= 0 ) {
			return null;
		}
		return implode( ' ', $parts );
	}

	private static function is_safe_path( $path ) {
		return '' !== $path && 12288 >= strlen( $path ) && preg_match( '/^[MmLlHhVvCcSsQqTtAaZz0-9eE+\-.,\s]+$/', $path ) && preg_match( '/[Mm]/', $path );
	}
}
