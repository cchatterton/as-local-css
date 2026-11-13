<?php
/**
 * Disposable WordPress integration test for generated CSS bundle delivery.
 */

wp_set_current_user( 1 );

function aslc_bundle_test( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function aslc_bundle_test_post( $title, $css, $location, $priority, $side = 'frontend', $generated_css = null ) {
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'custom-css-js',
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_title'   => $title,
			'post_content' => $css,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		throw new RuntimeException( 'Could not create a temporary CSS bundle test post.' );
	}

	update_post_meta(
		$post_id,
		'options',
		array(
			'type'     => $location,
			'linking'  => 'internal',
			'side'     => $side,
			'priority' => $priority,
			'language' => 'css',
		)
	);
	update_post_meta( $post_id, '_active', 'yes' );

	if ( null === $generated_css ) {
		$generated_css = $css;
	}
	$file_contents = '<!-- Local Start ID:' . $post_id . ' Author:1 -->' . PHP_EOL
		. '<style>' . PHP_EOL
		. $generated_css . PHP_EOL
		. '</style>' . PHP_EOL
		. '<!-- Local End ID:' . $post_id . ' Author:1 -->' . PHP_EOL;
	if ( false === file_put_contents( trailingslashit( CCJ_UPLOAD_DIR ) . $post_id . '.css', $file_contents ) ) {
		throw new RuntimeException( 'Could not create a temporary generated CSS snippet file.' );
	}

	return $post_id;
}

$post_ids = array();
$original_delivery_mode = get_option( ASLC_CSS_DELIVERY_OPTION, false );
$original_priority_order = get_option( ASLC_CSS_ORDER_OPTION, false );
$original_scan_results   = get_option( ASLC_CSS_SCAN_OPTION, false );
update_option( ASLC_CSS_DELIVERY_OPTION, 'file', false );
update_option( ASLC_CSS_ORDER_OPTION, '5-to-1', false );
$admin    = new CustomCSSandJS_Admin();
$rebuild  = new ReflectionMethod( 'CustomCSSandJS_Admin', 'build_search_tree' );
if ( PHP_VERSION_ID < 80100 ) {
	$rebuild->setAccessible( true );
}

