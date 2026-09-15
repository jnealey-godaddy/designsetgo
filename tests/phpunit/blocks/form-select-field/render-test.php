<?php
/**
 * Select placeholders must survive WordPress schema preparation without
 * converting a missing value into an authored English default.
 *
 * @package DesignSetGo
 * @group form-select-field
 */

/**
 * Exercises the registered build through do_blocks(), not an included template.
 */
class DesignSetGo_Form_Select_Field_Render_Test extends WP_UnitTestCase {

	/**
	 * Set up a frontend request with the built block registered.
	 */
	public function set_up() {
		parent::set_up();
		set_current_screen( 'front' );
		wp_set_current_user( 0 );
		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( 'designsetgo/form-select-field' ),
			'Build the plugin before running these tests.'
		);
	}

	/**
	 * Avoid leaking translation state into other suites.
	 */
	public function tear_down() {
		unload_textdomain( 'designsetgo', true );
		wp_set_current_user( 0 );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Render serialized block content through normal WordPress defaults/rendering.
	 *
	 * @param array $attributes Authored attributes.
	 * @return string Rendered HTML.
	 */
	private function render_field( array $attributes = array() ) {
		$attributes = array_merge( array( 'fieldName' => 'topic' ), $attributes );
		return do_blocks( '<!-- wp:designsetgo/form-select-field ' . wp_json_encode( $attributes ) . ' /-->' );
	}

	/**
	 * Literal expectations also check that the shipped MO files are compiled.
	 *
	 * @return array Locale and translated placeholder pairs.
	 */
	public function locales() {
		return array(
			'German'     => array( 'de_DE', '-- Bitte wählen Sie eine Option --' ),
			'Spanish'    => array( 'es_ES', '-- Selecciona una opción --' ),
			'French'     => array( 'fr_FR', '-- Sélectionnez une option --' ),
			'Italian'    => array( 'it_IT', '-- Seleziona un’opzione --' ),
			'Japanese'   => array( 'ja', '-- 選択してください --' ),
			'Dutch'      => array( 'nl_NL', '-- Selecteer een optie --' ),
			'Portuguese' => array( 'pt_BR', '-- Selecione uma opção --' ),
			'Russian'    => array( 'ru_RU', '-- Выберите вариант --' ),
			'Chinese'    => array( 'zh_CN', '-- 请选择一个选项 --' ),
		);
	}

	/**
	 * Use each bundled catalog after WordPress prepares the block attributes.
	 *
	 * @dataProvider locales
	 * @param string $locale Locale code.
	 * @param string $expected Translated option text.
	 */
	public function test_omitted_placeholder_uses_compiled_catalog( $locale, $expected ) {
		unload_textdomain( 'designsetgo', true );
		$this->assertTrue( load_textdomain( 'designsetgo', DESIGNSETGO_PATH . 'languages/designsetgo-' . $locale . '.mo', $locale ) );
		$this->assertStringContainsString( '<option value="">' . $expected . '</option>', $this->render_field() );
	}

	/**
	 * Without a loaded translation, the gettext source string is the fallback.
	 */
	public function test_omitted_placeholder_falls_back_to_english() {
		unload_textdomain( 'designsetgo' );
		$this->assertStringContainsString( '<option value="">-- Select an option --</option>', $this->render_field() );
	}

	/**
	 * An explicit empty value must not be replaced by the gettext fallback.
	 */
	public function test_empty_placeholder_omits_the_placeholder_option() {
		$this->assertTrue( load_textdomain( 'designsetgo', DESIGNSETGO_PATH . 'languages/designsetgo-fr_FR.mo', 'fr_FR' ) );
		$html = $this->render_field( array( 'placeholder' => '' ) );
		$this->assertStringContainsString( '<select ', $html );
		$this->assertStringNotContainsString( '<option value="">', $html );
		$this->assertStringContainsString( '<option value="option-1">Option 1</option>', $html );
	}

	/**
	 * Authored text remains intact (HTML-escaped), even with a catalog loaded.
	 */
	public function test_custom_placeholder_is_preserved() {
		$this->assertTrue( load_textdomain( 'designsetgo', DESIGNSETGO_PATH . 'languages/designsetgo-fr_FR.mo', 'fr_FR' ) );
		$html = $this->render_field( array( 'placeholder' => 'Custom <choice> & café' ) );
		$this->assertStringContainsString( '<option value="">Custom &lt;choice&gt; &amp; café</option>', $html );
		$html = $this->render_field( array( 'placeholder' => '-- Select an option --' ) );
		$this->assertStringContainsString( '<option value="">-- Select an option --</option>', $html );
	}

	/**
	 * Bundled translations must be discoverable without a separate language pack.
	 */
	public function test_frontend_uses_site_locale_when_user_locale_differs() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		update_user_meta( $user_id, 'locale', 'de_DE' );
		wp_set_current_user( $user_id );
		$site_locale = static function () {
			return 'fr_FR';
		};
		add_filter( 'locale', $site_locale );
		unload_textdomain( 'designsetgo', true );
		try {
			$this->assertSame( 'de_DE', get_user_locale() );
			$this->assertSame( 'fr_FR', determine_locale() );
			$this->assertStringContainsString( '<option value="">-- Sélectionnez une option --</option>', $this->render_field() );
		} finally {
			remove_filter( 'locale', $site_locale );
		}
	}

	/**
	 * WordPress must find the JSON catalog for the actual registered editor script.
	 */
	public function test_editor_script_loads_bundled_json() {
		$locale = static function () {
			return 'fr_FR';
		};
		add_filter( 'determine_locale', $locale );
		try {
			$json = load_script_textdomain( 'designsetgo-form-select-field-editor-script', 'designsetgo' );
			$this->assertNotFalse( $json, 'Registered editor script cannot find its bundled JSON catalog.' );
			$data = json_decode( $json, true );
			$this->assertSame( array( '-- Sélectionnez une option --' ), $data['locale_data']['messages']['-- Select an option --'] );
		} finally {
			remove_filter( 'determine_locale', $locale );
		}
	}
}
