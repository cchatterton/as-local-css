<?php
/**
 * Block editor content assets for AS Local CSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue active front-end CSS inside the block editor content canvas.
 *
 * WordPress runs enqueue_block_assets while collecting assets for the editor
 * iframe. The admin check keeps these additional enqueues editor-only because
 * the plugin already renders the same files through wp_head and wp_footer on
 * the front end.
 */
function aslc_enqueue_frontend_css_in_block_editor() {
	if ( ! is_admin() || ! defined( 'CCJ_UPLOAD_DIR' ) || ! defined( 'CCJ_UPLOAD_URL' ) ) {
		return;
	}

	$search_tree = get_option( 'custom-css-js-tree', array() );
	if ( ! is_array( $search_tree ) || empty( $search_tree ) ) {
		return;
	}

	foreach ( array( 'header', 'footer' ) as $location ) {
		foreach ( $search_tree as $branch => $priority_groups ) {
			if ( ! aslc_is_frontend_css_branch( $branch, $location ) || ! is_array( $priority_groups ) ) {
				continue;
			}

			ksort( $priority_groups, SORT_NUMERIC );
			foreach ( $priority_groups as $filenames ) {
				if ( ! is_array( $filenames ) ) {
					continue;
				}

				foreach ( $filenames as $filename ) {
					aslc_enqueue_editor_css_file( $branch, $filename );
				}
			}
		}
	}
}
add_action( 'enqueue_block_assets', 'aslc_enqueue_frontend_css_in_block_editor' );

/**
 * Determine whether a search-tree branch contains front-end CSS.
 *
 * @param string $branch   Search-tree branch name.
 * @param string $location Expected header or footer location.
 * @return bool
 */
function aslc_is_frontend_css_branch( $branch, $location ) {
	return is_string( $branch )
		&& 0 === strpos( $branch, 'frontend-css-' . $location . '-' )
		&& ( '-internal' === substr( $branch, -9 ) || '-external' === substr( $branch, -9 ) );
}

/**
 * Enqueue one generated CSS file for the editor content canvas.
 *
 * @param string $branch   Search-tree branch name.
 * @param string $filename Generated file name, optionally with a query string.
 */
function aslc_enqueue_editor_css_file( $branch, $filename ) {
	if ( ! is_string( $filename ) || '' === $filename ) {
		return;
	}

	$file_name_without_query = strtok( $filename, '?' );
	$safe_filename           = basename( (string) $file_name_without_query );
	if ( ! preg_match( '/^[A-Za-z0-9._-]+\.css$/', $safe_filename ) ) {
		return;
	}

	$handle = 'aslc-editor-css-' . md5( $branch . '|' . $filename );
	if ( '-external' === substr( $branch, -9 ) ) {
		$css_url = trailingslashit( CCJ_UPLOAD_URL ) . $safe_filename;
		$query   = wp_parse_url( $filename, PHP_URL_QUERY );
		if ( is_string( $query ) && '' !== $query ) {
			$css_url .= '?' . $query;
		}

		wp_enqueue_style( $handle, esc_url_raw( $css_url ), array(), null );
		return;
	}

	$css = aslc_read_internal_css( $safe_filename );
	if ( '' === $css ) {
		return;
	}

	wp_register_style( $handle, false, array(), ASLC_VERSION );
	wp_enqueue_style( $handle );
	wp_add_inline_style( $handle, $css );
}

/**
 * Read CSS from an internal generated file and remove its HTML wrapper.
 *
 * @param string $filename Safe generated CSS file name.
 * @return string
 */
function aslc_read_internal_css( $filename ) {
	$upload_directory = realpath( CCJ_UPLOAD_DIR );
	$css_file         = realpath( trailingslashit( CCJ_UPLOAD_DIR ) . $filename );
	if ( false === $upload_directory || false === $css_file || ! is_file( $css_file ) || ! is_readable( $css_file ) ) {
		return '';
	}

	$upload_prefix = trailingslashit( $upload_directory );
	if ( 0 !== strpos( $css_file, $upload_prefix ) ) {
		return '';
	}

	$file_contents = file_get_contents( $css_file );
	if ( false === $file_contents ) {
		return '';
	}

	if ( preg_match( '/<style\b[^>]*>([\s\S]*?)<\/style>/i', $file_contents, $matches ) ) {
		return trim( $matches[1] );
	}

	return trim( preg_replace( '/<!--\s*Local (?:Start|End)[\s\S]*?-->/', '', $file_contents ) );
}
