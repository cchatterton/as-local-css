<?php
/**
 * Per-record responsive CSS sections for AS Local CSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASLC_CSS_BREAKPOINT_SECTIONS_META', 'aslc_css_breakpoint_sections' );
define( 'ASLC_CSS_TARGET_SCOPE_META', 'aslc_css_target_scope' );

/**
 * Normalise a one-line CSS selector used as a record target scope.
 *
 * Delimiters that could escape the generated parent rule are rejected. The
 * selector itself is otherwise preserved so class, ID, attribute, compound,
 * and selector-list targets remain available.
 *
 * @param mixed $scope Submitted target scope.
 * @return string Safe target selector, or an empty string.
 */
function aslc_sanitize_css_target_scope( $scope ) {
	if ( ! is_string( $scope ) ) {
		return '';
	}

	$scope = trim( preg_replace( '/\s+/', ' ', $scope ) );
	if ( '' === $scope
		|| strlen( $scope ) > 255
		|| preg_match( '/[{};]/', $scope )
		|| false !== strpos( $scope, '/*' )
		|| false !== strpos( $scope, '*/' )
		|| '@' === substr( $scope, 0, 1 ) ) {
		return '';
	}

	return $scope;
}

/** Return a CSS record's optional target scope. */
function aslc_get_css_target_scope( $post_id ) {
	return aslc_sanitize_css_target_scope( get_post_meta( $post_id, ASLC_CSS_TARGET_SCOPE_META, true ) );
}

/** Save a CSS record's optional target scope. */
function aslc_save_css_target_scope( $post_id, $scope ) {
	$scope = aslc_sanitize_css_target_scope( is_string( $scope ) ? wp_unslash( $scope ) : '' );
	update_post_meta( $post_id, ASLC_CSS_TARGET_SCOPE_META, $scope );
	return $scope;
}

/**
 * Return all saved breakpoint sections, including sections retained for rows
 * that are no longer configured.
 *
 * @param int $post_id CSS record ID.
 * @return array<string,string> Section CSS keyed by stable breakpoint ID.
 */
function aslc_get_css_breakpoint_sections( $post_id ) {
	$sections = get_post_meta( $post_id, ASLC_CSS_BREAKPOINT_SECTIONS_META, true );
	if ( ! is_array( $sections ) ) {
		return array();
	}

	$clean = array();
	foreach ( $sections as $id => $css ) {
		$id = sanitize_key( $id );
		if ( '' !== $id && is_string( $css ) ) {
			$clean[ $id ] = $css;
		}
	}

	return $clean;
}

/**
 * Save submitted active sections without discarding CSS belonging to a
 * breakpoint row that has since been removed.
 *
 * @param int   $post_id  CSS record ID.
 * @param mixed $submitted Submitted section map.
 * @return array<string,string> Saved section map.
 */
function aslc_save_css_breakpoint_sections( $post_id, $submitted ) {
	$sections = aslc_get_css_breakpoint_sections( $post_id );
	$active   = array();
	foreach ( aslc_get_css_breakpoint_rows() as $row ) {
		$active[ $row['id'] ] = true;
	}

	$submitted = is_array( $submitted ) ? $submitted : array();
	foreach ( $active as $id => $unused ) {
		unset( $unused );
		$sections[ $id ] = isset( $submitted[ $id ] ) && is_string( $submitted[ $id ] )
			? wp_unslash( $submitted[ $id ] )
			: '';
	}

	update_post_meta( $post_id, ASLC_CSS_BREAKPOINT_SECTIONS_META, $sections );
	return $sections;
}

/**
 * Assemble a record's base CSS and non-empty responsive sections.
 *
 * @param string     $base_css Base CSS (no media query).
 * @param array      $sections Section CSS keyed by stable breakpoint ID.
 * @param array|null $rows     Optional breakpoint rows for tests/callers.
 * @param string     $target   Optional parent selector applied per section.
 * @return string Assembled CSS.
 */
