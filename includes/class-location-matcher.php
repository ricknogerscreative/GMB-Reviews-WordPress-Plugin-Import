<?php
// includes/class-location-matcher.php

class EDOA_Location_Matcher {

	/** @var array<string,int>|null  "city|state" (lowercased) => post ID */
	private ?array $map = null;

	private function build(): void {
		$this->map = array();
		$ids = get_posts( array(
			'post_type'   => 'location',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
		) );
		foreach ( $ids as $pid ) {
			$city  = (string) get_post_meta( $pid, 'loc_city', true );
			$state = (string) get_post_meta( $pid, 'loc_state', true );
			$key   = $this->key( $city, $state );
			if ( '' !== $key ) {
				$this->map[ $key ] = (int) $pid;
			}
		}
	}

	private function key( string $city, string $state ): string {
		$city  = strtolower( trim( $city ) );
		$state = strtolower( trim( $state ) );
		if ( '' === $city || '' === $state ) {
			return '';
		}
		return $city . '|' . $state;
	}

	/** @return int 0 if no match. */
	public function match( string $city, string $state ): int {
		if ( null === $this->map ) {
			$this->build();
		}
		return $this->map[ $this->key( $city, $state ) ] ?? 0;
	}
}
