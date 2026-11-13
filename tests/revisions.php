<?php

define( 'ABSPATH', __DIR__ );

$aslc_revision_upload_dir = sys_get_temp_dir() . '/aslc-revisions-' . getmypid();
if ( ! is_dir( $aslc_revision_upload_dir ) ) {
	mkdir( $aslc_revision_upload_dir );
}
define( 'CCJ_UPLOAD_DIR', $aslc_revision_upload_dir );
define( 'ASLC_PLUGIN_FILE', __FILE__ );

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
	$GLOBALS['aslc_registered_post_meta'][ $meta_key ] = array( $post_type, $meta_key, $args );
}

function get_option( $name, $default = false ) {
	return $default;
}

function update_post_meta() {
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $key ) );
}

function plugin_basename( $file ) {
	return basename( $file );
}

function wp_is_writable( $path ) {
	return is_writable( $path );
}

function wp_mkdir_p( $path ) {
	return mkdir( $path, 0777, true );
}

function get_post_meta( $post_id, $meta_key, $single ) {
	if ( 'aslc_css_target_scope' === $meta_key ) {
		return '#revision-scope';
	}
	return '';
}

function sanitize_file_name( $file_name ) {
	return $file_name;
}

function add_filter() {
}

function add_action() {
}

require dirname( __DIR__ ) . '/as-local-css/includes/css-bundles.php';
require dirname( __DIR__ ) . '/as-local-css/includes/breakpoint-content.php';
require dirname( __DIR__ ) . '/as-local-css/includes/code-domains.php';
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
aslc_revision_test( in_array( ASLC_CODE_DOMAIN_TAXONOMY, $post_type_args['taxonomies'], true ), 'The Code Domains taxonomy is not attached to the custom code post type.' );

list( $meta_post_type, $meta_key, $meta_args ) = $GLOBALS['aslc_registered_post_meta']['options'];
aslc_revision_test( 'custom-css-js' === $meta_post_type, 'Revisioned meta was registered for the wrong post type.' );
aslc_revision_test( 'options' === $meta_key, 'The custom code options meta key was not registered.' );
aslc_revision_test( true === $meta_args['revisions_enabled'], 'Custom code options were not enabled for revisions.' );
aslc_revision_test( true === $meta_args['single'], 'Custom code options must remain a single meta value.' );

list( $section_post_type, $section_meta_key, $section_meta_args ) = $GLOBALS['aslc_registered_post_meta'][ ASLC_CSS_BREAKPOINT_SECTIONS_META ];
aslc_revision_test( 'custom-css-js' === $section_post_type, 'Breakpoint CSS was registered for the wrong post type.' );
aslc_revision_test( ASLC_CSS_BREAKPOINT_SECTIONS_META === $section_meta_key, 'Breakpoint CSS meta was not registered.' );
aslc_revision_test( true === $section_meta_args['revisions_enabled'], 'Breakpoint CSS was not enabled for revisions.' );
aslc_revision_test( true === $section_meta_args['single'], 'Breakpoint CSS must remain a single meta value.' );

list( $target_post_type, $target_meta_key, $target_meta_args ) = $GLOBALS['aslc_registered_post_meta'][ ASLC_CSS_TARGET_SCOPE_META ];
aslc_revision_test( 'custom-css-js' === $target_post_type, 'The CSS target scope was registered for the wrong post type.' );
aslc_revision_test( ASLC_CSS_TARGET_SCOPE_META === $target_meta_key, 'The CSS target scope meta was not registered.' );
aslc_revision_test( true === $target_meta_args['revisions_enabled'], 'The CSS target scope was not enabled for revisions.' );
aslc_revision_test( true === $target_meta_args['single'], 'The CSS target scope must remain a single meta value.' );

$admin      = ( new ReflectionClass( 'CustomCSSandJS_Admin' ) )->newInstanceWithoutConstructor();
$write_file = new ReflectionMethod( 'CustomCSSandJS_Admin', 'write_code_file' );
if ( PHP_VERSION_ID < 80100 ) {
	$write_file->setAccessible( true );
}
$write_file->invoke(
	$admin,
	42,
	".revision-test { color: green; }\n@media (min-width: %MD%) { .revision-wide { display: grid; } }",
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
aslc_revision_test(
	false !== strpos( file_get_contents( $generated_file ), '@media (min-width: 68rem)' ),
	'Configured breakpoint tokens were not resolved in the shared generated CSS file.'
);
aslc_revision_test(
	false !== strpos( file_get_contents( $generated_file ), "#revision-scope {\n\tvisibility: visible;" ),
	'The restored CSS target scope was not applied to the generated revision file.'
);

unlink( $generated_file );
rmdir( CCJ_UPLOAD_DIR );

echo 'Revision registration test passed.' . PHP_EOL;
