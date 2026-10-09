<?php
/**
 * Compare HTML content without depending on attribute serialization order.
 *
 * @package DesignSetGo
 * @subpackage Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tokenize markup while preserving tags, attribute values, text and comments.
 *
 * WordPress sanitizers may reorder attributes between releases. Block comments
 * and all attribute values still participate in strict fixture comparisons.
 *
 * @param string $html HTML to compare.
 * @return array<int, array<string, mixed>> HTML tokens with sorted attributes.
 * @throws RuntimeException When markup ends with an incomplete token.
 */
function designsetgo_test_html_tokens( string $html ): array {
	$processor = new WP_HTML_Tag_Processor( $html );
	$tokens    = array();

	while ( $processor->next_token() ) {
		$token = array(
			'type' => $processor->get_token_type(),
			'text' => $processor->get_modifiable_text(),
		);
		if ( '#tag' === $token['type'] ) {
			$attributes = array();
			foreach ( $processor->get_attribute_names_with_prefix( '' ) ?? array() as $name ) {
				$attributes[ $name ] = $processor->get_attribute( $name );
			}
			ksort( $attributes );
			$token['tag']          = $processor->get_tag();
			$token['closing']      = $processor->is_tag_closer();
			$token['self_closing'] = $processor->has_self_closing_flag();
			$token['attributes']   = $attributes;
		}
		$tokens[] = $token;
	}

	if ( $processor->paused_at_incomplete_token() ) {
		throw new RuntimeException( 'Cannot compare an incomplete HTML token.' );
	}

	return $tokens;
}
