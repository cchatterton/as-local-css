<?php
/**
 * Generated CSS bundles for AS Local CSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASLC_CSS_BUNDLE_SCHEMA', 5 );
define( 'ASLC_CSS_DELIVERY_OPTION', 'aslc_css_delivery_mode' );
define( 'ASLC_CSS_ORDER_OPTION', 'aslc_css_priority_order' );
define( 'ASLC_CSS_BREAKPOINTS_OPTION', 'aslc_css_breakpoint_tokens' );

/**
 * Return the selected CSS delivery mode.
 *
 * @return string Either file or inline.
 */
function aslc_get_css_delivery_mode() {
	$mode = get_option( ASLC_CSS_DELIVERY_OPTION, 'file' );
	return 'inline' === $mode ? 'inline' : 'file';
}

/**
 * Determine whether generated-file CSS delivery is selected.
 *
 * @return bool
 */
function aslc_use_css_file_delivery() {
	return 'file' === aslc_get_css_delivery_mode();
}

/**
 * Return the selected top-to-bottom CSS priority order.
 *
 * @return string Either 1-to-5 or 5-to-1.
 */
function aslc_get_css_priority_order() {
	$order = get_option( ASLC_CSS_ORDER_OPTION, '5-to-1' );
	return '1-to-5' === $order ? '1-to-5' : '5-to-1';
}

/**
 * Return the default breakpoint-token replacements.
 *
 * The existing AlphaSys CSS convention uses a three-point responsive scale.
 * Sites can replace, remove, or extend these rows on the CSS Breakpoints page.
 *
 * @return array<string,string> Token to CSS length.
 */
function aslc_get_default_css_breakpoint_tokens() {
	return array(
		'%SM%' => '48rem',
		'%MD%' => '68rem',
		'%LG%' => '75rem',
	);
}

/**
 * Normalise submitted breakpoint repeater rows.
 *
 * @param array $rows Repeater rows or an existing token-to-value map.
 * @return array<string,string> Valid unique token replacements.
 */
function aslc_sanitize_css_breakpoint_tokens( $rows ) {
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$tokens = array();
	foreach ( $rows as $key => $row ) {
		if ( is_array( $row ) ) {
			$name  = isset( $row['token'] ) ? $row['token'] : '';
			$value = isset( $row['value'] ) ? $row['value'] : '';
		} else {
			$name  = $key;
			$value = $row;
		}

		$name  = strtoupper( trim( (string) $name ) );
		$value = strtolower( preg_replace( '/\s+/', '', trim( (string) $value ) ) );
		$name  = trim( $name, '%' );

		if ( ! preg_match( '/^[A-Z][A-Z0-9_-]{0,31}$/', $name ) ) {
			continue;
		}
		if ( ! preg_match( '/^(?:0|(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em|ch|ex|vw|vh|vmin|vmax|cm|mm|in|pt|pc))$/', $value ) ) {
			continue;
		}

		$tokens[ '%' . $name . '%' ] = $value;
	}

	return $tokens;
}

/**
 * Return configured breakpoint-token replacements.
 *
 * @return array<string,string> Token to CSS length.
 */
function aslc_get_css_breakpoint_tokens() {
	$saved = get_option( ASLC_CSS_BREAKPOINTS_OPTION, false );
	if ( false === $saved ) {
		return aslc_get_default_css_breakpoint_tokens();
	}

	return aslc_sanitize_css_breakpoint_tokens( $saved );
}

/**
 * Resolve configured breakpoint tokens in compiled CSS without changing the
 * authored snippet files used by legacy inline delivery.
 *
 * @param string $css Compiled CSS.
 * @return string CSS with configured breakpoint tokens resolved once.
 */
function aslc_replace_css_breakpoint_tokens( $css ) {
	$tokens = aslc_get_css_breakpoint_tokens();
	return empty( $tokens ) ? $css : strtr( $css, $tokens );
}

/**
 * Sort priority groups into the selected top-to-bottom output order.
 *
 * The labels use the common 1-5 range, while the numeric sort also handles
 * legacy records using priorities 6-10.
 *
 * @param array $priority_groups Priority-keyed groups to sort in place.
 */
