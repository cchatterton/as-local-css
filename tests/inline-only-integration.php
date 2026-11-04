<?php
/**
 * Disposable WordPress integration test for the AS Local CSS inline-only release.
 *
 * Run through WP-CLI with WP_ADMIN defined before WordPress loads.
 */

wp_set_current_user( 1 );

function aslc_inline_test( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

class ASLC_Inline_WP_Die_Exception extends RuntimeException {
}

function aslc_inline_wp_die_handler() {
	return static function ( $message ) {
		throw new ASLC_Inline_WP_Die_Exception( (string) $message );
	};
}

$post_id = wp_insert_post(
	array(
		'post_type'    => 'custom-css-js',
		'post_status'  => 'publish',
		'post_title'   => 'ASLC inline-only integration test',
		'post_content' => '.aslc-inline-test { color: green; }',
	),
	true
);
aslc_inline_test( ! is_wp_error( $post_id ), 'Could not create the temporary custom code post.' );

$user_id = wp_insert_user(
	array(
		'user_login' => 'aslc-inline-test-' . wp_generate_password( 8, false ),
		'user_pass'  => wp_generate_password( 24, true ),
		'role'       => 'subscriber',
	)
);
aslc_inline_test( ! is_wp_error( $user_id ), 'Could not create the temporary unauthorized user.' );

try {
	update_post_meta(
		$post_id,
		'options',
		array(
			'type'     => 'header',
			'linking'  => 'external',
			'side'     => 'frontend',
			'priority' => 4,
			'language' => 'css',
		)
	);
	update_post_meta( $post_id, '_active', 'yes' );
	wp_mkdir_p( CCJ_UPLOAD_DIR );
	file_put_contents( CCJ_UPLOAD_DIR . '/' . $post_id . '.css', '.legacy-external { color: red; }' );

	delete_option( 'aslc_inline_only_migration_0_5_0' );
	$admin = new CustomCSSandJS_Admin();
	$admin->migrate_inline_only_snippets();

	$options = get_post_meta( $post_id, 'options', true );
	aslc_inline_test( 'internal' === $options['linking'], 'The legacy external snippet was not migrated to internal output.' );

	$file_contents = file_get_contents( CCJ_UPLOAD_DIR . '/' . $post_id . '.css' );
	aslc_inline_test( false !== strpos( $file_contents, '<style>' ), 'The migrated CSS file is missing its inline style wrapper.' );
	aslc_inline_test( false !== strpos( $file_contents, '.aslc-inline-test { color: green; }' ), 'The migrated CSS file does not contain the database source.' );

	$tree = get_option( 'custom-css-js-tree', array() );
	aslc_inline_test(
		isset( $tree['frontend-css-header-internal'][4] ) && in_array( $post_id . '.css', $tree['frontend-css-header-internal'][4], true ),
		'The search tree was not rebuilt for inline output.'
	);
	aslc_inline_test( ! isset( $tree['frontend-css-header-external'] ), 'The legacy external search-tree branch remains active.' );

	$codemirror = file_get_contents( ASLC_PLUGIN_DIR . 'assets/codemirror/lib/codemirror.js' );
	aslc_inline_test( false !== strpos( $codemirror, 'CodeMirror.version = "5.65.20"' ), 'CodeMirror 5.65.20 is not installed.' );
	aslc_inline_test( false === has_action( 'add_meta_boxes', 'aslc_add_relationship_meta_boxes' ), 'The content-side Related Custom Code metabox is still registered.' );
	aslc_inline_test( false === has_action( 'admin_post_aslc_create_related_code', 'aslc_create_related_code' ), 'The content-side Create CSS/JS action is still registered.' );

	wp_set_current_user( $user_id );
	add_filter( 'wp_die_handler', 'aslc_inline_wp_die_handler' );

	$_GET['code_id'] = $post_id;
	$active_denied   = false;
	try {
		$admin->wp_ajax_ccj_active_code();
	} catch ( ASLC_Inline_WP_Die_Exception $exception ) {
		$active_denied = true;
	}
	aslc_inline_test( $active_denied, 'The activate/deactivate AJAX handler did not reject an unauthorized user.' );

	$_POST = array(
		'ccj_permalink_nonce' => wp_create_nonce( 'ccj-permalink' ),
		'code_id'             => $post_id,
		'new_slug'            => 'unauthorized-change',
	);
	$permalink_denied = false;
	try {
		$admin->wp_ajax_ccj_permalink();
	} catch ( ASLC_Inline_WP_Die_Exception $exception ) {
		$permalink_denied = true;
	}
	aslc_inline_test( $permalink_denied, 'The permalink AJAX handler did not reject an unauthorized user.' );

	echo "Inline-only integration test passed.\n";
} finally {
	remove_filter( 'wp_die_handler', 'aslc_inline_wp_die_handler' );
	wp_set_current_user( 1 );
	wp_delete_post( $post_id, true );
	wp_delete_user( $user_id );
	delete_option( 'aslc_inline_only_migration_0_5_0' );
	unset( $_GET['code_id'] );
	$_POST = array();
}
