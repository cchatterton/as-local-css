<?php
/**
 * CSS record scanning for AS Local CSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASLC_CSS_SCAN_OPTION', 'aslc_css_scan_results' );

/**
 * Return saved CSS scan findings.
 *
 * @return array
 */
function aslc_get_css_scan_findings() {
	$results = get_option( ASLC_CSS_SCAN_OPTION, array() );
	return ! empty( $results['findings'] ) && is_array( $results['findings'] ) ? $results['findings'] : array();
}

/**
 * Return generated CSS files that must be scanned.
 *
 * Published CSS posts are included for the admin report. Files referenced by
 * the active search tree are also included so compilation validates the exact
 * sources it is about to concatenate, even if post metadata is stale.
 *
 * @param array|null $search_tree Active custom-code search tree.
 * @return array<int,string> Post ID to generated filename.
 */
function aslc_get_css_scan_targets( $search_tree = null ) {
	$targets = array();
	$posts   = get_posts(
		array(
			'post_type'      => 'custom-css-js',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);

	foreach ( $posts as $post ) {
		$options = get_post_meta( $post->ID, 'options', true );
		if ( is_array( $options ) && 'css' === ( $options['language'] ?? '' ) ) {
			$targets[ (int) $post->ID ] = $post->ID . '.css';
		}
	}

	if ( null === $search_tree ) {
		$search_tree = get_option( 'custom-css-js-tree', array() );
	}
	if ( ! is_array( $search_tree ) ) {
		return $targets;
	}

	foreach ( $search_tree as $branch => $priority_groups ) {
		if ( false === strpos( (string) $branch, '-css-' ) || ! is_array( $priority_groups ) ) {
			continue;
		}
		foreach ( $priority_groups as $filenames ) {
			if ( ! is_array( $filenames ) ) {
				continue;
			}
			foreach ( $filenames as $filename ) {
				$safe_filename = basename( (string) strtok( (string) $filename, '?' ) );
				if ( preg_match( '/^(\d+)\.css$/', $safe_filename, $matches ) ) {
					$targets[ (int) $matches[1] ] = $safe_filename;
				}
			}
		}
	}

	ksort( $targets, SORT_NUMERIC );
	return $targets;
}

/**
 * Scan every published or actively referenced CSS record and save findings.
 *
 * @param array|null $search_tree Active custom-code search tree.
 * @return array
 */
function aslc_scan_css_records( $search_tree = null ) {
	$findings = array();
	$targets  = aslc_get_css_scan_targets( $search_tree );

	foreach ( $targets as $post_id => $filename ) {
		$css = aslc_get_generated_css_contents( $filename );
		if ( is_wp_error( $css ) ) {
			$findings[] = aslc_css_scan_finding(
				$post_id,
				0,
				'critical',
				'missing_generated_file',
				$css->get_error_message()
			);
			continue;
		}

		$post = get_post( $post_id );
		if ( $post && function_exists( 'aslc_get_css_breakpoint_sections' ) ) {
			$target_scope = aslc_get_css_target_scope( $post_id );
			$has_target   = '' !== $target_scope;
			$findings     = array_merge( $findings, aslc_scan_css_record( $post_id, $post->post_content, __( 'Base', 'custom-css-js' ), $has_target ) );
			$sections     = aslc_get_css_breakpoint_sections( $post_id );
			foreach ( aslc_get_css_breakpoint_rows() as $row ) {
				if ( empty( $sections[ $row['id'] ] ) ) {
					continue;
				}
				$findings = array_merge(
					$findings,
					aslc_scan_css_record( $post_id, $sections[ $row['id'] ], $row['token'], $has_target )
				);
			}
		} else {
			$findings = array_merge( $findings, aslc_scan_css_record( $post_id, $css ) );
		}
	}

	update_option(
		ASLC_CSS_SCAN_OPTION,
		array(
			'scanned_at' => time(),
			'findings'   => $findings,
		),
		false
	);

	return $findings;
}

/**
 * Return findings that can make later records parse inside a broken record.
 *
 * @param array $findings CSS scan findings.
 * @return array
 */
function aslc_get_bundle_blocking_css_findings( array $findings ) {
	$blocking_codes = array( 'unclosed_brace', 'unclosed_comment', 'unclosed_string', 'unresolved_breakpoint_token' );
	return array_values(
		array_filter(
			$findings,
			static function ( $finding ) use ( $blocking_codes ) {
				return isset( $finding['code'] ) && in_array( $finding['code'], $blocking_codes, true );
			}
		)
	);
}

/**
 * Scan one CSS record for structural and unambiguous syntax problems.
 *
 * @param int    $post_id CSS record ID.
 * @param string $css     Authored CSS without its HTML style wrapper.
 * @param string $section Optional editor section label.
 * @param bool   $allow_top_level_nesting Whether a generated target supplies the parent rule.
 * @return array
 */
function aslc_scan_css_record( $post_id, $css, $section = '', $allow_top_level_nesting = false ) {
	$findings     = array();
	$brace_stack  = array();
	$length       = strlen( $css );
	$line         = 1;
	$state        = 'normal';
	$state_line   = 1;
	$escaped      = false;
	$line_started = false;

	for ( $index = 0; $index < $length; $index++ ) {
		$character = $css[ $index ];
		$next      = $index + 1 < $length ? $css[ $index + 1 ] : '';

		if ( "\n" === $character ) {
			$line++;
			$line_started = false;
		}

		if ( 'comment' === $state ) {
			if ( '*' === $character && '/' === $next ) {
				$state = 'normal';
				$index++;
			}
			continue;
		}

		if ( 'single' === $state || 'double' === $state ) {
			if ( $escaped ) {
				$escaped = false;
				continue;
			}
			if ( '\\' === $character ) {
				$escaped = true;
				continue;
			}
			if ( ( 'single' === $state && "'" === $character ) || ( 'double' === $state && '"' === $character ) ) {
				$state = 'normal';
			}
			continue;
		}

		if ( '/' === $character && '*' === $next ) {
			$state      = 'comment';
			$state_line = $line;
			$index++;
			continue;
		}
		if ( "'" === $character || '"' === $character ) {
			$state      = "'" === $character ? 'single' : 'double';
			$state_line = $line;
			continue;
		}

		if ( ! $line_started && ! ctype_space( $character ) ) {
			$line_started = true;
			if ( '&' === $character && empty( $brace_stack ) && ! $allow_top_level_nesting ) {
				$findings[] = aslc_css_scan_finding(
					$post_id,
					$line,
					'error',
					'top_level_nesting',
					__( 'Top-level "&" has no parent rule.', 'custom-css-js' )
				);
			}
		}

		if ( '{' === $character ) {
			$brace_stack[] = $line;
		} elseif ( '}' === $character ) {
			if ( empty( $brace_stack ) ) {
				$findings[] = aslc_css_scan_finding(
					$post_id,
					$line,
					'critical',
					'extra_closing_brace',
					__( 'Extra closing brace.', 'custom-css-js' )
				);
			} else {
				array_pop( $brace_stack );
			}
		}
	}

	if ( 'comment' === $state ) {
		$findings[] = aslc_css_scan_finding(
			$post_id,
			$state_line,
			'critical',
			'unclosed_comment',
			__( 'Unclosed CSS comment.', 'custom-css-js' )
		);
	} elseif ( 'single' === $state || 'double' === $state ) {
		$findings[] = aslc_css_scan_finding(
			$post_id,
			$state_line,
			'critical',
			'unclosed_string',
			__( 'Unclosed quoted value.', 'custom-css-js' )
		);
	}

	foreach ( $brace_stack as $open_line ) {
		$findings[] = aslc_css_scan_finding(
			$post_id,
			$open_line,
			'critical',
			'unclosed_brace',
			__( 'Unclosed opening brace.', 'custom-css-js' )
		);
	}

	$without_comments = preg_replace_callback(
		'/\/\*[\s\S]*?\*\//',
		static function ( $matches ) {
			return preg_replace( '/[^\n]/', ' ', $matches[0] );
		},
		$css
	);
	if ( ! is_string( $without_comments ) ) {
		$without_comments = $css;
	}

	$has_structural_error = false;
	foreach ( $findings as $finding ) {
		if ( 'critical' === ( $finding['severity'] ?? '' ) ) {
			$has_structural_error = true;
			break;
		}
	}
	if ( ! $has_structural_error ) {
		$findings = array_merge(
			$findings,
			aslc_scan_css_nesting_compatibility( $post_id, $without_comments, $allow_top_level_nesting )
		);
	}

	if ( 0 === strcasecmp( $section, __( 'Base', 'custom-css-js' ) )
		&& preg_match( '/@media\b/i', $without_comments, $media_match, PREG_OFFSET_CAPTURE ) ) {
		$findings[] = aslc_css_scan_finding(
			$post_id,
			1 + substr_count( substr( $without_comments, 0, $media_match[0][1] ), "\n" ),
			'information',
			'media_query_in_base',
			__( 'Media query in Base.', 'custom-css-js' )
		);
	}

	$configured_tokens = function_exists( 'aslc_get_css_breakpoint_tokens' )
		? aslc_get_css_breakpoint_tokens()
		: array(
			'%SM%' => '48rem',
			'%MD%' => '68rem',
			'%LG%' => '75rem',
			'%XL%' => '80rem',
		);
	if ( preg_match_all( '/%[A-Z][A-Z0-9_-]*%/', $without_comments, $token_matches, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $token_matches[0] as $token_match ) {
			if ( isset( $configured_tokens[ $token_match[0] ] ) ) {
				continue;
			}
			$findings[] = aslc_css_scan_finding(
				$post_id,
				1 + substr_count( substr( $without_comments, 0, $token_match[1] ), "\n" ),
				'critical',
				'unresolved_breakpoint_token',
				sprintf(
					/* translators: %s: unresolved breakpoint token. */
					__( 'No breakpoint is configured for %s.', 'custom-css-js' ),
					$token_match[0]
				)
			);
		}
	}

	$patterns = array(
		array(
			'pattern'  => '/&\.\s+[a-z_-]/i',
			'severity' => 'error',
			'code'     => 'broken_nested_class',
			'message'  => __( 'Space after a nested class dot.', 'custom-css-js' ),
		),
		array(
			'pattern'  => '/:not\s*:has\s*\(/i',
			'severity' => 'error',
			'code'     => 'broken_not_has',
			'message'  => __( 'Use :not(:has(...)).', 'custom-css-js' ),
		),
		array(
			'pattern'  => '/\bcalc\([^)]*\/\s*\d+,\d+/i',
			'severity' => 'error',
			'code'     => 'comma_decimal',
			'message'  => __( 'Use a period for CSS decimals.', 'custom-css-js' ),
		),
		array(
			'pattern'  => '/@(?:media|supports|container)\b[^{}]*\{\s*(?:padding|margin|display|position|width|height|color|background|font-size|line-height|gap)\s*:/i',
			'severity' => 'error',
			'code'     => 'declaration_without_selector',
			'message'  => __( 'Declaration has no selector.', 'custom-css-js' ),
		),
	);

	foreach ( $patterns as $pattern ) {
		if ( preg_match_all( $pattern['pattern'], $without_comments, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$findings[] = aslc_css_scan_finding(
					$post_id,
					1 + substr_count( substr( $without_comments, 0, $match[1] ), "\n" ),
					$pattern['severity'],
					$pattern['code'],
					$pattern['message']
				);
			}
		}
	}

	if ( preg_match_all( '/\b(?:padding|margin)\s*:\s*([^;{}]+)\s*;/i', $without_comments, $declarations, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $declarations[1] as $declaration ) {
			$value = trim( $declaration[0] );
			if ( false !== stripos( $value, 'inherit' ) && 'inherit' !== strtolower( $value ) ) {
				$findings[] = aslc_css_scan_finding(
					$post_id,
					1 + substr_count( substr( $without_comments, 0, $declaration[1] ), "\n" ),
					'error',
					'mixed_css_wide_keyword',
					__( 'Use inherit by itself in this shorthand.', 'custom-css-js' )
				);
			}
		}
	}

	if ( '' !== $section ) {
		foreach ( $findings as &$finding ) {
			$finding['section'] = $section;
		}
		unset( $finding );
	}

	return $findings;
}

/**
 * Scan native CSS nesting against the plugin's explicit legacy-safe standard.
 *
 * @param int    $post_id CSS record ID.
 * @param string $css     Comment-free CSS with original line breaks retained.
 * @param bool   $implicit_parent Whether Target scope supplies an implicit parent rule.
 * @return array
 */
function aslc_scan_css_nesting_compatibility( $post_id, $css, $implicit_parent = false ) {
	$blocks = aslc_parse_css_blocks( $css );
	if ( empty( $blocks ) ) {
		return array();
	}

	foreach ( $blocks as $index => &$block ) {
		$ancestor             = $block['parent'];
		$block['in_keyframes'] = false;
		while ( null !== $ancestor ) {
			if ( preg_match( '/^@(?:-[a-z]+-)?keyframes\b/i', $blocks[ $ancestor ]['prelude'] ) ) {
				$block['in_keyframes'] = true;
				break;
			}
			$ancestor = $blocks[ $ancestor ]['parent'];
		}
		$block['is_style'] = '' !== $block['prelude']
			&& '@' !== substr( $block['prelude'], 0, 1 )
			&& ! $block['in_keyframes'];
		$block['style_parent'] = null;
		$block['nested_at']    = array();
	}
	unset( $block );

	foreach ( $blocks as $index => &$block ) {
		if ( ! $block['is_style'] ) {
			continue;
		}

		$ancestor = $block['parent'];
		while ( null !== $ancestor ) {
			if ( $blocks[ $ancestor ]['is_style'] ) {
				$block['style_parent'] = $ancestor;
				$blocks[ $ancestor ]['nested_at'][] = $block['open_offset'];
				break;
			}
			$ancestor = $blocks[ $ancestor ]['parent'];
		}
	}
	unset( $block );

	$findings = array();
	foreach ( $blocks as $block ) {
		if ( ! $block['is_style'] ) {
			continue;
		}

		$is_nested = null !== $block['style_parent']
			|| ( $implicit_parent && ! $block['in_keyframes'] );
		$has_nested_children = ! empty( $block['nested_at'] );

		if ( $is_nested ) {
			foreach ( aslc_split_css_selector_list( $block['prelude'], $block['prelude_offset'] ) as $selector ) {
				if ( false === strpos( $selector['text'], '&' ) ) {
					$findings[] = aslc_css_scan_finding(
						$post_id,
						aslc_css_line_from_offset( $css, $selector['offset'] ),
						'error',
						'nested_selector_without_ampersand',
						__( 'Nested selector must include &.', 'custom-css-js' )
					);
					break;
				}
			}
		}

		if ( ( $is_nested || $has_nested_children ) && empty( $block['declarations'] ) ) {
			$findings[] = aslc_css_scan_finding(
				$post_id,
				aslc_css_line_from_offset( $css, $block['prelude_offset'] ),
				'error',
				'nested_rule_without_declaration',
				__( 'Nested rule needs a direct declaration.', 'custom-css-js' )
			);
			continue;
		}

		if ( $has_nested_children
			&& ! empty( $block['declarations'] )
			&& min( $block['declarations'] ) > min( $block['nested_at'] ) ) {
			$findings[] = aslc_css_scan_finding(
				$post_id,
				aslc_css_line_from_offset( $css, min( $block['declarations'] ) ),
				'error',
				'nested_declaration_after_rule',
				__( 'Put parent declarations before nested rules.', 'custom-css-js' )
			);
		}
	}

	return $findings;
}

/** Parse CSS blocks and their immediate declarations without changing line offsets. */
function aslc_parse_css_blocks( $css ) {
	$blocks               = array();
	$stack                = array();
	$root_statement_start = 0;
	$length               = strlen( $css );
	$state                = 'normal';
	$escaped              = false;
	$parentheses          = 0;
	$brackets             = 0;

	for ( $index = 0; $index < $length; $index++ ) {
		$character = $css[ $index ];

		if ( 'single' === $state || 'double' === $state ) {
			if ( $escaped ) {
				$escaped = false;
				continue;
			}
			if ( '\\' === $character ) {
				$escaped = true;
				continue;
			}
			if ( ( 'single' === $state && "'" === $character ) || ( 'double' === $state && '"' === $character ) ) {
				$state = 'normal';
			}
			continue;
		}

		if ( "'" === $character || '"' === $character ) {
			$state = "'" === $character ? 'single' : 'double';
			continue;
		}
		if ( '(' === $character ) {
			$parentheses++;
			continue;
		}
		if ( ')' === $character && $parentheses > 0 ) {
			$parentheses--;
			continue;
		}
		if ( '[' === $character ) {
			$brackets++;
			continue;
		}
		if ( ']' === $character && $brackets > 0 ) {
			$brackets--;
			continue;
		}
		if ( $parentheses || $brackets ) {
			continue;
		}

		$current = empty( $stack ) ? null : end( $stack );
		if ( '{' === $character ) {
			$statement_start = null === $current ? $root_statement_start : $blocks[ $current ]['statement_start'];
			$raw             = substr( $css, $statement_start, $index - $statement_start );
			$leading         = strlen( $raw ) - strlen( ltrim( $raw ) );
			$prelude         = trim( $raw );
			$block_index     = count( $blocks );
			$blocks[]        = array(
				'prelude'          => $prelude,
				'prelude_offset'   => $statement_start + $leading,
				'open_offset'      => $index,
				'parent'           => $current,
				'statement_start'  => $index + 1,
				'declarations'     => array(),
			);
			$stack[] = $block_index;
			continue;
		}

		if ( ';' === $character ) {
			if ( null === $current ) {
				$root_statement_start = $index + 1;
			} else {
				aslc_record_css_declaration( $blocks[ $current ], $css, $index );
				$blocks[ $current ]['statement_start'] = $index + 1;
			}
			continue;
		}

		if ( '}' === $character && null !== $current ) {
			aslc_record_css_declaration( $blocks[ $current ], $css, $index );
			array_pop( $stack );
			$parent = empty( $stack ) ? null : end( $stack );
			if ( null === $parent ) {
				$root_statement_start = $index + 1;
			} else {
				$blocks[ $parent ]['statement_start'] = $index + 1;
			}
		}
	}

	return $blocks;
}

/** Record a direct declaration ending at the supplied delimiter. */
function aslc_record_css_declaration( &$block, $css, $end_offset ) {
	$raw     = substr( $css, $block['statement_start'], $end_offset - $block['statement_start'] );
	$leading = strlen( $raw ) - strlen( ltrim( $raw ) );
	$value   = trim( $raw );
	if ( '' !== $value && '@' !== substr( $value, 0, 1 ) && preg_match( '/^(?:--[a-z0-9_-]+|[a-z-]+)\s*:/i', $value ) ) {
		$block['declarations'][] = $block['statement_start'] + $leading;
	}
}

/** Split a selector list on commas outside functions and attribute selectors. */
function aslc_split_css_selector_list( $selector_list, $base_offset = 0 ) {
	$selectors   = array();
	$start       = 0;
	$parentheses = 0;
	$brackets    = 0;
	$length      = strlen( $selector_list );
	$state       = 'normal';
	$escaped     = false;

	for ( $index = 0; $index <= $length; $index++ ) {
		$character = $index < $length ? $selector_list[ $index ] : ',';
		if ( 'single' === $state || 'double' === $state ) {
			if ( $escaped ) {
				$escaped = false;
				continue;
			}
			if ( '\\' === $character ) {
				$escaped = true;
				continue;
			}
			if ( ( 'single' === $state && "'" === $character ) || ( 'double' === $state && '"' === $character ) ) {
				$state = 'normal';
			}
			continue;
		}
		if ( "'" === $character || '"' === $character ) {
			$state = "'" === $character ? 'single' : 'double';
			continue;
		}
		if ( '(' === $character ) {
			$parentheses++;
		} elseif ( ')' === $character && $parentheses > 0 ) {
			$parentheses--;
		} elseif ( '[' === $character ) {
			$brackets++;
		} elseif ( ']' === $character && $brackets > 0 ) {
			$brackets--;
		} elseif ( ',' === $character && 0 === $parentheses && 0 === $brackets ) {
			$raw     = substr( $selector_list, $start, $index - $start );
			$leading = strlen( $raw ) - strlen( ltrim( $raw ) );
			if ( '' !== trim( $raw ) ) {
				$selectors[] = array(
					'text'   => trim( $raw ),
					'offset' => $base_offset + $start + $leading,
				);
			}
			$start = $index + 1;
		}
	}

	return $selectors;
}

/** Return a one-based source line for a byte offset. */
function aslc_css_line_from_offset( $css, $offset ) {
	return 1 + substr_count( substr( $css, 0, max( 0, (int) $offset ) ), "\n" );
}

/**
 * Build one normalized CSS scan finding.
 *
 * @param int    $post_id  CSS record ID.
 * @param int    $line     One-based line number, or zero when unavailable.
 * @param string $severity Critical, error, or warning.
 * @param string $code     Stable machine-readable finding code.
 * @param string $message  Human-readable description.
 * @return array
 */
function aslc_css_scan_finding( $post_id, $line, $severity, $code, $message ) {
	return array(
		'post_id'  => (int) $post_id,
		'line'     => max( 0, (int) $line ),
		'severity' => $severity,
		'code'     => $code,
		'message'  => $message,
		'section'  => '',
	);
}
