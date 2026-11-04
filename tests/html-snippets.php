<?php

define( 'ABSPATH', __DIR__ );

class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public $post_content;

	public function __construct( $id, $post_type, $post_status, $post_content ) {
		$this->ID           = $id;
		$this->post_type    = $post_type;
		$this->post_status  = $post_status;
		$this->post_content = $post_content;
	}
}

$GLOBALS['aslc_html_posts'] = array(
	123 => new WP_Post( 123, 'custom-css-js', 'publish', '<aside>Rendered HTML</aside>' ),
	124 => new WP_Post( 124, 'custom-css-js', 'draft', '<aside>Draft HTML</aside>' ),
	125 => new WP_Post( 125, 'post', 'publish', '<aside>Wrong post type</aside>' ),
);
$GLOBALS['aslc_requested_post_ids'] = array();

function get_post( $post_id ) {
	$GLOBALS['aslc_requested_post_ids'][] = $post_id;
	return $GLOBALS['aslc_html_posts'][ $post_id ] ?? null;
}

require dirname( __DIR__ ) . '/as-local-css/includes/html-snippets.php';

function aslc_html_test( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

aslc_html_test(
	'<aside>Rendered HTML</aside>' === aslc_get_html_snippet_content( '123.html' ),
	'Internal filename-shaped HTML reference did not render.'
);
aslc_html_test(
	'<aside>Rendered HTML</aside>' === aslc_get_html_snippet_content( '123.html?priority=5&v=99' ),
	'External HTML reference with a query string did not render.'
);
aslc_html_test(
	'<aside>Rendered HTML</aside>' === aslc_get_html_snippet_content( '123' ),
	'Numeric HTML reference did not render.'
);
aslc_html_test( array( 123, 123, 123 ) === $GLOBALS['aslc_requested_post_ids'], 'HTML references were not resolved to integer post IDs.' );
aslc_html_test( '' === aslc_get_html_snippet_content( 'not-a-post.html' ), 'Invalid HTML reference was not rejected.' );
aslc_html_test( '' === aslc_get_html_snippet_content( '124.html' ), 'Draft HTML snippet was rendered.' );
aslc_html_test( '' === aslc_get_html_snippet_content( '125.html' ), 'Non-snippet post was rendered.' );
aslc_html_test( '' === aslc_get_html_snippet_content( '999.html' ), 'Missing HTML snippet was rendered.' );

echo 'HTML snippet behavior test passed.' . PHP_EOL;