function aslc_sort_css_priority_groups( &$priority_groups ) {
	if ( '1-to-5' === aslc_get_css_priority_order() ) {
		ksort( $priority_groups, SORT_NUMERIC );
	} else {
		krsort( $priority_groups, SORT_NUMERIC );
	}
}

/**
 * Return the supported CSS delivery contexts.
 *
 * @return string[]
 */
function aslc_css_bundle_sides() {
	return array( 'frontend', 'admin', 'login' );
}

/**
 * Refresh generated CSS bundles after this plugin is updated.
 *
 * @param WP_Upgrader $upgrader WordPress upgrader instance.
 * @param array       $options  Completed upgrade details.
 */
function aslc_refresh_css_bundles_after_plugin_update( $upgrader, $options ) {
	unset( $upgrader );

	if ( empty( $options['action'] )
		|| 'update' !== $options['action']
		|| empty( $options['type'] )
		|| 'plugin' !== $options['type'] ) {
		return;
	}

	$updated_plugins = array();
	if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
		$updated_plugins = $options['plugins'];
	} elseif ( ! empty( $options['plugin'] ) && is_string( $options['plugin'] ) ) {
		$updated_plugins = array( $options['plugin'] );
	}

	if ( ! in_array( plugin_basename( ASLC_PLUGIN_FILE ), $updated_plugins, true ) ) {
		return;
	}

	aslc_rebuild_css_bundles();
}
add_action( 'upgrader_process_complete', 'aslc_refresh_css_bundles_after_plugin_update', 10, 2 );

/**
 * Rebuild missing or obsolete bundles on the first request after an update.
 *
 * The manifest schema is bundle validation, not a one-time migration flag. It
 * ensures an update from a version that did not yet have the upgrader hook is
 * refreshed without keeping separate migration state.
 */
function aslc_ensure_current_css_bundles() {
	$bundle_error = get_option( 'aslc_css_bundle_error', array() );
	if ( ! empty( $bundle_error['unsafe'] ) ) {
		return;
	}

	$search_tree = get_option( 'custom-css-js-tree', array() );
	if ( ! is_array( $search_tree ) ) {
		return;
	}

	$required_sides = array();
	foreach ( array_keys( $search_tree ) as $branch ) {
		if ( preg_match( '/^(frontend|admin|login)-css-/', (string) $branch, $matches ) ) {
			$required_sides[ $matches[1] ] = true;
		}
	}

	foreach ( array_keys( $required_sides ) as $side ) {
		if ( ! aslc_css_bundle_is_available( $side ) ) {
			aslc_rebuild_css_bundles( $search_tree );
			return;
		}
	}
}
add_action( 'init', 'aslc_ensure_current_css_bundles', 20 );

/**
 * Print one generated CSS bundle at the legacy inline-CSS hook position.
 *
 * Enqueued styles are normally printed by WordPress at wp_head priority 8.
 * Legacy Local CSS is printed at priority 10, after that stylesheet pass.
 * Printing the generated link at the same position preserves that cascade.
 *
 * @param string $side Frontend, admin, or login.
 * @return bool Whether a valid bundle link was printed.
 */
function aslc_print_css_bundle_link( $side ) {
	if ( ! aslc_use_css_file_delivery() ) {
		return false;
	}

	$entry = aslc_get_css_bundle_entry( $side );
	if ( false === $entry ) {
		return false;
	}

	$handle     = 'aslc-css-' . $side;
	$bundle_url = trailingslashit( CCJ_UPLOAD_URL ) . rawurlencode( $entry['file'] );
	printf(
		'<link rel="stylesheet" id="%1$s-css" href="%2$s" media="all">' . "\n",
		esc_attr( $handle ),
		esc_url( $bundle_url )
	);

	return true;
}

/** Print the front-end bundle where legacy inline CSS is printed. */
function aslc_print_frontend_css_bundle() {
	aslc_print_css_bundle_link( 'frontend' );
}
add_action( 'wp_head', 'aslc_print_frontend_css_bundle', 10 );

