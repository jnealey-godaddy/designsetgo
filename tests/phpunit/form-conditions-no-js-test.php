<?php
/**
 * Conditional field logic on the no-JS (admin-post) submission path.
 *
 * Without JavaScript every field renders and is posted, so the server is the
 * only thing applying the rules: a field whose rules aren't met must neither
 * block the submission when required nor have its value stored.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Tests;

use WP_UnitTestCase;
use DesignSetGo\Blocks\Form_Handler;

/**
 * No-JS conditional submission test case.
 */
class Test_Form_Conditions_No_JS extends WP_UnitTestCase {

	/**
	 * Handler.
	 *
	 * @var Form_Handler
	 */
	private $handler;

	public function set_up() {
		parent::set_up();
		$this->handler = new Form_Handler();
	}

	/**
	 * Publish a form: type (select, required), company (text, required, shown
	 * when type is business).
	 *
	 * @param string $form_id Form ID.
	 */
	private function publish_form( $form_id ) {
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => wp_slash(
					'<!-- wp:designsetgo/form-builder {"formId":"' . $form_id . '","enableEmail":false} --><div class="wp-block-designsetgo-form-builder">'
					. '<!-- wp:designsetgo/form-select-field {"fieldName":"type","required":true,"options":[{"label":"Business","value":"business"},{"label":"Personal","value":"personal"}]} /-->'
					. '<!-- wp:designsetgo/form-text-field {"fieldName":"company","required":true,"dsgoConditions":{"rules":[{"field":"type","op":"is","value":"business"}]}} /-->'
					. '</div><!-- /wp:designsetgo/form-builder -->'
				),
			)
		);
	}

	/**
	 * Stored field map of the form's only submission.
	 *
	 * @param string $form_id Form ID.
	 * @return array Stored fields.
	 */
	private function stored( $form_id ) {
		$submissions = get_posts(
			array(
				'post_type'   => 'dsgo_form_submission',
				'post_status' => 'any',
				'meta_key'    => '_dsg_form_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
				'meta_value'  => $form_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
			)
		);
		$this->assertCount( 1, $submissions );
		return (array) get_post_meta( $submissions[0]->ID, '_dsg_form_fields', true );
	}

	public function test_hidden_required_field_does_not_block_a_no_js_submission() {
		$this->publish_form( 'cond-nojs-1' );
		$location = $this->post_without_js(
			array(
				'dsg_form_id'     => 'cond-nojs-1',
				'dsg_field_types' => wp_json_encode(
					array(
						'type'    => 'select',
						'company' => 'text',
					)
				),
				'type'            => 'personal',
				// Every field posts without JS; an empty hidden field included.
				'company'         => '',
			)
		);

		$this->assertStringContainsString( 'dsgo_form_status=success', $location );
		$this->assertSame( array( 'type' ), array_keys( $this->stored( 'cond-nojs-1' ) ) );
	}

	public function test_value_in_a_hidden_field_is_dropped_from_a_no_js_submission() {
		$this->publish_form( 'cond-nojs-2' );
		$location = $this->post_without_js(
			array(
				'dsg_form_id' => 'cond-nojs-2',
				'type'        => 'personal',
				'company'     => 'Smuggled Inc',
			)
		);

		$this->assertStringContainsString( 'dsgo_form_status=success', $location );
		$this->assertSame( array( 'type' ), array_keys( $this->stored( 'cond-nojs-2' ) ) );
	}

	public function test_visible_required_field_is_enforced_on_a_no_js_submission() {
		$this->publish_form( 'cond-nojs-3' );
		$location = $this->post_without_js(
			array(
				'dsg_form_id' => 'cond-nojs-3',
				'type'        => 'business',
				'company'     => '',
			)
		);

		$this->assertStringContainsString( 'dsgo_form_status=error', $location );
	}

	/**
	 * Run the admin-post handler and return where it redirects.
	 *
	 * The handler ends in wp_safe_redirect() + exit, so the redirect filter
	 * throws to hand control back before the exit (as in
	 * Test_Form_Submission_Contract).
	 *
	 * @param array $post Unslashed POST fields besides the nonce.
	 * @return string Redirect location.
	 */
	private function post_without_js( array $post ) {
		$saved_post    = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$saved_referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$_POST                   = wp_slash( array_merge( array( '_wpnonce' => wp_create_nonce( 'designsetgo_form_submit' ) ), $post ) );
		$_SERVER['HTTP_REFERER'] = home_url( '/contact/' );

		$throw = static function ( $location ) {
			throw new \RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test-only control flow; never output.
		};
		add_filter( 'wp_redirect', $throw );

		$location = '';
		try {
			$this->handler->handle_post_submission();
		} catch ( \RuntimeException $redirect ) {
			$location = $redirect->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $throw );
			$_POST = $saved_post;
			if ( null === $saved_referer ) {
				unset( $_SERVER['HTTP_REFERER'] );
			} else {
				$_SERVER['HTTP_REFERER'] = $saved_referer;
			}
		}

		return $location;
	}
}
