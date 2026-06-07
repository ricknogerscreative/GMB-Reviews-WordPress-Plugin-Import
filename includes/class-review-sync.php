<?php
// includes/class-review-sync.php

class EDOA_Review_Sync {

	const META_AIRTABLE_ID   = '_edoa_airtable_id';
	const META_LOCATION_ID   = '_edoa_location_id';
	const META_LOCATION_NAME = '_edoa_location_name';
	const META_VALUE_SCORE   = '_edoa_value_score';
	const META_BEST_OVERALL  = '_edoa_is_best_overall';

	// Legacy meta removed by v2 — stripped from existing posts on upsert.
	const LEGACY_META = array( '_edoa_service_ids', '_edoa_tags' );

	/** Cap on Airtable rows fetched (sorted by Value Score DESC — top-value first). */
	const FETCH_MAX = 5000;

	/** Trim whitespace from Airtable field keys (defensive — restructure fixed stray spaces). */
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

		// 1. Airtable location-record-id => [city,state,name].
		$locById = array();
		foreach ( $client->fetch_all( 'Locations' ) as $rec ) {
			$f = self::trim_keys( $rec['fields'] ?? array() );
			$locById[ $rec['id'] ] = array(
				'city'  => $f['City'] ?? '',
				'state' => $f['State'] ?? '',
				'name'  => trim( ( $f['City'] ?? '' ) . ', ' . ( $f['State'] ?? '' ), ', ' ),
			);
		}

		// 2. Fetch Display-Ready reviews, sorted by Value Score DESC, capped.
		$params = array(
			'filterByFormula' => '{Display Ready}',
			'maxRecords'      => self::FETCH_MAX,
			'sort'            => array( array( 'field' => 'Value Score', 'direction' => 'desc' ) ),
		);
		$raw     = $client->fetch_all( 'Reviews', $params );
		$reviews = array();
		foreach ( $raw as $rec ) {
			$f       = self::trim_keys( $rec['fields'] ?? array() );
			$linkIds = $f['Location'] ?? array();
			$locKey  = is_array( $linkIds ) && $linkIds ? $linkIds[0] : '';
			$reviews[] = array(
				'id'           => (string) ( $f['Review ID'] ?? $rec['id'] ),
				'stars'        => (int) ( $f['Stars'] ?? 0 ),
				'value'        => (float) ( $f['Value Score'] ?? 0 ),
				'date'         => (string) ( $f['Review Date'] ?? '' ),
				'display_text' => (string) ( $f['Display Text'] ?? '' ),
				'reviewer'     => (string) ( $f['Reviewer Display'] ?? '' ),
				'topics'       => array_values( array_filter( (array) ( $f['Topics'] ?? array() ) ) ),
				'services'     => array_values( array_filter( (array) ( $f['Services'] ?? array() ) ) ),
				'location_key' => $locKey,
			);
		}

		// 3. Quota selection (selector needs id,value,date,location_key,topics,services).
		$sel  = EDOA_Review_Selector::select( $reviews );
		$keep = $sel['keep'];
		$best = $sel['best_ids'];

		// Index full review rows by id so upsert has display_text/reviewer/stars.
		$byId = array();
		foreach ( $reviews as $r ) {
			$byId[ $r['id'] ] = $r;
		}

		// Pre-load existing synced posts once (airtable_id => post_id) to avoid an N+1 lookup per upsert.
		$postIdByAirtableId = array();
		$existingIds = get_posts( array(
			'post_type'   => 'testimonial',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => self::META_AIRTABLE_ID,
		) );
		foreach ( $existingIds as $pid ) {
			$aid = (string) get_post_meta( $pid, self::META_AIRTABLE_ID, true );
			if ( '' !== $aid ) {
				$postIdByAirtableId[ $aid ] = (int) $pid;
			}
		}

