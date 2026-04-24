<?php
/**
 * Dynamic Tags — bootstrap.
 *
 * Single entry point required from class-plugin.php. Instantiates the
 * metadata registry, registers all source classes on `init` priority 6
 * (after the existing query bindings on priority 5 so the ACF / meta /
 * Pods / MetaBox / JetEngine sources — whose sub-key support Dynamic
 * Tags adds metadata for — are already registered), and registers the
 * REST controller on `rest_api_init`.
 *
 * @package DesignSetGo
 * @since   2.2.0
 */

namespace DesignSetGo\Blocks\DynamicTags;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the Dynamic Tags subsystem.
 */
class Bootstrap {

	/**
	 * REST controller instance.
	 *
	 * @var RestController|null
	 */
	protected $rest;

	/**
	 * Wires the init and rest_api_init hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_sources' ), 6 );

		$this->rest = new RestController( Registry::instance() );
		$this->rest->register_hooks();
	}

	/**
	 * Returns the REST controller instance (test/debug accessor).
	 *
	 * @return RestController|null
	 */
	public function get_rest_controller() {
		return $this->rest;
	}

	/**
	 * Registers the four source families and the custom-field metadata.
	 */
	public function register_sources() {
		$registry = Registry::instance();

		PostSources::register( $registry );
		SiteSources::register( $registry );
		ArchiveSources::register( $registry );
		UserSources::register( $registry );

		$this->register_custom_field_metadata( $registry );

		/**
		 * Fires once all built-in Dynamic Tag sources are registered.
		 *
		 * Third-party plugins should hook here to register additional
		 * sources via `$registry->register_source()` and (for bindings
		 * value resolution) `designsetgo_register_bindings_source()`.
		 *
		 * @since 2.2.0
		 *
		 * @param Registry $registry Metadata registry.
		 */
		do_action( 'designsetgo_dynamic_tags_registered', $registry );
	}

	/**
	 * Adds picker-side metadata for the pre-existing custom-field bindings
	 * (post-meta, ACF, Meta Box, Pods, JetEngine) that were registered by
	 * the Query Bindings subsystem. Value callbacks are already registered
	 * there — this just gives the picker discovery + label info.
	 *
	 * @param Registry $registry Metadata registry.
	 */
	private function register_custom_field_metadata( Registry $registry ) {
		$image_subkey_arg = array(
			'subkey' => array(
				'type' => 'string',
				'enum' => array( 'url', 'id', 'alt', 'width', 'height', 'title', 'caption' ),
			),
		);
		$key_arg = array(
			'key' => array(
				'type'     => 'string',
				'required' => true,
			),
		);

		$registry->register_source(
			'designsetgo/post-meta',
			array(
				'label'        => __( 'Post meta', 'designsetgo' ),
				'group'        => 'custom-fields',
				'returns'      => array( 'text', 'url', 'number', 'date' ),
				'args'         => array_merge( $key_arg, $image_subkey_arg ),
				'discovery_cb' => array( FieldDiscovery::class, 'post_meta' ),
			)
		);

		if ( function_exists( 'get_field' ) ) {
			$registry->register_source(
				'designsetgo/acf',
				array(
					'label'        => __( 'ACF field', 'designsetgo' ),
					'group'        => 'custom-fields',
					'returns'      => array( 'text', 'image', 'url', 'number', 'date' ),
					'args'         => array_merge( $key_arg, $image_subkey_arg ),
					'discovery_cb' => array( FieldDiscovery::class, 'acf' ),
				)
			);
		}

		if ( function_exists( 'rwmb_meta' ) ) {
			$registry->register_source(
				'designsetgo/metabox',
				array(
					'label'   => __( 'Meta Box field', 'designsetgo' ),
					'group'   => 'custom-fields',
					'returns' => array( 'text', 'image', 'url', 'number', 'date' ),
					'args'    => array_merge( $key_arg, $image_subkey_arg ),
				)
			);
		}

		if ( function_exists( 'pods_field' ) ) {
			$registry->register_source(
				'designsetgo/pods',
				array(
					'label'   => __( 'Pods field', 'designsetgo' ),
					'group'   => 'custom-fields',
					'returns' => array( 'text', 'image', 'url', 'number', 'date' ),
					'args'    => array_merge( $key_arg, $image_subkey_arg ),
				)
			);
		}

		if ( class_exists( 'Jet_Engine' ) && function_exists( 'jet_engine' ) ) {
			$registry->register_source(
				'designsetgo/jetengine',
				array(
					'label'   => __( 'JetEngine field', 'designsetgo' ),
					'group'   => 'custom-fields',
					'returns' => array( 'text', 'image', 'url', 'number', 'date' ),
					'args'    => array_merge( $key_arg, $image_subkey_arg ),
				)
			);
		}
	}
}