/** Print the WordPress admin bundle where legacy inline CSS is printed. */
function aslc_print_admin_css_bundle() {
	aslc_print_css_bundle_link( 'admin' );
}
add_action( 'admin_head', 'aslc_print_admin_css_bundle', 10 );

/** Print the login bundle where legacy inline CSS is printed. */
function aslc_print_login_css_bundle() {
	aslc_print_css_bundle_link( 'login' );
}
add_action( 'login_head', 'aslc_print_login_css_bundle', 10 );

/**
 * Use double-quoted attributes for AS Local CSS bundle link elements.
 *
 * @param string $html   Rendered stylesheet link element.
 * @param string $handle Registered WordPress style handle.
 * @return string
 */
function aslc_double_quote_bundle_link_attributes( $html, $handle ) {
	if ( 0 !== strpos( $handle, 'aslc-css-' ) && 'aslc-editor-css-bundle' !== $handle ) {
		return $html;
	}

	$converted = preg_replace( "/='([^']*)'/", '="$1"', $html );
	return is_string( $converted ) ? $converted : $html;
}
add_filter( 'style_loader_tag', 'aslc_double_quote_bundle_link_attributes', PHP_INT_MAX, 2 );

/**
 * Enqueue one generated CSS bundle.
 *
 * @param string $side   Frontend, admin, or login.
 * @param string $handle Optional style handle override.
 * @return bool Whether a valid bundle was enqueued.
 */
function aslc_enqueue_css_bundle( $side, $handle = '' ) {
	if ( ! aslc_use_css_file_delivery() ) {
		return false;
	}

	$entry = aslc_get_css_bundle_entry( $side );
	if ( false === $entry ) {
		return false;
	}

	if ( '' === $handle ) {
		$handle = 'aslc-css-' . $side;
	}

	$bundle_url = trailingslashit( CCJ_UPLOAD_URL ) . rawurlencode( $entry['file'] );
	wp_enqueue_style( $handle, esc_url_raw( $bundle_url ), array(), $entry['hash'], 'all' );

	return true;
}

/**
 * Determine whether a valid bundle exists for a delivery context.
 *
 * @param string $side Frontend, admin, or login.
 * @return bool
 */
function aslc_css_bundle_is_available( $side ) {
	return false !== aslc_get_css_bundle_entry( $side );
}

/**
 * Return and validate one bundle manifest entry.
 *
 * @param string $side Frontend, admin, or login.
 * @return array|false
 */
function aslc_get_css_bundle_entry( $side ) {
	if ( ! in_array( $side, aslc_css_bundle_sides(), true ) || ! defined( 'CCJ_UPLOAD_DIR' ) ) {
		return false;
	}
	$bundle_error = get_option( 'aslc_css_bundle_error', array() );
	if ( ! empty( $bundle_error['unsafe'] ) ) {
		return false;
	}

	$manifest = get_option( 'aslc_css_bundles', array() );
	if ( ! is_array( $manifest ) || empty( $manifest[ $side ] ) || ! is_array( $manifest[ $side ] ) ) {
		return false;
	}

	$entry = $manifest[ $side ];
	if ( empty( $entry['file'] )
		|| empty( $entry['hash'] )
		|| ASLC_CSS_BUNDLE_SCHEMA !== (int) ( $entry['schema'] ?? 0 )
		|| ! preg_match( '/^as-local-css-' . preg_quote( $side, '/' ) . '-[a-f0-9]{16}\.css$/', $entry['file'] )
		|| ! preg_match( '/^[a-f0-9]{64}$/', $entry['hash'] ) ) {
		return false;
	}

	$upload_directory = realpath( CCJ_UPLOAD_DIR );
	$bundle_file      = realpath( trailingslashit( CCJ_UPLOAD_DIR ) . $entry['file'] );
	if ( false === $upload_directory || false === $bundle_file || ! is_file( $bundle_file ) || ! is_readable( $bundle_file ) ) {
		return false;
	}

	if ( 0 !== strpos( $bundle_file, trailingslashit( $upload_directory ) ) ) {
		return false;
	}

	return array(
		'file' => $entry['file'],
		'hash' => $entry['hash'],
	);
}

