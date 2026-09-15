<?php
/**
 * Installed language packs must not hide newer bundled translations.
 *
 * @package DesignSetGo
 */

// Test fixtures are temporary files, never deployed language packs.
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Deliberately exercises an unrelated domain.

/**
 * Exercises real PHP catalogs and registered block script catalogs.
 *
 * @group translation-fallback
 */
class Translation_Fallback_Test extends WP_UnitTestCase {
	/** Temporary language-pack directory.
	 *
	 * @var string
	 */
	private $directory;
	/** Original registry.
	 *
	 * @var WP_Textdomain_Registry
	 */
	private $registry;
	/** Site locale fixture.
	 *
	 * @var Closure
	 */
	private $locale_filter;
	/** Installed pack path fixture.
	 *
	 * @var Closure
	 */
	private $path_filter;
	/** Original locale switcher.
	 *
	 * @var WP_Locale_Switcher
	 */
	private $switcher;

	/** Start a fresh French request with an isolated pack directory. */
	public function set_up() {
		parent::set_up();
		set_current_screen( 'front' );
		wp_set_current_user( 0 );
		$this->registry = clone $GLOBALS['wp_textdomain_registry'];
		$this->directory = sys_get_temp_dir() . '/dsgo-translations-' . wp_generate_uuid4();
		wp_mkdir_p( $this->directory );
		$this->locale_filter = static function () {
			return 'fr_FR';
		};
		add_filter( 'locale', $this->locale_filter );
		// Model a request which started in French, not the test bootstrap's English.
		$this->switcher = $GLOBALS['wp_locale_switcher'];
		remove_filter( 'locale', array( $this->switcher, 'filter_locale' ) );
		remove_filter( 'determine_locale', array( $this->switcher, 'filter_locale' ) );
		$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
		$GLOBALS['wp_locale_switcher']->init();
		$this->path_filter = function ( $path, $domain, $locale ) {
			return 'designsetgo' === $domain && 'fr_FR' === $locale ? $this->directory . '/' : $path;
		};
		add_filter( 'lang_dir_for_domain', $this->path_filter, 10, 3 );
		unload_textdomain( 'designsetgo', true );
		WP_Translation_Controller::get_instance()->unload_textdomain( 'designsetgo' );
	}

