<?php
// includes/class-tag-service-map.php
if ( ! defined( 'ABSPATH' ) && ! defined( 'EDOA_RS_TEST' ) ) {
	// allow standalone test include
}

class EDOA_Tag_Service_Map {

	const MAP = array(
		'emergency'   => array( 'emergency-pain-relief', 'emergency-dental-exam' ),
		'staff'       => array( 'comprehensive-dental-exam' ),
		'wait_time'   => array( 'emergency-pain-relief' ),
		'cleanliness' => array( 'comprehensive-dental-exam' ),
	);

	/**
	 * @param string[] $tags
	 * @return string[] deduped service slugs (order: first-seen)
	 */
	public static function slugs_for_tags( array $tags ): array {
		$out = array();
		foreach ( $tags as $tag ) {
			if ( isset( self::MAP[ $tag ] ) ) {
				foreach ( self::MAP[ $tag ] as $slug ) {
					if ( ! in_array( $slug, $out, true ) ) {
						$out[] = $slug;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Resolve service slugs to post IDs. Builds a slug=>ID map once per request.
	 * @param string[] $slugs
	 * @return int[]
	 */
	public static function ids_for_slugs( array $slugs ): array {
		static $cache = null;
		if ( null === $cache ) {
			$cache = array();
			$posts = get_posts( array(
				'post_type'      => 'service',
				'post_status'    => 'publish',
				'numberposts'    => -1,
				'fields'         => 'ids',
			) );
			foreach ( $posts as $pid ) {
				$cache[ get_post_field( 'post_name', $pid ) ] = (int) $pid;
			}
		}
		$ids = array();
		foreach ( $slugs as $slug ) {
			if ( isset( $cache[ $slug ] ) ) {
				$ids[] = $cache[ $slug ];
			}
		}
		return $ids;
	}
}
