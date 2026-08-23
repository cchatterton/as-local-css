<?php
/**
 * Block editor content assets for AS Local CSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mirror styles registered for the front end into the editor iframe.
 *
 * Some themes and plugins expose design tokens and supporting CSS only through
 * wp_enqueue_scripts. WordPress does not automatically include those styles in
 * the editor canvas, even when Local CSS depends on them.
 *
 * This runs only while WordPress is collecting assets for the iframe. Script
 * queues are restored immediately because this plugin mirrors CSS, not
 * front-end JavaScript.
 */
function aslc_enqueue_registered_frontend_styles_in_block_editor() {
	if ( ! is_admin()
		|| false === has_filter( 'should_load_block_editor_scripts_and_styles', '__return_false' )
		|| doing_action( 'wp_enqueue_scripts' ) ) {
		return;
	}

	$wp_scripts        = wp_scripts();
	$script_queue      = $wp_scripts->queue;
	$script_to_do      = $wp_scripts->to_do;
	$script_done       = $wp_scripts->done;
	$script_groups     = $wp_scripts->groups;
	$script_in_footer = $wp_scripts->in_footer;

	try {
		do_action( 'wp_enqueue_scripts' );
	} finally {
		$wp_scripts->queue     = $script_queue;
		$wp_scripts->to_do     = $script_to_do;
		$wp_scripts->done      = $script_done;
		$wp_scripts->groups    = $script_groups;
		$wp_scripts->in_footer = $script_in_footer;
	}
}
add_action( 'enqueue_block_assets', 'aslc_enqueue_registered_frontend_styles_in_block_editor', 1 );

/**
 * Enqueue active front-end CSS inside the block editor content canvas.
 *
 * Raw stylesheet loading preserves front-end selectors such as :root, html,
 * body, and body classes. Passing this CSS through block editor settings would
 * scope and rewrite those selectors, changing their meaning.
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
 * Add front-end body classes to the block editor content canvas.
 */
function aslc_enqueue_editor_body_classes() {
	global $post;

	if ( ! $post instanceof WP_Post || 'custom-css-js' === $post->post_type ) {
		return;
	}

	$body_classes = aslc_get_frontend_body_classes( $post );
	if ( empty( $body_classes ) ) {
		return;
	}

	$handle = 'aslc-editor-body-classes';
	wp_enqueue_script(
		$handle,
		ASLC_PLUGIN_URL . 'assets/aslc_editor_body_classes.js',
		array(),
		ASLC_VERSION,
		true
	);

	wp_add_inline_script(
		$handle,
		'window.ASLC = window.ASLC || {}; window.ASLC.editorBodyClasses = ' . wp_json_encode( $body_classes ) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'aslc_enqueue_editor_body_classes' );

/**
 * Build the front-end body classes WordPress would add for an edited post.
 *
 * @param WP_Post $post Post being edited.
 * @return string[]
 */
function aslc_get_frontend_body_classes( $post ) {
	$post_id   = (int) $post->ID;
	$post_type = sanitize_html_class( $post->post_type, (string) $post_id );
	$classes   = array( 'wp-singular' );

	$template_slug = get_page_template_slug( $post_id );
	if ( '' !== $template_slug ) {
		$classes[]      = $post_type . '-template';
		$template_parts = explode( '/', $template_slug );
		foreach ( $template_parts as $template_part ) {
			$classes[] = $post_type . '-template-' . sanitize_html_class(
				str_replace( array( '.', '/' ), '-', basename( $template_part, '.php' ) )
			);
		}
		$classes[] = $post_type . '-template-' . sanitize_html_class( str_replace( '.', '-', $template_slug ) );
	} else {
		$classes[] = $post_type . '-template-default';
	}

	if ( 'attachment' === $post->post_type ) {
		$mime_type     = get_post_mime_type( $post_id );
		$mime_prefixes = array( 'application/', 'image/', 'text/', 'audio/', 'video/', 'music/' );
		$classes[]     = 'attachment';
		$classes[]     = 'attachmentid-' . $post_id;
		$classes[]     = 'attachment-' . sanitize_html_class( str_replace( $mime_prefixes, '', $mime_type ) );
	} elseif ( 'page' === $post->post_type ) {
		$classes[] = 'page';
		$classes[] = 'page-id-' . $post_id;

		if ( get_pages( array( 'parent' => $post_id, 'number' => 1 ) ) ) {
			$classes[] = 'page-parent';
		}
		if ( $post->post_parent ) {
			$classes[] = 'page-child';
			$classes[] = 'parent-pageid-' . (int) $post->post_parent;
		}
	} else {
		$classes[] = 'single';
		$classes[] = 'single-' . $post_type;
		$classes[] = 'postid-' . $post_id;

		if ( post_type_supports( $post->post_type, 'post-formats' ) ) {
			$post_format = get_post_format( $post_id );
			$classes[]   = $post_format && ! is_wp_error( $post_format )
				? 'single-format-' . sanitize_html_class( $post_format )
				: 'single-format-standard';
		}
	}

	if ( (int) get_option( 'page_on_front' ) === $post_id ) {
		$classes[] = 'home';
	}
	if ( (int) get_option( 'page_for_posts' ) === $post_id ) {
		$classes[] = 'blog';
	}
	if ( (int) get_option( 'wp_page_for_privacy_policy' ) === $post_id ) {
		$classes[] = 'privacy-policy';
	}
	if ( is_rtl() ) {
		$classes[] = 'rtl';
	}
	if ( is_user_logged_in() ) {
		$classes[] = 'logged-in';
	}
	if ( is_admin_bar_showing() ) {
		$classes[] = 'admin-bar';
		$classes[] = 'no-customize-support';
	}
	if ( current_theme_supports( 'custom-background' )
		&& ( get_background_color() !== get_theme_support( 'custom-background', 'default-color' ) || get_background_image() ) ) {
		$classes[] = 'custom-background';
	}
	if ( has_custom_logo() ) {
		$classes[] = 'wp-custom-logo';
	}
	if ( current_theme_supports( 'responsive-embeds' ) ) {
		$classes[] = 'wp-embed-responsive';
	}

	$classes[] = 'wp-theme-' . sanitize_html_class( get_template() );
	if ( is_child_theme() ) {
		$classes[] = 'wp-child-theme-' . sanitize_html_class( get_stylesheet() );
	}

	$classes = apply_filters( 'body_class', $classes, array() );
	if ( ! is_array( $classes ) ) {
		return array();
	}

	$safe_classes = array();
	foreach ( $classes as $class ) {
		foreach ( preg_split( '/\s+/', (string) $class ) as $class_part ) {
			$class_part = sanitize_html_class( $class_part );
			if ( '' !== $class_part ) {
				$safe_classes[] = $class_part;
			}
		}
	}

	return array_values( array_unique( $safe_classes ) );
}

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

	$css = aslc_read_generated_css( $safe_filename );
	if ( '' === $css ) {
		return;
	}

	wp_register_style( $handle, false, array(), ASLC_VERSION );
	wp_enqueue_style( $handle );
	wp_add_inline_style( $handle, $css );
}

/**
 * Read CSS from a generated file and remove any internal HTML wrapper.
 *
 * @param string $filename Safe generated CSS file name.
 * @return string
 */
function aslc_read_generated_css( $filename ) {
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
