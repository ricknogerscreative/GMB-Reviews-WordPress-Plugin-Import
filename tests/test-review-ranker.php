<?php
// tests/test-review-ranker.php — run: php tests/test-review-ranker.php
require __DIR__ . '/../includes/class-review-ranker.php';

$fail = 0;
function check( $cond, $msg, &$fail ) { if ( ! $cond ) { echo "FAIL: $msg\n"; $fail++; } else { echo "ok: $msg\n"; } }

function rev( $id, $stars, $text, $loc ) { return array( 'id'=>$id, 'stars'=>$stars, 'text'=>$text, 'location_key'=>$loc ); }

// build 20 reviews for loc A (mix of qualifying + not)
$reviews = array();
$reviews[] = rev( 'lowstar', 3, 'long enough text here', 'A' ); // filtered: < 4 stars
$reviews[] = rev( 'notext', 5, '', 'A' );                        // filtered: no text
for ( $i = 0; $i < 18; $i++ ) {
	$reviews[] = rev( "A$i", 4 + ( $i % 2 ), str_repeat( 'x', 10 + $i ), 'A' );
}
$reviews[] = rev( 'B1', 5, 'great vegas dentist', 'B' );

// qualifying filter
$q = EDOA_Review_Ranker::filter_qualifying( $reviews );
check( ! array_filter( $q, fn( $r ) => $r['stars'] < 4 || $r['text'] === '' ), 'all qualifying are >=4 stars with text', $fail );
check( count( $q ) === 19, 'filtered count = 19 (18 A + 1 B)', $fail );

// per-location top 15
$byLoc = EDOA_Review_Ranker::top_per_location( $q, 15 );
check( count( $byLoc['A'] ) === 15, 'loc A capped at 15', $fail );
check( count( $byLoc['B'] ) === 1, 'loc B has 1', $fail );
// sorted: 5-star before 4-star
check( $byLoc['A'][0]['stars'] === 5, 'loc A first is 5 stars', $fail );

// best overall top 30 deduped
$best = EDOA_Review_Ranker::best_overall( $q, 30 );
$ids  = array_column( $best, 'id' );
check( count( $ids ) === count( array_unique( $ids ) ), 'best-overall has no dupes', $fail );
check( count( $best ) === 19, 'best-overall = min(30, total qualifying)=19', $fail );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
