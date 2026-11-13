<?php
/**
 * Native WordPress taxonomy for organising Local CSS records.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ASLC_CODE_DOMAIN_TAXONOMY       = 'aslc_code_domain';
const ASLC_CODE_DOMAINS_SEEDED_OPTION = 'aslc_code_domains_seeded';

/** Return the initial Code Domain terms keyed by slug. */
function aslc_get_default_code_domains() {
	return array(
		'theme'     => __( 'Theme', 'custom-css-js' ),
		'block'     => __( 'Block', 'custom-css-js' ),
		'page'      => __( 'Page', 'custom-css-js' ),
		'cpt'       => __( 'CPT', 'custom-css-js' ),
		'plugin'    => __( 'Plugin', 'custom-css-js' ),
		'utility'   => __( 'Utility', 'custom-css-js' ),
		'component' => __( 'Component', 'custom-css-js' ),
		'shortcode' => __( 'Shortcode', 'custom-css-js' ),
	);
}

/** Register the hierarchical Code Domains taxonomy. */
function aslc_register_code_domain_taxonomy() {
	$labels = array(
		'name'              => _x( 'Code Domains', 'taxonomy general name', 'custom-css-js' ),
		'singular_name'     => _x( 'Code Domain', 'taxonomy singular name', 'custom-css-js' ),
		'menu_name'         => __( 'Code Domains', 'custom-css-js' ),
		'all_items'         => __( 'All Code Domains', 'custom-css-js' ),
		'edit_item'         => __( 'Edit Code Domain', 'custom-css-js' ),
		'view_item'         => __( 'View Code Domain', 'custom-css-js' ),
		'update_item'       => __( 'Update Code Domain', 'custom-css-js' ),
		'add_new_item'      => __( 'Add New Code Domain', 'custom-css-js' ),
		'new_item_name'     => __( 'New Code Domain Name', 'custom-css-js' ),
		'parent_item'       => __( 'Parent Code Domain', 'custom-css-js' ),
		'parent_item_colon' => __( 'Parent Code Domain:', 'custom-css-js' ),
		'search_items'      => __( 'Search Code Domains', 'custom-css-js' ),
		'not_found'         => __( 'No Code Domains found.', 'custom-css-js' ),
	);

	register_taxonomy(
		ASLC_CODE_DOMAIN_TAXONOMY,
		array( 'custom-css-js' ),
		array(
			'labels'            => $labels,
			'public'            => false,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_admin_column' => true,
			'show_in_quick_edit' => true,
			'show_in_rest'      => false,
			'query_var'         => false,
			'rewrite'           => false,
			'capabilities'      => array(
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'edit_custom_csss',
			),
		)
	);
}
add_action( 'init', 'aslc_register_code_domain_taxonomy', 11 );

/** Seed the initial terms once, without recreating intentionally removed terms. */
function aslc_seed_default_code_domains() {
	if ( get_option( ASLC_CODE_DOMAINS_SEEDED_OPTION, false ) || ! taxonomy_exists( ASLC_CODE_DOMAIN_TAXONOMY ) ) {
		return;
	}

	$seeded = true;
	foreach ( aslc_get_default_code_domains() as $slug => $name ) {
		if ( term_exists( $slug, ASLC_CODE_DOMAIN_TAXONOMY ) ) {
			continue;
		}

		$result = wp_insert_term( $name, ASLC_CODE_DOMAIN_TAXONOMY, array( 'slug' => $slug ) );
		if ( is_wp_error( $result ) ) {
			$seeded = false;
		}
	}

	if ( $seeded ) {
		update_option( ASLC_CODE_DOMAINS_SEEDED_OPTION, 1, false );
	}
}
add_action( 'init', 'aslc_seed_default_code_domains', 12 );

/** Keep the native taxonomy panel on the custom code editor. */
function aslc_allow_code_domain_meta_box( array $allowed ) {
	$allowed[] = ASLC_CODE_DOMAIN_TAXONOMY . 'div';
	return array_values( array_unique( $allowed ) );
}
add_filter( 'custom-css-js-meta-boxes', 'aslc_allow_code_domain_meta_box' );