		// 4. Upsert union.
		$matcher   = new EDOA_Location_Matcher();
		$syncedIds = array();
		foreach ( $keep as $id => $_kept ) {
			$r         = $byId[ $id ];
			$loc       = $locById[ $r['location_key'] ] ?? array( 'city' => '', 'state' => '', 'name' => '' );
			$locPostId = $matcher->match( $loc['city'], $loc['state'] );
			$this->upsert( $r, $locPostId, $loc['name'], isset( $best[ $id ] ), $postIdByAirtableId[ $id ] ?? 0 );
			$syncedIds[] = $id;
		}

		// 5. Cleanup stale.
		$deleted = $this->cleanup( $syncedIds, $postIdByAirtableId );

		return array(
			'ok'      => true,
			'kept'    => count( $syncedIds ),
			'deleted' => $deleted,
			'message' => sprintf( 'Synced %d testimonials, removed %d stale.', count( $syncedIds ), $deleted ),
		);
	}

	private function upsert( array $r, int $locPostId, string $locName, bool $isBest, int $postId ): void {

		$postarr = array(
			'post_type'   => 'testimonial',
			'post_status' => 'publish',
			'post_title'  => $r['reviewer'] !== '' ? $r['reviewer'] : ( 'Review ' . $r['id'] ),
		);
		// Use Review Date as post_date so orderby=date reflects recency (indexed, no meta sort).
		if ( $r['date'] !== '' ) {
			$ts = strtotime( $r['date'] );
			if ( $ts ) {
				$ts = min( $ts, time() ); // never future-date a published testimonial (would become status 'future')
				$postarr['post_date']     = gmdate( 'Y-m-d H:i:s', $ts );
				$postarr['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}
		if ( $postId ) {
			$postarr['ID'] = $postId;
			wp_update_post( $postarr );
		} else {
			$postId = (int) wp_insert_post( $postarr );
		}
		if ( ! $postId ) {
			return;
		}

		// Display fields (reuse existing testimonial ACF field names).
		update_post_meta( $postId, 'testimonial_quote', $r['display_text'] );
		update_post_meta( $postId, 'testimonial_author', $r['reviewer'] );
		update_post_meta( $postId, 'testimonial_rating', $r['stars'] );
		if ( $locPostId ) {
			update_post_meta( $postId, 'testimonial_location', $locPostId );
		}

		// Sync meta.
		update_post_meta( $postId, self::META_AIRTABLE_ID, $r['id'] );
		update_post_meta( $postId, self::META_LOCATION_ID, $locPostId );
		update_post_meta( $postId, self::META_LOCATION_NAME, $locName );
		update_post_meta( $postId, self::META_VALUE_SCORE, $r['value'] );
		update_post_meta( $postId, self::META_BEST_OVERALL, $isBest ? 1 : 0 );

		// Taxonomies — restrict to the known controlled vocabulary so an unexpected
		// Airtable value can't silently create an orphan term.
		$allowed_topics   = array_flip( EDOA_RS_Taxonomies::TOPICS );
		$allowed_services = array_flip( EDOA_RS_Taxonomies::SERVICES );
		$topics   = array_values( array_filter( $r['topics'], static function ( $t ) use ( $allowed_topics ) { return isset( $allowed_topics[ $t ] ); } ) );
		$services = array_values( array_filter( $r['services'], static function ( $s ) use ( $allowed_services ) { return isset( $allowed_services[ $s ] ); } ) );
		wp_set_object_terms( $postId, $topics, EDOA_RS_Taxonomies::TAX_TOPIC, false );
		wp_set_object_terms( $postId, $services, EDOA_RS_Taxonomies::TAX_SERVICE, false );

		// Strip retired legacy meta from previously-synced posts.
		foreach ( self::LEGACY_META as $mk ) {
			delete_post_meta( $postId, $mk );
		}
	}

	/** Delete previously-synced testimonial posts whose Airtable ID is no longer kept. */
	private function cleanup( array $keepIds, array $postIdByAirtableId ): int {
		$keep    = array_fill_keys( $keepIds, true );
		$deleted = 0;
		foreach ( $postIdByAirtableId as $aid => $pid ) {
			if ( ! isset( $keep[ $aid ] ) ) {
				wp_delete_post( $pid, true );
				$deleted++;
			}
		}
		return $deleted;
	}
}
