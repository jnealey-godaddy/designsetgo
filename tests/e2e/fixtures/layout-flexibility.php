<?php
/** Create an editable page of eight native reference compositions.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

wp_set_current_user( 1 );
$designsetgo_data    = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/layout-reference-designs.json' ), true );
$designsetgo_content = '';
$designsetgo_ability = function_exists( 'wp_get_ability' )
	? wp_get_ability( 'designsetgo/serialize-blocks' )
	: \DesignSetGo\Abilities\Abilities_Registry::get_instance()->get_ability( 'designsetgo/serialize-blocks' );
foreach ( $designsetgo_data['designs'] as $designsetgo_design ) {
	$designsetgo_result = $designsetgo_ability->execute( array( 'blocks' => $designsetgo_design['blocks'] ) );
	if ( is_wp_error( $designsetgo_result ) || empty( $designsetgo_result['success'] ) ) {
		throw new Exception( wp_json_encode( $designsetgo_result ) );
	}
	$designsetgo_content .= $designsetgo_result['content'] . "\n";
}
if ( false !== strpos( $designsetgo_content, 'dsgoCustomCSS' ) ) {
	throw new Exception( 'Reference compositions must have zero residual layout CSS.' );
}
$designsetgo_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'DSGo layout flexibility references',
		'post_content' => wp_slash( $designsetgo_content ),
	),
	true
);
if ( is_wp_error( $designsetgo_id ) ) {
	throw new Exception( esc_html( $designsetgo_id->get_error_message() ) );
}
echo (int) $designsetgo_id;
