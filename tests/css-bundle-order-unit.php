<?php
/**
 * Lightweight regression test for CSS bundle ordering and generated markup.
 */

define( 'ABSPATH', __DIR__ . '/' );

$aslc_test_upload_dir = sys_get_temp_dir() . '/aslc-css-bundle-' . uniqid( '', true );
mkdir( $aslc_test_upload_dir );
define( 'CCJ_UPLOAD_DIR', $aslc_test_upload_dir );
define( 'ASLC_PLUGIN_FILE', __FILE__ );

class WP_Post {
	public $ID;
	public $post_type = 'custom-css-js';
	public $post_status = 'publish';
	public $post_author;
	public $post_content;
}

function add_action() {}
function add_filter() {}
function plugin_basename( $file ) {
	return basename( $file );
}
function trailingslashit( $path ) {
	return rtrim( $path, '/\\' ) . '/';
}
function __( $text ) {
	return $text;
}
function get_option( $name, $default = false ) {
	global $aslc_test_delivery_mode, $aslc_test_priority_order, $aslc_test_breakpoints;
	if ( 'ccj_settings' === $name ) {
		return array( 'remove_comments' => false );
	}
	if ( 'aslc_css_delivery_mode' === $name ) {
		return $aslc_test_delivery_mode;
	}
	if ( 'aslc_css_priority_order' === $name ) {
		return $aslc_test_priority_order;
	}
	if ( 'aslc_css_breakpoint_tokens' === $name ) {
		return $aslc_test_breakpoints;
	}
	return $default;
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

class WP_Error {
	private $message;

	public function __construct( $code, $message ) {
		unset( $code );
		$this->message = $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

require dirname( __DIR__ ) . '/as-local-css/includes/css-bundles.php';

$aslc_test_delivery_mode = 'file';
$aslc_test_priority_order = '5-to-1';
$aslc_test_breakpoints = array( '%MD%' => '68rem' );
if ( ! aslc_use_css_file_delivery() ) {
	throw new RuntimeException( 'Generated-file mode was not recognised.' );
}
$aslc_test_delivery_mode = 'inline';
if ( aslc_use_css_file_delivery() ) {
	throw new RuntimeException( 'Inline mode still enabled generated-file delivery.' );
}
$aslc_test_delivery_mode = 'file';

$tree = array(
	'frontend-css-footer-internal' => array( 10 => array( '101.css' ) ),
	'frontend-css-header-internal' => array(
		2 => array( '102.css' ),
		1 => array( '103.css' ),
	),
);
$generated_css = array(
	101 => '.priority-10 {}',
	102 => '@media (min-width: %MD%) { .priority-2 {} }',
	103 => '.priority-1-from-file {}',
);
foreach ( $generated_css as $post_id => $contents ) {
	file_put_contents(
		trailingslashit( CCJ_UPLOAD_DIR ) . $post_id . '.css',
		'<!-- Local Start ID:' . $post_id . ' Author:7 -->' . PHP_EOL
		. '<style>' . PHP_EOL
		. $contents . PHP_EOL
		. '</style>' . PHP_EOL
		. '<!-- Local End ID:' . $post_id . ' Author:7 -->'
	);
}
$css = aslc_compile_css_bundle( $tree, 'frontend' );

if ( ! is_string( $css )
	|| ! ( strpos( $css, '.priority-10 {}' ) < strpos( $css, '.priority-2 {}' ) )
	|| ! ( strpos( $css, '.priority-2 {}' ) < strpos( $css, '.priority-1-from-file {}' ) ) ) {
	throw new RuntimeException( 'Priority 1 was not emitted last.' );
}
if ( false !== strpos( $css, '.priority-1 {}' ) ) {
	throw new RuntimeException( 'The bundle used stale database content instead of the generated CSS file.' );
}
if ( false !== strpos( $css, '%MD%' ) || false === strpos( $css, '@media (min-width: 68rem)' ) ) {
	throw new RuntimeException( 'Configured breakpoint tokens were not replaced in the generated bundle.' );
}
if ( array( '%MD%' => '68rem' ) !== aslc_sanitize_css_breakpoint_tokens( array( array( 'token' => 'md', 'value' => ' 68REM ' ) ) ) ) {
	throw new RuntimeException( 'Breakpoint repeater values were not normalised.' );
}
if ( array() !== aslc_sanitize_css_breakpoint_tokens( array( array( 'token' => 'bad token', 'value' => 'javascript:1' ) ) ) ) {
	throw new RuntimeException( 'Invalid breakpoint repeater values were accepted.' );
}
if ( false === strpos( $css, 'Local Start ID:103 Author:7' )
	|| false === strpos( $css, 'Local End ID:103 Author:7' ) ) {
	throw new RuntimeException( 'Author IDs were not retained in boundary comments.' );
}

$aslc_test_priority_order = '1-to-5';
$ascending_css = aslc_compile_css_bundle( $tree, 'frontend' );
if ( ! is_string( $ascending_css )
	|| ! ( strpos( $ascending_css, '.priority-1-from-file {}' ) < strpos( $ascending_css, '.priority-2 {}' ) )
	|| ! ( strpos( $ascending_css, '.priority-2 {}' ) < strpos( $ascending_css, '.priority-10 {}' ) ) ) {
	throw new RuntimeException( 'The selected ascending CSS priority order was not applied.' );
}
$aslc_test_priority_order = '5-to-1';

$tag = aslc_double_quote_bundle_link_attributes(
	"<link rel='stylesheet' id='aslc-css-frontend-css' href='https://example.test/bundle.css' media='all' />",
	'aslc-css-frontend'
);
if ( false !== strpos( $tag, "='" ) || false === strpos( $tag, 'href="https://example.test/bundle.css"' ) ) {
	throw new RuntimeException( 'Bundle link attributes were not converted to double quotes.' );
}

foreach ( array_keys( $generated_css ) as $post_id ) {
	unlink( trailingslashit( CCJ_UPLOAD_DIR ) . $post_id . '.css' );
}
rmdir( CCJ_UPLOAD_DIR );

echo "CSS bundle order and markup test passed.\n";
