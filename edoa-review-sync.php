<?php
/**
 * Plugin Name: EDOA Review Sync
 * Description: Nightly sync of curated 4-5 star text reviews from Airtable into the testimonial CPT.
 * Version: 1.0.0
 * Author: EDOA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EDOA_RS_VERSION', '1.0.0' );
define( 'EDOA_RS_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDOA_RS_CRON_HOOK', 'edoa_rs_sync' );
define( 'EDOA_RS_PLUGIN_FILE', __FILE__ );

add_filter( 'cron_schedules', function ( $schedules ) {
	$schedules['edoa_45days'] = array(
		'interval' => 45 * DAY_IN_SECONDS,
		'display'  => 'Every 45 Days',
	);
	return $schedules;
} );

require_once EDOA_RS_DIR . 'includes/class-airtable-client.php';
require_once EDOA_RS_DIR . 'includes/class-location-matcher.php';
require_once EDOA_RS_DIR . 'includes/class-review-selector.php';
require_once EDOA_RS_DIR . 'includes/class-review-sync.php';
require_once EDOA_RS_DIR . 'includes/class-admin-page.php';
require_once EDOA_RS_DIR . 'includes/class-taxonomies.php';
require_once EDOA_RS_DIR . 'includes/class-testimonials-renderer.php';

// Cron schedule on activation.
register_activation_hook( __FILE__, function () {
	// Migrate off the pre-v2.1 daily schedule if present.
	wp_clear_scheduled_hook( 'edoa_rs_daily_sync' );
	if ( ! wp_next_scheduled( EDOA_RS_CRON_HOOK ) ) {
		wp_schedule_event( strtotime( 'tomorrow 3:00am' ), 'edoa_45days', EDOA_RS_CRON_HOOK );
	}
	EDOA_RS_Taxonomies::seed_terms();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	$ts = wp_next_scheduled( EDOA_RS_CRON_HOOK );
	if ( $ts ) {
		wp_unschedule_event( $ts, EDOA_RS_CRON_HOOK );
	}
} );

// Cron callback.
add_action( EDOA_RS_CRON_HOOK, function () {
	( new EDOA_Review_Sync() )->run();
} );

add_action( 'init', array( 'EDOA_RS_Taxonomies', 'register' ) );

// One-time cron migration for installs already active before the v2.1 rename.
add_action( 'init', function () {
	if ( wp_next_scheduled( 'edoa_rs_daily_sync' ) ) {
		wp_clear_scheduled_hook( 'edoa_rs_daily_sync' );
	}
	if ( ! wp_next_scheduled( EDOA_RS_CRON_HOOK ) ) {
		wp_schedule_event( strtotime( 'tomorrow 3:00am' ), 'edoa_45days', EDOA_RS_CRON_HOOK );
	}
} );

// Admin page + manual trigger.
add_action( 'init', function () {
	if ( is_admin() ) {
		( new EDOA_RS_Admin_Page() )->init();
	}
} );

add_shortcode( 'edoa_testimonials', array( 'EDOA_Testimonials_Renderer', 'shortcode' ) );
add_action( 'wp_enqueue_scripts', array( 'EDOA_Testimonials_Renderer', 'register_assets' ) );

if ( ! function_exists( 'edoa_testimonials_render' ) ) {
	/** @param array $args see EDOA_Testimonials_Renderer::render */
	function edoa_testimonials_render( array $args = array() ): string {
		return class_exists( 'EDOA_Testimonials_Renderer' ) ? EDOA_Testimonials_Renderer::render( $args ) : '';
	}
}
if ( ! function_exists( 'edoa_testimonials_get' ) ) {
	/** @param array $args see EDOA_Testimonials_Renderer::get @return int[] */
	function edoa_testimonials_get( array $args = array() ): array {
		return class_exists( 'EDOA_Testimonials_Renderer' ) ? EDOA_Testimonials_Renderer::get( $args ) : array();
	}
}
