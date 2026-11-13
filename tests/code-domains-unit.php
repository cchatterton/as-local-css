<?php

define( 'ABSPATH', __DIR__ );

$GLOBALS['aslc_taxonomy'] = array();
$GLOBALS['aslc_terms']    = array();
$GLOBALS['aslc_options']  = array();
$GLOBALS['aslc_assigned'] = array();
$GLOBALS['aslc_enforced'] = 0;

function __( $text ) { return $text; }
function _x( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
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
function get_post_type() { return 'custom-css-js'; }
function wp_get_object_terms() { return $GLOBALS['aslc_assigned']; }
function wp_set_object_terms( $object_id, $terms ) {
	$GLOBALS['aslc_assigned'] = array_map( 'absint', $terms );
	$GLOBALS['aslc_enforced']++;
}

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
aslc_domain_assert( false === $GLOBALS['aslc_taxonomy']['args']['show_in_quick_edit'], 'Quick Edit must not show multi-select checkboxes.' );
aslc_domain_assert( 'aslc_render_code_domain_meta_box' === $GLOBALS['aslc_taxonomy']['args']['meta_box_cb'], 'The radio metabox callback is not registered.' );
aslc_domain_assert( array( 12 ) === aslc_sanitize_code_domain_meta_box( ASLC_CODE_DOMAIN_TAXONOMY, array( 0, 12, 24 ) ), 'The metabox sanitizer did not keep a single domain.' );
aslc_domain_assert( array() === aslc_sanitize_code_domain_meta_box( ASLC_CODE_DOMAIN_TAXONOMY, array( 0 ) ), 'No domain should clear the taxonomy.' );

$GLOBALS['aslc_assigned'] = array( 3, 7 );
aslc_enforce_single_code_domain( 42, array( 3, 7 ), array(), ASLC_CODE_DOMAIN_TAXONOMY );
aslc_domain_assert( array( 7 ) === $GLOBALS['aslc_assigned'], 'Programmatic assignment did not collapse to one Code Domain.' );
aslc_domain_assert( 1 === $GLOBALS['aslc_enforced'], 'Programmatic assignment was not enforced exactly once.' );

aslc_seed_default_code_domains();
$expected = array( 'theme', 'block', 'page', 'cpt', 'plugin', 'utility', 'component', 'shortcode' );
aslc_domain_assert( $expected === array_keys( $GLOBALS['aslc_terms'] ), 'The default Code Domains are incorrect.' );
aslc_domain_assert( 1 === $GLOBALS['aslc_options'][ ASLC_CODE_DOMAINS_SEEDED_OPTION ], 'The seed flag was not saved.' );

$GLOBALS['aslc_terms']['theme'] = 'Renamed Theme';
aslc_seed_default_code_domains();
aslc_domain_assert( 'Renamed Theme' === $GLOBALS['aslc_terms']['theme'], 'A later administrator change was overwritten.' );

echo "Code Domains unit test passed.\n";
