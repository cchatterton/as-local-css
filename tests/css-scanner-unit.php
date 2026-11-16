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

$base_media = aslc_scan_css_record(
	12,
	"/* @media in a comment */\n.selector { display: block; }\n@media (min-width: 40rem) { .selector { display: grid; } }",
	'Base'
);
if ( 1 !== count( $base_media )
	|| 'media_query_in_base' !== ( $base_media[0]['code'] ?? '' )
	|| 'information' !== ( $base_media[0]['severity'] ?? '' )
	|| 3 !== ( $base_media[0]['line'] ?? 0 )
	|| 'Base' !== ( $base_media[0]['section'] ?? '' ) ) {
	throw new RuntimeException( 'A Base media query was not reported as section-aware information.' );
}

if ( array() !== aslc_scan_css_record( 13, '@media (min-width: 40rem) { .selector { display: grid; } }', '%SM%' ) ) {
	throw new RuntimeException( 'A media query outside the Base section produced a Base information finding.' );
}

$blocking = aslc_get_bundle_blocking_css_findings( $base_media );
if ( array() !== $blocking ) {
	throw new RuntimeException( 'The Base media-query information finding incorrectly blocked generated CSS.' );
}

$scoped_nesting = aslc_scan_css_record( 14, '& .child { color: red; }', 'Base', true );
if ( array() !== $scoped_nesting ) {
	throw new RuntimeException( 'Top-level nesting was reported even though a target scope supplies its parent rule.' );
}

$legacy_safe_nesting = <<<'CSS'
form {
    --aslc-nesting: 1;
    & input {
        color: white;
        &:hover { background: red; }
    }
}
CSS;
if ( array() !== aslc_scan_css_record( 15, $legacy_safe_nesting ) ) {
	throw new RuntimeException( 'Legacy-safe explicit nesting produced a false positive.' );
}

$gravity_nesting = <<<'CSS'
.gform_wrapper {
    .gform_submission_error {
        font-size: 1.5rem;
    }
    visibility: visible;
    & div.ginput_container {
        visibility: visible;
        & textarea,
          select,
          input[type="text"] {
            border-radius: .5rem;
        }
        & select, input, textarea {
            border-color: grey;
        }
    }
    & .gfield_radio {
        visibility: visible;
        > div {
            background: transparent;
            > label { font-weight: bold; }
        }
    }
}
CSS;
$gravity_findings = aslc_scan_css_record( 16, $gravity_nesting );
$gravity_codes    = array_column( $gravity_findings, 'code' );
if ( 5 !== count( array_keys( $gravity_codes, 'nested_selector_without_ampersand', true ) ) ) {
	throw new RuntimeException( 'Implicit nested selectors were not reported consistently.' );
}
if ( ! in_array( 'nested_declaration_after_rule', $gravity_codes, true ) ) {
	throw new RuntimeException( 'A parent declaration placed after nesting began was not reported.' );
}
if ( array() !== aslc_get_bundle_blocking_css_findings( $gravity_findings ) ) {
	throw new RuntimeException( 'Nesting compatibility errors incorrectly blocked compiled delivery.' );
}

$declaration_free = aslc_scan_css_record( 17, ".parent {\n    & .child { color: red; }\n}" );
if ( ! in_array( 'nested_rule_without_declaration', array_column( $declaration_free, 'code' ), true ) ) {
	throw new RuntimeException( 'A nested parent without a direct declaration was not reported.' );
}

$nested_at_rule = aslc_scan_css_record(
	18,
	".parent {\n    display: block;\n    @media (min-width: 40rem) {\n        & .child { color: red; }\n    }\n}"
);
if ( array() !== $nested_at_rule ) {
	throw new RuntimeException( 'A valid nested at-rule produced a false positive.' );
}

$scoped_implicit = aslc_scan_css_record( 19, '.child { color: red; }', 'Base', true );
if ( ! in_array( 'nested_selector_without_ampersand', array_column( $scoped_implicit, 'code' ), true ) ) {
	throw new RuntimeException( 'An implicit selector beneath Target scope was not reported.' );
}

$keyframes = aslc_scan_css_record(
	20,
	"@keyframes pulse {\n    from { opacity: 0; }\n    50%, 75% { opacity: .5; }\n    to { opacity: 1; }\n}"
);
if ( array() !== $keyframes ) {
	throw new RuntimeException( 'Keyframe selectors produced a nesting compatibility false positive.' );
}

$unknown_token = aslc_scan_css_record( 11, '@media (min-width: %XXL%) { .valid { display: block; } }' );
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
