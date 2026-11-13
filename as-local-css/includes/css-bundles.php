<?php
/**
 * Generated CSS bundles for AS Local CSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASLC_CSS_BUNDLE_SCHEMA', 2 );

/**
 * Return the supported CSS delivery contexts.
 *
 * @return string[]
 */
function aslc_css_bundle_sides() {
	return array( 'frontend', 'admin', 'login' );
}

/**
 * Enqueue the active front-end CSS bundle.
 */
function aslc_enqueue_frontend_css_bundle() {
	aslc_enqueue_css_bundle( 'frontend' );
}
add_action( 'wp_enqueue_scripts', 'aslc_enqueue_frontend_css_bundle', PHP_INT_MAX );

/**
 * Enqueue the active WordPress admin CSS bundle.
 */
function aslc_enqueue_admin_css_bundle() {
	aslc_enqueue_css_bundle( 'admin' );
}
add_action( 'admin_enqueue_scripts', 'aslc_enqueue_admin_css_bundle', PHP_INT_MAX );

/**
 * Enqueue the active login CSS bundle.
 */
function aslc_enqueue_login_css_bundle() {
	aslc_enqueue_css_bundle( 'login' );
}
add_action( 'login_enqueue_scripts', 'aslc_enqueue_login_css_bundle', PHP_INT_MAX );

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

	// Priority 1 is highest and must be emitted last so it wins the cascade.
	krsort( $priority_groups, SORT_NUMERIC );
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

	return implode( PHP_EOL . PHP_EOL, $parts ) . PHP_EOL;
}

/**
 * Resolve one search-tree filename to its authored CSS post content.
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

	return array(
		'id'     => (int) $post->ID,
		'author' => (int) $post->post_author,
		'css'    => (string) $post->post_content,
	);
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
