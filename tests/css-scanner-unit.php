<?php
/**
 * Lightweight regression test for per-record CSS scanning.
 */

define( 'ABSPATH', __DIR__ . '/' );

function __( $text ) {
	return $text;
}

require dirname( __DIR__ ) . '/as-local-css/includes/css-scanner.php';

$css = <<<'CSS'
.valid {
    color: red;
}
@media (min-width: %MD%) {
    .valid { display: block; }
}
CSS;
if ( array() !== aslc_scan_css_record( 10, $css ) ) {
	throw new RuntimeException( 'Valid CSS or an approved token produced a false positive.' );
}

$unknown_token = aslc_scan_css_record( 11, '@media (min-width: %XL%) { .valid { display: block; } }' );
if ( 'unresolved_breakpoint_token' !== ( $unknown_token[0]['code'] ?? '' ) ) {
	throw new RuntimeException( 'An unresolved breakpoint token was not reported.' );
}

$broken = <<<'CSS'
& .top-level { color: red; }
.broken {
    padding: 1rem inherit;
    &. bad-class { color: blue; }
    .child:not:has(.other) { display: block; }
CSS;
$findings = aslc_scan_css_record( 20, $broken );
$codes    = array_column( $findings, 'code' );
foreach ( array( 'top_level_nesting', 'unclosed_brace', 'mixed_css_wide_keyword', 'broken_nested_class', 'broken_not_has' ) as $expected_code ) {
	if ( ! in_array( $expected_code, $codes, true ) ) {
		throw new RuntimeException( 'Missing CSS scan finding: ' . $expected_code );
	}
}

echo "CSS scanner unit test passed.\n";
