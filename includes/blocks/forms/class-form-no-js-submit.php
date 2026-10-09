<?php
/**
 * Form Builder submission without JavaScript.
 *
 * The saved form markup is `<form method="post">` with no action: view.js
 * points it at admin-post.php and adds the handler's fields when it runs. When
 * it doesn't (scripting off, the view script blocked or failed to load), the
 * browser posted to the page itself, nothing handled it, and the visitor's
 * answers were lost.
 *
 * This fills in at render time what view.js would have: the action URL, the
 * admin-post `action`, a nonce and a timestamp. It also prints the result of
 * the admin-post redirect into the form's message box, which only view.js
 * did before. Rendering it server-side leaves save(), its deprecations and
 * the PHP serializer mirror untouched, and fixes every form already saved.
 *
 * @package DesignSetGo
 * @since 2.10.0
 */

namespace DesignSetGo\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Form_No_JS_Submit.
 */
class Form_No_JS_Submit {

	/**
	 * Admin-post action, and the nonce action it verifies.
	 */
	const ACTION = 'designsetgo_form_submit';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'render_block_designsetgo/form-builder', array( $this, 'render' ), 10, 1 );
		add_action( 'template_redirect', array( $this, 'prevent_caching_status_page' ) );
	}

	/**
	 * Fragment id of a form's server-rendered message.
	 *
	 * The admin-post handler appends it to the URL it redirects back to, so a
	 * visitor without JavaScript lands on the message rather than the top of
	 * a long page.
	 *
	 * @param string $form_id Form ID.
	 * @return string Element id, or '' when the form ID has no usable characters.
	 */
	public static function message_anchor( $form_id ) {
		$slug = sanitize_html_class( (string) $form_id );

		return '' === $slug ? '' : 'dsgo-form-message-' . $slug;
	}

	/**
	 * Make a rendered form submit to admin-post.php without JavaScript.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @return string
	 */
	public function render( $block_content ) {
		if ( ! is_string( $block_content ) || false === strpos( $block_content, 'dsgo-form' ) ) {
			return $block_content;
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( ! $processor->next_tag( array( 'class_name' => 'dsgo-form-builder' ) ) ) {
			return $block_content;
		}

		// The same attributes view.js reads its messages from.
		$form_id   = self::attribute( $processor, 'data-form-id' );
		$success   = self::attribute( $processor, 'data-success-message' );
		$error     = self::attribute( $processor, 'data-error-message' );
		$turnstile = 'true' === self::attribute( $processor, 'data-dsgo-turnstile' );

		if ( ! $processor->next_tag( array( 'tag_name' => 'FORM' ) ) ) {
			return $block_content;
		}
		if ( '' === self::attribute( $processor, 'action' ) ) {
			$processor->set_attribute( 'action', admin_url( 'admin-post.php' ) );
		}

		// Never add a field the markup already carries (a second pass of this
		// filter, or a field someone else injected): duplicate names post twice.
		$present = array();
		while ( $processor->next_tag( array( 'tag_name' => 'INPUT' ) ) ) {
			$present[ self::attribute( $processor, 'name' ) ] = true;
		}

		$html   = $processor->get_updated_html();
		$hidden = '';
		foreach ( $this->hidden_fields() as $name => $value ) {
			if ( ! isset( $present[ $name ] ) ) {
				$hidden .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"/>';
			}
		}

		$status = $this->redirect_status( $form_id );
		if ( '' !== $status ) {
			$message = 'success' === $status
				? ( '' !== $success ? $success : __( 'Form submitted successfully!', 'designsetgo' ) )
				: ( '' !== $error ? $error : __( 'An error occurred. Please try again.', 'designsetgo' ) );

			list( $html, $unplaced ) = $this->print_message( $html, $status, $message, $form_id );
			$hidden                 .= $unplaced;
		}

		$close = strripos( $html, '</form>' );
		if ( '' !== $hidden && false !== $close ) {
			$html = substr_replace( $html, $hidden, $close, 0 );
		}

		if ( $turnstile ) {
			$html = $this->add_turnstile_notice( $html );
		}

		return $html;
	}

	/**
	 * Keep a page showing a form result out of page caches.
	 *
	 * The message is printed for this request's query string. A cache that
	 * ignored the query string would otherwise serve one visitor's "Thank you"
	 * to everyone. Most page-cache plugins honour DONOTCACHEPAGE; proxies and
	 * CDNs honour the no-cache headers.
	 */
	public function prevent_caching_status_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides cacheability; no data is processed.
		if ( ! isset( $_GET['dsgo_form_status'] ) ) {
			return;
		}

		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Shared page-cache convention, not ours to prefix.
			define( 'DONOTCACHEPAGE', true );
		}
	}

	/**
	 * Fields the admin-post handler reads, which view.js otherwise adds.
	 *
	 * The timestamp is the render time in milliseconds, the format view.js
	 * sends, so the "too fast" check applies to a no-JS submission too. A page
	 * served from cache carries an older time, which only makes the minimum
	 * easier to meet; there is no maximum age. The nonce goes stale on a page
	 * cached for more than a day, which the handler tolerates for logged-out
	 * visitors only (see Form_Handler::handle_post_submission()).
	 *
	 * @return array<string, string> Field name => value.
	 */
	private function hidden_fields() {
		return array(
			'action'        => self::ACTION,
			'_wpnonce'      => wp_create_nonce( self::ACTION ),
			'dsg_timestamp' => time() . '000',
		);
	}

	/**
	 * The admin-post result for this form, if this request is its redirect.
	 *
	 * @param string $form_id Form ID from the rendered markup.
	 * @return string 'success', 'error', or '' when the request isn't for this form.
	 */
	private function redirect_status( $form_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only: picks which fixed message to print.
		if ( '' === $form_id || ! isset( $_GET['dsgo_form_status'], $_GET['dsgo_form_id'] ) || ! is_string( $_GET['dsgo_form_status'] ) || ! is_string( $_GET['dsgo_form_id'] ) ) {
			return '';
		}

		$status    = sanitize_key( wp_unslash( $_GET['dsgo_form_status'] ) );
		$status_id = sanitize_text_field( wp_unslash( $_GET['dsgo_form_id'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( sanitize_text_field( $form_id ) !== $status_id || ! in_array( $status, array( 'success', 'error' ), true ) ) {
			return '';
		}

		return $status;
	}

	/**
	 * Print the result into the form's message box.
	 *
	 * The box save() writes is empty and hidden (`style="display:none"`), so it
	 * is replaced whole. Forms saved before it existed get one appended to the
	 * form instead. view.js recognises `data-dsgo-server-message` and replaces
	 * or hides it, so a page with scripting never shows the message twice.
	 *
	 * @param string $html    Rendered form HTML.
	 * @param string $status  'success' or 'error'.
	 * @param string $message Message text (unescaped).
	 * @param string $form_id Form ID.
	 * @return array{0: string, 1: string} The HTML, and a box still to append to the form ('' when placed).
	 */
	private function print_message( $html, $status, $message, $form_id ) {
		$anchor = self::message_anchor( $form_id );
		$box    = sprintf(
			'<div class="dsgo-form__message dsgo-form__message--%1$s"%2$s role="%3$s" aria-live="polite" aria-atomic="true" data-dsgo-server-message="%1$s">%4$s</div>',
			esc_attr( $status ),
			'' === $anchor ? '' : ' id="' . esc_attr( $anchor ) . '"',
			'error' === $status ? 'alert' : 'status',
			esc_html( $message )
		);

		$count = 0;
		$html  = (string) preg_replace_callback(
			'#<div class="dsgo-form__message"[^>]*>\s*</div>#',
			static function () use ( $box ) {
				return $box;
			},
			$html,
			1,
			$count
		);

		return array( $html, 0 === $count ? $box : '' );
	}

	/**
	 * Tell visitors without JavaScript that a Turnstile form can't be sent.
	 *
	 * The challenge is rendered by script, so without it the server rejects
	 * every submission. Saying so up front beats a "please try again" that
	 * can never succeed.
	 *
	 * @param string $html Rendered form HTML.
	 * @return string
	 */
	private function add_turnstile_notice( $html ) {
		$container = 'data-dsgo-turnstile-container="true"></div>';
		$at        = strpos( $html, $container );
		if ( false === $at ) {
			return $html;
		}

		$notice = '<noscript><p class="dsgo-form__noscript">'
			. esc_html__( 'This form needs JavaScript to check that you are not a bot. Please turn on JavaScript to send it.', 'designsetgo' )
			. '</p></noscript>';

		return substr_replace( $html, $notice, $at + strlen( $container ), 0 );
	}

	/**
	 * A string attribute of the current tag ('' when absent or boolean).
	 *
	 * @param \WP_HTML_Tag_Processor $processor Processor on the tag.
	 * @param string                 $name      Attribute name.
	 * @return string
	 */
	private static function attribute( \WP_HTML_Tag_Processor $processor, $name ) {
		$value = $processor->get_attribute( $name );

		return is_string( $value ) ? $value : '';
	}
}
