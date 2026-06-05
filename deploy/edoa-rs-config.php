<?php
/**
 * MU-Plugin: EDOA Review Sync — Airtable credentials.
 *
 * Defines EDOA_AIRTABLE_PAT / EDOA_AIRTABLE_BASE_ID for the edoa-review-sync plugin.
 * Survives wp-config.php rewrites (Local regenerates wp-config) and keeps creds out of
 * the plugin repo. On this Local box the values are read from the sibling gmb-reviews/.env;
 * on prod/staging, define them via the host environment or replace this file's source.
 *
 * Load order: mu-plugins run before regular plugins, so the constants exist by the time
 * edoa-review-sync boots.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

( static function () {
	// Already defined (e.g. prod host env or wp-config)? Do nothing.
	if ( defined( 'EDOA_AIRTABLE_PAT' ) && defined( 'EDOA_AIRTABLE_BASE_ID' ) ) {
		return;
	}

	// Local dev: read from the gmb-reviews app .env (website/plugins/gmb-reviews/.env).
	// ABSPATH = .../website/local-env/app/public/ → website is 3 levels up.
	$env_path = dirname( ABSPATH, 3 ) . '/plugins/gmb-reviews/.env';
	if ( ! is_readable( $env_path ) ) {
		return;
	}

	foreach ( file( $env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		if ( '' === $line || '#' === $line[0] || ! str_contains( $line, '=' ) ) {
			continue;
		}
		list( $k, $v ) = array_map( 'trim', explode( '=', $line, 2 ) );
		$v = trim( $v, "\"'" );
		if ( 'AIRTABLE_PAT' === $k && ! defined( 'EDOA_AIRTABLE_PAT' ) ) {
			define( 'EDOA_AIRTABLE_PAT', $v );
		}
		if ( 'AIRTABLE_BASE_ID' === $k && ! defined( 'EDOA_AIRTABLE_BASE_ID' ) ) {
			define( 'EDOA_AIRTABLE_BASE_ID', $v );
		}
	}
} )();
