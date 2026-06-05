<?php
// includes/class-review-sync.php

class EDOA_Review_Sync {

	const META_AIRTABLE_ID    = '_edoa_airtable_id';
	const META_LOCATION_ID    = '_edoa_location_id';
	const META_LOCATION_NAME  = '_edoa_location_name';
	const META_SERVICE_IDS    = '_edoa_service_ids';
	const META_TAGS           = '_edoa_tags';
	const META_BEST_OVERALL   = '_edoa_is_best_overall';
	const PER_LOCATION        = 15;
	const BEST_OVERALL        = 30;

	/** Trim whitespace from Airtable field keys (some columns have stray leading/trailing spaces). */
	private static function trim_keys( array $fields ): array {
		$out = array();
		foreach ( $fields as $k => $v ) {
			$out[ trim( $k ) ] = $v;
		}
		return $out;
	}

	public function run(): array {
		$client = new EDOA_Airtable_Client();
		if ( ! $client->is_configured() ) {
			return array( 'ok' => false, 'message' => 'Airtable not configured (EDOA_AIRTABLE_PAT / EDOA_AIRTABLE_BASE_ID).' );
		}

		// 1. Build Airtable location-record-id => [city,state,name]
		$locRecords = $client->fetch_all( 'Locations' );
		$locById    = array();
		foreach ( $locRecords as $rec ) {
			$f = self::trim_keys( $rec['fields'] ?? array() );
			$locById[ $rec['id'] ] = array(
				'city'  => $f['City'] ?? '',
				'state' => $f['State'] ?? '',
				'name'  => trim( ( $f['City'] ?? '' ) . ', ' . ( $f['State'] ?? '' ), ', ' ),
			);
		}

		// 2. Fetch reviews, normalize to flat shape for the ranker.
		$raw = $client->fetch_all( 'Reviews' );
		$reviews = array();
		foreach ( $raw as $rec ) {
			$f       = self::trim_keys( $rec['fields'] ?? array() );
			$linkIds = $f['Location'] ?? array();
			$locKey  = is_array( $linkIds ) && $linkIds ? $linkIds[0] : '';
			$reviews[] = array(
				'id'           => (string) ( $f['Review ID'] ?? $rec['id'] ),
				'stars'        => (int) ( $f['Stars'] ?? 0 ),
				'text'         => (string) ( $f['Review Text'] ?? '' ),
				'author'       => (string) ( $f['Reviewer'] ?? '' ),
				'date'         => (string) ( $f['Review Date'] ?? '' ),
				'tags'         => is_array( $f['Tags'] ?? null ) ? $f['Tags'] : array(),
				'location_key' => $locKey,
			);
		}

		// 3. Rank.
		$qualifying = EDOA_Review_Ranker::filter_qualifying( $reviews );
		$perLoc     = EDOA_Review_Ranker::top_per_location( $qualifying, self::PER_LOCATION );
		$best       = EDOA_Review_Ranker::best_overall( $qualifying, self::BEST_OVERALL );

		$bestIds = array();
		foreach ( $best as $r ) {
			$bestIds[ $r['id'] ] = true;
		}

		// 4. Flatten the final keep-set (union of per-location top15 + best-overall).
		$keep = array();
		foreach ( $perLoc as $list ) {
			foreach ( $list as $r ) {
				$keep[ $r['id'] ] = $r;
			}
		}
		foreach ( $best as $r ) {
			$keep[ $r['id'] ] = $r;
		}

		// 5. Upsert.
		$matcher    = new EDOA_Location_Matcher();
		$syncedIds  = array();
		foreach ( $keep as $r ) {
			$loc        = $locById[ $r['location_key'] ] ?? array( 'city' => '', 'state' => '', 'name' => '' );
			$locPostId  = $matcher->match( $loc['city'], $loc['state'] );
			$serviceIds = EDOA_Tag_Service_Map::ids_for_slugs(
				EDOA_Tag_Service_Map::slugs_for_tags( $r['tags'] )
			);
			$this->upsert( $r, $locPostId, $loc['name'], $serviceIds, isset( $bestIds[ $r['id'] ] ) );
			$syncedIds[] = $r['id'];
		}

		// 6. Cleanup stale.
		$deleted = $this->cleanup( $syncedIds );

		return array(
			'ok'      => true,
			'kept'    => count( $syncedIds ),
			'deleted' => $deleted,
			'message' => sprintf( 'Synced %d testimonials, removed %d stale.', count( $syncedIds ), $deleted ),
		);
	}

	private function upsert( array $r, int $locPostId, string $locName, array $serviceIds, bool $isBest ): void {
		$existing = get_posts( array(
			'post_type'   => 'testimonial',
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => self::META_AIRTABLE_ID,
			'meta_value'  => $r['id'],
		) );
		$postId = $existing ? (int) $existing[0] : 0;

		$postarr = array(
			'post_type'   => 'testimonial',
			'post_status' => 'publish',
			'post_title'  => $r['author'] !== '' ? $r['author'] : ( 'Review ' . $r['id'] ),
		);
		if ( $postId ) {
			$postarr['ID'] = $postId;
			wp_update_post( $postarr );
		} else {
			$postId = (int) wp_insert_post( $postarr );
		}
		if ( ! $postId ) {
			return;
		}

		// ACF/display fields (reuse existing testimonial ACF field names).
		update_post_meta( $postId, 'testimonial_quote', $r['text'] );
		update_post_meta( $postId, 'testimonial_author', $r['author'] );
		update_post_meta( $postId, 'testimonial_rating', $r['stars'] );
		if ( $locPostId ) {
			update_post_meta( $postId, 'testimonial_location', $locPostId );
		}

		// Sync meta.
		update_post_meta( $postId, self::META_AIRTABLE_ID, $r['id'] );
		update_post_meta( $postId, self::META_LOCATION_ID, $locPostId );
		update_post_meta( $postId, self::META_LOCATION_NAME, $locName );
		update_post_meta( $postId, self::META_SERVICE_IDS, $serviceIds );
		update_post_meta( $postId, self::META_TAGS, $r['tags'] );
		update_post_meta( $postId, self::META_BEST_OVERALL, $isBest ? 1 : 0 );
	}

	/** Delete testimonial posts (origin=sync) whose Airtable ID is no longer kept. */
	private function cleanup( array $keepIds ): int {
		$keep = array_fill_keys( $keepIds, true );
		$all  = get_posts( array(
			'post_type'   => 'testimonial',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_query'  => array(
				array( 'key' => self::META_AIRTABLE_ID, 'compare' => 'EXISTS' ),
			),
		) );
		$deleted = 0;
		foreach ( $all as $pid ) {
			$aid = (string) get_post_meta( $pid, self::META_AIRTABLE_ID, true );
			if ( $aid !== '' && ! isset( $keep[ $aid ] ) ) {
				wp_delete_post( $pid, true );
				$deleted++;
			}
		}
		return $deleted;
	}
}
