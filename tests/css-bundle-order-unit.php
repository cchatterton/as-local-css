<?php
/**
 * Lightweight regression test for CSS bundle ordering and generated markup.
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Post {
	public $ID;
	public $post_type = 'custom-css-js';
	public $post_status = 'publish';
	public $post_author;
	public $post_content;
}

function add_action() {}
function add_filter() {}
function get_option( $name, $default = false ) {
	return 'ccj_settings' === $name ? array( 'remove_comments' => false ) : $default;
}
function get_post( $post_id ) {
	$priorities = array( 101 => 10, 102 => 2, 103 => 1 );
	if ( ! isset( $priorities[ $post_id ] ) ) {
		return null;
	}
	$post = new WP_Post();
	$post->ID = $post_id;
	$post->post_author = 7;
	$post->post_content = '.priority-' . $priorities[ $post_id ] . ' {}';
	return $post;
}
function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

require dirname( __DIR__ ) . '/as-local-css/includes/css-bundles.php';

$tree = array(
	'frontend-css-footer-internal' => array( 10 => array( '101.css' ) ),
	'frontend-css-header-internal' => array(
		2 => array( '102.css' ),
		1 => array( '103.css' ),
	),
);
$css = aslc_compile_css_bundle( $tree, 'frontend' );

if ( ! is_string( $css )
	|| ! ( strpos( $css, '.priority-10 {}' ) < strpos( $css, '.priority-2 {}' ) )
	|| ! ( strpos( $css, '.priority-2 {}' ) < strpos( $css, '.priority-1 {}' ) ) ) {
	throw new RuntimeException( 'Priority 1 was not emitted last.' );
}
if ( false === strpos( $css, 'Local Start ID:103 Author:7' )
	|| false === strpos( $css, 'Local End ID:103 Author:7' ) ) {
	throw new RuntimeException( 'Author IDs were not retained in boundary comments.' );
}

$tag = aslc_double_quote_bundle_link_attributes(
	"<link rel='stylesheet' id='aslc-css-frontend-css' href='https://example.test/bundle.css' media='all' />",
	'aslc-css-frontend'
);
if ( false !== strpos( $tag, "='" ) || false === strpos( $tag, 'href="https://example.test/bundle.css"' ) ) {
	throw new RuntimeException( 'Bundle link attributes were not converted to double quotes.' );
}

echo "CSS bundle order and markup test passed.\n";
