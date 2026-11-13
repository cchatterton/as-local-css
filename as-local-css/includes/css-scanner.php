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
 * Scan every published CSS record and save its findings.
 *
 * @return array
 */
function aslc_scan_css_records() {
	$findings = array();
	$posts    = get_posts(
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
		if ( ! is_array( $options ) || 'css' !== ( $options['language'] ?? '' ) ) {
			continue;
		}

		$css = aslc_get_generated_css_contents( $post->ID . '.css' );
		if ( is_wp_error( $css ) ) {
			$findings[] = aslc_css_scan_finding(
				$post->ID,
				0,
				'critical',
				'missing_generated_file',
				$css->get_error_message()
			);
			continue;
		}

		$findings = array_merge( $findings, aslc_scan_css_record( $post->ID, $css ) );
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
 * Scan one CSS record for structural and unambiguous syntax problems.
 *
 * @param int    $post_id CSS record ID.
 * @param string $css     Authored CSS without its HTML style wrapper.
 * @return array
 */
function aslc_scan_css_record( $post_id, $css ) {
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
			if ( '&' === $character && empty( $brace_stack ) ) {
				$findings[] = aslc_css_scan_finding(
					$post_id,
					$line,
					'error',
					'top_level_nesting',
					__( 'A top-level "&" nesting selector has no parent rule.', 'custom-css-js' )
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
					__( 'An extra closing brace can change how later CSS is parsed.', 'custom-css-js' )
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
			__( 'A CSS comment is not closed.', 'custom-css-js' )
		);
	} elseif ( 'single' === $state || 'double' === $state ) {
		$findings[] = aslc_css_scan_finding(
			$post_id,
			$state_line,
			'critical',
			'unclosed_string',
			__( 'A quoted CSS value is not closed.', 'custom-css-js' )
		);
	}

	foreach ( $brace_stack as $open_line ) {
		$findings[] = aslc_css_scan_finding(
			$post_id,
			$open_line,
			'critical',
			'unclosed_brace',
			__( 'An opening brace is not closed. Later records may be captured by this rule.', 'custom-css-js' )
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

	$patterns = array(
		array(
			'pattern'  => '/&\.\s+[a-z_-]/i',
			'severity' => 'error',
			'code'     => 'broken_nested_class',
			'message'  => __( 'A nested class selector has whitespace between the period and class name.', 'custom-css-js' ),
		),
		array(
			'pattern'  => '/:not\s*:has\s*\(/i',
			'severity' => 'error',
			'code'     => 'broken_not_has',
			'message'  => __( 'The :has() selector must be placed inside :not(), for example :not(:has(...)).', 'custom-css-js' ),
		),
		array(
			'pattern'  => '/\bcalc\([^)]*\/\s*\d+,\d+/i',
			'severity' => 'error',
			'code'     => 'comma_decimal',
			'message'  => __( 'A calculation contains a comma decimal. CSS decimals must use a period.', 'custom-css-js' ),
		),
		array(
			'pattern'  => '/@(?:media|supports|container)\b[^{}]*\{\s*(?:padding|margin|display|position|width|height|color|background|font-size|line-height|gap)\s*:/i',
			'severity' => 'error',
			'code'     => 'declaration_without_selector',
			'message'  => __( 'A declaration appears directly inside a conditional block without a selector.', 'custom-css-js' ),
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
					__( 'The inherit keyword cannot be mixed with other values in a padding or margin shorthand.', 'custom-css-js' )
				);
			}
		}
	}

	return $findings;
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
	);
}
