<?php
/**
 * HTML snippet rendering helpers.
 *
 * @package ASLocalCSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve stored HTML references such as `123.html` to published snippet content.
 *
 * WordPress 7.0 no longer accepts a filename-shaped value in get_post(). The
 * search tree deliberately stores HTML entries with a .html suffix, so extract
 * and validate the numeric post ID before asking WordPress for the snippet.
 *
 * @param mixed $reference Search-tree HTML reference.
 * @return string Published HTML snippet content, or an empty string.
 */
function aslc_get_html_snippet_content( $reference ) {
	$reference = strtok( (string) $reference, '?' );
	$reference = basename( (string) $reference );

	if ( ! preg_match( '/^([1-9][0-9]*)(?:\.html)?$/i', $reference, $matches ) ) {
		return '';
	}

	$post = get_post( (int) $matches[1] );
	if ( ! $post instanceof WP_Post
		|| 'custom-css-js' !== $post->post_type
		|| 'publish' !== $post->post_status ) {
		return '';
	}

	return (string) $post->post_content;
}
