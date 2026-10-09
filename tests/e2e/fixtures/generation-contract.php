<?php
/**
 * Create the browser test's disposable page using installed public abilities.
 *
 * @package DesignSetGo
 */

defined( 'ABSPATH' ) || exit;

wp_set_current_user( 1 );
$designsetgo_input  = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/generation-contract.json' ), true );
$designsetgo_templates = array();
foreach ( array( 'tabletColumnTemplate', 'mobileColumnTemplate' ) as $designsetgo_key ) {
	$designsetgo_templates[ $designsetgo_key ] = $designsetgo_input['blocks'][0]['attributes'][ $designsetgo_key ];
	unset( $designsetgo_input['blocks'][0]['attributes'][ $designsetgo_key ] );
}
$designsetgo_result = wp_get_ability( 'designsetgo/serialize-blocks' )->execute( $designsetgo_input );
if ( is_wp_error( $designsetgo_result ) || empty( $designsetgo_result['success'] ) ) {
	throw new Exception( wp_json_encode( $designsetgo_result ) );
}
$designsetgo_page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'DSGo generation contract',
		'post_content' => wp_slash( $designsetgo_result['content'] ),
	),
	true
);
if ( is_wp_error( $designsetgo_page_id ) ) {
	throw new Exception( esc_html( $designsetgo_page_id->get_error_message() ) );
}
$designsetgo_update = wp_get_ability( 'designsetgo/update-block' )->execute(
	array(
		'post_id'     => $designsetgo_page_id,
		'block_index' => 0,
		'attributes'  => array_merge( $designsetgo_templates, array( 'dsgoLayout' => array( 'desktop' => array( 'minHeight' => '220px' ) ) ) ),
	)
);
if ( is_wp_error( $designsetgo_update ) || empty( $designsetgo_update['success'] ) ) {
	wp_delete_post( $designsetgo_page_id, true );
	throw new Exception( wp_json_encode( $designsetgo_update ) );
}
$designsetgo_css = wp_get_ability( 'designsetgo/configure-custom-css' )->execute(
	array(
		'post_id'    => $designsetgo_page_id,
		'block_name' => 'core/paragraph',
		'css'        => array(
			'desktop' => 'selector { color: rgb(18, 52, 86); }',
			'mobile'  => 'selector { color: rgb(101, 67, 33); }',
		),
	)
);
if ( is_wp_error( $designsetgo_css ) || empty( $designsetgo_css['success'] ) ) {
	wp_delete_post( $designsetgo_page_id, true );
	throw new Exception( wp_json_encode( $designsetgo_css ) );
}
echo (int) $designsetgo_page_id;
