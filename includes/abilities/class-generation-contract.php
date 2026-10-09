<?php
/**
 * Installed generation metadata and explicitly supported styling targets.
 *
 * @package DesignSetGo
 */

namespace DesignSetGo\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared, versioned discovery for content generators. */
class Generation_Contract {
	/**
	 * Installed contract identity and breakpoints.
	 *
	 * @return array<string, mixed> Installed contract identity and breakpoints.
	 */
	public static function describe(): array {
		return array(
			'version'          => 1,
			'pluginVersion'    => DESIGNSETGO_VERSION,
			'wordpressVersion' => get_bloginfo( 'version' ),
			'breakpoints'      => array(
				'mobileMax' => 767,
				'tabletMax' => 1024,
			),
		);
	}

	/**
	 * Describe a registered block without executing its serializer or renderer.
	 *
	 * @param \WP_Block_Type $block_type Registered block.
	 * @return array<string, mixed> Serializer capability and declared targets.
	 */
	public static function for_block( \WP_Block_Type $block_type ): array {
		static $metadata = null;
		if ( null === $metadata ) {
			$metadata = json_decode( file_get_contents( __DIR__ . '/generation-targets.json' ), true );
		}
		$gap           = Block_Inserter::get_serialization_gap( $block_type->name );
		$serialization = array(
			'supported' => null === $gap,
			'mode'      => Block_Inserter::is_dynamic_block( $block_type->name ) ? 'dynamic' : 'static',
		);
		if ( null !== $gap ) {
			$serialization['reason'] = $gap;
		}
		$generation = array_merge( array( 'serialization' => $serialization ), $metadata[ $block_type->name ] ?? array() );
		if ( isset( $block_type->attributes['dsgoLayout'] ) ) {
			$contract = \DesignSetGo\Layout_Support::contract();
			$role     = $contract['blocks'][ $block_type->name ]['role'];
			$generation['layoutOverrides'] = array(
				'version'    => 1,
				'attribute'  => 'dsgoLayout',
				'devices'    => $contract['devices'],
				'inheritance' => 'desktop -> tablet -> mobile; omitted properties inherit',
				'properties' => array_filter( $contract['properties'], static fn( $property ) => \DesignSetGo\Layout_Support::applies( $role, $property['scope'] ) ),
			);
		}
		return $generation;
	}

	/**
	 * Public registered style choices, without exposing stylesheet contents.
	 *
	 * @param string $block_name Registered block name.
	 * @return array<int, array<string, mixed>> Registered style choices.
	 */
	public static function block_styles( string $block_name ): array {
		$styles = \WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( $block_name );
		return array_values( array_map( static fn( $style ) => array_intersect_key( $style, array_flip( array( 'name', 'label', 'is_default' ) ) ), $styles ) );
	}
}