try {
	$post_ids[] = aslc_bundle_test_post(
		'Bundle header late',
		'.aslc-stale-database-copy { color: red; }',
		'header',
		8,
		'frontend',
		'.aslc-bundle-header-late { color: blue; }'
	);
	$post_ids[] = aslc_bundle_test_post( 'Bundle header early', '.aslc-bundle-header-early { color: green; }', 'header', 2 );
	$post_ids[] = aslc_bundle_test_post( 'Bundle footer', '.aslc-bundle-footer { color: purple; }', 'footer', 1 );
	$post_ids[] = aslc_bundle_test_post( 'Bundle footer low priority', '.aslc-bundle-footer-low { color: brown; }', 'footer', 10 );
	$post_ids[] = aslc_bundle_test_post( 'Bundle admin', '.aslc-bundle-admin { color: black; }', 'header', 3, 'admin' );

	aslc_bundle_test( true === $rebuild->invoke( $admin ), 'CSS bundles did not rebuild successfully.' );

	$frontend = aslc_get_css_bundle_entry( 'frontend' );
	$admin_css = aslc_get_css_bundle_entry( 'admin' );
	aslc_bundle_test( is_array( $frontend ), 'The frontend CSS bundle was not registered.' );
	aslc_bundle_test( is_array( $admin_css ), 'The admin CSS bundle was not registered.' );

	$frontend_path = trailingslashit( CCJ_UPLOAD_DIR ) . $frontend['file'];
	$bundle_css     = file_get_contents( $frontend_path );
	aslc_bundle_test( false !== $bundle_css, 'The frontend CSS bundle could not be read.' );
	aslc_bundle_test( false === strpos( $bundle_css, '<style' ), 'The external CSS bundle contains an inline style wrapper.' );
	aslc_bundle_test( false === strpos( $bundle_css, '.aslc-bundle-admin' ), 'Admin CSS leaked into the frontend bundle.' );
	aslc_bundle_test( false !== strpos( $bundle_css, '.aslc-bundle-header-early' ), 'Priority 2 header CSS is missing.' );
	aslc_bundle_test( false !== strpos( $bundle_css, '.aslc-bundle-header-late' ), 'Priority 8 header CSS is missing.' );
	aslc_bundle_test( false === strpos( $bundle_css, '.aslc-stale-database-copy' ), 'The bundle used stale database content instead of the generated CSS file.' );
	aslc_bundle_test( false !== strpos( $bundle_css, '.aslc-bundle-footer' ), 'Footer CSS is missing.' );
	aslc_bundle_test(
		strpos( $bundle_css, '.aslc-bundle-footer-low {' ) < strpos( $bundle_css, '.aslc-bundle-header-late {' ),
		'Priority 10 CSS was not emitted before higher-priority CSS.'
	);
	aslc_bundle_test(
		strpos( $bundle_css, '.aslc-bundle-header-late {' ) < strpos( $bundle_css, '.aslc-bundle-header-early {' ),
		'Priority 2 CSS was not emitted after priority 8 CSS.'
	);
	aslc_bundle_test(
		strpos( $bundle_css, '.aslc-bundle-header-early {' ) < strpos( $bundle_css, '.aslc-bundle-footer {' ),
		'Priority 1 CSS was not emitted last.'
	);
	aslc_bundle_test(
		false !== strpos( $bundle_css, 'Local Start ID:' . $post_ids[0] . ' Author:1' )
		&& false !== strpos( $bundle_css, 'Local End ID:' . $post_ids[0] . ' Author:1' ),
		'Generated bundle boundary comments did not retain the snippet author ID.'
	);
	aslc_bundle_test( is_array( get_option( ASLC_CSS_SCAN_OPTION, false ) ), 'Bundle rebuilding did not save CSS scan results.' );

	update_option( ASLC_CSS_ORDER_OPTION, '1-to-5', false );
	aslc_bundle_test( true === $rebuild->invoke( $admin ), 'The ascending CSS order did not rebuild successfully.' );
	$ascending_entry = aslc_get_css_bundle_entry( 'frontend' );
	$ascending_css   = file_get_contents( trailingslashit( CCJ_UPLOAD_DIR ) . $ascending_entry['file'] );
	aslc_bundle_test(
		strpos( $ascending_css, '.aslc-bundle-footer {' ) < strpos( $ascending_css, '.aslc-bundle-header-early {' )
		&& strpos( $ascending_css, '.aslc-bundle-header-early {' ) < strpos( $ascending_css, '.aslc-bundle-header-late {' )
		&& strpos( $ascending_css, '.aslc-bundle-header-late {' ) < strpos( $ascending_css, '.aslc-bundle-footer-low {' ),
		'The selected ascending CSS order was not written to the generated file.'
	);
	update_option( ASLC_CSS_ORDER_OPTION, '5-to-1', false );
	aslc_bundle_test( true === $rebuild->invoke( $admin ), 'The descending CSS order did not rebuild successfully.' );
	$frontend = aslc_get_css_bundle_entry( 'frontend' );

	aslc_bundle_test(
		10 === has_action( 'wp_head', 'aslc_print_frontend_css_bundle' ),
		'The generated frontend stylesheet was not registered at the legacy inline-CSS hook priority.'
	);
	ob_start();
	aslc_bundle_test( aslc_print_css_bundle_link( 'frontend' ), 'The frontend CSS bundle link was not printed.' );
	$bundle_link = ob_get_clean();
	aslc_bundle_test( false !== strpos( $bundle_link, 'id="aslc-css-frontend-css"' ), 'The generated bundle link has the wrong ID.' );
	aslc_bundle_test( false !== strpos( $bundle_link, $frontend['file'] ), 'The generated bundle link does not use the current file.' );

	wp_dequeue_style( 'aslc-css-frontend' );
	aslc_bundle_test( aslc_enqueue_css_bundle( 'frontend' ), 'The frontend CSS bundle was not enqueued.' );
	aslc_bundle_test( wp_style_is( 'aslc-css-frontend', 'enqueued' ), 'The frontend bundle handle is not in the style queue.' );
	$registered = wp_styles()->registered['aslc-css-frontend'];
	aslc_bundle_test( false !== strpos( $registered->src, $frontend['file'] ), 'The enqueued URL does not use the generated bundle file.' );
	aslc_bundle_test( $frontend['hash'] === $registered->ver, 'The enqueued bundle does not use its content hash as the version.' );
	$bundle_tag = aslc_double_quote_bundle_link_attributes(
		"<link rel='stylesheet' id='aslc-css-frontend-css' href='https://example.test/bundle.css' media='all' />",
		'aslc-css-frontend'
	);
	aslc_bundle_test( false === strpos( $bundle_tag, "='" ), 'The generated bundle link retained single-quoted attributes.' );
	aslc_bundle_test( false !== strpos( $bundle_tag, 'rel="stylesheet"' ), 'The generated bundle link did not use double-quoted attributes.' );
	aslc_bundle_test(
		"<link rel='stylesheet' id='theme-css' href='https://example.test/theme.css' />" === aslc_double_quote_bundle_link_attributes(
			"<link rel='stylesheet' id='theme-css' href='https://example.test/theme.css' />",
			'theme'
		),
		'The quote filter modified a stylesheet outside AS Local CSS.'
	);

	wp_dequeue_style( 'aslc-editor-css-bundle' );
	aslc_bundle_test(
		aslc_enqueue_css_bundle( 'frontend', 'aslc-editor-css-bundle' ),
		'The frontend CSS bundle was not enqueued for the editor.'
	);
	aslc_bundle_test( wp_style_is( 'aslc-editor-css-bundle', 'enqueued' ), 'The editor bundle handle is not in the style queue.' );

	CustomCSSandJS()->search_tree = get_option( 'custom-css-js-tree', array() );
	ob_start();
	CustomCSSandJS()->{'print_frontend-css-header-internal'}();
	$inline_output = ob_get_clean();
	aslc_bundle_test( '' === $inline_output, 'Inline frontend CSS was printed while a valid bundle was active.' );

	update_option( ASLC_CSS_DELIVERY_OPTION, 'inline', false );
	wp_dequeue_style( 'aslc-css-frontend' );
	aslc_bundle_test( false === aslc_enqueue_css_bundle( 'frontend' ), 'Inline mode still enqueued the generated CSS bundle.' );
	ob_start();
	CustomCSSandJS()->{'print_frontend-css-header-internal'}();
	$legacy_inline_output = ob_get_clean();
	aslc_bundle_test( false !== strpos( $legacy_inline_output, '.aslc-bundle-header-late' ), 'Inline mode did not print the generated snippet CSS.' );
	update_option( ASLC_CSS_DELIVERY_OPTION, 'file', false );

	$old_file = $frontend['file'];
	file_put_contents(
		trailingslashit( CCJ_UPLOAD_DIR ) . $post_ids[0] . '.css',
		'<!-- Local Start ID:' . $post_ids[0] . ' Author:1 -->' . PHP_EOL
		. '<style>' . PHP_EOL
		. '.aslc-bundle-header-late { color: orange; }' . PHP_EOL
		. '</style>' . PHP_EOL
		. '<!-- Local End ID:' . $post_ids[0] . ' Author:1 -->' . PHP_EOL
	);
	aslc_bundle_test( true === $rebuild->invoke( $admin ), 'The changed CSS bundle did not rebuild successfully.' );
	$changed_frontend = aslc_get_css_bundle_entry( 'frontend' );
	aslc_bundle_test( $old_file !== $changed_frontend['file'], 'A content change did not produce a cache-safe bundle filename.' );
	aslc_bundle_test( ! file_exists( trailingslashit( CCJ_UPLOAD_DIR ) . $old_file ), 'The superseded frontend bundle was not removed.' );

	$old_schema_manifest = get_option( 'aslc_css_bundles', array() );
	$old_schema_manifest['frontend']['schema'] = ASLC_CSS_BUNDLE_SCHEMA - 1;
	update_option( 'aslc_css_bundles', $old_schema_manifest, false );
	aslc_bundle_test( false === aslc_css_bundle_is_available( 'frontend' ), 'An obsolete bundle schema was accepted.' );
	aslc_ensure_current_css_bundles();
	aslc_bundle_test( aslc_css_bundle_is_available( 'frontend' ), 'The obsolete bundle was not refreshed on the next request.' );

	$before_update = aslc_get_css_bundle_entry( 'frontend' );
	file_put_contents(
		trailingslashit( CCJ_UPLOAD_DIR ) . $post_ids[0] . '.css',
		'<style>.aslc-bundle-header-late { color: teal; }</style>'
	);
	aslc_refresh_css_bundles_after_plugin_update(
		null,
		array(
			'action' => 'update',
			'type'   => 'plugin',
			'plugin' => plugin_basename( ASLC_PLUGIN_FILE ),
		)
	);
	$after_update = aslc_get_css_bundle_entry( 'frontend' );
	aslc_bundle_test( $before_update['file'] !== $after_update['file'], 'A plugin update did not refresh the generated CSS bundle.' );

	$unsafe_post_id      = $post_ids[0];
	$unsafe_file_path    = trailingslashit( CCJ_UPLOAD_DIR ) . $unsafe_post_id . '.css';
	$unsafe_options      = get_post_meta( $unsafe_post_id, 'options', true );
	$unsafe_search_tree  = get_option( 'custom-css-js-tree', array() );
	$unsafe_js_options   = $unsafe_options;
	$unsafe_js_options['language'] = 'js';
	update_post_meta( $unsafe_post_id, 'options', $unsafe_js_options );
	file_put_contents(
		$unsafe_file_path,
		'<style>.aslc-unclosed-rule {' . PHP_EOL . 'color: red;' . PHP_EOL . '</style>'
	);
	$unsafe_findings = aslc_scan_css_records( $unsafe_search_tree );
	aslc_bundle_test(
		in_array( 'unclosed_brace', array_column( $unsafe_findings, 'code' ), true ),
		'An active search-tree CSS file was skipped when its post metadata did not identify it as CSS.'
	);
	aslc_bundle_test( false === aslc_rebuild_css_bundles( $unsafe_search_tree ), 'An unsafe CSS bundle was published.' );
	$unsafe_error = get_option( 'aslc_css_bundle_error', array() );
	aslc_bundle_test( ! empty( $unsafe_error['unsafe'] ), 'The unsafe CSS bundle error was not recorded.' );
	aslc_bundle_test( false === aslc_get_css_bundle_entry( 'frontend' ), 'An unsafe last-known bundle remained active.' );

	update_post_meta( $unsafe_post_id, 'options', $unsafe_options );
	file_put_contents(
		$unsafe_file_path,
		'<style>.aslc-bundle-header-late { color: teal; }</style>'
	);
	aslc_bundle_test( true === aslc_rebuild_css_bundles( $unsafe_search_tree ), 'The bundle did not recover after unsafe CSS was fixed.' );
	$after_update = aslc_get_css_bundle_entry( 'frontend' );
	aslc_bundle_test( is_array( $after_update ), 'Generated-file delivery did not recover after unsafe CSS was fixed.' );

	$tree_with_error = get_option( 'custom-css-js-tree', array() );
	$tree_with_error['frontend-css-header-internal'][2][] = 'not-a-css-post.css';
	aslc_bundle_test( false === aslc_rebuild_css_bundles( $tree_with_error ), 'An invalid bundle source did not fail safely.' );
	$preserved = aslc_get_css_bundle_entry( 'frontend' );
	aslc_bundle_test( $after_update === $preserved, 'A failed rebuild replaced the last-known-good frontend bundle.' );
	aslc_bundle_test( false !== get_option( 'aslc_css_bundle_error', false ), 'A failed rebuild did not record an admin error.' );

	aslc_bundle_test( true === $rebuild->invoke( $admin ), 'The bundle did not recover after a failed rebuild.' );
	aslc_bundle_test( false === get_option( 'aslc_css_bundle_error', false ), 'The bundle error was not cleared after recovery.' );

	echo "Generated CSS bundle integration test passed.\n";
} finally {
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	$rebuild->invoke( $admin );
	if ( false === $original_delivery_mode ) {
		delete_option( ASLC_CSS_DELIVERY_OPTION );
	} else {
		update_option( ASLC_CSS_DELIVERY_OPTION, $original_delivery_mode, false );
	}
	if ( false === $original_priority_order ) {
		delete_option( ASLC_CSS_ORDER_OPTION );
	} else {
		update_option( ASLC_CSS_ORDER_OPTION, $original_priority_order, false );
	}
	if ( false === $original_scan_results ) {
		delete_option( ASLC_CSS_SCAN_OPTION );
	} else {
		update_option( ASLC_CSS_SCAN_OPTION, $original_scan_results, false );
	}
	wp_dequeue_style( 'aslc-css-frontend' );
	wp_dequeue_style( 'aslc-editor-css-bundle' );
}
