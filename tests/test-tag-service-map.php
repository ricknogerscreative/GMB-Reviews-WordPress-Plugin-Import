<?php
// tests/test-tag-service-map.php  — run: php tests/test-tag-service-map.php
require __DIR__ . '/../includes/class-tag-service-map.php';

$fail = 0;
function check( $cond, $msg, &$fail ) { if ( ! $cond ) { echo "FAIL: $msg\n"; $fail++; } else { echo "ok: $msg\n"; } }

// emergency maps to two slugs
$slugs = EDOA_Tag_Service_Map::slugs_for_tags( ['emergency'] );
check( $slugs === ['emergency'], 'emergency -> emergency', $fail );

// emergency + wait_time both map to emergency, deduped
check( EDOA_Tag_Service_Map::slugs_for_tags( ['emergency','wait_time'] ) === ['emergency'], 'emergency+wait_time dedup to emergency', $fail );

// pricing/insurance map to nothing
check( EDOA_Tag_Service_Map::slugs_for_tags( ['pricing','insurance'] ) === [], 'pricing/insurance -> []', $fail );

// dedup across multiple tags
$slugs2 = EDOA_Tag_Service_Map::slugs_for_tags( ['staff','cleanliness'] );
check( $slugs2 === ['comprehensive-exam'], 'staff+cleanliness dedup to single slug', $fail );

// unknown tag ignored
check( EDOA_Tag_Service_Map::slugs_for_tags( ['nonsense'] ) === [], 'unknown tag -> []', $fail );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