/**
 * Rebuild all generated CSS bundles from the active snippet search tree.
 *
 * The existing manifest entry remains active when a replacement cannot be
 * written, providing a last-known-good bundle instead of a partial stylesheet.
 *
 * @param array|null $search_tree Active custom-code search tree.
 * @return bool Whether every requested bundle was rebuilt successfully.
 */
function aslc_rebuild_css_bundles( $search_tree = null ) {
	if ( null === $search_tree ) {
		$search_tree = get_option( 'custom-css-js-tree', array() );
	}
	if ( ! is_array( $search_tree ) ) {
		$search_tree = array();
	}

	if ( function_exists( 'aslc_scan_css_records' ) ) {
		$findings          = aslc_scan_css_records( $search_tree );
		$blocking_findings = aslc_get_bundle_blocking_css_findings( $findings );
		if ( ! empty( $blocking_findings ) ) {
			$first = $blocking_findings[0];
			update_option(
				'aslc_css_bundle_error',
				array(
					'message' => sprintf(
						/* translators: 1: CSS record ID, 2: line number, 3: error description. */
						__( 'CSS record %1$d, line %2$d: %3$s Generated-file delivery has been disabled until the record is fixed.', 'custom-css-js' ),
						(int) $first['post_id'],
						(int) $first['line'],
						$first['message']
					),
					'time'    => time(),
					'unsafe'  => true,
				),
				false
			);
			return false;
		}
	}

	$manifest = get_option( 'aslc_css_bundles', array() );
	if ( ! is_array( $manifest ) ) {
		$manifest = array();
	}

	$errors  = array();
	$cleanup = array();

	foreach ( aslc_css_bundle_sides() as $side ) {
		$compiled = aslc_compile_css_bundle( $search_tree, $side );
		if ( is_wp_error( $compiled ) ) {
			$errors[] = $compiled->get_error_message();
			continue;
		}

		if ( '' === $compiled ) {
			unset( $manifest[ $side ] );
			$cleanup[ $side ] = '';
			continue;
		}

		$entry = aslc_write_css_bundle( $side, $compiled );
		if ( is_wp_error( $entry ) ) {
			$errors[] = $entry->get_error_message();
			continue;
		}

		$manifest[ $side ] = $entry;
		$cleanup[ $side ]  = $entry['file'];
	}

	update_option( 'aslc_css_bundles', $manifest, false );

	foreach ( $cleanup as $side => $keep_file ) {
		aslc_cleanup_css_bundles( $side, $keep_file );
	}

	if ( ! empty( $errors ) ) {
		update_option(
			'aslc_css_bundle_error',
			array(
				'message' => implode( ' ', array_unique( $errors ) ),
				'time'    => time(),
			),
			false
		);
		return false;
	}

	delete_option( 'aslc_css_bundle_error' );
	return true;
}

/**
 * Compile active CSS for one delivery context in global priority order.
 *
 * @param array  $search_tree Active custom-code search tree.
 * @param string $side        Frontend, admin, or login.
 * @return string|WP_Error
 */