	/** Restore WordPress state and remove temporary catalogs. */
	public function tear_down() {
		while ( is_locale_switched() ) {
			restore_previous_locale();
		}
		remove_filter( 'locale', $this->locale_filter );
		remove_filter( 'locale', array( $GLOBALS['wp_locale_switcher'], 'filter_locale' ) );
		remove_filter( 'determine_locale', array( $GLOBALS['wp_locale_switcher'], 'filter_locale' ) );
		$GLOBALS['wp_locale_switcher'] = $this->switcher;
		$this->switcher->init();
		remove_filter( 'lang_dir_for_domain', $this->path_filter );
		unload_textdomain( 'designsetgo', true );
		unload_textdomain( 'dsgo-unrelated-test', true );
		WP_Translation_Controller::get_instance()->unload_textdomain( 'designsetgo' );
		WP_Translation_Controller::get_instance()->unload_textdomain( 'dsgo-unrelated-test' );
		$GLOBALS['wp_textdomain_registry'] = $this->registry;
		WP_Translation_Controller::get_instance()->set_locale( determine_locale() );
		foreach ( glob( $this->directory . '/*' ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->directory );
		parent::tear_down();
	}

	/** Installed PHP catalog formats.
	 *
	 * @return array */
	public function catalog_formats() {
		return array(
			'MO' => array( 'mo' ),
			'PHP' => array( 'l10n.php' ),
		);
	}

	/**
	 * Write an intentionally incomplete, real language pack.
	 *
	 * @param string $format Catalog format.
	 * @param array  $messages Translated entries.
	 * @return string Catalog path.
	 */
	private function write_pack( $format, $messages = array( 'Settings' => 'Réglages du pack' ) ) {
		$path = $this->directory . '/designsetgo-fr_FR.' . $format;
		if ( 'mo' === $format ) {
			$mo = new MO();
			$mo->set_header( 'Language', 'fr_FR' );
			$mo->set_header( 'Plural-Forms', 'nplurals=2; plural=(n > 1);' );
			foreach ( $messages as $source => $translated ) {
				$mo->add_entry(
					new Translation_Entry(
						array(
							'singular' => $source,
							'translations' => array( $translated ),
						)
					)
				);
			}
			$this->assertTrue( $mo->export_to_file( $path ) );
		} else {
			file_put_contents(
				$path,
				'<?php return ' . var_export(
					array(
						'language' => 'fr_FR',
						'plural-forms' => 'nplurals=2; plural=(n > 1);',
						'messages' => $messages,
					),
					true
				) . ';'
			);
		}
		return $path;
	}

	/**
	 * Keep installed translations while supplying missing bundled entries.
	 *
	 * @dataProvider catalog_formats
	 * @param string $format Catalog format.
	 */
	public function test_missing_pack_entry_uses_bundle_without_replacing_pack( $format ) {
		$file = $this->write_pack( $format );
		$before = file_get_contents( $file );
		$this->assertSame( 'Réglages du pack', __( 'Settings', 'designsetgo' ) );
		$html = do_blocks( '<!-- wp:designsetgo/form-select-field {"fieldName":"topic"} /-->' );
		$this->assertStringContainsString( '<option value="">-- Sélectionnez une option --</option>', $html );
		$this->assertSame( 'Réglages du pack', __( 'Settings', 'designsetgo' ) );
		$this->assertSame( 'Soumissions de formulaire', _x( 'Form Submissions', 'admin menu', 'designsetgo' ) );
		remove_filter( 'lang_dir_for_domain', $this->path_filter );
		$registered_path = $GLOBALS['wp_textdomain_registry']->get( 'designsetgo', 'fr_FR' );
		add_filter( 'lang_dir_for_domain', $this->path_filter, 10, 3 );
		$this->assertSame( $this->directory . '/', $registered_path );
		$this->assertSame( $before, file_get_contents( $file ) );
	}

	/**
	 * Keep installed translations while supplying missing bundled entries.
	 *
	 * @dataProvider catalog_formats
	 * @param string $format Catalog format.
	 */
	public function test_existing_pack_placeholder_keeps_priority( $format ) {
		$this->write_pack( $format, array( '-- Select an option --' => 'Choix personnalisé du pack' ) );
		$this->assertSame( 'Choix personnalisé du pack', __( '-- Select an option --', 'designsetgo' ) );
	}

	/** A restored locale must retain both the pack and its fallback. */
	public function test_fallback_survives_locale_switch_and_restore() {
		$this->write_pack( 'mo' );
		$this->assertSame( '-- Sélectionnez une option --', __( '-- Select an option --', 'designsetgo' ) );
		$this->assertTrue( switch_to_locale( 'en_US' ) );
		$this->assertSame( '-- Select an option --', __( '-- Select an option --', 'designsetgo' ) );
		restore_previous_locale();
		$this->assertSame( '-- Sélectionnez une option --', __( '-- Select an option --', 'designsetgo' ) );
		$this->assertSame( 'Réglages du pack', __( 'Settings', 'designsetgo' ) );
	}

	/** Only DesignSetGo receives its bundled translations. */
	public function test_other_domains_do_not_get_designsetgo_translations() {
		$this->assertTrue( load_textdomain( 'dsgo-unrelated-test', $this->write_pack( 'mo' ), 'fr_FR' ) );
		$this->assertSame( '-- Select an option --', __( '-- Select an option --', 'dsgo-unrelated-test' ) );
	}

	/** Preserve WordPress PHP-before-MO preference. */
	public function test_php_pack_retains_priority_over_mo_pack() {
		$this->write_pack( 'mo', array( 'Settings' => 'Ancien MO' ) );
		$this->write_pack( 'l10n.php' );
		$this->assertSame( 'Réglages du pack', __( 'Settings', 'designsetgo' ) );
		$this->assertSame( '-- Sélectionnez une option --', __( '-- Select an option --', 'designsetgo' ) );
	}

	/** A removed or absent pack must not disable the bundled fallback. */
	public function test_missing_pack_file_still_loads_bundle() {
		$this->assertSame( '-- Sélectionnez une option --', __( '-- Select an option --', 'designsetgo' ) );
	}

	/** Locales without a bundled catalog retain core fallback behavior. */
	public function test_unknown_locale_keeps_english_fallback() {
		remove_filter( 'locale', $this->locale_filter );
		$this->locale_filter = static function () {
			return 'zz_ZZ';
		};
		add_filter( 'locale', $this->locale_filter );
		$this->assertSame( '-- Select an option --', __( '-- Select an option --', 'designsetgo' ) );
	}

	/** Script translation presence and supported domain shapes.
	 *
	 * @return array */
	public function script_catalog_cases() {
		return array(
			'missing' => array( 'messages', null, '-- Sélectionnez une option --' ),
			'empty' => array( 'messages', array( '' ), '-- Sélectionnez une option --' ),
			'custom' => array( 'designsetgo', array( 'Choix du pack' ), 'Choix du pack' ),
			'explicit English' => array( 'messages', array( '-- Select an option --' ), '-- Select an option --' ),
		);
	}

	/** A PHP language pack may exist without a matching editor JSON file. */
	public function test_missing_installed_editor_json_uses_bundle() {
		$this->write_pack( 'mo' );
		$json = load_script_textdomain( 'designsetgo-form-select-field-editor-script', 'designsetgo' );
		$this->assertNotFalse( $json );
		$messages = json_decode( $json, true )['locale_data']['messages'];
		$this->assertSame( array( '-- Sélectionnez une option --' ), $messages['-- Select an option --'] );
	}

	/** A damaged pack must not cause a fatal error in the fallback loader. */
	public function test_malformed_script_messages_are_left_to_wordpress() {
		$file = $this->directory . '/broken.json';
		$json = '{"locale_data":{"messages":"invalid"}}';
		file_put_contents( $file, $json );
		$this->assertSame( $json, load_script_translations( $file, 'designsetgo-form-select-field-editor-script', 'designsetgo' ) );
	}

	/**
	 * Editor catalogs obey the same installed-first fallback policy.
	 *
	 * @dataProvider script_catalog_cases
	 * @param string     $key JSON domain key.
	 * @param array|null $placeholder Existing entry, if any.
	 * @param string     $expected Expected placeholder.
	 */
	public function test_editor_json_fills_missing_entries_and_preserves_pack_values( $key, $placeholder, $expected ) {
		$this->write_pack( 'mo' );
		$name = 'designsetgo-fr_FR-' . md5( 'build/blocks/form-select-field/index.js' ) . '.json';
		$pack = array(
			'locale_data' => array(
				$key => array(
					'' => array(
						'domain' => 'messages',
						'lang' => 'fr_FR',
						'plural-forms' => 'nplurals=2; plural=(n > 1);',
					),
					'Settings' => array( 'Réglages du pack' ),
				),
			),
		);
		if ( null !== $placeholder ) {
			$pack['locale_data'][ $key ]['-- Select an option --'] = $placeholder;
		}
		file_put_contents( $this->directory . '/' . $name, wp_json_encode( $pack ) );
		$json = load_script_textdomain( 'designsetgo-form-select-field-editor-script', 'designsetgo' );
		$this->assertNotFalse( $json );
		$messages = json_decode( $json, true )['locale_data'][ $key ];
		$this->assertSame( array( $expected ), $messages['-- Select an option --'] ?? null );
		$this->assertSame( array( 'Réglages du pack' ), $messages['Settings'] );
	}
}
