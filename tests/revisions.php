<?php

define( 'ABSPATH', __DIR__ );

$aslc_revision_upload_dir = sys_get_temp_dir() . '/aslc-revisions-' . getmypid();
if ( ! is_dir( $aslc_revision_upload_dir ) ) {
	mkdir( $aslc_revision_upload_dir );
}
define( 'CCJ_UPLOAD_DIR', $aslc_revision_upload_dir );

$GLOBALS['aslc_registered_post_type'] = array();
$GLOBALS['aslc_registered_post_meta'] = array();

function _x( $text ) {
	return $text;
}

function __( $text ) {
	return $text;
}

function register_post_type( $post_type, $args ) {
	$GLOBALS['aslc_registered_post_type'] = array( $post_type, $args );
}

function register_post_meta( $post_type, $meta_key, $args ) {
	$GLOBALS['aslc_registered_post_meta'] = array( $post_type, $meta_key, $args );
}

function wp_is_writable( $path ) {
	return is_writable( $path );
}

function wp_mkdir_p( $path ) {
	return mkdir( $path, 0777, true );
}

function get_post_meta( $post_id, $meta_key, $single ) {
	return '';
}

function sanitize_file_name( $file_name ) {
	return $file_name;
}

function add_filter() {
}

function add_action() {
}

require dirname( __DIR__ ) . '/as-local-css/includes/admin-install.php';
require dirname( __DIR__ ) . '/as-local-css/includes/admin-screens.php';

function aslc_revision_test( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

CustomCSSandJS_Install::register_post_type();

list( $post_type, $post_type_args ) = $GLOBALS['aslc_registered_post_type'];
aslc_revision_test( 'custom-css-js' === $post_type, 'The custom code post type was not registered.' );
aslc_revision_test( in_array( 'revisions', $post_type_args['supports'], true ), 'The custom code post type does not support revisions.' );

list( $meta_post_type, $meta_key, $meta_args ) = $GLOBALS['aslc_registered_post_meta'];
aslc_revision_test( 'custom-css-js' === $meta_post_type, 'Revisioned meta was registered for the wrong post type.' );
aslc_revision_test( 'options' === $meta_key, 'The custom code options meta key was not registered.' );
aslc_revision_test( true === $meta_args['revisions_enabled'], 'Custom code options were not enabled for revisions.' );
aslc_revision_test( true === $meta_args['single'], 'Custom code options must remain a single meta value.' );

$admin      = ( new ReflectionClass( 'CustomCSSandJS_Admin' ) )->newInstanceWithoutConstructor();
$write_file = new ReflectionMethod( 'CustomCSSandJS_Admin', 'write_code_file' );
if ( PHP_VERSION_ID < 80100 ) {
	$write_file->setAccessible( true );
}
$write_file->invoke(
	$admin,
	42,
	'.revision-test { color: green; }',
	7,
	array(
		'language' => 'css',
		'linking'  => 'internal',
	)
);

$generated_file = CCJ_UPLOAD_DIR . '/42.css';
aslc_revision_test( file_exists( $generated_file ), 'Restored CSS was not written to the generated file.' );
aslc_revision_test(
	false !== strpos( file_get_contents( $generated_file ), '.revision-test { color: green; }' ),
	'The generated revision file did not contain the restored CSS.'
);

unlink( $generated_file );
rmdir( CCJ_UPLOAD_DIR );

echo 'Revision registration test passed.' . PHP_EOL;