function aslc_compile_css_bundle( array $search_tree, $side ) {
	if ( ! in_array( $side, aslc_css_bundle_sides(), true ) ) {
		return new WP_Error( 'aslc_invalid_css_bundle_side', __( 'AS Local CSS received an invalid CSS bundle context.', 'custom-css-js' ) );
	}

	$settings        = get_option( 'ccj_settings', array() );
	$remove_comments = is_array( $settings ) && ! empty( $settings['remove_comments'] );
	$parts           = array();
	$priority_groups = array();

	foreach ( array( 'header', 'footer' ) as $location ) {
		foreach ( array( 'internal', 'external' ) as $linking ) {
			$branch = $side . '-css-' . $location . '-' . $linking;
			if ( empty( $search_tree[ $branch ] ) || ! is_array( $search_tree[ $branch ] ) ) {
				continue;
			}

			foreach ( $search_tree[ $branch ] as $priority => $filenames ) {
				if ( ! is_array( $filenames ) ) {
					continue;
				}

				foreach ( $filenames as $filename ) {
					$priority_groups[ (int) $priority ][] = array(
						'filename' => $filename,
						'location' => $location,
					);
				}
			}
		}
	}

	aslc_sort_css_priority_groups( $priority_groups );
	foreach ( $priority_groups as $priority => $items ) {
		foreach ( $items as $item ) {
			$source = aslc_get_css_source_from_tree_filename( $item['filename'] );
			if ( is_wp_error( $source ) ) {
				return $source;
			}
			if ( '' === trim( $source['css'] ) ) {
				continue;
			}

			if ( ! $remove_comments ) {
				$parts[] = sprintf(
					'/* Local Start ID:%d Author:%d Location:%s Priority:%d */',
					$source['id'],
					$source['author'],
					$item['location'],
					(int) $priority
				);
			}
			$parts[] = rtrim( $source['css'] );
			if ( ! $remove_comments ) {
				$parts[] = sprintf(
					'/* Local End ID:%d Author:%d */',
					$source['id'],
					$source['author']
				);
			}
		}
	}

	if ( empty( $parts ) ) {
		return '';
	}

	if ( ! $remove_comments ) {
		array_unshift( $parts, '/* AS Local CSS generated bundle. Do not edit. */' );
	}

	return aslc_replace_css_breakpoint_tokens( implode( PHP_EOL . PHP_EOL, $parts ) . PHP_EOL );
}

/**
 * Resolve one search-tree filename to its generated CSS file.
 *
 * @param string $filename Search-tree filename, optionally with a query string.
 * @return array|WP_Error
 */
function aslc_get_css_source_from_tree_filename( $filename ) {
	$file_name_without_query = strtok( (string) $filename, '?' );
	$safe_filename           = basename( (string) $file_name_without_query );
	if ( ! preg_match( '/^(\d+)\.css$/', $safe_filename, $matches ) ) {
		return new WP_Error( 'aslc_invalid_css_source', __( 'AS Local CSS could not identify a CSS snippet while building its bundle.', 'custom-css-js' ) );
	}

	$post = get_post( (int) $matches[1] );
	if ( ! $post instanceof WP_Post || 'custom-css-js' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_Error( 'aslc_missing_css_source', __( 'AS Local CSS could not read an active CSS snippet while building its bundle.', 'custom-css-js' ) );
	}

	$css = aslc_get_generated_css_contents( $safe_filename );
	if ( is_wp_error( $css ) ) {
		return $css;
	}

	return array(
		'id'     => (int) $post->ID,
		'author' => (int) $post->post_author,
		'css'    => $css,
	);
}

/**
 * Read CSS from a generated snippet file and remove its inline HTML wrapper.
 *
 * @param string $filename Safe generated CSS filename.
 * @return string|WP_Error
 */
function aslc_get_generated_css_contents( $filename ) {
	$safe_filename = basename( (string) $filename );
	if ( $safe_filename !== $filename || ! preg_match( '/^\d+\.css$/', $safe_filename ) ) {
		return new WP_Error( 'aslc_invalid_generated_css_file', __( 'AS Local CSS could not identify a generated CSS snippet file.', 'custom-css-js' ) );
	}

	$upload_directory = realpath( CCJ_UPLOAD_DIR );
	$css_file         = realpath( trailingslashit( CCJ_UPLOAD_DIR ) . $safe_filename );
	if ( false === $upload_directory || false === $css_file || ! is_file( $css_file ) || ! is_readable( $css_file ) ) {
		return new WP_Error(
			'aslc_missing_generated_css_file',
			sprintf(
				/* translators: %s: generated CSS filename. */
				__( 'AS Local CSS could not read the generated snippet file %s while building its bundle.', 'custom-css-js' ),
				$safe_filename
			)
		);
	}

	if ( 0 !== strpos( $css_file, trailingslashit( $upload_directory ) ) ) {
		return new WP_Error( 'aslc_invalid_generated_css_path', __( 'AS Local CSS rejected a generated CSS path outside its upload directory.', 'custom-css-js' ) );
	}

	$file_contents = file_get_contents( $css_file );
	if ( false === $file_contents ) {
		return new WP_Error(
			'aslc_generated_css_read_failed',
			sprintf(
				/* translators: %s: generated CSS filename. */
				__( 'AS Local CSS could not read the generated snippet file %s while building its bundle.', 'custom-css-js' ),
				$safe_filename
			)
		);
	}

	if ( preg_match( '/<style\b[^>]*>([\s\S]*?)<\/style>/i', $file_contents, $matches ) ) {
		return trim( $matches[1] );
	}

	$css = preg_replace( '/<!--\s*Local (?:Start|End)[\s\S]*?-->/', '', $file_contents );
	return trim( is_string( $css ) ? $css : $file_contents );
}

