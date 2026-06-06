<?php
// includes/class-review-selector.php

class EDOA_Review_Selector {

	const DEFAULT_QUOTAS = array(
		'per_location'         => 15,
		'per_location_topic'   => 5,
		'per_location_service' => 5,
		'per_topic'            => 10,
		'per_service'          => 10,
		'best_overall'         => 30,
	);

	/**
	 * Select a curated union of reviews across per-context quota buckets, ranked by Value Score.
	 * Input MUST already be limited to Display-Ready reviews.
	 *
	 * @param array $reviews normalized reviews (id,value,date,location_key,topics[],services[])
	 * @param array $quotas  bucket limits (see DEFAULT_QUOTAS)
	 * @return array{keep: array<string,array>, best_ids: array<string,bool>}
	 */
	public static function select( array $reviews, array $quotas = array() ): array {
		$q = array_merge( self::DEFAULT_QUOTAS, $quotas );

		// Filter out reviews without a usable id — they cannot be keyed safely.
		$reviews = array_values( array_filter( $reviews, static function ( $r ) {
			return isset( $r['id'] ) && '' !== (string) $r['id'];
		} ) );

		// Rank: value DESC, then date DESC, then id ASC (fully deterministic).
		usort( $reviews, static function ( $a, $b ) {
			if ( (float) $a['value'] !== (float) $b['value'] ) {
				return (float) $b['value'] <=> (float) $a['value'];
			}
			if ( $a['date'] !== $b['date'] ) {
				return strcmp( (string) $b['date'], (string) $a['date'] );
			}
			return strcmp( (string) $a['id'], (string) $b['id'] );
		} );

		$counts   = array();   // bucketKey => count
		$limits   = array();   // bucketKey => limit
		$keep     = array();
		$best_ids = array();

		foreach ( $reviews as $r ) {
			$buckets = self::buckets_for( $r, $q, $limits ); // also populates $limits
			// Keep if at least one of its buckets still has room.
			$has_room = false;
			foreach ( $buckets as $bk ) {
				if ( ( $counts[ $bk ] ?? 0 ) < $limits[ $bk ] ) {
					$has_room = true;
					break;
				}
			}
			if ( ! $has_room ) {
				continue;
			}
			$keep[ $r['id'] ] = $r;
			foreach ( $buckets as $bk ) {
				if ( ( $counts[ $bk ] ?? 0 ) < $limits[ $bk ] ) {
					$counts[ $bk ] = ( $counts[ $bk ] ?? 0 ) + 1;
					if ( 'best' === $bk ) {
						$best_ids[ $r['id'] ] = true;
					}
				}
			}
		}

		return array( 'keep' => $keep, 'best_ids' => $best_ids );
	}

	/** Build the bucket keys this review belongs to and register their limits. */
	private static function buckets_for( array $r, array $q, array &$limits ): array {
		$loc     = (string) ( $r['location_key'] ?? '' );
		$buckets = array();

		$add = static function ( $key, $limit ) use ( &$buckets, &$limits ) {
			$buckets[]       = $key;
			$limits[ $key ]  = $limit;
		};

		$add( 'best', $q['best_overall'] );
		if ( '' !== $loc ) {
			$add( 'loc:' . $loc, $q['per_location'] );
		}
		foreach ( (array) ( $r['topics'] ?? array() ) as $t ) {
			$add( 'topic:' . $t, $q['per_topic'] );
			if ( '' !== $loc ) {
				$add( 'loctopic:' . $loc . '|' . $t, $q['per_location_topic'] );
			}
		}
		foreach ( (array) ( $r['services'] ?? array() ) as $s ) {
			$add( 'service:' . $s, $q['per_service'] );
			if ( '' !== $loc ) {
				$add( 'locsvc:' . $loc . '|' . $s, $q['per_location_service'] );
			}
		}
		return $buckets;
	}
}
