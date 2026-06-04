<?php
// tests/test-tag-service-map.php  — run: php tests/test-tag-service-map.php
require __DIR__ . '/../includes/class-tag-service-map.php';

$fail = 0;
function check( $cond, $msg, &$fail ) { if ( ! $cond ) { echo "FAIL: $msg\n"; $fail++; } else { echo "ok: $msg\n"; } }

// emergency maps to two slugs
$slugs = EDOA_Tag_Service_Map::slugs_for_tags( ['emergency'] );
check( in_array( 'emergency-pain-relief', $slugs, true ), 'emergency -> emergency-pain-relief', $fail );
check( in_array( 'emergency-dental-exam', $slugs, true ), 'emergency -> emergency-dental-exam', $fail );

// pricing/insurance map to nothing
check( EDOA_Tag_Service_Map::slugs_for_tags( ['pricing','insurance'] ) === [], 'pricing/insurance -> []', $fail );

// dedup across multiple tags
$slugs2 = EDOA_Tag_Service_Map::slugs_for_tags( ['staff','cleanliness'] );
check( $slugs2 === ['comprehensive-dental-exam'], 'staff+cleanliness dedup to single slug', $fail );

// unknown tag ignored
check( EDOA_Tag_Service_Map::slugs_for_tags( ['nonsense'] ) === [], 'unknown tag -> []', $fail );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
