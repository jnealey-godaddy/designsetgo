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

	const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

	const NUMBER_PATTERN = '[+-]?(?:(?:\\d+\\.\\d*|\\.\\d+|\\d+)(?:[eE][+-]?\\d+)?)';

	const PATH_ARGUMENT_COUNTS = array(
		'A' => 7,
		'C' => 6,
		'H' => 1,
		'L' => 2,
		'M' => 2,
		'Q' => 4,
		'S' => 4,
		'T' => 2,
		'V' => 1,
		'Z' => 0,
	);

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
		if (
			! $loaded ||
			! $document_element ||
			'svg' !== strtolower( $root_name ) ||
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMElement exposes this native property.
			self::SVG_NAMESPACE !== $document_element->namespaceURI
		) {
			return null;
		}

		$root = $document_element;
		foreach ( $root->getElementsByTagName( '*' ) as $element ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMElement exposes this native property.
			$element_name = $element->localName;
			if ( in_array( strtolower( $element_name ), array( 'script', 'foreignobject' ), true ) ) {
				return null;
			}
		}

		$view_box = self::normalise_view_box( $root->getAttribute( 'viewBox' ) );
		if ( ! $view_box ) {
			return null;
		}
		foreach ( $root->getElementsByTagNameNS( self::SVG_NAMESPACE, 'path' ) as $path ) {
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
			! self::is_safe_number( $parts[0] ) ||
			! self::is_safe_number( $parts[1] ) ||
			! self::is_safe_number( $parts[2] ) ||
			! self::is_safe_number( $parts[3] ) ||
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
		if ( '' === $path || self::MAX_LENGTH < strlen( $path ) ) {
			return false;
		}

		$matches = array();
		if ( ! preg_match_all( '/[AaCcHhLlMmQqSsTtVvZz]|' . self::NUMBER_PATTERN . '/', $path, $matches, PREG_OFFSET_CAPTURE ) ) {
			return false;
		}

		$tokens = array();
		$cursor = 0;
		foreach ( $matches[0] as $match ) {
			$token  = $match[0];
			$offset = $match[1];
			if ( ! self::is_legal_path_separator( substr( $path, $cursor, $offset - $cursor ), end( $tokens ), $token ) ) {
				return false;
			}
			$tokens[] = $token;
			$cursor   = $offset + strlen( $token );
		}

		if ( ! $tokens || ! preg_match( '/^\s*$/', substr( $path, $cursor ) ) ) {
			return false;
		}

		$command              = null;
		$argument_index       = 0;
		$has_move             = false;
		$has_drawable_segment = false;
		$ends_with_close      = false;

		foreach ( $tokens as $token ) {
			if ( self::is_path_command( $token ) ) {
				if ( $command && ! self::is_complete_argument_run( $command, $argument_index ) ) {
					return false;
				}

				$command        = strtoupper( $token );
				$argument_index = 0;
				if ( 'Z' === $command ) {
					$command         = null;
					$ends_with_close = true;
					continue;
				}

				$ends_with_close = false;
				if ( ! $has_move && 'M' !== $command ) {
					return false;
				}
				if ( 'M' === $command ) {
					$has_move = true;
				}
				continue;
			}

			if ( ! $command || ! self::is_safe_number( $token ) ) {
				return false;
			}

			if (
				'A' === $command &&
				( 3 === $argument_index % self::PATH_ARGUMENT_COUNTS['A'] || 4 === $argument_index % self::PATH_ARGUMENT_COUNTS['A'] ) &&
				'0' !== $token &&
				'1' !== $token
			) {
				return false;
			}

			if ( 'M' !== $command || $argument_index >= self::PATH_ARGUMENT_COUNTS['M'] ) {
				$has_drawable_segment = true;
			}
			++$argument_index;
			$ends_with_close = false;
		}

		return $has_move &&
			$has_drawable_segment &&
			( $ends_with_close || ( $command && self::is_complete_argument_run( $command, $argument_index ) ) );
	}

	/**
	 * Determines whether a command's arguments form one or more complete runs.
	 *
	 * @param string $command        Uppercase path command.
	 * @param int    $argument_index Number of arguments consumed for the command.
	 * @return bool Whether the argument count is a positive multiple of the command's arity.
	 */
	private static function is_complete_argument_run( $command, $argument_index ) {
		$count = isset( self::PATH_ARGUMENT_COUNTS[ $command ] ) ? self::PATH_ARGUMENT_COUNTS[ $command ] : 0;
		if ( $count < 1 ) {
			return false;
		}

		return $argument_index > 0 && 0 === $argument_index % $count;
	}

	/**
	 * Determines whether a token is a finite SVG number accepted by the editor.
	 *
	 * @param string $value Candidate numeric token.
	 * @return bool Whether the token is a finite SVG number.
	 */
	private static function is_safe_number( $value ) {
		return 1 === preg_match( '/^' . self::NUMBER_PATTERN . '$/', $value ) && is_finite( (float) $value );
	}

	/**
	 * Determines whether a token is an allowed SVG path command.
	 *
	 * @param string $token Candidate command token.
	 * @return bool Whether the token is a supported path command.
	 */
	private static function is_path_command( $token ) {
		return 1 === preg_match( '/^[AaCcHhLlMmQqSsTtVvZz]$/', $token );
	}

	/**
	 * Matches the editor's separator rules between path data tokens.
	 *
	 * @param string       $separator Text between two path tokens.
	 * @param string|false $previous Previous token, if any.
	 * @param string       $next Next token.
	 * @return bool Whether the separator is allowed.
	 */
	private static function is_legal_path_separator( $separator, $previous, $next ) {
		if ( '' === $separator || preg_match( '/^\s+$/', $separator ) ) {
			return true;
		}

		// Compare against '' rather than relying on truthiness: the token '0' is
		// falsy in PHP, which would reject ordinary path data such as 'M0,0'.
		return 1 === preg_match( '/^\s*,\s*$/', $separator ) &&
			is_string( $previous ) && '' !== $previous &&
			is_string( $next ) && '' !== $next &&
			! self::is_path_command( $previous ) &&
			! self::is_path_command( $next );
	}
}
