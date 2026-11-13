<?php

define( 'ABSPATH', __DIR__ );

$GLOBALS['aslc_taxonomy'] = array();
$GLOBALS['aslc_terms']    = array();
$GLOBALS['aslc_options']  = array();

function __( $text ) { return $text; }
function _x( $text ) { return $text; }
function add_action() {}
function add_filter() {}
function register_taxonomy( $taxonomy, $objects, $args ) {
	$GLOBALS['aslc_taxonomy'] = compact( 'taxonomy', 'objects', 'args' );
}
function taxonomy_exists( $taxonomy ) { return ASLC_CODE_DOMAIN_TAXONOMY === $taxonomy; }
function get_option( $name, $default = false ) { return $GLOBALS['aslc_options'][ $name ] ?? $default; }
function update_option( $name, $value ) { $GLOBALS['aslc_options'][ $name ] = $value; }
function term_exists( $slug ) { return isset( $GLOBALS['aslc_terms'][ $slug ] ); }
function wp_insert_term( $name, $taxonomy, $args ) {
	$GLOBALS['aslc_terms'][ $args['slug'] ] = $name;
	return array( 'term_id' => count( $GLOBALS['aslc_terms'] ) );
}
function is_wp_error() { return false; }

require dirname( __DIR__ ) . '/as-local-css/includes/code-domains.php';

function aslc_domain_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

aslc_register_code_domain_taxonomy();
aslc_domain_assert( 'aslc_code_domain' === $GLOBALS['aslc_taxonomy']['taxonomy'], 'The taxonomy key is incorrect.' );
aslc_domain_assert( array( 'custom-css-js' ) === $GLOBALS['aslc_taxonomy']['objects'], 'The taxonomy is attached to the wrong post type.' );
aslc_domain_assert( true === $GLOBALS['aslc_taxonomy']['args']['hierarchical'], 'Code Domains must be hierarchical.' );
aslc_domain_assert( 'Code Domains' === $GLOBALS['aslc_taxonomy']['args']['labels']['menu_name'], 'The submenu label is incorrect.' );

aslc_seed_default_code_domains();
$expected = array( 'theme', 'block', 'page', 'cpt', 'plugin', 'utility', 'component', 'shortcode' );
aslc_domain_assert( $expected === array_keys( $GLOBALS['aslc_terms'] ), 'The default Code Domains are incorrect.' );
aslc_domain_assert( 1 === $GLOBALS['aslc_options'][ ASLC_CODE_DOMAINS_SEEDED_OPTION ], 'The seed flag was not saved.' );

$GLOBALS['aslc_terms']['theme'] = 'Renamed Theme';
aslc_seed_default_code_domains();
aslc_domain_assert( 'Renamed Theme' === $GLOBALS['aslc_terms']['theme'], 'A later administrator change was overwritten.' );

echo "Code Domains unit test passed.\n";
