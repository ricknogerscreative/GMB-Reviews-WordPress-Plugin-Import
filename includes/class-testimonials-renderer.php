<?php
// includes/class-testimonials-renderer.php

class EDOA_Testimonials_Renderer {

	/** WP service-post slugs that collide with the canonical Airtable service slug. */
	const SERVICE_SLUG_ALIASES = array(
		'root-canals-2' => 'root-canals',
	);

	/**
	 * Resolve args → testimonial post IDs.
	 *
	 * @param array $args source, topic, service, location, count, orderby, ids
	 * @return int[]
	 */
	public static function get( array $args = array() ): array {
		$a = array_merge( array(
			'source'   => 'auto',
			'topic'    => '',
			'service'  => '',
			'location' => 0,
			'count'    => 3,
			'orderby'  => '',
			'ids'      => array(),
			'placement' => '',
		), $args );

		// Pinned IDs path (manual override).
		if ( ! empty( $a['ids'] ) ) {
			$ids = array_map( 'intval', (array) $a['ids'] );
			$q   = new WP_Query( array(
				'post_type'      => 'testimonial',
				'post_status'    => 'publish',
				'posts_per_page' => (int) $a['count'],
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'no_found_rows'  => true,
				'fields'         => 'ids',
			) );
			return $q->posts;
		}

		$source = $a['source'];
		if ( 'auto' === $source ) {
			if ( is_singular( 'location' ) ) {
				$source        = 'location';
				$a['location'] = get_the_ID();
			} elseif ( is_singular( 'service' ) ) {
				$source       = 'service';
				$a['service'] = self::current_service_slug();
			} else {
				$source = 'best';
			}
		}

		$query = array(
			'post_type'      => 'testimonial',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $a['count'],
			'no_found_rows'  => true,
			'fields'         => 'ids',
		);

		switch ( $source ) {
			case 'recent':
				// No filter — all published testimonials, ordered by date below.
				break;
			case 'value':
				// No filter — all published testimonials, ordered by value score below.
				break;
			case 'best':
				$query['meta_query'] = array( array( 'key' => EDOA_Review_Sync::META_BEST_OVERALL, 'value' => 1 ) );
				break;
			case 'topic':
				$topics = array_filter( array_map( 'trim', explode( ',', (string) $a['topic'] ) ) );
				if ( empty( $topics ) ) {
					return array(); // source=topic with no usable topic → nothing to show
				}
				$query['tax_query'] = array( array(
					'taxonomy' => EDOA_RS_Taxonomies::TAX_TOPIC,
					'field'    => 'slug',
					'terms'    => $topics,
				) );
				break;
			case 'service':
				$slug = self::normalize_service_slug( (string) $a['service'] );
				if ( '' === $slug ) {
					return array(); // source=service with no usable slug → nothing to show
				}
				$query['tax_query'] = array( array(
					'taxonomy' => EDOA_RS_Taxonomies::TAX_SERVICE,
					'field'    => 'slug',
					'terms'    => array( $slug ),
				) );
				break;
			case 'location':
				$query['meta_query'] = array( array(
					'key'   => EDOA_Review_Sync::META_LOCATION_ID,
					'value' => (int) $a['location'],
				) );
				break;
			case 'placement':
				$placements = self::filter_terms( (string) $a['placement'], EDOA_RS_Taxonomies::PLACEMENTS );
				if ( empty( $placements ) ) {
					return self::get( array_merge( $a, array( 'source' => 'best', 'placement' => '' ) ) );
				}
				$query['tax_query'] = array( array(
					'taxonomy' => EDOA_RS_Taxonomies::TAX_PLACEMENT,
					'field'    => 'slug',
					'terms'    => $placements,
				) );
				break;
		}

		// Ordering: explicit orderby wins; else recent=date, everything else=value.
		$orderby = $a['orderby'] ?: ( 'recent' === $source ? 'date' : 'value' );
		switch ( $orderby ) {
			case 'date':
				$query['orderby'] = 'date';
				$query['order']   = 'DESC';
				break;
			case 'rand':
				$query['orderby'] = 'rand';
				break;
			case 'value':
			default:
				// NOTE: the meta_key join excludes posts lacking _edoa_value_score.
				// All Airtable-synced testimonials have it (set by EDOA_Review_Sync).
				$query['meta_key'] = EDOA_Review_Sync::META_VALUE_SCORE;
				$query['orderby']  = 'meta_value_num';
				$query['order']    = 'DESC';
				break;
		}

		$q = new WP_Query( $query );
		if ( 'placement' === $source && empty( $q->posts ) ) {
			return self::get( array_merge( $a, array( 'source' => 'best', 'placement' => '' ) ) );
		}
		return $q->posts;
	}

