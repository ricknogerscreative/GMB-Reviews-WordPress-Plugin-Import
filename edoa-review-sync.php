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
define( 'EDOA_RS_CRON_HOOK', 'edoa_rs_daily_sync' );

require_once EDOA_RS_DIR . 'includes/class-airtable-client.php';
require_once EDOA_RS_DIR . 'includes/class-location-matcher.php';
require_once EDOA_RS_DIR . 'includes/class-review-selector.php';
require_once EDOA_RS_DIR . 'includes/class-review-sync.php';
require_once EDOA_RS_DIR . 'includes/class-admin-page.php';
require_once EDOA_RS_DIR . 'includes/class-taxonomies.php';

// Cron schedule on activation.
register_activation_hook( __FILE__, function () {
	if ( ! wp_next_scheduled( EDOA_RS_CRON_HOOK ) ) {
		// 3am server time today/tomorrow.
		$ts = strtotime( 'tomorrow 3:00am' );
		wp_schedule_event( $ts, 'daily', EDOA_RS_CRON_HOOK );
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

// Admin page + manual trigger.
add_action( 'init', function () {
	if ( is_admin() ) {
		( new EDOA_RS_Admin_Page() )->init();
	}
} );