/**
 * Atomically write one content-addressed CSS bundle.
 *
 * @param string $side Frontend, admin, or login.
 * @param string $css  Compiled CSS.
 * @return array|WP_Error
 */
function aslc_write_css_bundle( $side, $css ) {
	if ( ! file_exists( CCJ_UPLOAD_DIR ) && ! wp_mkdir_p( CCJ_UPLOAD_DIR ) ) {
		return new WP_Error( 'aslc_css_bundle_directory', __( 'AS Local CSS could not create its generated CSS directory.', 'custom-css-js' ) );
	}
	if ( ! wp_is_writable( CCJ_UPLOAD_DIR ) ) {
		return new WP_Error( 'aslc_css_bundle_not_writable', __( 'AS Local CSS could not write its generated CSS bundle.', 'custom-css-js' ) );
	}

	$hash      = hash( 'sha256', $css );
	$file_name = 'as-local-css-' . $side . '-' . substr( $hash, 0, 16 ) . '.css';
	$file_path = trailingslashit( CCJ_UPLOAD_DIR ) . $file_name;

	if ( ! is_file( $file_path ) ) {
		$temp_file = tempnam( CCJ_UPLOAD_DIR, '.aslc-' );
		if ( false === $temp_file || false === file_put_contents( $temp_file, $css, LOCK_EX ) ) {
			if ( is_string( $temp_file ) && is_file( $temp_file ) ) {
				unlink( $temp_file );
			}
			return new WP_Error( 'aslc_css_bundle_write_failed', __( 'AS Local CSS could not write its generated CSS bundle.', 'custom-css-js' ) );
		}

		if ( ! rename( $temp_file, $file_path ) ) {
			unlink( $temp_file );
			return new WP_Error( 'aslc_css_bundle_move_failed', __( 'AS Local CSS could not publish its generated CSS bundle.', 'custom-css-js' ) );
		}

		if ( defined( 'FS_CHMOD_FILE' ) ) {
			chmod( $file_path, FS_CHMOD_FILE );
		}
	}

	return array(
		'file'   => $file_name,
		'hash'   => $hash,
		'schema' => ASLC_CSS_BUNDLE_SCHEMA,
	);
}

/**
 * Remove superseded generated bundles for one delivery context.
 *
 * @param string $side      Frontend, admin, or login.
 * @param string $keep_file Current bundle filename, or empty to remove all.
 */
function aslc_cleanup_css_bundles( $side, $keep_file ) {
	if ( ! in_array( $side, aslc_css_bundle_sides(), true ) || ! is_dir( CCJ_UPLOAD_DIR ) ) {
		return;
	}

	$files = glob( trailingslashit( CCJ_UPLOAD_DIR ) . 'as-local-css-' . $side . '-*.css' );
	if ( false === $files ) {
		return;
	}

	foreach ( $files as $file ) {
		if ( basename( $file ) !== $keep_file && is_file( $file ) ) {
			unlink( $file );
		}
	}
}

/**
 * Show bundle-generation failures without removing the last working CSS.
 */
function aslc_css_bundle_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$error = get_option( 'aslc_css_bundle_error', array() );
	if ( empty( $error['message'] ) ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'AS Local CSS could not rebuild its generated stylesheet.', 'custom-css-js' ); ?></strong>
			<?php echo esc_html( $error['message'] ); ?>
			<?php esc_html_e( 'The last working bundle or inline fallback remains active.', 'custom-css-js' ); ?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'aslc_css_bundle_admin_notice' );
