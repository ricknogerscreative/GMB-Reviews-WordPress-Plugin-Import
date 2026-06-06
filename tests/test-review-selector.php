<?php
// tests/test-review-selector.php — run: php tests/test-review-selector.php
require __DIR__ . '/../includes/class-review-selector.php';

$fail = 0;
function check( $cond, $msg, &$fail ) { if ( ! $cond ) { echo "FAIL: $msg\n"; $fail++; } else { echo "ok: $msg\n"; } }
function rv( $id, $value, $loc, $topics = array(), $services = array(), $date = '2026-01-01' ) {
	return array( 'id'=>$id, 'value'=>$value, 'date'=>$date, 'location_key'=>$loc, 'topics'=>$topics, 'services'=>$services );
}

$quotas = array(
	'per_location'         => 2,
	'per_location_topic'   => 1,
	'per_location_service' => 1,
	'per_topic'            => 2,
	'per_service'          => 2,
	'best_overall'         => 3,
);

// Build reviews: loc A has 5 reviews of descending value; B has 2.
$reviews = array(
	rv( 'A1', 100, 'A', array('financing'), array('root-canals') ),
	rv( 'A2', 90,  'A', array('financing'), array('emergency') ),
	rv( 'A3', 80,  'A', array('emergency'), array() ),
	rv( 'A4', 70,  'A', array(), array() ),
	rv( 'A5', 60,  'A', array(), array() ),
	rv( 'B1', 95,  'B', array('financing'), array('emergency') ),
	rv( 'B2', 50,  'B', array(), array() ),
);

$out  = EDOA_Review_Selector::select( $reviews, $quotas );
$keep = $out['keep'];
$best = $out['best_ids'];

// A4/A5 are pure-location overflow past per_location=2 and carry no topic/service → dropped.
check( ! isset( $keep['A5'] ), 'A5 dropped (location quota full, no topic/service)', $fail );
check( ! isset( $keep['A4'] ), 'A4 dropped (location quota full, no topic/service)', $fail );
// A1 kept (top location + financing + root-canals + best).
check( isset( $keep['A1'] ), 'A1 kept', $fail );
// A3 kept via per-location emergency topic even though per_location(2) filled by A1,A2.
check( isset( $keep['A3'] ), 'A3 kept via emergency topic quota', $fail );
// best_overall = top 3 by value across kept: A1(100), B1(95), A2(90).
check( isset( $best['A1'] ) && isset( $best['B1'] ) && isset( $best['A2'] ), 'best-overall = A1,B1,A2', $fail );
check( count( $best ) === 3, 'best-overall capped at 3', $fail );
// determinism: same input → same keep set.
$out2 = EDOA_Review_Selector::select( $reviews, $quotas );
check( array_keys( $keep ) === array_keys( $out2['keep'] ), 'selection is deterministic', $fail );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