	/**
	 * Render testimonial cards as an HTML string.
	 *
	 * @param array $args get() args + heading, length (line-clamp lines, default 8)
	 */
	public static function render( array $args = array() ): string {
		$ids = self::get( $args );
		if ( ! $ids ) {
			return '';
		}
		self::enqueue_assets();

		$heading = isset( $args['heading'] ) ? (string) $args['heading'] : '';
		$length  = isset( $args['length'] ) ? (int) $args['length'] : 8;
		$cta_url = home_url( '/contact/' );

		ob_start();
		?>
		<section class="edoa-tt">
			<div class="edoa-tt__inner">
				<?php if ( $heading !== '' ) : ?>
					<div class="edoa-section-header">
						<h2 class="edoa-section-header__heading"><?php echo esc_html( $heading ); ?></h2>
					</div>
				<?php endif; ?>
				<div class="edoa-tt__grid">
					<?php foreach ( $ids as $id ) :
						$quote  = (string) get_post_meta( $id, 'testimonial_quote', true );
						$author = (string) get_post_meta( $id, 'testimonial_author', true );
						if ( '' === $author ) {
							$author = get_the_title( $id );
						}
						$rating   = (int) get_post_meta( $id, 'testimonial_rating', true );
						$raw_date = get_post_meta( $id, 'testimonial_date', true );
						$date     = $raw_date ? date_i18n( 'M j, Y', strtotime( (string) $raw_date ) ) : get_the_date( 'M j, Y', $id );
						if ( '' === $quote ) {
							continue;
						}
						?>
						<div class="edoa-tt__card">
							<?php if ( $rating > 0 ) : ?>
								<div class="edoa-tt__stars" aria-label="<?php echo esc_attr( $rating ); ?> out of 5 stars">
									<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
										<svg width="14" height="14" viewBox="0 0 24 24" fill="<?php echo esc_attr( $i <= $rating ? '#CC2229' : '#e5e5e5' ); ?>" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
									<?php endfor; ?>
								</div>
							<?php endif; ?>
							<blockquote class="edoa-tt__quote" style="--edoa-tt-lines: <?php echo esc_attr( $length ); ?>;">
								<p class="edoa-tt__text"><?php echo wp_kses_post( $quote ); ?></p>
							</blockquote>
							<button type="button" class="edoa-tt__more" hidden>Read more</button>
							<div class="edoa-tt__footer">
								<div class="edoa-tt__meta">
									<?php if ( $author !== '' ) : ?>
										<cite class="edoa-tt__author"><?php echo esc_html( $author ); ?></cite>
									<?php endif; ?>
									<?php if ( $date !== '' ) : ?>
										<span class="edoa-tt__date"><?php echo esc_html( $date ); ?></span>
									<?php endif; ?>
								</div>
								<a href="<?php echo esc_url( $cta_url ); ?>" class="edoa-btn edoa-btn--red edoa-btn--sm">Schedule</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** Shortcode: [edoa_testimonials source="recent" count="3" topic="" service="" location="" heading="" length="8" orderby=""] */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts( array(
			'source'   => 'auto',
			'topic'    => '',
			'service'  => '',
			'location' => 0,
			'count'    => 3,
			'heading'  => '',
			'length'   => 8,
			'orderby'  => '',
			'placement' => '',
		), $atts, 'edoa_testimonials' );
		return self::render( $atts );
	}

	private static function current_service_slug(): string {
		return self::normalize_service_slug( (string) get_post_field( 'post_name', get_the_ID() ) );
	}

	private static function normalize_service_slug( string $slug ): string {
		return self::SERVICE_SLUG_ALIASES[ $slug ] ?? $slug;
	}

	/**
	 * Validate a comma-separated term list against an allowed vocabulary.
	 * Pure — no WP calls. Trims, drops empties + unknowns, de-dupes, preserves order.
	 *
	 * @param string   $csv     e.g. "homepage, book"
	 * @param string[] $allowed controlled vocabulary
	 * @return string[]
	 */
	public static function filter_terms( string $csv, array $allowed ): array {
		$allow = array_flip( $allowed );
		$out   = array();
		foreach ( explode( ',', $csv ) as $t ) {
			$t = trim( $t );
			if ( '' !== $t && isset( $allow[ $t ] ) && ! in_array( $t, $out, true ) ) {
				$out[] = $t;
			}
		}
		return $out;
	}

	public static function register_assets(): void {
		$base = plugins_url( 'assets/', EDOA_RS_PLUGIN_FILE );
		wp_register_style( 'edoa-tt', $base . 'testimonials.css', array(), EDOA_RS_VERSION );
		wp_register_script( 'edoa-tt', $base . 'testimonials.js', array(), EDOA_RS_VERSION, true );
	}

	private static function enqueue_assets(): void {
		if ( ! wp_style_is( 'edoa-tt', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'edoa-tt' );
		wp_enqueue_script( 'edoa-tt' );
	}
}
