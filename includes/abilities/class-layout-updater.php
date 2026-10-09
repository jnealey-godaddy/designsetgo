<?php
/** Precision synchronization for authored layout attribute patches.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Abilities;

use DesignSetGo\Extension_Attributes;
use DesignSetGo\Layout_Support;
use DesignSetGo\Abilities\Serializers\Grid_Serializer;

defined( 'ABSPATH' ) || exit;

/** Keep new layout comment attributes and their own saved wrappers in sync. */
class Layout_Updater {

	/** Raw values must reach target-aware validation before generic sanitizing. */
	public const ATTRIBUTES = array( 'dsgoLayout', 'columnTemplate', 'tabletColumnTemplate', 'mobileColumnTemplate' );

	/**
	 * Validate a targeted patch and synchronize only the owning block markup.
	 *
	 * @param array $block Parsed block, including sourced child HTML.
	 * @param array $patch Attribute patch, normalized by reference.
	 * @return array|\WP_Error Updated block or validation error.
	 */
	public static function apply( array $block, array &$patch ) {
		$name   = $block['blockName'] ?? '';
		$fields = array_intersect_key( $patch, array_flip( self::ATTRIBUTES ) );
		$error  = self::validate_patch( $name, $patch );
		if ( null !== $error ) {
			return $error;
		}
		$block['attrs'] = array_merge( $block['attrs'] ?? array(), $patch );
		if ( empty( $fields ) ) {
			return $block;
		}
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( $name );
		if ( empty( $block['innerHTML'] ) && $type->is_dynamic() ) {
			return $block; // Dynamic wrappers are produced by their renderer.
		}
		$html = self::sync_html( $block['innerHTML'] ?? '', $name, $patch, $block['attrs'] );
		if ( is_wp_error( $html ) ) {
			return $html;
		}
		$block['innerHTML'] = $html;
		foreach ( $block['innerContent'] ?? array() as $index => $fragment ) {
			if ( ! is_string( $fragment ) ) {
				continue;
			}
			$probe = new \WP_HTML_Tag_Processor( $fragment );
			if ( ! $probe->next_tag() ) {
				continue;
			}
			$html = self::sync_html( $fragment, $name, $patch, $block['attrs'] );
			if ( is_wp_error( $html ) ) {
				return $html;
			}
			$block['innerContent'][ $index ] = $html;
			break; // Null child boundaries and every later fragment stay intact.
		}
		return $block;
	}

	/**
	 * Strictly validate the new fields before generic attribute sanitization.
	 *
	 * @param string $name Target block name.
	 * @param array  $patch Patch, canonicalized by reference.
	 * @return \WP_Error|null Validation error or null.
	 */
	public static function validate_patch( string $name, array &$patch ): ?\WP_Error {
		$fields = array_intersect_key( $patch, array_flip( self::ATTRIBUTES ) );
		if ( empty( $fields ) ) {
			return null;
		}
		$type   = \WP_Block_Type_Registry::get_instance()->get_registered( $name );
		foreach ( $fields as $key => $value ) {
			if ( ! $type || ! isset( $type->attributes[ $key ] ) ) {
				return self::invalid();
			}
			if ( 'dsgoLayout' === $key ) {
				if ( Extension_Attributes::is_block_excluded( $name ) ) {
					return self::invalid();
				}
				$layout = Layout_Support::sanitize( $name, $value );
				if ( is_wp_error( $layout ) ) {
					return self::invalid();
				}
				$patch[ $key ] = $layout;
			} else {
				// The existing column contract admits automatic repeats and named
				// lines; only declaration/markup escapes and url() are prohibited.
				if ( 'designsetgo/grid' !== $name || ! is_string( $value ) || preg_match( '/[;{}<>"\']|url\s*\(/i', $value ) ) {
					return self::invalid();
				}
				$patch[ $key ] = trim( $value );
			}
		}
		return null;
	}