function aslc_assemble_css_sections( $base_css, $sections, $rows = null, $target = '' ) {
	$parts    = array();
	$base_css = rtrim( (string) $base_css );
	$sections = is_array( $sections ) ? $sections : array();
	$rows     = is_array( $rows ) ? $rows : aslc_get_css_breakpoint_rows();
	$target   = aslc_sanitize_css_target_scope( $target );

	if ( '' !== trim( $base_css ) ) {
		$parts[] = aslc_wrap_css_target_scope( $base_css, $target );
	}

	foreach ( $rows as $row ) {
		if ( empty( $row['id'] ) || empty( $row['value'] ) ) {
			continue;
		}

		$css = isset( $sections[ $row['id'] ] ) ? rtrim( (string) $sections[ $row['id'] ] ) : '';
		if ( '' === trim( $css ) ) {
			continue;
		}

		$scoped = aslc_wrap_css_target_scope( $css, $target );
		if ( '' !== $target ) {
			$scoped = aslc_indent_css( $scoped );
		}
		$parts[] = '@media (min-width: ' . $row['value'] . ") {\n" . $scoped . "\n}";
	}

	return implode( "\n\n", $parts );
}

/**
 * Wrap one non-empty section in its configured parent selector.
 *
 * @param string $css    Section CSS.
 * @param string $target Sanitized target selector.
 * @return string Scoped or unchanged CSS.
 */
function aslc_wrap_css_target_scope( $css, $target ) {
	$css = rtrim( (string) $css );
	if ( '' === $target ) {
		return $css;
	}

	return $target . " {\n\tvisibility: visible;\n\n" . aslc_indent_css( $css ) . "\n}";
}

/** Indent non-empty CSS lines without adding whitespace to blank lines. */
function aslc_indent_css( $css ) {
	return preg_replace( '/^(?=.)/m', "\t", (string) $css );
}

/**
 * Write a CSS record's generated ID and slug files.
 *
 * @param int    $post_id   CSS record ID.
 * @param string $base_css  Base CSS.
 * @param int    $author_id Author ID.
 * @param array  $options   Record options.
 * @return bool Whether the file was written.
 */
function aslc_write_css_snippet_file( $post_id, $base_css, $author_id, $options ) {
	if ( ! file_exists( CCJ_UPLOAD_DIR ) ) {
		wp_mkdir_p( CCJ_UPLOAD_DIR );
	}
	if ( ! wp_is_writable( CCJ_UPLOAD_DIR ) ) {
		return false;
	}

	$content = aslc_assemble_css_sections(
		$base_css,
		aslc_get_css_breakpoint_sections( $post_id ),
		null,
		aslc_get_css_target_scope( $post_id )
	);
	$content = aslc_replace_css_breakpoint_tokens( $content );
	$before  = '';
	$after   = '';

	if ( 'internal' === $options['linking'] ) {
		$before = '<!-- Local Start ID:' . $post_id . ' Author:' . $author_id . ' -->' . PHP_EOL . '<style>' . PHP_EOL;
		$after  = '</style>' . PHP_EOL . '<!-- Local End ID:' . $post_id . ' Author:' . $author_id . ' -->' . PHP_EOL;
	} elseif ( 'external' === $options['linking'] ) {
		$before = '/******* Do not edit this file *******' . PHP_EOL .
			'Local CSS - Saved: ' . date( 'M d Y | H:i:s' ) . ' */' . PHP_EOL;
	} else {
		return false;
	}

	$file_content = $before . $content . $after;
	$result       = false !== @file_put_contents( CCJ_UPLOAD_DIR . '/' . $post_id . '.css', $file_content );
	$slug         = get_post_meta( $post_id, '_slug', true );
	if ( $slug ) {
		@file_put_contents( CCJ_UPLOAD_DIR . '/' . sanitize_file_name( $slug ) . '.css', $file_content );
	}

	return $result;
}

/** Regenerate every CSS record after breakpoint definitions change. */
function aslc_regenerate_all_css_snippet_files() {
	$post_ids = get_posts(
		array(
			'post_type'      => 'custom-css-js',
			'post_status'    => array_keys( get_post_stati() ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $post_ids as $post_id ) {
		$options = get_post_meta( $post_id, 'options', true );
		if ( ! is_array( $options ) || 'css' !== ( $options['language'] ?? '' ) ) {
			continue;
		}
		$post = get_post( $post_id );
		if ( $post ) {
			aslc_write_css_snippet_file( $post_id, $post->post_content, $post->post_author, $options );
		}
	}
}
