<?php
/**
 * DesignSetGo Form Block Handlers for HTML-to-Block Converter.
 *
 * Handles form-related HTML elements and maps them to DesignSetGo form blocks.
 *
 * @package DesignSetGo
 * @subpackage HTML_Converter
 * @since 2.1.0
 */

namespace DesignSetGo\HTML_Converter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DesignSetGo Form Handlers class.
 *
 * Handles form blocks: form, form fields, and nav (navigation).
 */
class Dsgo_Form_Handlers {

	/**
	 * Converter instance.
	 *
	 * @var Converter
	 */
	private Converter $converter;

	/**
	 * Attribute mapper instance.
	 *
	 * @var Attribute_Mapper
	 */
	private Attribute_Mapper $attribute_mapper;

	/**
	 * Constructor.
	 *
	 * @param Converter        $converter        Converter instance.
	 * @param Attribute_Mapper $attribute_mapper  Attribute mapper instance.
	 */
	public function __construct( Converter $converter, Attribute_Mapper $attribute_mapper ) {
		$this->converter        = $converter;
		$this->attribute_mapper = $attribute_mapper;
	}

	/**
	 * Register form handlers with the element handler registry.
	 *
	 * @param Element_Handler $registry Handler registry.
	 */
	public function register( Element_Handler $registry ): void {
		$registry->register_tag_handler( 'form', array( $this, 'handle_form' ) );
		$registry->register_tag_handler( 'input', array( $this, 'handle_form_field' ) );
		$registry->register_tag_handler( 'textarea', array( $this, 'handle_form_field' ) );
		$registry->register_tag_handler( 'select', array( $this, 'handle_form_field' ) );
		$registry->register_tag_handler( 'nav', array( $this, 'handle_nav' ) );
	}

	/**
	 * Handle form element -> designsetgo/form-builder.
	 *
	 * Save.js renders: <div class="dsgo-form-builder"><form class="dsgo-form" method="post" novalidate>
	 *   <div class="dsgo-form__fields">...inner blocks...</div>
	 *   <input type="hidden" name="dsg_form_id" value="..." />
	 * </form></div>
	 *
	 * @param \DOMElement $element   The form element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_form( \DOMElement $element, Converter $converter ): array {
		$attrs        = $this->attribute_mapper->map_attributes( $element, 'designsetgo/form-builder' );
		$inner_blocks = $converter->process_children( $element );

		$form_id         = wp_unique_id( 'form-' );
		$attrs['formId'] = $form_id;
		$open            = '<div class="wp-block-designsetgo-form-builder dsgo-form-builder" data-form-id="' . esc_attr( $form_id ) . '">';
		$open   .= '<form class="dsgo-form" method="post" novalidate><div class="dsgo-form__fields">';
		$close   = '</div><input type="hidden" name="dsg_form_id" value="' . esc_attr( $form_id ) . '" />';
		$close  .= '<div class="dsgo-form__message" role="status" aria-live="polite" aria-atomic="true" style="display: none;"></div>';
		$close  .= '</form></div>';

		return Dsgo_Handlers::build_container_block( 'designsetgo/form-builder', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Handle form field elements -> designsetgo/form-*-field.
	 *
	 * Maps input types to specific DesignSetGo form field blocks.
	 *
	 * @param \DOMElement $element   The input/textarea/select element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_form_field( \DOMElement $element, Converter $converter ): array {
		$tag_name   = strtolower( $element->tagName );
		$block_name = $this->get_form_field_block_name( $element, $tag_name );

		$attrs = array();

		// Extract field attributes.
		$name = $element->getAttribute( 'name' );
		if ( ! empty( $name ) ) {
			$attrs['fieldName'] = $name;
		}

		$placeholder = $element->getAttribute( 'placeholder' );
		if ( ! empty( $placeholder ) ) {
			$attrs['placeholder'] = $placeholder;
		}

		if ( $element->hasAttribute( 'required' ) ) {
			$attrs['required'] = true;
		}

		$label = $element->getAttribute( 'aria-label' );
		if ( ! empty( $label ) ) {
			$attrs['label'] = $label;
		}

		return array(
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Handle nav element -> designsetgo/breadcrumbs or core/group.
	 *
	 * @param \DOMElement $element   The nav element.
	 * @param Converter   $converter Converter instance.
	 * @return array<string, mixed> Block array.
	 */
	public function handle_nav( \DOMElement $element, Converter $converter ): array {
		$attrs = $this->attribute_mapper->map_attributes( $element, 'designsetgo/breadcrumbs' );

		$aria_label = strtolower( $element->getAttribute( 'aria-label' ) );
		$class      = strtolower( $element->getAttribute( 'class' ) );

		if ( strpos( $aria_label, 'breadcrumb' ) !== false || strpos( $class, 'breadcrumb' ) !== false ) {
			return array(
				'blockName'    => 'designsetgo/breadcrumbs',
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);
		}

		// Fallback to core/group with children.
		$inner_blocks = $converter->process_children( $element );
		$open         = "\n<div class=\"wp-block-group\">\n";
		$close        = "\n</div>\n";

		return Dsgo_Handlers::build_container_block( 'core/group', $attrs, $inner_blocks, $open, $close );
	}

	/**
	 * Determine the form field block name from an input element.
	 *
	 * @param \DOMElement $element  The form field element.
	 * @param string      $tag_name The tag name.
	 * @return string Block name.
	 */
	private function get_form_field_block_name( \DOMElement $element, string $tag_name ): string {
		if ( 'textarea' === $tag_name ) {
			return 'designsetgo/form-textarea-field';
		}

		if ( 'select' === $tag_name ) {
			return 'designsetgo/form-select-field';
		}

		// Map input types to specific field blocks.
		$raw_type = $element->getAttribute( 'type' );
		$type     = strtolower( $raw_type ? $raw_type : 'text' );
		$type_map = array(
			'text'     => 'designsetgo/form-text-field',
			'email'    => 'designsetgo/form-email-field',
			'tel'      => 'designsetgo/form-phone-field',
			'number'   => 'designsetgo/form-number-field',
			'url'      => 'designsetgo/form-url-field',
			'date'     => 'designsetgo/form-date-field',
			'time'     => 'designsetgo/form-time-field',
			'checkbox' => 'designsetgo/form-checkbox-field',
			'hidden'   => 'designsetgo/form-hidden-field',
			'password' => 'designsetgo/form-text-field',
			'search'   => 'designsetgo/form-text-field',
		);

		return $type_map[ $type ] ?? 'designsetgo/form-text-field';
	}
}
