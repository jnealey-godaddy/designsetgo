<?php
/**
 * Form Builder: put a failed no-JS submission's answers back into the form.
 *
 * Works on rendered form HTML with the tag processor, for
 * Form_No_JS_Submit::render(). See Form_No_JS_Retry for where the answers
 * come from.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_No_JS_Refill.
 */
class Form_No_JS_Refill {

	/**
	 * Put a visitor's answers back into a rendered form.
	 *
	 * Text-like inputs get their value, textareas their content, selects and
	 * checkboxes their choice. Hidden, system and file inputs are untouched.
	 * A checkbox that isn't in the answers was left unticked, so its default
	 * tick is removed too. Values are escaped by the tag processor.
	 *
	 * @param string                $html   Rendered form HTML.
	 * @param array<string, string> $values Field name => value.
	 * @return string
	 */
	public static function refill( string $html, array $values ): string {
		if ( ! $values ) {
			return $html;
		}
		$processor = new \WP_HTML_Tag_Processor( $html );
		$select    = '';
		$textareas = array();
		while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag = $processor->get_tag();
			if ( $processor->is_tag_closer() ) {
				$select = 'SELECT' === $tag ? '' : $select;
				continue;
			}
			$name = (string) $processor->get_attribute( 'name' );
			if ( 'SELECT' === $tag ) {
				$select = $name;
			} elseif ( 'OPTION' === $tag && isset( $values[ $select ] ) ) {
				$processor->set_attribute( 'selected', (string) $processor->get_attribute( 'value' ) === $values[ $select ] );
			} elseif ( 'TEXTAREA' === $tag && isset( $values[ $name ] ) ) {
				// Filled after this pass: how set_modifiable_text() escapes
				// differs between the WordPress versions this plugin supports.
				$processor->set_attribute( 'data-dsgo-refill', (string) count( $textareas ) );
				$textareas[] = $values[ $name ];
			} elseif ( 'INPUT' === $tag && '' !== $name && null === $processor->get_attribute( 'data-dsgo-system' ) ) {
				$type = strtolower( (string) $processor->get_attribute( 'type' ) );
				if ( in_array( $type, array( 'checkbox', 'radio' ), true ) ) {
					$own = $processor->get_attribute( 'value' );
					$processor->set_attribute( 'checked', isset( $values[ $name ] ) && ( is_string( $own ) ? $own : 'on' ) === $values[ $name ] );
				} elseif ( isset( $values[ $name ] ) && ! in_array( $type, array( 'hidden', 'password', 'file', 'submit', 'button', 'image', 'reset' ), true ) ) {
					$processor->set_attribute( 'value', $values[ $name ] );
				}
			}
		}
		return self::fill_textareas( $processor->get_updated_html(), $textareas );
	}

	/**
	 * Write answers into the textareas refill() tagged.
	 *
	 * Escaping with esc_textarea() makes `</textarea>` and every `&` inert. A leading
	 * newline is doubled, because HTML drops the first one after the tag.
	 *
	 * @param string   $html      HTML with `data-dsgo-refill` markers.
	 * @param string[] $textareas Answers by marker index.
	 * @return string
	 */
	private static function fill_textareas( string $html, array $textareas ): string {
		if ( ! $textareas ) {
			return $html;
		}
		$filled = preg_replace_callback(
			'#(<textarea\b[^>]*?)\s+data-dsgo-refill="(\d+)"([^>]*>).*?(</textarea>)#is',
			static function ( $matches ) use ( $textareas ) {
				$value = $textareas[ (int) $matches[2] ] ?? '';
				$lead  = 1 === strspn( $value, "\n\r", 0, 1 ) ? "\n" : '';
				return $matches[1] . $matches[3] . $lead . esc_textarea( $value ) . $matches[4];
			},
			$html
		);
		// On a PCRE failure (backtrack limit on a huge form), show the form
		// without the textarea answers rather than no form at all.
		return null === $filled ? $html : $filled;
	}

	/**
	 * The visible label of a field in rendered form HTML, or ''.
	 *
	 * Finds the control named `$name`, then the `<label for>` pointing at its
	 * id, and returns that label's text without the required-field marker.
	 *
	 * @param string $html Rendered form HTML.
	 * @param string $name Field name.
	 * @return string Plain-text label.
	 */
	public static function field_label( string $html, string $name ): string {
		$processor = new \WP_HTML_Tag_Processor( $html );
		$id        = '';
		while ( '' !== $name && $processor->next_tag() ) {
			if ( self::is_answer_control( $processor, $name ) ) {
				$id = (string) $processor->get_attribute( 'id' );
				break;
			}
		}
		if ( '' === $id ) {
			return '';
		}
		$processor = new \WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag( array( 'tag_name' => 'LABEL' ) ) ) {
			if ( $id !== $processor->get_attribute( 'for' ) ) {
				continue;
			}
			$text  = '';
			$depth = 0;
			while ( $processor->next_token() ) {
				$token = $processor->get_token_type();
				if ( '#tag' === $token && 'LABEL' === $processor->get_tag() ) {
					break;
				}
				if ( '#tag' === $token && 'SPAN' === $processor->get_tag() ) {
					// The required marker (`<span class="…required">*</span>`) isn't part of the label.
					$depth += $processor->is_tag_closer() ? -1 : ( $processor->has_class( 'dsgo-form-field__required' ) ? 1 : 0 );
					$depth  = max( 0, $depth );
				} elseif ( '#text' === $token && 0 === $depth ) {
					$text .= $processor->get_modifiable_text();
				}
			}
			return trim( preg_replace( '/\s+/', ' ', $text ) ?? '' );
		}
		return '';
	}

	/**
	 * Point the field a failure concerns at the error message.
	 *
	 * Sets `aria-invalid` and adds the message box to `aria-describedby`, so
	 * a screen reader moving to the field hears why it was rejected.
	 *
	 * @param string $html        Rendered form HTML.
	 * @param string $name        Field name, or ''.
	 * @param string $message_id  Message box id, or ''.
	 * @return string
	 */
	public static function mark_invalid( string $html, string $name, string $message_id ): string {
		if ( '' === $name ) {
			return $html;
		}
		$processor = new \WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag() ) {
			if ( ! self::is_answer_control( $processor, $name ) ) {
				continue;
			}
			$processor->set_attribute( 'aria-invalid', 'true' );
			if ( '' !== $message_id ) {
				$described = trim( (string) $processor->get_attribute( 'aria-describedby' ) . ' ' . $message_id );
				$processor->set_attribute( 'aria-describedby', $described );
			}
			break;
		}
		return $processor->get_updated_html();
	}

	/**
	 * Whether the processor is on a control named `$name` that holds a
	 * visitor's answer: not a hidden or system input, which a link naming
	 * it should not be able to flag.
	 *
	 * @param \WP_HTML_Tag_Processor $processor Processor on an opening tag.
	 * @param string                 $name      Field name.
	 * @return bool
	 */
	private static function is_answer_control( \WP_HTML_Tag_Processor $processor, string $name ): bool {
		if ( ! in_array( $processor->get_tag(), array( 'INPUT', 'SELECT', 'TEXTAREA' ), true ) || $name !== $processor->get_attribute( 'name' ) ) {
			return false;
		}
		return null === $processor->get_attribute( 'data-dsgo-system' )
			&& 'hidden' !== strtolower( (string) $processor->get_attribute( 'type' ) );
	}
}