	/**
	 * Screen only new layout fields in requested insertion trees.
	 *
	 * @param array $definitions New block definitions.
	 * @return \WP_Error|null Validation error or null.
	 */
	public static function validate_tree( array $definitions ): ?\WP_Error {
		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}
			$patch = (array) ( $definition['attributes'] ?? array() );
			$error = self::validate_patch( Block_Inserter::read_definition_name( $definition ), $patch ) ?? self::validate_tree( Block_Inserter::read_nested_inner_blocks( $definition ) );
			if ( null !== $error ) {
				return $error;
			}
		}
		return null;
	}

	/**
	 * Detect layout fields on legacy builders that cannot save their wrappers.
	 *
	 * @param array $definitions Requested child definitions.
	 * @return bool Whether any new layout field is authored.
	 */
	public static function has_layout_fields( array $definitions ): bool {
		foreach ( $definitions as $definition ) {
			if ( is_array( $definition ) && ( array_intersect_key( (array) ( $definition['attributes'] ?? array() ), array_flip( self::ATTRIBUTES ) ) || self::has_layout_fields( Block_Inserter::read_nested_inner_blocks( $definition ) ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Patch the root class and immediate Grid inner element; never descendants.
	 *
	 * @param string $html Owning block HTML or its opening fragment.
	 * @param string $name Block name.
	 * @param array  $patch Validated patch.
	 * @param array  $attributes Merged attributes.
	 * @return string|\WP_Error Synchronized HTML or invalid wrapper error.
	 */
	private static function sync_html( string $html, string $name, array $patch, array $attributes ) {
		$processor = new \WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag() ) {
			return self::invalid();
		}
		if ( array_key_exists( 'dsgoLayout', $patch ) ) {
			foreach ( preg_split( '/\s+/', (string) $processor->get_attribute( 'class' ) ) as $class ) {
				if ( preg_match( '/^dsgo-layout-[a-z0-9]+$/', $class ) ) {
					$processor->remove_class( $class );
				}
			}
			$class = Layout_Support::class_name( $name, $attributes['dsgoLayout'] );
			if ( '' !== $class ) {
				$processor->add_class( $class );
			}
		}
		$templates = array_intersect_key( $patch, array_flip( array_slice( self::ATTRIBUTES, 1 ) ) );
		if ( empty( $templates ) ) {
			return $processor->get_updated_html();
		}
		if ( ! $processor->has_class( 'wp-block-designsetgo-grid' ) || ! $processor->next_tag( array( 'tag_closers' => 'visit' ) ) || $processor->is_tag_closer() || ! $processor->has_class( 'dsgo-grid__inner' ) ) {
			return self::invalid();
		}
		$style = (string) $processor->get_attribute( 'style' );
		foreach ( $templates as $key => $value ) {
			if ( 'columnTemplate' === $key ) {
				$wrapper = Grid_Serializer::wrapper( $name, $attributes );
				$source  = new \WP_HTML_Tag_Processor( $wrapper['opening'] );
				$source->next_tag( array( 'class_name' => 'dsgo-grid__inner' ) );
				preg_match( '/(?:^|;)grid-template-columns:([^;]*)/', (string) $source->get_attribute( 'style' ), $match );
				$property = 'grid-template-columns';
				$value    = $match[1];
			} else {
				$property = 'tabletColumnTemplate' === $key ? '--dsgo-grid-columns-tablet' : '--dsgo-grid-columns-mobile';
			}
			$style = self::set_declaration( $style, $property, $value );
		}
		if ( '' === $style ) {
			$processor->remove_attribute( 'style' );
		} else {
			$processor->set_attribute( 'style', $style );
		}
		return $processor->get_updated_html();
	}

	/**
	 * Preserve unrelated declarations, including semicolons inside quoted values.
	 *
	 * @param string $style Existing inline CSS.
	 * @param string $property Owned declaration name.
	 * @param string $value Replacement; empty means remove.
	 * @return string Updated inline CSS.
	 */
	private static function set_declaration( string $style, string $property, string $value ): string {
		$parts = array();
		$start = 0;
		$depth = 0;
		$quote = '';
		$comment = false;
		$length = strlen( $style );
		for ( $i = 0; $i <= $length; ++$i ) {
			$char = $i < $length ? $style[ $i ] : ';';
			if ( $comment ) {
				if ( '*' === $char && '/' === ( $style[ $i + 1 ] ?? '' ) ) {
					$comment = false;
					++$i;
				}
				continue;
			}
			if ( '' !== $quote ) {
				if ( '\\' === $char ) {
					++$i;
				} elseif ( $quote === $char ) {
					$quote = '';
				}
				continue;
			}
			if ( '/' === $char && '*' === ( $style[ $i + 1 ] ?? '' ) ) {
				$comment = true;
				++$i;
			} elseif ( '"' === $char || "'" === $char ) {
				$quote = $char;
			} elseif ( '(' === $char ) {
				++$depth;
			} elseif ( ')' === $char ) {
				--$depth;
			} elseif ( ';' === $char && 0 === $depth ) {
				$part = substr( $style, $start, $i - $start );
				$trivia = '(?:\s|/\*.*?\*/)*';
				$flags  = 0 === strpos( $property, '--' ) ? 's' : 'is';
				if ( preg_match( '~^(' . $trivia . ')' . preg_quote( $property, '~' ) . '(' . $trivia . '):~' . $flags, $part, $owned ) ) {
					// Comments are CSS whitespace. Retain authored comment trivia
					// while removing the owned key/value, including when clearing.
					$part = $owned[1] . $owned[2];
					$part = '' === trim( $part ) ? '' : $part;
				}
				if ( '' !== $part ) {
					$parts[] = $part;
				}
				$start = $i + 1;
			}
		}
		if ( '' !== $value ) {
			$parts[] = $property . ':' . $value;
		}
		return implode( ';', $parts );
	}

	/**
	 * A consistent error makes every targeted walk abort before writing content.
	 *
	 * @return \WP_Error Validation failure.
	 */
	private static function invalid(): \WP_Error {
		return new \WP_Error( 'designsetgo_validation_failed', __( 'Layout updates require supported attributes, valid values and an owning block wrapper.', 'designsetgo' ), array( 'status' => 400 ) );
	}
}
