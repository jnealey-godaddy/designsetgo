<?php
/**
 * Modal Block Hooks and Filters
 *
 * Provides PHP filters and actions for developers to customize modal behavior.
 *
 * @package DesignSetGo
 * @since 1.3.0
 */

namespace DesignSetGo\Blocks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modal Hooks Class
 */
class Modal_Hooks {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'render_block_designsetgo/modal', array( $this, 'filter_modal_output' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'modal_footer_scripts' ) );
	}

	/**
	 * Filter modal block output before rendering.
	 *
	 * Allows developers to modify modal HTML, attributes, and classes.
	 *
	 * @param string $block_content Block HTML content.
	 * @param array  $block Block data including attributes.
	 * @return string Modified block content.
	 */
	public function filter_modal_output( $block_content, $block ) {
		$attributes = $block['attrs'] ?? array();

		/**
		 * Filter modal attributes before rendering.
		 *
		 * @param array $attributes Modal block attributes.
		 * @param array $block Complete block data.
		 * @return array Modified attributes.
		 */
		$attributes = apply_filters( 'designsetgo/modal/attributes', $attributes, $block );

		// Sanitize attributes after filtering.
		$attributes = $this->sanitize_attributes( $attributes );

		/**
		 * Filter modal CSS classes.
		 *
		 * @param array $classes Array of CSS classes.
		 * @param array $attributes Modal block attributes.
		 * @param array $block Complete block data.
		 * @return array Modified classes.
		 */
		$classes = apply_filters(
			'designsetgo/modal/classes',
			array( 'dsgo-modal' ),
			$attributes,
			$block
		);

		// Sanitize classes after filtering.
		$classes = array_map( 'sanitize_html_class', array_filter( $classes ) );

		/**
		 * Filter modal data attributes.
		 *
		 * These attributes are read by the JavaScript modal controller.
		 *
		 * @param array $data_attrs Associative array of data attributes.
		 * @param array $attributes Modal block attributes.
		 * @param array $block Complete block data.
		 * @return array Modified data attributes.
		 */
		$data_attrs = apply_filters(
			'designsetgo/modal/data_attributes',
			$this->get_default_data_attributes( $attributes ),
			$attributes,
			$block
		);

		// Sanitize data attributes after filtering.
		$data_attrs = $this->sanitize_data_attributes( $data_attrs );

		/**
		 * Action fired before modal block renders.
		 *
		 * @param array $attributes Modal block attributes.
		 * @param array $block Complete block data.
		 */
		do_action( 'designsetgo/modal/before_render', $attributes, $block );

		/**
		 * Filter complete modal block content.
		 *
		 * This is the final filter before output. Use this for major structural changes.
		 *
		 * @param string $block_content Current block HTML.
		 * @param array  $attributes Modal block attributes.
		 * @param array  $block Complete block data.
		 * @param array  $classes Filtered CSS classes.
		 * @param array  $data_attrs Filtered data attributes.
		 * @return string Modified block content.
		 */
		$block_content = apply_filters(
			'designsetgo/modal/content',
			$block_content,
			$attributes,
			$block,
			$classes,
			$data_attrs
		);

		/**
		 * Action fired after modal block renders.
		 *
		 * @param string $block_content Rendered block HTML.
		 * @param array  $attributes Modal block attributes.
		 * @param array  $block Complete block data.
		 */
		do_action( 'designsetgo/modal/after_render', $block_content, $attributes, $block );

		return $block_content;
	}

	/**
	 * Get default data attributes from modal attributes.
	 *
	 * @param array $attributes Modal block attributes.
	 * @return array Data attributes array.
	 */
	private function get_default_data_attributes( $attributes ) {
		$data_attrs = array();

		// Map modal attributes to data attributes.
		$attr_map = array(
			'modalId'                 => 'modal-id',
			'animationType'           => 'animation-type',
			'animationDuration'       => 'animation-duration',
			'closeOnBackdrop'         => 'close-on-backdrop',
			'closeOnEsc'              => 'close-on-esc',
			'disableBodyScroll'       => 'disable-body-scroll',
			'allowHashTrigger'        => 'allow-hash-trigger',
			'updateUrlOnOpen'         => 'update-url-on-open',
			'autoTriggerType'         => 'auto-trigger-type',
			'autoTriggerDelay'        => 'auto-trigger-delay',
			'autoTriggerFrequency'    => 'auto-trigger-frequency',
			'cookieDuration'          => 'cookie-duration',
			'exitIntentSensitivity'   => 'exit-intent-sensitivity',
			'exitIntentMinTime'       => 'exit-intent-min-time',
			'exitIntentExcludeMobile' => 'exit-intent-exclude-mobile',
			'scrollDepth'             => 'scroll-depth',
			'scrollDirection'         => 'scroll-direction',
			'timeOnPage'              => 'time-on-page',
			'galleryGroupId'          => 'gallery-group-id',
			'galleryIndex'            => 'gallery-index',
			'showGalleryNavigation'   => 'show-gallery-navigation',
			'navigationStyle'         => 'navigation-style',
			'navigationPosition'      => 'navigation-position',
		);

		foreach ( $attr_map as $attr_key => $data_key ) {
			if ( isset( $attributes[ $attr_key ] ) ) {
				$value = $attributes[ $attr_key ];

				// Convert booleans to strings.
				if ( is_bool( $value ) ) {
					$value = $value ? 'true' : 'false';
				}

				$data_attrs[ $data_key ] = $value;
			}
		}

		return $data_attrs;
	}

	/**
	 * Sanitize modal attributes.
	 *
	 * @param array $attributes Attributes to sanitize.
	 * @return array Sanitized attributes.
	 */
	private function sanitize_attributes( $attributes ) {
		$sanitized = array();

		// Define expected attribute types and validation.
		$validators = array(
			'modalId'                 => 'sanitize_key',
			'width'                   => 'sanitize_text_field',
			'maxWidth'                => 'sanitize_text_field',
			'height'                  => 'sanitize_text_field',
			'maxHeight'               => 'sanitize_text_field',
			'animationType'           => array( $this, 'validate_enum' ),
			'animationDuration'       => 'absint',
			'overlayOpacity'          => 'absint',
			'overlayColor'            => 'sanitize_hex_color',
			'overlayBlur'             => 'absint',
			'closeOnBackdrop'         => 'rest_sanitize_boolean',
			'closeOnEsc'              => 'rest_sanitize_boolean',
			'showCloseButton'         => 'rest_sanitize_boolean',
			'closeButtonPosition'     => array( $this, 'validate_enum' ),
			'closeButtonSize'         => 'absint',
			'closeButtonIconColor'    => 'sanitize_text_field',
			'closeButtonBgColor'      => 'sanitize_text_field',
			'disableBodyScroll'       => 'rest_sanitize_boolean',
			'allowHashTrigger'        => 'rest_sanitize_boolean',
			'updateUrlOnOpen'         => 'rest_sanitize_boolean',
			'autoTriggerType'         => array( $this, 'validate_enum' ),
			'autoTriggerDelay'        => 'absint',
			'autoTriggerFrequency'    => array( $this, 'validate_enum' ),
			'cookieDuration'          => 'absint',
			'exitIntentSensitivity'   => array( $this, 'validate_enum' ),
			'exitIntentMinTime'       => 'absint',
			'exitIntentExcludeMobile' => 'rest_sanitize_boolean',
			'scrollDepth'             => 'absint',
			'scrollDirection'         => array( $this, 'validate_enum' ),
			'timeOnPage'              => 'absint',
			'galleryGroupId'          => 'sanitize_key',
			'galleryIndex'            => 'absint',
			'showGalleryNavigation'   => 'rest_sanitize_boolean',
			'navigationStyle'         => array( $this, 'validate_enum' ),
			'navigationPosition'      => array( $this, 'validate_enum' ),
		);

		foreach ( $attributes as $key => $value ) {
			if ( isset( $validators[ $key ] ) ) {
				$sanitized[ $key ] = call_user_func( $validators[ $key ], $value, $key );
			} else {
				// If no validator defined for attribute, sanitize as text.
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Validate enum values.
	 *
	 * @param mixed  $value Value to validate.
	 * @param string $key Attribute key.
	 * @return mixed Validated value or default.
	 */
	private function validate_enum( $value, $key ) {
		// Define valid enum values for each attribute.
		$enums = array(
			'animationType'         => array( 'fade', 'slide-up', 'slide-down', 'zoom', 'none' ),
			'closeButtonPosition'   => array( 'inside-top-right', 'inside-top-left', 'top-right', 'top-left' ),
			'autoTriggerType'       => array( 'none', 'pageLoad', 'exitIntent', 'scroll', 'time' ),
			'autoTriggerFrequency'  => array( 'always', 'session', 'once' ),
			'exitIntentSensitivity' => array( 'low', 'medium', 'high' ),
			'scrollDirection'       => array( 'down', 'both' ),
			'navigationStyle'       => array( 'arrows', 'chevrons', 'text' ),
			'navigationPosition'    => array( 'sides', 'bottom', 'top' ),
		);

		if ( isset( $enums[ $key ] ) && in_array( $value, $enums[ $key ], true ) ) {
			return $value;
		}

		// Return first valid value as default if invalid value provided.
		return isset( $enums[ $key ] ) ? $enums[ $key ][0] : sanitize_text_field( $value );
	}

	/**
	 * Sanitize data attributes.
	 *
	 * @param array $data_attrs Data attributes to sanitize.
	 * @return array Sanitized data attributes.
	 */
	private function sanitize_data_attributes( $data_attrs ) {
		$sanitized = array();

		foreach ( $data_attrs as $key => $value ) {
			// Sanitize key.
			$clean_key = sanitize_key( $key );

			// Sanitize value - escape for HTML attribute context.
			if ( is_numeric( $value ) ) {
				$clean_value = (string) $value;
			} elseif ( is_string( $value ) ) {
				$clean_value = esc_attr( $value );
			} else {
				// Convert other types to string and escape.
				$clean_value = esc_attr( (string) $value );
			}

			$sanitized[ $clean_key ] = $clean_value;
		}

		return $sanitized;
	}

	/**
	 * Add custom scripts to footer for modal integrations.
	 *
	 * Developers can use this action to inject custom JavaScript for modal events.
	 */
	public function modal_footer_scripts() {
		/**
		 * Action fired in footer for custom modal scripts.
		 *
		 * Use this to add custom JavaScript that interacts with the modal API.
		 *
		 * Example:
		 * ```php
		 * add_action('designsetgo/modal/footer_scripts', function() {
		 *     ?>
		 *     <script>
		 *     window.dsgoModal.on('modalOpen', function(data) {
		 *         console.log('Modal opened:', data.modalId);
		 *     });
		 *     </script>
		 *     <?php
		 * });
		 * ```
		 */
		do_action( 'designsetgo/modal/footer_scripts' );
	}
}
