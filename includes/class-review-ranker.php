<?php
// includes/class-review-ranker.php

class EDOA_Review_Ranker {

	/** Keep only stars >= 4 with non-empty trimmed text. */
	public static function filter_qualifying( array $reviews ): array {
		return array_values( array_filter( $reviews, static function ( $r ) {
			return (int) $r['stars'] >= 4 && trim( (string) $r['text'] ) !== '';
		} ) );
	}

	/** Sort by stars DESC then text length DESC (stable). */
	private static function sort_ranked( array $reviews ): array {
		usort( $reviews, static function ( $a, $b ) {
			if ( (int) $a['stars'] !== (int) $b['stars'] ) {
				return (int) $b['stars'] <=> (int) $a['stars'];
			}
			return strlen( (string) $b['text'] ) <=> strlen( (string) $a['text'] );
		} );
		return $reviews;
	}

	/**
	 * @return array<string, array> location_key => top-N ranked reviews
	 */
	public static function top_per_location( array $qualifying, int $perLoc ): array {
		$byLoc = array();
		foreach ( $qualifying as $r ) {
			$byLoc[ $r['location_key'] ][] = $r;
		}
		foreach ( $byLoc as $key => $list ) {
			$byLoc[ $key ] = array_slice( self::sort_ranked( $list ), 0, $perLoc );
		}
		return $byLoc;
	}

	/** Global top-N ranked, deduped by id. */
	public static function best_overall( array $qualifying, int $limit ): array {
		$ranked = self::sort_ranked( $qualifying );
		$seen   = array();
		$out    = array();
		foreach ( $ranked as $r ) {
			if ( isset( $seen[ $r['id'] ] ) ) {
				continue;
			}
			$seen[ $r['id'] ] = true;
			$out[]            = $r;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}
}
