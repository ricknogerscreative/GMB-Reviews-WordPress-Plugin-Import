<?php
// includes/class-taxonomies.php

class EDOA_RS_Taxonomies {

	const TAX_TOPIC     = 'edoa_topic';
	const TAX_SERVICE   = 'edoa_service';
	const TAX_PLACEMENT = 'edoa_placement';

	/** Controlled vocabularies — must match Airtable Topics/Services option names exactly. */
	const TOPICS = array(
		'financing', 'insurance', 'emergency', 'pain_relief',
		'staff_quality', 'wait_time', 'cleanliness', 'results',
	);
	const SERVICES = array(
		'emergency', 'emergency-pain-relief', 'comprehensive-exam', 'dental-cleanings',
		'preventative', 'restorative', 'cosmetic', 'root-canals', 'tooth-extractions',
		'surgical-tooth-extractions', 'wisdom-teeth', 'dental-fillings', 'dental-crowns',
		'dental-bridges', 'dental-bonding', 'dental-implants', 'dentures', 'denture-repair',
		'teeth-whitening', 'porcelain-veneers', 'dental-trauma', 'abscess-tooth',
		'broken-chipped-teeth', 'tooth-reimplantation', 'swollen-jaw', 'dental-x-rays',
	);

	/** Editorial placement buckets for generic pages. Sync NEVER sets these — hand-curated in WP admin. */
	const PLACEMENTS = array( 'homepage', 'landing', 'book', 'general' );

	/** Register both taxonomies on the testimonial CPT. Hooked to 'init'. */
	public static function register(): void {
		$common = array(
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'hierarchical'      => false,
			'query_var'         => false,
			'rewrite'           => false,
		);
		register_taxonomy( self::TAX_TOPIC, 'testimonial', array_merge( $common, array(
			'label' => 'Review Topics',
		) ) );
		register_taxonomy( self::TAX_SERVICE, 'testimonial', array_merge( $common, array(
			'label' => 'Review Services',
		) ) );
		register_taxonomy( self::TAX_PLACEMENT, 'testimonial', array_merge( $common, array(
			'label' => 'Review Placement',
		) ) );
	}

	/** Seed the known terms. Idempotent — safe to call on every activation. */
	public static function seed_terms(): void {
		self::register();
		foreach ( self::TOPICS as $slug ) {
			if ( ! term_exists( $slug, self::TAX_TOPIC ) ) {
				wp_insert_term( $slug, self::TAX_TOPIC, array( 'slug' => $slug ) );
			}
		}
		foreach ( self::SERVICES as $slug ) {
			if ( ! term_exists( $slug, self::TAX_SERVICE ) ) {
				wp_insert_term( $slug, self::TAX_SERVICE, array( 'slug' => $slug ) );
			}
		}
		foreach ( self::PLACEMENTS as $slug ) {
			if ( ! term_exists( $slug, self::TAX_PLACEMENT ) ) {
				wp_insert_term( $slug, self::TAX_PLACEMENT, array( 'slug' => $slug ) );
			}
		}
	}
}
