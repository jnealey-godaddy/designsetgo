<?php
/**
 * Fill gaps in installed language packs from the bundled catalogs.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep installed translations first, including during locale switches.
 */
class Translations {
	/**
	 * Guard the nested core loader, not subsequent loads for other locales.
	 *
	 * @var bool
	 */
	private $loading = false;

	/**
	 * Guard the script lookup performed by the JSON fallback.
	 *
	 * @var bool
	 */
	private $loading_script = false;

	/** Register loaders before any translated plugin strings are requested. */
	public function __construct() {
		add_filter( 'pre_load_textdomain', array( $this, 'load_catalog_fallback' ), PHP_INT_MAX, 4 );
		add_filter( 'pre_load_script_translations', array( $this, 'load_missing_script_catalog' ), PHP_INT_MAX, 4 );
		add_filter( 'load_script_translations', array( $this, 'merge_script_catalog' ), 10, 4 );
	}

	/**
	 * Try the bundle only after core has exhausted its normal script locations.
	 *
	 * @param string|false|null $translations Existing short-circuit result.
	 * @param string|false      $file Candidate path, or false at the final lookup.
	 * @param string            $handle Registered script handle.
	 * @param string            $domain Text domain.
	 * @return string|false|null Translation JSON or the existing result.
	 */
	public function load_missing_script_catalog( $translations, $file, $handle, $domain ) {
		if ( null !== $translations || false !== $file || 'designsetgo' !== $domain || $this->loading_script ) {
			return $translations;
		}
		return $this->get_bundled_script_catalog( $handle );
	}

	/**
	 * Reuse core's handle/hash resolution without recursively merging catalogs.
	 *
	 * @param string $handle Registered script handle.
	 * @return string|false Bundled JSON, if available.
	 */
	private function get_bundled_script_catalog( $handle ) {
		$this->loading_script = true;
		try {
			return load_script_textdomain( $handle, 'designsetgo', DESIGNSETGO_PATH . 'languages' );
		} finally {
			$this->loading_script = false;
		}
	}

	/**
	 * Let core load the chosen pack, then append the bundled catalog.
	 *
	 * @param bool|null   $loaded Existing short-circuit result.
	 * @param string      $domain Text domain.
	 * @param string      $mofile Requested catalog path.
	 * @param string|null $locale Requested locale.
	 * @return bool|null Loading result, or null to leave core in control.
	 */
	public function load_catalog_fallback( $loaded, $domain, $mofile, $locale ) {
		if ( null !== $loaded || 'designsetgo' !== $domain || $this->loading ) {
			return $loaded;
		}

		$locale  = $locale ? $locale : determine_locale();
		$bundled = DESIGNSETGO_PATH . 'languages/designsetgo-' . basename( $locale ) . '.mo';
		if ( ! is_readable( $bundled ) || $bundled === $mofile ) {
			return null;
		}

		$this->loading = true;
		try {
			$loaded = load_textdomain( $domain, $mofile, $locale );
			if ( $loaded ) {
				// Core searches loaded files in order. Appending fills missing entries
				// without replacing pack translations or its registered directory.
				// Keeping that directory matters for editor JSON and locale reloads.
				\WP_Translation_Controller::get_instance()->load_file( $bundled, $domain, $locale );
			} else {
				$loaded = load_textdomain( $domain, $bundled, $locale );
			}
			return $loaded;
		} finally {
			$this->loading = false;
		}
	}

	/**
	 * Merge missing editor/frontend script messages using core's file lookup.
	 *
	 * @param string $translations Selected JSON catalog.
	 * @param string $file Selected file path.
	 * @param string $handle Registered script handle.
	 * @param string $domain Text domain.
	 * @return string JSON with installed messages taking precedence.
	 */
	public function merge_script_catalog( $translations, $file, $handle, $domain ) {
		if ( 'designsetgo' !== $domain || $this->loading_script || 0 === strpos( wp_normalize_path( $file ), wp_normalize_path( DESIGNSETGO_PATH . 'languages/' ) ) ) {
			return $translations;
		}

		$primary = json_decode( $translations, true );
		if ( ! is_array( $primary ) || ! isset( $primary['locale_data'] ) ) {
			return $translations;
		}

		$bundled  = $this->get_bundled_script_catalog( $handle );
		$fallback = $bundled ? json_decode( $bundled, true ) : null;
		if ( ! is_array( $fallback ) || ! isset( $fallback['locale_data'] ) ) {
			return $translations;
		}

		// WordPress accepts either the actual domain or the generic "messages".
		$primary_key  = isset( $primary['locale_data'][ $domain ] ) ? $domain : 'messages';
		$fallback_key = isset( $fallback['locale_data'][ $domain ] ) ? $domain : 'messages';
		if ( ! is_array( $primary['locale_data'][ $primary_key ] ?? null ) || ! is_array( $fallback['locale_data'][ $fallback_key ] ?? null ) ) {
			return $translations;
		}
		foreach ( $fallback['locale_data'][ $fallback_key ] as $source => $translation ) {
			$existing = $primary['locale_data'][ $primary_key ][ $source ] ?? null;
			$nonempty = is_array( $existing ) ? array_filter(
				$existing,
				static function ( $value ) {
					return '' !== $value && null !== $value;
				}
			) : $existing;
			if ( null === $existing || ( '' !== $source && is_array( $existing ) && ! $nonempty ) ) {
				$primary['locale_data'][ $primary_key ][ $source ] = $translation;
			}
		}
		return wp_json_encode( $primary );
	}
}
