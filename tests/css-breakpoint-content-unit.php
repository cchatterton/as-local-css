<?php
/** Lightweight tests for responsive CSS section assembly. */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ASLC_PLUGIN_FILE', __FILE__ );

$GLOBALS['aslc_test_rows'] = array(
	array( 'id' => 'sm', 'token' => '%SM%', 'value' => '48rem' ),
	array( 'id' => 'md', 'token' => '%MD%', 'value' => '68rem' ),
);
$GLOBALS['aslc_test_meta'] = array(
	'aslc_css_breakpoint_sections' => array(
		'removed-row' => '.retained { color: blue; }',
		'md'          => '.old { color: red; }',
	),
	'aslc_css_target_scope'        => '',
);

function add_action() {}
function add_filter() {}
function plugin_basename( $file ) { return basename( $file ); }
function get_option( $name, $default = false ) {
	return 'aslc_css_breakpoint_tokens' === $name ? $GLOBALS['aslc_test_rows'] : $default;
}
function get_post_meta( $post_id, $key, $single ) {
	unset( $post_id, $single );
	return $GLOBALS['aslc_test_meta'][ $key ] ?? '';
}
function update_post_meta( $post_id, $key, $value ) {
	unset( $post_id );
	$GLOBALS['aslc_test_meta'][ $key ] = $value;
}
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $key ) ); }
function wp_unslash( $value ) { return stripslashes( $value ); }

require dirname( __DIR__ ) . '/as-local-css/includes/css-bundles.php';
require dirname( __DIR__ ) . '/as-local-css/includes/breakpoint-content.php';

$css = aslc_assemble_css_sections(
	'.base { display: block; }',
	array(
		'sm' => '',
		'md' => ".wide { display: grid; }\n",
	)
);

if ( ".base { display: block; }\n\n@media (min-width: 68rem) {\n.wide { display: grid; }\n}" !== $css ) {
	throw new RuntimeException( 'Responsive CSS sections were not assembled correctly.' );
}
if ( false !== strpos( $css, '48rem' ) ) {
	throw new RuntimeException( 'An empty breakpoint section emitted a media query.' );
}

$scoped_css = aslc_assemble_css_sections(
	'& .base-child { display: block; }',
	array(
		'sm' => '',
		'md' => '& .wide-child { display: grid; }',
	),
	$GLOBALS['aslc_test_rows'],
	'.columns'
);
$expected_scoped_css = ".columns {\n\tvisibility: visible;\n\n\t& .base-child { display: block; }\n}\n\n"
	. "@media (min-width: 68rem) {\n\t.columns {\n\t\tvisibility: visible;\n\n\t\t& .wide-child { display: grid; }\n\t}\n}";
if ( $expected_scoped_css !== $scoped_css ) {
	throw new RuntimeException( "Target scope CSS was not wrapped independently around Base and breakpoint sections.\n" . $scoped_css );
}
if ( '' !== aslc_sanitize_css_target_scope( '.columns { color: red; }' ) ) {
	throw new RuntimeException( 'A target scope containing declarations was accepted.' );
}

$renamed = aslc_sanitize_css_breakpoint_rows(
	array(
		array( 'id' => 'md', 'token' => '%TABLET%', 'value' => '70rem' ),
	)
);
if ( 'md' !== $renamed[0]['id'] || '%TABLET%' !== $renamed[0]['token'] || '70rem' !== $renamed[0]['value'] ) {
	throw new RuntimeException( 'A renamed breakpoint did not retain its stable ID.' );
}

$saved = aslc_save_css_breakpoint_sections(
	99,
	array(
		'sm' => '',
		'md' => '.new { color: green; }',
	)
);
if ( '.retained { color: blue; }' !== $saved['removed-row'] ) {
	throw new RuntimeException( 'CSS from a removed breakpoint row was discarded.' );
}
if ( '.new { color: green; }' !== $saved['md'] ) {
	throw new RuntimeException( 'Active breakpoint CSS was not saved.' );
}

$saved_scope = aslc_save_css_target_scope( 99, '  #contact-us  ' );
if ( '#contact-us' !== $saved_scope || '#contact-us' !== aslc_get_css_target_scope( 99 ) ) {
	throw new RuntimeException( 'The CSS target scope was not normalized and saved.' );
}

echo "CSS breakpoint content test passed.\n";
