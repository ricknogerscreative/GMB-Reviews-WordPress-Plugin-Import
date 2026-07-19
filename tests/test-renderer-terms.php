<?php
// tests/test-renderer-terms.php — run: php tests/test-renderer-terms.php
require __DIR__ . '/../includes/class-taxonomies.php';
require __DIR__ . '/../includes/class-testimonials-renderer.php';

$fail = 0;
function check( $cond, $msg, &$fail ) { if ( ! $cond ) { echo "FAIL: $msg\n"; $fail++; } else { echo "ok: $msg\n"; } }

$P = EDOA_RS_Taxonomies::PLACEMENTS; // ['homepage','landing','book','general']

check( EDOA_Testimonials_Renderer::filter_terms( 'homepage', $P ) === array( 'homepage' ), 'single valid', $fail );
check( EDOA_Testimonials_Renderer::filter_terms( 'homepage, book', $P ) === array( 'homepage', 'book' ), 'multiple valid + trim', $fail );
check( EDOA_Testimonials_Renderer::filter_terms( 'homepage, bogus', $P ) === array( 'homepage' ), 'drops unknown', $fail );
check( EDOA_Testimonials_Renderer::filter_terms( '', $P ) === array(), 'empty string -> empty', $fail );
check( EDOA_Testimonials_Renderer::filter_terms( 'homepage,homepage', $P ) === array( 'homepage' ), 'de-dupe', $fail );
check( EDOA_Testimonials_Renderer::filter_terms( 'bogus', $P ) === array(), 'all unknown -> empty', $fail );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
